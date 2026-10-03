<?php

declare(strict_types=1);

/**
 * Doctor profiles — extend the users table with clinical/employment fields.
 * A doctor is a user with the 'doctor' role; this table holds the extra
 * profile data (specialty, fees, schedule, department assignment).
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `doctors` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL COMMENT 'FK users.id (the account)',
                `doctor_code` VARCHAR(20) NOT NULL COMMENT 'MCD-YYYY-NNNNN',
                `specialization` VARCHAR(100) NOT NULL,
                `qualifications` VARCHAR(500) NULL DEFAULT NULL,
                `registration_number` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Medical council reg.',
                `bio` TEXT NULL,
                `consultation_fee` DECIMAL(10,2) NULL DEFAULT NULL,
                `profile_image` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Stored filename in storage/uploads/doctors',
                `department_id` INT UNSIGNED NULL DEFAULT NULL,
                `room_number` VARCHAR(20) NULL DEFAULT NULL,
                `status` ENUM('active','inactive','on_leave') NOT NULL DEFAULT 'active',
                `hired_at` DATE NULL DEFAULT NULL,
                `archived_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_doctors_user` (`user_id`),
                UNIQUE KEY `uk_doctors_code` (`doctor_code`),
                UNIQUE KEY `uk_doctors_reg` (`registration_number`),
                KEY `idx_doctors_dept` (`department_id`),
                KEY `idx_doctors_status` (`status`),
                CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_doctors_dept` FOREIGN KEY (`department_id`)
                    REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Doctor profiles'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `doctors`');
    },
];
