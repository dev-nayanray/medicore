<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Permission catalogue. Includes abilities for modules that arrive in
 * later phases (patients, appointments, ...) so role matrices and UI
 * gating are wired from day one.
 */
final class PermissionSeeder extends Seeder
{
    public static function label(): string { return 'Permissions'; }
    public static function order(): int { return 110; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'permission_role', 'permissions');

        // module => [actions]
        $catalog = [
            'dashboard'   => ['view'],
            'users'       => ['view', 'create', 'update', 'delete', 'archive'],
            'roles'       => ['view', 'create', 'update', 'delete'],
            'settings'    => ['view', 'update'],
            'audit'       => ['view', 'export'],
            'patients'    => ['view', 'create', 'update', 'delete'],
            'appointments'=> ['view', 'create', 'update', 'delete', 'approve'],
            'doctors'     => ['view', 'create', 'update', 'delete'],
            'departments' => ['view', 'create', 'update', 'delete'],
            'beds'        => ['view', 'update'],
            'laboratory'  => ['view', 'create', 'update', 'approve'],
            'pharmacy'    => ['view', 'create', 'update'],
            'billing'     => ['view', 'create', 'update', 'delete', 'approve'],
            'payments'    => ['view', 'create', 'approve'],
            'reports'     => ['view', 'export'],
        ];

        $stmt = $db->prepare(
            'INSERT INTO permissions (module, action, name, label, description)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($catalog as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $stmt->execute([
                    $module,
                    $action,
                    $name,
                    ucfirst($action) . ' ' . rtrim(ucfirst($module), 's'),
                    "Allows the holder to {$action} records in the {$module} module.",
                ]);
            }
        }
    }
}
