<?php

declare(strict_types=1);

/**
 * Password reset tokens. Only the SHA-256 HASH of the token is stored —
 * a database leak must not yield usable reset links. Single-use + expiring.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `password_resets` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(190) NOT NULL,
                `token_hash` CHAR(64) NOT NULL COMMENT 'sha256(token)',
                `expires_at` DATETIME NOT NULL,
                `used_at` DATETIME NULL DEFAULT NULL,
                `requested_ip` VARCHAR(45) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_resets_token` (`token_hash`),
                KEY `idx_resets_email` (`email`),
                KEY `idx_resets_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hashed, single-use reset tokens'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `password_resets`');
    },
];
