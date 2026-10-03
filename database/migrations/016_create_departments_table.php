<?php

declare(strict_types=1);

/**
 * Hospital departments. head_doctor_id references users (a doctor's account)
 * rather than the doctors table to avoid a circular FK with doctors.department_id.
 * Archiving is soft so department history and references survive.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `departments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL COMMENT 'URL-safe unique key',
                `description` TEXT NULL,
                `head_doctor_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id (the doctor account)',
                `location` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Floor / wing / room block',
                `phone` VARCHAR(30) NULL DEFAULT NULL,
                `email` VARCHAR(190) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `archived_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_departments_slug` (`slug`),
                KEY `idx_departments_active` (`is_active`),
                KEY `idx_departments_archived` (`archived_at`),
                CONSTRAINT `fk_departments_head` FOREIGN KEY (`head_doctor_id`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hospital departments'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `departments`');
    },
];
