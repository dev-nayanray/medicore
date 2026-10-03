<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Doctor;
use App\Models\DoctorSchedule;

/**
 * Doctor administration — profile CRUD, weekly schedule management,
 * leave approval, and overlap validation. All mutations are audit-logged.
 */
final class DoctorService
{
    private const UPLOAD_DIR = BASE_PATH . '/storage/uploads/doctors';
    private const MAX_IMAGE_BYTES = 2097152; // 2 MB
    private const IMAGE_TYPES = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];

    /** @return array{ok: bool, error?: string, id?: int, code?: string} */
    public static function create(array $data, Request $request): array
    {
        // The user account must exist and have a doctor role.
        $userId = (int) $data['user_id'];
        $exists = Database::scalar(
            "SELECT COUNT(*) FROM users u
             INNER JOIN role_user ru ON ru.user_id = u.id
             INNER JOIN roles r ON r.id = ru.role_id
             WHERE u.id = ? AND r.slug IN ('doctor','administrator','super-admin')",
            [$userId]
        );
        if ((int) $exists === 0) {
            return ['ok' => false, 'error' => 'Selected user does not have a doctor role.'];
        }

        // One profile per user.
        if ((int) Database::scalar('SELECT COUNT(*) FROM doctors WHERE user_id = ?', [$userId]) > 0) {
            return ['ok' => false, 'error' => 'This user already has a doctor profile.'];
        }

        $code = Doctor::nextCode();
        Database::execute(
            'INSERT INTO doctors (user_id, doctor_code, specialization, qualifications, registration_number,
                bio, consultation_fee, department_id, room_number, status, hired_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId, $code,
                $data['specialization'],
                $data['qualifications'] ?? null,
                $data['registration_number'] ?? null,
                $data['bio'] ?? null,
                $data['consultation_fee'] !== '' && $data['consultation_fee'] !== null ? $data['consultation_fee'] : null,
                !empty($data['department_id']) ? (int) $data['department_id'] : null,
                $data['room_number'] ?? null,
                $data['status'] ?? 'active',
                $data['hired_at'] ?? null,
            ]
        );
        $id = Database::lastInsertId();

        // Optional profile image.
        if (!empty($_FILES['profile_image']['name'])) {
            $img = self::storeImage($_FILES['profile_image']);
            if (isset($img['stored'])) {
                Database::execute('UPDATE doctors SET profile_image = ? WHERE id = ?', [$img['stored'], $id]);
            } elseif (isset($img['error'])) {
                // Non-fatal — profile is created, image rejected.
                Logger::error('Doctor image rejected: ' . $img['error']);
            }
        }

        AuditService::log('doctor.created', 'doctors', 'create', "Created doctor profile {$code}.", [
            'doctor_id' => $id, 'doctor_code' => $code, 'user_id' => $userId,
        ], $request);

        return ['ok' => true, 'id' => $id, 'code' => $code];
    }

    /** @return array{ok: bool, error?: string} */
    public static function update(int $id, array $data, Request $request): array
    {
        $doctor = Doctor::find($id);
        if ($doctor === null) {
            return ['ok' => false, 'error' => 'Doctor profile not found.'];
        }

        Database::execute(
            'UPDATE doctors SET specialization = ?, qualifications = ?, registration_number = ?,
                bio = ?, consultation_fee = ?, department_id = ?, room_number = ?, status = ?, hired_at = ?
             WHERE id = ?',
            [
                $data['specialization'],
                $data['qualifications'] ?? null,
                $data['registration_number'] ?? null,
                $data['bio'] ?? null,
                $data['consultation_fee'] !== '' && $data['consultation_fee'] !== null ? $data['consultation_fee'] : null,
                !empty($data['department_id']) ? (int) $data['department_id'] : null,
                $data['room_number'] ?? null,
                $data['status'] ?? 'active',
                $data['hired_at'] ?? null,
                $id,
            ]
        );

        if (!empty($_FILES['profile_image']['name'])) {
            $img = self::storeImage($_FILES['profile_image']);
            if (isset($img['stored'])) {
                // Remove the old image file if present.
                if (!empty($doctor['profile_image'])) {
                    @unlink(self::UPLOAD_DIR . '/' . basename((string) $doctor['profile_image']));
                }
                Database::execute('UPDATE doctors SET profile_image = ? WHERE id = ?', [$img['stored'], $id]);
            }
        }

        AuditService::log('doctor.updated', 'doctors', 'update', "Updated doctor profile {$doctor['doctor_code']}.", [
            'doctor_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    public static function archive(int $id, Request $request): array
    {
        $doctor = Doctor::find($id);
        if ($doctor === null) {
            return ['ok' => false, 'error' => 'Doctor profile not found.'];
        }
        Database::execute("UPDATE doctors SET archived_at = NOW(), status = 'inactive' WHERE id = ? AND archived_at IS NULL", [$id]);
        AuditService::log('doctor.archived', 'doctors', 'archive', "Archived doctor {$doctor['doctor_code']}.", [
            'doctor_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    public static function restore(int $id, Request $request): array
    {
        $doctor = Doctor::find($id);
        if ($doctor === null) {
            return ['ok' => false, 'error' => 'Doctor profile not found.'];
        }
        Database::execute('UPDATE doctors SET archived_at = NULL, status = ? WHERE id = ?', ['active', $id]);
        AuditService::log('doctor.restored', 'doctors', 'update', "Restored doctor {$doctor['doctor_code']}.", [
            'doctor_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Weekly schedule
    // ------------------------------------------------------------------
    /** @return array{ok: bool, error?: string} */
    public static function saveScheduleSlot(int $doctorId, array $data, Request $request): array
    {
        $doctor = Doctor::find($doctorId);
        if ($doctor === null) {
            return ['ok' => false, 'error' => 'Doctor not found.'];
        }

        $day = (int) ($data['day_of_week']);
        $start = (string) $data['start_time'];
        $end = (string) $data['end_time'];
        if ($day < 0 || $day > 6) {
            return ['ok' => false, 'error' => 'Invalid day of week.'];
        }
        if ($start >= $end) {
            return ['ok' => false, 'error' => 'Start time must be earlier than end time.'];
        }

        $slotId = !empty($data['slot_id']) ? (int) $data['slot_id'] : null;
        if (DoctorSchedule::overlaps($doctorId, $day, $start, $end, $slotId)) {
            return ['ok' => false, 'error' => 'This time range overlaps an existing slot for the same day.'];
        }

        if ($slotId !== null) {
            Database::execute(
                'UPDATE doctor_schedules SET day_of_week = ?, start_time = ?, end_time = ?, max_patients = ?, room = ?, is_active = ?
                 WHERE id = ? AND doctor_id = ?',
                [$day, $start, $end, (int) ($data['max_patients'] ?? 20), $data['room'] ?? null, !empty($data['is_active']) ? 1 : 0, $slotId, $doctorId]
            );
        } else {
            Database::execute(
                'INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_patients, room, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time),
                     max_patients = VALUES(max_patients), room = VALUES(room), is_active = VALUES(is_active)',
                [$doctorId, $day, $start, $end, (int) ($data['max_patients'] ?? 20), $data['room'] ?? null, !empty($data['is_active']) ? 1 : 0]
            );
        }

        AuditService::log('doctor.schedule_saved', 'doctors', 'update', "Updated weekly schedule for {$doctor['doctor_code']}.", [
            'doctor_id' => $doctorId, 'day' => $day,
        ], $request);
        return ['ok' => true];
    }

    public static function deleteScheduleSlot(int $doctorId, int $slotId, Request $request): array
    {
        Database::execute('DELETE FROM doctor_schedules WHERE id = ? AND doctor_id = ?', [$slotId, $doctorId]);
        AuditService::log('doctor.schedule_deleted', 'doctors', 'delete', "Deleted schedule slot #{$slotId}.", [
            'doctor_id' => $doctorId, 'slot_id' => $slotId,
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Leave management
    // ------------------------------------------------------------------
    /** @return array{ok: bool, error?: string} */
    public static function createLeave(int $doctorId, array $data, Request $request): array
    {
        $doctor = Doctor::find($doctorId);
        if ($doctor === null) {
            return ['ok' => false, 'error' => 'Doctor not found.'];
        }
        if ($data['start_date'] > $data['end_date']) {
            return ['ok' => false, 'error' => 'Start date must be on or before end date.'];
        }
        Database::execute(
            'INSERT INTO doctor_leaves (doctor_id, start_date, end_date, reason, status)
             VALUES (?, ?, ?, ?, ?)',
            [$doctorId, $data['start_date'], $data['end_date'], $data['reason'] ?? null, $data['status'] ?? 'pending']
        );
        $leaveId = Database::lastInsertId();

        // Auto-mark doctor on_leave when leave is approved.
        if (($data['status'] ?? 'pending') === 'approved') {
            Database::execute("UPDATE doctors SET status = 'on_leave' WHERE id = ?", [$doctorId]);
        }

        AuditService::log('doctor.leave_created', 'doctors', 'create', "Recorded leave for {$doctor['doctor_code']}.", [
            'doctor_id' => $doctorId, 'leave_id' => $leaveId,
        ], $request);
        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function decideLeave(int $leaveId, string $decision, Request $request): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Invalid decision.'];
        }
        $leave = Database::queryOne('SELECT * FROM doctor_leaves WHERE id = ?', [$leaveId]);
        if ($leave === null) {
            return ['ok' => false, 'error' => 'Leave record not found.'];
        }
        Database::execute(
            'UPDATE doctor_leaves SET status = ?, approved_by = ? WHERE id = ?',
            [$decision, Auth::id(), $leaveId]
        );
        if ($decision === 'approved') {
            Database::execute("UPDATE doctors SET status = 'on_leave' WHERE id = ?", [$leave['doctor_id']]);
        }
        AuditService::log('doctor.leave_' . $decision, 'doctors', 'update', "Leave #{$leaveId} {$decision}.", [
            'leave_id' => $leaveId, 'doctor_id' => $leave['doctor_id'],
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Image upload helper
    // ------------------------------------------------------------------
    /** @return array{stored?: string, error?: string} */
    private static function storeImage(array $file): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Upload failed.'];
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_IMAGE_BYTES) {
            return ['error' => 'Image must be between 1 byte and 2 MB.'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::IMAGE_TYPES[$ext])) {
            return ['error' => 'Allowed image types: JPG, PNG, WebP.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::IMAGE_TYPES[$ext], true)) {
            return ['error' => 'Image content does not match its extension.'];
        }
        if (!is_dir(self::UPLOAD_DIR)) {
            @mkdir(self::UPLOAD_DIR, 0775, true);
        }
        $stored = bin2hex(random_bytes(20)) . '.' . $ext;
        $dest = self::UPLOAD_DIR . '/' . $stored;
        $moved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $dest) : copy($file['tmp_name'], $dest);
        if (!$moved) {
            return ['error' => 'Could not store the image.'];
        }
        return ['stored' => $stored];
    }

    /** @return array{file: string, mime: string, name: string}|array{ok: false, error: string} */
    public static function imagePath(string $storedName): array
    {
        $path = self::UPLOAD_DIR . '/' . basename($storedName);
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'Image not found.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return ['file' => $path, 'mime' => (string) $finfo->file($path), 'name' => $storedName];
    }
}
