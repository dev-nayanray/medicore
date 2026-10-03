<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\LabOrder;

/**
 * Laboratory service — order creation with billing integration, sample
 * collection, result entry, verification, release, and critical alerts.
 * The verification step requires a DIFFERENT user than the one who entered
 * the result (separation of duties).
 */
final class LaboratoryService
{
    /**
     * Create a lab order — links to patient/consultation, creates an invoice.
     *
     * @param array<int, int> $testIds
     * @return array{ok: bool, error?: string, id?: int, code?: string}
     */
    public static function createOrder(array $data, array $testIds, Request $request): array
    {
        if ($testIds === []) {
            return ['ok' => false, 'error' => 'Select at least one test.'];
        }

        $patientId = (int) $data['patient_id'];
        $consultationId = !empty($data['consultation_id']) ? (int) $data['consultation_id'] : null;
        $doctorId = !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null;

        $pdo = Database::pdo();
        $code = LabOrder::nextCode();

        try {
            $pdo->beginTransaction();

            // Create order.
            Database::execute(
                'INSERT INTO lab_orders (order_code, patient_id, doctor_id, consultation_id, status, notes, created_by)
                 VALUES (?, ?, ?, ?, "ordered", ?, ?)',
                [$code, $patientId, $doctorId, $consultationId, $data['notes'] ?? null, Auth::id()]
            );
            $orderId = (int) Database::lastInsertId();

            // Create order items + compute invoice total.
            $invoiceItems = [];
            $subtotal = 0.0;
            foreach ($testIds as $testId) {
                $test = Database::queryOne('SELECT * FROM lab_tests WHERE id = ?', [$testId]);
                if ($test === null || (int) $test['is_active'] !== 1) continue;

                Database::execute(
                    'INSERT INTO lab_order_items (order_id, test_id, status)
                     VALUES (?, ?, "ordered")',
                    [$orderId, $testId]
                );
                $invoiceItems[] = [
                    'test_id' => $testId,
                    'name' => $test['name'],
                    'price' => (float) $test['price'],
                ];
                $subtotal += (float) $test['price'];
            }

            $subtotal = round($subtotal, 2);

            // Create invoice (billing integration).
            $invoiceCode = \App\Models\Invoice::nextCode();
            Database::execute(
                'INSERT INTO invoices (invoice_code, patient_id, doctor_id, consultation_id, invoice_date, subtotal, discount_amount, discount_percentage, tax_percentage, tax_amount, total, paid_amount, balance_due, status, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, 0, ?, "sent", ?, ?)',
                [$invoiceCode, $patientId, $doctorId, $consultationId, date('Y-m-d'), $subtotal, $subtotal, 'Lab order ' . $code, Auth::id()]
            );
            $invoiceId = (int) Database::lastInsertId();

            $sort = 0;
            foreach ($invoiceItems as $ii) {
                Database::execute(
                    'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount_amount, line_total, sort_order)
                     VALUES (?, NULL, ?, 1, ?, 0, ?, ?)',
                    [$invoiceId, $ii['name'], $ii['price'], $ii['price'], $sort++]
                );
            }

            // Link invoice to order.
            Database::execute('UPDATE lab_orders SET invoice_id = ? WHERE id = ?', [$invoiceId, $orderId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Lab order creation failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not create lab order.'];
        }

        AuditService::log('lab.order_created', 'laboratory', 'create', "Created lab order {$code} for patient #{$patientId}. Invoice {$invoiceCode} created.", [
            'lab_order_id' => $orderId, 'lab_order_code' => $code,
        ], $request);

        return ['ok' => true, 'id' => $orderId, 'code' => $code];
    }

    /**
     * Collect sample for an order item — stamps collected_at/by.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function collectSample(int $orderItemId, Request $request): array
    {
        $item = Database::queryOne('SELECT * FROM lab_order_items WHERE id = ?', [$orderItemId]);
        if ($item === null) return ['ok' => false, 'error' => 'Test item not found.'];
        if ($item['status'] !== 'ordered') return ['ok' => false, 'error' => 'Sample already collected or test in progress.'];

        Database::execute(
            'UPDATE lab_order_items SET status = "collected", collected_at = NOW(), collected_by = ? WHERE id = ?',
            [Auth::id(), $orderItemId]
        );

        // Update order status if all items are collected.
        self::recalculateOrderStatus((int) $item['order_id']);

        AuditService::log('lab.sample_collected', 'laboratory', 'update', "Collected sample for test item #{$orderItemId}.", ['order_item_id' => $orderItemId], $request);
        return ['ok' => true];
    }

    /**
     * Enter test result — stamps resulted_at/by. If result is flagged
     * critical, creates a critical alert.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function enterResult(int $orderItemId, array $data, Request $request): array
    {
        $item = Database::queryOne('SELECT * FROM lab_order_items WHERE id = ?', [$orderItemId]);
        if ($item === null) return ['ok' => false, 'error' => 'Test item not found.'];
        if (!in_array($item['status'], ['collected', 'resulted'], true)) {
            return ['ok' => false, 'error' => 'Sample must be collected before entering results.'];
        }

        $isCritical = !empty($data['is_critical']) ? 1 : 0;

        Database::execute(
            'UPDATE lab_order_items SET result_value = ?, result_unit = ?, reference_range = ?, is_critical = ?, notes = ?, status = "resulted", resulted_at = NOW(), resulted_by = ? WHERE id = ?',
            [
                $data['result_value'] ?? null, $data['result_unit'] ?? null,
                $data['reference_range'] ?? null, $isCritical, $data['notes'] ?? null,
                Auth::id(), $orderItemId,
            ]
        );

        // Create critical alert if flagged.
        if ($isCritical) {
            $test = Database::queryOne('SELECT t.name FROM lab_order_items oi INNER JOIN lab_tests t ON t.id = oi.test_id WHERE oi.id = ?', [$orderItemId]);
            Database::execute(
                'INSERT INTO lab_critical_alerts (order_item_id, test_name, result_value, reference_range, alert_status)
                 VALUES (?, ?, ?, ?, "pending")',
                [
                    $orderItemId,
                    $test['name'] ?? 'Unknown',
                    $data['result_value'] ?? '',
                    $data['reference_range'] ?? null,
                ]
            );
        }

        self::recalculateOrderStatus((int) $item['order_id']);

        AuditService::log('lab.result_entered', 'laboratory', 'update', "Entered result for test item #{$orderItemId}." . ($isCritical ? ' [CRITICAL]' : ''), ['order_item_id' => $orderItemId], $request);
        return ['ok' => true];
    }

    /**
     * Verify a result — requires a different user than the one who entered
     * the result (separation of duties).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function verifyResult(int $orderItemId, Request $request): array
    {
        $item = Database::queryOne('SELECT * FROM lab_order_items WHERE id = ?', [$orderItemId]);
        if ($item === null) return ['ok' => false, 'error' => 'Test item not found.'];
        if ($item['status'] !== 'resulted') return ['ok' => false, 'error' => 'Result must be entered before verification.'];

        // Separation of duties — cannot verify own result.
        if ($item['resulted_by'] !== null && (int) $item['resulted_by'] === Auth::id()) {
            return ['ok' => false, 'error' => 'You cannot verify your own result — a different person must verify.'];
        }

        Database::execute(
            'UPDATE lab_order_items SET status = "verified", verified_at = NOW(), verified_by = ? WHERE id = ?',
            [Auth::id(), $orderItemId]
        );

        self::recalculateOrderStatus((int) $item['order_id']);

        AuditService::log('lab.result_verified', 'laboratory', 'update', "Verified test item #{$orderItemId}.", ['order_item_id' => $orderItemId], $request);
        return ['ok' => true];
    }

    /**
     * Release a verified result — the report is now printable and visible
     * to the ordering doctor.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function releaseResult(int $orderItemId, Request $request): array
    {
        $item = Database::queryOne('SELECT * FROM lab_order_items WHERE id = ?', [$orderItemId]);
        if ($item === null) return ['ok' => false, 'error' => 'Test item not found.'];
        if ($item['status'] !== 'verified') return ['ok' => false, 'error' => 'Result must be verified before release.'];

        Database::execute(
            'UPDATE lab_order_items SET status = "released", released_at = NOW(), released_by = ? WHERE id = ?',
            [Auth::id(), $orderItemId]
        );

        self::recalculateOrderStatus((int) $item['order_id']);

        AuditService::log('lab.result_released', 'laboratory', 'update', "Released test item #{$orderItemId}.", ['order_item_id' => $orderItemId], $request);
        return ['ok' => true];
    }

    /**
     * Acknowledge a critical alert.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function acknowledgeAlert(int $alertId, Request $request): array
    {
        $alert = Database::queryOne('SELECT * FROM lab_critical_alerts WHERE id = ?', [$alertId]);
        if ($alert === null || $alert['alert_status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Alert not found or already acknowledged.'];
        }
        Database::execute(
            'UPDATE lab_critical_alerts SET alert_status = "acknowledged", acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ?',
            [Auth::id(), $alertId]
        );
        AuditService::log('lab.alert_acknowledged', 'laboratory', 'update', "Acknowledged critical alert #{$alertId}.", ['alert_id' => $alertId], $request);
        return ['ok' => true];
    }

    /**
     * Recalculate order status based on item statuses.
     */
    private static function recalculateOrderStatus(int $orderId): void
    {
        $items = Database::query('SELECT status FROM lab_order_items WHERE order_id = ?', [$orderId]);
        if ($items === []) return;

        $statuses = array_column($items, 'status');
        if (in_array('ordered', $statuses, true)) {
            $status = 'collected'; // at least one collected, some still ordered
        } elseif (in_array('collected', $statuses, true)) {
            $status = 'collected';
        } elseif (in_array('resulted', $statuses, true)) {
            $status = 'resulted';
        } elseif (in_array('verified', $statuses, true)) {
            $status = 'verified';
        } elseif (count(array_unique($statuses)) === 1 && $statuses[0] === 'released') {
            $status = 'released';
        } else {
            $status = 'resulted';
        }

        Database::execute('UPDATE lab_orders SET status = ? WHERE id = ?', [$status, $orderId]);
    }
}
