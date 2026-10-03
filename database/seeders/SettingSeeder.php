<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Hospital settings catalogue — the settings screen renders directly
 * from this table, grouped by the `group` column.
 */
final class SettingSeeder extends Seeder
{
    public static function label(): string { return 'Hospital settings'; }
    public static function order(): int { return 140; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'hospital_settings');

        $settings = [
            // key, value, type, group, label, description, options(json), is_public
            ['hospital_name',    'MediCore General Hospital', 'string', 'general',  'Hospital Name',    'Full legal name of the hospital.', null, 1],
            ['hospital_tagline', 'Care that never sleeps',    'string', 'general',  'Tagline',          'Short line shown on documents and prints.', null, 1],
            ['hospital_email',   'hello@medicore.test',       'string', 'general',  'Contact Email',    'Primary contact email address.', null, 1],
            ['hospital_phone',   '+880 2 5566 7788',          'string', 'general',  'Contact Phone',    'Main reception phone number.', null, 1],
            ['hospital_address', '24/G Green Road, Dhanmondi, Dhaka 1205', 'text', 'general', 'Address', 'Physical address of the main campus.', null, 1],
            ['website',          'https://medicore.test',     'string', 'general',  'Website',          'Public website URL.', null, 1],

            ['timezone',   'Asia/Dhaka', 'string', 'localization', 'Timezone',            'IANA timezone used for display formatting.', null, 0],
            ['currency',   'BDT',        'string', 'localization', 'Currency',            'ISO currency code used across billing.', null, 0],
            ['date_format','d M Y',      'string', 'localization', 'Date Format',         'PHP date() format for on-screen dates.', null, 0],

            ['items_per_page',    '10',    'number',  'preferences', 'Rows Per Page',    'Table page size across list screens.', null, 0],
            ['default_theme',     'light', 'string',  'preferences', 'Default Theme',    'Initial color theme for new browsers.', null, 0],
            ['sidebar_collapsed', 'false', 'boolean', 'preferences', 'Collapse Sidebar', 'Start with the sidebar collapsed by default.', null, 0],

            ['maintenance_mode', 'false', 'boolean', 'system', 'Maintenance Mode', 'When enabled, only super admins can sign in.', null, 0],
            ['audit_retention_days', '365', 'number', 'system', 'Audit Retention (days)', 'How long audit trail rows are kept before pruning.', null, 0],
        ];

        $stmt = $db->prepare(
            'INSERT INTO hospital_settings (`key`, `value`, `type`, `group`, `label`, `description`, `options`, `is_public`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($settings as [$key, $value, $type, $group, $label, $description, $options, $isPublic]) {
            $stmt->execute([$key, $value, $type, $group, $label, $description, $options, $isPublic]);
        }
    }
}
