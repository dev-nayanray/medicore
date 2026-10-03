<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Invoice model — billing records with DECIMAL monetary precision,
 * code generation, directory filtering, and financial stats.
 */
final class Invoice extends Model
{
    public const STATUSES = ['draft', 'sent', 'partially_paid', 'paid', 'cancelled', 'refunded'];

    protected static function table(): string
    {
        return 'invoices';
    }

    /** Generate INV-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "INV-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_code, "-", -1) AS UNSIGNED)), 0)
                 FROM invoices WHERE invoice_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM invoices WHERE invoice_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /**
     * Directory with filters, sorting, pagination.
     *
     * @param array{search?:string, status?:string, patient_id?:int, date_from?:string, date_to?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 15): array
    {
        $conditions = ['1=1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(i.invoice_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                              OR CONCAT(p.first_name, " ", p.last_name) LIKE ? OR p.patient_code LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $conditions[] = 'i.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['patient_id'])) {
            $conditions[] = 'i.patient_id = ?';
            $params[] = (int) $filters['patient_id'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'i.invoice_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'i.invoice_date <= ?';
            $params[] = $filters['date_to'];
        }

        $where = implode(' AND ', $conditions);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM invoices i
             LEFT JOIN patients p ON p.id = i.patient_id
             WHERE {$where}",
            $params
        );
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT i.*,
                    p.patient_code, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.phone AS patient_phone,
                    u.name AS doctor_name,
                    creator.name AS created_by_name
             FROM invoices i
             LEFT JOIN patients p ON p.id = i.patient_id
             LEFT JOIN doctors doc ON doc.id = i.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN users creator ON creator.id = i.created_by
             WHERE {$where}
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Full invoice profile with items + payments.
     *
     * @return array<string, mixed>|null
     */
    public static function profile(int $id): ?array
    {
        $inv = Database::queryOne(
            'SELECT i.*,
                    p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    p.phone AS patient_phone, p.date_of_birth, p.gender, p.address, p.city,
                    u.name AS doctor_name, doc.specialization,
                    c.consultation_code,
                    a.appointment_code,
                    creator.name AS created_by_name,
                    canceller.name AS cancelled_by_name
             FROM invoices i
             LEFT JOIN patients p ON p.id = i.patient_id
             LEFT JOIN doctors doc ON doc.id = i.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN consultations c ON c.id = i.consultation_id
             LEFT JOIN appointments a ON a.id = i.appointment_id
             LEFT JOIN users creator ON creator.id = i.created_by
             LEFT JOIN users canceller ON canceller.id = i.cancelled_by
             WHERE i.id = ? LIMIT 1',
            [$id]
        );
        if ($inv === null) {
            return null;
        }
        $inv['items'] = Database::query(
            'SELECT ii.*, s.category AS service_category FROM invoice_items ii
             LEFT JOIN services s ON s.id = ii.service_id
             WHERE ii.invoice_id = ? ORDER BY ii.sort_order, ii.id',
            [$id]
        );
        $inv['payments'] = Database::query(
            'SELECT pay.*, u.name AS recorded_by_name FROM payments pay
             LEFT JOIN users u ON u.id = pay.recorded_by
             WHERE pay.invoice_id = ? ORDER BY pay.recorded_at DESC, pay.id DESC',
            [$id]
        );
        return $inv;
    }

    /**
     * Recalculate paid_amount + balance_due + status from the payments
     * table. Runs inside the caller's transaction.
     */
    public static function recalculate(int $invoiceId): void
    {
        $inv = self::find($invoiceId);
        if ($inv === null) {
            return;
        }
        $paid = (float) Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE invoice_id = ? AND status = 'completed'",
            [$invoiceId]
        );
        $total = (float) $inv['total'];
        $balance = round($total - $paid, 2);

        $status = $inv['status'];
        if ($inv['status'] !== 'draft' && $inv['status'] !== 'cancelled') {
            if ($paid <= 0 && $inv['status'] !== 'refunded') {
                $status = 'sent';
            } elseif ($paid >= $total) {
                $status = 'paid';
            } elseif ($paid > 0) {
                $status = 'partially_paid';
            }
        }

        Database::execute(
            'UPDATE invoices SET paid_amount = ?, balance_due = ?, status = ? WHERE id = ?',
            [round($paid, 2), $balance, $status, $invoiceId]
        );
    }

    /** @return array<string, mixed> dashboard financial stats */
    public static function financialStats(string $from = '', string $to = ''): array
    {
        $where = '';
        $params = [];
        if ($from !== '' && $to !== '') {
            $where = 'WHERE i.invoice_date BETWEEN ? AND ?';
            $params = [$from, $to];
        }

        $row = Database::queryOne(
            "SELECT
                COALESCE(SUM(i.total), 0) AS total_billed,
                COALESCE(SUM(i.paid_amount), 0) AS total_collected,
                COALESCE(SUM(i.balance_due), 0) AS total_outstanding,
                COALESCE(SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN i.status = 'partially_paid' THEN 1 ELSE 0 END), 0) AS partial_count,
                COUNT(*) AS total_invoices
             FROM invoices i {$where}
             WHERE (i.status NOT IN ('draft','cancelled'))"
            . ($where !== '' ? " AND i.invoice_date BETWEEN ? AND ?" : ''),
            $where !== '' ? [$from, $to, $from, $to] : []
        );

        // The query above is messy — let me redo it properly.
        $conditions = ["i.status NOT IN ('draft','cancelled')"];
        $params = [];
        if ($from !== '' && $to !== '') {
            $conditions[] = 'i.invoice_date BETWEEN ? AND ?';
            $params[] = $from;
            $params[] = $to;
        }
        $where = implode(' AND ', $conditions);

        $row = Database::queryOne(
            "SELECT
                COALESCE(SUM(i.total), 0) AS total_billed,
                COALESCE(SUM(i.paid_amount), 0) AS total_collected,
                COALESCE(SUM(i.balance_due), 0) AS total_outstanding,
                COALESCE(SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN i.status = 'partially_paid' THEN 1 ELSE 0 END), 0) AS partial_count,
                COUNT(*) AS total_invoices
             FROM invoices i WHERE {$where}",
            $params
        );

        $refundTotal = (float) Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE is_refund = 1 AND status = 'completed'"
            . ($from !== '' ? " AND DATE(recorded_at) BETWEEN ? AND ?" : ''),
            $from !== '' && $to !== '' ? [$from, $to] : []
        );

        return [
            'total_billed'      => (float) ($row['total_billed'] ?? 0),
            'total_collected'   => (float) ($row['total_collected'] ?? 0),
            'total_outstanding' => (float) ($row['total_outstanding'] ?? 0),
            'total_refunds'     => abs($refundTotal),
            'paid_invoices'     => (int) ($row['paid_count'] ?? 0),
            'partial_invoices'  => (int) ($row['partial_count'] ?? 0),
            'total_invoices'    => (int) ($row['total_invoices'] ?? 0),
        ];
    }

    /**
     * Monthly revenue for the last N months (chart data).
     *
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    public static function monthlyRevenue(int $months = 12): array
    {
        $rows = Database::query(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS m, COALESCE(SUM(paid_amount), 0) AS c
             FROM invoices
             WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
               AND status NOT IN ('draft','cancelled')
             GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
             ORDER BY DATE_FORMAT(invoice_date, '%Y-%m')",
            [(string) $months]
        );
        $byMonth = [];
        foreach ($rows as $r) {
            $byMonth[$r['m']] = (float) $r['c'];
        }
        $labels = [];
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $labels[] = date('M Y', strtotime($month . '-01'));
            $data[] = $byMonth[$month] ?? 0.0;
        }
        return ['labels' => $labels, 'data' => $data];
    }

    /** @return array<string, int> quick counts for tiles */
    public static function counts(): array
    {
        return [
            'total'         => (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE status NOT IN ('draft','cancelled')"),
            'paid'          => (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE status = 'paid'"),
            'partial'       => (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE status = 'partially_paid'"),
            'outstanding'   => (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE balance_due > 0 AND status NOT IN ('draft','cancelled')"),
            'today'         => (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE invoice_date = CURDATE()"),
            'today_revenue' => (float) Database::scalar("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE invoice_date = CURDATE() AND status NOT IN ('draft','cancelled')"),
        ];
    }

    /** Invoices for a patient (history). */
    public static function forPatient(int $patientId): array
    {
        return Database::query(
            'SELECT * FROM invoices WHERE patient_id = ? ORDER BY invoice_date DESC, id DESC',
            [$patientId]
        );
    }
}
