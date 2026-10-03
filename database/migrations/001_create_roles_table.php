<?php

declare(strict_types=1);

/**
 * Roles — RBAC backbone. `is_system` roles cannot be deleted from the UI.
 */

use App\Core\Database;

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `roles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL COMMENT 'Display name, e.g. Doctor',
                `slug` VARCHAR(100) NOT NULL COMMENT 'Machine name, e.g. doctor',
                `description` VARCHAR(255) NULL DEFAULT NULL,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = protected system role',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_roles_slug` (`slug`),
                KEY `idx_roles_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User roles'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `roles`');
    },
];
