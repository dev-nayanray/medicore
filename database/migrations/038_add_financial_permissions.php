<?php

declare(strict_types=1);

/**
 * Adds the `expenses` permission module (view/create/update/delete) and
 * grants full billing/payments access to the administrator role (was
 * missing from the original seed). Receptionists get payments.create so
 * they can collect cash at the front desk.
 */

return [
    'up' => static function (PDO $pdo): void {
        // 1) Seed the expenses permission module if missing.
        $check = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE module = ? AND action = ?');
        $insert = $pdo->prepare(
            'INSERT INTO permissions (module, action, name, label, description)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $check->execute(['expenses', $action]);
            if ((int) $check->fetchColumn() === 0) {
                $insert->execute([
                    'expenses', $action,
                    "expenses.{$action}",
                    ucfirst($action) . ' Expenses',
                    "Allows the holder to {$action} hospital expense records.",
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
            'administrator' => ['billing.*', 'payments.*', 'expenses.*'],
            'accountant'    => ['billing.*', 'payments.*', 'expenses.*'],
            'receptionist'  => ['billing.view', 'billing.create', 'payments.view', 'payments.create', 'expenses.view'],
            'doctor'        => ['billing.view'],
            'nurse'         => [],
            'pharmacist'    => [],
            'lab-technician' => [],
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
        $pdo->exec("DELETE FROM permission_role WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'expenses')");
        $pdo->exec("DELETE FROM permissions WHERE module = 'expenses'");
    },
];
