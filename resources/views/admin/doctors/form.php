<?php

declare(strict_types=1);

/**
 * Doctor profile form — create / edit.
 * $doctor (null = create), $departments, $userCandidates (only on create), $days
 */

$this->extend('layouts/admin');
$isEdit = $doctor !== null;
$title = $isEdit ? 'Edit Doctor' : 'New Doctor Profile';
$active = 'doctors';
$breadcrumbs = ['Hospital' => null, 'Doctors' => url('/admin/doctors'), ($isEdit ? 'Edit' : 'New') => ''];

$v = static fn (string $key) => old($key, (string) ($doctor[$key] ?? ''));
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit ' . e($doctor['user_name'] ?? 'Doctor') : 'New Doctor Profile' ?>
            <?php if ($isEdit): ?><span class="ml-2 font-mono text-sm font-normal text-teal-600 dark:text-teal-400"><?= e($doctor['doctor_code']) ?></span><?php endif; ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isEdit ? 'Changes are audit-logged.' : 'Link a doctor role account to a clinical profile.' ?>
        </p>
    </div>
    <a href="<?= url('/admin/doctors') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to directory
    </a>
</div>

<form method="post" action="<?= $isEdit ? url('/admin/doctors/' . (int) $doctor['id']) : url('/admin/doctors') ?>" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>

    <?php if (!$isEdit): ?>
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300"><i data-lucide="user-cog" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Account link</h2>
        </div>
        <div class="p-5">
            <label for="user_id" class="label">Doctor account <span class="text-rose-500">*</span></label>
            <select id="user_id" name="user_id" class="input" required>
                <option value="">Select a user with the doctor role…</option>
                <?php foreach ($userCandidates as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= old('user_id', '') === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?> · <?= e($u['email']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (error('user_id')): ?><p class="error-text"><?= e(error('user_id')) ?></p><?php endif; ?>
            <?php if ($userCandidates === []): ?>
                <p class="mt-1.5 text-[11px] text-amber-600 dark:text-amber-400">No unlinked doctor accounts available. Create a user with the doctor role first.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="stethoscope" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Clinical details</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div>
                <label for="specialization" class="label">Specialization <span class="text-rose-500">*</span></label>
                <input type="text" id="specialization" name="specialization" value="<?= $v('specialization') ?>" class="input <?= error('specialization') ? 'input-error' : '' ?>" placeholder="e.g. Cardiology" required>
                <?php if (error('specialization')): ?><p class="error-text"><?= e(error('specialization')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="registration_number" class="label">Medical registration no.</label>
                <input type="text" id="registration_number" name="registration_number" value="<?= $v('registration_number') ?>" class="input <?= error('registration_number') ? 'input-error' : '' ?>">
                <?php if (error('registration_number')): ?><p class="error-text"><?= e(error('registration_number')) ?></p><?php endif; ?>
            </div>
            <div class="sm:col-span-2">
                <label for="qualifications" class="label">Qualifications</label>
                <input type="text" id="qualifications" name="qualifications" value="<?= $v('qualifications') ?>" class="input" placeholder="MBBS, FCPS, MD — …">
            </div>
            <div>
                <label for="department_id" class="label">Department</label>
                <select id="department_id" name="department_id" class="input">
                    <option value="">—</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= old('department_id', (string) ($doctor['department_id'] ?? '')) === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="room_number" class="label">Room / chamber</label>
                <input type="text" id="room_number" name="room_number" value="<?= $v('room_number') ?>" class="input" placeholder="e.g. Cabin 3, OPD-2">
            </div>
            <div>
                <label for="consultation_fee" class="label">Consultation fee (<?= e((string) setting('currency', 'BDT')) ?>)</label>
                <input type="number" step="0.01" min="0" id="consultation_fee" name="consultation_fee" value="<?= $v('consultation_fee') ?>" class="input">
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <?php foreach (['active' => 'Active', 'on_leave' => 'On leave', 'inactive' => 'Inactive'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= old('status', (string) ($doctor['status'] ?? 'active')) === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="hired_at" class="label">Hired date</label>
                <input type="date" id="hired_at" name="hired_at" value="<?= $v('hired_at') ?>" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="bio" class="label">Bio</label>
                <textarea id="bio" name="bio" rows="3" class="input" placeholder="Short professional biography…"><?= $v('bio') ?></textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="profile_image" class="label">Profile image (JPG / PNG / WebP, max 2 MB)</label>
                <input type="file" id="profile_image" name="profile_image" class="input !py-1.5" accept=".jpg,.jpeg,.png,.webp">
            </div>
        </div>
    </section>

    <div class="card flex flex-col items-center justify-between gap-3 p-5 sm:flex-row">
        <p class="flex items-center gap-1.5 text-xs text-slate-400">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Audit-logged · doctor code auto-generated.
        </p>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Save changes' : 'Create profile' ?>
        </button>
    </div>
</form>
<?php $this->end(); ?>
