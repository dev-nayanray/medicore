<?php

declare(strict_types=1);

/**
 * Appointment detail — patient, doctor, schedule, status timeline, actions.
 * $appointment
 */

$this->extend('layouts/admin');
$title = 'Appointment ' . $appointment['appointment_code'];
$active = 'appointments';
$breadcrumbs = ['Hospital' => null, 'Appointments' => url('/admin/appointments'), $appointment['appointment_code'] => ''];

$statusTone = [
    'pending' => 'badge-amber', 'confirmed' => 'badge-teal', 'checked_in' => 'badge-navy',
    'in_consultation' => 'badge-violet', 'completed' => 'badge-emerald',
    'cancelled' => 'badge-slate', 'no_show' => 'badge-rose',
];
$isTerminal = in_array($appointment['status'], ['completed', 'cancelled', 'no_show'], true);
$canEdit = can('appointments.update') && !$isTerminal;
$canApprove = can('appointments.approve');
$age = $appointment['date_of_birth'] ? (int) \App\Core\Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$appointment['date_of_birth']]) : null;

$timeline = [];
if ($appointment['checked_in_at']) $timeline[] = ['Checked in', $appointment['checked_in_at'], 'log-in'];
if ($appointment['consultation_started_at']) $timeline[] = ['Consultation started', $appointment['consultation_started_at'], 'stethoscope'];
if ($appointment['completed_at']) $timeline[] = ['Completed', $appointment['completed_at'], 'check'];
if ($appointment['cancelled_by_name']) $timeline[] = ['Cancelled by ' . $appointment['cancelled_by_name'], $appointment['updated_at'], 'ban'];
?>

<?php $this->section('content'); ?>

<div class="card">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-teal-500 to-navy-700 text-lg font-bold text-white">
            <i data-lucide="calendar-days" class="h-6 w-6"></i>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    <span class="font-mono"><?= e($appointment['appointment_code']) ?></span>
                </h1>
                <span class="badge <?= $statusTone[$appointment['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $appointment['status']))) ?></span>
                <span class="badge badge-slate"><?= e(ucfirst(str_replace('_', ' ', $appointment['appointment_type']))) ?></span>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                <a href="<?= url('/admin/patients/' . (int) $appointment['patient_id']) ?>" class="font-medium text-teal-600 hover:underline dark:text-teal-400"><?= e($appointment['patient_name']) ?></a>
                <span class="font-mono text-xs"><?= e($appointment['patient_code']) ?></span>
                <span>·</span>
                <span><?= e(format_date($appointment['appointment_date'], 'l, M j, Y')) ?></span>
                <span class="font-mono"><?= e(substr((string) $appointment['start_time'], 0, 5)) ?>–<?= e(substr((string) $appointment['end_time'], 0, 5)) ?></span>
                <?php if ($appointment['queue_token']): ?><span>· Queue <span class="font-mono font-medium"><?= e($appointment['queue_token']) ?></span></span><?php endif; ?>
            </p>
            <p class="mt-1 text-[12.5px] text-slate-400">
                <?= e($appointment['doctor_name'] ?? 'No doctor assigned') ?>
                <?php if ($appointment['specialization']): ?> · <?= e($appointment['specialization']) ?><?php endif; ?>
                <?php if ($appointment['room_number']): ?> · <?= e($appointment['room_number']) ?><?php endif; ?>
                <?php if ($appointment['department_name']): ?> · <?= e($appointment['department_name']) ?><?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($canEdit && $appointment['status'] === 'pending' && $canApprove): ?>
                <form method="post" action="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/confirm') ?>"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><i data-lucide="check" class="h-4 w-4"></i>Confirm</button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit && in_array($appointment['status'], ['pending','confirmed'], true)): ?>
                <form method="post" action="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/check-in') ?>"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i data-lucide="log-in" class="h-4 w-4"></i>Check in</button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit && in_array($appointment['status'], ['confirmed','checked_in'], true)): ?>
                <form method="post" action="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/start') ?>"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i data-lucide="play" class="h-4 w-4"></i>Start consultation</button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit && in_array($appointment['status'], ['in_consultation'], true)): ?>
                <form method="post" action="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/complete') ?>"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i data-lucide="check-check" class="h-4 w-4"></i>Complete</button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit && in_array($appointment['status'], ['pending','confirmed','checked_in'], true)): ?>
                <form method="post" action="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/no-show') ?>"
                      data-confirm="Mark as no-show|Patient did not attend this appointment?|danger"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><i data-lucide="user-x" class="h-4 w-4"></i>No-show</button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit && !in_array($appointment['status'], ['completed'], true)): ?>
                <form method="post" action="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/cancel') ?>"
                      data-confirm="Cancel appointment|Provide a cancellation reason below."><?= csrf_field() ?>
                    <input type="text" name="cancellation_reason" placeholder="Reason…" class="input !inline-block !w-40 !py-1.5 !text-xs" maxlength="300">
                    <button type="submit" class="btn btn-danger"><i data-lucide="x-circle" class="h-4 w-4"></i>Cancel</button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit): ?>
                <a href="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/edit') ?>" class="btn btn-secondary">
                    <i data-lucide="calendar-clock" class="h-4 w-4"></i>Reschedule
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <!-- Patient card -->
    <div class="card">
        <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Patient</h2>
        <div class="p-5">
            <a href="<?= url('/admin/patients/' . (int) $appointment['patient_id']) ?>" class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-navy-600/90 text-[11px] font-semibold text-white">
                    <?= e(strtoupper(substr((string) $appointment['patient_name'], 0, 1))) ?>
                </span>
                <div class="min-w-0">
                    <p class="truncate text-[13.5px] font-medium text-slate-800 hover:underline dark:text-slate-100"><?= e($appointment['patient_name']) ?></p>
                    <p class="font-mono text-[11px] text-slate-400"><?= e($appointment['patient_code']) ?></p>
                </div>
            </a>
            <dl class="mt-4 space-y-2 text-[13px]">
                <?php if ($age !== null): ?>
                <div class="flex justify-between"><dt class="text-slate-400">Age</dt><dd class="font-medium text-slate-700 dark:text-slate-200"><?= (int) $age ?> yrs · <?= e(ucfirst((string) $appointment['gender'])) ?></dd></div>
                <?php endif; ?>
                <?php if ($appointment['blood_group']): ?>
                <div class="flex justify-between"><dt class="text-slate-400">Blood</dt><dd><span class="badge badge-rose"><?= e($appointment['blood_group']) ?></span></dd></div>
                <?php endif; ?>
                <div class="flex justify-between"><dt class="text-slate-400">Phone</dt><dd class="font-mono text-slate-700 dark:text-slate-200"><?= e($appointment['patient_phone']) ?></dd></div>
            </dl>
            <?php if ($appointment['allergies'] && trim((string) $appointment['allergies']) !== '' && strtolower(trim((string) $appointment['allergies'])) !== 'none known'): ?>
                <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2.5 dark:border-rose-900/60 dark:bg-rose-950/30">
                    <p class="flex items-center gap-1.5 text-[11px] font-semibold text-rose-700 dark:text-rose-400"><i data-lucide="triangle-alert" class="h-3 w-3"></i>Allergies</p>
                    <p class="mt-1 text-[12px] text-rose-700 dark:text-rose-300"><?= e($appointment['allergies']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Visit details -->
    <div class="card">
        <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Visit details</h2>
        <dl class="space-y-3 p-5 text-[13px]">
            <div class="flex justify-between"><dt class="text-slate-400">Date</dt><dd class="font-medium text-slate-700 dark:text-slate-200"><?= e(format_date($appointment['appointment_date'], 'M j, Y (D)')) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Time</dt><dd class="font-mono font-medium text-slate-700 dark:text-slate-200"><?= e(substr((string) $appointment['start_time'], 0, 5)) ?> – <?= e(substr((string) $appointment['end_time'], 0, 5)) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Type</dt><dd><span class="badge badge-slate"><?= e(ucfirst(str_replace('_', ' ', $appointment['appointment_type']))) ?></span></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Queue token</dt><dd class="font-mono font-medium text-teal-600 dark:text-teal-400"><?= e($appointment['queue_token'] ?? '—') ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Booked by</dt><dd class="text-slate-700 dark:text-slate-200"><?= e($appointment['created_by_name'] ?? 'System') ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Booked at</dt><dd class="text-slate-700 dark:text-slate-200"><?= e(format_date($appointment['created_at'], 'M j, Y — g:i A')) ?></dd></div>
        </dl>
        <?php if ($appointment['reason']): ?>
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Reason for visit</p>
                <p class="mt-1 text-[13px] text-slate-700 dark:text-slate-200"><?= e($appointment['reason']) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($appointment['cancellation_reason']): ?>
            <div class="border-t border-rose-100 bg-rose-50 px-5 py-3 dark:border-rose-900/60 dark:bg-rose-950/30">
                <p class="text-[11px] font-medium uppercase tracking-wide text-rose-700 dark:text-rose-400">Cancellation reason</p>
                <p class="mt-1 text-[13px] text-rose-700 dark:text-rose-300"><?= e($appointment['cancellation_reason']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Timeline -->
    <div class="card">
        <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Status timeline</h2>
        <?php if ($timeline === []): ?>
            <div class="p-5">
                <p class="text-[12.5px] text-slate-400">No status transitions yet — this appointment is still in the initial <strong><?= e(ucfirst($appointment['status'])) ?></strong> state.</p>
            </div>
        <?php else: ?>
            <ol class="relative p-5">
                <?php foreach ($timeline as $i => $t): ?>
                    <li class="relative flex gap-3 pb-4 last:pb-0 <?= $i < count($timeline) - 1 ? 'before:absolute before:left-[11px] before:top-6 before:bottom-0 before:w-px before:bg-slate-200 dark:before:bg-slate-700' : '' ?>">
                        <span class="z-10 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-teal-500/15 text-teal-600 ring-4 ring-white dark:ring-slate-900 dark:text-teal-400">
                            <i data-lucide="<?= $t[2] ?>" class="h-3 w-3"></i>
                        </span>
                        <div>
                            <p class="text-[13px] font-medium text-slate-800 dark:text-slate-100"><?= e($t[0]) ?></p>
                            <p class="text-[11px] text-slate-400"><?= e(format_date($t[1], 'M j, Y — g:i A')) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<!-- Notes (editable) -->
<?php if (can('appointments.update') && !$isTerminal): ?>
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Clinical / reception notes</h2>
    </div>
    <form method="post" action="<?= url('/admin/appointments/' . (int) $appointment['id'] . '/notes') ?>" class="p-5">
        <?= csrf_field() ?>
        <textarea name="notes" rows="3" class="input" placeholder="Add notes visible to clinical staff…"><?= e((string) ($appointment['notes'] ?? '')) ?></textarea>
        <div class="mt-3 flex justify-end">
            <button type="submit" class="btn btn-secondary"><i data-lucide="save" class="h-4 w-4"></i>Save notes</button>
        </div>
    </form>
</div>
<?php elseif (!empty($appointment['notes'])): ?>
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Notes</h2></div>
    <div class="p-5 text-[13px] leading-relaxed text-slate-700 dark:text-slate-200 whitespace-pre-line"><?= e((string) $appointment['notes']) ?></div>
</div>
<?php endif; ?>
<?php $this->end(); ?>
