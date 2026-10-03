<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Invoice line items — billable services on an invoice.
 */
final class InvoiceItem extends Model
{
    protected static function table(): string
    {
        return 'invoice_items';
    }

    /** @return array<int, array<string, mixed>> */
    public static function forInvoice(int $invoiceId): array
    {
        return Database::query(
            'SELECT ii.*, s.category AS service_category FROM invoice_items ii
             LEFT JOIN services s ON s.id = ii.service_id
             WHERE ii.invoice_id = ? ORDER BY ii.sort_order, ii.id',
            [$invoiceId]
        );
    }
}
