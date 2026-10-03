<?php

declare(strict_types=1);

/**
 * Clinical attachments — files uploaded against a consultation (lab
 * reports, scans, referral letters). Files live outside the docroot
 * in storage/uploads/consultations and are served only through a
 * permission-gated download endpoint — never as public URLs.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `consultation_attachments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `consultation_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `original_name` VARCHAR(255) NOT NULL COMMENT 'Display name only',
                `stored_name` VARCHAR(64) NOT NULL COMMENT 'Random filesystem name',
                `mime_type` VARCHAR(100) NOT NULL,
                `size_bytes` INT UNSIGNED NOT NULL,
                `uploaded_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_ca_stored` (`stored_name`),
                KEY `idx_ca_consultation` (`consultation_id`),
                CONSTRAINT `fk_ca_consultation` FOREIGN KEY (`consultation_id`)
                    REFERENCES `consultations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_ca_uploader` FOREIGN KEY (`uploaded_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Private clinical attachments'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `consultation_attachments`');
    },
];
