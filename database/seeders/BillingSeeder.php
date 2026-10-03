<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Realistic invoices + payments for seeded patients. Covers paid,
 * partially-paid, and outstanding scenarios. Also seeds a few expenses
 * for the P&L report.
 */
final class BillingSeeder extends Seeder
{
    public static function label(): string { return 'Billing'; }
    public static function order(): int { return 180; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'expenses', 'payments', 'invoice_items', 'invoices');

        $patients = $db->query('SELECT id, patient_code, first_name, last_name FROM patients WHERE archived_at IS NULL ORDER BY id LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
        $doctors = $db->query('SELECT doc.id, doc.department_id FROM doctors doc WHERE doc.archived_at IS NULL LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
        $services = [];
        foreach ($db->query('SELECT id, name, category, price FROM services WHERE is_active = 1') as $s) {
            $services[$s['category']][] = $s;
        }

        if (count($patients) < 4 || count($doctors) < 1) return;

        $year = (int) date('Y');
        $invSeq = 0;
        $paySeq = 0;
        $expSeq = 0;
        $now = time();

        $invInsert = $db->prepare(
            'INSERT INTO invoices (invoice_code, patient_id, doctor_id, consultation_id, appointment_id, invoice_date,
                subtotal, discount_amount, discount_percentage, tax_percentage, tax_amount, total,
                paid_amount, balance_due, status, notes, created_by, created_at)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $itemInsert = $db->prepare(
            'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount_amount, line_total, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $payInsert = $db->prepare(
            'INSERT INTO payments (payment_code, invoice_id, patient_id, amount, payment_method, reference_number, status, is_refund, recorded_by, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, "completed", 0, ?, ?)'
        );

        $adminId = (int) $db->query("SELECT id FROM users WHERE email = 'admin@medicore.test'")->fetchColumn() ?: null;
        $rahimId = (int) $db->query("SELECT id FROM users WHERE email = 'rahim@medicore.test'")->fetchColumn() ?: $adminId;

        // --- Invoices spanning the last 30 days ---
        $invoicePlans = [
            // [patient_idx, days_ago, items: [category, count], discount_pct, tax_pct, status, payments]
            [0, 3, [['consultation', 1], ['laboratory', 2]], 0, 0, 'paid', [['cash', null]]],
            [1, 5, [['consultation', 1], ['laboratory', 1], ['medicine', 1]], 0, 0, 'paid', [['card', 'TXN-2024-001234']]],
            [2, 7, [['consultation', 1], ['procedure', 1], ['laboratory', 2]], 5, 0, 'partially_paid', [['mobile_banking', 'BKASH-7788']]],
            [3, 1, [['consultation', 1], ['laboratory', 1]], 0, 0, 'sent', []], // unpaid
            [4, 10, [['consultation', 1], ['admission', 1]], 0, 5, 'paid', [['cash', null], ['cash', null]]],
            [5, 2, [['consultation', 1], ['laboratory', 3], ['medicine', 2]], 0, 0, 'partially_paid', [['insurance', 'INS-CLM-991']]],
            [6, 15, [['consultation', 1], ['procedure', 1]], 10, 0, 'paid', [['bank_transfer', 'TRF-4471']]],
            [7, 20, [['consultation', 1], ['laboratory', 1]], 0, 0, 'cancelled', []],
        ];

        foreach ($invoicePlans as $plan) {
            [$pIdx, $daysAgo, $itemSpecs, $discPct, $taxPct, $status, $payments] = $plan;
            $patient = $patients[$pIdx] ?? null;
            if ($patient === null) continue;

            $invSeq++;
            $code = sprintf('INV-%s-%05d', $year, $invSeq);
            $date = date('Y-m-d', $now - $daysAgo * 86400);

            // Build line items.
            $items = [];
            $subtotal = 0.0;
            $sort = 0;
            foreach ($itemSpecs as [$cat, $count]) {
                $catServices = $services[$cat] ?? [];
                for ($i = 0; $i < $count; $i++) {
                    $svc = $catServices[array_rand($catServices)] ?? null;
                    if ($svc === null) continue;
                    $qty = 1;
                    $price = (float) $svc['price'];
                    $lineTotal = round($qty * $price, 2);
                    $subtotal += $lineTotal;
                    $items[] = [
                        'service_id' => (int) $svc['id'],
                        'description' => $svc['name'],
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'discount_amount' => 0,
                        'line_total' => $lineTotal,
                        'sort_order' => $sort++,
                    ];
                }
            }

            $subtotal = round($subtotal, 2);
            $discountAmount = round($subtotal * ($discPct / 100), 2);
            $afterDiscount = $subtotal - $discountAmount;
            $taxAmount = round($afterDiscount * ($taxPct / 100), 2);
            $total = round($afterDiscount + $taxAmount, 2);

            $invStatus = $status === 'cancelled' ? 'cancelled' : ($status === 'sent' ? 'sent' : ($status === 'partially_paid' ? 'partially_paid' : 'paid'));
            $cancelledAt = $status === 'cancelled' ? date('Y-m-d H:i:s', $now - $daysAgo * 86400 + 3600) : null;
            $cancelledBy = $status === 'cancelled' ? $adminId : null;
            $cancelReason = $status === 'cancelled' ? 'Patient did not proceed with the tests.' : null;

            $invInsert->execute([
                $code, (int) $patient['id'], (int) $doctors[0]['id'], $date,
                $subtotal, $discountAmount, $discPct, $taxPct, $taxAmount, $total,
                0, $total, $invStatus, null, $adminId, date('Y-m-d H:i:s', $now - $daysAgo * 86400),
            ]);
            $invId = (int) $db->lastInsertId();

            // Update cancelled fields if needed.
            if ($status === 'cancelled') {
                $db->prepare('UPDATE invoices SET cancelled_at = ?, cancelled_by = ?, cancellation_reason = ? WHERE id = ?')
                   ->execute([$cancelledAt, $cancelledBy, $cancelReason, $invId]);
            }

            // Insert items.
            foreach ($items as $item) {
                $itemInsert->execute([
                    $invId, $item['service_id'], $item['description'], $item['quantity'],
                    $item['unit_price'], $item['discount_amount'], $item['line_total'], $item['sort_order'],
                ]);
            }

            // Record payments.
            if ($status !== 'cancelled' && $status !== 'sent') {
                $totalPaid = 0;
                $payCount = count($payments);
                foreach ($payments as $idx => [$method, $ref]) {
                    $paySeq++;
                    $payCode = sprintf('PAY-%s-%05d', $year, $paySeq);
                    // Split the total across payments.
                    $amt = $idx === $payCount - 1 ? round($total - $totalPaid, 2) : round($total / $payCount, 2);
                    if ($status === 'partially_paid') {
                        $amt = round($total * 0.4, 2); // 40% partial payment
                    }
                    $totalPaid += $amt;
                    $payInsert->execute([
                        $payCode, $invId, (int) $patient['id'], $amt, $method, $ref,
                        $rahimId, date('Y-m-d H:i:s', $now - $daysAgo * 86400 + 1800),
                    ]);
                }
                // Recalculate paid + balance + status.
                $paid = $totalPaid;
                $balance = round($total - $paid, 2);
                $finalStatus = $paid >= $total ? 'paid' : 'partially_paid';
                $db->prepare('UPDATE invoices SET paid_amount = ?, balance_due = ?, status = ? WHERE id = ?')
                   ->execute([$paid, $balance, $finalStatus, $invId]);
            }
        }

        // --- One refund on the first invoice ---
        $firstInv = $db->query('SELECT id, patient_id, paid_amount, invoice_code FROM invoices ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($firstInv && (float) $firstInv['paid_amount'] > 0) {
            $paySeq++;
            $refundCode = sprintf('PAY-%s-%05d', $year, $paySeq);
            $refundAmt = round((float) $firstInv['paid_amount'] * 0.3, 2); // 30% refund
            $db->prepare(
                'INSERT INTO payments (payment_code, invoice_id, patient_id, amount, payment_method, reference_number, status, is_refund, refund_reason, authorized_by, recorded_by, recorded_at)
                 VALUES (?, ?, ?, ?, "cash", NULL, "completed", 1, "Duplicate service — patient returned for re-billing", ?, ?, NOW())'
            )->execute([$refundCode, (int) $firstInv['id'], (int) $firstInv['patient_id'], -$refundAmt, $adminId, $adminId]);

            // Recalculate.
            $paid = (float) $firstInv['paid_amount'] - $refundAmt;
            $total = (float) $db->query('SELECT total FROM invoices WHERE id = ' . (int) $firstInv['id'])->fetchColumn();
            $balance = round($total - $paid, 2);
            $finalStatus = $paid >= $total ? 'paid' : 'partially_paid';
            $db->prepare('UPDATE invoices SET paid_amount = ?, balance_due = ?, status = ? WHERE id = ?')
               ->execute([$paid, $balance, $finalStatus, (int) $firstInv['id']]);
        }

        // --- Expenses for the P&L ---
        $expInsert = $db->prepare(
            'INSERT INTO expenses (expense_code, category, description, amount, expense_date, paid_to, payment_method, reference_number, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $expensePlans = [
            ['salaries', 'Monthly nursing staff salary', 85000, 'Nursing Department', 'bank_transfer', 'SAL-JAN-001'],
            ['utilities', 'Electricity bill — January', 12500, 'WASA Electric', 'bank_transfer', 'ELEC-JAN-2024'],
            ['supplies', 'Surgical gloves + syringes restock', 8500, 'MediSupply Ltd', 'cheque', 'CHQ-0451'],
            ['maintenance', 'AC service — OPD wing', 6000, 'CoolTech Services', 'cash', null],
            ['equipment', 'Pulse oximeter (x3 units)', 15000, 'MedEquip BD', 'bank_transfer', 'TRF-9981'],
            ['rent', 'Parking lot lease — January', 10000, 'City Parking Authority', 'bank_transfer', 'RENT-JAN'],
            ['supplies', 'Laboratory reagents — monthly', 18000, 'LabChem BD', 'bank_transfer', 'TRF-9982'],
            ['utilities', 'Internet + phone — January', 3500, 'ISP Telecom', 'card', 'CARD-3344'],
        ];
        foreach ($expensePlans as $i => [$cat, $desc, $amt, $paidTo, $method, $ref]) {
            $expSeq++;
            $expCode = sprintf('EXP-%s-%05d', $year, $expSeq);
            $expDate = date('Y-m-d', $now - random_int(1, 25) * 86400);
            $expInsert->execute([$expCode, $cat, $desc, $amt, $expDate, $paidTo, $method, $ref, $adminId]);
        }
    }
}
