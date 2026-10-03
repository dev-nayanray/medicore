<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Dispense Medicines'; $active = 'pharmacy';
$breadcrumbs = ['Operations' => null, 'Pharmacy' => url('/admin/pharmacy'), 'Dispense' => ''];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Dispense Medicines</h1>
        <p class="mt-0.5 text-sm text-slate-500">Select medicines and quantities. Stock is deducted using FEFO (first-expiry-first-out).</p></div>
    <a href="<?= url('/admin/pharmacy') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>
<form method="post" action="<?= url('/admin/pharmacy/dispense') ?>" class="space-y-4" x-data="{ rows: [{}] }">
    <?= csrf_field() ?>
    <?php if ($prescription): ?>
    <input type="hidden" name="prescription_id" value="<?= (int) $prescription['id'] ?>">
    <input type="hidden" name="patient_id" value="<?= (int) $prescription['patient_id'] ?>">
    <section class="card"><div class="p-4"><p class="text-sm"><span class="text-slate-400">Prescription:</span> <span class="font-mono text-teal-600"><?= e($prescription['prescription_code']) ?></span></p></div></section>
    <?php elseif ($patientId): ?>
    <input type="hidden" name="patient_id" value="<?= (int) $patientId ?>">
    <?php else: ?>
    <section class="card"><div class="p-5"><label class="label">Patient ID</label><input type="number" name="patient_id" class="input" required placeholder="Enter patient ID"></div></section>
    <?php endif; ?>
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600"><i data-lucide="pill" class="h-4 w-4"></i></span><h2 class="text-sm font-semibold">Medicines</h2></div>
        <div class="p-5 space-y-2">
            <template x-for="(row, i) in rows" :key="i">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <select x-model="row.medicine_id" name="medicine_id[]" class="input col-span-7 !py-1.5 !text-xs" required>
                        <option value="">Select medicine…</option>
                        <?php foreach ($medicines as $m): ?><option value="<?= (int) $m['id'] ?>" data-stock="<?= (int) $m['stock'] ?>"><?= e($m['name']) ?> (<?= e($m['dosage_form']) ?>) — stock: <?= (int) $m['stock'] ?></option><?php endforeach; ?>
                    </select>
                    <input type="number" x-model="row.quantity" name="quantity[]" min="1" placeholder="Qty" class="input col-span-3 !py-1.5 !text-xs" required>
                    <button type="button" @click="rows.splice(i, 1)" x-show="rows.length > 1" class="col-span-2 text-rose-500 text-xs">Remove</button>
                </div>
            </template>
            <button type="button" @click="rows.push({})" class="btn btn-secondary !py-1.5 !text-xs"><i data-lucide="plus" class="h-3.5 w-3.5"></i>Add medicine</button>
        </div>
    </section>
    <div class="card flex flex-col items-center justify-between gap-3 p-5 sm:flex-row">
        <label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="generate_invoice" value="1" checked class="checkbox !static"> Generate invoice for dispensed medicines</label>
        <button type="submit" class="btn btn-primary"><i data-lucide="check" class="h-4 w-4"></i>Dispense</button>
    </div>
</form>
<?php $this->end(); ?>
