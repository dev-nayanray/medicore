<?php

declare(strict_types=1);

/**
 * Daily attendance records — one row per (staff, date). Supports manual
 * check-in/check-out times and a derived status (present / late / absent /
 * half-day / leave).
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `staff_attendance` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` INT UNSIGNED NOT NULL,
                `date` DATE NOT NULL,
                `check_in` TIME NULL DEFAULT NULL,
                `check_out` TIME NULL DEFAULT NULL,
                `status` ENUM('present','late','absent','half_day','leave') NOT NULL DEFAULT 'present',
                `notes` VARCHAR(300) NULL DEFAULT NULL,
                `recorded_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_attendance_staff_date` (`staff_id`, `date`),
                KEY `idx_attendance_date` (`date`),
                KEY `idx_attendance_status` (`status`),
                CONSTRAINT `fk_attendance_staff` FOREIGN KEY (`staff_id`)
                    REFERENCES `staff_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_attendance_recorder` FOREIGN KEY (`recorded_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff attendance'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `staff_attendance`');
    },
];
