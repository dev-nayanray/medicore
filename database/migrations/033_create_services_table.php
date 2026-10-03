<?php

declare(strict_types=1);

/**
 * Hospital services — configurable catalogue with category + price.
 * Drives the invoice line-item picker. When the pharmacy or laboratory
 * module ships, services can cross-reference their records.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `services` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `category` ENUM('consultation','laboratory','procedure','medicine','admission','other') NOT NULL DEFAULT 'other',
                `description` TEXT NULL,
                `price` DECIMAL(10,2) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_services_category` (`category`),
                KEY `idx_services_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurable hospital services & pricing'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `services`');
    },
];
