<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Medicine model — catalogue with batch-level stock tracking.
 */
final class Medicine extends Model
{
    public const DOSAGE_FORMS = ['tablet', 'capsule', 'syrup', 'injection', 'ointment', 'drops', 'inhaler', 'other'];

    protected static function table(): string { return 'medicines'; }

    /** Total remaining stock across all batches. */
    public static function totalStock(int $medicineId): int
    {
        return (int) Database::scalar('SELECT COALESCE(SUM(quantity_remaining), 0) FROM medicine_batches WHERE medicine_id = ?', [$medicineId]);
    }

    /** Batches for a medicine, sorted FEFO (earliest expiry first). */
    public static function batches(int $medicineId): array
    {
        return Database::query(
            'SELECT * FROM medicine_batches WHERE medicine_id = ? AND quantity_remaining > 0 ORDER BY expiry_date ASC, id ASC',
            [$medicineId]
        );
    }

    /** All batches (including empty) for admin view. */
    public static function allBatches(int $medicineId): array
    {
        return Database::query(
            'SELECT mb.*, s.name AS supplier_name FROM medicine_batches mb
             LEFT JOIN suppliers s ON s.id = mb.supplier_id
             WHERE mb.medicine_id = ? ORDER BY mb.expiry_date DESC, mb.id DESC',
            [$medicineId]
        );
    }

    /** Medicines low on stock (total across batches < reorder_level). */
    public static function lowStock(): array
    {
        return Database::query(
            'SELECT m.*, COALESCE(SUM(mb.quantity_remaining), 0) AS stock
             FROM medicines m
             LEFT JOIN medicine_batches mb ON mb.medicine_id = m.id
             WHERE m.is_active = 1
             GROUP BY m.id
             HAVING stock < m.reorder_level
             ORDER BY stock ASC'
        );
    }

    /** Batches expiring within N days. */
    public static function expiringBatches(int $days = 90): array
    {
        return Database::query(
            'SELECT mb.*, m.name AS medicine_name, m.generic_name
             FROM medicine_batches mb
             INNER JOIN medicines m ON m.id = mb.medicine_id
             WHERE mb.quantity_remaining > 0
               AND mb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY mb.expiry_date ASC',
            [(string) $days]
        );
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        return [
            'total'   => (int) Database::scalar('SELECT COUNT(*) FROM medicines WHERE is_active = 1'),
            'low_stock' => count(self::lowStock()),
            'expiring' => count(self::expiringBatches(90)),
        ];
    }
}
