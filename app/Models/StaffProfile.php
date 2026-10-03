<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Staff employment profile model — directory with filters, code generation,
 * and shift / leave / attendance aggregation.
 */
final class StaffProfile extends Model
{
    private const SORTABLE = [
        'employee_id'  => 'sp.employee_id',
        'name'         => 'u.name',
        'job_title'    => 'sp.job_title',
        'status'       => 'sp.status',
        'hire_date'    => 'sp.hire_date',
        'created_at'   => 'sp.created_at',
    ];

    protected static function table(): string
    {
        return 'staff_profiles';
    }

    /** Generate MCS-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "MCS-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(employee_id, "-", -1) AS UNSIGNED)), 0)
                 FROM staff_profiles WHERE employee_id LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM staff_profiles WHERE employee_id = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /**
     * @param array{search?:string, department_id?:int, status?:string, sort?:string, dir?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 10): array
    {
        $conditions = ['sp.archived_at IS NULL'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(u.name LIKE ? OR sp.employee_id LIKE ? OR sp.job_title LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['department_id'])) {
            $conditions[] = 'sp.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'on_leave', 'terminated'], true)) {
            $conditions[] = 'sp.status = ?';
            $params[] = $filters['status'];
        }

        $where = implode(' AND ', $conditions);
        $orderBy = self::SORTABLE[$filters['sort'] ?? 'created_at'] ?? self::SORTABLE['created_at'];
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        if ($orderBy === 'u.name') {
            $dir = 'ASC';
        }

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM staff_profiles sp INNER JOIN users u ON u.id = sp.user_id WHERE {$where}",
            $params
        );
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT sp.*, u.name AS user_name, u.email, u.phone,
                    d.name AS department_name,
                    (SELECT COUNT(*) FROM staff_shifts ss WHERE ss.staff_id = sp.id AND ss.shift_date = CURDATE()) AS shifts_today
             FROM staff_profiles sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN departments d ON d.id = sp.department_id
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** Full staff profile (user + department + shifts + attendance + leaves). */
    public static function profile(int $id): ?array
    {
        $staff = Database::queryOne(
            'SELECT sp.*, u.name AS user_name, u.email, u.phone,
                    d.name AS department_name
             FROM staff_profiles sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN departments d ON d.id = sp.department_id
             WHERE sp.id = ? LIMIT 1',
            [$id]
        );
        if ($staff === null) {
            return null;
        }

        $staff['shifts'] = Database::query(
            'SELECT ss.*, d.name AS department_name, u.name AS creator_name
             FROM staff_shifts ss
             LEFT JOIN departments d ON d.id = ss.department_id
             LEFT JOIN users u ON u.id = ss.created_by
             WHERE ss.staff_id = ? AND ss.shift_date >= CURDATE()
             ORDER BY ss.shift_date ASC, ss.start_time ASC LIMIT 14',
            [$id]
        );
        $staff['recent_attendance'] = Database::query(
            'SELECT a.*, u.name AS recorder_name FROM staff_attendance a
             LEFT JOIN users u ON u.id = a.recorded_by
             WHERE a.staff_id = ? ORDER BY a.date DESC LIMIT 10',
            [$id]
        );
        $staff['leaves'] = Database::query(
            'SELECT sl.*, u.name AS approver_name FROM staff_leaves sl
             LEFT JOIN users u ON u.id = sl.approved_by
             WHERE sl.staff_id = ? ORDER BY sl.start_date DESC LIMIT 10',
            [$id]
        );

        $staff['attendance_summary'] = [
            'present'  => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE staff_id = ? AND status = 'present' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$id]),
            'late'     => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE staff_id = ? AND status = 'late' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$id]),
            'absent'   => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE staff_id = ? AND status = 'absent' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$id]),
            'leave'    => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE staff_id = ? AND status = 'leave' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$id]),
        ];

        return $staff;
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        return [
            'total'    => (int) Database::scalar('SELECT COUNT(*) FROM staff_profiles WHERE archived_at IS NULL'),
            'active'   => (int) Database::scalar("SELECT COUNT(*) FROM staff_profiles WHERE archived_at IS NULL AND status = 'active'"),
            'on_leave' => (int) Database::scalar("SELECT COUNT(*) FROM staff_profiles WHERE archived_at IS NULL AND status = 'on_leave'"),
        ];
    }
}
