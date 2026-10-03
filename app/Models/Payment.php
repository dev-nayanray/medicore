<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Payment model — incoming (positive) + refunds (negative, is_refund=1).
 * Code generation is collision-safe. Duplicate prevention is done in
 * BillingService via transaction + row lock.
 */
final class Payment extends Model
{
    public const METHODS = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'insurance', 'other'];
    public const STATUSES = ['completed', 'pending', 'void'];

    protected static function table(): string
    {
        return 'payments';
    }

    /** Generate PAY-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "PAY-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(payment_code, "-", -1) AS UNSIGNED)), 0)
                 FROM payments WHERE payment_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM payments WHERE payment_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /** @return array<int, array<string, mixed>> */
    public static function forInvoice(int $invoiceId): array
    {
        return Database::query(
            'SELECT pay.*, u.name AS recorded_by_name FROM payments pay
             LEFT JOIN users u ON u.id = pay.recorded_by
             WHERE pay.invoice_id = ? ORDER BY pay.recorded_at DESC, pay.id DESC',
            [$invoiceId]
        );
    }

    /**
     * Daily collection for the last N days (chart data).
     *
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    public static function dailyCollection(int $days = 14): array
    {
        $rows = Database::query(
            "SELECT DATE(recorded_at) AS d, COALESCE(SUM(amount), 0) AS c
             FROM payments
             WHERE status = 'completed' AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(recorded_at)
             ORDER BY DATE(recorded_at)",
            [(string) $days]
        );
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['d']] = (float) $r['c'];
        }
        $labels = [];
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));
            $data[] = $byDate[$date] ?? 0.0;
        }
        return ['labels' => $labels, 'data' => $data];
    }

    /** @return array<string, mixed> payment method breakdown for a date range */
    public static function methodBreakdown(string $from, string $to): array
    {
        $rows = Database::query(
            "SELECT payment_method, COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE status = 'completed' AND amount > 0
               AND DATE(recorded_at) BETWEEN ? AND ?
             GROUP BY payment_method ORDER BY total DESC",
            [$from, $to]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['payment_method']] = (float) $r['total'];
        }
        return $out;
    }
}
