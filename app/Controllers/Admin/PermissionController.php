<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Permission;

/**
 * Permission catalogue browser — read-only in phase 2 (the catalogue is
 * code-defined; new abilities ship with migrations + seeders).
 */
final class PermissionController extends Controller
{
    public function index(Request $request): string
    {
        // Per-permission role holders, aggregated for the badges.
        $roleMap = [];
        foreach (Database::query(
            'SELECT p.id AS permission_id, r.name AS role_name, r.slug AS role_slug
             FROM permissions p
             INNER JOIN permission_role pr ON pr.permission_id = p.id
             INNER JOIN roles r ON r.id = pr.role_id
             ORDER BY r.id'
        ) as $row) {
            $roleMap[(int) $row['permission_id']][] = ['name' => (string) $row['role_name'], 'slug' => (string) $row['role_slug']];
        }

        // Users holding direct overrides per permission.
        $overrideMap = [];
        foreach (Database::query(
            'SELECT pu.permission_id, COUNT(*) AS n FROM permission_user pu GROUP BY pu.permission_id'
        ) as $row) {
            $overrideMap[(int) $row['permission_id']] = (int) $row['n'];
        }

        return Response::html(view('admin/permissions', [
            'permissions' => Permission::groupedByModule(),
            'roleMap'     => $roleMap,
            'overrideMap' => $overrideMap,
            'totalCount'  => Permission::count(),
        ]));
    }
}
