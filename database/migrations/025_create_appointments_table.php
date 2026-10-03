<?php

declare(strict_types=1);

/**
 * Appointments — the clinical scheduling spine. Every row links a patient
 * to a doctor (optional for walk-ins) on a date + time range. The unique
 * constraint on (doctor_id, appointment_date, start_time) catches exact-time
 * double-booking at the DB level; application-level overlap detection in a
 * transaction catches range overlaps. Archiving is not soft-deleted —
 * cancelled/no_show rows stay for reporting.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `appointments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_code` VARCHAR(20) NOT NULL COMMENT 'APT-YYYY-NNNNN',
                `patient_id` INT UNSIGNED NOT NULL,
                `doctor_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK doctors.id (nullable for unassigned walk-ins)',
                `department_id` INT UNSIGNED NULL DEFAULT NULL,
                `appointment_date` DATE NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `appointment_type` ENUM('scheduled','walk_in','follow_up','telemedicine') NOT NULL DEFAULT 'scheduled',
                `status` ENUM('pending','confirmed','checked_in','in_consultation','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
                `queue_token` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Per-day sequence, e.g. Q-042',
                `reason` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Reason for visit',
                `notes` TEXT NULL COMMENT 'Clinical / reception notes',
                `cancellation_reason` VARCHAR(300) NULL DEFAULT NULL,
                `cancelled_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `checked_in_at` DATETIME NULL DEFAULT NULL,
                `consultation_started_at` DATETIME NULL DEFAULT NULL,
                `completed_at` DATETIME NULL DEFAULT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_apt_code` (`appointment_code`),
                UNIQUE KEY `uk_apt_doctor_slot` (`doctor_id`, `appointment_date`, `start_time`),
                KEY `idx_apt_patient` (`patient_id`),
                KEY `idx_apt_doctor_date` (`doctor_id`, `appointment_date`),
                KEY `idx_apt_dept_date` (`department_id`, `appointment_date`),
                KEY `idx_apt_date_status` (`appointment_date`, `status`),
                KEY `idx_apt_status` (`status`),
                KEY `idx_apt_queue` (`appointment_date`, `department_id`, `queue_token`),
                CONSTRAINT `fk_apt_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_apt_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `doctors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_apt_dept` FOREIGN KEY (`department_id`)
                    REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_apt_cancelled_by` FOREIGN KEY (`cancelled_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_apt_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Appointment scheduling & queue'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `appointments`');
    },
];
