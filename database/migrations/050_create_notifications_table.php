<?php
declare(strict_types=1);
/**
 * Notifications — DB-backed in-app notification center. Targeted alerts
 * for appointment reminders, low-stock, expiry, lab pending tasks, and
 * admin announcements. The existing audit-log-based feed in
 * NotificationService stays for general activity; this table is for
 * targeted, actionable alerts with read/unread tracking.
 */
return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `notifications` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = broadcast to all',
                `type` ENUM('appointment_reminder','low_stock','expiry_alert','lab_pending','admission_alert','announcement','system') NOT NULL DEFAULT 'system',
                `title` VARCHAR(200) NOT NULL,
                `message` TEXT NOT NULL,
                `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
                `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                `read_at` DATETIME NULL DEFAULT NULL,
                `action_url` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Click-through URL',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_notif_user` (`user_id`, `is_read`),
                KEY `idx_notif_type` (`type`),
                KEY `idx_notif_created` (`created_at`),
                CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='In-app notifications'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `notifications`');
    },
];
