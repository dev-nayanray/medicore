<?php

declare(strict_types=1);

/**
 * Medicine purchases + line items. A purchase creates batches on receive.
 * Stock movements are logged for every received item.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `medicine_purchases` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `purchase_code` VARCHAR(20) NOT NULL COMMENT 'MPUR-YYYY-NNNNN',
                `supplier_id` INT UNSIGNED NULL DEFAULT NULL,
                `purchase_date` DATE NOT NULL,
                `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `status` ENUM('draft','received','cancelled') NOT NULL DEFAULT 'draft',
                `notes` TEXT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_mpur_code` (`purchase_code`),
                KEY `idx_mpur_supplier` (`supplier_id`),
                KEY `idx_mpur_status` (`status`),
                CONSTRAINT `fk_mpur_supplier` FOREIGN KEY (`supplier_id`)
                    REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_mpur_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medicine purchase orders'"
        );

        $pdo->exec(
            "CREATE TABLE `medicine_purchase_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `purchase_id` INT UNSIGNED NOT NULL,
                `medicine_id` INT UNSIGNED NOT NULL,
                `batch_number` VARCHAR(50) NOT NULL,
                `expiry_date` DATE NOT NULL,
                `quantity` INT UNSIGNED NOT NULL,
                `unit_cost` DECIMAL(10,2) NOT NULL,
                `line_total` DECIMAL(12,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_mpi_purchase` (`purchase_id`),
                KEY `idx_mpi_medicine` (`medicine_id`),
                CONSTRAINT `fk_mpi_purchase` FOREIGN KEY (`purchase_id`)
                    REFERENCES `medicine_purchases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_mpi_medicine` FOREIGN KEY (`medicine_id`)
                    REFERENCES `medicines` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medicine purchase line items'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `medicine_purchase_items`');
        $pdo->exec('DROP TABLE IF EXISTS `medicine_purchases`');
    },
];
