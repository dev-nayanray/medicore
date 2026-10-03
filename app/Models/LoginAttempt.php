<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Login attempt trail — successes and failures with reasons, used for
 * per-account history and DB-backed rate limiting.
 */
final class LoginAttempt extends Model
{
    protected static function table(): string
    {
        return 'login_attempts';
    }

    public static function record(
        string $email,
        ?int $userId,
        bool $successful,
        ?string $failureReason,
        string $ip,
        string $userAgent,
    ): void {
        Database::execute(
            'INSERT INTO login_attempts (email, user_id, successful, failure_reason, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)',
            [mb_substr($email, 0, 190), $userId, $successful ? 1 : 0, $failureReason, $ip, mb_substr($userAgent, 0, 255)]
        );
    }

    /**
     * Failures for one identifier inside the window — the rate-limiting
     * counter. Email and IP are counted separately so a shared NAT does
     * not lock everyone out (the caller applies a higher IP threshold).
     */
    public static function recentFailuresForEmail(string $email, int $windowMinutes): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE successful = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) AND email = ?',
            [$windowMinutes, $email]
        );
    }

    public static function recentFailuresForIp(string $ip, int $windowMinutes): int
    {
        if ($ip === '' || $ip === '0.0.0.0') {
            return 0; // unknown IPs are not rate limited on the IP dimension
        }
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE successful = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) AND ip_address = ?',
            [$windowMinutes, $ip]
        );
    }

    /** Recent attempts for one account (admin/profile views). */
    public static function recentForUser(int $userId, int $limit = 10): array
    {
        return Database::query(
            'SELECT * FROM login_attempts WHERE user_id = ?
             ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(50, $limit)),
            [$userId]
        );
    }

    /** Clear the failure counter for an identifier after a success. */
    public static function clearFailures(string $email): void
    {
        Database::execute(
            'UPDATE login_attempts SET failure_reason = NULL WHERE email = ? AND successful = 0 AND failure_reason IS NOT NULL',
            [$email]
        );
    }

    /** Remove attempts older than N days (console auth:purge). */
    public static function purgeOlderThanDays(int $days): int
    {
        return Database::execute(
            'DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
    }
}
