<?php

declare(strict_types=1);

/**
 * Financial reports — revenue vs expenses, payment method breakdown,
 * daily collection chart, expense category breakdown.
 * $from, $to, $stats, $expenses, $net, $dailyCollection, $methodBreakdown
 */

$this->extend('layouts/admin');
$title = 'Financial Reports';
$active = 'billing';
$breadcrumbs = ['Finance' => null, 'Reports' => ''];

$currency = (string) setting('currency', 'BDT');
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Financial Reports</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"><?= e(format_date($from, 'M j, Y')) ?> → <?= e(format_date($to, 'M j, Y')) ?></p>
    </div>
    <form method="get" class="flex flex-wrap items-center gap-2">
        <input type="date" name="from" value="<?= e($from) ?>" class="input !py-1.5 !text-xs w-auto">
        <input type="date" name="to" value="<?= e($to) ?>" class="input !py-1.5 !text-xs w-auto">
        <button type="submit" class="btn btn-secondary">Apply</button>
    </form>
</div>

<!-- KPI tiles -->
<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <div class="card flex items-center gap-3 p-4">
        <i data-lucide="receipt-text" class="h-8 w-8 text-navy-600 dark:text-navy-300"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_billed'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Total billed</p></div>
    </div>
    <div class="card flex items-center gap-3 p-4">
        <i data-lucide="banknote" class="h-8 w-8 text-emerald-600 dark:text-emerald-400"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_collected'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Collected</p></div>
    </div>
    <div class="card flex items-center gap-3 p-4">
        <i data-lucide="alert-circle" class="h-8 w-8 text-amber-600 dark:text-amber-400"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($stats['total_outstanding'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Outstanding</p></div>
    </div>
    <div class="card flex items-center gap-3 p-4">
        <i data-lucide="trending-down" class="h-8 w-8 text-rose-500"></i>
        <div><p class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($expenses['total'], $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Expenses</p></div>
    </div>
    <div class="card flex items-center gap-3 p-4">
        <i data-lucide="trending-up" class="h-8 w-8 text-teal-600 dark:text-teal-400"></i>
        <div><p class="text-lg font-bold <?= $net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>"><?= e(format_money($net, $currency)) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Net (P&L)</p></div>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <!-- Daily collection chart -->
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Daily collection — last 14 days</h2>
            <p class="text-xs text-slate-400">All completed payments</p>
        </div>
        <div class="p-5">
            <canvas id="collectionChart" height="100"
                    data-labels="<?= e(json_encode($dailyCollection['labels'])) ?>"
                    data-values="<?= e(json_encode($dailyCollection['data'])) ?>"></canvas>
        </div>
    </div>

    <!-- Expense breakdown -->
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Expenses by category</h2>
            <p class="text-xs text-slate-400">For the selected range</p>
        </div>
        <div class="p-5 space-y-3">
            <?php if (empty($expenses['by_category'])): ?>
                <p class="text-sm text-slate-400 text-center py-4">No expenses in this range.</p>
            <?php else: ?>
                <?php foreach ($expenses['by_category'] as $cat => $amt):
                    $pct = $expenses['total'] > 0 ? round(($amt / $expenses['total']) * 100, 1) : 0;
                ?>
                    <div>
                        <div class="flex items-center justify-between text-[12.5px]">
                            <span class="font-medium capitalize text-slate-700 dark:text-slate-200"><?= e(str_replace('_', ' ', $cat)) ?></span>
                            <span class="text-slate-500"><?= e(format_money($amt, $currency)) ?> · <?= $pct ?>%</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-2 rounded-full bg-rose-400" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment method breakdown -->
    <div class="card xl:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Payment methods breakdown</h2>
            <p class="text-xs text-slate-400">For the selected range</p>
        </div>
        <?php if (empty($methodBreakdown)): ?>
            <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No payments in this range.</p>
        <?php else: ?>
            <div class="grid grid-cols-2 gap-4 p-5 sm:grid-cols-3 lg:grid-cols-6">
                <?php foreach (App\Models\Payment::METHODS as $m): $amt = $methodBreakdown[$m] ?? 0; ?>
                    <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-800/60">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400"><?= e(str_replace('_', ' ', $m)) ?></p>
                        <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100"><?= e(format_money($amt, $currency)) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
<?php $this->end(); ?>
