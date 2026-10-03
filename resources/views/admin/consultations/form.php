<?php

declare(strict_types=1);

/**
 * Consultation create / edit form.
 * $consultation (null=create), $appointment, $patient, $doctor, $doctors, $age (edit only)
 */

$this->extend('layouts/admin');
$isEdit = $consultation !== null;
$title = $isEdit ? 'Edit Consultation' : 'New Consultation';
$active = 'consultations';
$breadcrumbs = ['Clinical' => null, 'Consultations' => url('/admin/consultations'), ($isEdit ? 'Edit' : 'New') => ''];

$v = static fn (string $key) => old($key, (string) ($consultation[$key] ?? ''));
$isFinalized = $isEdit && $consultation['status'] !== 'draft';
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit ' . e($consultation['consultation_code']) : 'New Consultation' ?>
            <?php if ($isEdit): ?>
                <span class="badge <?= $isFinalized ? 'badge-emerald' : 'badge-amber' ?>"><?= e(ucfirst($consultation['status'])) ?></span>
            <?php endif; ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?php if ($isEdit): ?>Update clinical details.<?php else: ?>Record symptoms, vitals, examination, and diagnoses.<?php endif; ?>
        </p>
    </div>
    <a href="<?= url('/admin/consultations') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back
    </a>
</div>

<?php if ($isFinalized): ?>
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
        <p class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
            <i data-lucide="lock" class="h-4 w-4"></i>This consultation is finalized — direct editing is locked.
        </p>
        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Use the amend action on the consultation detail page for corrections. Each amendment is recorded with a reason.</p>
    </div>
<?php endif; ?>

<?php if ($patient): ?>
<!-- Patient summary sidebar (inline on create) -->
<div class="card mb-4">
    <div class="flex items-center gap-4 p-4">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-navy-600/90 text-sm font-bold text-white">
            <?= e(strtoupper(substr((string) ($patient['first_name'] ?? $patient['patient_name'] ?? 'P'), 0, 1))) ?>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-[15px] font-semibold text-slate-800 dark:text-slate-100"><?= e($patient['first_name'] . ' ' . $patient['last_name'] ?? $patient['patient_name'] ?? '') ?></p>
            <p class="font-mono text-[11px] text-slate-400"><?= e($patient['patient_code'] ?? '') ?></p>
        </div>
        <?php if (!empty($patient['allergies']) && strtolower(trim((string) $patient['allergies'])) !== 'none known'): ?>
            <span class="badge badge-rose" title="<?= e($patient['allergies']) ?>"><i data-lucide="triangle-alert" class="h-3 w-3"></i>Allergies</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? url('/admin/consultations/' . (int) $consultation['id']) : url('/admin/consultations') ?>" class="space-y-4">
    <?= csrf_field() ?>

    <?php if (!$isEdit): ?>
    <input type="hidden" name="patient_id" value="<?= (int) ($patient['id'] ?? 0) ?>">
    <input type="hidden" name="doctor_id" value="<?= (int) ($doctor['id'] ?? $doctor) ?>">
    <?php if ($appointment): ?>
    <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- Clinical details -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="clipboard-list" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Clinical presentation</h2>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <label for="chief_complaint" class="label">Chief complaint</label>
                <input type="text" id="chief_complaint" name="chief_complaint" value="<?= $v('chief_complaint') ?>" class="input" placeholder="e.g. Chest pain for 2 days" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="history_presenting" class="label">History of presenting illness</label>
                <textarea id="history_presenting" name="history_presenting" rows="3" class="input" placeholder="Onset, duration, progression, aggravating/relieving factors…" <?= $isFinalized ? 'disabled' : '' ?>><?= $v('history_presenting') ?></textarea>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="symptoms" class="label">Symptoms</label>
                    <textarea id="symptoms" name="symptoms" rows="4" class="input" placeholder="Associated symptoms…" <?= $isFinalized ? 'disabled' : '' ?>><?= $v('symptoms') ?></textarea>
                </div>
                <div>
                    <label for="observations" class="label">Observations / physical exam</label>
                    <textarea id="observations" name="observations" rows="4" class="input" placeholder="Examination findings…" <?= $isFinalized ? 'disabled' : '' ?>><?= $v('observations') ?></textarea>
                </div>
            </div>
            <div>
                <label for="diagnoses" class="label">Diagnoses</label>
                <textarea id="diagnoses" name="diagnoses" rows="2" class="input" placeholder="Primary + differential diagnoses" <?= $isFinalized ? 'disabled' : '' ?>><?= $v('diagnoses') ?></textarea>
            </div>
        </div>
    </section>

    <!-- Vitals -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400"><i data-lucide="activity" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Vital signs</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 p-5 sm:grid-cols-3 lg:grid-cols-5">
            <div>
                <label for="temperature" class="label">Temp (°C)</label>
                <input type="number" step="0.1" id="temperature" name="temperature" value="<?= $v('temperature') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="bp_systolic" class="label">BP systolic</label>
                <input type="number" id="bp_systolic" name="bp_systolic" value="<?= $v('bp_systolic') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="bp_diastolic" class="label">BP diastolic</label>
                <input type="number" id="bp_diastolic" name="bp_diastolic" value="<?= $v('bp_diastolic') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="pulse" class="label">Pulse (bpm)</label>
                <input type="number" id="pulse" name="pulse" value="<?= $v('pulse') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="respiratory_rate" class="label">Resp rate</label>
                <input type="number" id="respiratory_rate" name="respiratory_rate" value="<?= $v('respiratory_rate') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="spo2" class="label">SpO₂ (%)</label>
                <input type="number" id="spo2" name="spo2" value="<?= $v('spo2') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="weight" class="label">Weight (kg)</label>
                <input type="number" step="0.1" id="weight" name="weight" value="<?= $v('weight') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="height" class="label">Height (cm)</label>
                <input type="number" step="0.1" id="height" name="height" value="<?= $v('height') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
            </div>
            <div>
                <label class="label">BMI (auto)</label>
                <input type="text" value="<?= $isEdit && $consultation['bmi'] ? e((string) $consultation['bmi']) : '—' ?>" class="input bg-slate-50 dark:bg-slate-800" disabled>
            </div>
        </div>
    </section>

    <!-- Notes + Plan -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300"><i data-lucide="file-text" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Clinical notes & plan</h2>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <label for="clinical_notes" class="label">Clinical notes</label>
                <textarea id="clinical_notes" name="clinical_notes" rows="5" class="input" placeholder="Assessment, plan, investigations ordered, advice…" <?= $isFinalized ? 'disabled' : '' ?>><?= $v('clinical_notes') ?></textarea>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="follow_up_date" class="label">Follow-up date</label>
                    <input type="date" id="follow_up_date" name="follow_up_date" value="<?= $v('follow_up_date') ?>" min="<?= date('Y-m-d') ?>" class="input" <?= $isFinalized ? 'disabled' : '' ?>>
                </div>
                <div>
                    <label for="referral_to" class="label">Referral to</label>
                    <input type="text" id="referral_to" name="referral_to" value="<?= $v('referral_to') ?>" class="input" placeholder="Specialist / department" <?= $isFinalized ? 'disabled' : '' ?>>
                </div>
            </div>
            <div>
                <label for="referral_reason" class="label">Referral reason</label>
                <textarea id="referral_reason" name="referral_reason" rows="2" class="input" placeholder="Reason for referral…" <?= $isFinalized ? 'disabled' : '' ?>><?= $v('referral_reason') ?></textarea>
            </div>
        </div>
    </section>

    <?php if (!$isFinalized): ?>
    <div class="card flex flex-col items-center justify-between gap-3 p-5 sm:flex-row">
        <p class="flex items-center gap-1.5 text-xs text-slate-400">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Saved as draft — finalize to lock the record and enable prescriptions.
        </p>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i>Save consultation
        </button>
    </div>
    <?php endif; ?>
</form>
<?php $this->end(); ?>
