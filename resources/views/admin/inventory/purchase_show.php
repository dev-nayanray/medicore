<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Inventory Purchase ' . e($purchase['purchase_code']); $active = 'inventory';
$breadcrumbs = ['Operations' => null, 'Inventory' => url('/admin/inventory'), e($purchase['purchase_code']) => ''];
$currency = (string) setting('currency', 'BDT');
$isReceived = $purchase['status'] === 'received';
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><span class="font-mono"><?= e($purchase['purchase_code']) ?></span> <span class="badge <?= $isReceived ? 'badge-emerald' : 'badge-amber' ?>"><?= e(ucfirst($purchase['status'])) ?></span></h1>
        <p class="mt-1 text-sm text-slate-500"><?= e(format_date($purchase['purchase_date'], 'M j, Y')) ?> · <?= e($purchase['supplier_name'] ?? 'No supplier') ?></p></div>
    <a href="<?= url('/admin/inventory') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<?php if (!$isReceived): ?>
<form method="post" action="<?= url('/admin/inventory/purchases/' . (int) $purchase['id'] . '/receive') ?>" class="space-y-4" x-data="{ rows: [{}] }"><?= csrf_field() ?>
    <section class="card"><div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600"><i data-lucide="truck" class="h-4 w-4"></i></span><h2 class="text-sm font-semibold">Receive items</h2></div>
        <div class="p-5 space-y-2"><template x-for="(row, i) in rows" :key="i">
            <div class="grid grid-cols-12 gap-2 items-center">
                <select x-model="row.item_id" name="item_id[]" class="input col-span-6 !py-1.5 !text-xs" required><option value="">Select item…</option><?php foreach ($inventoryItems as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?> (stock: <?= (int) $i['current_stock'] ?>)</option><?php endforeach; ?></select>
                <input type="number" x-model="row.quantity" name="quantity[]" min="1" placeholder="Qty" class="input col-span-2 !py-1.5 !text-xs" required>
                <input type="number" step="0.01" x-model="row.unit_cost" name="unit_cost[]" placeholder="Cost" class="input col-span-3 !py-1.5 !text-xs" required>
                <button type="button" @click="rows.splice(i, 1)" x-show="rows.length > 1" class="col-span-1 text-rose-500 text-xs">X</button>
            </div></template>
            <button type="button" @click="rows.push({})" class="btn btn-secondary !py-1.5 !text-xs"><i data-lucide="plus" class="h-3.5 w-3.5"></i>Add line</button>
        </div></section>
    <button type="submit" class="btn btn-primary"><i data-lucide="check" class="h-4 w-4"></i>Receive stock</button>
</form>
<?php else: ?>
<div class="card"><div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Received items</h2></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Cost</th><th class="text-right">Total</th></tr></thead><tbody>
    <?php foreach ($purchase['items'] as $item): ?>
        <tr><td class="font-medium"><?= e($item['item_name']) ?></td><td class="text-right font-mono"><?= (int) $item['quantity'] ?></td><td class="text-right font-mono"><?= e(format_money($item['unit_cost'], $currency)) ?></td><td class="text-right font-mono font-medium"><?= e(format_money($item['line_total'], $currency)) ?></td></tr>
    <?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
<?php $this->end(); ?>
