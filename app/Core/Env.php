<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader — no external dependencies.
 *
 * Supports KEY=VALUE pairs, comments (#) and quoted values.
 * Values are exposed through $_ENV / getenv() and cached in memory.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            return; // Fall back to defaults — config layer handles missing keys.
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip surrounding quotes.
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            if ($value === '') {
                $value = ''; // "empty" keyword -> empty string
            } elseif (strtolower($value) === 'true') {
                $value = 'true';
            } elseif (strtolower($value) === 'false') {
                $value = 'false';
            } elseif (strtolower($value) === 'null') {
                $value = '';
            }

            self::$vars[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    public static function get(string $key, mixed $default = null): string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }
        return $default === null ? '' : (string) $default;
    }

    public static function has(string $key): bool
    {
        return self::get($key, '') !== '';
    }
}
