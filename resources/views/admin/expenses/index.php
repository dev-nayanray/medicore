<?php

declare(strict_types=1);

/**
 * Expenses directory — list with filters, add/edit form.
 * $expenses, $total, $page, $pages, $perPage, $filters, $totals, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Expenses';
$active = 'billing';
$breadcrumbs = ['Finance' => null, 'Expenses' => ''];

$currency = (string) setting('currency', 'BDT');
$categoryLabels = ['salaries' => 'Salaries', 'utilities' => 'Utilities', 'supplies' => 'Medical supplies', 'maintenance' => 'Maintenance', 'equipment' => 'Equipment', 'rent' => 'Rent', 'other' => 'Other'];
$categoryTone = ['salaries' => 'badge-navy', 'utilities' => 'badge-amber', 'supplies' => 'badge-teal', 'maintenance' => 'badge-slate', 'equipment' => 'badge-violet', 'rent' => 'badge-rose', 'other' => 'badge-slate'];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Expenses</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">This month: <?= e(format_money($totals['total'], $currency)) ?></p>
    </div>
    <?php if (can('expenses.create')): ?>
        <button @click="$dispatch('open-expense-modal')" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Record expense</button>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= url('/admin/expenses') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search code, description, paid_to…" class="input pl-9 text-sm">
        </div>
        <select name="category" class="input w-auto text-sm">
            <option value="">All categories</option>
            <?php foreach (App\Models\Expense::CATEGORIES as $c): ?>
                <option value="<?= e($c) ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= e($categoryLabels[$c] ?? ucfirst($c)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="input w-auto text-sm">
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="input w-auto text-sm">
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <?php if ($expenses === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', ['icon' => 'receipt', 'title' => 'No expenses recorded', 'message' => 'Record your first expense to start tracking operational spending.']) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Code</th><th>Date</th><th>Category</th><th>Description</th><th>Paid to</th><th>Method</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td class="font-mono text-[12px] text-teal-600 dark:text-teal-400"><?= e($e['expense_code']) ?></td>
                        <td class="whitespace-nowrap text-[12.5px] text-slate-500"><?= e(format_date($e['expense_date'], 'M j, Y')) ?></td>
                        <td><span class="badge <?= $categoryTone[$e['category']] ?? 'badge-slate' ?>"><?= e($categoryLabels[$e['category']] ?? ucfirst($e['category'])) ?></span></td>
                        <td class="text-[13px] text-slate-700 dark:text-slate-200"><?= e($e['description']) ?></td>
                        <td class="text-[12.5px] text-slate-500"><?= e($e['paid_to'] ?? '—') ?></td>
                        <td class="text-[12.5px] capitalize text-slate-500"><?= e(str_replace('_', ' ', $e['payment_method'])) ?></td>
                        <td class="text-right font-mono font-medium text-rose-600 dark:text-rose-400"><?= e(format_money($e['amount'], $currency)) ?></td>
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

<!-- Add expense modal -->
<div x-data="{ open: false }" @open-expense-modal.window="open = true" x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Record expense</h3>
        <form method="post" action="<?= url('/admin/expenses') ?>" class="mt-4 space-y-3">
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Category</label>
                    <select name="category" class="input">
                        <?php foreach (App\Models\Expense::CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?= e($categoryLabels[$c] ?? ucfirst($c)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="label">Date</label><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" class="input" required></div>
            </div>
            <div><label class="label">Description <span class="text-rose-500">*</span></label><input type="text" name="description" class="input" required></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Amount <?= e($currency) ?> <span class="text-rose-500">*</span></label><input type="number" step="0.01" min="0" name="amount" class="input" required></div>
                <div><label class="label">Paid to</label><input type="text" name="paid_to" class="input" placeholder="Vendor / payee"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Method</label>
                    <select name="payment_method" class="input">
                        <?php foreach (App\Models\Expense::METHODS as $m): ?><option value="<?= e($m) ?>"><?= e(ucfirst(str_replace('_', ' ', $m))) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="label">Reference</label><input type="text" name="reference_number" class="input" placeholder="Check / txn no."></div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save" class="h-4 w-4"></i>Record</button>
            </div>
        </form>
    </div>
</div>
<?php $this->end(); ?>
