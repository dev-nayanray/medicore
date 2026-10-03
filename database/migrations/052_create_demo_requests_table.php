<?php
declare(strict_types=1);
/**
 * Demo requests — contact form submissions stored in the database.
 */
return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `demo_requests` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `full_name` VARCHAR(150) NOT NULL,
                `organization` VARCHAR(200) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `phone` VARCHAR(30) NULL DEFAULT NULL,
                `hospital_size` VARCHAR(20) NULL DEFAULT NULL,
                `message` TEXT NULL,
                `status` ENUM('pending','contacted','converted','rejected') NOT NULL DEFAULT 'pending',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_demo_status` (`status`),
                KEY `idx_demo_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demo request submissions'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `demo_requests`');
    },
];
