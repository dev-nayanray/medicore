<?php

declare(strict_types=1);

/**
 * Appointment booking / edit form.
 * $appointment (null=create), $patients (pre-selected patient or null), $doctors, $departments, $prefillDoctor, $prefillDate
 */

$this->extend('layouts/admin');
$isEdit = $appointment !== null;
$title = $isEdit ? 'Edit Appointment' : 'Book Appointment';
$active = 'appointments';
$breadcrumbs = ['Hospital' => null, 'Appointments' => url('/admin/appointments'), ($isEdit ? 'Edit' : 'Book') => ''];

$v = static fn (string $key) => old($key, (string) ($appointment[$key] ?? ''));

// Patient search is AJAX-powered — the hidden input stores the selected patient_id.
// On edit, the patient is already set on the appointment.
$selectedPatientId = $isEdit ? (int) $appointment['patient_id'] : (int) old('patient_id', $patients['id'] ?? 0);
$selectedPatientName = $isEdit ? ($appointment['patient_name'] ?? '') : ($patients ? ($patients['first_name'] . ' ' . $patients['last_name'] . ' · ' . $patients['patient_code']) : old('patient_label', ''));
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit ' . e($appointment['appointment_code']) : 'Book Appointment' ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isEdit ? 'Reschedule or update appointment details.' : 'Select a patient, doctor and available time slot.' ?>
        </p>
    </div>
    <a href="<?= url('/admin/appointments') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to directory
    </a>
</div>

<form method="post" action="<?= $isEdit ? url('/admin/appointments/' . (int) $appointment['id'] . '/reschedule') : url('/admin/appointments') ?>" class="space-y-4">
    <?= csrf_field() ?>

    <?php if (!$isEdit): ?>
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="user-round" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Patient</h2>
        </div>
        <div class="p-5">
            <label for="patient_search" class="label">Search patient <span class="text-rose-500">*</span></label>
            <div class="relative" x-data="{ open: false, results: [], loading: false, timer: null, search: '<?= e($selectedPatientName) ?>' }">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="patient_search" autocomplete="off"
                       x-model="search"
                       @input.debounce.350ms="timer && clearTimeout(timer); timer = setTimeout(function(){ if(search.trim().length < 2){ results = []; open = false; return; } loading = true; fetch(MEDICORE_BASE + '/api/search?q=' + encodeURIComponent(search), {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()}).then(function(j){ var g = (j.data && j.data.groups) || []; var p = (g.find(function(x){return x.label==='Patients'}) || {results:[]}).results; results = p; open = true; loading = false; }).catch(function(){ loading = false; }) }, 350)"
                       @blur="setTimeout(function(){ open = false }, 200)"
                       @focus="search.length > 0 && results.length > 0 && (open = true)"
                       placeholder="Type patient name, code or phone…"
                       class="input pl-9 <?= error('patient_id') ? 'input-error' : '' ?>" required>
                <span x-show="loading" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">…</span>
                <div x-show="open && results.length > 0" x-cloak class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    <template x-for="r in results" :key="r.url">
                        <button type="button" @click="search = r.title; open = false; document.getElementById('patient_id').value = r.url.split('/').pop()"
                                class="block w-full px-3 py-2 text-left text-[13px] hover:bg-slate-50 dark:hover:bg-slate-800" x-text="r.title"></button>
                    </template>
                    <p class="px-3 py-2 text-[11px] text-slate-400" x-show="results.length === 0">No patients found.</p>
                </div>
            </div>
            <input type="hidden" id="patient_id" name="patient_id" value="<?= (int) $selectedPatientId ?>">
            <?php if (error('patient_id')): ?><p class="error-text"><?= e(error('patient_id')) ?></p><?php endif; ?>
            <?php if ($selectedPatientId > 0 && !$isEdit): ?>
                <p class="mt-1.5 text-[11px] text-teal-600 dark:text-teal-400">Selected patient ID: <?= (int) $selectedPatientId ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php else: ?>
        <input type="hidden" name="patient_id" value="<?= (int) $appointment['patient_id'] ?>">
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <p class="text-[13px]"><span class="text-slate-400">Patient:</span> <span class="font-medium text-slate-800 dark:text-slate-100"><?= e($appointment['patient_name']) ?></span> <span class="font-mono text-[11px] text-slate-400"><?= e($appointment['patient_code']) ?></span></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300"><i data-lucide="stethoscope" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Schedule</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php if (!$isEdit): ?>
            <div>
                <label for="appointment_type" class="label">Type</label>
                <select id="appointment_type" name="appointment_type" class="input">
                    <?php foreach (['scheduled' => 'Scheduled', 'walk_in' => 'Walk-in', 'follow_up' => 'Follow-up', 'telemedicine' => 'Telemedicine'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= old('appointment_type', 'scheduled') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label for="doctor_id" class="label">Doctor <?= $isEdit ? '' : '<span class="text-slate-400 font-normal">(required for scheduled)</span>' ?></label>
                <select id="doctor_id" name="doctor_id" class="input" <?= $isEdit ? 'disabled' : '' ?>>
                    <option value="">—</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= (int) $doc['id'] ?>" <?= old('doctor_id', (string) ($appointment['doctor_id'] ?? '')) === (string) $doc['id'] || (string) $prefillDoctor === (string) $doc['id'] ? 'selected' : '' ?>><?= e($doc['name']) ?> · <?= e($doc['specialization']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isEdit): ?>
                    <p class="mt-1 text-[11px] text-slate-400">Doctor cannot be changed after booking. Cancel + rebook to change doctors.</p>
                <?php endif; ?>
            </div>
            <div>
                <label for="department_id" class="label">Department</label>
                <select id="department_id" name="department_id" class="input" <?= $isEdit ? 'disabled' : '' ?>>
                    <option value="">Auto (from doctor)</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= old('department_id', (string) ($appointment['department_id'] ?? '')) === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="appointment_date" class="label">Date <span class="text-rose-500">*</span></label>
                <input type="date" id="appointment_date" name="appointment_date" value="<?= old('appointment_date', (string) ($appointment['appointment_date'] ?? $prefillDate)) ?>" min="<?= date('Y-m-d') ?>" class="input" required>
            </div>
            <div>
                <label for="start_time" class="label">Start time <span class="text-rose-500">*</span></label>
                <input type="time" id="start_time" name="start_time" value="<?= old('start_time', (string) ($appointment['start_time'] ?? '09:00')) ?>" class="input" required>
            </div>
            <div>
                <label for="end_time" class="label">End time <span class="text-rose-500">*</span></label>
                <input type="time" id="end_time" name="end_time" value="<?= old('end_time', (string) ($appointment['end_time'] ?? '09:30')) ?>" class="input" required>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label for="reason" class="label">Reason for visit</label>
                <input type="text" id="reason" name="reason" value="<?= $v('reason') ?>" class="input" placeholder="Chief complaint / referral reason" <?= $isEdit ? '' : 'maxlength="500"' ?>>
            </div>
            <?php if (!$isEdit): ?>
            <div class="sm:col-span-2 lg:col-span-3">
                <label for="notes" class="label">Notes (reception / clinical)</label>
                <textarea id="notes" name="notes" rows="2" class="input" placeholder="Optional notes for the front desk or doctor"></textarea>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="card flex flex-col items-center justify-between gap-3 p-5 sm:flex-row">
        <p class="flex items-center gap-1.5 text-xs text-slate-400">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Overlap validation runs server-side inside a transaction — concurrent bookings cannot double-book.
        </p>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Reschedule' : 'Book appointment' ?>
        </button>
    </div>
</form>
<?php $this->end(); ?>
