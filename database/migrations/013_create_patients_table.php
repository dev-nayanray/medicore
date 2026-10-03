<?php

declare(strict_types=1);

/**
 * Patients — the core clinical record. Archiving is a soft timestamp so
 * historical encounters, documents and billing references survive.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `patients` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `patient_code` VARCHAR(20) NOT NULL COMMENT 'MCP-YYYY-NNNNN',
                `first_name` VARCHAR(80) NOT NULL,
                `last_name` VARCHAR(80) NOT NULL,
                `gender` ENUM('male','female','other') NOT NULL,
                `date_of_birth` DATE NOT NULL,
                `blood_group` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL DEFAULT NULL,
                `marital_status` ENUM('single','married','divorced','widowed') NULL DEFAULT NULL,
                `national_id` VARCHAR(40) NULL DEFAULT NULL,
                `phone` VARCHAR(30) NOT NULL,
                `email` VARCHAR(190) NULL DEFAULT NULL,
                `address` VARCHAR(255) NULL DEFAULT NULL,
                `city` VARCHAR(100) NULL DEFAULT NULL,
                `postal_code` VARCHAR(20) NULL DEFAULT NULL,
                `country` VARCHAR(100) NULL DEFAULT 'Bangladesh',
                `emergency_contact_name` VARCHAR(150) NULL DEFAULT NULL,
                `emergency_contact_phone` VARCHAR(30) NULL DEFAULT NULL,
                `emergency_contact_relation` VARCHAR(50) NULL DEFAULT NULL,
                `medical_history` TEXT NULL COMMENT 'Chronic conditions, past surgeries',
                `allergies` TEXT NULL,
                `notes` TEXT NULL COMMENT 'Authorized clinical notes',
                `registered_by` INT UNSIGNED NULL DEFAULT NULL,
                `archived_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_patients_code` (`patient_code`),
                UNIQUE KEY `uk_patients_national_id` (`national_id`),
                KEY `idx_patients_name` (`last_name`, `first_name`),
                KEY `idx_patients_phone` (`phone`),
                KEY `idx_patients_archived` (`archived_at`),
                KEY `idx_patients_created` (`created_at`),
                CONSTRAINT `fk_patients_registrar` FOREIGN KEY (`registered_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Patient master records'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `patients`');
    },
];
