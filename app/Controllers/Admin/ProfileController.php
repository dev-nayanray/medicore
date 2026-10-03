<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuditService;

/**
 * Own-account profile: edit details + change password.
 */
final class ProfileController extends Controller
{
    public function index(Request $request): string
    {
        $user = User::find((int) Auth::id());
        if ($user === null) {
            Auth::logout();
            return Response::redirect(url('/login'));
        }

        return Response::html(view('admin/profile', [
            'user'   => $user,
            'roles'  => User::roleSlugs((int) $user['id']),
        ]));
    }

    /** Login history + security events for the signed-in account. */
    public function activity(Request $request): string
    {
        $userId = (int) Auth::id();
        $user = User::find($userId);
        if ($user === null) {
            Auth::logout();
            return Response::redirect(url('/login'));
        }

        return Response::html(view('admin/profile/activity', [
            'user'    => $user,
            'attempts' => \App\Models\LoginAttempt::recentForUser($userId, 20),
            'events'   => \App\Core\Database::query(
                'SELECT event, module, description, created_at FROM audit_logs
                 WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 15',
                [$userId]
            ),
            'stats' => [
                'success' => (int) \App\Core\Database::scalar('SELECT COUNT(*) FROM login_attempts WHERE user_id = ? AND successful = 1', [$userId]),
                'failed'  => (int) \App\Core\Database::scalar('SELECT COUNT(*) FROM login_attempts WHERE user_id = ? AND successful = 0', [$userId]),
                'last_pw' => (string) ($user['password_changed_at'] ?? $user['created_at']),
            ],
        ]));
    }

    public function update(Request $request): string
    {
        $result = $this->validate($request, [
            'name'  => 'required|min:2|max:150',
            'phone' => 'nullable|max:30',
        ]);
        $data = $result['data'];

        User::updateProfile((int) Auth::id(), (string) $data['name'], $data['phone'] !== '' ? (string) $data['phone'] : null);

        // Keep the session snapshot in sync.
        $session = Auth::user();
        if ($session !== null) {
            $session['name'] = (string) $data['name'];
            Session::put('_auth_user', $session);
        }

        AuditService::log('profile.updated', 'users', 'update', 'Profile details updated.', [], $request);
        Session::flash('success', 'Profile updated.');

        return Response::redirect(url('/admin/profile'));
    }

    public function updatePassword(Request $request): string
    {
        $result = $this->validate($request, [
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);
        $data = $result['data'];

        $user = User::find((int) Auth::id());
        if ($user === null || !password_verify((string) $data['current_password'], (string) $user['password_hash'])) {
            Session::put('_errors', ['current_password' => 'Current password is incorrect.']);
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/profile'));
        }

        User::updatePassword((int) $user['id'], password_hash((string) $data['password'], PASSWORD_BCRYPT, ['cost' => 12]));

        AuditService::log('password.changed', 'auth', 'update', 'Password changed.', [], $request);
        Session::flash('success', 'Password changed successfully.');

        return Response::redirect(url('/admin/profile'));
    }
}
