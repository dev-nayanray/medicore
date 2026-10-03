<?php

declare(strict_types=1);

/**
 * Patient document metadata. Files live OUTSIDE the docroot
 * (storage/uploads/patients) and are served only through the
 * permission-checked download endpoint — never as public URLs.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `patient_documents` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `patient_id` INT UNSIGNED NOT NULL,
                `document_type` ENUM('report','prescription','scan','identification','consent','other') NOT NULL DEFAULT 'other',
                `title` VARCHAR(150) NOT NULL,
                `original_name` VARCHAR(255) NOT NULL COMMENT 'Display name only',
                `stored_name` VARCHAR(64) NOT NULL COMMENT 'Random filesystem name',
                `mime_type` VARCHAR(100) NOT NULL,
                `size_bytes` INT UNSIGNED NOT NULL,
                `uploaded_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_documents_stored` (`stored_name`),
                KEY `idx_documents_patient` (`patient_id`),
                CONSTRAINT `fk_documents_patient` FOREIGN KEY (`patient_id`)
                    REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_documents_uploader` FOREIGN KEY (`uploaded_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Private patient files'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `patient_documents`');
    },
];
