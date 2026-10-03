<?php

declare(strict_types=1);

/**
 * New abilities for the access-control phase: approval workflows
 * (appointments / laboratory / billing / payments) and user archiving.
 *
 * Data lives in seeders as the source of truth; this migration keeps
 * EXISTING databases consistent without a reseed (INSERT ... ON DUPLICATE
 * keeps it idempotent), and grants sensible role defaults.
 */

return [
    'up' => static function (PDO $pdo): void {
        $newPermissions = [
            ['appointments', 'approve', 'Approve Appointments'],
            ['laboratory',   'approve', 'Approve Lab Results'],
            ['billing',      'approve', 'Approve Invoices'],
            ['payments',     'approve', 'Approve Payments'],
            ['users',        'archive', 'Archive Users'],
        ];

        $insert = $pdo->prepare(
            'INSERT INTO permissions (module, action, name, label, description)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label)'
        );
        foreach ($newPermissions as [$module, $action, $label]) {
            $insert->execute([
                $module,
                $action,
                "{$module}.{$action}",
                $label,
                "Allows the holder to {$action} records in the {$module} module.",
            ]);
        }

        // Role defaults: administrator (full admin scope), plus domain owners.
        $grant = $pdo->prepare(
            'INSERT IGNORE INTO permission_role (permission_id, role_id)
             SELECT p.id, r.id FROM permissions p
             CROSS JOIN roles r
             WHERE p.name = ? AND r.slug = ?'
        );
        $defaults = [
            ['appointments.approve', 'administrator'],
            ['appointments.approve', 'doctor'],
            ['laboratory.approve',   'administrator'],
            ['laboratory.approve',   'doctor'],
            ['billing.approve',      'administrator'],
            ['billing.approve',      'accountant'],
            ['payments.approve',     'administrator'],
            ['payments.approve',     'accountant'],
            ['users.archive',        'administrator'],
        ];
        foreach ($defaults as [$permission, $role]) {
            $grant->execute([$permission, $role]);
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec(
            "DELETE FROM permission_role WHERE permission_id IN (
                SELECT id FROM permissions WHERE name IN (
                    'appointments.approve', 'laboratory.approve', 'billing.approve',
                    'payments.approve', 'users.archive'
                )
            )"
        );
        $pdo->exec(
            "DELETE FROM permissions WHERE name IN (
                'appointments.approve', 'laboratory.approve', 'billing.approve',
                'payments.approve', 'users.archive'
            )"
        );
    },
];
