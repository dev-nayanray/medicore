<?php
declare(strict_types=1);
/**
 * Adds admissions permissions (view/create/update/delete) and grants
 * beds + admissions to the appropriate roles. Reports permissions
 * already exist in the PermissionSeeder.
 */
return [
    'up' => static function (PDO $pdo): void {
        $check = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE module = ? AND action = ?');
        $insert = $pdo->prepare('INSERT INTO permissions (module, action, name, label, description) VALUES (?, ?, ?, ?, ?)');
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $check->execute(['admissions', $action]);
            if ((int) $check->fetchColumn() === 0) {
                $insert->execute(['admissions', $action, "admissions.{$action}", ucfirst($action) . ' Admissions', "Allows the holder to {$action} patient admission records."]);
            }
        }

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
            'administrator'  => ['admissions.*', 'beds.view', 'beds.update', 'reports.*'],
            'doctor'         => ['admissions.view', 'admissions.create', 'admissions.update', 'beds.view', 'reports.view'],
            'nurse'          => ['admissions.view', 'admissions.update', 'beds.view', 'beds.update', 'reports.view'],
            'receptionist'   => ['admissions.view', 'admissions.create', 'beds.view', 'reports.view'],
            'pharmacist'     => ['reports.view'],
            'lab-technician' => ['reports.view'],
            'accountant'     => ['reports.view', 'reports.export', 'admissions.view'],
        ];
        foreach ($matrix as $slug => $patterns) {
            $roleId = $roles[$slug] ?? null;
            if ($roleId === null) continue;
            foreach ($patterns as $pattern) {
                if (str_ends_with($pattern, '.*')) {
                    $module = substr($pattern, 0, -2);
                    foreach ($byModule[$module] ?? [] as $pid) { $grant->execute([$pid, $roleId]); }
                } elseif (isset($byName[$pattern])) {
                    $grant->execute([$byName[$pattern], $roleId]);
                }
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DELETE FROM permission_role WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'admissions')");
        $pdo->exec("DELETE FROM permissions WHERE module = 'admissions'");
    },
];
