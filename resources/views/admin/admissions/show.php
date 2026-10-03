<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Admission ' . e($admission['admission_code']); $active = 'admissions';
$breadcrumbs = ['Operations' => null, 'Admissions' => url('/admin/admissions'), e($admission['admission_code']) => ''];
$statusTone = ['admitted' => 'badge-teal', 'discharged' => 'badge-slate', 'transferred_out' => 'badge-navy'];
$isAdmitted = $admission['status'] === 'admitted';
$currency = (string) setting('currency', 'BDT');
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><span class="font-mono"><?= e($admission['admission_code']) ?></span> <span class="badge <?= $statusTone[$admission['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $admission['status']))) ?></span></h1>
        <p class="mt-1 text-sm text-slate-500"><a href="<?= url('/admin/patients/' . (int) $admission['patient_id']) ?>" class="text-teal-600 hover:underline"><?= e($admission['patient_name']) ?></a> · <?= e($admission['patient_code']) ?> · Admitted <?= e(format_date(substr((string) $admission['admission_date'], 0, 10), 'M j, Y')) ?><?php if ($admission['doctor_name']): ?> · Dr. <?= e($admission['doctor_name']) ?><?php endif; ?><?php if ($admission['ward_name']): ?> · <?= e($admission['ward_name']) ?> / <?= e($admission['room_number'] ?? '—') ?> / <?= e($admission['bed_number'] ?? '—') ?><?php endif; ?></p></div>
    <a href="<?= url('/admin/admissions') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Admission details</h2></div>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800">
            <div class="px-5 py-3"><dt class="text-[11px] uppercase tracking-wide text-slate-400">Reason</dt><dd class="mt-0.5 text-[13px] text-slate-700 dark:text-slate-200"><?= e($admission['admission_reason'] ?? '—') ?></dd></div>
            <div class="px-5 py-3"><dt class="text-[11px] uppercase tracking-wide text-slate-400">Diagnosis at admission</dt><dd class="mt-0.5 text-[13px] text-slate-700 dark:text-slate-200"><?= e($admission['diagnosis_at_admission'] ?? '—') ?></dd></div>
            <?php if ($admission['expected_discharge']): ?><div class="px-5 py-3"><dt class="text-[11px] uppercase tracking-wide text-slate-400">Expected discharge</dt><dd class="mt-0.5 text-[13px] text-slate-700 dark:text-slate-200"><?= e(format_date($admission['expected_discharge'], 'M j, Y')) ?></dd></div><?php endif; ?>
            <?php if ($admission['actual_discharge_date']): ?>
            <div class="px-5 py-3"><dt class="text-[11px] uppercase tracking-wide text-slate-400">Discharged at</dt><dd class="mt-0.5 text-[13px] text-slate-700 dark:text-slate-200"><?= e(format_date($admission['actual_discharge_date'], 'M j, Y — g:i A')) ?> by <?= e($admission['discharged_by_name'] ?? '—') ?></dd></div>
            <div class="px-5 py-3"><dt class="text-[11px] uppercase tracking-wide text-slate-400">Discharge diagnosis</dt><dd class="mt-0.5 text-[13px] text-slate-700 dark:text-slate-200"><?= e($admission['discharge_diagnosis'] ?? '—') ?></dd></div>
            <div class="px-5 py-3"><dt class="text-[11px] uppercase tracking-wide text-slate-400">Discharge summary</dt><dd class="mt-0.5 text-[13px] text-slate-700 dark:text-slate-200 whitespace-pre-line"><?= e($admission['discharge_summary'] ?? '—') ?></dd></div>
            <div class="px-5 py-3"><dt class="text-[11px] uppercase tracking-wide text-slate-400">Discharge instructions</dt><dd class="mt-0.5 text-[13px] text-slate-700 dark:text-slate-200 whitespace-pre-line"><?= e($admission['discharge_instructions'] ?? '—') ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>
    <div class="space-y-4">
        <?php if ($isAdmitted && can('admissions.update') && !empty($availableBeds)): ?>
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Transfer bed</h2></div>
            <form method="post" action="<?= url('/admin/admissions/' . (int) $admission['id'] . '/transfer') ?>" class="space-y-3 p-5" data-confirm="Transfer bed|Move patient to a new bed?"><?= csrf_field() ?>
                <select name="to_bed_id" class="input" required><option value="">Select available bed…</option><?php foreach ($availableBeds as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['ward_name']) ?> / <?= e($b['room_number']) ?> / <?= e($b['bed_number']) ?></option><?php endforeach; ?></select>
                <input type="text" name="reason" class="input" placeholder="Reason (optional)">
                <button type="submit" class="btn btn-primary w-full justify-center"><i data-lucide="arrow-right-left" class="h-4 w-4"></i>Transfer</button>
            </form>
        </div>
        <?php endif; ?>
        <?php if ($isAdmitted && can('admissions.update')): ?>
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold text-rose-600">Discharge patient</h2></div>
            <form method="post" action="<?= url('/admin/admissions/' . (int) $admission['id'] . '/discharge') ?>" class="space-y-3 p-5" data-confirm="Discharge patient|This will free the bed (marked for cleaning)."><?= csrf_field() ?>
                <div><label class="label">Discharge diagnosis</label><input type="text" name="discharge_diagnosis" class="input"></div>
                <div><label class="label">Discharge summary</label><textarea name="discharge_summary" rows="3" class="input"></textarea></div>
                <div><label class="label">Discharge instructions</label><textarea name="discharge_instructions" rows="2" class="input"></textarea></div>
                <button type="submit" class="btn btn-danger w-full justify-center"><i data-lucide="door-closed" class="h-4 w-4"></i>Discharge</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($admission['transfers']): ?>
<div class="card mt-4"><div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Transfer history</h2></div>
    <ul class="divide-y divide-slate-100 dark:divide-slate-800"><?php foreach ($admission['transfers'] as $t): ?>
        <li class="px-5 py-3 text-[12.5px]"><span class="font-mono"><?= e($t['from_bed'] ?? '—') ?></span> → <span class="font-mono"><?= e($t['to_bed'] ?? '—') ?></span> · <?= e(format_date($t['transfer_date'], 'M j, Y — g:i A')) ?> by <?= e($t['transferred_by_name'] ?? '—') ?><?php if ($t['reason']): ?> · <?= e($t['reason']) ?><?php endif; ?></li>
    <?php endforeach; ?></ul></div>
<?php endif; ?>
<?php $this->end(); ?>
