<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Supplier model — shared between Pharmacy and Inventory.
 */
final class Supplier extends Model
{
    protected static function table(): string { return 'suppliers'; }

    /** @return array<int, array<string, mixed>> */
    public static function options(): array
    {
        return Database::query('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');
    }
}
