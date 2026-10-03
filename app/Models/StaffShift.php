<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Staff shift schedules. Overlap detection prevents double-booking.
 */
final class StaffShift extends Model
{
    protected static function table(): string
    {
        return 'staff_shifts';
    }

    /**
     * Check if a new shift overlaps an existing one for the same staff
     * on the same date (excluding an optional record id on edit).
     */
    public static function overlaps(int $staffId, string $date, string $start, string $end, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM staff_shifts
                WHERE staff_id = ? AND shift_date = ?
                  AND (start_time < ? AND end_time > ?)';
        $params = [$staffId, $date, $end, $start];
        if ($ignoreId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreId;
        }
        return (int) Database::scalar($sql, $params) > 0;
    }

    /** Shifts for a date range, optionally filtered by department. */
    public static function forDateRange(string $from, string $to, ?int $departmentId = null): array
    {
        $sql = 'SELECT ss.*, u.name AS staff_name, sp.employee_id, d.name AS department_name
                FROM staff_shifts ss
                INNER JOIN staff_profiles sp ON sp.id = ss.staff_id
                INNER JOIN users u ON u.id = sp.user_id
                LEFT JOIN departments d ON d.id = ss.department_id
                WHERE ss.shift_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($departmentId !== null) {
            $sql .= ' AND ss.department_id = ?';
            $params[] = $departmentId;
        }
        $sql .= ' ORDER BY ss.shift_date DESC, ss.start_time';
        return Database::query($sql, $params);
    }

    /** Today's shifts, optionally for one department. */
    public static function today(?int $departmentId = null): array
    {
        return self::forDateRange(date('Y-m-d'), date('Y-m-d'), $departmentId);
    }
}
