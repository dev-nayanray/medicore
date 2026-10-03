<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = e($item['name']); $active = 'inventory';
$breadcrumbs = ['Operations' => null, 'Inventory' => url('/admin/inventory'), e($item['name']) => ''];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><?= e($item['name']) ?></h1>
        <p class="mt-1 text-sm text-slate-500">SKU: <span class="font-mono"><?= e($item['sku'] ?? '—') ?></span> · Unit: <?= e($item['unit']) ?> · Current stock: <span class="font-mono font-medium <?= (int) $item['current_stock'] < (int) $item['reorder_level'] ? 'text-amber-600' : 'text-emerald-600' ?>"><?= (int) $item['current_stock'] ?></span></p></div>
    <a href="<?= url('/admin/inventory') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Stock movement history</h2></div>
    <?php if ($item['movements'] === []): ?><p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No movements yet.</p>
    <?php else: ?>
    <ul class="divide-y divide-slate-100 dark:divide-slate-800"><?php foreach ($item['movements'] as $m): ?>
        <li class="flex items-center justify-between px-5 py-2.5 text-[12.5px]"><span><span class="font-mono <?= (int) $m['quantity'] >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= (int) $m['quantity'] >= 0 ? '+' : '' ?><?= (int) $m['quantity'] ?></span> <span class="text-slate-500"><?= e($m['movement_type']) ?></span><?php if ($m['notes']): ?> · <?= e($m['notes']) ?><?php endif; ?></span><span class="text-[11px] text-slate-400">Balance: <?= (int) $m['balance_after'] ?> · <?= e(time_ago($m['created_at'])) ?></span></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
