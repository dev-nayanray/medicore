<?php

declare(strict_types=1);

/**
 * Staff profiles — extend users with HR / employment fields (employee ID,
 * job title, department, hire date). Auth stays in users; HR data lives here.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `staff_profiles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL COMMENT 'FK users.id (the account)',
                `employee_id` VARCHAR(20) NOT NULL COMMENT 'MCS-YYYY-NNNNN',
                `job_title` VARCHAR(100) NOT NULL,
                `department_id` INT UNSIGNED NULL DEFAULT NULL,
                `employment_type` ENUM('full_time','part_time','contract','visiting','intern') NOT NULL DEFAULT 'full_time',
                `hire_date` DATE NULL DEFAULT NULL,
                `profile_image` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Stored filename',
                `status` ENUM('active','inactive','on_leave','terminated') NOT NULL DEFAULT 'active',
                `archived_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_staff_user` (`user_id`),
                UNIQUE KEY `uk_staff_code` (`employee_id`),
                KEY `idx_staff_dept` (`department_id`),
                KEY `idx_staff_status` (`status`),
                CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_staff_dept` FOREIGN KEY (`department_id`)
                    REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff employment profiles'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `staff_profiles`');
    },
];
