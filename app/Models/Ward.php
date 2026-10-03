<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;

final class Ward extends Model
{
    protected static function table(): string { return 'wards'; }

    public static function withCounts(): array
    {
        return Database::query(
            'SELECT w.*, COUNT(DISTINCT r.id) AS room_count, COUNT(DISTINCT b.id) AS bed_count,
                    SUM(CASE WHEN b.status = "available" THEN 1 ELSE 0 END) AS available_beds,
                    SUM(CASE WHEN b.status = "occupied" THEN 1 ELSE 0 END) AS occupied_beds
             FROM wards w
             LEFT JOIN rooms r ON r.ward_id = w.id AND r.is_active = 1
             LEFT JOIN beds b ON b.room_id = r.id
             WHERE w.is_active = 1
             GROUP BY w.id ORDER BY w.name'
        );
    }

    public static function options(): array
    {
        return Database::query('SELECT id, name FROM wards WHERE is_active = 1 ORDER BY name');
    }
}
