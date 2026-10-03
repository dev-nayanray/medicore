<?php

declare(strict_types=1);

/**
 * Inventory categories + items + stock. Inventory items are non-medicine
 * supplies (syringes, gloves, reagents, etc.) with reorder levels.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `inventory_categories` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_icat_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory categories'"
        );

        $pdo->exec(
            "CREATE TABLE `inventory_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `category_id` INT UNSIGNED NULL DEFAULT NULL,
                `name` VARCHAR(200) NOT NULL,
                `sku` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Stock keeping unit',
                `unit` VARCHAR(30) NOT NULL DEFAULT 'piece',
                `description` TEXT NULL,
                `reorder_level` INT UNSIGNED NOT NULL DEFAULT 20,
                `current_stock` INT NOT NULL DEFAULT 0,
                `unit_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'For valuation',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_inv_category` (`category_id`),
                KEY `idx_inv_active` (`is_active`),
                CONSTRAINT `fk_inv_category` FOREIGN KEY (`category_id`)
                    REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory items'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `inventory_items`');
        $pdo->exec('DROP TABLE IF EXISTS `inventory_categories`');
    },
];
