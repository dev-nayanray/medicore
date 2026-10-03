<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;

final class Admission extends Model
{
    protected static function table(): string { return 'admissions'; }
    public const STATUSES = ['admitted', 'discharged', 'transferred_out'];
    public const TYPES = ['emergency', 'scheduled', 'transfer_in'];

    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "ADM-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(admission_code, "-", -1) AS UNSIGNED)), 0)
                 FROM admissions WHERE admission_code LIKE ?', [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM admissions WHERE admission_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    public static function directory(array $filters, int $page = 1, int $perPage = 15): array
    {
        $conditions = ['1=1']; $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(a.admission_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, " ", p.last_name) LIKE ? OR p.patient_code LIKE ?)';
            $like = '%' . $search . '%'; array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $conditions[] = 'a.status = ?'; $params[] = $filters['status'];
        }
        if (!empty($filters['ward_id'])) { $conditions[] = 'a.ward_id = ?'; $params[] = (int) $filters['ward_id']; }
        if (!empty($filters['date_from'])) { $conditions[] = 'DATE(a.admission_date) >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $conditions[] = 'DATE(a.admission_date) <= ?'; $params[] = $filters['date_to']; }
        $where = implode(' AND ', $conditions);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM admissions a LEFT JOIN patients p ON p.id = a.patient_id WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages); $offset = ($page - 1) * $perPage;
        $rows = Database::query(
            "SELECT a.*, p.patient_code, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.phone AS patient_phone,
                    u.name AS doctor_name, w.name AS ward_name, r.room_number, r.room_type, r.daily_rate, b.bed_number
             FROM admissions a
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN doctors doc ON doc.id = a.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN wards w ON w.id = a.ward_id
             LEFT JOIN rooms r ON r.id = a.room_id
             LEFT JOIN beds b ON b.id = a.bed_id
             WHERE {$where}
             ORDER BY a.admission_date DESC, a.id DESC
             LIMIT {$perPage} OFFSET {$offset}", $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    public static function profile(int $id): ?array
    {
        $adm = Database::queryOne(
            'SELECT a.*, p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    p.phone, p.date_of_birth, p.gender, p.blood_group, p.allergies,
                    u.name AS doctor_name, doc.specialization,
                    w.name AS ward_name, r.room_number, r.room_type, r.daily_rate, b.bed_number,
                    creator.name AS created_by_name, discharger.name AS discharged_by_name
             FROM admissions a
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN doctors doc ON doc.id = a.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN wards w ON w.id = a.ward_id
             LEFT JOIN rooms r ON r.id = a.room_id
             LEFT JOIN beds b ON b.id = a.bed_id
             LEFT JOIN users creator ON creator.id = a.created_by
             LEFT JOIN users discharger ON discharger.id = a.discharged_by
             WHERE a.id = ? LIMIT 1', [$id]
        );
        if ($adm === null) return null;
        $adm['transfers'] = Database::query(
            'SELECT bt.*, fb.bed_number AS from_bed, fb.room_id AS from_room_id,
                    tb.bed_number AS to_bed, tb.room_id AS to_room_id,
                    u.name AS transferred_by_name
             FROM bed_transfers bt
             LEFT JOIN beds fb ON fb.id = bt.from_bed_id
             LEFT JOIN beds tb ON tb.id = bt.to_bed_id
             LEFT JOIN users u ON u.id = bt.transferred_by
             WHERE bt.admission_id = ? ORDER BY bt.transfer_date DESC', [$id]
        );
        return $adm;
    }

    public static function counts(): array
    {
        return [
            'admitted' => (int) Database::scalar("SELECT COUNT(*) FROM admissions WHERE status = 'admitted'"),
            'discharged' => (int) Database::scalar("SELECT COUNT(*) FROM admissions WHERE status = 'discharged'"),
            'today' => (int) Database::scalar('SELECT COUNT(*) FROM admissions WHERE DATE(admission_date) = CURDATE()'),
            'discharged_today' => (int) Database::scalar("SELECT COUNT(*) FROM admissions WHERE status = 'discharged' AND DATE(actual_discharge_date) = CURDATE()"),
        ];
    }

    public static function forPatient(int $patientId): array
    {
        return Database::query('SELECT * FROM admissions WHERE patient_id = ? ORDER BY admission_date DESC', [$patientId]);
    }
}
