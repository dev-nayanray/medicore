<?php

declare(strict_types=1);

/**
 * permission_role pivot — which abilities each role holds.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `permission_role` (
                `permission_id` INT UNSIGNED NOT NULL,
                `role_id` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`permission_id`, `role_id`),
                KEY `fk_pr_role` (`role_id`),
                CONSTRAINT `fk_pr_permission` FOREIGN KEY (`permission_id`)
                    REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_pr_role` FOREIGN KEY (`role_id`)
                    REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role abilities'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `permission_role`');
    },
];
