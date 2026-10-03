<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\HospitalSetting;

/**
 * Cached access to hospital settings. Reads hit the DB once per request;
 * writes invalidate the cache and update the catalogue row.
 */
final class SettingService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return array_key_exists($key, self::$cache) ? self::$cache[$key] : $default;
    }

    public static function set(string $key, string $value, ?int $updatedBy = null): bool
    {
        $updated = HospitalSetting::setValue($key, $value, $updatedBy) > 0;
        if ($updated) {
            self::$cache = null;
        }
        return $updated;
    }

    /** @return array<string, string> key => value */
    public static function allValues(): array
    {
        self::load();
        /** @var array<string, string> */
        return self::$cache;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];

        if (!Database::tableExists('hospital_settings')) {
            return; // pre-migration context (installer, tests)
        }

        foreach (HospitalSetting::allAsMap() as $key => $row) {
            self::$cache[$key] = $row['value'];
        }
    }
}
