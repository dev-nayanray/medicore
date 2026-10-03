<?php

declare(strict_types=1);

/**
 * Prescription detail (standalone read-only view).
 * $rx (with items, patient, doctor, consultation joins)
 */

$this->extend('layouts/admin');
$title = 'Prescription ' . $rx['prescription_code'];
$active = 'consultations';
$breadcrumbs = ['Clinical' => null, 'Consultations' => url('/admin/consultations'), $rx['prescription_code'] => ''];

$statusTone = ['draft' => 'badge-amber', 'finalized' => 'badge-emerald', 'dispensed' => 'badge-teal'];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <span class="font-mono"><?= e($rx['prescription_code']) ?></span>
            <span class="badge <?= $statusTone[$rx['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($rx['status'])) ?></span>
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            For <?= e($rx['patient_name']) ?> · Dr. <?= e($rx['doctor_name']) ?> · <?= e(format_date(substr((string) $rx['consultation_date'], 0, 10), 'M j, Y')) ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/consultations/' . (int) $rx['consultation_id']) ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to consultation
        </a>
        <?php if ($rx['status'] !== 'draft'): ?>
            <a href="<?= url('/admin/prescriptions/' . (int) $rx['id'] . '/print') ?>" target="_blank" class="btn btn-primary">
                <i data-lucide="printer" class="h-4 w-4"></i>Print
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Medicines (<?= count($rx['items']) ?>)</h2>
    </div>
    <?php if ($rx['items'] === []): ?>
        <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No medicines on this prescription.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Qty</th>
                    <th>Instructions</th>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($rx['items'] as $i => $item): ?>
                        <tr>
                            <td class="font-mono text-[12px] text-slate-400"><?= (int) $i + 1 ?></td>
                            <td class="font-medium text-slate-800 dark:text-slate-100"><?= e($item['medicine_name']) ?></td>
                            <td class="text-[13px] text-slate-600 dark:text-slate-300"><?= e($item['dosage']) ?></td>
                            <td class="text-[13px] text-slate-600 dark:text-slate-300"><?= e($item['frequency']) ?></td>
                            <td class="text-[13px] text-slate-600 dark:text-slate-300"><?= e($item['duration']) ?></td>
                            <td class="text-[13px] text-slate-600 dark:text-slate-300"><?= $item['quantity'] !== null ? (int) $item['quantity'] : '—' ?></td>
                            <td class="text-[13px] text-slate-500 dark:text-slate-400"><?= e($item['instructions'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php if ($rx['notes']): ?>
        <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Pharmacist notes</p>
            <p class="mt-1 text-[13px] text-slate-600 dark:text-slate-300"><?= e($rx['notes']) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card">
        <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Patient</h2>
        <div class="p-5">
            <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100"><?= e($rx['patient_name']) ?></p>
            <p class="font-mono text-[11px] text-slate-400"><?= e($rx['patient_code']) ?></p>
            <p class="mt-1 text-[12px] text-slate-500"><?= e($rx['gender']) ?> · <?= e($rx['phone'] ?? '') ?></p>
            <?php if (!empty($rx['allergies']) && strtolower(trim((string) $rx['allergies'])) !== 'none known'): ?>
                <p class="mt-2 text-[11px] font-semibold text-rose-600 dark:text-rose-400">⚠ Allergies: <?= e($rx['allergies']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Prescriber</h2>
        <div class="p-5">
            <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100">Dr. <?= e($rx['doctor_name']) ?></p>
            <p class="text-[12px] text-slate-500"><?= e($rx['specialization']) ?></p>
            <p class="text-[11px] text-slate-400">Reg: <?= e($rx['registration_number'] ?? '—') ?></p>
            <p class="text-[11px] text-slate-400"><?= e($rx['department_name'] ?? '') ?></p>
        </div>
    </div>
    <div class="card">
        <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Consultation</h2>
        <div class="p-5">
            <p class="font-mono text-[12px] text-teal-600 dark:text-teal-400"><?= e($rx['consultation_code']) ?></p>
            <p class="mt-1 text-[12px] text-slate-500"><?= e(format_date(substr((string) $rx['consultation_date'], 0, 10), 'M j, Y')) ?></p>
            <?php if ($rx['diagnoses']): ?>
                <p class="mt-2 text-[11px] text-slate-400">Dx: <?= e($rx['diagnoses']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $this->end(); ?>
