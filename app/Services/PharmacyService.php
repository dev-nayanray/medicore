<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineDispensing;
use App\Models\StockMovement;

/**
 * Pharmacy service — dispensing with FEFO batch deduction, returns,
 * purchase receiving with batch creation, and billing integration.
 * All stock operations use transactions + stock movement logging.
 */
final class PharmacyService
{
    /**
     * Dispense medicines — deducts from batches (FEFO), creates a
     * dispensing record, and optionally generates an invoice.
     *
     * @param array<int, array{medicine_id:int, quantity:int, instructions?:string}> $items
     * @return array{ok: bool, error?: string, id?: int, code?: string}
     */
    public static function dispense(array $data, array $items, Request $request): array
    {
        if ($items === []) {
            return ['ok' => false, 'error' => 'Add at least one medicine to dispense.'];
        }

        $patientId = (int) $data['patient_id'];
        $prescriptionId = !empty($data['prescription_id']) ? (int) $data['prescription_id'] : null;
        $consultationId = !empty($data['consultation_id']) ? (int) $data['consultation_id'] : null;

        $pdo = Database::pdo();
        $code = MedicineDispensing::nextCode();

        try {
            $pdo->beginTransaction();

            $dispensingItems = [];
            $invoiceItems = [];
            foreach ($items as $item) {
                $medicineId = (int) $item['medicine_id'];
                $qty = (int) $item['quantity'];
                if ($qty <= 0) continue;

                // Check stock.
                $stock = Medicine::totalStock($medicineId);
                if ($stock < $qty) {
                    $name = (string) Database::scalar('SELECT name FROM medicines WHERE id = ?', [$medicineId]);
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => "Insufficient stock for {$name} (have {$stock}, need {$qty})."];
                }

                // FEFO deduction.
                $used = MedicineBatch::deductFEFO($medicineId, $qty);
                if ($used === null) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Could not deduct stock — batch inconsistency.'];
                }

                $medicineName = (string) Database::scalar('SELECT name FROM medicines WHERE id = ?', [$medicineId]);
                foreach ($used as $u) {
                    $dispensingItems[] = [
                        'medicine_id' => $medicineId,
                        'batch_id' => $u['batch_id'],
                        'quantity' => $u['quantity'],
                        'unit_price' => $u['unit_price'],
                        'instructions' => $item['instructions'] ?? null,
                    ];
                    $invoiceItems[] = [
                        'description' => $medicineName . ' (' . $u['quantity'] . ' units)',
                        'quantity' => (string) $u['quantity'],
                        'unit_price' => (string) $u['unit_price'],
                    ];

                    // Log stock movement.
                    $batchStock = (int) Database::scalar('SELECT quantity_remaining FROM medicine_batches WHERE id = ?', [$u['batch_id']]);
                    StockMovement::log('medicine', $medicineId, $u['batch_id'], 'dispensing', -$u['quantity'], 'dispensing', null, $batchStock, Auth::id());
                }
            }

            // Create dispensing record.
            Database::execute(
                'INSERT INTO medicine_dispensings (dispensing_code, prescription_id, consultation_id, patient_id, status, notes, dispensed_by)
                 VALUES (?, ?, ?, ?, "dispensed", ?, ?)',
                [$code, $prescriptionId, $consultationId, $patientId, $data['notes'] ?? null, Auth::id()]
            );
            $dispensingId = (int) Database::lastInsertId();

            foreach ($dispensingItems as $di) {
                Database::execute(
                    'INSERT INTO medicine_dispensing_items (dispensing_id, medicine_id, batch_id, quantity_dispensed, unit_price, instructions)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$dispensingId, $di['medicine_id'], $di['batch_id'], $di['quantity'], $di['unit_price'], $di['instructions']]
                );
            }

            // Create invoice for the dispensed medicines (billing integration).
            $invoice = null;
            if (!empty($data['generate_invoice'])) {
                $invoiceCode = \App\Models\Invoice::nextCode();
                $subtotal = 0.0;
                foreach ($invoiceItems as $ii) {
                    $subtotal += (float) $ii['quantity'] * (float) $ii['unit_price'];
                }
                $subtotal = round($subtotal, 2);
                Database::execute(
                    'INSERT INTO invoices (invoice_code, patient_id, invoice_date, subtotal, discount_amount, discount_percentage, tax_percentage, tax_amount, total, paid_amount, balance_due, status, notes, created_by)
                     VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?, 0, ?, "sent", ?, ?)',
                    [$invoiceCode, $patientId, date('Y-m-d'), $subtotal, $subtotal, 'Medicine dispensing ' . $code, Auth::id()]
                );
                $invoiceId = (int) Database::lastInsertId();
                $sort = 0;
                foreach ($invoiceItems as $ii) {
                    $lt = round((float) $ii['quantity'] * (float) $ii['unit_price'], 2);
                    Database::execute(
                        'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount_amount, line_total, sort_order)
                         VALUES (?, NULL, ?, ?, ?, 0, ?, ?)',
                        [$invoiceId, $ii['description'], $ii['quantity'], $ii['unit_price'], $lt, $sort++]
                    );
                }
                Database::execute('UPDATE medicine_dispensings SET invoice_id = ? WHERE id = ?', [$invoiceId, $dispensingId]);
                $invoice = $invoiceCode;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Dispensing failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not complete dispensing. Please try again.'];
        }

        AuditService::log('pharmacy.dispensed', 'pharmacy', 'create', "Dispensed medicines ({$code}) for patient #{$patientId}." . ($invoice ? " Invoice {$invoice} created." : ''), [
            'dispensing_id' => $dispensingId, 'dispensing_code' => $code,
        ], $request);

        return ['ok' => true, 'id' => $dispensingId, 'code' => $code];
    }

    /**
     * Return a dispensed medicine — restores batch stock.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function returnMedicine(int $dispensingId, int $dispensingItemId, int $quantity, string $reason, Request $request): array
    {
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            $item = Database::queryOne('SELECT * FROM medicine_dispensing_items WHERE id = ? AND dispensing_id = ?', [$dispensingItemId, $dispensingId]);
            if ($item === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Dispensing item not found.'];
            }

            $alreadyReturned = (int) $item['quantity_returned'];
            $available = (int) $item['quantity_dispensed'] - $alreadyReturned;
            if ($quantity > $available) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Cannot return more than {$available} units."];
            }

            // Restore batch stock.
            MedicineBatch::restore((int) $item['batch_id'], $quantity);
            $batchStock = (int) Database::scalar('SELECT quantity_remaining FROM medicine_batches WHERE id = ?', [$item['batch_id']]);
            StockMovement::log('medicine', (int) $item['medicine_id'], (int) $item['batch_id'], 'return', $quantity, 'return', $dispensingId, $batchStock, Auth::id());

            // Update dispensing item.
            Database::execute('UPDATE medicine_dispensing_items SET quantity_returned = quantity_returned + ? WHERE id = ?', [$quantity, $dispensingItemId]);

            // Create return record.
            Database::execute(
                'INSERT INTO medicine_returns (dispensing_id, dispensing_item_id, medicine_id, batch_id, quantity_returned, reason, returned_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$dispensingId, $dispensingItemId, $item['medicine_id'], $item['batch_id'], $quantity, $reason, Auth::id()]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Medicine return failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not process return.'];
        }

        AuditService::log('pharmacy.returned', 'pharmacy', 'create', "Returned {$quantity} units from dispensing #{$dispensingId}: {$reason}", [
            'dispensing_id' => $dispensingId, 'quantity' => $quantity,
        ], $request);

        return ['ok' => true];
    }

    /**
     * Receive a medicine purchase — creates batches + stock movements.
     *
     * @param array<int, array{medicine_id:int, batch_number:string, expiry_date:string, quantity:int, unit_cost:string}> $items
     * @return array{ok: bool, error?: string}
     */
    public static function receivePurchase(int $purchaseId, array $items, Request $request): array
    {
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            $total = 0.0;
            $sort = 0;
            foreach ($items as $item) {
                $medicineId = (int) $item['medicine_id'];
                $qty = (int) $item['quantity'];
                $cost = (float) $item['unit_cost'];
                $lineTotal = round($qty * $cost, 2);
                $total += $lineTotal;

                Database::execute(
                    'INSERT INTO medicine_purchase_items (purchase_id, medicine_id, batch_number, expiry_date, quantity, unit_cost, line_total)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$purchaseId, $medicineId, $item['batch_number'], $item['expiry_date'], $qty, $cost, $lineTotal]
                );

                // Create batch.
                $sellPrice = round($cost * 1.25, 2); // 25% markup default
                Database::execute(
                    'INSERT INTO medicine_batches (medicine_id, batch_number, expiry_date, quantity_received, quantity_remaining, cost_price, sell_price, supplier_id, received_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT supplier_id FROM medicine_purchases WHERE id = ?), CURDATE())',
                    [$medicineId, $item['batch_number'], $item['expiry_date'], $qty, $qty, $cost, $sellPrice, $purchaseId]
                );
                $batchId = (int) Database::lastInsertId();

                // Stock movement.
                StockMovement::log('medicine', $medicineId, $batchId, 'purchase', $qty, 'purchase', $purchaseId, $qty, Auth::id());

                $sort++;
            }

            Database::execute('UPDATE medicine_purchases SET status = "received", total_amount = ? WHERE id = ?', [round($total, 2), $purchaseId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Purchase receive failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not receive purchase.'];
        }

        AuditService::log('pharmacy.purchase_received', 'pharmacy', 'create', "Received medicine purchase #{$purchaseId} ({$total} total).", [
            'purchase_id' => $purchaseId,
        ], $request);

        return ['ok' => true];
    }
}
