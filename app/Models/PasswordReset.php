<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Password reset tokens — hashed at rest, single-use, expiring.
 */
final class PasswordReset extends Model
{
    protected static function table(): string
    {
        return 'password_resets';
    }

    /**
     * Issue a token for an email; invalidates previous unused tokens for
     * the same address (only the newest link works). Returns the PLAIN
     * token — the hash is what gets stored.
     */
    public static function issue(string $email, string $ip, int $ttlMinutes = 60): string
    {
        Database::execute(
            'UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL',
            [$email]
        );

        $plain = bin2hex(random_bytes(32));
        Database::execute(
            'INSERT INTO password_resets (email, token_hash, expires_at, requested_ip)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)',
            [$email, hash('sha256', $plain), $ttlMinutes, $ip]
        );

        return $plain;
    }

    /** @return array<string, mixed>|null valid (unused, unexpired) reset row */
    public static function findValid(string $plainToken): ?array
    {
        return Database::queryOne(
            'SELECT * FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1',
            [hash('sha256', $plainToken)]
        );
    }

    public static function markUsed(int $id): void
    {
        Database::execute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$id]);
    }

    /** Remove expired/used tokens (console auth:purge). */
    public static function purgeConsumed(): int
    {
        return Database::execute(
            'DELETE FROM password_resets WHERE used_at IS NOT NULL OR expires_at < NOW()'
        );
    }
}
