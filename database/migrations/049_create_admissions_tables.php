<?php
declare(strict_types=1);
/**
 * Admissions + bed transfers. Admission is the patient's inpatient
 * stay record — linked to patient, doctor, ward, room, bed. Transfer
 * records track bed-to-bed moves within the same admission.
 */
return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `admissions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `admission_code` VARCHAR(20) NOT NULL COMMENT 'ADM-YYYY-NNNNN',
                `patient_id` INT UNSIGNED NOT NULL,
                `doctor_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK doctors.id (attending)',
                `ward_id` INT UNSIGNED NULL DEFAULT NULL,
                `room_id` INT UNSIGNED NULL DEFAULT NULL,
                `bed_id` INT UNSIGNED NULL DEFAULT NULL,
                `admission_type` ENUM('emergency','scheduled','transfer_in') NOT NULL DEFAULT 'scheduled',
                `admission_date` DATETIME NOT NULL,
                `expected_discharge` DATE NULL DEFAULT NULL,
                `actual_discharge_date` DATETIME NULL DEFAULT NULL,
                `admission_reason` VARCHAR(500) NULL DEFAULT NULL,
                `diagnosis_at_admission` VARCHAR(500) NULL DEFAULT NULL,
                `discharge_summary` TEXT NULL,
                `discharge_diagnosis` VARCHAR(500) NULL DEFAULT NULL,
                `discharge_instructions` TEXT NULL,
                `status` ENUM('admitted','discharged','transferred_out') NOT NULL DEFAULT 'admitted',
                `created_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `discharged_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_adm_code` (`admission_code`),
                KEY `idx_adm_patient` (`patient_id`),
                KEY `idx_adm_doctor` (`doctor_id`),
                KEY `idx_adm_bed` (`bed_id`),
                KEY `idx_adm_status` (`status`),
                KEY `idx_adm_date` (`admission_date`),
                CONSTRAINT `fk_adm_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_adm_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `doctors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_adm_ward` FOREIGN KEY (`ward_id`)
                    REFERENCES `wards` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_adm_room` FOREIGN KEY (`room_id`)
                    REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_adm_bed` FOREIGN KEY (`bed_id`)
                    REFERENCES `beds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_adm_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_adm_discharged_by` FOREIGN KEY (`discharged_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Patient admissions'"
        );
        $pdo->exec(
            "CREATE TABLE `bed_transfers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `admission_id` INT UNSIGNED NOT NULL,
                `from_bed_id` INT UNSIGNED NULL DEFAULT NULL,
                `to_bed_id` INT UNSIGNED NULL DEFAULT NULL,
                `transfer_date` DATETIME NOT NULL,
                `reason` VARCHAR(300) NULL DEFAULT NULL,
                `transferred_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_bt_admission` (`admission_id`),
                CONSTRAINT `fk_bt_admission` FOREIGN KEY (`admission_id`)
                    REFERENCES `admissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_bt_from_bed` FOREIGN KEY (`from_bed_id`)
                    REFERENCES `beds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_bt_to_bed` FOREIGN KEY (`to_bed_id`)
                    REFERENCES `beds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_bt_transferred_by` FOREIGN KEY (`transferred_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bed transfer records'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `bed_transfers`');
        $pdo->exec('DROP TABLE IF EXISTS `admissions`');
    },
];
