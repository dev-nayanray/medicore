<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Consultation model — clinical encounter records with draft/finalized
 * states, patient history timeline, vitals, and code generation.
 */
final class Consultation extends Model
{
    protected static function table(): string
    {
        return 'consultations';
    }

    public const STATUSES = ['draft', 'finalized', 'amended'];

    /** Generate CON-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "CON-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(consultation_code, "-", -1) AS UNSIGNED)), 0)
                 FROM consultations WHERE consultation_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM consultations WHERE consultation_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /**
     * Directory with filters, sorting, pagination.
     *
     * @param array{search?:string, status?:string, patient_id?:int, doctor_id?:int, date_from?:string, date_to?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 15): array
    {
        $conditions = ['1=1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(c.consultation_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                              OR CONCAT(p.first_name, " ", p.last_name) LIKE ? OR p.patient_code LIKE ?
                              OR u.name LIKE ? OR c.chief_complaint LIKE ? OR c.diagnoses LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $conditions[] = 'c.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['patient_id'])) {
            $conditions[] = 'c.patient_id = ?';
            $params[] = (int) $filters['patient_id'];
        }

        if (!empty($filters['doctor_id'])) {
            $conditions[] = 'c.doctor_id = ?';
            $params[] = (int) $filters['doctor_id'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(c.consultation_date) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(c.consultation_date) <= ?';
            $params[] = $filters['date_to'];
        }

        $where = implode(' AND ', $conditions);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM consultations c
             LEFT JOIN patients p ON p.id = c.patient_id
             LEFT JOIN doctors doc ON doc.id = c.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             WHERE {$where}",
            $params
        );
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT c.*,
                    p.patient_code, CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    p.phone AS patient_phone, p.id AS patient_id,
                    u.name AS doctor_name, doc.doctor_code, doc.specialization,
                    d.name AS department_name,
                    a.appointment_code
             FROM consultations c
             LEFT JOIN patients p ON p.id = c.patient_id
             LEFT JOIN doctors doc ON doc.id = c.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = c.department_id
             LEFT JOIN appointments a ON a.id = c.appointment_id
             WHERE {$where}
             ORDER BY c.consultation_date DESC, c.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Full consultation profile with prescription, attachments, amendments.
     *
     * @return array<string, mixed>|null
     */
    public static function profile(int $id): ?array
    {
        $con = Database::queryOne(
            'SELECT c.*,
                    p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    p.phone AS patient_phone, p.date_of_birth, p.gender, p.blood_group, p.allergies,
                    p.address, p.city,
                    u.name AS doctor_name, doc.doctor_code, doc.specialization, doc.qualifications, doc.room_number,
                    d.name AS department_name,
                    a.appointment_code, a.appointment_date, a.start_time AS apt_start, a.end_time AS apt_end,
                    creator.name AS created_by_name,
                    fin.name AS finalized_by_name
             FROM consultations c
             LEFT JOIN patients p ON p.id = c.patient_id
             LEFT JOIN doctors doc ON doc.id = c.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = c.department_id
             LEFT JOIN appointments a ON a.id = c.appointment_id
             LEFT JOIN users creator ON creator.id = c.created_by
             LEFT JOIN users fin ON fin.id = c.finalized_by
             WHERE c.id = ? LIMIT 1',
            [$id]
        );
        if ($con === null) {
            return null;
        }

        $con['prescription'] = Database::queryOne(
            'SELECT pr.*, u.name AS doctor_name FROM prescriptions pr
             LEFT JOIN users u ON u.id = pr.doctor_id
             WHERE pr.consultation_id = ? LIMIT 1',
            [$id]
        );
        if ($con['prescription'] !== null) {
            $con['prescription']['items'] = Database::query(
                'SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY sort_order, id',
                [$con['prescription']['id']]
            );
        }
        $con['attachments'] = Database::query(
            'SELECT ca.*, u.name AS uploaded_by_name FROM consultation_attachments ca
             LEFT JOIN users u ON u.id = ca.uploaded_by
             WHERE ca.consultation_id = ? ORDER BY ca.created_at DESC',
            [$id]
        );
        $con['amendments'] = Database::query(
            'SELECT am.*, u.name AS amended_by_name FROM consultation_amendments am
             LEFT JOIN users u ON u.id = am.amended_by
             WHERE am.consultation_id = ? ORDER BY am.created_at DESC',
            [$id]
        );

        return $con;
    }

    /**
     * Patient consultation history (timeline).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forPatient(int $patientId): array
    {
        return Database::query(
            'SELECT c.*, u.name AS doctor_name, doc.specialization, d.name AS department_name
             FROM consultations c
             LEFT JOIN doctors doc ON doc.id = c.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE c.patient_id = ?
             ORDER BY c.consultation_date DESC, c.id DESC',
            [$patientId]
        );
    }

    /**
     * Consultations for a doctor on a date (workspace view).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forDoctorOnDate(int $doctorId, string $date): array
    {
        return Database::query(
            'SELECT c.*, p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    p.phone AS patient_phone
             FROM consultations c
             LEFT JOIN patients p ON p.id = c.patient_id
             WHERE c.doctor_id = ? AND DATE(c.consultation_date) = ?
             ORDER BY c.consultation_date DESC',
            [$doctorId, $date]
        );
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        return [
            'total'     => (int) Database::scalar('SELECT COUNT(*) FROM consultations'),
            'drafts'    => (int) Database::scalar("SELECT COUNT(*) FROM consultations WHERE status = 'draft'"),
            'finalized' => (int) Database::scalar("SELECT COUNT(*) FROM consultations WHERE status = 'finalized'"),
            'today'     => (int) Database::scalar('SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = CURDATE()'),
        ];
    }
}
