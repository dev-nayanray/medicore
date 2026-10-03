<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Purchase ' . e($purchase['purchase_code']); $active = 'pharmacy';
$breadcrumbs = ['Operations' => null, 'Pharmacy' => url('/admin/pharmacy/purchases'), e($purchase['purchase_code']) => ''];
$currency = (string) setting('currency', 'BDT');
$isReceived = $purchase['status'] === 'received';
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><span class="font-mono"><?= e($purchase['purchase_code']) ?></span> <span class="badge <?= $isReceived ? 'badge-emerald' : 'badge-amber' ?>"><?= e(ucfirst($purchase['status'])) ?></span></h1>
        <p class="mt-1 text-sm text-slate-500"><?= e(format_date($purchase['purchase_date'], 'M j, Y')) ?> · <?= e($purchase['supplier_name'] ?? 'No supplier') ?><?php if ($purchase['total_amount'] > 0): ?> · Total: <span class="font-mono font-medium"><?= e(format_money($purchase['total_amount'], $currency)) ?></span><?php endif; ?></p></div>
    <a href="<?= url('/admin/pharmacy/purchases') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<?php if (!$isReceived): ?>
<form method="post" action="<?= url('/admin/pharmacy/purchases/' . (int) $purchase['id'] . '/receive') ?>" class="space-y-4" x-data="{ rows: [{}] }">
    <?= csrf_field() ?>
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600"><i data-lucide="truck" class="h-4 w-4"></i></span><h2 class="text-sm font-semibold">Receive items</h2></div>
        <div class="p-5 space-y-2">
            <template x-for="(row, i) in rows" :key="i">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <select x-model="row.medicine_id" name="medicine_id[]" class="input col-span-4 !py-1.5 !text-xs" required><option value="">Select medicine…</option><?php foreach ($medicines as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
                    <input type="text" x-model="row.batch_number" name="batch_number[]" placeholder="Batch no." class="input col-span-2 !py-1.5 !text-xs" required>
                    <input type="date" x-model="row.expiry_date" name="expiry_date[]" class="input col-span-2 !py-1.5 !text-xs" required>
                    <input type="number" x-model="row.quantity" name="quantity[]" min="1" placeholder="Qty" class="input col-span-1 !py-1.5 !text-xs" required>
                    <input type="number" step="0.01" x-model="row.unit_cost" name="unit_cost[]" placeholder="Cost" class="input col-span-2 !py-1.5 !text-xs" required>
                    <button type="button" @click="rows.splice(i, 1)" x-show="rows.length > 1" class="col-span-1 text-rose-500 text-xs">X</button>
                </div>
            </template>
            <button type="button" @click="rows.push({})" class="btn btn-secondary !py-1.5 !text-xs"><i data-lucide="plus" class="h-3.5 w-3.5"></i>Add line</button>
        </div>
    </section>
    <button type="submit" class="btn btn-primary"><i data-lucide="check" class="h-4 w-4"></i>Receive & create batches</button>
</form>
<?php else: ?>
<div class="card"><div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Received items</h2></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-right">Qty</th><th class="text-right">Cost</th><th class="text-right">Total</th></tr></thead><tbody>
    <?php foreach ($purchase['items'] as $item): ?>
        <tr><td class="font-medium text-slate-800 dark:text-slate-100"><?= e($item['medicine_name'] ?? '—') ?></td><td class="font-mono text-[12px]"><?= e($item['batch_number']) ?></td><td class="text-[13px]"><?= e(format_date($item['expiry_date'], 'M j, Y')) ?></td><td class="text-right font-mono"><?= (int) $item['quantity'] ?></td><td class="text-right font-mono"><?= e(format_money($item['unit_cost'], $currency)) ?></td><td class="text-right font-mono font-medium"><?= e(format_money($item['line_total'], $currency)) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>
<?php $this->end(); ?>
