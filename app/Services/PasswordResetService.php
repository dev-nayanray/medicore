<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;

/**
 * Forgot-password / reset-password workflow.
 *
 * Security posture:
 *  - tokens are 256-bit random, stored SHA-256 hashed, single-use, 60 min TTL
 *  - requesting a reset for an unknown email responds identically (no
 *    account enumeration) — only the mail log reveals the truth
 *  - reset requests are rate-limited like logins
 */
final class PasswordResetService
{
    /** @return array{ok: bool, message: string, dev_link?: string} */
    public static function request(string $email, Request $request): array
    {
        $email = trim(strtolower($email));
        $lockoutMinutes = (int) config('auth.throttle.lockout_minutes', 10);

        // Rate-limit reset requests too (same counter namespace as logins).
        if (LoginAttempt::recentFailuresForEmail('reset:' . $email, $lockoutMinutes) >= (int) config('auth.throttle.max_attempts', 5)
            || LoginAttempt::recentFailuresForIp($request->ip(), $lockoutMinutes) >= 4 * (int) config('auth.throttle.max_attempts', 5)) {
            return ['ok' => false, 'message' => 'Too many reset requests. Please try again later.'];
        }

        $generic = ['ok' => true, 'message' => 'If that email exists, a reset link has been sent.'];

        $user = User::findByEmail($email);
        if ($user === null || $user['archived_at'] !== null) {
            // Record a failed attempt so brute-forcing emails gets throttled.
            LoginAttempt::record('reset:' . $email, null, false, 'reset_unknown_email', $request->ip(), $request->userAgent());
            AuditService::log('password.reset_requested', 'auth', 'create', "Reset requested for unknown email {$email}.", [], $request);
            return $generic;
        }

        $plain = PasswordReset::issue($email, $request->ip());
        $link = url('/reset-password/' . $plain);

        $report = MailService::send($email, 'Reset your MediCore password', 'password-reset', [
            'name' => $user['name'],
            'link' => $link,
            'ttl'  => 60,
        ]);

        AuditService::log('password.reset_requested', 'auth', 'create', "Password reset requested for {$email}.", ['email' => $email], $request);

        $result = $generic;
        if (isset($report['dev_link'])) {
            $result['dev_link'] = $report['dev_link'];
        }
        return $result;
    }

    /**
     * Complete the reset: verify token, apply the new password, consume
     * the token, revoke other sessions is not possible server-side with
     * file sessions — instead force re-auth everywhere by regenerating
     * nothing (the user signs in fresh anyway).
     *
     * @return array{ok: bool, message: string}
     */
    public static function reset(string $plainToken, string $newPassword, Request $request): array
    {
        $row = PasswordReset::findValid($plainToken);
        if ($row === null) {
            AuditService::log('password.reset_failed', 'auth', 'update', 'Invalid or expired reset token used.', [], $request);
            return ['ok' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.'];
        }

        $user = User::findByEmail((string) $row['email']);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Account not found.'];
        }

        User::updatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]));
        PasswordReset::markUsed((int) $row['id']);

        // If the user is mid-session (e.g. reset while signed in on another
        // device), clear any pending forced-change flag.
        if (Auth::check() && Auth::id() === (int) $user['id']) {
            Auth::clearMustChangePassword();
        }

        AuditService::log('password.reset_used', 'auth', 'update', "Password reset completed for {$user['email']}.", [], $request);

        return ['ok' => true, 'message' => 'Password updated. You can now sign in with your new password.'];
    }

    /** Validate a token without consuming it (for the reset form screen). */
    public static function tokenEmail(string $plainToken): ?string
    {
        $row = PasswordReset::findValid($plainToken);
        return $row !== null ? (string) $row['email'] : null;
    }
}
