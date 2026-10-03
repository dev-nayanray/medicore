<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Lab test — catalogue with pricing, sample type, turnaround.
 */
final class LabTest extends Model
{
    protected static function table(): string { return 'lab_tests'; }

    /** @return array<int, array<string, mixed>> grouped by category */
    public static function groupedByCategory(): array
    {
        $rows = Database::query('SELECT * FROM lab_tests WHERE is_active = 1 ORDER BY category, name');
        $grouped = [];
        foreach ($rows as $r) { $grouped[$r['category']][] = $r; }
        return $grouped;
    }

    /** @return array<int, array<string, mixed>> */
    public static function options(): array
    {
        return Database::query('SELECT id, name, price, sample_type FROM lab_tests WHERE is_active = 1 ORDER BY category, name');
    }
}
