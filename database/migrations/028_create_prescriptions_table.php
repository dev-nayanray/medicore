<?php

declare(strict_types=1);

/**
 * Prescriptions — one per consultation (1:1 for simplicity). A
 * prescription has many prescription_items (medicines with dosage,
 * frequency, duration, instructions). Supports draft → finalized
 * states; finalized prescriptions are immutable.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `prescriptions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `prescription_code` VARCHAR(20) NOT NULL COMMENT 'RX-YYYY-NNNNN',
                `consultation_id` INT UNSIGNED NOT NULL,
                `patient_id` INT UNSIGNED NOT NULL,
                `doctor_id` INT UNSIGNED NOT NULL,
                `status` ENUM('draft','finalized','dispensed') NOT NULL DEFAULT 'draft',
                `notes` TEXT NULL COMMENT 'Pharmacist / dispensing notes',
                `finalized_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_rx_code` (`prescription_code`),
                UNIQUE KEY `uk_rx_consultation` (`consultation_id`),
                KEY `idx_rx_patient` (`patient_id`),
                KEY `idx_rx_doctor` (`doctor_id`),
                KEY `idx_rx_status` (`status`),
                CONSTRAINT `fk_rx_consultation` FOREIGN KEY (`consultation_id`)
                    REFERENCES `consultations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_rx_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_rx_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `doctors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Digital prescriptions'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `prescriptions`');
    },
];
