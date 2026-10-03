<?php

declare(strict_types=1);

/**
 * Patient profile — tabs for overview, visits (timeline), documents,
 * prescriptions / lab reports / invoices (future modules → empty states).
 *
 * $patient, $age, $visits, $documents, $doctorOptions, $documentTypes, $visitTypes, $tab
 */

$this->extend('layouts/admin');
$title = $patient['first_name'] . ' ' . $patient['last_name'];
$active = 'patients';
$breadcrumbs = ['Hospital' => null, 'Patients' => url('/admin/patients'), $patient['patient_code'] => ''];

$isArchived = $patient['archived_at'] !== null;
$canEdit = can('patients.update') && !$isArchived;

$tabs = [
    'overview'     => ['label' => 'Overview',    'icon' => 'user-round',     'count' => null],
    'visits'       => ['label' => 'Visits',      'icon' => 'calendar-days',  'count' => count($visits)],
    'documents'    => ['label' => 'Documents',   'icon' => 'folder-open',    'count' => count($documents)],
    'prescriptions'=> ['label' => 'Prescriptions','icon' => 'prescription',  'count' => null],
    'lab-reports'  => ['label' => 'Lab Reports', 'icon' => 'flask-conical',  'count' => null],
    'invoices'     => ['label' => 'Invoices',    'icon' => 'receipt-text',   'count' => null],
];

$visitTypeTone = [
    'outpatient'    => 'badge-teal',
    'inpatient'     => 'badge-navy',
    'emergency'     => 'badge-rose',
    'follow-up'     => 'badge-amber',
    'telemedicine'  => 'badge-slate',
];
?>
<?php $this->section('content'); ?>

<!-- Header -->
<div class="card overflow-visible">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
        <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl text-xl font-bold text-white <?= $patient['gender'] === 'female' ? 'bg-gradient-to-br from-rose-400 to-rose-500' : 'bg-gradient-to-br from-navy-700 to-navy-900' ?>">
            <?= e(strtoupper(substr((string) $patient['first_name'], 0, 1) . substr((string) $patient['last_name'], 0, 1))) ?>
        </span>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?>
                </h1>
                <?php if ($isArchived): ?>
                    <span class="badge badge-slate"><i data-lucide="archive" class="h-3 w-3"></i>Archived — history preserved</span>
                <?php endif; ?>
                <?php if ($patient['allergies'] !== null && trim((string) $patient['allergies']) !== '' && strtolower(trim((string) $patient['allergies'])) !== 'none known'): ?>
                    <span class="badge badge-rose" title="<?= e($patient['allergies']) ?>"><i data-lucide="triangle-alert" class="h-3 w-3"></i>Allergies on file</span>
                <?php endif; ?>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                <span class="font-mono font-medium text-teal-700 dark:text-teal-400"><?= e($patient['patient_code']) ?></span>
                <span><?= (int) $age ?> yrs · <?= e(ucfirst((string) $patient['gender'])) ?></span>
                <?php if ($patient['blood_group']): ?><span class="badge badge-rose"><?= e($patient['blood_group']) ?></span><?php endif; ?>
                <span><?= e($patient['phone']) ?></span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('/admin/patients/' . (int) $patient['id'] . '/print') ?>" target="_blank" class="btn btn-secondary">
                <i data-lucide="printer" class="h-4 w-4"></i>Print summary
            </a>
            <?php if ($canEdit): ?>
                <a href="<?= url('/admin/patients/' . (int) $patient['id'] . '/edit') ?>" class="btn btn-primary">
                    <i data-lucide="pencil" class="h-4 w-4"></i>Edit
                </a>
                <form method="post" action="<?= url('/admin/patients/' . (int) $patient['id'] . '/archive') ?>"
                      data-confirm="Archive patient|<?= e($patient['first_name']) ?> will be hidden from the active directory. Encounters, documents and audit history are preserved.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><i data-lucide="archive" class="h-4 w-4"></i>Archive</button>
                </form>
            <?php elseif ($isArchived && can('patients.update')): ?>
                <form method="post" action="<?= url('/admin/patients/' . (int) $patient['id'] . '/restore') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i data-lucide="archive-restore" class="h-4 w-4"></i>Restore</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-t border-slate-100 px-4 dark:border-slate-800">
        <nav class="flex gap-1 overflow-x-auto" role="tablist">
            <?php foreach ($tabs as $key => $meta): ?>
                <a href="<?= url('/admin/patients/' . (int) $patient['id'] . '?tab=' . $key) ?>"
                   class="flex items-center gap-2 whitespace-nowrap border-b-2 px-3.5 py-3 text-[13px] font-medium transition-colors <?= $tab === $key
                       ? 'border-teal-500 text-teal-700 dark:text-teal-400'
                       : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' ?>">
                    <i data-lucide="<?= $meta['icon'] ?>" class="h-4 w-4"></i><?= e($meta['label']) ?>
                    <?php if ($meta['count'] !== null): ?>
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold <?= $tab === $key ? 'bg-teal-500/15 text-teal-700 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' ?>"><?= (int) $meta['count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<?php if ($tab === 'overview'): ?>
    <!-- ============================ OVERVIEW ============================ -->
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="card">
                <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Personal &amp; contact</h2>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                    <?php
                    $rows = [
                        'Date of birth' => format_date($patient['date_of_birth'], 'M j, Y') . ' (' . (int) $age . ' yrs)',
                        'Gender' => ucfirst((string) $patient['gender']),
                        'Blood group' => $patient['blood_group'] ?? '—',
                        'Marital status' => $patient['marital_status'] ? ucfirst((string) $patient['marital_status']) : '—',
                        'National ID' => $patient['national_id'] ?? '—',
                        'Phone' => $patient['phone'],
                        'Email' => $patient['email'] ?? '—',
                        'Registered' => format_date($patient['created_at'], 'M j, Y'),
                    ];
                    ?>
                    <?php foreach ($rows as $label => $value): ?>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400"><?= e($label) ?></dt>
                            <dd class="mt-0.5 text-[13.5px] text-slate-700 dark:text-slate-200"><?= e((string) $value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Address</dt>
                        <dd class="mt-0.5 text-[13.5px] text-slate-700 dark:text-slate-200">
                            <?= e(trim(($patient['address'] ?? '') . ' ' . ($patient['city'] ?? '') . ' ' . ($patient['postal_code'] ?? '') . ' ' . ($patient['country'] ?? '')) ?: '—') ?>
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Medical summary</h2>
                <div class="space-y-4 p-5">
                    <?php foreach (['medical_history' => 'Medical history', 'allergies' => 'Allergies', 'notes' => 'Clinical notes'] as $field => $label): ?>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400"><?= e($label) ?></p>
                            <p class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-[13px] leading-relaxed text-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                                <?= e(trim((string) ($patient[$field] ?? '')) !== '' ? (string) $patient[$field] : '—') ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="space-y-4">
            <section class="card">
                <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">Emergency contact</h2>
                <div class="p-5 text-[13.5px]">
                    <p class="font-medium text-slate-800 dark:text-slate-100"><?= e($patient['emergency_contact_name'] ?? '—') ?></p>
                    <p class="mt-0.5 text-slate-500 dark:text-slate-400"><?= e($patient['emergency_contact_relation'] ?? '') ?></p>
                    <p class="mt-1 font-mono text-teal-700 dark:text-teal-400"><?= e($patient['emergency_contact_phone'] ?? '—') ?></p>
                </div>
            </section>

            <section class="card">
                <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">At a glance</h2>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php
                    $glance = [
                        ['icon' => 'calendar-days', 'label' => 'Total visits', 'value' => (string) count($visits)],
                        ['icon' => 'folder-open', 'label' => 'Documents', 'value' => (string) count($documents)],
                        ['icon' => 'clock', 'label' => 'Last visit', 'value' => !empty($visits) ? format_date($visits[0]['visited_at'], 'M j, Y') : '—'],
                        ['icon' => 'user-round-plus', 'label' => 'Registered', 'value' => format_date($patient['created_at'], 'M j, Y')],
                    ];
                    ?>
                    <?php foreach ($glance as $row): ?>
                        <div class="flex items-center justify-between px-5 py-3 text-[13px]">
                            <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400"><i data-lucide="<?= $row['icon'] ?>" class="h-3.5 w-3.5"></i><?= e($row['label']) ?></span>
                            <span class="font-medium text-slate-700 dark:text-slate-200"><?= e($row['value']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>

<?php elseif ($tab === 'visits'): ?>
    <!-- ============================ VISITS ============================ -->
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <!-- Timeline -->
        <div class="card xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Encounter timeline</h2>
                <p class="text-xs text-slate-400">Complete history<?= $isArchived ? ' — preserved after archiving' : '' ?></p>
            </div>

            <?php if ($visits === []): ?>
                <div class="p-5">
                    <?= $this->insert('components/empty-state', [
                        'icon' => 'calendar-x', 'compact' => true,
                        'title' => 'No visits recorded',
                        'message' => 'Encounters appear here as the patient attends the hospital.',
                    ]) ?>
                </div>
            <?php else: ?>
                <ol class="relative p-5">
                    <?php foreach ($visits as $i => $visit): ?>
                        <li class="relative flex gap-4 pb-6 last:pb-0 <?= $i < count($visits) - 1 ? 'before:absolute before:left-[15px] before:top-9 before:bottom-0 before:w-px before:bg-slate-200 dark:before:bg-slate-700' : '' ?>">
                            <span class="z-10 grid h-8 w-8 shrink-0 place-items-center rounded-full ring-4 ring-white dark:ring-slate-900 <?= $visit['status'] === 'cancelled' ? 'bg-slate-200 text-slate-400' : ($visit['visit_type'] === 'emergency' ? 'bg-rose-500/15 text-rose-600' : 'bg-teal-500/15 text-teal-600 dark:text-teal-400') ?>">
                                <i data-lucide="<?= ['outpatient' => 'stethoscope', 'inpatient' => 'bed-double', 'emergency' => 'siren', 'follow-up' => 'calendar-check', 'telemedicine' => 'video'][$visit['visit_type']] ?? 'activity' ?>" class="h-4 w-4"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge <?= $visitTypeTone[$visit['visit_type']] ?? 'badge-slate' ?>"><?= e(ucfirst((string) $visit['visit_type'])) ?></span>
                                    <?php if ($visit['status'] !== 'completed'): ?>
                                        <span class="badge badge-amber"><?= e(ucfirst((string) $visit['status'])) ?></span>
                                    <?php endif; ?>
                                    <span class="text-[11.5px] text-slate-400"><?= e(format_date($visit['visited_at'], 'M j, Y — g:i A')) ?></span>
                                </div>
                                <p class="mt-1.5 text-[13.5px] font-medium text-slate-800 dark:text-slate-100"><?= e($visit['chief_complaint']) ?></p>
                                <?php if ($visit['diagnosis']): ?>
                                    <p class="mt-0.5 text-[13px] text-slate-600 dark:text-slate-300"><span class="text-slate-400">Dx:</span> <?= e($visit['diagnosis']) ?></p>
                                <?php endif; ?>
                                <?php if ($visit['notes']): ?>
                                    <p class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 p-2.5 text-[12.5px] leading-relaxed text-slate-600 dark:bg-slate-800/60 dark:text-slate-300"><?= e($visit['notes']) ?></p>
                                <?php endif; ?>
                                <p class="mt-1.5 text-[11px] text-slate-400">
                                    <?= $visit['doctor_name'] ? 'Dr. ' . e($visit['doctor_name']) : 'No attending doctor' ?>
                                </p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <!-- Record visit -->
        <?php if (can('patients.update') && !$isArchived): ?>
            <form method="post" action="<?= url('/admin/patients/' . (int) $patient['id'] . '/visits') ?>" class="card h-fit">
                <?= csrf_field() ?>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Record a visit</h2>
                    <p class="text-xs text-slate-400">Adds to the encounter timeline</p>
                </div>
                <div class="space-y-3.5 p-5">
                    <div>
                        <label for="visited_at" class="label">Date &amp; time</label>
                        <input type="datetime-local" id="visited_at" name="visited_at" value="<?= date('Y-m-d\TH:i') ?>" class="input" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="visit_type" class="label">Type</label>
                            <select id="visit_type" name="visit_type" class="input">
                                <?php foreach ($visitTypes as $type): ?>
                                    <option value="<?= e($type) ?>"><?= e(ucfirst($type)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="status" class="label">Status</label>
                            <select id="status" name="status" class="input">
                                <option value="completed">Completed</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="doctor_id" class="label">Attending doctor</label>
                        <select id="doctor_id" name="doctor_id" class="input">
                            <option value="">—</option>
                            <?php foreach ($doctorOptions as $doc): ?>
                                <option value="<?= (int) $doc['id'] ?>"><?= e($doc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="chief_complaint" class="label">Chief complaint <span class="text-rose-500">*</span></label>
                        <textarea id="chief_complaint" name="chief_complaint" rows="2" class="input" required placeholder="Reason for visit…"></textarea>
                    </div>
                    <div>
                        <label for="diagnosis" class="label">Diagnosis</label>
                        <input type="text" id="diagnosis" name="diagnosis" class="input">
                    </div>
                    <div>
                        <label for="visit_notes" class="label">Notes</label>
                        <textarea id="visit_notes" name="notes" rows="2" class="input"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <i data-lucide="plus" class="h-4 w-4"></i>Add to timeline
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'documents'): ?>
    <!-- ============================ DOCUMENTS ============================ -->
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Documents</h2>
                <p class="text-xs text-slate-400">Stored privately — downloads are permission-checked</p>
            </div>

            <?php if ($documents === []): ?>
                <div class="p-5">
                    <?= $this->insert('components/empty-state', [
                        'icon' => 'folder-open', 'compact' => true,
                        'title' => 'No documents',
                        'message' => 'Upload reports, prescriptions, scans or identification papers.',
                    ]) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($documents as $doc): ?>
                        <li class="flex items-center gap-3.5 px-5 py-3.5">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg <?= $doc['mime_type'] === 'application/pdf' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-teal-500/10 text-teal-600 dark:text-teal-400' ?>">
                                <i data-lucide="<?= $doc['mime_type'] === 'application/pdf' ? 'file-text' : 'image' ?>" class="h-5 w-5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13.5px] font-medium text-slate-800 dark:text-slate-100">
                                    <?= e($doc['title']) ?>
                                    <span class="badge badge-slate ml-1"><?= e($doc['document_type']) ?></span>
                                </p>
                                <p class="truncate text-xs text-slate-400">
                                    <?= e($doc['original_name']) ?> · <?= e(number_format((int) $doc['size_bytes'] / 1024, 0)) ?> KB ·
                                    <?= e($doc['uploaded_by_name'] ?? 'system') ?> · <?= e(format_date($doc['created_at'], 'M j, Y')) ?>
                                </p>
                            </div>
                            <a href="<?= url('/admin/patients/' . (int) $patient['id'] . '/documents/' . (int) $doc['id']) ?>" target="_blank" class="icon-btn" title="View / download">
                                <i data-lucide="eye" class="h-4 w-4"></i>
                            </a>
                            <?php if (can('patients.update')): ?>
                                <form method="post" action="<?= url('/admin/patients/' . (int) $patient['id'] . '/documents/' . (int) $doc['id'] . '/delete') ?>"
                                      data-confirm="Delete document|Delete &quot;<?= e($doc['title']) ?>&quot; permanently?|danger">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn hover:!text-rose-600" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if (can('patients.update') && !$isArchived): ?>
            <form method="post" action="<?= url('/admin/patients/' . (int) $patient['id'] . '/documents') ?>" enctype="multipart/form-data" class="card h-fit">
                <?= csrf_field() ?>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Upload document</h2>
                    <p class="text-xs text-slate-400">PDF / JPG / PNG / WebP · max 5 MB</p>
                </div>
                <div class="space-y-3.5 p-5">
                    <div>
                        <label for="document_title" class="label">Title</label>
                        <input type="text" id="document_title" name="document_title" class="input" placeholder="e.g. Chest X-ray">
                    </div>
                    <div>
                        <label for="document_type" class="label">Type</label>
                        <select id="document_type" name="document_type" class="input">
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= e($type) ?>"><?= e(ucfirst($type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="document" class="label">File <span class="text-rose-500">*</span></label>
                        <input type="file" id="document" name="document" class="input !py-1.5" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        <p class="mt-1.5 text-[11px] leading-relaxed text-slate-400">Content is MIME-sniffed server-side; mismatched files are rejected.</p>
                    </div>
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <i data-lucide="upload" class="h-4 w-4"></i>Upload
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ============================ FUTURE MODULE TABS ============================ -->
    <div class="card mt-4">
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $tab === 'prescriptions' ? 'prescription' : ($tab === 'lab-reports' ? 'flask-conical' : 'receipt-text'),
                'title'   => ucfirst(str_replace('-', ' ', $tab)) . ' module is not installed yet',
                'message' => 'This tab fills with real records automatically once the corresponding module ships. Visit data is already being captured on the timeline.',
            ]) ?>
        </div>
    </div>
<?php endif; ?>

<?php $this->end(); ?>
