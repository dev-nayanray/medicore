<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Laboratory'; $active = 'laboratory';
$breadcrumbs = ['Operations' => null, 'Laboratory' => ''];
$statusTone = ['ordered' => 'badge-amber', 'collected' => 'badge-navy', 'resulted' => 'badge-violet', 'verified' => 'badge-teal', 'released' => 'badge-emerald', 'cancelled' => 'badge-rose'];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Laboratory Work Queue</h1>
        <p class="mt-0.5 text-sm text-slate-500"><?= (int) $counts['pending'] ?> pending · <?= (int) $counts['verified'] ?> awaiting release · <?= (int) $counts['critical'] ?> critical alerts<?php if ($counts['today'] > 0): ?> · <?= (int) $counts['today'] ?> ordered today<?php endif; ?></p></div>
    <a href="<?= url('/admin/laboratory/create') ?>" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>New order</a>
</div>
<?php if (!empty($criticalAlerts)): ?>
<div class="card mb-4"><div class="border-b border-rose-200 bg-rose-50 px-5 py-3 dark:border-rose-700/60 dark:bg-rose-950/30"><p class="flex items-center gap-2 text-sm font-semibold text-rose-700 dark:text-rose-400"><i data-lucide="alert-octagon" class="h-4 w-4"></i>Critical results — <?= count($criticalAlerts) ?> pending acknowledgement</p></div>
    <ul class="divide-y divide-slate-100 dark:divide-slate-800"><?php foreach ($criticalAlerts as $alert): ?>
        <li class="flex items-center justify-between px-5 py-3"><div><p class="text-[13px] font-medium text-slate-800 dark:text-slate-100"><?= e($alert['test_name']) ?></p><p class="text-[12px] text-slate-400">Result: <?= e($alert['result_value']) ?><?= $alert['reference_range'] ? ' (Ref: ' . e($alert['reference_range']) . ')' : '' ?></p></div><span class="badge badge-rose">Pending</span></li>
    <?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="card">
    <form method="get" action="<?= url('/admin/laboratory') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <select name="status" class="input w-auto text-sm" onchange="this.form.submit()">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All active</option>
            <?php foreach (['ordered','collected','resulted','verified','released'] as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
        </select>
    </form>
    <?php if ($orders === []): ?><div class="p-6"><?= $this->insert('components/empty-state', ['icon' => 'flask-conical', 'title' => 'No orders', 'message' => 'Create a lab order to start the work queue.']) ?></div>
    <?php else: ?>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Code</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?>
        <tr><td><a href="<?= url('/admin/laboratory/' . (int) $o['id']) ?>" class="font-mono text-[12px] text-teal-600 hover:underline"><?= e($o['order_code']) ?></a></td>
        <td class="text-[13px] font-medium"><?= e($o['patient_name']) ?> <span class="font-mono text-[11px] text-slate-400"><?= e($o['patient_code']) ?></span></td>
        <td class="text-[13px] text-slate-500"><?= e($o['doctor_name'] ?? '—') ?></td>
        <td class="text-[13px] text-slate-500"><?= e(format_date($o['created_at'], 'M j, Y')) ?></td>
        <td><span class="badge <?= $statusTone[$o['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($o['status'])) ?></span></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
