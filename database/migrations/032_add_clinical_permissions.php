<?php

declare(strict_types=1);

/**
 * Adds the clinical permission modules: consultations (view/create/
 * update/finalize/amend) and prescriptions (view/create/update/
 * finalize). Grants them to the appropriate roles:
 * - doctor: full clinical access (own patients)
 * - administrator: full clinical access
 * - nurse: view consultations + prescriptions
 * - receptionist: view consultations (read-only)
 * - pharmacist: view prescriptions (for dispensing)
 */

return [
    'up' => static function (PDO $pdo): void {
        // 1) Seed the clinical permission modules if missing.
        $catalog = [
            'consultations' => ['view', 'create', 'update', 'finalize', 'amend'],
            'prescriptions' => ['view', 'create', 'update', 'finalize'],
        ];

        $check = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE module = ? AND action = ?');
        $insert = $pdo->prepare(
            'INSERT INTO permissions (module, action, name, label, description)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($catalog as $module => $actions) {
            foreach ($actions as $action) {
                $check->execute([$module, $action]);
                if ((int) $check->fetchColumn() === 0) {
                    $insert->execute([
                        $module, $action,
                        "{$module}.{$action}",
                        ucfirst($action) . ' ' . rtrim(ucfirst($module), 's'),
                        "Allows the holder to {$action} records in the {$module} module.",
                    ]);
                }
            }
        }

        // 2) Resolve role + permission ids.
        $roles = [];
        foreach ($pdo->query('SELECT id, slug FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roles[$r['slug']] = (int) $r['id'];
        }
        $byName = [];
        foreach ($pdo->query('SELECT id, module, action, name FROM permissions')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $byName[$p['name']] = (int) $p['id'];
        }
        $byModule = [];
        foreach ($pdo->query('SELECT id, module, action FROM permissions')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $byModule[$p['module']][] = (int) $p['id'];
        }

        $grant = $pdo->prepare('INSERT IGNORE INTO permission_role (permission_id, role_id) VALUES (?, ?)');

        $matrix = [
            'administrator' => ['consultations.*', 'prescriptions.*'],
            'doctor'         => ['consultations.*', 'prescriptions.*'],
            'nurse'          => ['consultations.view', 'prescriptions.view'],
            'receptionist'   => ['consultations.view'],
            'pharmacist'     => ['prescriptions.view', 'prescriptions.finalize'],
            'lab-technician' => [],
            'accountant'     => [],
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
        $pdo->exec("DELETE FROM permission_role WHERE permission_id IN (SELECT id FROM permissions WHERE module IN ('consultations','prescriptions'))");
        $pdo->exec("DELETE FROM permissions WHERE module IN ('consultations','prescriptions')");
    },
];
