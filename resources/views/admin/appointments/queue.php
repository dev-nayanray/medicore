<?php

declare(strict_types=1);

/**
 * Live queue dashboard — today's appointments grouped by status, with
 * quick-action buttons to advance patients through the workflow.
 * $date, $departmentId, $departments, $queue, $counts, $todayCounts
 */

$this->extend('layouts/admin');
$title = 'Live Queue';
$active = 'appointments';
$breadcrumbs = ['Hospital' => null, 'Appointments' => url('/admin/appointments'), 'Queue' => ''];

$statusTone = [
    'pending' => 'badge-amber', 'confirmed' => 'badge-teal', 'checked_in' => 'badge-navy',
    'in_consultation' => 'badge-violet', 'completed' => 'badge-emerald',
    'cancelled' => 'badge-slate', 'no_show' => 'badge-rose',
];
$isToday = $date === date('Y-m-d');

// Group by status for the swim-lanes.
$lanes = [
    'checked_in' => ['label' => 'Waiting', 'icon' => 'hourglass', 'tone' => 'navy'],
    'in_consultation' => ['label' => 'In consultation', 'icon' => 'stethoscope', 'tone' => 'violet'],
    'completed' => ['label' => 'Completed today', 'icon' => 'check-check', 'tone' => 'emerald'],
    'pending' => ['label' => 'Pending / scheduled', 'icon' => 'clock', 'tone' => 'amber'],
    'cancelled' => ['label' => 'Cancelled / no-show', 'icon' => 'x-circle', 'tone' => 'slate'],
];
$byStatus = [];
foreach ($queue as $a) {
    if (in_array($a['status'], ['cancelled','no_show'], true)) {
        $byStatus['cancelled'][] = $a;
    } else {
        $byStatus[$a['status']][] = $a;
    }
}
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="flex items-center gap-2 text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            Live Queue
            <?php if ($isToday): ?><span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span><?php endif; ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isToday ? 'Today — ' : '' ?><?= e(format_date($date, 'l, F j, Y')) ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <form method="get" class="flex items-center gap-2">
            <input type="date" name="date" value="<?= e($date) ?>" class="input !py-1.5 !text-xs w-auto" onchange="this.form.submit()">
            <select name="department_id" class="input !py-1.5 !text-xs w-auto" onchange="this.form.submit()">
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (string) $departmentId === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if (can('appointments.create')): ?>
            <a href="<?= url('/admin/appointments/create?' . http_build_query(['date' => $date, 'department_id' => $departmentId])) ?>" class="btn btn-primary">
                <i data-lucide="calendar-plus" class="h-4 w-4"></i><span class="hidden sm:inline">Book</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stat tiles -->
<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
    <?php
    $tiles = [
        ['label' => 'Total', 'value' => $counts['total'], 'icon' => 'calendar-days', 'tone' => 'text-slate-700 dark:text-slate-200'],
        ['label' => 'Scheduled', 'value' => $counts['pending'], 'icon' => 'clock', 'tone' => 'text-amber-600 dark:text-amber-400'],
        ['label' => 'Checked in', 'value' => $counts['checked_in'], 'icon' => 'log-in', 'tone' => 'text-navy-600 dark:text-navy-300'],
        ['label' => 'In consult', 'value' => $counts['in_consultation'], 'icon' => 'stethoscope', 'tone' => 'text-violet-600 dark:text-violet-400'],
        ['label' => 'Completed', 'value' => $counts['completed'], 'icon' => 'check-check', 'tone' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => 'Cancelled', 'value' => $counts['cancelled'], 'icon' => 'x-circle', 'tone' => 'text-slate-400'],
        ['label' => 'No-show', 'value' => $counts['no_show'], 'icon' => 'user-x', 'tone' => 'text-rose-600 dark:text-rose-400'],
    ];
    foreach ($tiles as $t): ?>
        <div class="card flex items-center gap-3 px-4 py-3">
            <i data-lucide="<?= $t['icon'] ?>" class="h-7 w-7 <?= $t['tone'] ?>"></i>
            <div>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= (int) $t['value'] ?></p>
                <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400"><?= e($t['label']) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Swim lanes -->
<div class="space-y-4">
    <?php foreach ($lanes as $status => $lane): $items = $byStatus[$status] ?? []; ?>
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <i data-lucide="<?= $lane['icon'] ?>" class="h-4 w-4 text-<?= $lane['tone'] ?>-600 dark:text-<?= $lane['tone'] ?>-400"></i>
                    <?= e($lane['label']) ?>
                </h2>
                <span class="badge badge-slate"><?= count($items) ?></span>
            </div>
            <?php if ($items === []): ?>
                <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No appointments in this lane.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Queue</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Type</th>
                            <?php if (in_array($status, ['checked_in','in_consultation','pending'])): ?>
                                <th class="text-right">Actions</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $a): ?>
                            <tr>
                                <td class="font-mono text-[13px] font-medium text-teal-600 dark:text-teal-400"><?= e($a['queue_token'] ?? '—') ?></td>
                                <td>
                                    <a href="<?= url('/admin/appointments/' . (int) $a['id']) ?>" class="text-[13px] font-medium text-slate-800 hover:underline dark:text-slate-100">
                                        <?= e($a['patient_name']) ?>
                                    </a>
                                    <p class="font-mono text-[11px] text-slate-400"><?= e($a['patient_code']) ?></p>
                                </td>
                                <td class="text-[12.5px] text-slate-600 dark:text-slate-300"><?= e($a['doctor_name'] ?? '—') ?><?php if ($a['room_number']): ?> · <?= e($a['room_number']) ?><?php endif; ?></td>
                                <td class="font-mono text-[12px] text-slate-500 dark:text-slate-400"><?= e(substr((string) $a['start_time'], 0, 5)) ?>–<?= e(substr((string) $a['end_time'], 0, 5)) ?></td>
                                <td><span class="badge badge-slate"><?= e(ucfirst(str_replace('_', ' ', $a['appointment_type']))) ?></span></td>
                                <?php if (in_array($status, ['checked_in','in_consultation','pending'])): ?>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <?php if ($status === 'pending' && can('appointments.update')): ?>
                                            <form method="post" action="<?= url('/admin/appointments/' . (int) $a['id'] . '/check-in') ?>"><?= csrf_field() ?>
                                                <button type="submit" class="btn btn-secondary !py-1 !px-2.5 !text-xs"><i data-lucide="log-in" class="h-3 w-3"></i>Check in</button>
                                            </form>
                                        <?php elseif ($status === 'checked_in' && can('appointments.update')): ?>
                                            <form method="post" action="<?= url('/admin/appointments/' . (int) $a['id'] . '/start') ?>"><?= csrf_field() ?>
                                                <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs"><i data-lucide="play" class="h-3 w-3"></i>Start</button>
                                            </form>
                                        <?php elseif ($status === 'in_consultation' && can('appointments.update')): ?>
                                            <form method="post" action="<?= url('/admin/appointments/' . (int) $a['id'] . '/complete') ?>"><?= csrf_field() ?>
                                                <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs"><i data-lucide="check" class="h-3 w-3"></i>Complete</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="<?= url('/admin/appointments/' . (int) $a['id']) ?>" class="icon-btn !p-1" title="Details"><i data-lucide="eye" class="h-3.5 w-3.5"></i></a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php $this->end(); ?>
