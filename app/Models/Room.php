<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;

final class Room extends Model
{
    protected static function table(): string { return 'rooms'; }
    public const TYPES = ['general', 'semi_private', 'private', 'icu', 'nicu', 'isolation'];

    public static function forWard(int $wardId): array
    {
        return Database::query(
            'SELECT r.*, COUNT(b.id) AS bed_count,
                    SUM(CASE WHEN b.status = "available" THEN 1 ELSE 0 END) AS available,
                    SUM(CASE WHEN b.status = "occupied" THEN 1 ELSE 0 END) AS occupied
             FROM rooms r
             LEFT JOIN beds b ON b.room_id = r.id
             WHERE r.ward_id = ? AND r.is_active = 1
             GROUP BY r.id ORDER BY r.room_number', [$wardId]
        );
    }
}
