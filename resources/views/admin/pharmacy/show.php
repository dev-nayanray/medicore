<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = e($medicine['name']); $active = 'pharmacy';
$breadcrumbs = ['Operations' => null, 'Pharmacy' => url('/admin/pharmacy'), e($medicine['name']) => ''];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><?= e($medicine['name']) ?> <?php if ($medicine['strength']): ?><span class="font-mono text-sm text-slate-400"><?= e($medicine['strength']) ?></span><?php endif; ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= e($medicine['generic_name'] ?? '—') ?> · <?= e(ucfirst($medicine['dosage_form'])) ?> · Stock: <span class="font-mono font-medium <?= (int) $medicine['stock'] < (int) $medicine['reorder_level'] ? 'text-amber-600' : 'text-emerald-600' ?>"><?= (int) $medicine['stock'] ?></span></p></div>
    <a href="<?= url('/admin/pharmacy') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="card"><div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Batches</h2></div>
        <?php if ($medicine['batches'] === []): ?><p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No batches in stock.</p><?php else: ?>
        <div class="overflow-x-auto"><table class="table"><thead><tr><th>Batch</th><th>Expiry</th><th class="text-right">Qty</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($medicine['batches'] as $b): $expired = strtotime((string) $b['expiry_date']) < time(); $expiringSoon = strtotime((string) $b['expiry_date']) < strtotime('+90 days'); ?>
            <tr class="<?= $expired ? 'opacity-50 line-through' : '' ?>"><td class="font-mono text-[12px]"><?= e($b['batch_number']) ?></td>
            <td class="text-[13px] <?= $expired ? 'text-rose-600' : ($expiringSoon ? 'text-amber-600' : 'text-slate-500') ?>"><?= e(format_date($b['expiry_date'], 'M j, Y')) ?></td>
            <td class="text-right font-mono <?= (int) $b['quantity_remaining'] > 0 ? 'text-slate-700 dark:text-slate-200' : 'text-slate-300' ?>"><?= (int) $b['quantity_remaining'] ?></td>
            <td><?php if ($expired): ?><span class="badge badge-rose">Expired</span><?php elseif ($expiringSoon): ?><span class="badge badge-amber">Expiring</span><?php elseif ((int) $b['quantity_remaining'] > 0): ?><span class="badge badge-emerald">Active</span><?php else: ?><span class="badge badge-slate">Empty</span><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
    <div class="card"><div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Stock movements</h2></div>
        <?php if ($medicine['movements'] === []): ?><p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No movements yet.</p><?php else: ?>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800"><?php foreach ($medicine['movements'] as $m): ?>
            <li class="flex items-center justify-between px-5 py-2.5 text-[12.5px]"><span><span class="font-mono <?= (int) $m['quantity'] >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= (int) $m['quantity'] >= 0 ? '+' : '' ?><?= (int) $m['quantity'] ?></span> <span class="text-slate-500"><?= e($m['movement_type']) ?></span></span><span class="text-[11px] text-slate-400">Balance: <?= (int) $m['balance_after'] ?> · <?= e(time_ago($m['created_at'])) ?></span></li>
        <?php endforeach; ?></ul><?php endif; ?>
    </div>
</div>
<?php $this->end(); ?>
