<?php

declare(strict_types=1);

/**
 * Inventory purchases + adjustments. Purchases increase stock;
 * adjustments (increase/decrease) require approval. All movements
 * are logged in stock_movements.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `inventory_purchases` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `purchase_code` VARCHAR(20) NOT NULL COMMENT 'IPUR-YYYY-NNNNN',
                `supplier_id` INT UNSIGNED NULL DEFAULT NULL,
                `purchase_date` DATE NOT NULL,
                `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `status` ENUM('draft','received','cancelled') NOT NULL DEFAULT 'draft',
                `notes` TEXT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_ipur_code` (`purchase_code`),
                KEY `idx_ipur_supplier` (`supplier_id`),
                CONSTRAINT `fk_ipur_supplier` FOREIGN KEY (`supplier_id`)
                    REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_ipur_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory purchase orders'"
        );

        $pdo->exec(
            "CREATE TABLE `inventory_purchase_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `purchase_id` INT UNSIGNED NOT NULL,
                `item_id` INT UNSIGNED NOT NULL,
                `quantity` INT UNSIGNED NOT NULL,
                `unit_cost` DECIMAL(10,2) NOT NULL,
                `line_total` DECIMAL(12,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_ipi_purchase` (`purchase_id`),
                CONSTRAINT `fk_ipi_purchase` FOREIGN KEY (`purchase_id`)
                    REFERENCES `inventory_purchases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_ipi_item` FOREIGN KEY (`item_id`)
                    REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory purchase line items'"
        );

        $pdo->exec(
            "CREATE TABLE `inventory_adjustments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `item_id` INT UNSIGNED NOT NULL,
                `adjustment_type` ENUM('increase','decrease') NOT NULL,
                `quantity` INT UNSIGNED NOT NULL,
                `reason` VARCHAR(300) NOT NULL,
                `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `requested_by` INT UNSIGNED NULL DEFAULT NULL,
                `approved_by` INT UNSIGNED NULL DEFAULT NULL,
                `approved_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_adj_item` (`item_id`),
                KEY `idx_adj_status` (`status`),
                CONSTRAINT `fk_adj_item` FOREIGN KEY (`item_id`)
                    REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_adj_requested_by` FOREIGN KEY (`requested_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_adj_approved_by` FOREIGN KEY (`approved_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory stock adjustments'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `inventory_adjustments`');
        $pdo->exec('DROP TABLE IF EXISTS `inventory_purchase_items`');
        $pdo->exec('DROP TABLE IF EXISTS `inventory_purchases`');
    },
];
