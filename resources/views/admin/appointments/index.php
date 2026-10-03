<?php

declare(strict_types=1);

/**
 * Appointment directory — filters, sortable, pagination, export.
 * $appointments, $total, $page, $pages, $perPage, $filters, $counts, $doctors, $departments, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Appointments';
$active = 'appointments';
$breadcrumbs = ['Hospital' => null, 'Appointments' => ''];

$statusTone = [
    'pending'         => 'badge-amber',
    'confirmed'       => 'badge-teal',
    'checked_in'      => 'badge-navy',
    'in_consultation' => 'badge-violet',
    'completed'       => 'badge-emerald',
    'cancelled'       => 'badge-slate',
    'no_show'         => 'badge-rose',
];
$typeTone = ['scheduled' => 'badge-teal', 'walk_in' => 'badge-amber', 'follow_up' => 'badge-navy', 'telemedicine' => 'badge-slate'];

$exportQuery = http_build_query(array_filter([
    'q' => $filters['search'], 'status' => $filters['status'], 'type' => $filters['type'],
    'doctor_id' => $filters['doctor_id'], 'department_id' => $filters['department_id'],
    'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
]));
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Appointments</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $counts['today'] ?> today ·
            <span class="text-teal-600 dark:text-teal-400"><?= (int) $counts['today_completed'] ?> completed</span> ·
            <span class="text-amber-600 dark:text-amber-400"><?= (int) ($counts['today_checked_in'] + $counts['today_in_consultation']) ?> active</span> ·
            <span class="text-rose-600 dark:text-rose-400"><?= (int) $counts['today_no_show'] ?> no-show</span>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/appointments/queue') ?>" class="btn btn-secondary">
            <i data-lucide="list-checks" class="h-4 w-4"></i><span class="hidden sm:inline">Live queue</span>
        </a>
        <a href="<?= url('/admin/appointments/calendar') ?>" class="btn btn-secondary">
            <i data-lucide="calendar-days" class="h-4 w-4"></i><span class="hidden sm:inline">Calendar</span>
        </a>
        <a href="<?= url('/admin/appointments/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>" class="btn btn-secondary">
            <i data-lucide="download" class="h-4 w-4"></i><span class="hidden sm:inline">Export</span>
        </a>
        <?php if (can('appointments.create')): ?>
            <a href="<?= url('/admin/appointments/create') ?>" class="btn btn-primary">
                <i data-lucide="calendar-plus" class="h-4 w-4"></i>Book appointment
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <!-- Filter toolbar -->
    <form method="get" action="<?= url('/admin/appointments') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search code, patient, doctor, queue…" class="input pl-9 text-sm">
        </div>
        <select name="status" class="input w-auto text-sm">
            <option value="">All statuses</option>
            <?php foreach (App\Models\Appointment::STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="input w-auto text-sm">
            <option value="">All types</option>
            <?php foreach (App\Models\Appointment::TYPES as $t): ?>
                <option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $t))) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="doctor_id" class="input w-auto text-sm">
            <option value="">All doctors</option>
            <?php foreach ($doctors as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>" <?= (string) $filters['doctor_id'] === (string) $doc['id'] ? 'selected' : '' ?>><?= e($doc['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="input w-auto text-sm" title="From date">
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="input w-auto text-sm" title="To date">
        <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
        <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['status'] !== '' || $filters['type'] !== '' || $filters['doctor_id'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== ''): ?>
            <a href="<?= url('/admin/appointments') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($appointments === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $filters['search'] !== '' || $filters['status'] !== '' ? 'search-x' : 'calendar-days',
                'title'   => $filters['search'] !== '' || $filters['status'] !== '' ? 'No appointments match your filters' : 'No appointments yet',
                'message' => $filters['search'] !== '' || $filters['status'] !== '' ? 'Try widening the filters.' : 'Book the first appointment to get started.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date / Time</th>
                    <th>Type</th>
                    <th>Queue</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($appointments as $a): ?>
                    <tr class="<?= in_array($a['status'], ['cancelled','no_show'], true) ? 'opacity-60' : '' ?>">
                        <td>
                            <a href="<?= url('/admin/appointments/' . (int) $a['id']) ?>" class="font-mono text-[12px] font-medium text-teal-700 hover:underline dark:text-teal-400">
                                <?= e($a['appointment_code']) ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?= url('/admin/patients/' . (int) $a['patient_id']) ?>" class="text-[13px] font-medium text-slate-800 hover:underline dark:text-slate-100">
                                <?= e($a['patient_name']) ?>
                            </a>
                            <p class="font-mono text-[11px] text-slate-400"><?= e($a['patient_code']) ?></p>
                        </td>
                        <td class="text-[13px] text-slate-600 dark:text-slate-300">
                            <?= e($a['doctor_name'] ?? '—') ?>
                            <?php if ($a['specialization']): ?><span class="block text-[11px] text-slate-400"><?= e($a['specialization']) ?></span><?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap text-[13px] text-slate-600 dark:text-slate-300">
                            <?= e(format_date($a['appointment_date'], 'M j, Y')) ?>
                            <span class="font-mono text-slate-400"><?= e(substr((string) $a['start_time'], 0, 5)) ?>–<?= e(substr((string) $a['end_time'], 0, 5)) ?></span>
                        </td>
                        <td><span class="badge <?= $typeTone[$a['appointment_type']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $a['appointment_type']))) ?></span></td>
                        <td class="font-mono text-[12px] text-slate-500 dark:text-slate-400"><?= e($a['queue_token'] ?? '—') ?></td>
                        <td><span class="badge <?= $statusTone[$a['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $a['status']))) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->insert('components/pagination', [
            'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage,
            'baseUrl' => $baseUrl,
        ]) ?>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
