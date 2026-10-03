<?php

declare(strict_types=1);

/**
 * Staff shift schedules — specific date + time windows assigned to a
 * staff member in a department. Overlap is prevented in StaffService.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `staff_shifts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` INT UNSIGNED NOT NULL,
                `shift_date` DATE NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `shift_type` ENUM('morning','evening','night','on_call') NOT NULL DEFAULT 'morning',
                `department_id` INT UNSIGNED NULL DEFAULT NULL,
                `notes` VARCHAR(300) NULL DEFAULT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_shifts_staff_date` (`staff_id`, `shift_date`),
                KEY `idx_shifts_date` (`shift_date`),
                KEY `idx_shifts_dept` (`department_id`),
                CONSTRAINT `fk_shifts_staff` FOREIGN KEY (`staff_id`)
                    REFERENCES `staff_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_shifts_dept` FOREIGN KEY (`department_id`)
                    REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_shifts_creator` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff shift schedules'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `staff_shifts`');
    },
];
