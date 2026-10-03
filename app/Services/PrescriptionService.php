<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Models\Consultation;
use App\Models\Prescription;

/**
 * Prescription administration — create from a consultation, manage
 * medicine items, draft → finalize lifecycle. Only the consulting
 * doctor (or an admin) can create/finalize prescriptions.
 */
final class PrescriptionService
{
    /**
     * Create a prescription (draft) for a consultation. One prescription
     * per consultation (enforced by unique constraint).
     *
     * @return array{ok: bool, error?: string, id?: int, code?: string}
     */
    public static function create(int $consultationId, array $items, ?string $notes, Request $request): array
    {
        $con = Consultation::find($consultationId);
        if ($con === null) {
            return ['ok' => false, 'error' => 'Consultation not found.'];
        }

        // Check no existing prescription.
        if (Prescription::findByConsultation($consultationId) !== null) {
            return ['ok' => false, 'error' => 'A prescription already exists for this consultation.'];
        }

        if ($items === []) {
            return ['ok' => false, 'error' => 'Add at least one medicine to the prescription.'];
        }

        $code = Prescription::nextCode();

        Database::execute(
            'INSERT INTO prescriptions (prescription_code, consultation_id, patient_id, doctor_id, status, notes)
             VALUES (?, ?, ?, ?, "draft", ?)',
            [$code, $consultationId, (int) $con['patient_id'], (int) $con['doctor_id'], $notes]
        );
        $id = Database::lastInsertId();

        self::replaceItems($id, $items);

        AuditService::log('prescription.created', 'prescriptions', 'create', "Created prescription {$code} for consultation {$con['consultation_code']}.", [
            'prescription_id' => $id, 'prescription_code' => $code, 'consultation_id' => $consultationId,
        ], $request);

        return ['ok' => true, 'id' => (int) $id, 'code' => $code];
    }

    /**
     * Update a draft prescription's items. Finalized prescriptions
     * cannot be modified.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function update(int $id, array $items, ?string $notes, Request $request): array
    {
        $rx = Prescription::find($id);
        if ($rx === null) {
            return ['ok' => false, 'error' => 'Prescription not found.'];
        }
        if ($rx['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Cannot edit a finalized prescription.'];
        }
        if ($items === []) {
            return ['ok' => false, 'error' => 'Add at least one medicine.'];
        }

        Database::execute('UPDATE prescriptions SET notes = ? WHERE id = ?', [$notes, $id]);
        self::replaceItems($id, $items);

        AuditService::log('prescription.updated', 'prescriptions', 'update', "Updated prescription {$rx['prescription_code']}.", [
            'prescription_id' => $id,
        ], $request);

        return ['ok' => true];
    }

    /**
     * Finalize a prescription — locks it for editing. The underlying
     * consultation must also be finalized first.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function finalize(int $id, Request $request): array
    {
        $rx = Prescription::find($id);
        if ($rx === null) {
            return ['ok' => false, 'error' => 'Prescription not found.'];
        }
        if ($rx['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only draft prescriptions can be finalized.'];
        }

        // Verify the consultation is finalized.
        $con = Consultation::find((int) $rx['consultation_id']);
        if ($con === null || $con['status'] === 'draft') {
            return ['ok' => false, 'error' => 'Finalize the consultation before finalizing the prescription.'];
        }

        // Verify at least one item exists.
        $count = (int) Database::scalar('SELECT COUNT(*) FROM prescription_items WHERE prescription_id = ?', [$id]);
        if ($count === 0) {
            return ['ok' => false, 'error' => 'Cannot finalize a prescription with no medicines.'];
        }

        Database::execute(
            "UPDATE prescriptions SET status = 'finalized', finalized_at = NOW() WHERE id = ?",
            [$id]
        );

        AuditService::log('prescription.finalized', 'prescriptions', 'finalize', "Finalized prescription {$rx['prescription_code']}.", [
            'prescription_id' => $id,
        ], $request);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    /**
     * Replace all items on a prescription (used by create + update).
     *
     * @param array<int, array{medicine_name: string, dosage: string, frequency: string, duration: string, quantity?: int, instructions?: string}> $items
     */
    private static function replaceItems(int $prescriptionId, array $items): void
    {
        Database::execute('DELETE FROM prescription_items WHERE prescription_id = ?', [$prescriptionId]);

        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['medicine_name']) || empty($item['dosage']) || empty($item['frequency']) || empty($item['duration'])) {
                continue; // skip incomplete rows
            }
            Database::execute(
                'INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, quantity, instructions, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $prescriptionId,
                    mb_substr((string) $item['medicine_name'], 0, 200),
                    mb_substr((string) $item['dosage'], 0, 50),
                    mb_substr((string) $item['frequency'], 0, 50),
                    mb_substr((string) $item['duration'], 0, 50),
                    !empty($item['quantity']) ? (int) $item['quantity'] : null,
                    !empty($item['instructions']) ? mb_substr((string) $item['instructions'], 0, 300) : null,
                    $sort++,
                ]
            );
        }
    }
}
