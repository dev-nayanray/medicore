<?php

declare(strict_types=1);

/**
 * Consultation amendments — formal corrections to finalized clinical
 * records. Each row records the field changed, old/new values, the
 * reason, and the authorizing user. This provides an auditable trail
 * for clinical record modifications without allowing silent edits.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `consultation_amendments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `consultation_id` INT UNSIGNED NOT NULL,
                `field_name` VARCHAR(100) NOT NULL,
                `old_value` TEXT NULL,
                `new_value` TEXT NULL,
                `reason` VARCHAR(500) NOT NULL,
                `amended_by` INT UNSIGNED NOT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_am_consultation` (`consultation_id`),
                CONSTRAINT `fk_am_consultation` FOREIGN KEY (`consultation_id`)
                    REFERENCES `consultations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_am_amender` FOREIGN KEY (`amended_by`)
                    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clinical record amendment history'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `consultation_amendments`');
    },
];
