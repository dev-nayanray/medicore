<?php

declare(strict_types=1);

/**
 * Hospital settings — self-describing key/value catalogue. The settings
 * screen renders groups straight from this table; adding a row extends
 * the UI without code changes.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `hospital_settings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `key` VARCHAR(100) NOT NULL,
                `value` TEXT NULL DEFAULT NULL,
                `type` ENUM('string','text','number','boolean','date','json','time','select') NOT NULL DEFAULT 'string',
                `group` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Settings screen tab/group',
                `label` VARCHAR(150) NOT NULL,
                `description` VARCHAR(255) NULL DEFAULT NULL,
                `options` JSON NULL DEFAULT NULL COMMENT 'For type=select: [{value,label}]',
                `is_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = safe to expose outside admin',
                `updated_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_settings_key` (`key`),
                KEY `idx_settings_group` (`group`),
                CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hospital configuration'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `hospital_settings`');
    },
];
