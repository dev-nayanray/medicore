<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Lab Order ' . e($order['order_code']); $active = 'laboratory';
$breadcrumbs = ['Operations' => null, 'Laboratory' => url('/admin/laboratory'), e($order['order_code']) => ''];
$statusTone = ['ordered' => 'badge-amber', 'collected' => 'badge-navy', 'resulted' => 'badge-violet', 'verified' => 'badge-teal', 'released' => 'badge-emerald'];
$itemStatusTone = $statusTone;
$currency = (string) setting('currency', 'BDT');
$age = $order['date_of_birth'] !== null ? (int) Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$order['date_of_birth']]) : null;
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><span class="font-mono"><?= e($order['order_code']) ?></span> <span class="badge <?= $statusTone[$order['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($order['status'])) ?></span></h1>
        <p class="mt-1 text-sm text-slate-500"><?= e($order['patient_name']) ?> · <?= e($order['patient_code']) ?> · <?= $order['gender'] ?><?php if ($age): ?> · <?= $age ?> yrs<?php endif; ?><?php if ($order['doctor_name']): ?> · Dr. <?= e($order['doctor_name']) ?><?php endif; ?><?php if ($order['invoice_code']): ?> · Invoice: <span class="font-mono text-teal-600"><?= e($order['invoice_code']) ?></span><?php endif; ?></p></div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if ($order['status'] === 'released'): ?><a href="<?= url('/admin/laboratory/' . (int) $order['id'] . '/print') ?>" target="_blank" class="btn btn-secondary"><i data-lucide="printer" class="h-4 w-4"></i>Print report</a><?php endif; ?>
        <a href="<?= url('/admin/laboratory') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
    </div>
</div>
<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Test results</h2></div>
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($order['items'] as $item):
            $canCollect = $item['status'] === 'ordered';
            $canResult = $item['status'] === 'collected';
            $canVerify = $item['status'] === 'resulted';
            $canRelease = $item['status'] === 'verified';
        ?>
        <div class="px-5 py-4">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-[14px] font-semibold text-slate-800 dark:text-slate-100"><?= e($item['test_name']) ?></p><p class="text-[11px] text-slate-400"><?= e($item['category']) ?><?php if ($item['sample_type']): ?> · Sample: <?= e($item['sample_type']) ?><?php endif; ?> · <?= e(format_money($item['price'], $currency)) ?></p></div>
                <span class="badge <?= $itemStatusTone[$item['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($item['status'])) ?></span>
            </div>
            <?php if ($item['result_value'] !== null): ?>
            <div class="mt-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                <p class="text-[13px]"><span class="text-slate-400">Result:</span> <span class="font-medium <?= (int) $item['is_critical'] === 1 ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100' ?>"><?= e($item['result_value']) ?> <?= e($item['result_unit'] ?? '') ?></span><?php if ($item['reference_range']): ?> <span class="text-slate-400">(Ref: <?= e($item['reference_range']) ?>)</span><?php endif; ?><?php if ((int) $item['is_critical'] === 1): ?> <span class="badge badge-rose ml-1">CRITICAL</span><?php endif; ?></p>
                <?php if ($item['notes']): ?><p class="mt-1 text-[12px] text-slate-500"><?= e($item['notes']) ?></p><?php endif; ?>
                <p class="mt-1 text-[11px] text-slate-400"><?php if ($item['collected_at']): ?>Collected: <?= e(format_date($item['collected_at'], 'M j, g:i A')) ?><?php endif; ?><?php if ($item['resulted_at']): ?> · Resulted: <?= e(format_date($item['resulted_at'], 'M j, g:i A')) ?><?php endif; ?><?php if ($item['verified_at']): ?> · Verified: <?= e(format_date($item['verified_at'], 'M j, g:i A')) ?><?php endif; ?><?php if ($item['released_at']): ?> · Released: <?= e(format_date($item['released_at'], 'M j, g:i A')) ?><?php endif; ?></p>
            </div>
            <?php endif; ?>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <?php if ($canCollect): ?>
                <form method="post" action="<?= url('/admin/laboratory/' . (int) $order['id'] . '/items/' . (int) $item['id'] . '/collect') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary !py-1 !px-2.5 !text-xs"><i data-lucide="test-tube" class="h-3 w-3"></i>Collect sample</button></form>
                <?php elseif ($canResult && can('laboratory.update')): ?>
                <form method="post" action="<?= url('/admin/laboratory/' . (int) $order['id'] . '/items/' . (int) $item['id'] . '/result') ?>" class="flex flex-wrap items-center gap-2"><?= csrf_field() ?>
                    <input type="text" name="result_value" class="input !py-1 !text-xs w-32" placeholder="Result value" required>
                    <input type="text" name="result_unit" class="input !py-1 !text-xs w-16" placeholder="Unit">
                    <input type="text" name="reference_range" class="input !py-1 !text-xs w-24" placeholder="Ref range">
                    <label class="flex items-center gap-1 text-[11px] text-slate-500"><input type="checkbox" name="is_critical" value="1" class="checkbox !static"> Critical</label>
                    <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs"><i data-lucide="save" class="h-3 w-3"></i>Enter</button>
                </form>
                <?php elseif ($canVerify && can('laboratory.approve')): ?>
                <form method="post" action="<?= url('/admin/laboratory/' . (int) $order['id'] . '/items/' . (int) $item['id'] . '/verify') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary !py-1 !px-2.5 !text-xs" data-confirm="Verify result|Confirm verification. You cannot verify your own result."><i data-lucide="check" class="h-3 w-3"></i>Verify</button></form>
                <?php elseif ($canRelease && can('laboratory.approve')): ?>
                <form method="post" action="<?= url('/admin/laboratory/' . (int) $order['id'] . '/items/' . (int) $item['id'] . '/release') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs"><i data-lucide="send" class="h-3 w-3"></i>Release</button></form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $this->end(); ?>
