<?php

declare(strict_types=1);

/**
 * Medicines catalogue + batch-level stock records. Each batch tracks
 * its own expiry date and remaining quantity. The `medicines` table
 * holds the catalogue (name, generic, brand, dosage form, strength);
 * `medicine_batches` holds the physical stock with batch numbers.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `medicines` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(200) NOT NULL,
                `generic_name` VARCHAR(200) NULL DEFAULT NULL,
                `brand_name` VARCHAR(200) NULL DEFAULT NULL,
                `category` VARCHAR(100) NULL DEFAULT NULL,
                `dosage_form` ENUM('tablet','capsule','syrup','injection','ointment','drops','inhaler','other') NOT NULL DEFAULT 'tablet',
                `strength` VARCHAR(50) NULL DEFAULT NULL COMMENT 'e.g. 500mg, 5mg/5ml',
                `unit` VARCHAR(30) NOT NULL DEFAULT 'piece',
                `description` TEXT NULL,
                `reorder_level` INT UNSIGNED NOT NULL DEFAULT 50,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_med_name` (`name`),
                KEY `idx_med_generic` (`generic_name`),
                KEY `idx_med_category` (`category`),
                KEY `idx_med_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medicine catalogue'"
        );

        $pdo->exec(
            "CREATE TABLE `medicine_batches` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `medicine_id` INT UNSIGNED NOT NULL,
                `batch_number` VARCHAR(50) NOT NULL,
                `expiry_date` DATE NOT NULL,
                `quantity_received` INT UNSIGNED NOT NULL DEFAULT 0,
                `quantity_remaining` INT UNSIGNED NOT NULL DEFAULT 0,
                `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `sell_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `supplier_id` INT UNSIGNED NULL DEFAULT NULL,
                `received_at` DATE NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_batch_medicine` (`medicine_id`),
                KEY `idx_batch_expiry` (`expiry_date`),
                KEY `idx_batch_supplier` (`supplier_id`),
                CONSTRAINT `fk_batch_medicine` FOREIGN KEY (`medicine_id`)
                    REFERENCES `medicines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_batch_supplier` FOREIGN KEY (`supplier_id`)
                    REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medicine batch-level stock'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `medicine_batches`');
        $pdo->exec('DROP TABLE IF EXISTS `medicines`');
    },
];
