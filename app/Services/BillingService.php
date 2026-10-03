<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * Billing administration — invoice creation with line items, automatic
 * totals/discount/tax calculation, payment recording with duplicate
 * prevention, refunds, and invoice cancellation. All monetary operations
 * use transactions + row locking.
 */
final class BillingService
{
    /**
     * Create an invoice with line items. Calculates subtotal, discount,
     * tax, and total automatically. Starts in 'draft' status.
     *
     * @param array<int, array{service_id?:int, description:string, quantity:string, unit_price:string, discount_amount?:string}> $items
     * @return array{ok: bool, error?: string, id?: int, code?: string}
     */
    public static function createInvoice(array $data, array $items, Request $request): array
    {
        if ($items === []) {
            return ['ok' => false, 'error' => 'Add at least one line item.'];
        }

        $patientId = (int) $data['patient_id'];
        if (Database::scalar('SELECT COUNT(*) FROM patients WHERE id = ? AND archived_at IS NULL', [$patientId]) == 0) {
            return ['ok' => false, 'error' => 'Patient not found or archived.'];
        }

        $pdo = Database::pdo();
        $code = Invoice::nextCode();

        try {
            $pdo->beginTransaction();

            $subtotal = 0.0;
            $processedItems = [];
            $sort = 0;
            foreach ($items as $item) {
                if (empty($item['description'])) continue;
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $itemDiscount = (float) ($item['discount_amount'] ?? 0);
                $lineTotal = round(($qty * $price) - $itemDiscount, 2);
                if ($lineTotal < 0) $lineTotal = 0.0;
                $subtotal += $lineTotal;
                $processedItems[] = [
                    'service_id' => !empty($item['service_id']) ? (int) $item['service_id'] : null,
                    'description' => mb_substr((string) $item['description'], 0, 255),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_amount' => $itemDiscount,
                    'line_total' => $lineTotal,
                    'sort_order' => $sort++,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discountPercentage = (float) ($data['discount_percentage'] ?? 0);
            $discountAmount = round($subtotal * ($discountPercentage / 100), 2);
            $afterDiscount = round($subtotal - $discountAmount, 2);
            $taxPercentage = (float) ($data['tax_percentage'] ?? 0);
            $taxAmount = round($afterDiscount * ($taxPercentage / 100), 2);
            $total = round($afterDiscount + $taxAmount, 2);

            Database::execute(
                'INSERT INTO invoices
                    (invoice_code, patient_id, doctor_id, consultation_id, appointment_id, invoice_date,
                     subtotal, discount_amount, discount_percentage, tax_percentage, tax_amount, total,
                     paid_amount, balance_due, status, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, "draft", ?, ?)',
                [
                    $code, $patientId,
                    !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null,
                    !empty($data['consultation_id']) ? (int) $data['consultation_id'] : null,
                    !empty($data['appointment_id']) ? (int) $data['appointment_id'] : null,
                    $data['invoice_date'] ?? date('Y-m-d'),
                    $subtotal, $discountAmount, $discountPercentage, $taxPercentage, $taxAmount, $total,
                    $total, // balance_due = total initially
                    $data['notes'] ?? null,
                    Auth::id(),
                ]
            );
            $id = Database::lastInsertId();

            foreach ($processedItems as $item) {
                Database::execute(
                    'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount_amount, line_total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, $item['service_id'], $item['description'], $item['quantity'], $item['unit_price'], $item['discount_amount'], $item['line_total'], $item['sort_order']]
                );
            }

            // If no discount or tax, auto-send the invoice (move from draft to sent).
            $autoSend = !empty($data['auto_send']);
            if ($autoSend) {
                Database::execute("UPDATE invoices SET status = 'sent' WHERE id = ?", [$id]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Invoice creation failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not create invoice. Please try again.'];
        }

        AuditService::log('invoice.created', 'billing', 'create', "Created invoice {$code} (total {$total}).", [
            'invoice_id' => $id, 'invoice_code' => $code, 'total' => $total,
        ], $request);

        return ['ok' => true, 'id' => (int) $id, 'code' => $code];
    }

    /**
     * Record a payment against an invoice. Uses a transaction + row lock
     * on the invoice to prevent duplicate concurrent payments. Supports
     * partial payments. Recalculates paid_amount, balance_due, status.
     *
     * @return array{ok: bool, error?: string, id?: int, code?: string}
     */
    public static function recordPayment(int $invoiceId, array $data, Request $request): array
    {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            // Lock the invoice row.
            $inv = Database::queryOne('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if ($inv === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Invoice not found.'];
            }
            if (in_array($inv['status'], ['cancelled', 'draft'], true)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Cannot record payment on a ' . $inv['status'] . ' invoice.'];
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Payment amount must be greater than zero.'];
            }
            if ($amount > (float) $inv['balance_due'] + 0.01) {
                // Allow tiny rounding overpayment but not large overpayments.
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Payment exceeds the outstanding balance of ' . format_money((string) $inv['balance_due']) . '.'];
            }

            // Duplicate detection: same invoice + method + amount + reference within 5 minutes.
            $dupeWindow = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $dupe = (int) Database::scalar(
                "SELECT COUNT(*) FROM payments
                 WHERE invoice_id = ? AND payment_method = ? AND amount = ?
                   AND reference_number <=> ? AND status = 'completed' AND recorded_at > ?",
                [$invoiceId, $data['payment_method'], $amount, $data['reference_number'] ?? null, $dupeWindow]
            );
            if ($dupe > 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'A duplicate payment was detected (same invoice, method, amount and reference within 5 minutes).'];
            }

            $code = Payment::nextCode();
            Database::execute(
                'INSERT INTO payments (payment_code, invoice_id, patient_id, amount, payment_method, reference_number, status, is_refund, recorded_by, recorded_at)
                 VALUES (?, ?, ?, ?, ?, ?, "completed", 0, ?, ?)',
                [
                    $code, $invoiceId, (int) $inv['patient_id'], $amount,
                    $data['payment_method'],
                    !empty($data['reference_number']) ? $data['reference_number'] : null,
                    Auth::id(),
                    $data['recorded_at'] ?? date('Y-m-d H:i:s'),
                ]
            );
            $payId = Database::lastInsertId();

            // Recalculate the invoice totals.
            Invoice::recalculate($invoiceId);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Payment recording failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not record payment. Please try again.'];
        }

        AuditService::log('payment.recorded', 'payments', 'create', "Recorded payment {$code} of {$amount} against invoice {$inv['invoice_code']}.", [
            'payment_id' => $payId, 'payment_code' => $code, 'invoice_id' => $invoiceId, 'amount' => $amount,
        ], $request);

        return ['ok' => true, 'id' => (int) $payId, 'code' => $code];
    }

    /**
     * Record a refund — a negative payment. Requires authorization
     * (payments.approve). Cannot refund more than the paid amount.
     *
     * @return array{ok: bool, error?: string, id?: int, code?: string}
     */
    public static function recordRefund(int $invoiceId, array $data, Request $request): array
    {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $inv = Database::queryOne('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if ($inv === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Invoice not found.'];
            }
            if (in_array($inv['status'], ['cancelled', 'draft'])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Cannot refund a ' . $inv['status'] . ' invoice.'];
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Refund amount must be greater than zero.'];
            }
            if ($amount > (float) $inv['paid_amount'] + 0.01) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Refund exceeds the paid amount of ' . format_money((string) $inv['paid_amount']) . '.'];
            }

            if (empty($data['refund_reason'])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'A reason is required for refunds.'];
            }

            $code = Payment::nextCode();
            Database::execute(
                'INSERT INTO payments (payment_code, invoice_id, patient_id, amount, payment_method, reference_number, status, is_refund, refund_reason, authorized_by, recorded_by, recorded_at)
                 VALUES (?, ?, ?, ?, ?, ?, "completed", 1, ?, ?, ?, ?)',
                [
                    $code, $invoiceId, (int) $inv['patient_id'], -$amount,
                    $data['payment_method'],
                    !empty($data['reference_number']) ? $data['reference_number'] : null,
                    $data['refund_reason'],
                    Auth::id(), // authorized_by
                    Auth::id(), // recorded_by
                    $data['recorded_at'] ?? date('Y-m-d H:i:s'),
                ]
            );
            $payId = Database::lastInsertId();

            Invoice::recalculate($invoiceId);

            // If fully refunded (paid == 0), mark status as 'refunded'.
            $fresh = Invoice::find($invoiceId);
            if ($fresh !== null && (float) $fresh['paid_amount'] <= 0 && (float) $fresh['total'] > 0) {
                // Check if there was ever a payment (to distinguish from 'sent').
                $hadPayment = (int) Database::scalar(
                    "SELECT COUNT(*) FROM payments WHERE invoice_id = ? AND is_refund = 0 AND status = 'completed'",
                    [$invoiceId]
                );
                if ($hadPayment > 0) {
                    Database::execute("UPDATE invoices SET status = 'refunded' WHERE id = ?", [$invoiceId]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Refund recording failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not record refund. Please try again.'];
        }

        AuditService::log('payment.refunded', 'payments', 'create', "Recorded refund {$code} of {$amount} against invoice {$inv['invoice_code']}: {$data['refund_reason']}", [
            'payment_id' => $payId, 'payment_code' => $code, 'invoice_id' => $invoiceId, 'amount' => $amount,
        ], $request);

        return ['ok' => true, 'id' => (int) $payId, 'code' => $code];
    }

    /**
     * Cancel an invoice with a reason. Only draft / sent / partially_paid
     * invoices can be cancelled (paid invoices must be refunded first).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function cancelInvoice(int $invoiceId, string $reason, Request $request): array
    {
        $inv = Invoice::find($invoiceId);
        if ($inv === null) {
            return ['ok' => false, 'error' => 'Invoice not found.'];
        }
        if (in_array($inv['status'], ['cancelled'], true)) {
            return ['ok' => false, 'error' => 'Invoice is already cancelled.'];
        }
        if ($inv['status'] === 'paid') {
            return ['ok' => false, 'error' => 'Cannot cancel a paid invoice — refund the payments first.'];
        }
        if (trim($reason) === '') {
            return ['ok' => false, 'error' => 'A cancellation reason is required.'];
        }

        Database::execute(
            "UPDATE invoices SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ? WHERE id = ?",
            [Auth::id(), mb_substr($reason, 0, 300), $invoiceId]
        );

        // Void any pending payments.
        Database::execute(
            "UPDATE payments SET status = 'void' WHERE invoice_id = ? AND status = 'pending'",
            [$invoiceId]
        );

        AuditService::log('invoice.cancelled', 'billing', 'update', "Cancelled invoice {$inv['invoice_code']}: {$reason}", [
            'invoice_id' => $invoiceId,
        ], $request);

        return ['ok' => true];
    }

    /**
     * Update invoice notes (only non-cancelled invoices).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function updateNotes(int $invoiceId, string $notes, Request $request): array
    {
        $inv = Invoice::find($invoiceId);
        if ($inv === null) {
            return ['ok' => false, 'error' => 'Invoice not found.'];
        }
        if ($inv['status'] === 'cancelled') {
            return ['ok' => false, 'error' => 'Cannot update a cancelled invoice.'];
        }
        Database::execute('UPDATE invoices SET notes = ? WHERE id = ?', [mb_substr($notes, 0, 65535), $invoiceId]);
        AuditService::log('invoice.notes_updated', 'billing', 'update', "Updated notes on {$inv['invoice_code']}.", [
            'invoice_id' => $invoiceId,
        ], $request);
        return ['ok' => true];
    }
}
