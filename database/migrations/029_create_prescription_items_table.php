<?php

declare(strict_types=1);

/**
 * Prescription items — individual medicines on a prescription. The
 * medicine_name is a free-text field (not a FK to a medicines table)
 * because the pharmacy module isn't built yet. When it ships, the
 * medicine_name can be cross-referenced or migrated to a FK.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `prescription_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `prescription_id` INT UNSIGNED NOT NULL,
                `medicine_name` VARCHAR(200) NOT NULL,
                `dosage` VARCHAR(50) NOT NULL COMMENT 'e.g. 500mg',
                `frequency` VARCHAR(50) NOT NULL COMMENT 'e.g. BD, TDS',
                `duration` VARCHAR(50) NOT NULL COMMENT 'e.g. 7 days',
                `quantity` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Units to dispense',
                `instructions` VARCHAR(300) NULL DEFAULT NULL COMMENT 'e.g. After meals',
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pi_prescription` (`prescription_id`),
                CONSTRAINT `fk_pi_prescription` FOREIGN KEY (`prescription_id`)
                    REFERENCES `prescriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prescription medicine items'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `prescription_items`');
    },
];
