<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Admit Patient'; $active = 'admissions';
$breadcrumbs = ['Operations' => null, 'Admissions' => url('/admin/admissions'), 'Admit' => ''];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Admit Patient</h1><p class="mt-0.5 text-sm text-slate-500">Select patient and allocate a bed.</p></div>
    <a href="<?= url('/admin/admissions') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<form method="post" action="<?= url('/admin/admissions') ?>" class="space-y-4">
    <?= csrf_field() ?>
    <section class="card"><div class="p-5">
        <label class="label">Patient ID <span class="text-rose-500">*</span></label>
        <input type="number" name="patient_id" value="<?= $patientId ?? '' ?>" class="input" required placeholder="Enter patient ID">
    </div></section>
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600"><i data-lucide="bed-double" class="h-4 w-4"></i></span><h2 class="text-sm font-semibold">Bed allocation</h2></div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div><label class="label">Admission type</label><select name="admission_type" class="input"><?php foreach (App\Models\Admission::TYPES as $t): ?><option value="<?= e($t) ?>"><?= e(ucfirst(str_replace('_', ' ', $t))) ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Admission date</label><input type="datetime-local" name="admission_date" value="<?= date('Y-m-d\TH:i') ?>" class="input"></div>
            <div><label class="label">Expected discharge</label><input type="date" name="expected_discharge" class="input"></div>
            <div><label class="label">Allocate bed (available)</label><select name="bed_id" class="input"><option value="">— No bed (admit without bed) —</option><?php foreach ($availableBeds as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['ward_name']) ?> / <?= e($b['room_number']) ?> / <?= e($b['bed_number']) ?> (<?= e(ucfirst(str_replace('_', ' ', $b['room_type']))) ?> · <?= e(format_money($b['daily_rate'], (string) setting('currency', 'BDT'))) ?>/day)</option><?php endforeach; ?></select></div>
        </div>
        <div class="grid grid-cols-1 gap-4 px-5 pb-5 sm:grid-cols-2">
            <div><label class="label">Admission reason</label><textarea name="admission_reason" rows="2" class="input" placeholder="Reason for admission…"></textarea></div>
            <div><label class="label">Diagnosis at admission</label><textarea name="diagnosis_at_admission" rows="2" class="input" placeholder="Provisional diagnosis…"></textarea></div>
        </div>
    </section>
    <button type="submit" class="btn btn-primary"><i data-lucide="door-open" class="h-4 w-4"></i>Admit patient</button>
</form>
<?php $this->end(); ?>
