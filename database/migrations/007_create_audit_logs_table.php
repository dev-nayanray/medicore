<?php

declare(strict_types=1);

/**
 * Audit logs — append-only trail of security-relevant actions.
 * Powers the dashboard activity feed and the Audit Log browser.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `audit_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Actor; NULL = system',
                `event` VARCHAR(80) NOT NULL COMMENT 'login.success, settings.updated ...',
                `module` VARCHAR(50) NULL DEFAULT NULL COMMENT 'auth, users, settings ...',
                `action` VARCHAR(50) NULL DEFAULT NULL COMMENT 'create, update, delete, login ...',
                `description` VARCHAR(500) NULL DEFAULT NULL,
                `context` JSON NULL DEFAULT NULL COMMENT 'Structured before/after data',
                `ip_address` VARCHAR(45) NULL DEFAULT NULL,
                `user_agent` VARCHAR(255) NULL DEFAULT NULL,
                `url` VARCHAR(255) NULL DEFAULT NULL,
                `method` VARCHAR(10) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_audit_user_created` (`user_id`, `created_at`),
                KEY `idx_audit_event` (`event`),
                KEY `idx_audit_created` (`created_at`),
                CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable activity trail'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `audit_logs`');
    },
];
