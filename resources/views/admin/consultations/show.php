<?php

declare(strict_types=1);

/**
 * Consultation detail — patient sidebar, clinical sections, prescription
 * builder, attachments, amendments, status timeline.
 * $consultation, $age, $patientHistory
 */

$this->extend('layouts/admin');
$title = 'Consultation ' . $consultation['consultation_code'];
$active = 'consultations';
$breadcrumbs = ['Clinical' => null, 'Consultations' => url('/admin/consultations'), $consultation['consultation_code'] => ''];

$statusTone = ['draft' => 'badge-amber', 'finalized' => 'badge-emerald', 'amended' => 'badge-violet'];
$isDraft = $consultation['status'] === 'draft';
$isFinalized = !$isDraft;
$canFinalize = can('consultations.finalize');
$canAmend = can('consultations.amend');
$canEditRx = can('prescriptions.create') || can('prescriptions.update');
$rx = $consultation['prescription'];
$rxIsDraft = $rx !== null && $rx['status'] === 'draft';
$rxIsFinalized = $rx !== null && $rx['status'] === 'finalized';
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <span class="font-mono"><?= e($consultation['consultation_code']) ?></span>
            <span class="badge <?= $statusTone[$consultation['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($consultation['status'])) ?></span>
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            <?= e(format_date(substr((string) $consultation['consultation_date'], 0, 10), 'M j, Y')) ?> at <?= e(substr((string) $consultation['consultation_date'], 11, 5)) ?>
            · Dr. <?= e($consultation['doctor_name']) ?> · <?= e($consultation['department_name'] ?? '—') ?>
            <?php if ($consultation['appointment_code']): ?>· from appointment <span class="font-mono"><?= e($consultation['appointment_code']) ?></span><?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if ($isDraft && can('consultations.update')): ?>
            <a href="<?= url('/admin/consultations/' . (int) $consultation['id'] . '/edit') ?>" class="btn btn-secondary">
                <i data-lucide="pencil" class="h-4 w-4"></i>Edit
            </a>
        <?php endif; ?>
        <?php if ($isDraft && $canFinalize): ?>
            <form method="post" action="<?= url('/admin/consultations/' . (int) $consultation['id'] . '/finalize') ?>"
                  data-confirm="Finalize consultation|This locks the record. Corrections will require a formal amendment with a reason.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><i data-lucide="lock" class="h-4 w-4"></i>Finalize</button>
            </form>
        <?php endif; ?>
        <?php if ($rxIsFinalized): ?>
            <a href="<?= url('/admin/prescriptions/' . (int) $rx['id'] . '/print') ?>" target="_blank" class="btn btn-secondary">
                <i data-lucide="printer" class="h-4 w-4"></i>Print Rx
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
    <!-- Patient sidebar -->
    <div class="space-y-4 lg:col-span-1">
        <div class="card">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Patient</h2>
            <div class="p-5">
                <a href="<?= url('/admin/patients/' . (int) $consultation['patient_id']) ?>" class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-navy-600/90 text-[11px] font-semibold text-white">
                        <?= e(strtoupper(substr((string) $consultation['patient_name'], 0, 1))) ?>
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-[13.5px] font-medium text-slate-800 hover:underline dark:text-slate-100"><?= e($consultation['patient_name']) ?></p>
                        <p class="font-mono text-[11px] text-slate-400"><?= e($consultation['patient_code']) ?></p>
                    </div>
                </a>
                <dl class="mt-4 space-y-2 text-[13px]">
                    <div class="flex justify-between"><dt class="text-slate-400">Age</dt><dd><?= (int) $age ?> yrs · <?= e(ucfirst((string) $consultation['gender'])) ?></dd></div>
                    <?php if ($consultation['blood_group']): ?>
                    <div class="flex justify-between"><dt class="text-slate-400">Blood</dt><dd><span class="badge badge-rose"><?= e($consultation['blood_group']) ?></span></dd></div>
                    <?php endif; ?>
                    <div class="flex justify-between"><dt class="text-slate-400">Phone</dt><dd class="font-mono text-slate-700 dark:text-slate-200"><?= e($consultation['patient_phone']) ?></dd></div>
                </dl>
                <?php if (!empty($consultation['allergies']) && strtolower(trim((string) $consultation['allergies'])) !== 'none known'): ?>
                    <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2.5 dark:border-rose-900/60 dark:bg-rose-950/30">
                        <p class="flex items-center gap-1.5 text-[11px] font-semibold text-rose-700 dark:text-rose-400"><i data-lucide="triangle-alert" class="h-3 w-3"></i>Allergies</p>
                        <p class="mt-1 text-[12px] text-rose-700 dark:text-rose-300"><?= e($consultation['allergies']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vitals summary -->
        <?php if ($consultation['temperature'] !== null || $consultation['bp_systolic'] !== null || $consultation['pulse'] !== null): ?>
        <div class="card">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Vitals</h2>
            <dl class="grid grid-cols-2 gap-3 p-4 text-[12px]">
                <?php
                $vitals = [
                    'Temp' => $consultation['temperature'] !== null ? $consultation['temperature'] . ' °C' : null,
                    'BP' => $consultation['bp_systolic'] !== null ? $consultation['bp_systolic'] . '/' . $consultation['bp_diastolic'] : null,
                    'Pulse' => $consultation['pulse'] !== null ? $consultation['pulse'] . ' bpm' : null,
                    'Resp' => $consultation['respiratory_rate'] !== null ? $consultation['respiratory_rate'] . '/min' : null,
                    'SpO₂' => $consultation['spo2'] !== null ? $consultation['spo2'] . '%' : null,
                    'Weight' => $consultation['weight'] !== null ? $consultation['weight'] . ' kg' : null,
                    'Height' => $consultation['height'] !== null ? $consultation['height'] . ' cm' : null,
                    'BMI' => $consultation['bmi'] !== null ? $consultation['bmi'] : null,
                ];
                foreach ($vitals as $label => $value):
                    if ($value !== null): ?>
                    <div><dt class="text-slate-400"><?= e($label) ?></dt><dd class="font-medium text-slate-700 dark:text-slate-200"><?= e((string) $value) ?></dd></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php endif; ?>

        <!-- Patient history -->
        <div class="card">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Recent history</h2>
            <?php if (count($patientHistory) <= 1): ?>
                <p class="px-5 py-4 text-[12px] text-slate-400">No prior consultations.</p>
            <?php else: ?>
                <ol class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach (array_slice($patientHistory, 0, 5) as $h): ?>
                        <?php if ((int) $h['id'] === (int) $consultation['id']) continue; ?>
                        <li class="px-5 py-2.5">
                            <a href="<?= url('/admin/consultations/' . (int) $h['id']) ?>" class="block text-[12px]">
                                <span class="font-medium text-slate-700 hover:underline dark:text-slate-200"><?= e(substr((string) $h['consultation_date'], 0, 10)) ?></span>
                                <span class="text-slate-400">· <?= e($h['doctor_name'] ?? '—') ?></span>
                                <span class="block truncate text-[11px] text-slate-400"><?= e($h['chief_complaint'] ?? '—') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main column -->
    <div class="space-y-4 lg:col-span-3">
        <!-- Clinical sections -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Clinical record</h2>
            </div>
            <dl class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php
                $sections = [
                    'Chief complaint' => 'chief_complaint',
                    'History of presenting illness' => 'history_presenting',
                    'Symptoms' => 'symptoms',
                    'Observations / exam' => 'observations',
                    'Diagnoses' => 'diagnoses',
                    'Clinical notes' => 'clinical_notes',
                ];
                foreach ($sections as $label => $field):
                    $val = trim((string) ($consultation[$field] ?? ''));
                ?>
                    <div class="px-5 py-3.5">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400"><?= e($label) ?></dt>
                        <dd class="mt-1 whitespace-pre-line text-[13px] leading-relaxed text-slate-700 dark:text-slate-200"><?= $val !== '' ? e($val) : '—' ?></dd>
                    </div>
                <?php endforeach; ?>
                <?php if ($consultation['follow_up_date']): ?>
                    <div class="px-5 py-3.5">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Follow-up</dt>
                        <dd class="mt-1 text-[13px] text-teal-600 dark:text-teal-400">Review on <?= e(format_date($consultation['follow_up_date'], 'l, M j, Y')) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($consultation['referral_to']): ?>
                    <div class="px-5 py-3.5">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Referral</dt>
                        <dd class="mt-1 text-[13px] text-slate-700 dark:text-slate-200">To <?= e($consultation['referral_to']) ?><?php if ($consultation['referral_reason']): ?> — <?= e($consultation['referral_reason']) ?><?php endif; ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Prescription builder -->
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Prescription</h2>
                <?php if ($rx): ?>
                    <span class="badge <?= $rxIsDraft ? 'badge-amber' : 'badge-emerald' ?>"><?= e(ucfirst($rx['status'])) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($rx === null): ?>
                <?php if ($isFinalized && can('prescriptions.create')): ?>
                    <?php include_prescription_form(null, [], '', url('/admin/consultations/' . (int) $consultation['id'] . '/prescriptions'), csrf_field()); ?>
                <?php elseif ($isDraft): ?>
                    <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">Finalize the consultation before creating a prescription.</p>
                <?php else: ?>
                    <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No prescription on this consultation.</p>
                <?php endif; ?>
            <?php elseif ($rxIsDraft && can('prescriptions.update')): ?>
                <?php
                $items = array_map(static fn($i) => [
                    'medicine_name' => $i['medicine_name'], 'dosage' => $i['dosage'], 'frequency' => $i['frequency'],
                    'duration' => $i['duration'], 'quantity' => $i['quantity'] ?? '', 'instructions' => $i['instructions'] ?? '',
                ], $rx['items']);
                include_prescription_form($rx, $items, (string) ($rx['notes'] ?? ''), url('/admin/consultations/' . (int) $consultation['id'] . '/prescriptions/' . (int) $rx['id']), csrf_field());
                ?>
            <?php else: ?>
                <!-- Finalized prescription (read-only) -->
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($rx['items'] as $item): ?>
                        <li class="flex items-start gap-3 px-5 py-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400"><i data-lucide="pill" class="h-4 w-4"></i></span>
                            <div class="flex-1">
                                <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100"><?= e($item['medicine_name']) ?></p>
                                <p class="text-[12px] text-slate-500 dark:text-slate-400">
                                    <?= e($item['dosage']) ?> · <?= e($item['frequency']) ?> · <?= e($item['duration']) ?>
                                    <?php if ($item['quantity']): ?> · qty <?= (int) $item['quantity'] ?><?php endif; ?>
                                    <?php if ($item['instructions']): ?> · <?= e($item['instructions']) ?><?php endif; ?>
                                </p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($rx['notes']): ?>
                    <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Notes</p>
                        <p class="mt-1 text-[12.5px] text-slate-600 dark:text-slate-300"><?= e($rx['notes']) ?></p>
                    </div>
                <?php endif; ?>
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    <span class="font-mono text-[11px] text-slate-400"><?= e($rx['prescription_code']) ?></span>
                    <?php if (can('prescriptions.finalize') && $rxIsDraft): ?>
                        <form method="post" action="<?= url('/admin/consultations/' . (int) $consultation['id'] . '/prescriptions/' . (int) $rx['id'] . '/finalize') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary !py-1.5 !text-xs"><i data-lucide="lock" class="h-3.5 w-3.5"></i>Finalize Rx</button>
                        </form>
                    <?php elseif ($rxIsFinalized): ?>
                        <a href="<?= url('/admin/prescriptions/' . (int) $rx['id'] . '/print') ?>" target="_blank" class="btn btn-secondary !py-1.5 !text-xs"><i data-lucide="printer" class="h-3.5 w-3.5"></i>Print</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Attachments -->
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Attachments</h2>
                <span class="badge badge-slate"><?= count($consultation['attachments']) ?></span>
            </div>
            <?php if ($consultation['attachments'] === []): ?>
                <p class="px-5 py-4 text-[12.5px] text-slate-400">No clinical attachments.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($consultation['attachments'] as $att): ?>
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg <?= $att['mime_type'] === 'application/pdf' ? 'bg-rose-500/10 text-rose-600' : 'bg-teal-500/10 text-teal-600' ?>">
                                <i data-lucide="<?= $att['mime_type'] === 'application/pdf' ? 'file-text' : 'image' ?>" class="h-4 w-4"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-medium text-slate-800 dark:text-slate-100"><?= e($att['title']) ?></p>
                                <p class="text-[11px] text-slate-400"><?= e($att['original_name']) ?> · <?= e(number_format((int) $att['size_bytes'] / 1024, 0)) ?> KB</p>
                            </div>
                            <a href="<?= url('/admin/consultations/' . (int) $consultation['id'] . '/attachments/' . (int) $att['id']) ?>" target="_blank" class="icon-btn !p-1.5" title="View"><i data-lucide="eye" class="h-3.5 w-3.5"></i></a>
                            <?php if (!$isFinalized && can('consultations.update')): ?>
                                <form method="post" action="<?= url('/admin/consultations/' . (int) $consultation['id'] . '/attachments/' . (int) $att['id'] . '/delete') ?>" data-confirm="Delete attachment|Delete this file permanently?|danger">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn hover:!text-rose-600 !p-1.5" title="Delete"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!$isFinalized && can('consultations.update')): ?>
                <form method="post" action="<?= url('/admin/consultations/' . (int) $consultation['id'] . '/attachments') ?>" enctype="multipart/form-data" class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <?= csrf_field() ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" name="title" class="input flex-1 !py-1.5 !text-xs" placeholder="Attachment title (optional)">
                        <input type="file" name="attachment" class="input !py-1 !text-xs w-auto" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        <button type="submit" class="btn btn-secondary !py-1.5 !text-xs"><i data-lucide="upload" class="h-3.5 w-3.5"></i>Upload</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Amendment history -->
        <?php if ($consultation['amendments'] !== []): ?>
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Amendment history</h2>
            </div>
            <ol class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($consultation['amendments'] as $am): ?>
                    <li class="px-5 py-3">
                        <p class="text-[12.5px] font-medium text-slate-800 dark:text-slate-100">
                            <?= e(str_replace('_', ' ', ucfirst($am['field_name']))) ?>:
                            <span class="text-rose-500 line-through"><?= e((string) ($am['old_value'] ?? '—')) ?></span>
                            →
                            <span class="text-emerald-600 dark:text-emerald-400"><?= e((string) ($am['new_value'] ?? '—')) ?></span>
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-400">Reason: <?= e($am['reason']) ?> · by <?= e($am['amended_by_name']) ?> · <?= e(format_date($am['created_at'], 'M j, Y — g:i A')) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>

        <!-- Amend form (finalized consultations only) -->
        <?php if ($isFinalized && $canAmend): ?>
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Record an amendment</h2>
                <p class="text-xs text-slate-400">Corrections to finalized records are auditable</p>
            </div>
            <form method="post" action="<?= url('/admin/consultations/' . (int) $consultation['id'] . '/amend') ?>" class="space-y-3 p-5">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label for="amend_field" class="label">Field</label>
                        <select id="amend_field" name="field" class="input">
                            <optgroup label="Clinical">
                                <option value="chief_complaint">Chief complaint</option>
                                <option value="history_presenting">History presenting</option>
                                <option value="symptoms">Symptoms</option>
                                <option value="observations">Observations</option>
                                <option value="clinical_notes">Clinical notes</option>
                                <option value="diagnoses">Diagnoses</option>
                            </optgroup>
                            <optgroup label="Plan">
                                <option value="follow_up_date">Follow-up date</option>
                                <option value="referral_to">Referral to</option>
                                <option value="referral_reason">Referral reason</option>
                            </optgroup>
                            <optgroup label="Vitals">
                                <option value="temperature">Temperature</option>
                                <option value="bp_systolic">BP systolic</option>
                                <option value="bp_diastolic">BP diastolic</option>
                                <option value="pulse">Pulse</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="amend_value" class="label">New value</label>
                        <input type="text" id="amend_value" name="value" class="input" required>
                    </div>
                </div>
                <div>
                    <label for="amend_reason" class="label">Reason <span class="text-rose-500">*</span></label>
                    <input type="text" id="amend_reason" name="reason" class="input" placeholder="e.g. Typo in diagnosis — corrected after reviewing lab report" required>
                </div>
                <button type="submit" class="btn btn-secondary"><i data-lucide="edit" class="h-4 w-4"></i>Record amendment</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * Helper: render the prescription builder form (inline PHP function).
 */
function include_prescription_form(?array $rx, array $items, string $notes, string $action, string $csrf): void
{
    $rows = $items === [] ? [['medicine_name' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'quantity' => '', 'instructions' => '']] : $items;
    ?>
    <form method="post" action="<?= e($action) ?>" x-data="{ rows: <?= e(json_encode($rows, JSON_HEX_APOS | JSON_HEX_QUOT)) ?> }" class="p-5 space-y-3">
        <?= $csrf ?>
        <template x-for="(row, i) in rows" :key="i">
            <div class="grid grid-cols-12 gap-2 items-center">
                <input type="text" x-model="row.medicine_name" name="medicine_name[]" placeholder="Medicine name" class="input col-span-4 !py-1.5 !text-xs" required>
                <input type="text" x-model="row.dosage" name="dosage[]" placeholder="500mg" class="input col-span-2 !py-1.5 !text-xs" required>
                <input type="text" x-model="row.frequency" name="frequency[]" placeholder="BD" class="input col-span-2 !py-1.5 !text-xs" required>
                <input type="text" x-model="row.duration" name="duration[]" placeholder="7 days" class="input col-span-2 !py-1.5 !text-xs" required>
                <input type="text" x-model="row.instructions" name="instructions[]" placeholder="After meals" class="input col-span-2 !py-1.5 !text-xs">
                <button type="button" @click="rows.splice(i, 1)" class="text-rose-500 hover:text-rose-700 p-1" x-show="rows.length > 1"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
        </template>
        <div class="flex items-center justify-between">
            <button type="button" @click="rows.push({medicine_name:'',dosage:'',frequency:'',duration:'',instructions:''})" class="btn btn-secondary !py-1.5 !text-xs">
                <i data-lucide="plus" class="h-3.5 w-3.5"></i>Add medicine
            </button>
            <button type="submit" class="btn btn-primary !py-1.5 !text-xs">
                <i data-lucide="save" class="h-3.5 w-3.5"></i>Save prescription
            </button>
        </div>
        <input type="text" name="rx_notes" value="<?= e($notes) ?>" class="input !py-1.5 !text-xs" placeholder="Pharmacist notes (optional)">
    </form>
    <?php
}
?>

<?php $this->end(); ?>
