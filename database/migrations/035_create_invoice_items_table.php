<?php

declare(strict_types=1);

/**
 * Invoice line items — each row is a billable service or custom charge.
 * service_id is nullable so ad-hoc items (not in the catalogue) can be
 * added. line_total = (quantity * unit_price) - discount_amount.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `invoice_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `invoice_id` INT UNSIGNED NOT NULL,
                `service_id` INT UNSIGNED NULL DEFAULT NULL,
                `description` VARCHAR(255) NOT NULL,
                `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                `unit_price` DECIMAL(10,2) NOT NULL,
                `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `line_total` DECIMAL(12,2) NOT NULL,
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_items_invoice` (`invoice_id`),
                KEY `idx_items_service` (`service_id`),
                CONSTRAINT `fk_items_invoice` FOREIGN KEY (`invoice_id`)
                    REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_items_service` FOREIGN KEY (`service_id`)
                    REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoice line items'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `invoice_items`');
    },
];
