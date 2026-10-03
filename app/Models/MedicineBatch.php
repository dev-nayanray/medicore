<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Medicine batch — physical stock with expiry date and remaining quantity.
 */
final class MedicineBatch extends Model
{
    protected static function table(): string { return 'medicine_batches'; }

    /** Deduct from the earliest-expiring batch with stock (FEFO). Returns true if successful. */
    public static function deductFEFO(int $medicineId, int $quantity): ?array
    {
        $batches = Medicine::batches($medicineId);
        $remaining = $quantity;
        $used = [];
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $take = min($remaining, (int) $batch['quantity_remaining']);
            Database::execute('UPDATE medicine_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ?', [$take, $batch['id']]);
            $used[] = ['batch_id' => (int) $batch['id'], 'quantity' => $take, 'unit_price' => (float) $batch['sell_price']];
            $remaining -= $take;
        }
        return $remaining > 0 ? null : $used;
    }

    /** Restore stock to a specific batch (for returns). */
    public static function restore(int $batchId, int $quantity): void
    {
        Database::execute('UPDATE medicine_batches SET quantity_remaining = quantity_remaining + ? WHERE id = ?', [$quantity, $batchId]);
    }
}
