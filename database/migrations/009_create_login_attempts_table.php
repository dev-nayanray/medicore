<?php

declare(strict_types=1);

/**
 * Login attempt tracking — both successes and failures, with the attempted
 * identifier preserved even when no account matches (security visibility).
 * Doubles as the data source for DB-backed rate limiting.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `login_attempts` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(190) NOT NULL COMMENT 'Identifier as typed',
                `user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Matched account, if any',
                `successful` TINYINT(1) NOT NULL DEFAULT 0,
                `failure_reason` VARCHAR(50) NULL DEFAULT NULL COMMENT 'invalid_password | inactive | archived | locked',
                `ip_address` VARCHAR(45) NULL DEFAULT NULL,
                `user_agent` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_attempts_email_created` (`email`, `created_at`),
                KEY `idx_attempts_user_created` (`user_id`, `created_at`),
                KEY `idx_attempts_ip_created` (`ip_address`, `created_at`),
                CONSTRAINT `fk_attempts_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Authentication attempt trail'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `login_attempts`');
    },
];
