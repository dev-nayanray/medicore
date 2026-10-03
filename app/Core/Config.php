<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Immutable configuration repository.
 *
 * Config::init(['app' => [...], 'database' => [...]]);   // once, at boot
 * config('app.name');        config('database.host');
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];
    private static bool $initialized = false;

    /**
     * @param array<string, mixed> $items
     */
    public static function init(array $items): void
    {
        if (self::$initialized) {
            throw new RuntimeException('Config has already been initialized.');
        }
        self::$items = $items;
        self::$initialized = true;
    }

    /**
     * Dot-notation access:  config('app.debug', false)
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
