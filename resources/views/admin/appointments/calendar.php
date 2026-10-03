<?php

declare(strict_types=1);

/**
 * Appointment calendar — day / week / month views.
 * $view, $date, $appointments, $doctors, $departments, $filters
 */

$this->extend('layouts/admin');
$title = 'Appointment Calendar';
$active = 'appointments';
$breadcrumbs = ['Hospital' => null, 'Appointments' => url('/admin/appointments'), 'Calendar' => ''];

$statusTone = [
    'pending' => 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    'confirmed' => 'border-teal-300 bg-teal-50 text-teal-800 dark:border-teal-700 dark:bg-teal-950/40 dark:text-teal-300',
    'checked_in' => 'border-navy-300 bg-navy-50 text-navy-800 dark:border-navy-700 dark:bg-navy-950/40 dark:text-navy-300',
    'in_consultation' => 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
    'completed' => 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    'cancelled' => 'border-slate-300 bg-slate-50 text-slate-500 line-through dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500',
    'no_show' => 'border-rose-300 bg-rose-50 text-rose-600 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-400',
];

$ts = strtotime($date);
if ($view === 'week') {
    $rangeStart = strtotime('Sunday this week', $ts);
    $rangeEnd = strtotime('Saturday this week', $ts);
} elseif ($view === 'month') {
    $rangeStart = strtotime('first day of this month', $ts);
    $rangeEnd = strtotime('last day of this month', $ts);
} else {
    $rangeStart = $rangeEnd = $ts;
}

// Group appointments by date for week/month.
$byDate = [];
foreach ($appointments as $a) {
    $byDate[$a['appointment_date']][] = $a;
}

$prevDate = date('Y-m-d', $view === 'day' ? strtotime('-1 day', $ts) : ($view === 'week' ? strtotime('-7 days', $ts) : strtotime('first day of previous month', $ts)));
$nextDate = date('Y-m-d', $view === 'day' ? strtotime('+1 day', $ts) : ($view === 'week' ? strtotime('+7 days', $ts) : strtotime('first day of next month', $ts)));
$todayDate = date('Y-m-d');

// Pre-compute navigation URLs to avoid complex inline expressions.
$f = array_filter($filters);
$todayUrl = url('/admin/appointments/calendar?' . http_build_query(array_merge(['view' => $view, 'date' => $todayDate], $f)));
$prevUrl = url('/admin/appointments/calendar?' . http_build_query(array_merge(['view' => $view, 'date' => $prevDate], $f)));
$nextUrl = url('/admin/appointments/calendar?' . http_build_query(array_merge(['view' => $view, 'date' => $nextDate], $f)));
$viewUrls = [];
foreach (['day', 'week', 'month'] as $vv) {
    $viewUrls[$vv] = url('/admin/appointments/calendar?' . http_build_query(array_merge(['view' => $vv, 'date' => $date], $f)));
}
$bookUrl = url('/admin/appointments/create?' . http_build_query(['date' => $date, 'doctor_id' => $filters['doctor_id']]));
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Calendar</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?php if ($view === 'day'): ?>
                <?= e(format_date($date, 'l, F j, Y')) ?>
            <?php elseif ($view === 'week'): ?>
                <?= e(format_date(date('Y-m-d', $rangeStart), 'M j')) ?> – <?= e(format_date(date('Y-m-d', $rangeEnd), 'M j, Y')) ?>
            <?php else: ?>
                <?= e(format_date($date, 'F Y')) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if (can('appointments.create')): ?>
            <a href="<?= e($bookUrl) ?>" class="btn btn-primary">
                <i data-lucide="calendar-plus" class="h-4 w-4"></i>Book
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
        <div class="flex items-center gap-1">
            <a href="<?= e($todayUrl) ?>" class="btn btn-secondary !py-1.5 !px-3 !text-xs">Today</a>
            <a href="<?= e($prevUrl) ?>" class="icon-btn !p-1.5" aria-label="Previous"><i data-lucide="chevron-left" class="h-4 w-4"></i></a>
            <a href="<?= e($nextUrl) ?>" class="icon-btn !p-1.5" aria-label="Next"><i data-lucide="chevron-right" class="h-4 w-4"></i></a>
        </div>
        <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800">
            <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $v => $label): ?>
                <a href="<?= e($viewUrls[$v]) ?>" class="rounded-md px-3 py-1.5 text-[12px] font-medium transition-colors <?= $view === $v ? 'bg-white text-teal-700 shadow-sm dark:bg-slate-900 dark:text-teal-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <form method="get" class="flex flex-wrap items-center gap-2">
            <select name="doctor_id" class="input !py-1.5 !text-xs w-auto" onchange="this.form.submit()">
                <option value="">All doctors</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (string) $filters['doctor_id'] === (string) $doc['id'] ? 'selected' : '' ?>><?= e($doc['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="department_id" class="input !py-1.5 !text-xs w-auto" onchange="this.form.submit()">
                <option value="">All depts</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (string) $filters['department_id'] === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input type="hidden" name="date" value="<?= e($date) ?>">
        </form>
    </div>

    <?php if ($view === 'day'): ?>
        <!-- DAY VIEW: hourly grid -->
        <div class="p-5">
            <?php if (empty($byDate[$date])): ?>
                <?= $this->insert('components/empty-state', ['icon' => 'calendar-x', 'compact' => true, 'title' => 'No appointments today', 'message' => 'Book one or switch to another date.']) ?>
            <?php else: ?>
                <div class="space-y-1.5">
                    <?php foreach ($byDate[$date] as $a): ?>
                        <a href="<?= url('/admin/appointments/' . (int) $a['id']) ?>" class="flex items-center gap-3 rounded-lg border-l-4 px-4 py-2.5 transition-shadow hover:shadow-md <?= $statusTone[$a['status']] ?? 'border-slate-300 bg-slate-50' ?>">
                            <span class="font-mono text-[13px] font-medium"><?= e(substr((string) $a['start_time'], 0, 5)) ?></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13.5px] font-medium"><?= e($a['patient_name']) ?></p>
                                <p class="text-[11px] text-slate-500"><?= e($a['doctor_name'] ?? 'Unassigned') ?> · <?= e(ucfirst(str_replace('_', ' ', $a['appointment_type']))) ?><?php if ($a['queue_token']): ?> · <?= e($a['queue_token']) ?><?php endif; ?></p>
                            </div>
                            <span class="badge badge-slate"><?= e(ucfirst(str_replace('_', ' ', $a['status']))) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($view === 'week'): ?>
        <!-- WEEK VIEW: 7 columns -->
        <div class="grid grid-cols-1 gap-px overflow-x-auto bg-slate-100 sm:grid-cols-7 dark:bg-slate-800">
            <?php for ($d = 0; $d < 7; $d++): $dayTs = strtotime("+{$d} days", $rangeStart); $dayDate = date('Y-m-d', $dayTs); $dayAppts = $byDate[$dayDate] ?? []; $isToday = $dayDate === $todayDate; ?>
                <div class="bg-white dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800 <?= $isToday ? 'bg-teal-500/10' : '' ?>">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"><?= e(date('D', $dayTs)) ?></p>
                        <p class="text-[15px] font-bold <?= $isToday ? 'text-teal-600 dark:text-teal-400' : 'text-slate-800 dark:text-slate-100' ?>"><?= e(date('j', $dayTs)) ?></p>
                    </div>
                    <div class="min-h-[200px] p-1.5 space-y-1">
                        <?php foreach ($dayAppts as $a): ?>
                            <a href="<?= url('/admin/appointments/' . (int) $a['id']) ?>" class="block rounded border-l-[3px] px-1.5 py-1 text-left transition-shadow hover:shadow-sm <?= $statusTone[$a['status']] ?? 'border-slate-300 bg-slate-50' ?>">
                                <p class="font-mono text-[10px] font-semibold"><?= e(substr((string) $a['start_time'], 0, 5)) ?></p>
                                <p class="truncate text-[11px] font-medium"><?= e($a['patient_name']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

    <?php else: ?>
        <!-- MONTH VIEW: calendar grid -->
        <?php
        $firstDow = (int) date('w', strtotime(date('Y-m-01', $ts)));
        $daysInMonth = (int) date('t', $ts);
        $gridStart = strtotime(date('Y-m-01', $ts) . ' -' . $firstDow . ' days');
        ?>
        <div class="grid grid-cols-7 gap-px bg-slate-100 dark:bg-slate-800">
            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?>
                <div class="bg-slate-50 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:bg-slate-900/60"><?= e($dn) ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < 42; $i++): $cellTs = strtotime("+{$i} days", $gridStart); $cellDate = date('Y-m-d', $cellTs); $inMonth = date('m', $cellTs) === date('m', $ts); $isToday = $cellDate === $todayDate; $cellAppts = $byDate[$cellDate] ?? []; ?>
                <div class="min-h-[110px] bg-white p-1.5 dark:bg-slate-900 <?= !$inMonth ? 'opacity-40' : '' ?>">
                    <p class="mb-1 text-right text-[11px] font-medium <?= $isToday ? 'inline-block rounded-full bg-teal-500 px-1.5 text-white' : 'text-slate-500 dark:text-slate-400' ?>"><?= e(date('j', $cellTs)) ?></p>
                    <div class="space-y-0.5">
                        <?php foreach (array_slice($cellAppts, 0, 3) as $a): ?>
                            <a href="<?= url('/admin/appointments/' . (int) $a['id']) ?>" class="block truncate rounded px-1 py-0.5 text-[10px] <?= $statusTone[$a['status']] ?? 'bg-slate-50' ?>" title="<?= e($a['patient_name'] . ' — ' . substr((string) $a['start_time'], 0, 5)) ?>">
                                <span class="font-mono"><?= e(substr((string) $a['start_time'], 0, 5)) ?></span> <?= e($a['patient_name']) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($cellAppts) > 3): ?>
                            <a href="<?= url('/admin/appointments/calendar?view=day&date=' . $cellDate) ?>" class="block px-1 text-[10px] font-medium text-teal-600 hover:underline dark:text-teal-400">+<?= count($cellAppts) - 3 ?> more</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Legend -->
<div class="mt-4 flex flex-wrap items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400">
    <?php foreach (['pending','confirmed','checked_in','in_consultation','completed','cancelled','no_show'] as $s): ?>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full <?= ['pending'=>'bg-amber-400','confirmed'=>'bg-teal-400','checked_in'=>'bg-navy-400','in_consultation'=>'bg-violet-400','completed'=>'bg-emerald-400','cancelled'=>'bg-slate-400','no_show'=>'bg-rose-400'][$s] ?>"></span><?= e(ucfirst(str_replace('_', ' ', $s))) ?></span>
    <?php endforeach; ?>
</div>
<?php $this->end(); ?>
