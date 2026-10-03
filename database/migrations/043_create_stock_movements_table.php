<?php

declare(strict_types=1);

/**
 * Unified stock movements — every stock change for medicines and
 * inventory items is logged here. Types: purchase, dispensing, return,
 * adjustment, transfer, initial. The `balance_after` column records
 * the running stock level at the time of the movement.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `stock_movements` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `item_type` ENUM('medicine','inventory') NOT NULL,
                `item_id` INT UNSIGNED NOT NULL,
                `batch_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK medicine_batches (nullable for inventory)',
                `movement_type` ENUM('purchase','dispensing','return','adjustment','transfer','initial') NOT NULL,
                `quantity` INT NOT NULL COMMENT 'Positive = in, negative = out',
                `reference_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'e.g. dispensing, purchase, adjustment',
                `reference_id` INT UNSIGNED NULL DEFAULT NULL,
                `balance_after` INT NOT NULL DEFAULT 0,
                `notes` VARCHAR(300) NULL DEFAULT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sm_item` (`item_type`, `item_id`),
                KEY `idx_sm_type` (`movement_type`),
                KEY `idx_sm_created` (`created_at`),
                CONSTRAINT `fk_sm_batch` FOREIGN KEY (`batch_id`)
                    REFERENCES `medicine_batches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_sm_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Unified stock movement log'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `stock_movements`');
    },
];
