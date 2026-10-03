<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\StaffProfile;
use App\Models\StaffShift;

/**
 * Staff administration — profile CRUD, shift scheduling with overlap
 * validation, attendance recording, and leave approval workflow.
 */
final class StaffService
{
    private const UPLOAD_DIR = BASE_PATH . '/storage/uploads/staff';
    private const MAX_IMAGE_BYTES = 2097152; // 2 MB
    private const IMAGE_TYPES = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];

    /** @return array{ok: bool, error?: string, id?: int, code?: string} */
    public static function create(array $data, Request $request): array
    {
        $userId = (int) $data['user_id'];
        $userExists = (int) Database::scalar('SELECT COUNT(*) FROM users WHERE id = ?', [$userId]);
        if ($userExists === 0) {
            return ['ok' => false, 'error' => 'Selected user does not exist.'];
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM staff_profiles WHERE user_id = ?', [$userId]) > 0) {
            return ['ok' => false, 'error' => 'This user already has a staff profile.'];
        }

        $code = StaffProfile::nextCode();
        Database::execute(
            'INSERT INTO staff_profiles (user_id, employee_id, job_title, department_id, employment_type, hire_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId, $code,
                $data['job_title'],
                !empty($data['department_id']) ? (int) $data['department_id'] : null,
                $data['employment_type'] ?? 'full_time',
                $data['hire_date'] ?? null,
                $data['status'] ?? 'active',
            ]
        );
        $id = Database::lastInsertId();

        if (!empty($_FILES['profile_image']['name'])) {
            $img = self::storeImage($_FILES['profile_image']);
            if (isset($img['stored'])) {
                Database::execute('UPDATE staff_profiles SET profile_image = ? WHERE id = ?', [$img['stored'], $id]);
            }
        }

        AuditService::log('staff.created', 'staff', 'create', "Created staff profile {$code}.", [
            'staff_id' => $id, 'employee_id' => $code, 'user_id' => $userId,
        ], $request);
        return ['ok' => true, 'id' => $id, 'code' => $code];
    }

    /** @return array{ok: bool, error?: string} */
    public static function update(int $id, array $data, Request $request): array
    {
        $staff = StaffProfile::find($id);
        if ($staff === null) {
            return ['ok' => false, 'error' => 'Staff profile not found.'];
        }

        Database::execute(
            'UPDATE staff_profiles SET job_title = ?, department_id = ?, employment_type = ?, hire_date = ?, status = ?
             WHERE id = ?',
            [
                $data['job_title'],
                !empty($data['department_id']) ? (int) $data['department_id'] : null,
                $data['employment_type'] ?? 'full_time',
                $data['hire_date'] ?? null,
                $data['status'] ?? 'active',
                $id,
            ]
        );

        if (!empty($_FILES['profile_image']['name'])) {
            $img = self::storeImage($_FILES['profile_image']);
            if (isset($img['stored'])) {
                if (!empty($staff['profile_image'])) {
                    @unlink(self::UPLOAD_DIR . '/' . basename((string) $staff['profile_image']));
                }
                Database::execute('UPDATE staff_profiles SET profile_image = ? WHERE id = ?', [$img['stored'], $id]);
            }
        }

        AuditService::log('staff.updated', 'staff', 'update', "Updated staff profile {$staff['employee_id']}.", [
            'staff_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    public static function archive(int $id, Request $request): array
    {
        $staff = StaffProfile::find($id);
        if ($staff === null) {
            return ['ok' => false, 'error' => 'Staff profile not found.'];
        }
        Database::execute("UPDATE staff_profiles SET archived_at = NOW(), status = 'terminated' WHERE id = ? AND archived_at IS NULL", [$id]);
        AuditService::log('staff.archived', 'staff', 'archive', "Archived staff {$staff['employee_id']}.", [
            'staff_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    public static function restore(int $id, Request $request): array
    {
        $staff = StaffProfile::find($id);
        if ($staff === null) {
            return ['ok' => false, 'error' => 'Staff profile not found.'];
        }
        Database::execute("UPDATE staff_profiles SET archived_at = NULL, status = 'active' WHERE id = ?", [$id]);
        AuditService::log('staff.restored', 'staff', 'update', "Restored staff {$staff['employee_id']}.", [
            'staff_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Shifts
    // ------------------------------------------------------------------
    /** @return array{ok: bool, error?: string} */
    public static function createShift(int $staffId, array $data, Request $request): array
    {
        $staff = StaffProfile::find($staffId);
        if ($staff === null) {
            return ['ok' => false, 'error' => 'Staff not found.'];
        }
        if ($data['start_time'] >= $data['end_time']) {
            return ['ok' => false, 'error' => 'Start time must be earlier than end time.'];
        }
        if (StaffShift::overlaps($staffId, (string) $data['shift_date'], (string) $data['start_time'], (string) $data['end_time'])) {
            return ['ok' => false, 'error' => 'This shift overlaps an existing shift for the same date.'];
        }

        Database::execute(
            'INSERT INTO staff_shifts (staff_id, shift_date, start_time, end_time, shift_type, department_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $staffId, $data['shift_date'], $data['start_time'], $data['end_time'],
                $data['shift_type'] ?? 'morning',
                !empty($data['department_id']) ? (int) $data['department_id'] : $staff['department_id'],
                $data['notes'] ?? null,
                Auth::id(),
            ]
        );
        $shiftId = Database::lastInsertId();
        AuditService::log('staff.shift_created', 'staff', 'create', "Created shift for {$staff['employee_id']} on {$data['shift_date']}.", [
            'staff_id' => $staffId, 'shift_id' => $shiftId,
        ], $request);
        return ['ok' => true];
    }

    public static function deleteShift(int $staffId, int $shiftId, Request $request): array
    {
        Database::execute('DELETE FROM staff_shifts WHERE id = ? AND staff_id = ?', [$shiftId, $staffId]);
        AuditService::log('staff.shift_deleted', 'staff', 'delete', "Deleted shift #{$shiftId}.", [
            'staff_id' => $staffId, 'shift_id' => $shiftId,
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Attendance
    // ------------------------------------------------------------------
    /** @return array{ok: bool, error?: string} */
    public static function recordAttendance(int $staffId, array $data, Request $request): array
    {
        $staff = StaffProfile::find($staffId);
        if ($staff === null) {
            return ['ok' => false, 'error' => 'Staff not found.'];
        }

        $existing = Database::queryOne('SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ?', [$staffId, $data['date']]);
        if ($existing !== null) {
            Database::execute(
                'UPDATE staff_attendance SET check_in = ?, check_out = ?, status = ?, notes = ?, recorded_by = ? WHERE id = ?',
                [$data['check_in'] ?? null, $data['check_out'] ?? null, $data['status'], $data['notes'] ?? null, Auth::id(), $existing['id']]
            );
            $msg = "Updated attendance for {$staff['employee_id']} on {$data['date']}.";
        } else {
            Database::execute(
                'INSERT INTO staff_attendance (staff_id, date, check_in, check_out, status, notes, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$staffId, $data['date'], $data['check_in'] ?? null, $data['check_out'] ?? null, $data['status'], $data['notes'] ?? null, Auth::id()]
            );
            $msg = "Recorded attendance for {$staff['employee_id']} on {$data['date']}.";
        }

        AuditService::log('staff.attendance', 'staff', 'create', $msg, [
            'staff_id' => $staffId, 'date' => $data['date'],
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Leaves
    // ------------------------------------------------------------------
    /** @return array{ok: bool, error?: string} */
    public static function createLeave(int $staffId, array $data, Request $request): array
    {
        $staff = StaffProfile::find($staffId);
        if ($staff === null) {
            return ['ok' => false, 'error' => 'Staff not found.'];
        }
        if ($data['start_date'] > $data['end_date']) {
            return ['ok' => false, 'error' => 'Start date must be on or before end date.'];
        }
        Database::execute(
            'INSERT INTO staff_leaves (staff_id, leave_type, start_date, end_date, reason, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$staffId, $data['leave_type'] ?? 'casual', $data['start_date'], $data['end_date'], $data['reason'] ?? null, $data['status'] ?? 'pending']
        );
        $leaveId = Database::lastInsertId();

        if (($data['status'] ?? 'pending') === 'approved') {
            Database::execute("UPDATE staff_profiles SET status = 'on_leave' WHERE id = ?", [$staffId]);
        }

        AuditService::log('staff.leave_created', 'staff', 'create', "Created leave for {$staff['employee_id']}.", [
            'staff_id' => $staffId, 'leave_id' => $leaveId,
        ], $request);
        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function decideLeave(int $leaveId, string $decision, Request $request): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Invalid decision.'];
        }
        $leave = Database::queryOne('SELECT * FROM staff_leaves WHERE id = ?', [$leaveId]);
        if ($leave === null) {
            return ['ok' => false, 'error' => 'Leave record not found.'];
        }
        Database::execute(
            'UPDATE staff_leaves SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?',
            [$decision, Auth::id(), $leaveId]
        );
        if ($decision === 'approved') {
            Database::execute("UPDATE staff_profiles SET status = 'on_leave' WHERE id = ?", [$leave['staff_id']]);
        }
        AuditService::log('staff.leave_' . $decision, 'staff', 'update', "Leave #{$leaveId} {$decision}.", [
            'leave_id' => $leaveId, 'staff_id' => $leave['staff_id'],
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Image upload
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
}
