<?php

declare(strict_types=1);

/**
 * Doctor profile — tabs for overview, weekly schedule, leaves, recent visits.
 * $doctor (with 'schedule', 'leaves', 'recent_visits', 'visit_count')
 * $departments, $days, $tab
 */

$this->extend('layouts/admin');
$title = $doctor['user_name'];
$active = 'doctors';
$breadcrumbs = ['Hospital' => null, 'Doctors' => url('/admin/doctors'), $doctor['doctor_code'] => ''];

$isArchived = $doctor['archived_at'] !== null;
$canEdit = can('doctors.update') && !$isArchived;

$tabs = [
    'overview'  => ['label' => 'Overview',  'icon' => 'user-round',     'count' => null],
    'schedule'  => ['label' => 'Schedule',  'icon' => 'calendar-clock', 'count' => count($doctor['schedule'])],
    'leaves'    => ['label' => 'Leaves',    'icon' => 'plane',          'count' => count($doctor['leaves'])],
    'visits'    => ['label' => 'Visits',    'icon' => 'activity',       'count' => $doctor['visit_count']],
];

$statusTone = ['active' => 'badge-emerald', 'on_leave' => 'badge-amber', 'inactive' => 'badge-slate'];
$scheduleByDay = [];
foreach ($doctor['schedule'] as $slot) {
    $scheduleByDay[(int) $slot['day_of_week']][] = $slot;
}
?>

<?php $this->section('content'); ?>

<!-- Header -->
<div class="card">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
        <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-navy-700 to-navy-900 text-xl font-bold text-white">
            <?= e(strtoupper(substr((string) $doctor['user_name'], 0, 1))) ?>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><?= e($doctor['user_name']) ?></h1>
                <span class="badge <?= $statusTone[$doctor['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $doctor['status']))) ?></span>
                <?php if ($isArchived): ?><span class="badge badge-slate">Archived</span><?php endif; ?>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                <span class="font-mono font-medium text-teal-700 dark:text-teal-400"><?= e($doctor['doctor_code']) ?></span>
                <span><?= e($doctor['specialization']) ?></span>
                <?php if ($doctor['department_name']): ?><span>· <?= e($doctor['department_name']) ?></span><?php endif; ?>
                <?php if ($doctor['room_number']): ?><span>· <?= e($doctor['room_number']) ?></span><?php endif; ?>
            </p>
            <p class="mt-1 text-[12.5px] text-slate-400">
                <?= e($doctor['phone']) ?> · <?= e($doctor['email']) ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($canEdit): ?>
                <a href="<?= url('/admin/doctors/' . (int) $doctor['id'] . '/edit') ?>" class="btn btn-primary">
                    <i data-lucide="pencil" class="h-4 w-4"></i>Edit
                </a>
                <form method="post" action="<?= url('/admin/doctors/' . (int) $doctor['id'] . '/archive') ?>"
                      data-confirm="Archive doctor|<?= e($doctor['user_name']) ?> will be marked inactive. Schedule and history are preserved.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><i data-lucide="archive" class="h-4 w-4"></i>Archive</button>
                </form>
            <?php elseif ($isArchived && can('doctors.update')): ?>
                <form method="post" action="<?= url('/admin/doctors/' . (int) $doctor['id'] . '/restore') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i data-lucide="archive-restore" class="h-4 w-4"></i>Restore</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <!-- Tabs -->
    <div class="border-t border-slate-100 px-4 dark:border-slate-800">
        <nav class="flex gap-1 overflow-x-auto">
            <?php foreach ($tabs as $key => $meta): ?>
                <a href="<?= url('/admin/doctors/' . (int) $doctor['id'] . '?tab=' . $key) ?>"
                   class="flex items-center gap-2 whitespace-nowrap border-b-2 px-3.5 py-3 text-[13px] font-medium transition-colors <?= $tab === $key
                       ? 'border-teal-500 text-teal-700 dark:text-teal-400'
                       : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' ?>">
                    <i data-lucide="<?= $meta['icon'] ?>" class="h-4 w-4"></i><?= e($meta['label']) ?>
                    <?php if ($meta['count'] !== null): ?>
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold <?= $tab === $key ? 'bg-teal-500/15 text-teal-700 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' ?>"><?= (int) $meta['count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<?php if ($tab === 'overview'): ?>
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Profile</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                <?php
                $rows = [
                    'Specialization' => $doctor['specialization'],
                    'Department'      => $doctor['department_name'] ?? '—',
                    'Room'            => $doctor['room_number'] ?? '—',
                    'Registration'    => $doctor['registration_number'] ?? '—',
                    'Consultation fee' => $doctor['consultation_fee'] !== null ? format_money((string) $doctor['consultation_fee']) : '—',
                    'Hired'           => $doctor['hired_at'] ? format_date($doctor['hired_at'], 'M j, Y') : '—',
                ];
                ?>
                <?php foreach ($rows as $label => $value): ?>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400"><?= e($label) ?></dt>
                        <dd class="mt-0.5 text-[13.5px] text-slate-700 dark:text-slate-200"><?= e((string) $value) ?></dd>
                    </div>
                <?php endforeach; ?>
                <div class="sm:col-span-2">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Qualifications</dt>
                    <dd class="mt-0.5 text-[13.5px] text-slate-700 dark:text-slate-200"><?= e($doctor['qualifications'] ?? '—') ?></dd>
                </div>
                <?php if ($doctor['bio']): ?>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Bio</dt>
                        <dd class="mt-0.5 text-[13px] leading-relaxed text-slate-600 dark:text-slate-300"><?= e($doctor['bio']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
        <div class="space-y-4">
            <section class="card">
                <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">At a glance</h2>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                        <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="activity" class="h-3.5 w-3.5"></i>Total visits</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200"><?= (int) $doctor['visit_count'] ?></span>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                        <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="calendar-clock" class="h-3.5 w-3.5"></i>Schedule slots</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200"><?= count($doctor['schedule']) ?></span>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                        <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="plane" class="h-3.5 w-3.5"></i>Leave records</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200"><?= count($doctor['leaves']) ?></span>
                    </div>
                </div>
            </section>
        </div>
    </div>

<?php elseif ($tab === 'schedule'): ?>
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Weekly availability</h2>
                <p class="text-xs text-slate-400">Recurring consultation slots per day of week</p>
            </div>
            <?php if ($doctor['schedule'] === []): ?>
                <div class="p-5">
                    <?= $this->insert('components/empty-state', ['icon' => 'calendar-clock', 'compact' => true, 'title' => 'No schedule set', 'message' => 'Add weekly recurring slots so the appointments module can offer them.']) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($days as $i => $label): ?>
                        <?php $slots = $scheduleByDay[$i] ?? []; ?>
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <span class="w-10 text-[12px] font-semibold text-slate-500 dark:text-slate-400"><?= e($label) ?></span>
                            <div class="flex-1">
                                <?php if ($slots === []): ?>
                                    <span class="text-[12.5px] text-slate-300 dark:text-slate-600">—</span>
                                <?php else: ?>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php foreach ($slots as $slot): ?>
                                            <span class="badge <?= (int) $slot['is_active'] === 1 ? 'badge-teal' : 'badge-slate' ?>">
                                                <?= e(substr((string) $slot['start_time'], 0, 5)) ?>–<?= e(substr((string) $slot['end_time'], 0, 5)) ?>
                                                <?php if ($slot['room']): ?> · <?= e($slot['room']) ?><?php endif; ?>
                                                · max <?= (int) $slot['max_patients'] ?>
                                            </span>
                                            <?php if (can('doctors.update') && !$isArchived): ?>
                                                <form method="post" action="<?= url('/admin/doctors/' . (int) $doctor['id'] . '/schedule/' . (int) $slot['id'] . '/delete') ?>" class="inline" data-confirm="Delete slot|Remove this schedule slot?">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="text-[10px] text-rose-500 hover:underline">remove</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php if (can('doctors.update') && !$isArchived): ?>
            <form method="post" action="<?= url('/admin/doctors/' . (int) $doctor['id'] . '/schedule') ?>" class="card h-fit">
                <?= csrf_field() ?>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Add schedule slot</h2>
                    <p class="text-xs text-slate-400">Overlap validation is server-side</p>
                </div>
                <div class="space-y-3.5 p-5">
                    <div>
                        <label for="day_of_week" class="label">Day of week</label>
                        <select id="day_of_week" name="day_of_week" class="input">
                            <?php foreach ($days as $i => $label): ?>
                                <option value="<?= $i ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="start_time" class="label">Start</label>
                            <input type="time" id="start_time" name="start_time" value="09:00" class="input" required>
                        </div>
                        <div>
                            <label for="end_time" class="label">End</label>
                            <input type="time" id="end_time" name="end_time" value="17:00" class="input" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="max_patients" class="label">Max patients</label>
                            <input type="number" min="1" id="max_patients" name="max_patients" value="20" class="input">
                        </div>
                        <div>
                            <label for="room" class="label">Room</label>
                            <input type="text" id="room" name="room" class="input" placeholder="Cabin 3">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <input type="checkbox" name="is_active" value="1" class="checkbox !static" checked> active slot
                    </label>
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <i data-lucide="plus" class="h-4 w-4"></i>Add slot
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'leaves'): ?>
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Leave records</h2>
            </div>
            <?php if ($doctor['leaves'] === []): ?>
                <div class="p-5">
                    <?= $this->insert('components/empty-state', ['icon' => 'plane', 'compact' => true, 'title' => 'No leave records', 'message' => 'Time-off requests appear here.']) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($doctor['leaves'] as $leave): ?>
                        <li class="flex items-center justify-between gap-3 px-5 py-3.5">
                            <div>
                                <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100">
                                    <?= e(format_date($leave['start_date'], 'M j, Y')) ?> → <?= e(format_date($leave['end_date'], 'M j, Y')) ?>
                                </p>
                                <?php if ($leave['reason']): ?><p class="text-xs text-slate-400"><?= e($leave['reason']) ?></p><?php endif; ?>
                                <?php if ($leave['approver_name']): ?><p class="text-[11px] text-slate-400">by <?= e($leave['approver_name']) ?></p><?php endif; ?>
                            </div>
                            <span class="badge <?= $leave['status'] === 'approved' ? 'badge-emerald' : ($leave['status'] === 'rejected' ? 'badge-rose' : 'badge-amber') ?>"><?= e(ucfirst($leave['status'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php if (can('doctors.update') && !$isArchived): ?>
            <form method="post" action="<?= url('/admin/doctors/' . (int) $doctor['id'] . '/leaves') ?>" class="card h-fit">
                <?= csrf_field() ?>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Record leave</h2>
                </div>
                <div class="space-y-3.5 p-5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="leave_start" class="label">Start</label>
                            <input type="date" id="leave_start" name="start_date" value="<?= date('Y-m-d') ?>" class="input" required>
                        </div>
                        <div>
                            <label for="leave_end" class="label">End</label>
                            <input type="date" id="leave_end" name="end_date" value="<?= date('Y-m-d') ?>" class="input" required>
                        </div>
                    </div>
                    <div>
                        <label for="leave_reason" class="label">Reason</label>
                        <textarea id="leave_reason" name="reason" rows="2" class="input"></textarea>
                    </div>
                    <div>
                        <label for="leave_status" class="label">Status</label>
                        <select id="leave_status" name="status" class="input">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <i data-lucide="plus" class="h-4 w-4"></i>Record leave
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'visits'): ?>
    <div class="card mt-4">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Recent consultations</h2>
            <p class="text-xs text-slate-400"><?= (int) $doctor['visit_count'] ?> total visit<?= $doctor['visit_count'] !== 1 ? 's' : '' ?></p>
        </div>
        <?php if ($doctor['recent_visits'] === []): ?>
            <div class="p-5">
                <?= $this->insert('components/empty-state', ['icon' => 'activity', 'compact' => true, 'title' => 'No visits yet', 'message' => 'Consultations appear here as they are recorded in patient visits.']) ?>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($doctor['recent_visits'] as $v): ?>
                    <li class="flex items-center justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0 flex-1">
                            <a href="<?= url('/admin/patients/' . (int) $v['patient_id']) ?>" class="text-[13.5px] font-medium text-slate-800 hover:underline dark:text-slate-100">
                                <?= e($v['patient_name']) ?> <span class="font-mono text-xs text-slate-400"><?= e($v['patient_code']) ?></span>
                            </a>
                            <p class="text-xs text-slate-400"><?= e($v['chief_complaint']) ?> · <?= e(format_date($v['visited_at'], 'M j, Y — g:i A')) ?></p>
                        </div>
                        <span class="badge badge-slate"><?= e(ucfirst((string) $v['visit_type'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $this->end(); ?>
