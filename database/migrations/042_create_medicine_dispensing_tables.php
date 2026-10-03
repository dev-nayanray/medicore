<?php

declare(strict_types=1);

/**
 * Medicine dispensing + items + returns. Dispensing can be linked to a
 * prescription (pharmacy.fill) or be a direct sale. Stock is deducted
 * from the specific batch (FEFO — first expiry, first out). Returns
 * restore stock. All operations are transaction-protected.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `medicine_dispensings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `dispensing_code` VARCHAR(20) NOT NULL COMMENT 'DSP-YYYY-NNNNN',
                `prescription_id` INT UNSIGNED NULL DEFAULT NULL,
                `consultation_id` INT UNSIGNED NULL DEFAULT NULL,
                `patient_id` INT UNSIGNED NOT NULL,
                `invoice_id` INT UNSIGNED NULL DEFAULT NULL,
                `status` ENUM('dispensed','returned','partial_return') NOT NULL DEFAULT 'dispensed',
                `notes` TEXT NULL,
                `dispensed_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_dsp_code` (`dispensing_code`),
                KEY `idx_dsp_prescription` (`prescription_id`),
                KEY `idx_dsp_patient` (`patient_id`),
                KEY `idx_dsp_invoice` (`invoice_id`),
                CONSTRAINT `fk_dsp_prescription` FOREIGN KEY (`prescription_id`)
                    REFERENCES `prescriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_dsp_consultation` FOREIGN KEY (`consultation_id`)
                    REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_dsp_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_dsp_invoice` FOREIGN KEY (`invoice_id`)
                    REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_dsp_dispensed_by` FOREIGN KEY (`dispensed_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medicine dispensing records'"
        );

        $pdo->exec(
            "CREATE TABLE `medicine_dispensing_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `dispensing_id` INT UNSIGNED NOT NULL,
                `medicine_id` INT UNSIGNED NOT NULL,
                `batch_id` INT UNSIGNED NOT NULL,
                `quantity_dispensed` INT UNSIGNED NOT NULL,
                `unit_price` DECIMAL(10,2) NOT NULL,
                `instructions` VARCHAR(300) NULL DEFAULT NULL,
                `quantity_returned` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_di_dispensing` (`dispensing_id`),
                KEY `idx_di_batch` (`batch_id`),
                CONSTRAINT `fk_di_dispensing` FOREIGN KEY (`dispensing_id`)
                    REFERENCES `medicine_dispensings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_di_medicine` FOREIGN KEY (`medicine_id`)
                    REFERENCES `medicines` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_di_batch` FOREIGN KEY (`batch_id`)
                    REFERENCES `medicine_batches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dispensed medicine items'"
        );

        $pdo->exec(
            "CREATE TABLE `medicine_returns` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `dispensing_id` INT UNSIGNED NOT NULL,
                `dispensing_item_id` INT UNSIGNED NOT NULL,
                `medicine_id` INT UNSIGNED NOT NULL,
                `batch_id` INT UNSIGNED NOT NULL,
                `quantity_returned` INT UNSIGNED NOT NULL,
                `reason` VARCHAR(300) NOT NULL,
                `returned_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ret_dispensing` (`dispensing_id`),
                CONSTRAINT `fk_ret_dispensing` FOREIGN KEY (`dispensing_id`)
                    REFERENCES `medicine_dispensings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_ret_batch` FOREIGN KEY (`batch_id`)
                    REFERENCES `medicine_batches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_ret_returned_by` FOREIGN KEY (`returned_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medicine returns'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `medicine_returns`');
        $pdo->exec('DROP TABLE IF EXISTS `medicine_dispensing_items`');
        $pdo->exec('DROP TABLE IF EXISTS `medicine_dispensings`');
    },
];
