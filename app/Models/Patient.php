<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Patient model — master records, filtered directory, duplicate
 * detection and timeline aggregation.
 */
final class Patient extends Model
{
    /** Columns the directory is allowed to sort by (whitelist). */
    private const SORTABLE = [
        'patient_code' => 'p.patient_code',
        'name'         => 'p.last_name, p.first_name',
        'date_of_birth' => 'p.date_of_birth',
        'created_at'   => 'p.created_at',
        'visits'       => 'visit_count',
    ];

    protected static function table(): string
    {
        return 'patients';
    }

    // ------------------------------------------------------------------
    // Patient ID generation: MCP-<year>-<sequence>, collision-safe
    // ------------------------------------------------------------------
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "MCP-{$year}-";

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(patient_code, "-", -1) AS UNSIGNED)), 0)
                 FROM patients WHERE patient_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);

            $exists = (int) Database::scalar('SELECT COUNT(*) FROM patients WHERE patient_code = ?', [$candidate]);
            if ($exists === 0) {
                return $candidate;
            }
        }

        // Practically unreachable; fall back to a time-based suffix.
        return $prefix . date('His') . random_int(10, 99);
    }

    // ------------------------------------------------------------------
    // Directory: search + filters + sorting + pagination
    // ------------------------------------------------------------------
    /**
     * @param array{search?:string, gender?:string, blood_group?:string, status?:string,
     *               sort?:string, dir?:string, min_age?:int, max_age?:int} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 10): array
    {
        $conditions = ['1=1'];
        $params = [];

        $status = $filters['status'] ?? 'not_archived';
        if ($status === 'archived') {
            $conditions[] = 'p.archived_at IS NOT NULL';
        } elseif ($status === 'all') {
            // no condition
        } else {
            $conditions[] = 'p.archived_at IS NULL';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, " ", p.last_name) LIKE ?
                              OR p.patient_code LIKE ? OR p.phone LIKE ? OR p.national_id LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['gender']) && in_array($filters['gender'], ['male', 'female', 'other'], true)) {
            $conditions[] = 'p.gender = ?';
            $params[] = $filters['gender'];
        }

        if (!empty($filters['blood_group'])) {
            $conditions[] = 'p.blood_group = ?';
            $params[] = $filters['blood_group'];
        }

        if (!empty($filters['min_age']) || !empty($filters['max_age'])) {
            $min = max(0, (int) ($filters['min_age'] ?? 0));
            $max = min(130, (int) ($filters['max_age'] ?? 130));
            if ($min > 0) {
                $conditions[] = 'p.date_of_birth <= DATE_SUB(CURDATE(), INTERVAL ? YEAR)';
                $params[] = $min;
            }
            if ($max < 130) {
                $conditions[] = 'p.date_of_birth > DATE_SUB(CURDATE(), INTERVAL ? YEAR)';
                $params[] = $max + 1;
            }
        }

        $where = implode(' AND ', $conditions);
        $orderBy = self::SORTABLE[$filters['sort'] ?? 'created_at'] ?? self::SORTABLE['created_at'];
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        if ($orderBy === 'p.last_name, p.first_name') {
            // Natural name ordering is A→Z regardless of the requested direction knob.
            $dir = 'ASC';
        }

        $total = (int) Database::scalar("SELECT COUNT(*) FROM patients p WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT p.*,
                    TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age,
                    (SELECT COUNT(*) FROM patient_visits v WHERE v.patient_id = p.id) AS visit_count,
                    (SELECT MAX(v.visited_at) FROM patient_visits v WHERE v.patient_id = p.id AND v.status = 'completed') AS last_visit,
                    (SELECT COUNT(*) FROM patient_documents d WHERE d.patient_id = p.id) AS document_count
             FROM patients p
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Directory rows as CSV (respects the same filters, ignores paging).
     *
     * @param array<string, mixed> $filters
     */
    public static function directoryCsv(array $filters): string
    {
        $data = self::directory($filters, 1, 100000);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Patient ID', 'First name', 'Last name', 'Gender', 'Date of birth', 'Age', 'Blood group', 'Phone', 'Email', 'City', 'Visits', 'Registered', 'Status']);
        foreach ($data['rows'] as $row) {
            fputcsv($out, [
                $row['patient_code'], $row['first_name'], $row['last_name'], $row['gender'],
                $row['date_of_birth'], $row['age'], $row['blood_group'], $row['phone'],
                $row['email'], $row['city'], $row['visit_count'],
                date('Y-m-d', strtotime((string) $row['created_at'])),
                $row['archived_at'] !== null ? 'archived' : 'active',
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    // ------------------------------------------------------------------
    // Duplicate detection
    // ------------------------------------------------------------------
    /**
     * Possible duplicates for the given details (used pre-create and
     * by the AJAX live check). Matches: national id, exact phone,
     * or same first+last name with the same date of birth.
     *
     * @return array<int, array{id:int, patient_code:string, name:string, phone:string, date_of_birth:string}>
     */
    public static function findPossibleDuplicates(array $data, ?int $ignoreId = null): array
    {
        $conditions = [];
        $params = [];

        if (!empty($data['national_id'])) {
            $conditions[] = 'national_id = ?';
            $params[] = $data['national_id'];
        }
        if (!empty($data['phone'])) {
            $conditions[] = 'REPLACE(REPLACE(phone, " ", ""), "-", "") = ?';
            $params[] = preg_replace('/[\s\-]/', '', (string) $data['phone']);
        }
        if (!empty($data['first_name']) && !empty($data['last_name']) && !empty($data['date_of_birth'])) {
            $conditions[] = '(LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?) AND date_of_birth = ?)';
            array_push($params, $data['first_name'], $data['last_name'], $data['date_of_birth']);
        }

        if ($conditions === []) {
            return [];
        }

        $where = '(' . implode(' OR ', $conditions) . ') AND archived_at IS NULL';
        if ($ignoreId !== null) {
            $where .= ' AND id != ?';
            $params[] = $ignoreId;
        }

        $rows = Database::query(
            "SELECT id, patient_code, CONCAT(first_name, ' ', last_name) AS name, phone, date_of_birth
             FROM patients WHERE {$where} ORDER BY created_at DESC LIMIT 5",
            $params
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'patient_code' => (string) $r['patient_code'],
            'name' => (string) $r['name'],
            'phone' => (string) $r['phone'],
            'date_of_birth' => (string) $r['date_of_birth'],
        ], $rows);
    }

    // ------------------------------------------------------------------
    // Timeline & documents
    // ------------------------------------------------------------------
    /** @return array<int, array<string, mixed>> visits with doctor names */
    public static function visits(int $patientId): array
    {
        return Database::query(
            'SELECT v.*, u.name AS doctor_name
             FROM patient_visits v
             LEFT JOIN users u ON u.id = v.doctor_id
             WHERE v.patient_id = ?
             ORDER BY v.visited_at DESC, v.id DESC',
            [$patientId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function documents(int $patientId): array
    {
        return Database::query(
            'SELECT d.*, u.name AS uploaded_by_name
             FROM patient_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.patient_id = ?
             ORDER BY d.created_at DESC',
            [$patientId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findDocument(int $documentId): ?array
    {
        return Database::queryOne(
            'SELECT d.*, u.name AS uploaded_by_name FROM patient_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.id = ? LIMIT 1',
            [$documentId]
        );
    }

    /** @return array<string, int> dashboard/panel counts */
    public static function counts(): array
    {
        return [
            'total' => (int) Database::scalar('SELECT COUNT(*) FROM patients'),
            'active' => (int) Database::scalar('SELECT COUNT(*) FROM patients WHERE archived_at IS NULL'),
            'archived' => (int) Database::scalar('SELECT COUNT(*) FROM patients WHERE archived_at IS NOT NULL'),
            'this_month' => (int) Database::scalar('SELECT COUNT(*) FROM patients WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")'),
            'visits_today' => (int) Database::scalar('SELECT COUNT(*) FROM patient_visits WHERE DATE(visited_at) = CURDATE() AND status != "cancelled"'),
        ];
    }
}
