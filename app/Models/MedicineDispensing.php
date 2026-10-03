<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Medicine dispensing model — prescription-based + direct sales.
 */
final class MedicineDispensing extends Model
{
    protected static function table(): string { return 'medicine_dispensings'; }

    /** Generate DSP-YYYY-NNNNN with collision retry. */
    public static function nextCode(): string
    {
        $year = (int) date('Y');
        $prefix = "DSP-{$year}-";
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) Database::scalar(
                'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(dispensing_code, "-", -1) AS UNSIGNED)), 0)
                 FROM medicine_dispensings WHERE dispensing_code LIKE ?',
                [$prefix . '%']
            );
            $candidate = sprintf('%s%05d', $prefix, $max + 1);
            if ((int) Database::scalar('SELECT COUNT(*) FROM medicine_dispensings WHERE dispensing_code = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return $prefix . date('His') . random_int(10, 99);
    }

    /** Full dispensing profile with items + patient + prescription. */
    public static function profile(int $id): ?array
    {
        $dsp = Database::queryOne(
            'SELECT d.*, p.patient_code, CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    rx.prescription_code, c.consultation_code,
                    u.name AS dispensed_by_name,
                    inv.invoice_code
             FROM medicine_dispensings d
             LEFT JOIN patients p ON p.id = d.patient_id
             LEFT JOIN prescriptions rx ON rx.id = d.prescription_id
             LEFT JOIN consultations c ON c.id = d.consultation_id
             LEFT JOIN users u ON u.id = d.dispensed_by
             LEFT JOIN invoices inv ON inv.id = d.invoice_id
             WHERE d.id = ? LIMIT 1',
            [$id]
        );
        if ($dsp === null) return null;
        $dsp['items'] = Database::query(
            'SELECT di.*, m.name AS medicine_name, m.generic_name
             FROM medicine_dispensing_items di
             INNER JOIN medicines m ON m.id = di.medicine_id
             WHERE di.dispensing_id = ? ORDER BY di.id',
            [$id]
        );
        return $dsp;
    }
}
