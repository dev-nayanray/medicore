<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Dispensing ' . e($dsp['dispensing_code']); $active = 'pharmacy';
$breadcrumbs = ['Operations' => null, 'Pharmacy' => url('/admin/pharmacy'), e($dsp['dispensing_code']) => ''];
$currency = (string) setting('currency', 'BDT');
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><span class="font-mono"><?= e($dsp['dispensing_code']) ?></span></h1>
        <p class="mt-1 text-sm text-slate-500"><?= e($dsp['patient_name']) ?> · <?= e(format_date($dsp['created_at'], 'M j, Y — g:i A')) ?> · by <?= e($dsp['dispensed_by_name'] ?? 'system') ?><?php if ($dsp['invoice_code']): ?> · Invoice: <span class="font-mono text-teal-600"><?= e($dsp['invoice_code']) ?></span><?php endif; ?></p></div>
    <a href="<?= url('/admin/pharmacy') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Dispensed items</h2></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Medicine</th><th>Qty</th><th class="text-right">Price</th><th class="text-right">Total</th><th>Returned</th><th>Instructions</th></tr></thead><tbody>
    <?php foreach ($dsp['items'] as $item): ?>
        <tr><td class="font-medium text-slate-800 dark:text-slate-100"><?= e($item['medicine_name']) ?></td>
        <td class="font-mono"><?= (int) $item['quantity_dispensed'] ?></td>
        <td class="text-right font-mono"><?= e(format_money($item['unit_price'], $currency)) ?></td>
        <td class="text-right font-mono font-medium"><?= e(format_money((float) $item['unit_price'] * (int) $item['quantity_dispensed'], $currency)) ?></td>
        <td class="text-[12px]"><?= (int) $item['quantity_returned'] > 0 ? '<span class="badge badge-amber">' . (int) $item['quantity_returned'] . '</span>' : '—' ?></td>
        <td class="text-[12px] text-slate-400"><?= e($item['instructions'] ?? '—') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<?php if ($dsp['status'] !== 'returned'): ?>
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold text-rose-600">Return medicine</h2></div>
    <form method="post" action="<?= url('/admin/pharmacy/dispensing/' . (int) $dsp['id'] . '/return') ?>" class="space-y-3 p-5" data-confirm="Return medicine|This will restore stock to the batch.">
        <?= csrf_field() ?>
        <div class="grid grid-cols-3 gap-3">
            <div><label class="label">Item</label><select name="item_id" class="input" required><?php foreach ($dsp['items'] as $i): $avail = (int) $i['quantity_dispensed'] - (int) $i['quantity_returned']; if ($avail > 0): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['medicine_name']) ?> (avail: <?= $avail ?>)</option><?php endif; ?><?php endforeach; ?></select></div>
            <div><label class="label">Quantity</label><input type="number" name="quantity" min="1" class="input" required></div>
            <div><label class="label">Reason</label><input type="text" name="reason" class="input" required placeholder="Reason…"></div>
        </div>
        <button type="submit" class="btn btn-danger"><i data-lucide="undo-2" class="h-4 w-4"></i>Process return</button>
    </form>
</div>
<?php endif; ?>
<?php $this->end(); ?>
