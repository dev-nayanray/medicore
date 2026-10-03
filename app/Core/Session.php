<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Secure session wrapper.
 *
 * - Hardened cookie flags (httponly, samesite, secure-on-https, strict mode).
 * - CSRF token management with timing-safe comparison.
 * - Flash messages (survive exactly one request) and old-form-input.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $cfg = Config::get('auth.session');

        session_name((string) $cfg['name']);
        session_set_cookie_params([
            'lifetime' => (int) $cfg['lifetime'],
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) $cfg['secure'],
            'httponly' => (bool) $cfg['httponly'],
            'samesite' => (string) $cfg['samesite'],
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $cfg['lifetime']);

        session_start();
        self::$started = true;

        // Idle expiration — a session unused longer than the configured
        // lifetime is destroyed; the login screen then shows "expired".
        $now = time();
        $lastActivity = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($lastActivity > 0 && ($now - $lastActivity) > (int) $cfg['lifetime']) {
            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION['_expired'] = true;
        }
        $_SESSION['_last_activity'] = $now;

        // Periodic ID regeneration (defence-in-depth against fixation).
        $lastRegen = (int) ($_SESSION['_last_regenerated'] ?? 0);
        if ($lastRegen === 0 || ($now - $lastRegen) > 1800) {
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['_last_regenerated'] = $now;
        }

        // Lightweight fingerprint binding to mitigate session hijacking.
        $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . config('app.key'));
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif (!hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
            // Fingerprint mismatch — destroy and restart clean.
            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION['_fingerprint'] = $fingerprint;
        }
    }

    /** Whether the previous session was killed by idle expiry (read-once). */
    public static function wasExpired(): bool
    {
        $expired = (bool) ($_SESSION['_expired'] ?? false);
        unset($_SESSION['_expired']);
        return $expired;
    }

    // ------------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------------
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token);
    }

    // ------------------------------------------------------------------
    // Generic accessors
    // ------------------------------------------------------------------
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // ------------------------------------------------------------------
    // Flash messages:  ['type' => 'success', 'message' => '...']
    // ------------------------------------------------------------------
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type:string, message:string}> */
    public static function pullFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    // ------------------------------------------------------------------
    // Old input (form re-population after validation errors)
    // ------------------------------------------------------------------
    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token']);
        $_SESSION['_old'] = $input;
    }

    public static function pullOld(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return $old;
    }

    public static function regenerate(): void
    {
        // headers_sent() only happens in CLI tests after output — on the
        // web, login/logout run before any view output, so regeneration
        // always applies where it matters.
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }
}
