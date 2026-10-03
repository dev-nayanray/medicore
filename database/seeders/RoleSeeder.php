<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Hospital role catalogue. `super-admin` bypasses every permission check.
 */
final class RoleSeeder extends Seeder
{
    public static function label(): string { return 'Roles'; }
    public static function order(): int { return 100; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'roles');

        $roles = [
            ['Super Admin',     'super-admin',    'Unrestricted access to every module and setting.', true],
            ['Administrator',   'administrator',  'Day-to-day administration: users, settings, reports.', true],
            ['Doctor',          'doctor',         'Clinical staff — patients, appointments, prescriptions.', false],
            ['Nurse',           'nurse',          'Ward care — patients, vitals, bed management.', false],
            ['Receptionist',    'receptionist',   'Front desk — registrations, appointments, billing.', false],
            ['Pharmacist',      'pharmacist',     'Pharmacy — inventory, dispensing, purchase orders.', false],
            ['Lab Technician',  'lab-technician', 'Laboratory — tests, samples, result entry.', false],
            ['Accountant',      'accountant',     'Finance — invoices, payments, financial reports.', false],
        ];

        $stmt = $db->prepare(
            'INSERT INTO roles (name, slug, description, is_system) VALUES (?, ?, ?, ?)'
        );
        foreach ($roles as [$name, $slug, $description, $isSystem]) {
            $stmt->execute([$name, $slug, $description, (int) $isSystem]);
        }
    }
}
