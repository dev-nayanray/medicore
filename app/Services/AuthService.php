<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Models\LoginAttempt;
use App\Models\User;

/**
 * Authentication engine — credential verification with DB-backed rate
 * limiting, attempt tracking and audit coverage.
 *
 * Result object:
 *   ok            bool  authenticated + session established
 *   status        ok|invalid|locked|inactive|archived
 *   user          array|null
 *   retrySeconds  int   remaining lockout (status=locked)
 */
final class AuthService
{
    private const STATUS_OK = 'ok';

    /** @return array{ok: bool, status: string, user: array|null, retrySeconds: int} */
    public static function attempt(string $email, string $password, Request $request): array
    {
        $email = trim(strtolower($email));
        $maxAttempts = (int) config('auth.throttle.max_attempts', 5);
        $lockoutMinutes = (int) config('auth.throttle.lockout_minutes', 10);
        $ip = $request->ip();
        $ua = $request->userAgent();

        $fail = static function (string $reason, ?array $user) use ($email, $ip, $ua, $request): array {
            LoginAttempt::record($email, $user['id'] ?? null, false, $reason, $ip, $ua);
            AuditService::log(
                'login.failed',
                'auth',
                'login',
                "Failed sign-in for {$email} ({$reason}).",
                ['reason' => $reason, 'email' => $email],
                $request
            );
            return ['ok' => false, 'status' => $reason === 'locked' ? 'locked' : $reason, 'user' => null, 'retrySeconds' => 0];
        };

        // 1. Rate limit BEFORE touching the password hash (cheap DoS defence).
        //    Per-email threshold protects the account; a 4x threshold on IP
        //    protects the service without locking out a shared office NAT.
        if (LoginAttempt::recentFailuresForEmail($email, $lockoutMinutes) >= $maxAttempts
            || LoginAttempt::recentFailuresForIp($ip, $lockoutMinutes) >= $maxAttempts * 4) {
            $result = $fail('locked', null);
            $result['retrySeconds'] = $lockoutMinutes * 60;
            return $result;
        }

        $user = User::findByEmail($email);

        // 2. Always run a password verify (even for unknown emails) to keep
        //    timing uniform — then discard the result.
        $hash = $user['password_hash'] ?? '$2y$12$0000000000000000000000000000000000000000000000000000';
        $valid = password_verify($password, (string) $hash);

        if ($user === null || !$valid) {
            return $fail('invalid_password', $user);
        }

        // 3. Account state gates.
        if ($user['archived_at'] !== null) {
            return $fail('archived', $user);
        }
        if ((int) $user['is_active'] !== 1) {
            return $fail('inactive', $user);
        }

        // 4. Maintenance mode: only super admins may enter.
        if (SettingService::get('maintenance_mode', 'false') === 'true' && !self::isSuperAdmin((int) $user['id'])) {
            return $fail('inactive', $user);
        }

        // 5. Success — record, log in, audit.
        LoginAttempt::record($email, (int) $user['id'], true, null, $ip, $ua);
        LoginAttempt::clearFailures($email);
        Auth::login($user);
        User::updateLastLogin((int) $user['id']);
        AuditService::log('login.success', 'auth', 'login', "{$user['name']} signed in.", [], $request);

        return ['ok' => true, 'status' => self::STATUS_OK, 'user' => $user, 'retrySeconds' => 0];
    }

    public static function logout(Request $request): void
    {
        $user = Auth::user();
        if ($user !== null) {
            AuditService::log('logout', 'auth', 'logout', "{$user['name']} signed out.", [], $request);
        }
        Auth::logout();
    }

    private static function isSuperAdmin(int $userId): bool
    {
        return in_array('super-admin', User::roleSlugs($userId), true);
    }
}
