<?php

declare(strict_types=1);

/**
 * User lifecycle & password-management columns:
 *  - archived_at       soft-delete timestamp (archived users can't sign in
 *                      and disappear from default lists)
 *  - must_change_password  admin-forced password rotation flag
 *  - password_changed_at  last successful password change
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "ALTER TABLE `users`
                ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `last_login_at`,
                ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `archived_at`,
                ADD COLUMN `password_changed_at` DATETIME NULL DEFAULT NULL AFTER `must_change_password`,
                ADD KEY `idx_users_archived` (`archived_at`)"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec(
            "ALTER TABLE `users`
                DROP KEY `idx_users_archived`,
                DROP COLUMN `archived_at`,
                DROP COLUMN `must_change_password`,
                DROP COLUMN `password_changed_at`"
        );
    },
];
