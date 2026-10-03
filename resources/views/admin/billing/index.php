<?php

declare(strict_types=1);

/**
 * Invoice directory — advanced filters, sortable, pagination, export.
 * $invoices, $total, $page, $pages, $perPage, $filters, $counts, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Invoices';
$active = 'billing';
$breadcrumbs = ['Finance' => null, 'Invoices' => ''];

$currency = (string) setting('currency', 'BDT');
$statusTone = ['draft' => 'badge-slate', 'sent' => 'badge-amber', 'partially_paid' => 'badge-navy', 'paid' => 'badge-emerald', 'cancelled' => 'badge-rose', 'refunded' => 'badge-violet'];
$exportQuery = http_build_query(array_filter($filters));
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Invoices</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $counts['total'] ?> total · <?= (int) $counts['paid'] ?> paid · <?= (int) $counts['partial'] ?> partial · <?= (int) $counts['outstanding'] ?> with outstanding balance
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/billing/dashboard') ?>" class="btn btn-secondary"><i data-lucide="layout-dashboard" class="h-4 w-4"></i><span class="hidden sm:inline">Dashboard</span></a>
        <a href="<?= url('/admin/billing/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>" class="btn btn-secondary"><i data-lucide="download" class="h-4 w-4"></i><span class="hidden sm:inline">Export</span></a>
        <?php if (can('billing.create')): ?>
            <a href="<?= url('/admin/billing/create') ?>" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>New invoice</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= url('/admin/billing') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search invoice code, patient…" class="input pl-9 text-sm">
        </div>
        <select name="status" class="input w-auto text-sm">
            <option value="">All statuses</option>
            <?php foreach (App\Models\Invoice::STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="input w-auto text-sm" title="From">
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="input w-auto text-sm" title="To">
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <?php if ($invoices === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $filters['search'] !== '' ? 'search-x' : 'receipt-text',
                'title'   => $filters['search'] !== '' ? 'No invoices match your filters' : 'No invoices yet',
                'message' => $filters['search'] !== '' ? 'Try widening the filters.' : 'Create the first invoice to get started.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Patient</th>
                    <th>Date</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Paid</th>
                    <th class="text-right">Balance</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr class="<?= in_array($inv['status'], ['cancelled'], true) ? 'opacity-60' : '' ?>">
                        <td>
                            <a href="<?= url('/admin/billing/' . (int) $inv['id']) ?>" class="font-mono text-[12px] font-medium text-teal-700 hover:underline dark:text-teal-400">
                                <?= e($inv['invoice_code']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="text-[13px] font-medium text-slate-800 dark:text-slate-100"><?= e($inv['patient_name']) ?></span>
                            <span class="font-mono text-[11px] text-slate-400"><?= e($inv['patient_code']) ?></span>
                        </td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400"><?= e(format_date($inv['invoice_date'], 'M j, Y')) ?></td>
                        <td class="text-right font-mono text-[13px] font-medium text-slate-700 dark:text-slate-200"><?= e(format_money($inv['total'], $currency)) ?></td>
                        <td class="text-right font-mono text-[13px] text-emerald-600 dark:text-emerald-400"><?= e(format_money($inv['paid_amount'], $currency)) ?></td>
                        <td class="text-right font-mono text-[13px] <?= (float) $inv['balance_due'] > 0 ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-slate-400' ?>"><?= e(format_money($inv['balance_due'], $currency)) ?></td>
                        <td><span class="badge <?= $statusTone[$inv['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $inv['status']))) ?></span></td>
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
