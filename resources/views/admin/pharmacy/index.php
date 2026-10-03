<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Pharmacy'; $active = 'pharmacy';
$breadcrumbs = ['Operations' => null, 'Pharmacy' => ''];
$currency = (string) setting('currency', 'BDT');
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Pharmacy</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"><?= (int) $counts['total'] ?> medicines · <?= (int) $counts['low_stock'] ?> low stock · <?= (int) $counts['expiring'] ?> expiring soon</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/pharmacy/dispense') ?>" class="btn btn-primary"><i data-lucide="pill" class="h-4 w-4"></i>Dispense</a>
        <a href="<?= url('/admin/pharmacy/purchases') ?>" class="btn btn-secondary"><i data-lucide="truck" class="h-4 w-4"></i>Purchases</a>
    </div>
</div>
<?php if ($filter === 'low_stock' && $lowStock): ?>
<div class="card mb-4"><div class="border-b border-amber-200 bg-amber-50 px-5 py-3 dark:border-amber-700/60 dark:bg-amber-950/30"><p class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-400"><i data-lucide="alert-triangle" class="h-4 w-4"></i>Low stock — <?= count($lowStock) ?> medicines need restocking</p></div></div>
<?php elseif ($filter === 'expiring' && $expiring): ?>
<div class="card mb-4"><div class="border-b border-rose-200 bg-rose-50 px-5 py-3 dark:border-rose-700/60 dark:bg-rose-950/30"><p class="flex items-center gap-2 text-sm font-semibold text-rose-700 dark:text-rose-400"><i data-lucide="calendar-x" class="h-4 w-4"></i>Expiring batches — <?= count($expiring) ?> batches expire within 90 days</p></div></div>
<?php endif; ?>
<div class="card">
    <form method="get" action="<?= url('/admin/pharmacy') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i><input type="text" name="q" value="<?= e($search) ?>" placeholder="Search medicines…" class="input pl-9 text-sm"></div>
        <select name="filter" class="input w-auto text-sm" onchange="this.form.submit()">
            <option value="">All medicines</option>
            <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low stock</option>
            <option value="expiring" <?= $filter === 'expiring' ? 'selected' : '' ?>>Expiring soon</option>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>
    <?php if ($medicines === []): ?>
    <div class="p-6"><?= $this->insert('components/empty-state', ['icon' => 'pill', 'title' => 'No medicines', 'message' => 'Add medicines to the catalogue or receive a purchase.']) ?></div>
    <?php else: ?>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Name</th><th>Generic</th><th>Form</th><th>Stock</th><th>Reorder</th><th>Status</th></tr></thead><tbody>
    <?php foreach (($filter === 'low_stock' ? $lowStock : $medicines) as $m): ?>
        <tr><td><a href="<?= url('/admin/pharmacy/' . (int) $m['id']) ?>" class="font-medium text-slate-800 hover:underline dark:text-slate-100"><?= e($m['name']) ?></a><?php if ($m['strength']): ?><span class="font-mono text-[11px] text-slate-400"> · <?= e($m['strength']) ?></span><?php endif; ?></td>
        <td class="text-[13px] text-slate-500"><?= e($m['generic_name'] ?? '—') ?></td><td class="text-[13px] text-slate-500"><?= e(ucfirst($m['dosage_form'])) ?></td>
        <td class="font-mono <?= (int) ($m['stock'] ?? 0) < (int) $m['reorder_level'] ? 'text-amber-600 font-medium' : 'text-slate-600' ?>"><?= (int) ($m['stock'] ?? 0) ?></td>
        <td class="text-[12px] text-slate-400"><?= (int) $m['reorder_level'] ?></td>
        <td><?php if ((int) ($m['stock'] ?? 0) === 0): ?><span class="badge badge-rose">Out</span><?php elseif ((int) ($m['stock'] ?? 0) < (int) $m['reorder_level']): ?><span class="badge badge-amber">Low</span><?php else: ?><span class="badge badge-emerald">OK</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
