<?php
declare(strict_types=1);
/**
 * Wards — hospital floor/unit configuration. Each ward has rooms,
 * each room has beds.
 */
return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `wards` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `floor` VARCHAR(20) NULL DEFAULT NULL,
                `description` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`), KEY `idx_wards_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hospital wards'"
        );
        $pdo->exec(
            "CREATE TABLE `rooms` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ward_id` INT UNSIGNED NOT NULL,
                `room_number` VARCHAR(20) NOT NULL,
                `room_type` ENUM('general','semi_private','private','icu','nicu','isolation') NOT NULL DEFAULT 'general',
                `daily_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_room_ward` (`ward_id`, `room_number`),
                KEY `idx_rooms_ward` (`ward_id`),
                CONSTRAINT `fk_rooms_ward` FOREIGN KEY (`ward_id`)
                    REFERENCES `wards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hospital rooms'"
        );
        $pdo->exec(
            "CREATE TABLE `beds` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `room_id` INT UNSIGNED NOT NULL,
                `bed_number` VARCHAR(20) NOT NULL,
                `status` ENUM('available','occupied','maintenance','cleaning') NOT NULL DEFAULT 'available',
                `current_admission_id` INT UNSIGNED NULL DEFAULT NULL,
                `notes` VARCHAR(200) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_bed_room` (`room_id`, `bed_number`),
                KEY `idx_beds_status` (`status`),
                CONSTRAINT `fk_beds_room` FOREIGN KEY (`room_id`)
                    REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hospital beds'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `beds`');
        $pdo->exec('DROP TABLE IF EXISTS `rooms`');
        $pdo->exec('DROP TABLE IF EXISTS `wards`');
    },
];
