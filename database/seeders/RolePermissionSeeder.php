<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Maps permissions to roles. Super Admin is intentionally EMPTY — the
 * Auth layer short-circuits every check for that slug.
 */
final class RolePermissionSeeder extends Seeder
{
    public static function label(): string { return 'Role permissions'; }
    public static function order(): int { return 120; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'permission_role');

        /** role => [exact names or "module.*" wildcards] */
        $matrix = [
            'administrator' => [
                'dashboard.view', 'users.*', 'roles.view', 'settings.*', 'audit.*',
                'patients.view', 'appointments.*', 'doctors.*', 'departments.*', 'staff.*',
                'consultations.*', 'prescriptions.*',
                'beds.view', 'reports.*',
            ],
            'doctor' => [
                'dashboard.view', 'patients.*', 'appointments.*', 'laboratory.view',
                'laboratory.update', 'laboratory.approve', 'pharmacy.view', 'reports.view',
                'staff.view', 'departments.view',
                'consultations.*', 'prescriptions.*',
            ],
            'nurse' => [
                'dashboard.view', 'patients.view', 'patients.update', 'appointments.view',
                'beds.*', 'laboratory.view', 'staff.view', 'departments.view',
                'consultations.view', 'prescriptions.view',
            ],
            'receptionist' => [
                'dashboard.view', 'patients.*', 'appointments.*', 'doctors.view',
                'departments.view', 'staff.view', 'billing.view', 'billing.create', 'payments.view',
                'consultations.view',
            ],
            'pharmacist' => [
                'dashboard.view', 'pharmacy.*', 'departments.view',
                'prescriptions.view', 'prescriptions.finalize',
            ],
            'lab-technician' => [
                'dashboard.view', 'laboratory.*', 'departments.view',
            ],
            'accountant' => [
                'dashboard.view', 'billing.*', 'payments.*', 'reports.view', 'reports.export',
                'staff.view', 'departments.view',
            ],
        ];

        $allPermissions = $db->query('SELECT id, module, action, name FROM permissions')->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($allPermissions as $p) {
            $byName[$p['name']] = (int) $p['id'];
        }
        $byModule = [];
        foreach ($allPermissions as $p) {
            $byModule[$p['module']][] = (int) $p['id'];
        }

        $roleStmt = $db->prepare('SELECT id, slug FROM roles');
        $roleStmt->execute();
        $roles = [];
        foreach ($roleStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roles[$r['slug']] = (int) $r['id'];
        }

        $insert = $db->prepare(
            'INSERT IGNORE INTO permission_role (permission_id, role_id) VALUES (?, ?)'
        );

        foreach ($matrix as $roleSlug => $patterns) {
            $roleId = $roles[$roleSlug] ?? null;
            if ($roleId === null) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (str_ends_with($pattern, '.*')) {
                    $module = substr($pattern, 0, -2);
                    foreach ($byModule[$module] ?? [] as $permissionId) {
                        $insert->execute([$permissionId, $roleId]);
                    }
                } elseif (isset($byName[$pattern])) {
                    $insert->execute([$byName[$pattern], $roleId]);
                }
            }
        }
    }
}
