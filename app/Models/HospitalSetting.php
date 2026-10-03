<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Hospital settings model (self-describing key/value catalogue).
 */
final class HospitalSetting extends Model
{
    protected static function table(): string
    {
        return 'hospital_settings';
    }

    /** @return array<string, array<string, mixed>> settings keyed by `key` */
    public static function allAsMap(): array
    {
        $map = [];
        foreach (Database::query('SELECT * FROM hospital_settings') as $row) {
            $map[(string) $row['key']] = $row;
        }
        return $map;
    }

    /** @return array<string, array<int, array<string, mixed>>> grouped by `group`, ordered by id */
    public static function groupedOrdered(): array
    {
        $grouped = [];
        foreach (Database::query('SELECT * FROM hospital_settings ORDER BY `group` ASC, id ASC') as $row) {
            $grouped[(string) $row['group']][] = $row;
        }
        return $grouped;
    }

    public static function setValue(string $key, string $value, ?int $updatedBy): int
    {
        return Database::execute(
            'UPDATE hospital_settings SET `value` = ?, updated_by = ? WHERE `key` = ?',
            [$value, $updatedBy, $key]
        );
    }
}
