<?php

declare(strict_types=1);

/**
 * Doctor leave / time-off records. A pending/approved leave blocks the
 * doctor's schedule for the date range — the appointments module will
 * respect this when slot lookup ships.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `doctor_leaves` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `doctor_id` INT UNSIGNED NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `reason` VARCHAR(300) NULL DEFAULT NULL,
                `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `approved_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_dleave_doctor` (`doctor_id`),
                KEY `idx_dleave_dates` (`start_date`, `end_date`),
                KEY `idx_dleave_status` (`status`),
                CONSTRAINT `fk_dleave_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_dleave_approver` FOREIGN KEY (`approved_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Doctor leave records'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `doctor_leaves`');
    },
];
