<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Patient;

/**
 * Patient administration — persistence, archiving and the secure
 * document pipeline. All mutations are audited.
 */
final class PatientService
{
    /** Documents live outside the docroot; downloads are permission-gated. */
    private const UPLOAD_DIR = BASE_PATH . '/storage/uploads/patients';

    private const MAX_UPLOAD_BYTES = 5242880; // 5 MB

    /** Extension => allowed MIME types (both must match finfo). */
    private const ALLOWED_TYPES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public const DOCUMENT_TYPES = ['report', 'prescription', 'scan', 'identification', 'consent', 'other'];

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------
    /** @return array{ok: bool, error?: string, id?: int, code?: string} */
    public static function create(array $data, Request $request): array
    {
        $code = Patient::nextCode();

        Database::execute(
            'INSERT INTO patients (patient_code, first_name, last_name, gender, date_of_birth, blood_group,
                marital_status, national_id, phone, email, address, city, postal_code, country,
                emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                medical_history, allergies, notes, registered_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code,
                $data['first_name'], $data['last_name'], $data['gender'], $data['date_of_birth'],
                $data['blood_group'] ?? null, $data['marital_status'] ?? null, $data['national_id'] ?? null,
                $data['phone'], $data['email'] ?? null, $data['address'] ?? null, $data['city'] ?? null,
                $data['postal_code'] ?? null, $data['country'] ?? null,
                $data['emergency_contact_name'] ?? null, $data['emergency_contact_phone'] ?? null,
                $data['emergency_contact_relation'] ?? null,
                $data['medical_history'] ?? null, $data['allergies'] ?? null, $data['notes'] ?? null,
                Auth::id(),
            ]
        );

        $id = Database::lastInsertId();
        AuditService::log('patient.created', 'patients', 'create', "Registered patient {$code} ({$data['first_name']} {$data['last_name']}).", [
            'patient_id' => $id, 'patient_code' => $code,
        ], $request);

        return ['ok' => true, 'id' => $id, 'code' => $code];
    }

    /** @return array{ok: bool, error?: string} */
    public static function update(int $id, array $data, Request $request): array
    {
        $patient = Patient::find($id);
        if ($patient === null) {
            return ['ok' => false, 'error' => 'Patient not found.'];
        }

        // Track meaningful changes for the audit context.
        $changed = [];
        foreach (['first_name', 'last_name', 'phone', 'email', 'city', 'blood_group', 'allergies', 'medical_history'] as $field) {
            if ((string) ($patient[$field] ?? '') !== (string) ($data[$field] ?? '')) {
                $changed[] = $field;
            }
        }

        Database::execute(
            'UPDATE patients SET first_name = ?, last_name = ?, gender = ?, date_of_birth = ?, blood_group = ?,
                marital_status = ?, national_id = ?, phone = ?, email = ?, address = ?, city = ?, postal_code = ?, country = ?,
                emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relation = ?,
                medical_history = ?, allergies = ?, notes = ?
             WHERE id = ?',
            [
                $data['first_name'], $data['last_name'], $data['gender'], $data['date_of_birth'],
                $data['blood_group'] ?? null, $data['marital_status'] ?? null, $data['national_id'] ?? null,
                $data['phone'], $data['email'] ?? null, $data['address'] ?? null, $data['city'] ?? null,
                $data['postal_code'] ?? null, $data['country'] ?? null,
                $data['emergency_contact_name'] ?? null, $data['emergency_contact_phone'] ?? null,
                $data['emergency_contact_relation'] ?? null,
                $data['medical_history'] ?? null, $data['allergies'] ?? null, $data['notes'] ?? null,
                $id,
            ]
        );

        AuditService::log('patient.updated', 'patients', 'update', "Updated patient {$patient['patient_code']} ({$data['first_name']} {$data['last_name']}).", [
            'patient_id' => $id, 'patient_code' => $patient['patient_code'], 'changed' => $changed,
        ], $request);

        return ['ok' => true];
    }

    /**
     * Archive / restore. Archiving is soft: visits, documents and audit
     * history remain intact and the profile stays viewable (read-only).
     */
    public static function archive(int $id, Request $request): array
    {
        $patient = Patient::find($id);
        if ($patient === null) {
            return ['ok' => false, 'error' => 'Patient not found.'];
        }
        Database::execute('UPDATE patients SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL', [$id]);
        AuditService::log('patient.archived', 'patients', 'archive', "Archived patient {$patient['patient_code']}. History preserved.", [
            'patient_id' => $id, 'patient_code' => $patient['patient_code'],
        ], $request);
        return ['ok' => true];
    }

    public static function restore(int $id, Request $request): array
    {
        $patient = Patient::find($id);
        if ($patient === null) {
            return ['ok' => false, 'error' => 'Patient not found.'];
        }
        Database::execute('UPDATE patients SET archived_at = NULL WHERE id = ?', [$id]);
        AuditService::log('patient.restored', 'patients', 'update', "Restored patient {$patient['patient_code']} from archive.", [
            'patient_id' => $id, 'patient_code' => $patient['patient_code'],
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Visits
    // ------------------------------------------------------------------
    /** @return array{ok: bool, error?: string} */
    public static function addVisit(int $patientId, array $data, Request $request): array
    {
        $patient = Patient::find($patientId);
        if ($patient === null) {
            return ['ok' => false, 'error' => 'Patient not found.'];
        }

        $doctorId = !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null;
        if ($doctorId !== null) {
            $exists = Database::scalar(
                "SELECT COUNT(*) FROM users u
                 INNER JOIN role_user ru ON ru.user_id = u.id
                 INNER JOIN roles r ON r.id = ru.role_id
                 WHERE u.id = ? AND r.slug IN ('doctor','administrator','super-admin') AND u.is_active = 1",
                [$doctorId]
            );
            if ((int) $exists === 0) {
                $doctorId = null; // silently drop invalid doctor references
            }
        }

        Database::execute(
            'INSERT INTO patient_visits (patient_id, visited_at, visit_type, doctor_id, chief_complaint, diagnosis, notes, status, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $patientId,
                $data['visited_at'] ?? date('Y-m-d H:i:s'),
                $data['visit_type'] ?? 'outpatient',
                $doctorId,
                $data['chief_complaint'],
                $data['diagnosis'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? 'completed',
                Auth::id(),
            ]
        );

        AuditService::log('patient.visit_recorded', 'patients', 'create', "Recorded {$data['visit_type']} visit for {$patient['patient_code']}: {$data['chief_complaint']}.", [
            'patient_id' => $patientId, 'patient_code' => $patient['patient_code'],
        ], $request);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Documents (secure pipeline)
    // ------------------------------------------------------------------
    /**
     * Validate + store an uploaded file. Defence layers:
     * size cap, extension whitelist, finfo MIME sniffing (never trust
     * the client-supplied type), random stored name, private directory.
     *
     * @param array<string, mixed> $file $_FILES entry
     * @return array{ok: bool, error?: string}
     */
    public static function storeDocument(int $patientId, array $file, string $documentType, string $title, Request $request): array
    {
        $patient = Patient::find($patientId);
        if ($patient === null) {
            return ['ok' => false, 'error' => 'Patient not found.'];
        }

        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Invalid upload parameters.'];
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Choose a file to upload.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed (php error ' . $file['error'] . ').'];
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'error' => 'File must be between 1 byte and 5 MB.'];
        }

        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            $documentType = 'other';
        }

        $originalName = (string) $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$extension])) {
            return ['ok' => false, 'error' => 'Allowed file types: PDF, JPG, PNG, WebP.'];
        }

        // MIME sniffing — the extension and the real content must agree.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_TYPES[$extension], true)) {
            return ['ok' => false, 'error' => "File content ({$mime}) does not match its .{$extension} extension. Upload rejected."];
        }

        if (!is_dir(self::UPLOAD_DIR)) {
            @mkdir(self::UPLOAD_DIR, 0775, true);
        }
        if (!is_writable(self::UPLOAD_DIR)) {
            Logger::error('Patient upload dir not writable: ' . self::UPLOAD_DIR);
            return ['ok' => false, 'error' => 'Storage is not writable. Contact an administrator.'];
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . $extension;
        $destination = self::UPLOAD_DIR . '/' . $storedName;
        // Real uploads go through move_uploaded_file (which verifies the
        // file came from this request); the copy fallback only serves
        // CLI/test contexts where is_uploaded_file() is false.
        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $destination)
            : copy($file['tmp_name'], $destination);
        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not store the file. Try again.'];
        }

        $title = trim($title) !== '' ? mb_substr(trim($title), 0, 150) : $originalName;

        Database::execute(
            'INSERT INTO patient_documents (patient_id, document_type, title, original_name, stored_name, mime_type, size_bytes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$patientId, $documentType, $title, mb_substr($originalName, 0, 255), $storedName, $mime, (int) $file['size'], Auth::id()]
        );

        AuditService::log('patient.document_uploaded', 'patients', 'create', "Uploaded document \"{$title}\" for {$patient['patient_code']}.", [
            'patient_id' => $patientId, 'patient_code' => $patient['patient_code'], 'type' => $documentType, 'bytes' => (int) $file['size'],
        ], $request);

        return ['ok' => true];
    }

    /**
     * Stream a stored document to the client. The path is built from the
     * DB row's server-generated name — user input never touches the path.
     *
     * @return array{file: string, mime: string, name: string, size: int}|array{ok: false, error: string}
     */
    public static function documentDownloadPath(int $documentId): array
    {
        $doc = Patient::findDocument($documentId);
        if ($doc === null) {
            return ['ok' => false, 'error' => 'Document not found.'];
        }

        $path = self::UPLOAD_DIR . '/' . basename((string) $doc['stored_name']);
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'Stored file is missing.'];
        }

        return [
            'file' => $path,
            'mime' => (string) $doc['mime_type'],
            'name' => (string) $doc['original_name'],
            'size' => (int) $doc['size_bytes'],
        ];
    }

    /** @return array{ok: bool, error?: string} */
    public static function deleteDocument(int $documentId, Request $request): array
    {
        $doc = Patient::findDocument($documentId);
        if ($doc === null) {
            return ['ok' => false, 'error' => 'Document not found.'];
        }

        Database::execute('DELETE FROM patient_documents WHERE id = ?', [$documentId]);
        $path = self::UPLOAD_DIR . '/' . basename((string) $doc['stored_name']);
        if (is_file($path)) {
            @unlink($path);
        }

        AuditService::log('patient.document_deleted', 'patients', 'delete', "Deleted document \"{$doc['title']}\" of patient #{$doc['patient_id']}.", [
            'document_id' => $documentId,
        ], $request);

        return ['ok' => true];
    }
}
