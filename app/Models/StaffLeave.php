<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Staff leave requests — approval workflow (pending → approved/rejected).
 */
final class StaffLeave extends Model
{
    protected static function table(): string
    {
        return 'staff_leaves';
    }

    /** Pending leave count across the whole staff (for dashboard badges). */
    public static function pendingCount(): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM staff_leaves WHERE status = 'pending'");
    }
}
