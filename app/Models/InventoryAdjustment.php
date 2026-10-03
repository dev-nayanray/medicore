<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Inventory adjustment — stock increase/decrease with approval workflow.
 */
final class InventoryAdjustment extends Model
{
    protected static function table(): string { return 'inventory_adjustments'; }

    /** @return array<int, array<string, mixed>> */
    public static function pending(): array
    {
        return Database::query(
            'SELECT a.*, i.name AS item_name, i.sku, u.name AS requested_by_name
             FROM inventory_adjustments a
             INNER JOIN inventory_items i ON i.id = a.item_id
             LEFT JOIN users u ON u.id = a.requested_by
             WHERE a.status = "pending" ORDER BY a.created_at DESC'
        );
    }
}
