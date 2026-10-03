<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\LoginAttempt;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use App\Services\UserService;

/**
 * User management — searchable/filterable directory + create / edit /
 * activate / archive / delete with server-side authorization on every
 * action (route middleware can:users.* plus UserService protections).
 */
final class UserController extends Controller
{
    public function index(Request $request): string
    {
        $search = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', 'not_archived'));
        if (!in_array($status, ['not_archived', 'active', 'inactive', 'archived', 'all'], true)) {
            $status = 'not_archived';
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 10)));

        $data = User::paginateWithRoles($search, $role, $status, $page, $perPage);

        $query = array_filter(['q' => $search, 'role' => $role, 'status' => $status], static fn ($v) => $v !== '');

        return Response::html(view('admin/users/index', [
            'users'   => $data['rows'],
            'total'   => $data['total'],
            'page'    => $data['page'],
            'pages'   => $data['pages'],
            'perPage' => $data['perPage'],
            'search'  => $search,
            'role'    => $role,
            'status'  => $status,
            'roles'   => Role::allWithCounts(),
            'baseUrl' => url('/admin/users') . ($query !== [] ? '?' . http_build_query($query) . '&' : '?'),
        ]));
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------
    public function create(Request $request): string
    {
        return Response::html(view('admin/users/form', [
            'user'         => null,
            'userRoles'    => [],
            'overrides'    => [],
            'roles'        => Role::allWithCounts(),
            'permissions'  => \App\Models\Permission::groupedByModule(),
            'mayManageSuper' => Auth::hasRole('super-admin'),
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'name'     => 'required|min:2|max:150',
            'email'    => 'required|email|max:190|unique:users,email',
            'phone'    => 'nullable|max:30',
            'password' => 'required|strong|confirmed',
        ]);
        $data = $result['data'];

        $roles = $this->extractRoles($request);
        if (!UserService::mayAssignRoles($roles)) {
            Session::put('_errors', ['roles' => 'Only a Super Admin may assign the Super Admin role.']);
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/users/create'));
        }

        $payload = [
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'password' => (string) $data['password'],
            'phone' => $data['phone'] !== '' ? (string) $data['phone'] : null,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'must_change_password' => $request->input('must_change_password') ? 1 : 0,
            'roles' => $roles,
            'overrides' => $this->extractOverrides($request),
        ];

        $created = UserService::create($payload, $request);
        Session::flash('success', 'User account created for ' . $data['email'] . '.');

        return Response::redirect(url('/admin/users'));
    }

    // ------------------------------------------------------------------
    // Edit / update
    // ------------------------------------------------------------------
    public function edit(Request $request, string $id): string
    {
        $user = User::find((int) $id);
        if ($user === null) {
            throw HttpException::notFound('User not found.');
        }

        return Response::html(view('admin/users/form', [
            'user'           => $user,
            'userRoles'      => User::roleSlugs((int) $user['id']),
            'overrides'      => User::directOverrides((int) $user['id']),
            'roles'          => Role::allWithCounts(),
            'permissions'    => \App\Models\Permission::groupedByModule(),
            'mayManageSuper' => Auth::hasRole('super-admin') || !in_array('super-admin', User::roleSlugs((int) $user['id']), true),
            'recentLogins'   => LoginAttempt::recentForUser((int) $user['id'], 6),
        ]));
    }

    public function update(Request $request, string $id): string
    {
        $userId = (int) $id;
        $user = User::find($userId);
        if ($user === null) {
            throw HttpException::notFound('User not found.');
        }

        $rules = [
            'name'  => 'required|min:2|max:150',
            'email' => 'required|email|max:190|unique:users,email,' . $userId,
            'phone' => 'nullable|max:30',
            'password' => 'nullable|strong|confirmed',
        ];
        $result = $this->validate($request, $rules);
        $data = $result['data'];

        $roles = $this->extractRoles($request);
        if (!UserService::mayAssignRoles($roles)) {
            Session::put('_errors', ['roles' => 'Only a Super Admin may assign the Super Admin role.']);
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/users/' . $userId . '/edit'));
        }

        $payload = [
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => $data['phone'] !== '' ? (string) $data['phone'] : null,
            'password' => $data['password'] !== '' && $data['password'] !== null ? (string) $data['password'] : null,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'must_change_password' => $request->input('must_change_password') ? 1 : 0,
            'roles' => $roles,
            'overrides' => $this->extractOverrides($request),
        ];

        $updated = UserService::update($userId, $payload, $request);
        if (!$updated['ok']) {
            Session::flash('error', $updated['error'] ?? 'Update failed.');
            return Response::redirect(url('/admin/users/' . $userId . '/edit'));
        }

        Session::flash('success', 'User updated.');
        return Response::redirect(url('/admin/users'));
    }

    // ------------------------------------------------------------------
    // Lifecycle actions
    // ------------------------------------------------------------------
    public function activate(Request $request, string $id): string
    {
        return $this->lifecycle($request, (int) $id, static fn (int $uid, Request $r) => UserService::setActive($uid, true, $r), 'User activated.');
    }

    public function deactivate(Request $request, string $id): string
    {
        return $this->lifecycle($request, (int) $id, static fn (int $uid, Request $r) => UserService::setActive($uid, false, $r), 'User deactivated — they can no longer sign in.');
    }

    public function archive(Request $request, string $id): string
    {
        return $this->lifecycle($request, (int) $id, static fn (int $uid, Request $r) => UserService::archive($uid, $r), 'User archived.');
    }

    public function restore(Request $request, string $id): string
    {
        return $this->lifecycle($request, (int) $id, static fn (int $uid, Request $r) => UserService::restore($uid, $r), 'User restored.');
    }

    public function destroy(Request $request, string $id): string
    {
        return $this->lifecycle($request, (int) $id, static fn (int $uid, Request $r) => UserService::delete($uid, $r), 'User permanently deleted.');
    }

    private function lifecycle(Request $request, int $userId, callable $action, string $successMessage): string
    {
        $result = $action($userId, $request);
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? 'Action failed.');
        } else {
            Session::flash('success', $successMessage);
        }
        return Response::redirect(url('/admin/users'));
    }

    // ------------------------------------------------------------------
    // Input extraction helpers
    // ------------------------------------------------------------------
    /** @return array<int, int> */
    private function extractRoles(Request $request): array
    {
        $raw = (array) ($request->input('roles') ?? []);
        $ids = array_values(array_filter(array_map('intval', $raw), static fn ($v) => $v > 0));

        // Reject tampered IDs that don't exist.
        if ($ids === []) {
            return [];
        }
        $valid = array_map(
            static fn (array $r): int => (int) $r['id'],
            \App\Core\Database::query('SELECT id FROM roles WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids)
        );
        return array_values(array_intersect($ids, $valid));
    }

    /** @return array<int, string> permission_id => allow|deny */
    private function extractOverrides(Request $request): array
    {
        $raw = (array) ($request->input('overrides') ?? []);
        $overrides = [];
        foreach ($raw as $permissionId => $type) {
            $permissionId = (int) $permissionId;
            if ($permissionId > 0 && in_array($type, ['allow', 'deny'], true)) {
                $overrides[$permissionId] = $type;
            }
        }
        return $overrides;
    }
}
