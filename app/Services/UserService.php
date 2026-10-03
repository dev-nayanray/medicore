<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Models\Role;
use App\Models\User;

/**
 * User administration with self-protection and privilege-escalation rules:
 *
 *  - you cannot deactivate / archive / delete YOURSELF
 *  - only a super-admin may assign the super-admin role, edit a
 *    super-admin, deactivate or archive one
 *  - archiving requires zero active roles cleanup — it simply hides the
 *    account and blocks sign-in (reversible via restore)
 */
final class UserService
{
    /** @return array{ok: bool, error?: string, id?: int} */
    public static function create(array $data, Request $request): array
    {
        $userId = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'phone' => $data['phone'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 1),
            'must_change_password' => (int) ($data['must_change_password'] ?? 0),
        ])['id'];

        if (!empty($data['roles'])) {
            User::setRoles((int) $userId, (array) $data['roles']);
        }
        if (!empty($data['overrides'])) {
            User::setDirectPermissions((int) $userId, (array) $data['overrides']);
        }

        AuditService::log('user.created', 'users', 'create', "User account created for {$data['email']}.", [
            'email' => $data['email'],
            'roles' => array_values(array_map('intval', (array) ($data['roles'] ?? []))),
        ], $request);

        return ['ok' => true, 'id' => (int) $userId];
    }

    /** @return array{ok: bool, error?: string} */
    public static function update(int $userId, array $data, Request $request): array
    {
        $target = User::find($userId);
        if ($target === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if (!self::mayManage($target)) {
            return ['ok' => false, 'error' => 'Only a Super Admin may modify a Super Admin account.'];
        }

        User::updateDetails($userId, $data['name'], strtolower(trim($data['email'])), $data['phone'] ?? null);
        User::setActive($userId, (int) ($data['is_active'] ?? 1) === 1);

        // Optional password rotation from the edit screen.
        if (!empty($data['password'])) {
            User::updatePassword($userId, password_hash((string) $data['password'], PASSWORD_BCRYPT, ['cost' => 12]), !empty($data['must_change_password']));
        } elseif (array_key_exists('must_change_password', $data)) {
            Database::execute('UPDATE users SET must_change_password = ? WHERE id = ?', [(int) $data['must_change_password'], $userId]);
        }

        if (array_key_exists('roles', $data)) {
            User::setRoles($userId, (array) $data['roles']);
        }
        if (array_key_exists('overrides', $data)) {
            User::setDirectPermissions($userId, (array) $data['overrides']);
        }

        // If the target is the signed-in user, keep the session snapshot fresh.
        if (Auth::id() === $userId) {
            Auth::expireSnapshot();
        }

        AuditService::log('user.updated', 'users', 'update', "User {$data['email']} updated.", [
            'email' => $data['email'],
            'roles' => array_values(array_map('intval', (array) ($data['roles'] ?? []))),
        ], $request);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function setActive(int $userId, bool $active, Request $request): array
    {
        $target = User::find($userId);
        if ($target === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if ($userId === Auth::id()) {
            return ['ok' => false, 'error' => 'You cannot change your own account status.'];
        }
        if (!self::mayManage($target)) {
            return ['ok' => false, 'error' => 'Only a Super Admin may modify a Super Admin account.'];
        }

        User::setActive($userId, $active);
        AuditService::log(
            $active ? 'user.activated' : 'user.deactivated',
            'users',
            'update',
            ($active ? 'Activated' : 'Deactivated') . " account {$target['email']}.",
            ['email' => $target['email']],
            $request
        );
        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function archive(int $userId, Request $request): array
    {
        $target = User::find($userId);
        if ($target === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if ($userId === Auth::id()) {
            return ['ok' => false, 'error' => 'You cannot archive your own account.'];
        }
        if (!self::mayManage($target)) {
            return ['ok' => false, 'error' => 'Only a Super Admin may archive a Super Admin account.'];
        }

        User::archive($userId);
        AuditService::log('user.archived', 'users', 'archive', "Archived account {$target['email']}.", ['email' => $target['email']], $request);
        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function restore(int $userId, Request $request): array
    {
        $target = User::find($userId);
        if ($target === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if (!self::mayManage($target)) {
            return ['ok' => false, 'error' => 'Only a Super Admin may restore a Super Admin account.'];
        }

        User::restore($userId);
        AuditService::log('user.restored', 'users', 'update', "Restored account {$target['email']}.", ['email' => $target['email']], $request);
        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function delete(int $userId, Request $request): array
    {
        $target = User::find($userId);
        if ($target === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if ($userId === Auth::id()) {
            return ['ok' => false, 'error' => 'You cannot delete your own account.'];
        }
        if (!self::mayManage($target)) {
            return ['ok' => false, 'error' => 'Only a Super Admin may delete a Super Admin account.'];
        }

        \App\Models\User::delete($userId);
        AuditService::log('user.deleted', 'users', 'delete', "Permanently deleted account {$target['email']}.", ['email' => $target['email']], $request);
        return ['ok' => true];
    }

    /**
     * Super-admin accounts may only be managed by super-admins, and only
     * super-admins may hand out the super-admin role.
     *
     * @param array<string, mixed> $target
     */
    public static function mayManage(array $target): bool
    {
        if (Auth::hasRole('super-admin')) {
            return true;
        }
        $targetRoles = User::roleSlugs((int) $target['id']);
        return !in_array('super-admin', $targetRoles, true);
    }

    /** May the caller assign the given role IDs (privilege escalation gate)? */
    public static function mayAssignRoles(array $roleIds): bool
    {
        if (Auth::hasRole('super-admin')) {
            return true;
        }
        if ($roleIds === []) {
            return true;
        }
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $count = (int) Database::scalar(
            "SELECT COUNT(*) FROM roles WHERE id IN ({$placeholders}) AND slug = 'super-admin'",
            array_map('intval', $roleIds)
        );
        return $count === 0;
    }
}
