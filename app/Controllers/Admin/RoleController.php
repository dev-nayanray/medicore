<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;

/**
 * Role management — CRUD with a configurable permission matrix.
 * System roles can be edited but never deleted; roles still assigned to
 * users must be cleared first.
 */
final class RoleController extends Controller
{
    public function index(Request $request): string
    {
        return Response::html(view('admin/roles/index', [
            'roles'       => Role::allWithCounts(),
            'permissions' => Permission::groupedByModule(),
        ]));
    }

    public function create(Request $request): string
    {
        return Response::html(view('admin/roles/form', [
            'role'         => null,
            'rolePerms'    => [],
            'permissions'  => Permission::groupedByModule(),
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'name'        => 'required|min:2|max:100|unique:roles,name',
            'description' => 'nullable|max:255',
        ]);
        $data = $result['data'];

        $roleId = Role::create((string) $data['name'], (string) ($data['description'] ?? ''));
        Role::setPermissions($roleId, $this->extractPermissions($request));

        AuditService::log('role.created', 'roles', 'create', "Role {$data['name']} created.", ['role_id' => $roleId], $request);
        Session::flash('success', 'Role created.');

        return Response::redirect(url('/admin/roles'));
    }

    public function edit(Request $request, string $id): string
    {
        $role = Role::find((int) $id);
        if ($role === null) {
            throw HttpException::notFound('Role not found.');
        }

        return Response::html(view('admin/roles/form', [
            'role'        => $role,
            'rolePerms'   => Role::permissionIds((int) $role['id']),
            'permissions' => Permission::groupedByModule(),
        ]));
    }

    public function update(Request $request, string $id): string
    {
        $roleId = (int) $id;
        $role = Role::find($roleId);
        if ($role === null) {
            throw HttpException::notFound('Role not found.');
        }
        if ((string) $role['slug'] === 'super-admin') {
            Session::flash('error', 'The Super Admin role cannot be modified.');
            return Response::redirect(url('/admin/roles'));
        }

        $result = $this->validate($request, [
            'name'        => 'required|min:2|max:100|unique:roles,name,' . $roleId,
            'description' => 'nullable|max:255',
        ]);
        $data = $result['data'];

        Role::updateDetails($roleId, (string) $data['name'], (string) ($data['description'] ?? ''));
        Role::setPermissions($roleId, $this->extractPermissions($request));

        // Affected users re-sync their session snapshot within minutes
        // (Auth::PERMISSION_TTL) or on next sign-in.
        AuditService::log('role.updated', 'roles', 'update', "Role {$data['name']} updated.", ['role_id' => $roleId], $request);
        Session::flash('success', 'Role updated.');

        return Response::redirect(url('/admin/roles'));
    }

    public function destroy(Request $request, string $id): string
    {
        $roleId = (int) $id;

        if (!Role::safeDelete($roleId)) {
            Session::flash('error', 'System roles and roles still assigned to users cannot be deleted.');
            return Response::redirect(url('/admin/roles'));
        }

        AuditService::log('role.deleted', 'roles', 'delete', "Role #{$roleId} deleted.", ['role_id' => $roleId], $request);
        Session::flash('success', 'Role deleted.');

        return Response::redirect(url('/admin/roles'));
    }

    /** @return array<int, int> validated permission IDs */
    private function extractPermissions(Request $request): array
    {
        $raw = (array) ($request->input('permissions') ?? []);
        $ids = array_values(array_filter(array_map('intval', $raw), static fn ($v) => $v > 0));
        if ($ids === []) {
            return [];
        }

        $rows = \App\Core\Database::query(
            'SELECT id FROM permissions WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );
        $valid = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        return array_values(array_intersect($ids, $valid));
    }
}
