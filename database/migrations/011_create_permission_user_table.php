<?php

declare(strict_types=1);

/**
 * Direct per-user permission overrides.
 * type='allow' grants the ability even without the role;
 * type='deny'  revokes it even when a role would grant it.
 * Deny always wins over allow; super-admin bypasses both.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `permission_user` (
                `user_id` INT UNSIGNED NOT NULL,
                `permission_id` INT UNSIGNED NOT NULL,
                `type` ENUM('allow','deny') NOT NULL DEFAULT 'allow',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`, `permission_id`),
                KEY `fk_pu_permission` (`permission_id`),
                CONSTRAINT `fk_pu_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_pu_permission` FOREIGN KEY (`permission_id`)
                    REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User-specific permission overrides'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `permission_user`');
    },
];
