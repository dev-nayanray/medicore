<?php

declare(strict_types=1);

/**
 * Suppliers — shared between Pharmacy and Inventory modules.
 * Tracks vendor contact details for purchase orders.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `suppliers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `contact_person` VARCHAR(100) NULL DEFAULT NULL,
                `phone` VARCHAR(30) NULL DEFAULT NULL,
                `email` VARCHAR(190) NULL DEFAULT NULL,
                `address` VARCHAR(255) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_suppliers_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pharmacy & inventory suppliers'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `suppliers`');
    },
];
