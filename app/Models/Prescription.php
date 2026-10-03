<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Prescription model — digital prescriptions with code generation,
 * item management, and finalize/dispense states.
 */
final class Prescription extends Model
{
    protected static function table(): string
    {
        return 'prescriptions';
    }

    public const STATUSES = ['draft', 'finalized', 'dispensed'];

    /** Generate RX-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "RX-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(prescription_code, "-", -1) AS UNSIGNED)), 0)
                 FROM prescriptions WHERE prescription_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM prescriptions WHERE prescription_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /**
     * Full prescription with items + patient + doctor joins.
     *
     * @return array<string, mixed>|null
     */
    public static function profile(int $id): ?array
    {
        $rx = Database::queryOne(
            'SELECT pr.*,
                    p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    p.phone AS patient_phone, p.date_of_birth, p.gender, p.blood_group,
                    p.address, p.city, p.allergies,
                    u.name AS doctor_name, doc.doctor_code, doc.specialization, doc.qualifications, doc.registration_number,
                    d.name AS department_name,
                    c.consultation_code, c.chief_complaint, c.diagnoses,
                    c.consultation_date
             FROM prescriptions pr
             INNER JOIN consultations c ON c.id = pr.consultation_id
             INNER JOIN patients p ON p.id = pr.patient_id
             INNER JOIN doctors doc ON doc.id = pr.doctor_id
             INNER JOIN users u ON u.id = doc.user_id
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE pr.id = ? LIMIT 1',
            [$id]
        );
        if ($rx === null) {
            return null;
        }
        $rx['items'] = Database::query(
            'SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY sort_order, id',
            [$id]
        );
        return $rx;
    }

    /**
     * Prescriptions for a patient (history).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forPatient(int $patientId): array
    {
        return Database::query(
            'SELECT pr.*, u.name AS doctor_name, c.consultation_code, c.consultation_date
             FROM prescriptions pr
             INNER JOIN consultations c ON c.id = pr.consultation_id
             INNER JOIN doctors doc ON doc.id = pr.doctor_id
             INNER JOIN users u ON u.id = doc.user_id
             WHERE pr.patient_id = ?
             ORDER BY pr.created_at DESC',
            [$patientId]
        );
    }

    /** Find by consultation id (1:1 relationship). */
    public static function findByConsultation(int $consultationId): ?array
    {
        return Database::queryOne('SELECT * FROM prescriptions WHERE consultation_id = ?', [$consultationId]);
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        return [
            'total'     => (int) Database::scalar('SELECT COUNT(*) FROM prescriptions'),
            'drafts'    => (int) Database::scalar("SELECT COUNT(*) FROM prescriptions WHERE status = 'draft'"),
            'finalized' => (int) Database::scalar("SELECT COUNT(*) FROM prescriptions WHERE status = 'finalized'"),
            'today'     => (int) Database::scalar('SELECT COUNT(*) FROM prescriptions WHERE DATE(created_at) = CURDATE()'),
        ];
    }
}
