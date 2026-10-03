<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Medicine Purchases'; $active = 'pharmacy';
$breadcrumbs = ['Operations' => null, 'Pharmacy' => url('/admin/pharmacy'), 'Purchases' => ''];
$currency = (string) setting('currency', 'BDT');
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Medicine Purchases</h1></div>
    <form method="post" action="<?= url('/admin/pharmacy/purchases') ?>" class="flex items-center gap-2">
        <?= csrf_field() ?>
        <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" class="input !py-1.5 !text-xs w-auto">
        <select name="supplier_id" class="input !py-1.5 !text-xs w-auto"><option value="">No supplier</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>New purchase</button>
    </form>
</div>
<div class="card">
    <?php if ($purchases === []): ?><div class="p-6"><?= $this->insert('components/empty-state', ['icon' => 'truck', 'title' => 'No purchases', 'message' => 'Create a purchase order and receive stock.']) ?></div><?php else: ?>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Code</th><th>Date</th><th>Supplier</th><th class="text-right">Total</th><th>Status</th><th>By</th></tr></thead><tbody>
    <?php foreach ($purchases as $p): ?>
        <tr><td><a href="<?= url('/admin/pharmacy/purchases/' . (int) $p['id']) ?>" class="font-mono text-[12px] text-teal-600 hover:underline"><?= e($p['purchase_code']) ?></a></td>
        <td class="text-[13px] text-slate-500"><?= e(format_date($p['purchase_date'], 'M j, Y')) ?></td>
        <td class="text-[13px]"><?= e($p['supplier_name'] ?? '—') ?></td>
        <td class="text-right font-mono text-[13px]"><?= (float) $p['total_amount'] > 0 ? e(format_money($p['total_amount'], $currency)) : '—' ?></td>
        <td><span class="badge <?= $p['status'] === 'received' ? 'badge-emerald' : ($p['status'] === 'cancelled' ? 'badge-rose' : 'badge-amber') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        <td class="text-[12px] text-slate-400"><?= e($p['created_by_name'] ?? '—') ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php $this->end(); ?>
