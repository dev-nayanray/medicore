<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Demo staff accounts for development. All use the password shown in
 * the README and on the login screen hint box.
 *
 *   admin@medicore.test  /  Admin@12345   (Super Admin)
 */
final class UserSeeder extends Seeder
{
    public static function label(): string { return 'Users'; }
    public static function order(): int { return 130; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'role_user', 'users');

        $hash = password_hash('Admin@12345', PASSWORD_BCRYPT, ['cost' => 12]);
        $altHash = password_hash('Staff@12345', PASSWORD_BCRYPT, ['cost' => 12]);

        $users = [
            // name, email, password, phone, roles, active, last_login (relative days ago)
            ['Ayesha Rahman',     'admin@medicore.test',      $hash,     '+880 1711 200110', ['super-admin'],   1, 0],
            ['Dr. Sarah Chen',    'sarah.chen@medicore.test', $altHash,  '+880 1811 300220', ['doctor'],        1, 1],
            ['Dr. Imran Hossain', 'imran@medicore.test',      $altHash,  '+880 1911 400330', ['doctor'],        1, 2],
            ['Farhana Akter',     'farhana@medicore.test',    $altHash,  '+880 1611 500440', ['nurse'],         1, 0],
            ['Rahim Uddin',       'rahim@medicore.test',      $altHash,  '+880 1521 600550', ['receptionist'],  1, 3],
            ['Nusrat Jahan',      'nusrat@medicore.test',     $altHash,  '+880 1731 700660', ['pharmacist'],    1, 5],
            ['Tanvir Islam',      'tanvir@medicore.test',     $altHash,  '+880 1881 800770', ['lab-technician'],1, 4],
            ['Mahin Khan',        'mahin@medicore.test',      $altHash,  '+880 1991 900880', ['accountant'],    1, 1],
            ['Omar Farooq',       'omar@medicore.test',       $altHash,  '+880 1741 100990', ['administrator'], 0, 30], // deactivated example
        ];

        $insertUser = $db->prepare(
            'INSERT INTO users (name, email, password_hash, phone, is_active, last_login_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $findRole = $db->prepare('SELECT id FROM roles WHERE slug = ?');
        $insertRole = $db->prepare('INSERT IGNORE INTO role_user (user_id, role_id) VALUES (?, ?)');

        foreach ($users as [$name, $email, $password, $phone, $roles, $active, $lastLoginDaysAgo]) {
            $lastLogin = $lastLoginDaysAgo > 0
                ? date('Y-m-d H:i:s', strtotime("-{$lastLoginDaysAgo} days", strtotime('-2 hours')))
                : date('Y-m-d H:i:s', strtotime('-2 hours'));
            $createdAt = date('Y-m-d H:i:s', strtotime('-' . random_int(45, 180) . ' days'));

            $insertUser->execute([$name, $email, $password, $phone, $active, $lastLogin, $createdAt]);
            $userId = (int) $db->lastInsertId();

            foreach ($roles as $slug) {
                $findRole->execute([$slug]);
                $roleId = $findRole->fetchColumn();
                if ($roleId !== false) {
                    $insertRole->execute([$userId, (int) $roleId]);
                }
            }
        }
    }
}
