<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Reports'; $active = 'reports';
$breadcrumbs = ['Finance' => null, 'Reports' => ''];
$currency = (string) setting('currency', 'BDT');
$reportTypes = ['overview' => 'Overview', 'patients' => 'Patient Registrations', 'appointments' => 'Appointments', 'revenue' => 'Revenue (Invoices)', 'pharmacy' => 'Pharmacy Dispensing', 'laboratory' => 'Laboratory Tests', 'admissions' => 'Admissions'];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Reports & Analytics</h1>
        <p class="mt-0.5 text-sm text-slate-500"><?= e(format_date($from, 'M j, Y')) ?> → <?= e(format_date($to, 'M j, Y')) ?></p></div>
    <a href="<?= url('/admin/reports/export?' . http_build_query(['from' => $from, 'to' => $to, 'type' => $type])) ?>" class="btn btn-primary"><i data-lucide="download" class="h-4 w-4"></i>Export CSV</a>
</div>
<div class="card mb-4">
    <form method="get" action="<?= url('/admin/reports') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <select name="type" class="input w-auto text-sm" onchange="this.form.submit()"><?php foreach ($reportTypes as $val => $label): ?><option value="<?= e($val) ?>" <?= $type === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= e($from) ?>" class="input w-auto text-sm">
        <input type="date" name="to" value="<?= e($to) ?>" class="input w-auto text-sm">
        <button type="submit" class="btn btn-secondary">Generate</button>
    </form>
</div>
<?php if ($type === 'overview' && !empty($data['summary'])): ?>
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
    <?php foreach ($data['summary'] as $label => $value):
        $display = is_float($value) ? format_money($value, $currency) : (string) $value; ?>
    <div class="card p-4"><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= e($display) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400"><?= e(ucfirst(str_replace('_', ' ', $label))) ?></p></div>
    <?php endforeach; ?>
</div>
<?php elseif (!empty($data['rows'])): ?>
<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold"><?= e($data['title'] ?? 'Report') ?></h2><p class="text-xs text-slate-400"><?= count($data['rows']) ?> records</p></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><?php foreach (array_keys($data['rows'][0]) as $col): ?><th><?= e(ucfirst(str_replace('_', ' ', $col))) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($data['rows'] as $row): ?><tr><?php foreach ($row as $val): ?><td class="text-[12.5px] text-slate-600 dark:text-slate-300"><?= e((string) ($val ?? '—')) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php else: ?>
<div class="card"><div class="p-6"><?= $this->insert('components/empty-state', ['icon' => 'chart-bar', 'title' => 'No data', 'message' => 'No records match the selected range and report type.']) ?></div></div>
<?php endif; ?>
<?php $this->end(); ?>
