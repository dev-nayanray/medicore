<?php

declare(strict_types=1);

/**
 * Adds the `inventory` permission module (view/create/update/delete)
 * and grants pharmacy + laboratory + inventory to the appropriate roles.
 */

return [
    'up' => static function (PDO $pdo): void {
        // 1) Seed the inventory permission module if missing.
        $check = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE module = ? AND action = ?');
        $insert = $pdo->prepare(
            'INSERT INTO permissions (module, action, name, label, description)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $check->execute(['inventory', $action]);
            if ((int) $check->fetchColumn() === 0) {
                $insert->execute([
                    'inventory', $action,
                    "inventory.{$action}",
                    ucfirst($action) . ' Inventory',
                    "Allows the holder to {$action} inventory records.",
                ]);
            }
        }

        // 2) Resolve role + permission ids.
        $roles = [];
        foreach ($pdo->query('SELECT id, slug FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roles[$r['slug']] = (int) $r['id'];
        }
        $byName = [];
        foreach ($pdo->query('SELECT id, name FROM permissions')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $byName[$p['name']] = (int) $p['id'];
        }
        $byModule = [];
        foreach ($pdo->query('SELECT id, module FROM permissions')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $byModule[$p['module']][] = (int) $p['id'];
        }

        $grant = $pdo->prepare('INSERT IGNORE INTO permission_role (permission_id, role_id) VALUES (?, ?)');

        $matrix = [
            'administrator'  => ['pharmacy.*', 'laboratory.*', 'inventory.*'],
            'doctor'         => ['laboratory.view', 'laboratory.create', 'pharmacy.view', 'inventory.view'],
            'nurse'          => ['laboratory.view', 'pharmacy.view', 'inventory.view'],
            'receptionist'   => ['laboratory.view', 'pharmacy.view', 'inventory.view'],
            'pharmacist'     => ['pharmacy.*', 'inventory.view'],
            'lab-technician' => ['laboratory.*', 'inventory.view'],
            'accountant'     => ['inventory.view', 'pharmacy.view'],
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
        $pdo->exec("DELETE FROM permission_role WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'inventory')");
        $pdo->exec("DELETE FROM permissions WHERE module = 'inventory'");
    },
];
