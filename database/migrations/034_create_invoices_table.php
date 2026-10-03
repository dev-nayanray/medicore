<?php

declare(strict_types=1);

/**
 * Invoices — the financial billing record. All monetary values are
 * DECIMAL for precision. Links to patient + optional doctor /
 * consultation / appointment. Status: draft → sent → partially_paid →
 * paid (or cancelled / refunded). paid_amount and balance_due are
 * recalculated on every payment / refund inside a transaction.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `invoices` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `invoice_code` VARCHAR(20) NOT NULL COMMENT 'INV-YYYY-NNNNN',
                `patient_id` INT UNSIGNED NOT NULL,
                `doctor_id` INT UNSIGNED NULL DEFAULT NULL,
                `consultation_id` INT UNSIGNED NULL DEFAULT NULL,
                `appointment_id` INT UNSIGNED NULL DEFAULT NULL,
                `invoice_date` DATE NOT NULL,
                `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `tax_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `balance_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `status` ENUM('draft','sent','partially_paid','paid','cancelled','refunded') NOT NULL DEFAULT 'draft',
                `notes` TEXT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL,
                `cancelled_at` DATETIME NULL DEFAULT NULL,
                `cancelled_by` INT UNSIGNED NULL DEFAULT NULL,
                `cancellation_reason` VARCHAR(300) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_inv_code` (`invoice_code`),
                KEY `idx_inv_patient` (`patient_id`),
                KEY `idx_inv_doctor` (`doctor_id`),
                KEY `idx_inv_date` (`invoice_date`),
                KEY `idx_inv_status` (`status`),
                CONSTRAINT `fk_inv_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_inv_doctor` FOREIGN KEY (`doctor_id`)
                    REFERENCES `doctors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_inv_consultation` FOREIGN KEY (`consultation_id`)
                    REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_inv_appointment` FOREIGN KEY (`appointment_id`)
                    REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_inv_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_inv_cancelled_by` FOREIGN KEY (`cancelled_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Patient invoices'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `invoices`');
    },
];
