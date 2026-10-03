<?php

declare(strict_types=1);

/**
 * Appointment reminders — integration-ready notification queue. Each row
 * is a scheduled reminder (SMS / email / in-app) that a gateway adapter
 * picks up and sends. The `process()` method in AppointmentReminderService
 * marks due rows as sent and writes to the dev mail log / audit trail.
 * Swap the transport for a real SMS/email gateway in production — the
 * schema and service contract stay unchanged.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `appointment_reminders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_id` INT UNSIGNED NOT NULL,
                `channel` ENUM('sms','email','in_app') NOT NULL DEFAULT 'sms',
                `scheduled_at` DATETIME NOT NULL COMMENT 'When to send',
                `sent_at` DATETIME NULL DEFAULT NULL,
                `status` ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
                `message` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Rendered message body',
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `last_error` VARCHAR(300) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_reminder_scheduled` (`scheduled_at`, `status`),
                KEY `idx_reminder_appointment` (`appointment_id`),
                CONSTRAINT `fk_reminder_appointment` FOREIGN KEY (`appointment_id`)
                    REFERENCES `appointments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Appointment reminder queue'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `appointment_reminders`');
    },
];
