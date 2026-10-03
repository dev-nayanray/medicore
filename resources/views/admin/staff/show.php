<?php

declare(strict_types=1);

/**
 * Staff profile — tabs for overview, shifts, attendance, leaves.
 * $staff (with 'shifts', 'recent_attendance', 'leaves', 'attendance_summary')
 * $departments, $tab
 */

$this->extend('layouts/admin');
$title = $staff['user_name'];
$active = 'staff';
$breadcrumbs = ['Hospital' => null, 'Staff' => url('/admin/staff'), $staff['employee_id'] => ''];

$isArchived = $staff['archived_at'] !== null;
$canEdit = can('staff.update') && !$isArchived;

$tabs = [
    'overview'   => ['label' => 'Overview',   'icon' => 'user-round',     'count' => null],
    'shifts'     => ['label' => 'Shifts',     'icon' => 'calendar-clock', 'count' => count($staff['shifts'])],
    'attendance' => ['label' => 'Attendance', 'icon' => 'clipboard-check', 'count' => count($staff['recent_attendance'])],
    'leaves'     => ['label' => 'Leaves',     'icon' => 'plane',          'count' => count($staff['leaves'])],
];

$statusTone = ['active' => 'badge-emerald', 'on_leave' => 'badge-amber', 'inactive' => 'badge-slate', 'terminated' => 'badge-rose'];
$shiftTone = ['morning' => 'badge-amber', 'evening' => 'badge-teal', 'night' => 'badge-navy', 'on_call' => 'badge-rose'];
$attendanceTone = ['present' => 'badge-emerald', 'late' => 'badge-amber', 'absent' => 'badge-rose', 'half_day' => 'badge-slate', 'leave' => 'badge-navy'];
?>

<?php $this->section('content'); ?>

<div class="card">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
        <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-slate-500 to-slate-700 text-xl font-bold text-white">
            <?= e(strtoupper(substr((string) $staff['user_name'], 0, 1))) ?>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><?= e($staff['user_name']) ?></h1>
                <span class="badge <?= $statusTone[$staff['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $staff['status']))) ?></span>
                <?php if ($isArchived): ?><span class="badge badge-slate">Archived</span><?php endif; ?>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                <span class="font-mono font-medium text-teal-700 dark:text-teal-400"><?= e($staff['employee_id']) ?></span>
                <span><?= e($staff['job_title']) ?></span>
                <?php if ($staff['department_name']): ?><span>· <?= e($staff['department_name']) ?></span><?php endif; ?>
            </p>
            <p class="mt-1 text-[12.5px] text-slate-400">
                <?= e($staff['phone']) ?> · <?= e($staff['email']) ?>
                <?php if ($staff['hire_date']): ?>· hired <?= e(format_date($staff['hire_date'], 'M j, Y')) ?><?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($canEdit): ?>
                <a href="<?= url('/admin/staff/' . (int) $staff['id'] . '/edit') ?>" class="btn btn-primary">
                    <i data-lucide="pencil" class="h-4 w-4"></i>Edit
                </a>
                <form method="post" action="<?= url('/admin/staff/' . (int) $staff['id'] . '/archive') ?>"
                      data-confirm="Archive staff|<?= e($staff['user_name']) ?> will be marked terminated. Shifts and history are preserved.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><i data-lucide="archive" class="h-4 w-4"></i>Archive</button>
                </form>
            <?php elseif ($isArchived && can('staff.update')): ?>
                <form method="post" action="<?= url('/admin/staff/' . (int) $staff['id'] . '/restore') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i data-lucide="archive-restore" class="h-4 w-4"></i>Restore</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="border-t border-slate-100 px-4 dark:border-slate-800">
        <nav class="flex gap-1 overflow-x-auto">
            <?php foreach ($tabs as $key => $meta): ?>
                <a href="<?= url('/admin/staff/' . (int) $staff['id'] . '?tab=' . $key) ?>"
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
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Employment</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                <?php
                $rows = [
                    'Job title'       => $staff['job_title'],
                    'Department'      => $staff['department_name'] ?? '—',
                    'Employment type' => ucfirst(str_replace('_', ' ', $staff['employment_type'])),
                    'Hire date'       => $staff['hire_date'] ? format_date($staff['hire_date'], 'M j, Y') : '—',
                    'Status'          => ucfirst(str_replace('_', ' ', $staff['status'])),
                    'Employee ID'     => $staff['employee_id'],
                ];
                ?>
                <?php foreach ($rows as $label => $value): ?>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400"><?= e($label) ?></dt>
                        <dd class="mt-0.5 text-[13.5px] text-slate-700 dark:text-slate-200"><?= e((string) $value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="card">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Attendance (30 days)</h2>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                    <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-500"></i>Present</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200"><?= (int) $staff['attendance_summary']['present'] ?></span>
                </div>
                <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                    <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="clock" class="h-3.5 w-3.5 text-amber-500"></i>Late</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200"><?= (int) $staff['attendance_summary']['late'] ?></span>
                </div>
                <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                    <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="x" class="h-3.5 w-3.5 text-rose-500"></i>Absent</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200"><?= (int) $staff['attendance_summary']['absent'] ?></span>
                </div>
                <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                    <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="plane" class="h-3.5 w-3.5 text-navy-500"></i>Leave</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200"><?= (int) $staff['attendance_summary']['leave'] ?></span>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'shifts'): ?>
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Upcoming shifts</h2>
                <p class="text-xs text-slate-400">Next 14 days</p>
            </div>
            <?php if ($staff['shifts'] === []): ?>
                <div class="p-5">
                    <?= $this->insert('components/empty-state', ['icon' => 'calendar-clock', 'compact' => true, 'title' => 'No shifts scheduled', 'message' => 'Schedule a shift using the form.']) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($staff['shifts'] as $shift): ?>
                        <li class="flex items-center justify-between gap-3 px-5 py-3.5">
                            <div>
                                <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100">
                                    <?= e(format_date($shift['shift_date'], 'D, M j, Y')) ?>
                                    <span class="ml-1 font-mono text-xs text-slate-400"><?= e(substr((string) $shift['start_time'], 0, 5)) ?>–<?= e(substr((string) $shift['end_time'], 0, 5)) ?></span>
                                </p>
                                <p class="text-xs text-slate-400">
                                    <span class="badge <?= $shiftTone[$shift['shift_type']] ?? 'badge-slate' ?>"><?= e(ucfirst($shift['shift_type'])) ?></span>
                                    <?= $shift['department_name'] ? ' · ' . e($shift['department_name']) : '' ?>
                                    <?= $shift['notes'] ? ' · ' . e($shift['notes']) : '' ?>
                                </p>
                            </div>
                            <?php if (can('staff.update') && !$isArchived): ?>
                                <form method="post" action="<?= url('/admin/staff/' . (int) $staff['id'] . '/shifts/' . (int) $shift['id'] . '/delete') ?>" data-confirm="Delete shift|Remove this shift?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn hover:!text-rose-600" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php if (can('staff.update') && !$isArchived): ?>
            <form method="post" action="<?= url('/admin/staff/' . (int) $staff['id'] . '/shifts') ?>" class="card h-fit">
                <?= csrf_field() ?>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Schedule shift</h2>
                    <p class="text-xs text-slate-400">Overlap validation is server-side</p>
                </div>
                <div class="space-y-3.5 p-5">
                    <div>
                        <label for="shift_date" class="label">Date</label>
                        <input type="date" id="shift_date" name="shift_date" value="<?= date('Y-m-d') ?>" class="input" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="shift_start" class="label">Start</label>
                            <input type="time" id="shift_start" name="start_time" value="09:00" class="input" required>
                        </div>
                        <div>
                            <label for="shift_end" class="label">End</label>
                            <input type="time" id="shift_end" name="end_time" value="17:00" class="input" required>
                        </div>
                    </div>
                    <div>
                        <label for="shift_type" class="label">Type</label>
                        <select id="shift_type" name="shift_type" class="input">
                            <option value="morning">Morning</option>
                            <option value="evening">Evening</option>
                            <option value="night">Night</option>
                            <option value="on_call">On-call</option>
                        </select>
                    </div>
                    <div>
                        <label for="shift_dept" class="label">Department override</label>
                        <select id="shift_dept" name="department_id" class="input">
                            <option value="">Use staff department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="shift_notes" class="label">Notes</label>
                        <input type="text" id="shift_notes" name="notes" class="input" placeholder="Optional">
                    </div>
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <i data-lucide="plus" class="h-4 w-4"></i>Schedule shift
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'attendance'): ?>
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Recent attendance</h2>
            </div>
            <?php if ($staff['recent_attendance'] === []): ?>
                <div class="p-5">
                    <?= $this->insert('components/empty-state', ['icon' => 'clipboard-check', 'compact' => true, 'title' => 'No attendance recorded', 'message' => 'Mark daily attendance using the form.']) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($staff['recent_attendance'] as $a): ?>
                        <li class="flex items-center justify-between gap-3 px-5 py-3.5">
                            <div>
                                <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100"><?= e(format_date($a['date'], 'D, M j, Y')) ?></p>
                                <p class="text-xs text-slate-400">
                                    <?php if ($a['check_in']): ?>in <?= e(substr((string) $a['check_in'], 0, 5)) ?><?php endif; ?>
                                    <?php if ($a['check_out']): ?> · out <?= e(substr((string) $a['check_out'], 0, 5)) ?><?php endif; ?>
                                    <?= $a['recorder_name'] ? ' · by ' . e($a['recorder_name']) : '' ?>
                                </p>
                            </div>
                            <span class="badge <?= $attendanceTone[$a['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $a['status']))) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php if (can('staff.update') && !$isArchived): ?>
            <form method="post" action="<?= url('/admin/staff/' . (int) $staff['id'] . '/attendance') ?>" class="card h-fit">
                <?= csrf_field() ?>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Record attendance</h2>
                </div>
                <div class="space-y-3.5 p-5">
                    <div>
                        <label for="att_date" class="label">Date</label>
                        <input type="date" id="att_date" name="date" value="<?= date('Y-m-d') ?>" class="input" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="att_in" class="label">Check-in</label>
                            <input type="time" id="att_in" name="check_in" class="input" value="09:00">
                        </div>
                        <div>
                            <label for="att_out" class="label">Check-out</label>
                            <input type="time" id="att_out" name="check_out" class="input" value="17:00">
                        </div>
                    </div>
                    <div>
                        <label for="att_status" class="label">Status</label>
                        <select id="att_status" name="status" class="input">
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="half_day">Half-day</option>
                            <option value="leave">On leave</option>
                        </select>
                    </div>
                    <div>
                        <label for="att_notes" class="label">Notes</label>
                        <input type="text" id="att_notes" name="notes" class="input">
                    </div>
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <i data-lucide="save" class="h-4 w-4"></i>Save
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
            <?php if ($staff['leaves'] === []): ?>
                <div class="p-5">
                    <?= $this->insert('components/empty-state', ['icon' => 'plane', 'compact' => true, 'title' => 'No leave records', 'message' => 'Leave requests appear here.']) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($staff['leaves'] as $leave): ?>
                        <li class="flex items-center justify-between gap-3 px-5 py-3.5">
                            <div>
                                <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100">
                                    <span class="badge badge-slate mr-1"><?= e(ucfirst(str_replace('_', ' ', $leave['leave_type']))) ?></span>
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
        <?php if (can('staff.update') && !$isArchived): ?>
            <form method="post" action="<?= url('/admin/staff/' . (int) $staff['id'] . '/leaves') ?>" class="card h-fit">
                <?= csrf_field() ?>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Record leave</h2>
                </div>
                <div class="space-y-3.5 p-5">
                    <div>
                        <label for="leave_type" class="label">Type</label>
                        <select id="leave_type" name="leave_type" class="input">
                            <option value="casual">Casual</option>
                            <option value="sick">Sick</option>
                            <option value="annual">Annual</option>
                            <option value="maternity">Maternity</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
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
<?php endif; ?>

<?php $this->end(); ?>
