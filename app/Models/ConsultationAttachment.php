<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Clinical attachments — private files linked to a consultation.
 * Downloads are permission-gated; files never live in the docroot.
 */
final class ConsultationAttachment extends Model
{
    protected static function table(): string
    {
        return 'consultation_attachments';
    }

    /** @return array<int, array<string, mixed>> */
    public static function forConsultation(int $consultationId): array
    {
        return Database::query(
            'SELECT ca.*, u.name AS uploaded_by_name FROM consultation_attachments ca
             LEFT JOIN users u ON u.id = ca.uploaded_by
             WHERE ca.consultation_id = ? ORDER BY ca.created_at DESC',
            [$consultationId]
        );
    }

    /** Find a single attachment by id. */
    public static function find(int $id): ?array
    {
        return Database::queryOne(
            'SELECT ca.*, u.name AS uploaded_by_name FROM consultation_attachments ca
             LEFT JOIN users u ON u.id = ca.uploaded_by
             WHERE ca.id = ? LIMIT 1',
            [$id]
        );
    }
}
