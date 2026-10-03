<?php

declare(strict_types=1);

/**
 * Patient registration / edit form. $patient (null=create), $bloodGroups, $documentTypes
 */

$this->extend('layouts/admin');
$isEdit = $patient !== null;
$title = $isEdit ? 'Edit Patient' : 'Register Patient';
$active = 'patients';
$breadcrumbs = ['Hospital' => null, 'Patients' => url('/admin/patients'), ($isEdit ? 'Edit' : 'Register') => ''];

// Duplicate warnings flashed by the controller on a blocked save.
$duplicateWarnings = \App\Core\Session::get('_patient_duplicates', []);
\App\Core\Session::forget('_patient_duplicates');

$v = static fn (string $key) => old($key, (string) ($patient[$key] ?? ''));
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit ' . e($patient['first_name'] . ' ' . $patient['last_name']) : 'Register Patient' ?>
            <?php if ($isEdit): ?><span class="ml-2 font-mono text-sm font-normal text-teal-600 dark:text-teal-400"><?= e($patient['patient_code']) ?></span><?php endif; ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isEdit ? 'Update the master record — changes are audit-logged.' : 'A unique patient ID is generated automatically on save.' ?>
        </p>
    </div>
    <a href="<?= url('/admin/patients') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to directory
    </a>
</div>

<?php if (error('duplicates') !== null): ?>
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
        <p class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
            <i data-lucide="copy-x" class="h-4 w-4"></i> Possible duplicate patient detected
        </p>
        <div class="mt-3 space-y-2">
            <?php foreach ($duplicateWarnings as $dupe): ?>
                <div class="flex items-center justify-between gap-3 rounded-lg bg-white/70 px-3 py-2 dark:bg-slate-900/60">
                    <div class="min-w-0 text-[13px]">
                        <span class="font-medium"><?= e($dupe['name']) ?></span>
                        <span class="ml-1 font-mono text-xs text-slate-400"><?= e($dupe['patient_code']) ?></span>
                        <span class="block text-xs text-slate-400"><?= e($dupe['phone']) ?> · DOB <?= e(format_date($dupe['date_of_birth'], 'M j, Y')) ?></span>
                    </div>
                    <a href="<?= url('/admin/patients/' . (int) $dupe['id']) ?>" class="btn btn-secondary !py-1.5 !text-xs">Open profile</a>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-3 text-xs leading-relaxed text-amber-700 dark:text-amber-400/90">
            If this is genuinely a different person, submit again — the form includes a one-time override.
        </p>
    </div>
<?php endif; ?>

<form method="post"
      action="<?= $isEdit ? url('/admin/patients/' . (int) $patient['id']) : url('/admin/patients') ?>"
      enctype="multipart/form-data"
      class="space-y-4"
      x-data="patientDupes({ editId: <?= $isEdit ? (int) $patient['id'] : 0 ?> })">

    <?= csrf_field() ?>

    <!-- Section 1: Personal -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="id-card" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Personal details</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="first_name" class="label">First name <span class="text-rose-500">*</span></label>
                <input type="text" id="first_name" name="first_name" x-model="first_name" value="<?= $v('first_name') ?>" class="input <?= error('first_name') ? 'input-error' : '' ?>" required>
                <?php if (error('first_name')): ?><p class="error-text"><?= e(error('first_name')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="last_name" class="label">Last name <span class="text-rose-500">*</span></label>
                <input type="text" id="last_name" name="last_name" x-model="last_name" value="<?= $v('last_name') ?>" class="input <?= error('last_name') ? 'input-error' : '' ?>" required>
                <?php if (error('last_name')): ?><p class="error-text"><?= e(error('last_name')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="date_of_birth" class="label">Date of birth <span class="text-rose-500">*</span></label>
                <input type="date" id="date_of_birth" name="date_of_birth" x-model="date_of_birth" value="<?= $v('date_of_birth') ?>" max="<?= date('Y-m-d') ?>" class="input <?= error('date_of_birth') ? 'input-error' : '' ?>" required>
                <?php if (error('date_of_birth')): ?><p class="error-text"><?= e(error('date_of_birth')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="gender" class="label">Gender <span class="text-rose-500">*</span></label>
                <select id="gender" name="gender" class="input" required>
                    <?php foreach (['' => 'Select…', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= old('gender', (string) ($patient['gender'] ?? '')) === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (error('gender')): ?><p class="error-text"><?= e(error('gender')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="blood_group" class="label">Blood group</label>
                <select id="blood_group" name="blood_group" class="input">
                    <option value="">Unknown</option>
                    <?php foreach ($bloodGroups as $bg): ?>
                        <option value="<?= e($bg) ?>" <?= old('blood_group', (string) ($patient['blood_group'] ?? '')) === $bg ? 'selected' : '' ?>><?= e($bg) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="marital_status" class="label">Marital status</label>
                <select id="marital_status" name="marital_status" class="input">
                    <option value="">Unspecified</option>
                    <?php foreach (['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= old('marital_status', (string) ($patient['marital_status'] ?? '')) === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="national_id" class="label">National ID <span class="text-slate-400 font-normal">(unique)</span></label>
                <input type="text" id="national_id" name="national_id" x-model="national_id" value="<?= $v('national_id') ?>" class="input <?= error('national_id') ? 'input-error' : '' ?>" placeholder="NID-…">
                <?php if (error('national_id')): ?><p class="error-text"><?= e(error('national_id')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Section 2: Contact -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300"><i data-lucide="phone" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Contact &amp; address</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="phone" class="label">Phone <span class="text-rose-500">*</span></label>
                <input type="text" id="phone" name="phone" x-model="phone" value="<?= $v('phone') ?>" class="input <?= error('phone') ? 'input-error' : '' ?>" placeholder="+880 …" required>
                <?php if (error('phone')): ?><p class="error-text"><?= e(error('phone')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="email" class="label">Email</label>
                <input type="email" id="email" name="email" value="<?= $v('email') ?>" class="input <?= error('email') ? 'input-error' : '' ?>">
                <?php if (error('email')): ?><p class="error-text"><?= e(error('email')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="city" class="label">City</label>
                <input type="text" id="city" name="city" value="<?= $v('city') ?>" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="address" class="label">Street address</label>
                <input type="text" id="address" name="address" value="<?= $v('address') ?>" class="input" placeholder="House / road / area">
            </div>
            <div>
                <label for="postal_code" class="label">Postal code</label>
                <input type="text" id="postal_code" name="postal_code" value="<?= $v('postal_code') ?>" class="input">
            </div>
        </div>
    </section>

    <!-- Section 3: Emergency -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400"><i data-lucide="siren" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Emergency contact</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
            <div>
                <label for="emergency_contact_name" class="label">Contact name</label>
                <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= $v('emergency_contact_name') ?>" class="input">
            </div>
            <div>
                <label for="emergency_contact_relation" class="label">Relationship</label>
                <input type="text" id="emergency_contact_relation" name="emergency_contact_relation" value="<?= $v('emergency_contact_relation') ?>" class="input" placeholder="Wife, Father…">
            </div>
            <div>
                <label for="emergency_contact_phone" class="label">Contact phone</label>
                <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= $v('emergency_contact_phone') ?>" class="input">
            </div>
        </div>
    </section>

    <!-- Section 4: Clinical -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400"><i data-lucide="stethoscope" class="h-4 w-4"></i></span>
            <div>
                <h2 class="text-sm font-semibold">Medical information</h2>
                <p class="text-xs text-slate-400">Visible to clinical roles — access is permission-gated</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
            <div>
                <label for="medical_history" class="label">Medical history</label>
                <textarea id="medical_history" name="medical_history" rows="4" class="input" placeholder="Chronic conditions, past surgeries…"><?= $v('medical_history') ?></textarea>
            </div>
            <div>
                <label for="allergies" class="label">Known allergies</label>
                <textarea id="allergies" name="allergies" rows="4" class="input" placeholder="Drug, food, environmental…"><?= $v('allergies') ?></textarea>
            </div>
            <div>
                <label for="notes" class="label">Clinical notes</label>
                <textarea id="notes" name="notes" rows="4" class="input" placeholder="Authorized notes…"><?= $v('notes') ?></textarea>
            </div>
        </div>
    </section>

    <?php if (!$isEdit): ?>
        <!-- Optional first document -->
        <section class="card">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-slate-500/10 text-slate-600 dark:text-slate-300"><i data-lucide="paperclip" class="h-4 w-4"></i></span>
                <h2 class="text-sm font-semibold">Attach a document <span class="font-normal text-slate-400">(optional)</span></h2>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label for="document" class="label">File (PDF / JPG / PNG / WebP, max 5 MB)</label>
                    <input type="file" id="document" name="document" class="input !py-1.5" accept=".pdf,.jpg,.jpeg,.png,.webp">
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
                    <label for="document_title" class="label">Title</label>
                    <input type="text" id="document_title" name="document_title" class="input" placeholder="e.g. Referral letter">
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Live duplicate detector -->
    <div x-show="duplicates.length > 0" x-cloak class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
        <p class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
            <i data-lucide="copy-x" class="h-4 w-4"></i> Live check: possible existing patients
        </p>
        <div class="mt-2 space-y-1.5">
            <template x-for="d in duplicates" :key="d.id">
                <div class="flex items-center justify-between gap-3 rounded-lg bg-white/70 px-3 py-1.5 text-[13px] dark:bg-slate-900/60">
                    <span><span class="font-medium" x-text="d.name"></span> <span class="font-mono text-xs text-slate-400" x-text="d.patient_code"></span></span>
                    <a :href="MEDICORE_BASE + '/admin/patients/' + d.id" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">view</a>
                </div>
            </template>
        </div>
    </div>

    <!-- Submit bar -->
    <div class="card flex flex-col items-center justify-between gap-3 p-5 sm:flex-row">
        <p class="flex items-center gap-1.5 text-xs text-slate-400">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            <?= $isEdit ? 'Every change is written to the audit trail.' : 'Audit-logged · duplicates checked automatically.' ?>
        </p>
        <div class="flex items-center gap-2">
            <?php if ($isEdit && can('patients.update')): ?>
                <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <input type="checkbox" name="confirm_dupes" value="1" class="checkbox !static"> override duplicate warning
                </label>
            <?php else: ?>
                <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <input type="checkbox" name="confirm_dupes" value="1" class="checkbox !static"> register anyway (override duplicates)
                </label>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Save changes' : 'Register patient' ?>
            </button>
        </div>
    </div>
</form>
<?php $this->end(); ?>
