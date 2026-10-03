<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\InventoryItem;
use App\Models\StockMovement;

/**
 * Inventory service — purchase receiving, stock adjustments with approval,
 * and stock movement logging. All operations use transactions.
 */
final class InventoryService
{
    /**
     * Receive an inventory purchase — increases stock + logs movement.
     *
     * @param array<int, array{item_id:int, quantity:int, unit_cost:string}> $items
     * @return array{ok: bool, error?: string}
     */
    public static function receivePurchase(int $purchaseId, array $items, Request $request): array
    {
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $total = 0.0;
            foreach ($items as $item) {
                $itemId = (int) $item['item_id'];
                $qty = (int) $item['quantity'];
                $cost = (float) $item['unit_cost'];
                $lineTotal = round($qty * $cost, 2);
                $total += $lineTotal;

                Database::execute(
                    'INSERT INTO inventory_purchase_items (purchase_id, item_id, quantity, unit_cost, line_total)
                     VALUES (?, ?, ?, ?, ?)',
                    [$purchaseId, $itemId, $qty, $cost, $lineTotal]
                );

                InventoryItem::adjustStock($itemId, $qty);
                $newStock = (int) Database::scalar('SELECT current_stock FROM inventory_items WHERE id = ?', [$itemId]);
                StockMovement::log('inventory', $itemId, null, 'purchase', $qty, 'purchase', $purchaseId, $newStock, Auth::id());
            }

            Database::execute('UPDATE inventory_purchases SET status = "received", total_amount = ? WHERE id = ?', [round($total, 2), $purchaseId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Inventory purchase receive failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not receive purchase.'];
        }

        AuditService::log('inventory.purchase_received', 'inventory', 'create', "Received inventory purchase #{$purchaseId}.", ['purchase_id' => $purchaseId], $request);
        return ['ok' => true];
    }

    /**
     * Request a stock adjustment — requires approval before stock changes.
     *
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function requestAdjustment(int $itemId, string $type, int $quantity, string $reason, Request $request): array
    {
        if (!in_array($type, ['increase', 'decrease'], true)) {
            return ['ok' => false, 'error' => 'Invalid adjustment type.'];
        }
        if ($quantity <= 0) {
            return ['ok' => false, 'error' => 'Quantity must be greater than zero.'];
        }
        if (trim($reason) === '') {
            return ['ok' => false, 'error' => 'A reason is required.'];
        }

        Database::execute(
            'INSERT INTO inventory_adjustments (item_id, adjustment_type, quantity, reason, status, requested_by)
             VALUES (?, ?, ?, ?, "pending", ?)',
            [$itemId, $type, $quantity, $reason, Auth::id()]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('inventory.adjustment_requested', 'inventory', 'create', "Requested {$type} of {$quantity} for item #{$itemId}: {$reason}", ['adjustment_id' => $id], $request);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Approve a stock adjustment — applies the stock change + logs movement.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function approveAdjustment(int $adjustmentId, Request $request): array
    {
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            $adj = Database::queryOne('SELECT * FROM inventory_adjustments WHERE id = ? FOR UPDATE', [$adjustmentId]);
            if ($adj === null || $adj['status'] !== 'pending') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Adjustment not found or already processed.'];
            }

            $delta = $adj['adjustment_type'] === 'increase' ? (int) $adj['quantity'] : -(int) $adj['quantity'];
            $newStock = (int) Database::scalar('SELECT current_stock FROM inventory_items WHERE id = ?', [$adj['item_id']]) + $delta;
            if ($newStock < 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Cannot reduce stock below zero.'];
            }

            InventoryItem::adjustStock((int) $adj['item_id'], $delta);
            StockMovement::log('inventory', (int) $adj['item_id'], null, 'adjustment', $delta, 'adjustment', $adjustmentId, $newStock, Auth::id());

            Database::execute(
                'UPDATE inventory_adjustments SET status = "approved", approved_by = ?, approved_at = NOW() WHERE id = ?',
                [Auth::id(), $adjustmentId]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Adjustment approval failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not approve adjustment.'];
        }

        AuditService::log('inventory.adjustment_approved', 'inventory', 'update', "Approved adjustment #{$adjustmentId}.", ['adjustment_id' => $adjustmentId], $request);
        return ['ok' => true];
    }

    /**
     * Reject a stock adjustment.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function rejectAdjustment(int $adjustmentId, Request $request): array
    {
        $adj = Database::queryOne('SELECT * FROM inventory_adjustments WHERE id = ?', [$adjustmentId]);
        if ($adj === null || $adj['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Adjustment not found or already processed.'];
        }
        Database::execute('UPDATE inventory_adjustments SET status = "rejected", approved_by = ?, approved_at = NOW() WHERE id = ?', [Auth::id(), $adjustmentId]);
        AuditService::log('inventory.adjustment_rejected', 'inventory', 'update', "Rejected adjustment #{$adjustmentId}.", ['adjustment_id' => $adjustmentId], $request);
        return ['ok' => true];
    }
}
