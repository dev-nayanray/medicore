<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Appointment model — scheduling, queue, overlap detection, statistics,
 * and calendar queries. Code generation is collision-safe.
 */
final class Appointment extends Model
{
    private const SORTABLE = [
        'appointment_code' => 'a.appointment_code',
        'patient'          => 'patient_name',
        'doctor'           => 'doctor_name',
        'appointment_date' => 'a.appointment_date',
        'start_time'       => 'a.start_time',
        'status'           => 'a.status',
        'created_at'       => 'a.created_at',
    ];

    protected static function table(): string
    {
        return 'appointments';
    }

    public const STATUSES = ['pending', 'confirmed', 'checked_in', 'in_consultation', 'completed', 'cancelled', 'no_show'];
    public const ACTIVE_STATUSES = ['pending', 'confirmed', 'checked_in', 'in_consultation'];
    public const TYPES = ['scheduled', 'walk_in', 'follow_up', 'telemedicine'];

    /** Generate APT-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "APT-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(appointment_code, "-", -1) AS UNSIGNED)), 0)
                 FROM appointments WHERE appointment_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM appointments WHERE appointment_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /**
     * Next per-day queue token (per department) — e.g. "Q-042".
     */
    public static function nextQueueToken(string $date, ?int $departmentId = null): string
    {
        if ($departmentId !== null) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(queue_token, "-", -1) AS UNSIGNED)), 0)
                 FROM appointments WHERE appointment_date = ? AND department_id = ? AND queue_token IS NOT NULL',
                [$date, $departmentId]
            );
        } else {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(queue_token, "-", -1) AS UNSIGNED)), 0)
                 FROM appointments WHERE appointment_date = ? AND queue_token IS NOT NULL',
                [$date]
            );
        }
        return sprintf('Q-%03d', $max + 1);
    }

    /**
     * Range-overlap check for the same doctor on the same date.
     * Two ranges [s1,e1) and [s2,e2) overlap iff s1 < e2 AND s2 < e1.
     * Touching boundaries (end == start) are allowed — adjacent slots.
     *
     * @param int|null $ignoreId  Exclude this appointment (for reschedule / edit).
     */
    public static function overlaps(int $doctorId, string $date, string $start, string $end, ?int $ignoreId = null, ?int $ignoreCode = null): bool
    {
        $sql = "SELECT COUNT(*) FROM appointments
                WHERE doctor_id = ? AND appointment_date = ?
                  AND status NOT IN ('cancelled','no_show')
                  AND (start_time < ? AND end_time > ?)";
        $params = [$doctorId, $date, $end, $start];
        if ($ignoreId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreId;
        }
        if ($ignoreCode !== null) {
            $sql .= ' AND appointment_code != ?';
            $params[] = $ignoreCode;
        }
        return (int) Database::scalar($sql, $params) > 0;
    }

    /**
     * Directory with filters, sorting, pagination.
     *
     * @param array{search?:string, status?:string, type?:string, doctor_id?:int, department_id?:int, date_from?:string, date_to?:string, sort?:string, dir?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 15): array
    {
        $conditions = ['1=1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(a.appointment_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                              OR CONCAT(p.first_name, " ", p.last_name) LIKE ? OR p.patient_code LIKE ?
                              OR u.name LIKE ? OR a.queue_token LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $conditions[] = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['type']) && in_array($filters['type'], self::TYPES, true)) {
            $conditions[] = 'a.appointment_type = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['doctor_id'])) {
            $conditions[] = 'a.doctor_id = ?';
            $params[] = (int) $filters['doctor_id'];
        }

        if (!empty($filters['department_id'])) {
            $conditions[] = 'a.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'a.appointment_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'a.appointment_date <= ?';
            $params[] = $filters['date_to'];
        }

        $where = implode(' AND ', $conditions);
        $orderBy = self::SORTABLE[$filters['sort'] ?? 'appointment_date'] ?? self::SORTABLE['appointment_date'];
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        if (in_array($orderBy, ['patient_name', 'doctor_name'], true)) {
            $dir = 'ASC';
        }

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN doctors doc ON doc.id = a.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             WHERE {$where}",
            $params
        );
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT a.*,
                    p.patient_code, CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    p.phone AS patient_phone, p.id AS patient_id,
                    u.name AS doctor_name, doc.doctor_code, doc.specialization,
                    d.name AS department_name,
                    creator.name AS created_by_name
             FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN doctors doc ON doc.id = a.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = a.department_id
             LEFT JOIN users creator ON creator.id = a.created_by
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}, a.start_time ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Full appointment detail with patient + doctor + department joins.
     *
     * @return array<string, mixed>|null
     */
    public static function profile(int $id): ?array
    {
        $apt = Database::queryOne(
            'SELECT a.*,
                    p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    p.phone AS patient_phone, p.date_of_birth, p.gender, p.blood_group, p.id AS patient_id,
                    p.allergies,
                    u.name AS doctor_name, doc.doctor_code, doc.specialization, doc.room_number,
                    d.name AS department_name,
                    creator.name AS created_by_name,
                    canceller.name AS cancelled_by_name
             FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN doctors doc ON doc.id = a.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = a.department_id
             LEFT JOIN users creator ON creator.id = a.created_by
             LEFT JOIN users canceller ON canceller.id = a.cancelled_by
             WHERE a.id = ? LIMIT 1',
            [$id]
        );
        if ($apt === null) {
            return null;
        }
        $apt['reminders'] = Database::query(
            'SELECT * FROM appointment_reminders WHERE appointment_id = ? ORDER BY scheduled_at',
            [$id]
        );
        return $apt;
    }

    /**
     * Appointments for a specific date (the daily schedule / queue).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forDate(string $date, ?int $departmentId = null, ?int $doctorId = null): array
    {
        $sql = 'SELECT a.*, p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                       p.phone AS patient_phone,
                       u.name AS doctor_name, doc.specialization, doc.room_number,
                       d.name AS department_name
                FROM appointments a
                LEFT JOIN patients p ON p.id = a.patient_id
                LEFT JOIN doctors doc ON doc.id = a.doctor_id
                LEFT JOIN users u ON u.id = doc.user_id
                LEFT JOIN departments d ON d.id = a.department_id
                WHERE a.appointment_date = ?';
        $params = [$date];
        if ($departmentId !== null) {
            $sql .= ' AND a.department_id = ?';
            $params[] = $departmentId;
        }
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }
        $sql .= ' ORDER BY a.start_time ASC, a.queue_token ASC';
        return Database::query($sql, $params);
    }

    /**
     * Appointments in a date range (calendar views).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forRange(string $from, string $to, ?int $doctorId = null, ?int $departmentId = null): array
    {
        $sql = 'SELECT a.*, p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                       u.name AS doctor_name, doc.specialization,
                       d.name AS department_name
                FROM appointments a
                LEFT JOIN patients p ON p.id = a.patient_id
                LEFT JOIN doctors doc ON doc.id = a.doctor_id
                LEFT JOIN users u ON u.id = doc.user_id
                LEFT JOIN departments d ON d.id = a.department_id
                WHERE a.appointment_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }
        if ($departmentId !== null) {
            $sql .= ' AND a.department_id = ?';
            $params[] = $departmentId;
        }
        $sql .= ' ORDER BY a.appointment_date ASC, a.start_time ASC';
        return Database::query($sql, $params);
    }

    /**
     * Today's queue grouped by status — powers the live queue dashboard.
     *
     * @return array{rows: array<int, array<string, mixed>>, counts: array<string, int>}
     */
    public static function queueForDate(string $date, ?int $departmentId = null): array
    {
        $rows = self::forDate($date, $departmentId);
        $counts = [
            'total' => count($rows),
            'pending' => 0, 'confirmed' => 0, 'checked_in' => 0,
            'in_consultation' => 0, 'completed' => 0, 'cancelled' => 0, 'no_show' => 0,
        ];
        foreach ($rows as $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
        }
        return ['rows' => $rows, 'counts' => $counts];
    }

    /** @return array<string, int> dashboard/panel counts */
    public static function counts(): array
    {
        return [
            'today'          => (int) Database::scalar('SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status NOT IN ("cancelled","no_show")'),
            'today_scheduled'=> (int) Database::scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'pending'"),
            'today_checked_in' => (int) Database::scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'checked_in'"),
            'today_in_consultation' => (int) Database::scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'in_consultation'"),
            'today_completed' => (int) Database::scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'completed'"),
            'today_cancelled' => (int) Database::scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'cancelled'"),
            'today_no_show' => (int) Database::scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'no_show'"),
            'this_month'    => (int) Database::scalar('SELECT COUNT(*) FROM appointments WHERE appointment_date >= DATE_FORMAT(NOW(), "%Y-%m-01") AND status NOT IN ("cancelled","no_show")'),
        ];
    }

    /**
     * No-show rate for a date range (percentage of no-shows out of
     * appointments that were due, excluding cancelled).
     *
     * @return array{rate: float, total: int, no_show: int}
     */
    public static function noShowRate(string $from, string $to): array
    {
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM appointments
             WHERE appointment_date BETWEEN ? AND ?
               AND status NOT IN ('cancelled','pending')",
            [$from, $to]
        );
        $noShow = (int) Database::scalar(
            "SELECT COUNT(*) FROM appointments
             WHERE appointment_date BETWEEN ? AND ? AND status = 'no_show'",
            [$from, $to]
        );
        $rate = $total > 0 ? round(($noShow / $total) * 100, 1) : 0.0;
        return ['rate' => $rate, 'total' => $total, 'no_show' => $noShow];
    }

    /**
     * Daily volume for the last N days (for charts).
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public static function dailyVolume(int $days = 14): array
    {
        $rows = Database::query(
            "SELECT DATE(appointment_date) AS d, COUNT(*) AS c
             FROM appointments
             WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND status NOT IN ('cancelled','no_show')
             GROUP BY DATE(appointment_date)
             ORDER BY DATE(appointment_date)",
            [(string) $days]
        );
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['d']] = (int) $r['c'];
        }
        $labels = [];
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));
            $data[] = $byDate[$date] ?? 0;
        }
        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Report: appointments grouped by status for a date range.
     *
     * @return array{by_status: array<string, int>, by_type: array<string, int>, by_doctor: array<int, array{count: int, name: string}>, total: int}
     */
    public static function reportForRange(string $from, string $to): array
    {
        $byStatus = [];
        foreach (self::STATUSES as $s) {
            $byStatus[$s] = (int) Database::scalar(
                'SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = ?',
                [$from, $to, $s]
            );
        }

        $byType = [];
        foreach (self::TYPES as $t) {
            $byType[$t] = (int) Database::scalar(
                'SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND appointment_type = ?',
                [$from, $to, $t]
            );
        }

        $byDoctor = Database::query(
            "SELECT COUNT(*) AS count, u.name, doc.doctor_code
             FROM appointments a
             INNER JOIN doctors doc ON doc.id = a.doctor_id
             INNER JOIN users u ON u.id = doc.user_id
             WHERE a.appointment_date BETWEEN ? AND ?
               AND a.status NOT IN ('cancelled','no_show')
             GROUP BY doc.id ORDER BY count DESC LIMIT 10",
            [$from, $to]
        );

        $total = (int) Database::scalar('SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?', [$from, $to]);

        return ['by_status' => $byStatus, 'by_type' => $byType, 'by_doctor' => $byDoctor, 'total' => $total];
    }
}
