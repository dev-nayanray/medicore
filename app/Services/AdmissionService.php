<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Admission;
use App\Models\Bed;

/**
 * Admission service — admit, transfer, discharge with transaction-
 * protected bed allocation. Bed status changes are atomic.
 */
final class AdmissionService
{
    public static function admit(array $data, Request $request): array
    {
        $patientId = (int) $data['patient_id'];
        $bedId = !empty($data['bed_id']) ? (int) $data['bed_id'] : null;
        $pdo = Database::pdo();
        $code = Admission::nextCode();
        try {
            $pdo->beginTransaction();
            if ($bedId !== null) {
                $bed = Database::queryOne('SELECT * FROM beds WHERE id = ? FOR UPDATE', [$bedId]);
                if ($bed === null) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Bed not found.']; }
                if ($bed['status'] !== 'available') { $pdo->rollBack(); return ['ok' => false, 'error' => 'Bed is not available (status: ' . $bed['status'] . ').']; }
                Database::execute("UPDATE beds SET status = 'occupied', current_admission_id = ? WHERE id = ?", [0, $bedId]); // admission_id set after insert
            }
            $doctorId = !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null;
            $wardId = !empty($data['ward_id']) ? (int) $data['ward_id'] : null;
            $roomId = !empty($data['room_id']) ? (int) $data['room_id'] : null;
            Database::execute(
                'INSERT INTO admissions (admission_code, patient_id, doctor_id, ward_id, room_id, bed_id, admission_type, admission_date, expected_discharge, admission_reason, diagnosis_at_admission, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "admitted", ?)',
                [$code, $patientId, $doctorId, $wardId, $roomId, $bedId, $data['admission_type'] ?? 'scheduled',
                 $data['admission_date'] ?? date('Y-m-d H:i:s'), $data['expected_discharge'] ?? null,
                 $data['admission_reason'] ?? null, $data['diagnosis_at_admission'] ?? null, Auth::id()]
            );
            $admissionId = (int) Database::lastInsertId();
            if ($bedId !== null) {
                Database::execute('UPDATE beds SET current_admission_id = ? WHERE id = ?', [$admissionId, $bedId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Admission failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not admit patient.'];
        }
        AuditService::log('admission.created', 'admissions', 'create', "Admitted patient #{$patientId} ({$code})." . ($bedId ? " Bed #{$bedId} allocated." : ''), ['admission_id' => $admissionId, 'admission_code' => $code], $request);
        return ['ok' => true, 'id' => $admissionId, 'code' => $code];
    }

    public static function transfer(int $admissionId, int $toBedId, ?string $reason, Request $request): array
    {
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $adm = Database::queryOne('SELECT * FROM admissions WHERE id = ? AND status = "admitted" FOR UPDATE', [$admissionId]);
            if ($adm === null) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Active admission not found.']; }
            $fromBedId = $adm['bed_id'] !== null ? (int) $adm['bed_id'] : null;
            $newBed = Database::queryOne('SELECT * FROM beds WHERE id = ? FOR UPDATE', [$toBedId]);
            if ($newBed === null) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Target bed not found.']; }
            if ($newBed['status'] !== 'available') { $pdo->rollBack(); return ['ok' => false, 'error' => 'Target bed is not available.']; }
            if ($fromBedId !== null && $fromBedId === $toBedId) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Patient is already in this bed.']; }
            // Free old bed.
            if ($fromBedId !== null) {
                Database::execute("UPDATE beds SET status = 'cleaning', current_admission_id = NULL WHERE id = ?", [$fromBedId]);
            }
            // Occupy new bed.
            Database::execute("UPDATE beds SET status = 'occupied', current_admission_id = ? WHERE id = ?", [$admissionId, $toBedId]);
            // Update admission's bed/room/ward.
            $newRoomId = (int) Database::scalar('SELECT room_id FROM beds WHERE id = ?', [$toBedId]);
            $newWardId = (int) Database::scalar('SELECT ward_id FROM rooms WHERE id = ?', [$newRoomId]);
            Database::execute('UPDATE admissions SET bed_id = ?, room_id = ?, ward_id = ? WHERE id = ?', [$toBedId, $newRoomId, $newWardId ?: null, $admissionId]);
            // Create transfer record.
            Database::execute(
                'INSERT INTO bed_transfers (admission_id, from_bed_id, to_bed_id, transfer_date, reason, transferred_by) VALUES (?, ?, ?, NOW(), ?, ?)',
                [$admissionId, $fromBedId, $toBedId, $reason, Auth::id()]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Bed transfer failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not transfer patient.'];
        }
        AuditService::log('admission.transferred', 'admissions', 'update', "Transferred admission #{$admissionId} to bed #{$toBedId}." . ($reason ? " Reason: {$reason}" : ''), ['admission_id' => $admissionId], $request);
        return ['ok' => true];
    }

    public static function discharge(int $admissionId, array $data, Request $request): array
    {
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $adm = Database::queryOne('SELECT * FROM admissions WHERE id = ? FOR UPDATE', [$admissionId]);
            if ($adm === null) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Admission not found.']; }
            if ($adm['status'] !== 'admitted') { $pdo->rollBack(); return ['ok' => false, 'error' => 'Patient is not currently admitted.']; }
            // Free the bed.
            if ($adm['bed_id'] !== null) {
                Database::execute("UPDATE beds SET status = 'cleaning', current_admission_id = NULL WHERE id = ?", [$adm['bed_id']]);
            }
            Database::execute(
                'UPDATE admissions SET status = "discharged", actual_discharge_date = NOW(), discharge_summary = ?, discharge_diagnosis = ?, discharge_instructions = ?, discharged_by = ? WHERE id = ?',
                [$data['discharge_summary'] ?? null, $data['discharge_diagnosis'] ?? null, $data['discharge_instructions'] ?? null, Auth::id(), $admissionId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::error('Discharge failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not discharge patient.'];
        }
        AuditService::log('admission.discharged', 'admissions', 'update', "Discharged admission #{$admissionId}.", ['admission_id' => $admissionId], $request);
        return ['ok' => true];
    }

    public static function setBedStatus(int $bedId, string $status, Request $request): array
    {
        if (!in_array($status, Bed::STATUSES, true)) return ['ok' => false, 'error' => 'Invalid status.'];
        $bed = Bed::find($bedId);
        if ($bed === null) return ['ok' => false, 'error' => 'Bed not found.'];
        if ($status === 'occupied') return ['ok' => false, 'error' => 'Cannot manually set occupied — admit a patient instead.'];
        Database::execute('UPDATE beds SET status = ? WHERE id = ? AND status != "occupied"', [$status, $bedId]);
        AuditService::log('bed.status_changed', 'beds', 'update', "Bed #{$bedId} → {$status}.", ['bed_id' => $bedId, 'status' => $status], $request);
        return ['ok' => true];
    }
}
