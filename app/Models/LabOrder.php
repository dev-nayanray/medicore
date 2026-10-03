<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Lab order — test orders linked to patients/consultations. Each order
 * has multiple order_items (individual tests) with independent lifecycles.
 */
final class LabOrder extends Model
{
    public const STATUSES = ['ordered', 'collected', 'resulted', 'verified', 'released', 'cancelled'];
    public const ITEM_STATUSES = ['ordered', 'collected', 'resulted', 'verified', 'released'];

    protected static function table(): string { return 'lab_orders'; }

    /** Generate LAB-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "LAB-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(order_code, "-", -1) AS UNSIGNED)), 0)
                 FROM lab_orders WHERE order_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM lab_orders WHERE order_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /** Full lab order profile with items + patient + doctor. */
    public static function profile(int $id): ?array
    {
        $order = Database::queryOne(
            'SELECT o.*, p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    p.phone, p.date_of_birth, p.gender,
                    u.name AS doctor_name, c.consultation_code,
                    inv.invoice_code,
                    creator.name AS created_by_name
             FROM lab_orders o
             LEFT JOIN patients p ON p.id = o.patient_id
             LEFT JOIN doctors doc ON doc.id = o.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN consultations c ON c.id = o.consultation_id
             LEFT JOIN invoices inv ON inv.id = o.invoice_id
             LEFT JOIN users creator ON creator.id = o.created_by
             WHERE o.id = ? LIMIT 1',
            [$id]
        );
        if ($order === null) return null;
        $order['items'] = Database::query(
            'SELECT oi.*, t.name AS test_name, t.category, t.sample_type, t.price,
                    col.name AS collected_by_name, res.name AS resulted_by_name,
                    ver.name AS verified_by_name, rel.name AS released_by_name
             FROM lab_order_items oi
             INNER JOIN lab_tests t ON t.id = oi.test_id
             LEFT JOIN users col ON col.id = oi.collected_by
             LEFT JOIN users res ON res.id = oi.resulted_by
             LEFT JOIN users ver ON ver.id = oi.verified_by
             LEFT JOIN users rel ON rel.id = oi.released_by
             WHERE oi.order_id = ? ORDER BY oi.id',
            [$id]
        );
        return $order;
    }

    /** @return array<int, array<string, mixed>> */
    public static function workQueue(string $status): array
    {
        if (!in_array($status, self::STATUSES, true)) $status = 'ordered';
        if ($status === 'all') {
            return Database::query(
                'SELECT o.*, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                        p.patient_code, u.name AS doctor_name
                 FROM lab_orders o
                 LEFT JOIN patients p ON p.id = o.patient_id
                 LEFT JOIN doctors doc ON doc.id = o.doctor_id
                 LEFT JOIN users u ON u.id = doc.user_id
                 WHERE o.status NOT IN ("cancelled","released")
                 ORDER BY o.created_at DESC LIMIT 50'
            );
        }
        return Database::query(
            "SELECT o.*, CONCAT(p.first_name, \" \", p.last_name) AS patient_name,
                    p.patient_code, u.name AS doctor_name
             FROM lab_orders o
             LEFT JOIN patients p ON p.id = o.patient_id
             LEFT JOIN doctors doc ON doc.id = o.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             WHERE o.status = ?
             ORDER BY o.created_at DESC LIMIT 50",
            [$status]
        );
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        return [
            'pending'   => (int) Database::scalar("SELECT COUNT(*) FROM lab_orders WHERE status IN ('ordered','collected','resulted')"),
            'verified'  => (int) Database::scalar("SELECT COUNT(*) FROM lab_orders WHERE status = 'verified'"),
            'released'  => (int) Database::scalar("SELECT COUNT(*) FROM lab_orders WHERE status = 'released'"),
            'today'     => (int) Database::scalar('SELECT COUNT(*) FROM lab_orders WHERE DATE(created_at) = CURDATE()'),
            'critical'  => (int) Database::scalar("SELECT COUNT(*) FROM lab_critical_alerts WHERE alert_status = 'pending'"),
        ];
    }
}
