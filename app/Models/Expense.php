<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Expense model — hospital operational spending for P&L reports.
 */
final class Expense extends Model
{
    public const CATEGORIES = ['salaries', 'utilities', 'supplies', 'maintenance', 'equipment', 'rent', 'other'];
    public const METHODS = ['cash', 'card', 'bank_transfer', 'cheque', 'other'];

    protected static function table(): string
    {
        return 'expenses';
    }

    /** Generate EXP-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "EXP-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(expense_code, "-", -1) AS UNSIGNED)), 0)
                 FROM expenses WHERE expense_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM expenses WHERE expense_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /**
     * @param array{search?:string, category?:string, date_from?:string, date_to?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 15): array
    {
        $conditions = ['1=1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(expense_code LIKE ? OR description LIKE ? OR paid_to LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        if (!empty($filters['category']) && in_array($filters['category'], self::CATEGORIES, true)) {
            $conditions[] = 'category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'expense_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'expense_date <= ?';
            $params[] = $filters['date_to'];
        }

        $where = implode(' AND ', $conditions);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM expenses WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT e.*, u.name AS recorded_by_name FROM expenses e
             LEFT JOIN users u ON u.id = e.recorded_by
             WHERE {$where}
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** @return array<string, mixed> expense totals for a date range */
    public static function totalsForRange(string $from, string $to): array
    {
        $total = (float) Database::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?',
            [$from, $to]
        );
        $byCategory = [];
        $rows = Database::query(
            'SELECT category, COALESCE(SUM(amount), 0) AS total FROM expenses
             WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC',
            [$from, $to]
        );
        foreach ($rows as $r) {
            $byCategory[$r['category']] = (float) $r['total'];
        }
        return ['total' => $total, 'by_category' => $byCategory];
    }
}
