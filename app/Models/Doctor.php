<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Doctor model — profile directory with filters, code generation, and
 * schedule / leave aggregation.
 */
final class Doctor extends Model
{
    private const SORTABLE = [
        'doctor_code'    => 'doc.doctor_code',
        'name'           => 'u.name',
        'specialization' => 'doc.specialization',
        'fee'            => 'doc.consultation_fee',
        'status'         => 'doc.status',
        'created_at'     => 'doc.created_at',
    ];

    protected static function table(): string
    {
        return 'doctors';
    }

    /** Generate MCD-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "MCD-{$year}-";

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(doctor_code, "-", -1) AS UNSIGNED)), 0)
                 FROM doctors WHERE doctor_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM doctors WHERE doctor_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /**
     * Directory with filters, sorting and pagination.
     *
     * @param array{search?:string, department_id?:int, status?:string, sort?:string, dir?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 10): array
    {
        $conditions = ['doc.archived_at IS NULL'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(u.name LIKE ? OR doc.doctor_code LIKE ? OR doc.specialization LIKE ?
                              OR doc.registration_number LIKE ? OR u.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['department_id'])) {
            $conditions[] = 'doc.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'on_leave'], true)) {
            $conditions[] = 'doc.status = ?';
            $params[] = $filters['status'];
        }

        $where = implode(' AND ', $conditions);
        $orderBy = self::SORTABLE[$filters['sort'] ?? 'created_at'] ?? self::SORTABLE['created_at'];
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        if ($orderBy === 'u.name') {
            $dir = 'ASC'; // natural A→Z for names
        }

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM doctors doc INNER JOIN users u ON u.id = doc.user_id WHERE {$where}",
            $params
        );
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT doc.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
                    d.name AS department_name,
                    (SELECT COUNT(*) FROM patient_visits v WHERE v.doctor_id = u.id) AS visit_count,
                    (SELECT COUNT(*) FROM doctor_schedules ds WHERE ds.doctor_id = doc.id AND ds.is_active = 1) AS schedule_slots
             FROM doctors doc
             INNER JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = doc.department_id
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** Full doctor profile (user + department + schedule + stats). */
    public static function profile(int $id): ?array
    {
        $doctor = Database::queryOne(
            'SELECT doc.*, u.name AS user_name, u.email, u.phone, u.name,
                    d.name AS department_name, d.id AS department_id
             FROM doctors doc
             INNER JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = doc.department_id
             WHERE doc.id = ? LIMIT 1',
            [$id]
        );
        if ($doctor === null) {
            return null;
        }

        $doctor['schedule'] = DoctorSchedule::forDoctor($id);
        $doctor['leaves'] = Database::query(
            'SELECT dl.*, u.name AS approver_name
             FROM doctor_leaves dl
             LEFT JOIN users u ON u.id = dl.approved_by
             WHERE dl.doctor_id = ?
             ORDER BY dl.start_date DESC, dl.id DESC',
            [$id]
        );
        $doctor['visit_count'] = (int) Database::scalar(
            'SELECT COUNT(*) FROM patient_visits v WHERE v.doctor_id = ?',
            [$doctor['user_id']]
        );
        $doctor['recent_visits'] = Database::query(
            'SELECT v.*, p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name
             FROM patient_visits v
             INNER JOIN patients p ON p.id = v.patient_id
             WHERE v.doctor_id = ?
             ORDER BY v.visited_at DESC LIMIT 5',
            [$doctor['user_id']]
        );

        return $doctor;
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        return [
            'total'    => (int) Database::scalar('SELECT COUNT(*) FROM doctors WHERE archived_at IS NULL'),
            'active'   => (int) Database::scalar("SELECT COUNT(*) FROM doctors WHERE archived_at IS NULL AND status = 'active'"),
            'on_leave' => (int) Database::scalar("SELECT COUNT(*) FROM doctors WHERE archived_at IS NULL AND status = 'on_leave'"),
        ];
    }

    /** Doctors as a select-friendly list. */
    public static function options(?int $departmentId = null): array
    {
        $sql = 'SELECT doc.id, u.name, doc.specialization, doc.doctor_code
                FROM doctors doc
                INNER JOIN users u ON u.id = doc.user_id
                WHERE doc.archived_at IS NULL AND doc.status = ?';
        $params = ['active'];
        if ($departmentId !== null) {
            $sql .= ' AND doc.department_id = ?';
            $params[] = $departmentId;
        }
        $sql .= ' ORDER BY u.name';
        return Database::query($sql, $params);
    }
}
