<?php

declare(strict_types=1);

/**
 * Payments — both incoming (positive) and refunds (negative with
 * is_refund = 1). Each payment links to an invoice. Duplicate prevention
 * uses a transaction + SELECT ... FOR UPDATE on the invoice row before
 * inserting, so two concurrent identical payments cannot both succeed.
 * The unique constraint on payment_code is the last-resort guard.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `payments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `payment_code` VARCHAR(20) NOT NULL COMMENT 'PAY-YYYY-NNNNN',
                `invoice_id` INT UNSIGNED NOT NULL,
                `patient_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL COMMENT 'Negative for refunds',
                `payment_method` ENUM('cash','card','mobile_banking','bank_transfer','insurance','other') NOT NULL,
                `reference_number` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Transaction ID / check no.',
                `status` ENUM('completed','pending','void') NOT NULL DEFAULT 'completed',
                `is_refund` TINYINT(1) NOT NULL DEFAULT 0,
                `refund_reason` VARCHAR(300) NULL DEFAULT NULL,
                `authorized_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Who authorized the refund',
                `recorded_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `recorded_at` DATETIME NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_pay_code` (`payment_code`),
                KEY `idx_pay_invoice` (`invoice_id`),
                KEY `idx_pay_patient` (`patient_id`),
                KEY `idx_pay_method` (`payment_method`),
                KEY `idx_pay_date` (`recorded_at`),
                KEY `idx_pay_status` (`status`),
                CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`)
                    REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_pay_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_pay_authorized_by` FOREIGN KEY (`authorized_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_pay_recorded_by` FOREIGN KEY (`recorded_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payments & refunds'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `payments`');
    },
];
