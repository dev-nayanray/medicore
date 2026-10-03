<?php

declare(strict_types=1);

/**
 * Billing dashboard — revenue tiles, charts, outstanding balance.
 * $stats, $counts, $monthlyRevenue, $dailyCollection, $expenseTotals, $methodBreakdown
 */

$this->extend('layouts/admin');
$title = 'Financial Dashboard';
$active = 'billing';
$breadcrumbs = ['Finance' => null, 'Dashboard' => ''];

$currency = (string) setting('currency', 'BDT');
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Financial Dashboard</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            This month: <?= e(format_money($stats['total_collected'], $currency)) ?> collected · <?= e(format_money($stats['total_outstanding'], $currency)) ?> outstanding
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/billing') ?>" class="btn btn-secondary"><i data-lucide="receipt-text" class="h-4 w-4"></i><span class="hidden sm:inline">Invoices</span></a>
        <a href="<?= url('/admin/billing/reports') ?>" class="btn btn-secondary"><i data-lucide="chart-pie" class="h-4 w-4"></i><span class="hidden sm:inline">Reports</span></a>
        <?php if (can('billing.create')): ?>
            <a href="<?= url('/admin/billing/create') ?>" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>New invoice</a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI tiles -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="banknote" class="h-7 w-7 text-teal-600 dark:text-teal-400"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_collected'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Collected (month)</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="receipt-text" class="h-7 w-7 text-navy-600 dark:text-navy-300"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_billed'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Billed (month)</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="alert-circle" class="h-7 w-7 text-amber-600 dark:text-amber-400"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_outstanding'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Outstanding</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="undo-2" class="h-7 w-7 text-rose-600 dark:text-rose-400"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_refunds'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Refunds</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="trending-down" class="h-7 w-7 text-rose-500"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($expenseTotals['total'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Expenses (month)</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="trending-up" class="h-7 w-7 text-emerald-600 dark:text-emerald-400"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_collected'] - $expenseTotals['total'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Net (month)</p></div>
    </div>
</div>

<!-- Charts -->
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div><h2 class="text-sm font-semibold">Revenue — last 12 months</h2><p class="text-xs text-slate-400">Collected amounts per month</p></div>
            <span class="badge badge-slate">Chart.js</span>
        </div>
        <div class="p-5">
            <canvas id="revenueChart" height="132"
                    data-labels="<?= e(json_encode($monthlyRevenue['labels'])) ?>"
                    data-values="<?= e(json_encode($monthlyRevenue['data'])) ?>"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Payment methods (month)</h2></div>
        <div class="p-5 space-y-3">
            <?php if (empty($methodBreakdown)): ?>
                <p class="text-sm text-slate-400 text-center py-4">No payments this month.</p>
            <?php else: ?>
                <?php foreach ($methodBreakdown as $method => $amount):
                    $pct = $stats['total_collected'] > 0 ? round(($amount / $stats['total_collected']) * 100, 1) : 0;
                ?>
                    <div>
                        <div class="flex items-center justify-between text-[12.5px]">
                            <span class="font-medium text-slate-700 dark:text-slate-200"><?= e(ucfirst(str_replace('_', ' ', $method))) ?></span>
                            <span class="text-slate-500"><?= e(format_money($amount, $currency)) ?> · <?= $pct ?>%</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-2 rounded-full bg-teal-500" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Daily collection -->
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Daily collection — last 14 days</h2>
        <p class="text-xs text-slate-400">All completed payments</p>
    </div>
    <div class="p-5">
        <canvas id="collectionChart" height="80"
                data-labels="<?= e(json_encode($dailyCollection['labels'])) ?>"
                data-values="<?= e(json_encode($dailyCollection['data'])) ?>"></canvas>
    </div>
</div>

<script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
<?php $this->end(); ?>
