<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Weekly recurring doctor schedule. One row per (doctor, day_of_week).
 * Overlap detection for the same doctor + day prevents slot conflicts.
 */
final class DoctorSchedule extends Model
{
    protected static function table(): string
    {
        return 'doctor_schedules';
    }

    public const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /** Schedule rows for a doctor, ordered by day_of_week. */
    public static function forDoctor(int $doctorId): array
    {
        return Database::query(
            'SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week',
            [$doctorId]
        );
    }

    /**
     * Check if a time range overlaps an existing slot for the same doctor
     * on the same day (excluding an optional record id on edit).
     */
    public static function overlaps(int $doctorId, int $dayOfWeek, string $start, string $end, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM doctor_schedules
                WHERE doctor_id = ? AND day_of_week = ?
                  AND is_active = 1
                  AND (start_time < ? AND end_time > ?)';
        $params = [$doctorId, $dayOfWeek, $end, $start];
        if ($ignoreId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreId;
        }
        return (int) Database::scalar($sql, $params) > 0;
    }

    /** True if the doctor has an approved/approvable leave on the given date. */
    public static function isOnLeaveOn(int $doctorId, string $date): bool
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM doctor_leaves
             WHERE doctor_id = ? AND ? BETWEEN start_date AND end_date
               AND status IN ('approved','pending')",
            [$doctorId, $date]
        ) > 0;
    }

    /** Available slot count for a specific date (based on day-of-week schedule). */
    public static function slotsForDate(int $doctorId, string $date): array
    {
        $dayOfWeek = (int) date('w', strtotime($date));
        return Database::query(
            'SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1 ORDER BY start_time',
            [$doctorId, $dayOfWeek]
        );
    }
}
