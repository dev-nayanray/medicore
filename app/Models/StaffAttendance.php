<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Staff daily attendance — one row per (staff, date). Derived status
 * with optional check-in / check-out times for payroll integration later.
 */
final class StaffAttendance extends Model
{
    protected static function table(): string
    {
        return 'staff_attendance';
    }

    /** @return array<string, int> */
    public static function summaryForDate(string $date): array
    {
        return [
            'present'  => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE date = ? AND status = 'present'", [$date]),
            'late'     => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE date = ? AND status = 'late'", [$date]),
            'absent'   => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE date = ? AND status = 'absent'", [$date]),
            'leave'    => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE date = ? AND status = 'leave'", [$date]),
            'half_day' => (int) Database::scalar("SELECT COUNT(*) FROM staff_attendance WHERE date = ? AND status = 'half_day'", [$date]),
        ];
    }
}
