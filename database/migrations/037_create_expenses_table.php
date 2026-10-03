<?php

declare(strict_types=1);

/**
 * Expenses — hospital operational spending tracked for the P&L report.
 * Categorised for reporting (salaries, utilities, supplies, etc.).
 * Not linked to invoices — purely an expense ledger.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `expenses` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `expense_code` VARCHAR(20) NOT NULL COMMENT 'EXP-YYYY-NNNNN',
                `category` ENUM('salaries','utilities','supplies','maintenance','equipment','rent','other') NOT NULL DEFAULT 'other',
                `description` VARCHAR(300) NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL,
                `expense_date` DATE NOT NULL,
                `paid_to` VARCHAR(150) NULL DEFAULT NULL,
                `payment_method` ENUM('cash','card','bank_transfer','cheque','other') NOT NULL DEFAULT 'cash',
                `reference_number` VARCHAR(100) NULL DEFAULT NULL,
                `recorded_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_exp_code` (`expense_code`),
                KEY `idx_exp_category` (`category`),
                KEY `idx_exp_date` (`expense_date`),
                CONSTRAINT `fk_exp_recorded_by` FOREIGN KEY (`recorded_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hospital expenses'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `expenses`');
    },
];
