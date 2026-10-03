<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\PasswordResetService;
use App\Services\SettingService;

/**
 * Authentication — sign in/out, forgot/reset password, forced change.
 */
final class AuthController extends Controller
{
    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------
    public function showLogin(Request $request): string
    {
        $expired = Session::wasExpired();

        return Response::html(view('auth/login', [
            'hospitalName' => (string) SettingService::get('hospital_name', 'MediCore'),
            'sessionExpired' => $expired,
        ]));
    }

    public function login(Request $request): string
    {
        $result = $this->validate($request, [
            'email'    => 'required|email',
            'password' => 'required|min:1',
        ]);

        $email = (string) $result['data']['email'];
        $password = (string) $result['data']['password'];

        $attempt = AuthService::attempt($email, $password, $request);

        if (!$attempt['ok']) {
            $message = match ($attempt['status']) {
                'locked' => sprintf(
                    'Too many failed attempts. Try again in %d minute(s).',
                    (int) ceil($attempt['retrySeconds'] / 60)
                ),
                'archived' => 'This account has been archived. Contact an administrator.',
                'inactive' => 'This account is deactivated. Contact an administrator.',
                default => 'Invalid email or password.',
            };
            Session::flash('error', $message);
            Session::flashInput(['email' => $email]);
            return Response::redirect(url('/login'));
        }

        $user = Auth::user();
        Session::flash('success', "Welcome back, {$user['name']}!");

        if (Auth::mustChangePassword()) {
            Session::flash('info', 'Please set a new password to continue.');
            return Response::redirect(url('/change-password'));
        }

        $intended = (string) (Session::get('_intended') ?: '/');
        Session::forget('_intended');

        return Response::redirect(url($intended));
    }

    public function logout(Request $request): string
    {
        AuthService::logout($request);
        Session::flash('success', 'You have been signed out.');
        return Response::redirect(url('/login'));
    }

    // ------------------------------------------------------------------
    // Forgot password
    // ------------------------------------------------------------------
    public function showForgot(Request $request): string
    {
        return Response::html(view('auth/forgot', [
            'hospitalName' => (string) SettingService::get('hospital_name', 'MediCore'),
        ]));
    }

    public function sendResetLink(Request $request): string
    {
        $result = $this->validate($request, [
            'email' => 'required|email',
        ]);

        $outcome = PasswordResetService::request((string) $result['data']['email'], $request);

        if (!$outcome['ok']) {
            Session::flash('error', $outcome['message']);
        } else {
            Session::flash('success', $outcome['message']);
            // Dev convenience: surface the link the mailer "sent".
            if (isset($outcome['dev_link'])) {
                Session::flash('dev_link', $outcome['dev_link']);
            }
        }
        Session::flashInput(['email' => $result['data']['email']]);

        return Response::redirect(url('/forgot-password'));
    }

    // ------------------------------------------------------------------
    // Reset password (via emailed token)
    // ------------------------------------------------------------------
    public function showResetForm(Request $request, string $token = ''): string
    {
        $email = PasswordResetService::tokenEmail($token);

        return Response::html(view('auth/reset', [
            'hospitalName' => (string) SettingService::get('hospital_name', 'MediCore'),
            'token'  => $token,
            'email'  => $email,   // null => invalid/expired token
        ]));
    }

    public function resetPassword(Request $request, string $token = ''): string
    {
        $result = $this->validate($request, [
            'password' => 'required|strong|confirmed',
        ], redirectBack: false);

        if (!$result['ok']) {
            Session::put('_errors', $result['errors']);
            Session::flashInput($request->all());
            return Response::redirect(url('/reset-password/' . urlencode($token)));
        }

        $outcome = PasswordResetService::reset($token, (string) $result['data']['password'], $request);

        if (!$outcome['ok']) {
            Session::flash('error', $outcome['message']);
            return Response::redirect(url('/forgot-password'));
        }

        Session::flash('success', $outcome['message']);
        return Response::redirect(url('/login'));
    }

    // ------------------------------------------------------------------
    // Forced password change (admin-triggered)
    // ------------------------------------------------------------------
    public function showChangePassword(Request $request): string
    {
        return Response::html(view('auth/change-password', [
            'hospitalName' => (string) SettingService::get('hospital_name', 'MediCore'),
        ]));
    }

    public function changePassword(Request $request): string
    {
        $result = $this->validate($request, [
            'current_password' => 'required',
            'password'         => 'required|strong|confirmed',
        ], redirectBack: false);

        if (!$result['ok']) {
            Session::put('_errors', $result['errors']);
            Session::flashInput($request->all());
            return Response::redirect(url('/change-password'));
        }

        $user = \App\Models\User::find((int) Auth::id());

        if ($user === null || !password_verify((string) $result['data']['current_password'], (string) $user['password_hash'])) {
            Session::put('_errors', ['current_password' => 'Current password is incorrect.']);
            Session::flashInput($request->all());
            return Response::redirect(url('/change-password'));
        }

        if (password_verify((string) $result['data']['password'], (string) $user['password_hash'])) {
            Session::put('_errors', ['password' => 'New password must differ from the current one.']);
            Session::flashInput($request->all());
            return Response::redirect(url('/change-password'));
        }

        \App\Models\User::updatePassword((int) $user['id'], password_hash((string) $result['data']['password'], PASSWORD_BCRYPT, ['cost' => 12]));
        Auth::clearMustChangePassword();
        AuditService::log('password.changed', 'auth', 'update', 'Password changed (forced rotation completed).', [], $request);

        Session::flash('success', 'Password updated. Welcome back!');
        return Response::redirect(url('/'));
    }
}
