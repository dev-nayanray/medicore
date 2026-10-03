<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'New Lab Order'; $active = 'laboratory';
$breadcrumbs = ['Operations' => null, 'Laboratory' => url('/admin/laboratory'), 'New' => ''];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">New Lab Order</h1><p class="mt-0.5 text-sm text-slate-500">Select tests — an invoice is created automatically on submit.</p></div>
    <a href="<?= url('/admin/laboratory') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<form method="post" action="<?= url('/admin/laboratory') ?>" class="space-y-4">
    <?= csrf_field() ?>
    <section class="card"><div class="p-5">
        <?php if ($patientId): ?><input type="hidden" name="patient_id" value="<?= (int) $patientId ?>"><?php else: ?>
        <label class="label">Patient ID <span class="text-rose-500">*</span></label><input type="number" name="patient_id" class="input" required placeholder="Enter patient ID"><?php endif; ?>
        <?php if ($consultationId): ?><input type="hidden" name="consultation_id" value="<?= (int) $consultationId ?>"><?php endif; ?>
        <input type="hidden" name="doctor_id" value="">
        <textarea name="notes" rows="2" class="input mt-3" placeholder="Order notes (optional)"></textarea>
    </div></section>
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600"><i data-lucide="flask-conical" class="h-4 w-4"></i></span><h2 class="text-sm font-semibold">Select tests</h2></div>
        <div class="grid grid-cols-1 gap-2 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($tests as $category => $items): foreach ($items as $t): ?>
                <label class="flex items-center gap-2 rounded-lg border border-slate-100 p-2.5 text-[13px] hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                    <input type="checkbox" name="test_ids[]" value="<?= (int) $t['id'] ?>" class="checkbox !static">
                    <div><p class="font-medium text-slate-800 dark:text-slate-100"><?= e($t['name']) ?></p><p class="text-[11px] text-slate-400"><?= e($category) ?> · <?= e(format_money($t['price'], (string) setting('currency', 'BDT'))) ?><?php if ($t['sample_type']): ?> · <?= e($t['sample_type']) ?><?php endif; ?></p></div>
                </label>
            <?php endforeach; endforeach; ?>
        </div>
    </section>
    <button type="submit" class="btn btn-primary"><i data-lucide="save" class="h-4 w-4"></i>Create order + invoice</button>
</form>
<?php $this->end(); ?>
