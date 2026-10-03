<?php

declare(strict_types=1);

/**
 * Weekly recurring doctor schedule — one row per (doctor, day_of_week).
 * Specific date exclusions go into doctor_leaves. The appointments module
 * (future) reads this to determine available slots.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `doctor_schedules` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `doctor_id` INT UNSIGNED NOT NULL,
                `day_of_week` TINYINT NOT NULL COMMENT '0=Sun … 6=Sat',
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `max_patients` INT UNSIGNED NOT NULL DEFAULT 20,
                `room` VARCHAR(20) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_schedule_doctor_day` (`doctor_id`, `day_of_week`),
                KEY `idx_schedule_day` (`day_of_week`),
                CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Weekly doctor availability'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `doctor_schedules`');
    },
];
