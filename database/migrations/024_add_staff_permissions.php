<?php

declare(strict_types=1);

/**
 * Adds the `staff` permission module (view / create / update / delete) and
 * grants it — plus full doctors/departments grants — to the administrator
 * role. Receptionists get staff.view + doctors.view so they can look up
 * who is on shift. Pharmacists / lab-techs get read access to their own
 * department's staff directory.
 *
 * Note: the doctors.* and departments.* permissions already exist in the
 * PermissionSeeder catalogue; only the staff.* module is new here.
 */

return [
    'up' => static function (PDO $pdo): void {
        // 1) Seed the staff permission module if it is missing.
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE module = ?');
        $stmt->execute(['staff']);
        if ((int) $stmt->fetchColumn() === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO permissions (module, action, name, label, description)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $insert->execute([
                    'staff',
                    $action,
                    "staff.{$action}",
                    ucfirst($action) . ' Staff',
                    "Allows the holder to {$action} staff employment records.",
                ]);
            }
        }

        // 2) Resolve role ids.
        $roles = [];
        foreach ($pdo->query('SELECT id, slug FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roles[$r['slug']] = (int) $r['id'];
        }

        // 3) Resolve permission ids grouped by module and by name.
        $byModule = [];
        $byName = [];
        foreach ($pdo->query('SELECT id, module, action, name FROM permissions')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $byModule[$p['module']][] = (int) $p['id'];
            $byName[$p['name']] = (int) $p['id'];
        }

        $grant = $pdo->prepare('INSERT IGNORE INTO permission_role (permission_id, role_id) VALUES (?, ?)');

        $matrix = [
            'administrator' => ['staff.*', 'doctors.*', 'departments.*'],
            'receptionist'   => ['staff.view', 'doctors.view', 'departments.view'],
            'nurse'          => ['staff.view', 'departments.view'],
            'doctor'         => ['staff.view', 'departments.view'],
            'pharmacist'     => ['departments.view'],
            'lab-technician' => ['departments.view'],
            'accountant'     => ['staff.view', 'departments.view'],
        ];

        foreach ($matrix as $slug => $patterns) {
            $roleId = $roles[$slug] ?? null;
            if ($roleId === null) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (str_ends_with($pattern, '.*')) {
                    $module = substr($pattern, 0, -2);
                    foreach ($byModule[$module] ?? [] as $permissionId) {
                        $grant->execute([$permissionId, $roleId]);
                    }
                } elseif (isset($byName[$pattern])) {
                    $grant->execute([$byName[$pattern], $roleId]);
                }
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DELETE FROM permission_role WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'staff')");
        $pdo->exec("DELETE FROM permissions WHERE module = 'staff'");
    },
];
