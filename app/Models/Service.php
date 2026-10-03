<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Service model — configurable hospital services & pricing catalogue.
 */
final class Service extends Model
{
    public const CATEGORIES = ['consultation', 'laboratory', 'procedure', 'medicine', 'admission', 'other'];

    protected static function table(): string
    {
        return 'services';
    }

    /** @return array<int, array<string, mixed>> */
    public static function activeByCategory(): array
    {
        return Database::query(
            'SELECT * FROM services WHERE is_active = 1 ORDER BY category, name'
        );
    }

    /** @return array<string, array<int, array<string, mixed>>> grouped by category */
    public static function groupedByCategory(): array
    {
        $rows = self::activeByCategory();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['category']][] = $r;
        }
        return $grouped;
    }
}
