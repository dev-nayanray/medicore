<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;

final class Bed extends Model
{
    protected static function table(): string { return 'beds'; }
    public const STATUSES = ['available', 'occupied', 'maintenance', 'cleaning'];

    public static function forRoom(int $roomId): array
    {
        return Database::query('SELECT * FROM beds WHERE room_id = ? ORDER BY bed_number', [$roomId]);
    }

    public static function allWithDetails(): array
    {
        return Database::query(
            'SELECT b.*, r.room_number, r.room_type, r.daily_rate, w.name AS ward_name,
                    CONCAT(p.first_name, " ", p.last_name) AS patient_name, p.patient_code
             FROM beds b
             INNER JOIN rooms r ON r.id = b.room_id
             INNER JOIN wards w ON w.id = r.ward_id
             LEFT JOIN admissions a ON a.id = b.current_admission_id AND a.status = "admitted"
             LEFT JOIN patients p ON p.id = a.patient_id
             ORDER BY w.name, r.room_number, b.bed_number'
        );
    }

    public static function counts(): array
    {
        $rows = Database::query('SELECT status, COUNT(*) AS c FROM beds GROUP BY status');
        $out = ['total' => 0, 'available' => 0, 'occupied' => 0, 'maintenance' => 0, 'cleaning' => 0];
        foreach ($rows as $r) { $out[$r['status']] = (int) $r['c']; $out['total'] += (int) $r['c']; }
        return $out;
    }

    public static function available(): array
    {
        return Database::query(
            'SELECT b.*, r.room_number, r.room_type, r.daily_rate, w.name AS ward_name
             FROM beds b
             INNER JOIN rooms r ON r.id = b.room_id
             INNER JOIN wards w ON w.id = r.ward_id
             WHERE b.status = "available"
             ORDER BY w.name, r.room_number, b.bed_number'
        );
    }
}
