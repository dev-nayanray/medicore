<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;

/**
 * Appointment administration — booking, reschedule, status transitions,
 * queue management, and reminders. Overlap detection runs inside a
 * transaction with row locking to prevent concurrent double-booking.
 */
final class AppointmentService
{
    /** Valid forward status transitions (from → [to]). */
    private const TRANSITIONS = [
        'pending'         => ['confirmed', 'checked_in', 'cancelled', 'no_show'],
        'confirmed'       => ['checked_in', 'in_consultation', 'cancelled', 'no_show', 'completed'],
        'checked_in'      => ['in_consultation', 'cancelled', 'no_show', 'completed'],
        'in_consultation' => ['completed', 'cancelled'],
        'completed'       => [],  // terminal
        'cancelled'       => [],  // terminal
        'no_show'         => [],  // terminal
    ];

    /**
     * Book a new appointment. Overlap check + insert run inside a
     * transaction with SELECT ... FOR UPDATE on the relevant rows.
     *
     * @return array{ok: bool, error?: string, id?: int, code?: string, queue_token?: string}
     */
    public static function book(array $data, Request $request): array
    {
        $doctorId = !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null;
        $departmentId = !empty($data['department_id']) ? (int) $data['department_id'] : null;
        $date = (string) $data['appointment_date'];
        $start = (string) $data['start_time'];
        $end = (string) $data['end_time'];
        $type = $data['appointment_type'] ?? 'scheduled';
        $isWalkIn = $type === 'walk_in';

        // Basic time sanity.
        if ($start >= $end) {
            return ['ok' => false, 'error' => 'Start time must be earlier than end time.'];
        }

        // Walk-ins without a doctor are allowed (front desk assigns later).
        // Scheduled appointments require a doctor.
        if (!$isWalkIn && $doctorId === null) {
            return ['ok' => false, 'error' => 'A doctor is required for scheduled appointments.'];
        }

        $pdo = Database::pdo();
        $code = Appointment::nextCode();

        try {
            $pdo->beginTransaction();

            // Lock all rows for this doctor + date to prevent concurrent overlap.
            if ($doctorId !== null) {
                $pdo->prepare(
                    'SELECT id FROM appointments
                     WHERE doctor_id = ? AND appointment_date = ?
                     FOR UPDATE'
                )->execute([$doctorId, $date]);

                // Range overlap check (inside the transaction, post-lock).
                if (Appointment::overlaps($doctorId, $date, $start, $end, ignoreCode: $code)) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'This time slot overlaps an existing appointment for the selected doctor.'];
                }

                // Validate against the doctor's weekly schedule (skip for walk-ins
                // and telemedicine, which may be outside regular hours).
                if (in_array($type, ['scheduled', 'follow_up'], true) && !DoctorSchedule::isOnLeaveOn($doctorId, $date)) {
                    $dayOfWeek = (int) date('w', strtotime($date));
                    $slots = DoctorSchedule::slotsForDate($doctorId, $date);
                    if ($slots !== []) {
                        $withinSlot = false;
                        foreach ($slots as $slot) {
                            if ((int) $slot['day_of_week'] === $dayOfWeek
                                && (int) $slot['is_active'] === 1
                                && $start >= substr((string) $slot['start_time'], 0, 5)
                                && $end <= substr((string) $slot['end_time'], 0, 5)) {
                                $withinSlot = true;
                                break;
                            }
                        }
                        if (!$withinSlot) {
                            $pdo->rollBack();
                            return ['ok' => false, 'error' => 'The selected time is outside the doctor\'s working schedule for this day.'];
                        }
                    }
                }

                // Auto-assign department from the doctor's profile if not provided.
                if ($departmentId === null) {
                    $departmentId = (int) (Database::scalar('SELECT department_id FROM doctors WHERE id = ?', [$doctorId]) ?: 0) ?: null;
                }
            }

            // Queue token: walk-ins get one immediately, scheduled get one on check-in.
            $queueToken = null;
            if ($isWalkIn) {
                $queueToken = Appointment::nextQueueToken($date, $departmentId);
            }

            $status = $isWalkIn ? 'checked_in' : ($data['status'] ?? 'pending');
            if (!in_array($status, ['pending', 'confirmed', 'checked_in'], true)) {
                $status = 'pending';
            }

            Database::execute(
                'INSERT INTO appointments
                    (appointment_code, patient_id, doctor_id, department_id, appointment_date,
                     start_time, end_time, appointment_type, status, queue_token, reason, notes, checked_in_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $code,
                    (int) $data['patient_id'],
                    $doctorId,
                    $departmentId,
                    $date, $start, $end,
                    $type, $status, $queueToken,
                    $data['reason'] ?? null, $data['notes'] ?? null,
                    $isWalkIn ? date('Y-m-d H:i:s') : null,
                    Auth::id(),
                ]
            );
            $id = Database::lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('Appointment booking failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not book the appointment. Please try again.'];
        }

        // Schedule the default reminder (24h before), unless the appointment is today.
        self::scheduleReminder($id, $date, $start);

        AuditService::log('appointment.created', 'appointments', 'create', "Booked appointment {$code} for {$date} {$start}–{$end}.", [
            'appointment_id' => $id, 'appointment_code' => $code, 'type' => $type, 'status' => $status,
        ], $request);

        return ['ok' => true, 'id' => (int) $id, 'code' => $code, 'queue_token' => $queueToken];
    }

    /**
     * Reschedule an appointment — change date/time. Re-runs overlap
     * detection in a transaction. Only non-terminal appointments can be rescheduled.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function reschedule(int $id, array $data, Request $request): array
    {
        $apt = Appointment::find($id);
        if ($apt === null) {
            return ['ok' => false, 'error' => 'Appointment not found.'];
        }
        if (in_array($apt['status'], ['completed', 'cancelled', 'no_show'], true)) {
            return ['ok' => false, 'error' => 'Cannot reschedule a ' . $apt['status'] . ' appointment.'];
        }

        $date = (string) $data['appointment_date'];
        $start = (string) $data['start_time'];
        $end = (string) $data['end_time'];

        if ($start >= $end) {
            return ['ok' => false, 'error' => 'Start time must be earlier than end time.'];
        }

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            if ($apt['doctor_id'] !== null) {
                $pdo->prepare(
                    'SELECT id FROM appointments
                     WHERE doctor_id = ? AND appointment_date = ?
                     FOR UPDATE'
                )->execute([$apt['doctor_id'], $date]);

                if (Appointment::overlaps((int) $apt['doctor_id'], $date, $start, $end, ignoreId: $id)) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'The new time slot overlaps an existing appointment for this doctor.'];
                }
            }

            Database::execute(
                'UPDATE appointments SET appointment_date = ?, start_time = ?, end_time = ? WHERE id = ?',
                [$date, $start, $end, $id]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('Reschedule failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not reschedule. Please try again.'];
        }

        // Re-schedule the reminder for the new date.
        Database::execute("UPDATE appointment_reminders SET status = 'cancelled' WHERE appointment_id = ? AND status = 'pending'", [$id]);
        self::scheduleReminder($id, $date, $start);

        AuditService::log('appointment.rescheduled', 'appointments', 'update', "Rescheduled {$apt['appointment_code']} to {$date} {$start}.", [
            'appointment_id' => $id, 'appointment_code' => $apt['appointment_code'],
        ], $request);

        return ['ok' => true];
    }

    /**
     * Transition an appointment to a new status. Validates the transition
     * is legal per the TRANSITIONS map. Side effects: check-in stamps
     * checked_in_at + assigns queue_token; in_consultation stamps
     * consultation_started_at; completed stamps completed_at.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function transition(int $id, string $to, ?Request $request = null): array
    {
        $apt = Appointment::find($id);
        if ($apt === null) {
            return ['ok' => false, 'error' => 'Appointment not found.'];
        }

        $from = $apt['status'];
        if ($from === $to) {
            return ['ok' => true]; // idempotent
        }
        if (!in_array($to, Appointment::STATUSES, true)) {
            return ['ok' => false, 'error' => 'Invalid target status.'];
        }
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            return ['ok' => false, 'error' => "Cannot transition from '{$from}' to '{$to}'."];
        }

        $updates = ['status = ?'];
        $params = [$to];

        if ($to === 'checked_in') {
            $updates[] = 'checked_in_at = ?';
            $params[] = date('Y-m-d H:i:s');
            // Assign a queue token if not yet set.
            if ($apt['queue_token'] === null) {
                $token = Appointment::nextQueueToken($apt['appointment_date'], $apt['department_id'] !== null ? (int) $apt['department_id'] : null);
                $updates[] = 'queue_token = ?';
                $params[] = $token;
            }
        } elseif ($to === 'in_consultation') {
            $updates[] = 'consultation_started_at = ?';
            $params[] = date('Y-m-d H:i:s');
        } elseif ($to === 'completed') {
            $updates[] = 'completed_at = ?';
            $params[] = date('Y-m-d H:i:s');
        } elseif ($to === 'cancelled') {
            // cancellation_reason + cancelled_by set separately in cancel().
        }

        $params[] = $id;
        Database::execute('UPDATE appointments SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);

        AuditService::log("appointment.{$to}", 'appointments', 'update', "Appointment {$apt['appointment_code']} → {$to}.", [
            'appointment_id' => $id, 'appointment_code' => $apt['appointment_code'], 'from' => $from, 'to' => $to,
        ], $request);

        return ['ok' => true];
    }

    /**
     * Cancel an appointment with a reason. Only non-terminal appointments.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function cancel(int $id, string $reason, Request $request): array
    {
        $apt = Appointment::find($id);
        if ($apt === null) {
            return ['ok' => false, 'error' => 'Appointment not found.'];
        }
        if (in_array($apt['status'], ['completed', 'cancelled', 'no_show'], true)) {
            return ['ok' => false, 'error' => 'Cannot cancel a ' . $apt['status'] . ' appointment.'];
        }

        Database::execute(
            "UPDATE appointments SET status = 'cancelled', cancellation_reason = ?, cancelled_by = ? WHERE id = ?",
            [trim($reason) !== '' ? mb_substr($reason, 0, 300) : null, Auth::id(), $id]
        );
        // Cancel pending reminders.
        Database::execute("UPDATE appointment_reminders SET status = 'cancelled' WHERE appointment_id = ? AND status = 'pending'", [$id]);

        AuditService::log('appointment.cancelled', 'appointments', 'update', "Cancelled appointment {$apt['appointment_code']}: " . ($reason ?: 'no reason given') . '.', [
            'appointment_id' => $id, 'appointment_code' => $apt['appointment_code'],
        ], $request);

        return ['ok' => true];
    }

    /**
     * Update notes on an appointment (clinical / reception notes).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function updateNotes(int $id, string $notes, Request $request): array
    {
        $apt = Appointment::find($id);
        if ($apt === null) {
            return ['ok' => false, 'error' => 'Appointment not found.'];
        }
        Database::execute('UPDATE appointments SET notes = ? WHERE id = ?', [mb_substr($notes, 0, 65535), $id]);
        AuditService::log('appointment.notes_updated', 'appointments', 'update', "Updated notes on {$apt['appointment_code']}.", [
            'appointment_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Reminders
    // ------------------------------------------------------------------
    /**
     * Schedule the default reminder: 24 hours before the appointment.
     * If the appointment is within 24h, schedule for "now" (send on next process).
     */
    public static function scheduleReminder(int $appointmentId, string $date, string $startTime): void
    {
        $scheduledAt = date('Y-m-d H:i:s', strtotime("{$date} {$startTime}") - 86400);
        if (strtotime($scheduledAt) <= time()) {
            $scheduledAt = date('Y-m-d H:i:s'); // due now
        }

        $message = 'You have an appointment on ' . date('M j, Y \a\t g:i A', strtotime("{$date} {$startTime}")) . '.';

        Database::execute(
            'INSERT INTO appointment_reminders (appointment_id, channel, scheduled_at, message, status)
             VALUES (?, ?, ?, ?, ?)',
            [$appointmentId, 'sms', $scheduledAt, $message, 'pending']
        );
    }

    /**
     * Process due reminders — marks them as sent. The dev transport writes
     * to storage/logs/mail.log (same pattern as MailService). Swap for a
     * real SMS/email gateway in production; the DB contract is unchanged.
     *
     * @return int Number of reminders processed.
     */
    public static function processReminders(): int
    {
        $due = Database::query(
            "SELECT r.id, r.message, r.appointment_id, a.appointment_code,
                    p.phone AS patient_phone, p.first_name, p.last_name
             FROM appointment_reminders r
             INNER JOIN appointments a ON a.id = r.appointment_id
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE r.status = 'pending' AND r.scheduled_at <= NOW()
             ORDER BY r.scheduled_at LIMIT 100"
        );

        $sent = 0;
        foreach ($due as $r) {
            try {
                $line = sprintf(
                    '[%s] SMS to %s (%s): %s [apt %s]',
                    date('Y-m-d H:i:s'),
                    $r['patient_phone'] ?? '—',
                    trim($r['first_name'] . ' ' . $r['last_name']),
                    $r['message'],
                    $r['appointment_code']
                );
                @file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line . PHP_EOL, FILE_APPEND);
                Database::execute(
                    "UPDATE appointment_reminders SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?",
                    [(int) $r['id']]
                );
                $sent++;
            } catch (\Throwable $e) {
                Database::execute(
                    "UPDATE appointment_reminders SET status = 'failed', last_error = ?, attempts = attempts + 1 WHERE id = ?",
                    [mb_substr($e->getMessage(), 0, 300), (int) $r['id']]
                );
                Logger::error('Reminder send failed for #' . $r['id'] . ': ' . $e->getMessage());
            }
        }
        return $sent;
    }
}
