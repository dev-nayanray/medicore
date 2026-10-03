<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Consultation amendments — formal correction history for finalized
 * clinical records. Each row is one field-level change with a reason
 * and authorizing user.
 */
final class ConsultationAmendment extends Model
{
    protected static function table(): string
    {
        return 'consultation_amendments';
    }

    /** @return array<int, array<string, mixed>> */
    public static function forConsultation(int $consultationId): array
    {
        return Database::query(
            'SELECT am.*, u.name AS amended_by_name FROM consultation_amendments am
             LEFT JOIN users u ON u.id = am.amended_by
             WHERE am.consultation_id = ? ORDER BY am.created_at DESC',
            [$consultationId]
        );
    }
}
