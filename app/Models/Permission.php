<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Permission catalogue model.
 */
final class Permission extends Model
{
    protected static function table(): string
    {
        return 'permissions';
    }

    /** @return array<string, array<int, array<string, mixed>>> grouped by module */
    public static function groupedByModule(): array
    {
        $grouped = [];
        foreach (static::all('module', 'ASC') as $permission) {
            $grouped[(string) $permission['module']][] = $permission;
        }
        return $grouped;
    }
}
