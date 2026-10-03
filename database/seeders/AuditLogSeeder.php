<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Seeds a realistic audit trail — the dashboard "Recent Activity" feed
 * and the audit browser read from here. Records reflect the seeding
 * process itself plus plausible historical staff actions.
 */
final class AuditLogSeeder extends Seeder
{
    public static function label(): string { return 'Audit trail'; }
    public static function order(): int { return 150; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'audit_logs');

        $userId = static function (string $email) use ($db): ?int {
            static $map = null;
            if ($map === null) {
                $map = [];
                foreach ($db->query('SELECT id, email FROM users')->fetchAll(PDO::FETCH_ASSOC) as $u) {
                    $map[$u['email']] = (int) $u['id'];
                }
            }
            return $map[$email] ?? null;
        };

        // [actor email (null=system), event, module, action, description, context, days ago, hours offset]
        $entries = [
            [null,                   'system.seeded',   'system',   'seed',   'Database seeded with foundation demo data (roles, permissions, users, settings).', '{"batch":"phase-1"}', 0, 0],
            ['admin@medicore.test',  'role.created',    'roles',    'create', 'Role matrix provisioned for 7 hospital roles.', '{"count":8}', 0, 0],
            ['admin@medicore.test',  'settings.updated','settings', 'update', 'Hospital profile configured (name, contact, localization).', '{"keys":["hospital_name","timezone","currency"]}', 0, 0],

            ['rahim@medicore.test', 'login.success',   'auth',   'login',  'Receptionist signed in from front desk.', '{"device":"desktop"}', 3, 1],
            ['sarah.chen@medicore.test', 'login.success','auth',  'login', 'Doctor signed in.', '{"device":"desktop"}', 1, 2],
            ['farhana@medicore.test','login.success',   'auth',   'login', 'Nurse signed in from ward station 2.', '{"device":"tablet"}', 0, 5],
            ['imran@medicore.test', 'login.failed',     'auth',   'login', 'Failed sign-in attempt (wrong password).', '{"attempts":1}', 2, 9],
            ['mahin@medicore.test', 'login.success',   'auth',   'login', 'Accountant signed in.', '{"device":"desktop"}', 1, 4],
            ['admin@medicore.test', 'login.success',   'auth',   'login', 'Administrator signed in.', '{"device":"desktop"}', 0, 2],
            ['tanvir@medicore.test','login.success',   'auth',   'login', 'Lab technician signed in.', '{"device":"desktop"}', 4, 3],
            ['nusrat@medicore.test','login.success',   'auth',   'login', 'Pharmacist signed in.', '{"device":"desktop"}', 5, 6],
            ['rahim@medicore.test','login.success',   'auth',   'login', 'Receptionist signed in.', '{"device":"desktop"}', 6, 8],
            ['sarah.chen@medicore.test','login.success','auth', 'login', 'Doctor signed in.', '{"device":"mobile"}', 7, 5],
        ];

        $stmt = $db->prepare(
            'INSERT INTO audit_logs (user_id, event, module, action, description, context, ip_address, user_agent, url, method, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($entries as [$email, $event, $module, $action, $description, $context, $daysAgo, $hoursAgo]) {
            $ts = strtotime("-{$daysAgo} days -{$hoursAgo} hours");
            $stmt->execute([
                $email !== null ? $userId($email) : null,
                $event,
                $module,
                $action,
                $description,
                $context,
                '127.0.0.1',
                'MediCore Seeder (CLI)',
                url('/'),
                'CLI',
                date('Y-m-d H:i:s', $ts),
            ]);
        }
    }
}
