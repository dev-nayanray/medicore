<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Inventory'; $active = 'inventory';
$breadcrumbs = ['Operations' => null, 'Inventory' => ''];
$currency = (string) setting('currency', 'BDT');
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Inventory</h1>
        <p class="mt-0.5 text-sm text-slate-500"><?= (int) $counts['total'] ?> items · <?= (int) $counts['low_stock'] ?> low stock · valuation: <span class="font-mono"><?= e(format_money($counts['value'], $currency)) ?></span></p></div>
    <div class="flex items-center gap-2">
        <form method="post" action="<?= url('/inventory/purchases') ?>" class="flex items-center gap-2"><?= csrf_field() ?><input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" class="input !py-1.5 !text-xs w-auto"><select name="supplier_id" class="input !py-1.5 !text-xs w-auto"><option value="">No supplier</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-secondary"><i data-lucide="truck" class="h-4 w-4"></i>New purchase</button></form>
    </div>
</div>
<?php if (!empty($pendingAdjustments)): ?>
<div class="card mb-4"><div class="border-b border-amber-200 bg-amber-50 px-5 py-3 dark:border-amber-700/60 dark:bg-amber-950/30"><p class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-400"><i data-lucide="alert-triangle" class="h-4 w-4"></i><?= count($pendingAdjustments) ?> adjustment(s) pending approval</p></div>
<ul class="divide-y divide-slate-100 dark:divide-slate-800"><?php foreach ($pendingAdjustments as $a): ?>
    <li class="flex items-center justify-between px-5 py-3 text-[13px]"><span><span class="font-medium"><?= e($a['item_name']) ?></span> · <span class="<?= $a['adjustment_type'] === 'increase' ? 'text-emerald-600' : 'text-rose-600' ?>"><?= e($a['adjustment_type']) ?> <?= (int) $a['quantity'] ?></span> · <?= e($a['reason']) ?> · by <?= e($a['requested_by_name'] ?? '—') ?></span>
    <div class="flex gap-1">
        <form method="post" action="<?= url('/admin/inventory/adjustments/' . (int) $a['id'] . '/approve') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary !py-1 !px-2 !text-xs" data-confirm="Approve|Apply this stock adjustment?">Approve</button></form>
        <form method="post" action="<?= url('/admin/inventory/adjustments/' . (int) $a['id'] . '/reject') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary !py-1 !px-2 !text-xs hover:!text-rose-600">Reject</button></form>
    </div></li>
<?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="card">
    <form method="get" action="<?= url('/admin/inventory') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800"><div class="relative w-full max-w-xs sm:flex-1"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i><input type="text" name="q" value="<?= e($search) ?>" placeholder="Search items…" class="input pl-9 text-sm"></div><button type="submit" class="btn btn-secondary">Filter</button></form>
    <?php if ($items === []): ?><div class="p-6"><?= $this->insert('components/empty-state', ['icon' => 'boxes', 'title' => 'No inventory items', 'message' => 'Add items or receive a purchase.']) ?></div>
    <?php else: ?>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Name</th><th>Category</th><th>SKU</th><th>Unit</th><th class="text-right">Stock</th><th>Reorder</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?>
        <tr><td><a href="<?= url('/admin/inventory/' . (int) $i['id']) ?>" class="font-medium text-slate-800 hover:underline dark:text-slate-100"><?= e($i['name']) ?></a></td><td class="text-[13px] text-slate-500"><?= e($i['category_name'] ?? '—') ?></td><td class="font-mono text-[12px] text-slate-400"><?= e($i['sku'] ?? '—') ?></td><td class="text-[13px] text-slate-500"><?= e($i['unit']) ?></td><td class="text-right font-mono <?= (int) $i['current_stock'] < (int) $i['reorder_level'] ? 'text-amber-600 font-medium' : 'text-slate-600' ?>"><?= (int) $i['current_stock'] ?></td><td class="text-[12px] text-slate-400"><?= (int) $i['reorder_level'] ?></td><td><?php if ((int) $i['current_stock'] === 0): ?><span class="badge badge-rose">Out</span><?php elseif ((int) $i['current_stock'] < (int) $i['reorder_level']): ?><span class="badge badge-amber">Low</span><?php else: ?><span class="badge badge-emerald">OK</span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</div>
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Request stock adjustment</h2></div>
    <form method="post" action="<?= url('/admin/inventory/adjustments') ?>" class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-4"><?= csrf_field() ?>
        <select name="item_id" class="input" required><option value="">Select item…</option><?php foreach ($items as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?> (stock: <?= (int) $i['current_stock'] ?>)</option><?php endforeach; ?></select>
        <select name="adjustment_type" class="input"><option value="increase">Increase</option><option value="decrease">Decrease</option></select>
        <input type="number" name="quantity" min="1" class="input" placeholder="Quantity" required>
        <input type="text" name="reason" class="input" placeholder="Reason" required>
        <button type="submit" class="btn btn-secondary sm:col-span-4"><i data-lucide="send" class="h-4 w-4"></i>Request adjustment</button>
    </form>
</div>
<?php $this->end(); ?>
