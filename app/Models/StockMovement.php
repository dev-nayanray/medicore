<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Stock movement — unified log for all medicine + inventory stock changes.
 */
final class StockMovement extends Model
{
    protected static function table(): string { return 'stock_movements'; }

    /** Log a stock movement. Called inside transactions. */
    public static function log(string $itemType, int $itemId, ?int $batchId, string $type, int $qty, ?string $refType, ?int $refId, int $balanceAfter, ?int $createdBy, ?string $notes = null): void
    {
        Database::execute(
            'INSERT INTO stock_movements (item_type, item_id, batch_id, movement_type, quantity, reference_type, reference_id, balance_after, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$itemType, $itemId, $batchId, $type, $qty, $refType, $refId, $balanceAfter, $notes, $createdBy]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function forMedicine(int $medicineId): array
    {
        return Database::query(
            'SELECT sm.*, u.name AS created_by_name
             FROM stock_movements sm
             LEFT JOIN users u ON u.id = sm.created_by
             WHERE sm.item_type = "medicine" AND sm.item_id = ?
             ORDER BY sm.created_at DESC, sm.id DESC LIMIT 30',
            [$medicineId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function forInventoryItem(int $itemId): array
    {
        return Database::query(
            'SELECT sm.*, u.name AS created_by_name
             FROM stock_movements sm
             LEFT JOIN users u ON u.id = sm.created_by
             WHERE sm.item_type = "inventory" AND sm.item_id = ?
             ORDER BY sm.created_at DESC, sm.id DESC LIMIT 30',
            [$itemId]
        );
    }
}
