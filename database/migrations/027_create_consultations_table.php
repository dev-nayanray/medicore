<?php

declare(strict_types=1);

/**
 * Consultations — the clinical encounter record. Linked to a patient,
 * a doctor, and optionally an appointment. Supports draft → finalized
 * states; finalized records are immutable except via formal amendments
 * (consultation_amendments). Vitals are stored as typed columns for
 * queryability; the schema is designed to integrate with future lab
 * and pharmacy modules.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `consultations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `consultation_code` VARCHAR(20) NOT NULL COMMENT 'CON-YYYY-NNNNN',
                `patient_id` INT UNSIGNED NOT NULL,
                `doctor_id` INT UNSIGNED NOT NULL COMMENT 'FK doctors.id',
                `appointment_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK appointments.id (nullable for walk-ins)',
                `department_id` INT UNSIGNED NULL DEFAULT NULL,
                `consultation_date` DATETIME NOT NULL,
                `status` ENUM('draft','finalized','amended') NOT NULL DEFAULT 'draft',
                `chief_complaint` VARCHAR(500) NULL DEFAULT NULL,
                `history_presenting` TEXT NULL COMMENT 'History of presenting illness',
                `symptoms` TEXT NULL,
                `observations` TEXT NULL COMMENT 'Physical examination findings',
                `temperature` DECIMAL(4,2) NULL DEFAULT NULL COMMENT '°C',
                `bp_systolic` INT UNSIGNED NULL DEFAULT NULL,
                `bp_diastolic` INT UNSIGNED NULL DEFAULT NULL,
                `pulse` INT UNSIGNED NULL DEFAULT NULL COMMENT 'bpm',
                `respiratory_rate` INT UNSIGNED NULL DEFAULT NULL COMMENT 'per minute',
                `spo2` INT UNSIGNED NULL DEFAULT NULL COMMENT '% oxygen saturation',
                `weight` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'kg',
                `height` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'cm',
                `bmi` DECIMAL(4,2) NULL DEFAULT NULL,
                `clinical_notes` TEXT NULL,
                `diagnoses` TEXT NULL COMMENT 'Primary + differential diagnoses',
                `follow_up_date` DATE NULL DEFAULT NULL,
                `referral_to` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Specialist / department',
                `referral_reason` TEXT NULL,
                `finalized_at` DATETIME NULL DEFAULT NULL,
                `finalized_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_con_code` (`consultation_code`),
                KEY `idx_con_patient` (`patient_id`),
                KEY `idx_con_doctor` (`doctor_id`),
                KEY `idx_con_appointment` (`appointment_id`),
                KEY `idx_con_date` (`consultation_date`),
                KEY `idx_con_status` (`status`),
                CONSTRAINT `fk_con_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_con_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `doctors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_con_appointment` FOREIGN KEY (`appointment_id`)
                    REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_con_dept` FOREIGN KEY (`department_id`)
                    REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_con_finalized_by` FOREIGN KEY (`finalized_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_con_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clinical encounter records'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `consultations`');
    },
];
