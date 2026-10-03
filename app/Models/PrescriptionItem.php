<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Prescription items — individual medicine rows on a prescription.
 */
final class PrescriptionItem extends Model
{
    protected static function table(): string
    {
        return 'prescription_items';
    }

    /** @return array<int, array<string, mixed>> */
    public static function forPrescription(int $prescriptionId): array
    {
        return Database::query(
            'SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY sort_order, id',
            [$prescriptionId]
        );
    }
}
