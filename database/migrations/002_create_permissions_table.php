<?php

declare(strict_types=1);

/**
 * Permissions — granular abilities (module.action) assigned to roles.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `permissions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `module` VARCHAR(50) NOT NULL COMMENT 'users, patients, billing ...',
                `action` VARCHAR(50) NOT NULL COMMENT 'view, create, update, delete ...',
                `name` VARCHAR(120) NOT NULL COMMENT 'Full name: users.view',
                `label` VARCHAR(150) NOT NULL COMMENT 'Human label for UI',
                `description` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_permissions_name` (`name`),
                KEY `idx_permissions_module` (`module`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ability catalog'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `permissions`');
    },
];
