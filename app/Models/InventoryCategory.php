<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Inventory category — groups for inventory items.
 */
final class InventoryCategory extends Model
{
    protected static function table(): string { return 'inventory_categories'; }

    /** @return array<int, array<string, mixed>> */
    public static function options(): array
    {
        return Database::query('SELECT id, name FROM inventory_categories WHERE is_active = 1 ORDER BY name');
    }
}
