<?php

declare(strict_types=1);

/**
 * Patient visits — the encounter timeline. Preserved forever even when
 * the patient is archived (queries join regardless of archive state).
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `patient_visits` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `patient_id` INT UNSIGNED NOT NULL,
                `visited_at` DATETIME NOT NULL,
                `visit_type` ENUM('outpatient','inpatient','emergency','follow-up','telemedicine') NOT NULL DEFAULT 'outpatient',
                `doctor_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Attending staff doctor',
                `chief_complaint` VARCHAR(500) NOT NULL,
                `diagnosis` VARCHAR(500) NULL DEFAULT NULL,
                `notes` TEXT NULL,
                `status` ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'completed',
                `recorded_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_visits_patient_time` (`patient_id`, `visited_at`),
                KEY `idx_visits_doctor` (`doctor_id`),
                CONSTRAINT `fk_visits_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_visits_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_visits_recorder` FOREIGN KEY (`recorded_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Encounter timeline'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `patient_visits`');
    },
];
