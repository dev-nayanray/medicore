<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Doctor leave / time-off records. Status workflow mirrors staff_leaves.
 */
final class DoctorLeave extends Model
{
    protected static function table(): string
    {
        return 'doctor_leaves';
    }

    public static function pendingCount(): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM doctor_leaves WHERE status = 'pending'");
    }
}
