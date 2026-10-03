<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Consultation;
use App\Models\ConsultationAmendment;

/**
 * Consultation administration — create from appointment or walk-in,
 * draft → finalize lifecycle, amendment tracking for finalized records,
 * and secure attachment handling. Only doctors (or admins) can create
 * or modify clinical records.
 */
final class ConsultationService
{
    private const UPLOAD_DIR = BASE_PATH . '/storage/uploads/consultations';
    private const MAX_UPLOAD_BYTES = 10485760; // 10 MB
    private const ALLOWED_TYPES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    /**
     * Create a new consultation (draft). Links to an appointment if
     * provided; walk-ins pass appointment_id = null.
     *
     * @return array{ok: bool, error?: string, id?: int, code?: string}
     */
    public static function create(array $data, Request $request): array
    {
        $patientId = (int) $data['patient_id'];
        $doctorId = (int) $data['doctor_id'];
        $appointmentId = !empty($data['appointment_id']) ? (int) $data['appointment_id'] : null;

        // Validate patient + doctor exist.
        if (Database::scalar('SELECT COUNT(*) FROM patients WHERE id = ? AND archived_at IS NULL', [$patientId]) == 0) {
            return ['ok' => false, 'error' => 'Patient not found or archived.'];
        }
        if (Database::scalar('SELECT COUNT(*) FROM doctors WHERE id = ? AND archived_at IS NULL', [$doctorId]) == 0) {
            return ['ok' => false, 'error' => 'Doctor not found or archived.'];
        }

        // If appointment_id provided, validate it belongs to this patient+doctor.
        if ($appointmentId !== null) {
            $apt = Database::queryOne('SELECT patient_id, doctor_id, status FROM appointments WHERE id = ?', [$appointmentId]);
            if ($apt === null) {
                return ['ok' => false, 'error' => 'Appointment not found.'];
            }
            if ((int) $apt['patient_id'] !== $patientId || (int) $apt['doctor_id'] !== $doctorId) {
                return ['ok' => false, 'error' => 'Appointment does not belong to the selected patient/doctor.'];
            }
            // Also check no existing consultation for this appointment.
            $existing = Database::scalar('SELECT COUNT(*) FROM consultations WHERE appointment_id = ?', [$appointmentId]);
            if ((int) $existing > 0) {
                return ['ok' => false, 'error' => 'A consultation already exists for this appointment.'];
            }
        }

        $code = Consultation::nextCode();
        $departmentId = !empty($data['department_id']) ? (int) $data['department_id'] : null;
        if ($departmentId === null) {
            $departmentId = (int) (Database::scalar('SELECT department_id FROM doctors WHERE id = ?', [$doctorId]) ?: 0) ?: null;
        }

        $vitals = self::normalizeVitals($data);
        $consultationDate = $data['consultation_date'] ?? date('Y-m-d H:i:s');

        Database::execute(
            'INSERT INTO consultations
                (consultation_code, patient_id, doctor_id, appointment_id, department_id, consultation_date,
                 status, chief_complaint, history_presenting, symptoms, observations,
                 temperature, bp_systolic, bp_diastolic, pulse, respiratory_rate, spo2, weight, height, bmi,
                 clinical_notes, diagnoses, follow_up_date, referral_to, referral_reason, created_by)
             VALUES (?, ?, ?, ?, ?, ?, "draft", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code, $patientId, $doctorId, $appointmentId, $departmentId, $consultationDate,
                $data['chief_complaint'] ?? null, $data['history_presenting'] ?? null,
                $data['symptoms'] ?? null, $data['observations'] ?? null,
                $vitals['temperature'], $vitals['bp_systolic'], $vitals['bp_diastolic'],
                $vitals['pulse'], $vitals['respiratory_rate'], $vitals['spo2'],
                $vitals['weight'], $vitals['height'], $vitals['bmi'],
                $data['clinical_notes'] ?? null, $data['diagnoses'] ?? null,
                !empty($data['follow_up_date']) ? $data['follow_up_date'] : null,
                $data['referral_to'] ?? null, $data['referral_reason'] ?? null,
                Auth::id(),
            ]
        );

        $id = Database::lastInsertId();

        AuditService::log('consultation.created', 'consultations', 'create', "Created consultation {$code} (draft).", [
            'consultation_id' => $id, 'consultation_code' => $code, 'patient_id' => $patientId, 'doctor_id' => $doctorId,
        ], $request);

        return ['ok' => true, 'id' => (int) $id, 'code' => $code];
    }

    /**
     * Update a draft consultation. Finalized consultations cannot be
     * updated — use amend() instead.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function update(int $id, array $data, Request $request): array
    {
        $con = Consultation::find($id);
        if ($con === null) {
            return ['ok' => false, 'error' => 'Consultation not found.'];
        }
        if ($con['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Cannot edit a finalized consultation. Use the amend action for corrections.'];
        }

        $vitals = self::normalizeVitals($data);
        $bmi = $vitals['bmi'];
        if ($bmi === null && $vitals['weight'] !== null && $vitals['height'] !== null && (float) $vitals['height'] > 0) {
            $h = (float) $vitals['height'] / 100;
            $bmi = round((float) $vitals['weight'] / ($h * $h), 1);
        }

        Database::execute(
            'UPDATE consultations SET
                chief_complaint = ?, history_presenting = ?, symptoms = ?, observations = ?,
                temperature = ?, bp_systolic = ?, bp_diastolic = ?, pulse = ?, respiratory_rate = ?,
                spo2 = ?, weight = ?, height = ?, bmi = ?,
                clinical_notes = ?, diagnoses = ?, follow_up_date = ?, referral_to = ?, referral_reason = ?
             WHERE id = ?',
            [
                $data['chief_complaint'] ?? null, $data['history_presenting'] ?? null,
                $data['symptoms'] ?? null, $data['observations'] ?? null,
                $vitals['temperature'], $vitals['bp_systolic'], $vitals['bp_diastolic'],
                $vitals['pulse'], $vitals['respiratory_rate'], $vitals['spo2'],
                $vitals['weight'], $vitals['height'], $bmi,
                $data['clinical_notes'] ?? null, $data['diagnoses'] ?? null,
                !empty($data['follow_up_date']) ? $data['follow_up_date'] : null,
                $data['referral_to'] ?? null, $data['referral_reason'] ?? null,
                $id,
            ]
        );

        AuditService::log('consultation.updated', 'consultations', 'update', "Updated draft consultation {$con['consultation_code']}.", [
            'consultation_id' => $id,
        ], $request);

        return ['ok' => true];
    }

    /**
     * Finalize a consultation — locks the record. Finalized records
     * can only be modified via amend().
     *
     * @return array{ok: bool, error?: string}
     */
    public static function finalize(int $id, Request $request): array
    {
        $con = Consultation::find($id);
        if ($con === null) {
            return ['ok' => false, 'error' => 'Consultation not found.'];
        }
        if ($con['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only draft consultations can be finalized.'];
        }

        Database::execute(
            "UPDATE consultations SET status = 'finalized', finalized_at = NOW(), finalized_by = ? WHERE id = ?",
            [Auth::id(), $id]
        );

        // If linked to an appointment, mark it completed.
        if ($con['appointment_id'] !== null) {
            Database::execute(
                "UPDATE appointments SET status = 'completed', completed_at = NOW() WHERE id = ? AND status IN ('checked_in','in_consultation','confirmed')",
                [$con['appointment_id']]
            );
        }

        AuditService::log('consultation.finalized', 'consultations', 'finalize', "Finalized consultation {$con['consultation_code']}.", [
            'consultation_id' => $id,
        ], $request);

        return ['ok' => true];
    }

    /**
     * Amend a finalized consultation — records the old/new value + reason
     * in the amendment trail, then applies the change. The consultation
     * status becomes 'amended'.
     *
     * @param array{field: string, value: string, reason: string} $data
     * @return array{ok: bool, error?: string}
     */
    public static function amend(int $id, array $data, Request $request): array
    {
        $con = Consultation::find($id);
        if ($con === null) {
            return ['ok' => false, 'error' => 'Consultation not found.'];
        }
        if ($con['status'] !== 'finalized' && $con['status'] !== 'amended') {
            return ['ok' => false, 'error' => 'Only finalized consultations can be amended.'];
        }

        $field = (string) $data['field'];
        $newValue = (string) $data['value'];
        $reason = trim((string) $data['reason']);

        if ($reason === '') {
            return ['ok' => false, 'error' => 'A reason is required for clinical record amendments.'];
        }

        // Whitelist of amendable fields.
        $allowedFields = [
            'chief_complaint', 'history_presenting', 'symptoms', 'observations',
            'clinical_notes', 'diagnoses', 'follow_up_date', 'referral_to', 'referral_reason',
            'temperature', 'bp_systolic', 'bp_diastolic', 'pulse', 'respiratory_rate',
            'spo2', 'weight', 'height',
        ];
        if (!in_array($field, $allowedFields, true)) {
            return ['ok' => false, 'error' => 'Field is not amendable.'];
        }

        $oldValue = (string) ($con[$field] ?? '');

        // Record the amendment BEFORE applying the change.
        Database::execute(
            'INSERT INTO consultation_amendments (consultation_id, field_name, old_value, new_value, reason, amended_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $field, $oldValue !== '' ? $oldValue : null, $newValue !== '' ? $newValue : null, $reason, Auth::id()]
        );

        // Apply the change. Numeric fields are cast; text fields are stored as-is.
        $isNumeric = in_array($field, ['temperature', 'bp_systolic', 'bp_diastolic', 'pulse', 'respiratory_rate', 'spo2', 'weight', 'height'], true);
        $value = $isNumeric ? ($newValue !== '' ? $newValue : null) : ($newValue !== '' ? $newValue : null);

        Database::execute(
            "UPDATE consultations SET {$field} = ?, status = 'amended' WHERE id = ?",
            [$value, $id]
        );

        AuditService::log('consultation.amended', 'consultations', 'amend', "Amended {$field} on {$con['consultation_code']}: {$reason}", [
            'consultation_id' => $id, 'field' => $field, 'reason' => $reason,
        ], $request);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Attachments
    // ------------------------------------------------------------------
    /**
     * Validate + store a clinical attachment. Same secure pipeline as
     * patient documents: extension whitelist + finfo MIME sniffing +
     * random stored name + private directory.
     *
     * @param array<string, mixed> $file $_FILES entry
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function storeAttachment(int $consultationId, array $file, string $title, Request $request): array
    {
        $con = Consultation::find($consultationId);
        if ($con === null) {
            return ['ok' => false, 'error' => 'Consultation not found.'];
        }
        if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed.'];
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'error' => 'File must be between 1 byte and 10 MB.'];
        }

        $originalName = (string) $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$ext])) {
            return ['ok' => false, 'error' => 'Allowed types: PDF, JPG, PNG, WebP.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_TYPES[$ext], true)) {
            return ['ok' => false, 'error' => "File content ({$mime}) does not match .{$ext}. Upload rejected."];
        }

        if (!is_dir(self::UPLOAD_DIR)) {
            @mkdir(self::UPLOAD_DIR, 0775, true);
        }
        if (!is_writable(self::UPLOAD_DIR)) {
            Logger::error('Consultation upload dir not writable: ' . self::UPLOAD_DIR);
            return ['ok' => false, 'error' => 'Storage is not writable.'];
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . $ext;
        $destination = self::UPLOAD_DIR . '/' . $storedName;
        $moved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $destination) : copy($file['tmp_name'], $destination);
        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not store the file.'];
        }

        $title = trim($title) !== '' ? mb_substr(trim($title), 0, 150) : $originalName;

        Database::execute(
            'INSERT INTO consultation_attachments (consultation_id, title, original_name, stored_name, mime_type, size_bytes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$consultationId, $title, mb_substr($originalName, 0, 255), $storedName, $mime, (int) $file['size'], Auth::id()]
        );

        $id = Database::lastInsertId();
        AuditService::log('consultation.attachment_uploaded', 'consultations', 'create', "Uploaded attachment \"{$title}\" for consultation #{$consultationId}.", [
            'consultation_id' => $consultationId, 'attachment_id' => $id, 'bytes' => (int) $file['size'],
        ], $request);

        return ['ok' => true, 'id' => (int) $id];
    }

    /** @return array{file: string, mime: string, name: string}|array{ok: false, error: string} */
    public static function attachmentDownloadPath(int $attachmentId): array
    {
        $att = \App\Models\ConsultationAttachment::find($attachmentId);
        if ($att === null) {
            return ['ok' => false, 'error' => 'Attachment not found.'];
        }
        $path = self::UPLOAD_DIR . '/' . basename((string) $att['stored_name']);
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'Stored file is missing.'];
        }
        return ['file' => $path, 'mime' => (string) $att['mime_type'], 'name' => (string) $att['original_name']];
    }

    public static function deleteAttachment(int $attachmentId, Request $request): array
    {
        $att = \App\Models\ConsultationAttachment::find($attachmentId);
        if ($att === null) {
            return ['ok' => false, 'error' => 'Attachment not found.'];
        }
        Database::execute('DELETE FROM consultation_attachments WHERE id = ?', [$attachmentId]);
        $path = self::UPLOAD_DIR . '/' . basename((string) $att['stored_name']);
        if (is_file($path)) {
            @unlink($path);
        }
        AuditService::log('consultation.attachment_deleted', 'consultations', 'delete', "Deleted attachment \"{$att['title']}\".", [
            'attachment_id' => $attachmentId,
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    /** @return array<string, mixed> normalized vitals (nulls for empty) */
    private static function normalizeVitals(array $data): array
    {
        $fields = ['temperature', 'bp_systolic', 'bp_diastolic', 'pulse', 'respiratory_rate', 'spo2', 'weight', 'height'];
        $out = [];
        foreach ($fields as $f) {
            $val = $data[$f] ?? '';
            $out[$f] = ($val === '' || $val === null) ? null : $val;
        }
        // Auto-calculate BMI if weight + height present.
        $out['bmi'] = null;
        if ($out['weight'] !== null && $out['height'] !== null && (float) $out['height'] > 0) {
            $h = (float) $out['height'] / 100;
            $out['bmi'] = round((float) $out['weight'] / ($h * $h), 1);
        }
        return $out;
    }
}
