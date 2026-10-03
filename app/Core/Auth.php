<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-based authentication with role/permission authorization.
 *
 * The signed-in identity is cached in the session and refreshed from the
 * database at most every PERMISSION_TTL seconds, so role/permission edits
 * apply within minutes without hammering the DB on every request.
 *
 * Permission resolution order (Auth::can):
 *   1. super-admin role      -> always allow
 *   2. direct 'deny'  rule   -> deny (strongest override)
 *   3. direct 'allow' rule   -> allow
 *   4. role-granted permission -> allow
 *   5. otherwise             -> deny
 */
final class Auth
{
    private const SESSION_KEY = '_auth_user';

    /** Re-sync the identity snapshot from the DB at most this often (seconds). */
    private const PERMISSION_TTL = 300;

    public static function check(): bool
    {
        return Session::get(self::SESSION_KEY) !== null;
    }

    public static function id(): ?int
    {
        $user = Session::get(self::SESSION_KEY);
        return is_array($user) ? (int) ($user['id'] ?? 0) ?: null : null;
    }

    /**
     * Authenticated user snapshot (session cache — do not mutate).
     *
     * @return array{id:int, name:string, email:string, avatar_path:?string,
     *               roles:string[], permissions:string[],
     *               direct_allow:string[], direct_deny:string[],
     *               must_change_password:bool}|null
     */
    public static function user(): ?array
    {
        $user = Session::get(self::SESSION_KEY);
        if (!is_array($user)) {
            return null;
        }

        // Periodic re-sync so permission edits apply without re-login.
        $loadedAt = (int) Session::get('_auth_loaded_at', 0);
        if ($loadedAt > 0 && (time() - $loadedAt) > self::PERMISSION_TTL) {
            $fresh = User::find((int) $user['id']);
            if ($fresh === null || $fresh['archived_at'] !== null || (int) $fresh['is_active'] !== 1) {
                // Account vanished, was archived or deactivated mid-session — kill it.
                self::logout();
                return null;
            }
            self::store($fresh);
            $user = Session::get(self::SESSION_KEY);
        }

        return is_array($user) ? $user : null;
    }

    /**
     * Log a user row in (regenerates the session id — fixation defence).
     *
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        Session::regenerate();
        self::store($user);
    }

    private static function store(array $user): void
    {
        $userId = (int) $user['id'];
        Session::put(self::SESSION_KEY, [
            'id'          => $userId,
            'name'        => (string) $user['name'],
            'email'       => (string) $user['email'],
            'avatar_path' => $user['avatar_path'] ?? null,
            'roles'       => User::roleSlugs($userId),
            'permissions' => User::permissionNames($userId),
            'direct_allow' => User::directPermissionNames($userId, 'allow'),
            'direct_deny'  => User::directPermissionNames($userId, 'deny'),
            'must_change_password' => (int) ($user['must_change_password'] ?? 0) === 1,
        ]);
        Session::put('_auth_loaded_at', time());
    }

    /** Force an immediate re-sync on the next user() call. */
    public static function expireSnapshot(): void
    {
        Session::put('_auth_loaded_at', 0);
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget('_auth_loaded_at');
        Session::regenerate();
    }

    /** Update a field on the cached identity (name after profile edit, etc.). */
    public static function updateSessionField(string $field, mixed $value): void
    {
        $user = Session::get(self::SESSION_KEY);
        if (is_array($user)) {
            $user[$field] = $value;
            Session::put(self::SESSION_KEY, $user);
        }
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------
    public static function hasRole(string ...$slugs): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if (in_array('super-admin', $user['roles'], true)) {
            return true;
        }
        return array_intersect($slugs, $user['roles']) !== [];
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if (in_array('super-admin', $user['roles'], true)) {
            return true;                    // 1. super-admin bypass
        }
        if (in_array($permission, $user['direct_deny'] ?? [], true)) {
            return false;                   // 2. explicit deny
        }
        if (in_array($permission, $user['direct_allow'] ?? [], true)) {
            return true;                    // 3. explicit allow
        }
        return in_array($permission, $user['permissions'] ?? [], true); // 4. role grant
    }

    public static function mustChangePassword(): bool
    {
        $user = self::user();
        return $user !== null && (bool) ($user['must_change_password'] ?? false);
    }

    /** Clear the forced-password-change flag in DB + session. */
    public static function clearMustChangePassword(): void
    {
        $user = self::user();
        if ($user !== null) {
            User::clearMustChangePassword((int) $user['id']);
        }
        self::updateSessionField('must_change_password', false);
    }
}
