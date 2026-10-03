<?php

declare(strict_types=1);

/**
 * Laboratory tests + orders + order items. Each order item tracks its
 * own lifecycle: ordered → collected → resulted → verified → released.
 * Critical alerts flag abnormal results. Verification requires a
 * second person (lab technician → lab supervisor). Reports can only be
 * printed after verification + release.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `lab_tests` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(200) NOT NULL,
                `category` VARCHAR(100) NOT NULL DEFAULT 'general',
                `description` TEXT NULL,
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `sample_type` VARCHAR(100) NULL DEFAULT NULL COMMENT 'e.g. Blood, Urine, CSF',
                `turnaround_hours` INT UNSIGNED NOT NULL DEFAULT 24,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_lt_category` (`category`),
                KEY `idx_lt_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laboratory test catalogue'"
        );

        $pdo->exec(
            "CREATE TABLE `lab_orders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_code` VARCHAR(20) NOT NULL COMMENT 'LAB-YYYY-NNNNN',
                `patient_id` INT UNSIGNED NOT NULL,
                `doctor_id` INT UNSIGNED NULL DEFAULT NULL,
                `consultation_id` INT UNSIGNED NULL DEFAULT NULL,
                `invoice_id` INT UNSIGNED NULL DEFAULT NULL,
                `status` ENUM('ordered','collected','resulted','verified','released','cancelled') NOT NULL DEFAULT 'ordered',
                `notes` TEXT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_lab_code` (`order_code`),
                KEY `idx_lo_patient` (`patient_id`),
                KEY `idx_lo_doctor` (`doctor_id`),
                KEY `idx_lo_status` (`status`),
                CONSTRAINT `fk_lo_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_lo_consultation` FOREIGN KEY (`consultation_id`)
                    REFERENCES `consultations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_lo_invoice` FOREIGN KEY (`invoice_id`)
                    REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_lo_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lab test orders'"
        );

        $pdo->exec(
            "CREATE TABLE `lab_order_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` INT UNSIGNED NOT NULL,
                `test_id` INT UNSIGNED NOT NULL,
                `status` ENUM('ordered','collected','resulted','verified','released') NOT NULL DEFAULT 'ordered',
                `result_value` VARCHAR(500) NULL DEFAULT NULL,
                `result_unit` VARCHAR(50) NULL DEFAULT NULL,
                `reference_range` VARCHAR(200) NULL DEFAULT NULL,
                `is_critical` TINYINT(1) NOT NULL DEFAULT 0,
                `notes` TEXT NULL,
                `collected_at` DATETIME NULL DEFAULT NULL,
                `collected_by` INT UNSIGNED NULL DEFAULT NULL,
                `resulted_at` DATETIME NULL DEFAULT NULL,
                `resulted_by` INT UNSIGNED NULL DEFAULT NULL,
                `verified_at` DATETIME NULL DEFAULT NULL,
                `verified_by` INT UNSIGNED NULL DEFAULT NULL,
                `released_at` DATETIME NULL DEFAULT NULL,
                `released_by` INT UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_loi_order` (`order_id`),
                KEY `idx_loi_test` (`test_id`),
                KEY `idx_loi_status` (`status`),
                CONSTRAINT `fk_loi_order` FOREIGN KEY (`order_id`)
                    REFERENCES `lab_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_loi_test` FOREIGN KEY (`test_id`)
                    REFERENCES `lab_tests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_loi_collected_by` FOREIGN KEY (`collected_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_loi_resulted_by` FOREIGN KEY (`resulted_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_loi_verified_by` FOREIGN KEY (`verified_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_loi_released_by` FOREIGN KEY (`released_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lab order test items'"
        );

        $pdo->exec(
            "CREATE TABLE `lab_critical_alerts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_item_id` INT UNSIGNED NOT NULL,
                `test_name` VARCHAR(200) NOT NULL,
                `result_value` VARCHAR(500) NOT NULL,
                `reference_range` VARCHAR(200) NULL DEFAULT NULL,
                `alert_status` ENUM('pending','acknowledged','resolved') NOT NULL DEFAULT 'pending',
                `acknowledged_by` INT UNSIGNED NULL DEFAULT NULL,
                `acknowledged_at` DATETIME NULL DEFAULT NULL,
                `notes` TEXT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_lca_item` (`order_item_id`),
                KEY `idx_lca_status` (`alert_status`),
                CONSTRAINT `fk_lca_item` FOREIGN KEY (`order_item_id`)
                    REFERENCES `lab_order_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_lca_acknowledged_by` FOREIGN KEY (`acknowledged_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lab critical result alerts'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `lab_critical_alerts`');
        $pdo->exec('DROP TABLE IF EXISTS `lab_order_items`');
        $pdo->exec('DROP TABLE IF EXISTS `lab_orders`');
        $pdo->exec('DROP TABLE IF EXISTS `lab_tests`');
    },
];
