<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Inventory item — non-medicine supplies with stock tracking.
 */
final class InventoryItem extends Model
{
    protected static function table(): string { return 'inventory_items'; }

    /** @return array<int, array<string, mixed>> */
    public static function withCategory(): array
    {
        return Database::query(
            'SELECT i.*, c.name AS category_name
             FROM inventory_items i
             LEFT JOIN inventory_categories c ON c.id = i.category_id
             WHERE i.is_active = 1
             ORDER BY c.name, i.name'
        );
    }

    /** Items low on stock. */
    public static function lowStock(): array
    {
        return Database::query(
            'SELECT * FROM inventory_items WHERE is_active = 1 AND current_stock < reorder_level ORDER BY current_stock ASC'
        );
    }

    /** @return array<string, int|float> */
    public static function counts(): array
    {
        return [
            'total'    => (int) Database::scalar('SELECT COUNT(*) FROM inventory_items WHERE is_active = 1'),
            'low_stock' => count(self::lowStock()),
            'value'    => (float) Database::scalar('SELECT COALESCE(SUM(current_stock * unit_value), 0) FROM inventory_items WHERE is_active = 1'),
        ];
    }

    /** Adjust current stock. Called inside transactions. */
    public static function adjustStock(int $itemId, int $delta): void
    {
        Database::execute('UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?', [$delta, $itemId]);
    }
}
