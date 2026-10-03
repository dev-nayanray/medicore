<?php

declare(strict_types=1);

/**
 * Appointment reports — daily / weekly / monthly volume, status breakdown,
 * no-show rate, doctor leaderboard, 14-day volume chart.
 * $from, $to, $report, $noShow, $volume
 */

$this->extend('layouts/admin');
$title = 'Appointment Reports';
$active = 'appointments';
$breadcrumbs = ['Hospital' => null, 'Appointments' => url('/admin/appointments'), 'Reports' => ''];

$statusTone = [
    'pending' => 'badge-amber', 'confirmed' => 'badge-teal', 'checked_in' => 'badge-navy',
    'in_consultation' => 'badge-violet', 'completed' => 'badge-emerald',
    'cancelled' => 'badge-slate', 'no_show' => 'badge-rose',
];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Reports</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= e(format_date($from, 'M j, Y')) ?> → <?= e(format_date($to, 'M j, Y')) ?> · <?= (int) $report['total'] ?> appointment<?= $report['total'] !== 1 ? 's' : '' ?>
        </p>
    </div>
    <form method="get" class="flex flex-wrap items-center gap-2">
        <input type="date" name="from" value="<?= e($from) ?>" class="input !py-1.5 !text-xs w-auto">
        <input type="date" name="to" value="<?= e($to) ?>" class="input !py-1.5 !text-xs w-auto">
        <button type="submit" class="btn btn-secondary">Apply</button>
    </form>
</div>

<!-- KPI cards -->
<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card flex items-center gap-4 p-5">
        <span class="grid h-12 w-12 place-items-center rounded-xl bg-navy-500/10 text-navy-600 dark:text-navy-300"><i data-lucide="calendar-days" class="h-6 w-6"></i></span>
        <div><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= (int) $report['total'] ?></p><p class="text-[11px] uppercase tracking-wide text-slate-400">Total appointments</p></div>
    </div>
    <div class="card flex items-center gap-4 p-5">
        <span class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><i data-lucide="check-check" class="h-6 w-6"></i></span>
        <div><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= (int) ($report['by_status']['completed'] ?? 0) ?></p><p class="text-[11px] uppercase tracking-wide text-slate-400">Completed</p></div>
    </div>
    <div class="card flex items-center gap-4 p-5">
        <span class="grid h-12 w-12 place-items-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400"><i data-lucide="user-x" class="h-6 w-6"></i></span>
        <div><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= e(number_format($noShow['rate'], 1)) ?>%</p><p class="text-[11px] uppercase tracking-wide text-slate-400">No-show rate (<?= (int) $noShow['no_show'] ?> / <?= (int) $noShow['total'] ?>)</p></div>
    </div>
</div>

<!-- Volume chart -->
<div class="card mb-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Daily volume — last 14 days</h2>
        <p class="text-xs text-slate-400">Excludes cancelled & no-show</p>
    </div>
    <div class="p-5">
        <canvas id="appointmentsChart" height="120"
                data-labels="<?= e(json_encode($volume['labels'])) ?>"
                data-values="<?= e(json_encode($volume['data'])) ?>"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <!-- Status breakdown -->
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">By status</h2>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach (App\Models\Appointment::STATUSES as $s): $count = (int) ($report['by_status'][$s] ?? 0); ?>
                <li class="flex items-center justify-between px-5 py-3 text-[13px]">
                    <span class="flex items-center gap-2"><span class="badge <?= $statusTone[$s] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $s))) ?></span></span>
                    <span class="font-medium text-slate-700 dark:text-slate-200"><?= $count ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Type breakdown -->
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">By type</h2>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach (App\Models\Appointment::TYPES as $t): $count = (int) ($report['by_type'][$t] ?? 0); ?>
                <li class="flex items-center justify-between px-5 py-3 text-[13px]">
                    <span><?= e(ucfirst(str_replace('_', ' ', $t))) ?></span>
                    <span class="font-medium text-slate-700 dark:text-slate-200"><?= $count ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Doctor leaderboard -->
    <div class="card xl:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Top doctors by volume</h2>
            <p class="text-xs text-slate-400">Excludes cancelled & no-show</p>
        </div>
        <?php if ($report['by_doctor'] === []): ?>
            <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No appointments in this range.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($report['by_doctor'] as $row): ?>
                    <li class="flex items-center justify-between px-5 py-3 text-[13px]">
                        <span class="flex items-center gap-3">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-navy-600/90 text-[10px] font-semibold text-white"><?= e(strtoupper(substr((string) $row['name'], 0, 1))) ?></span>
                            <span class="font-medium text-slate-800 dark:text-slate-100"><?= e($row['name']) ?></span>
                            <span class="font-mono text-[11px] text-slate-400"><?= e($row['doctor_code']) ?></span>
                        </span>
                        <span class="font-bold text-teal-600 dark:text-teal-400"><?= (int) $row['count'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
<?php $this->end(); ?>
