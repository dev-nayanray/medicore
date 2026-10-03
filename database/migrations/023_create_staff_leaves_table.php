<?php

declare(strict_types=1);

/**
 * Staff leave requests — leave_type, date range, reason, approval workflow.
 * Status: pending → approved/rejected by an authorised approver.
 */

return [
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE `staff_leaves` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` INT UNSIGNED NOT NULL,
                `leave_type` ENUM('casual','sick','annual','maternity','unpaid','other') NOT NULL DEFAULT 'casual',
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `reason` VARCHAR(500) NULL DEFAULT NULL,
                `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `approved_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK users.id',
                `approved_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sleave_staff` (`staff_id`),
                KEY `idx_sleave_dates` (`start_date`, `end_date`),
                KEY `idx_sleave_status` (`status`),
                CONSTRAINT `fk_sleave_staff` FOREIGN KEY (`staff_id`)
                    REFERENCES `staff_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_sleave_approver` FOREIGN KEY (`approved_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff leave requests'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `staff_leaves`');
    },
];
