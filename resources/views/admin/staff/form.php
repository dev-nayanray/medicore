<?php

declare(strict_types=1);

/**
 * Staff profile form — create / edit.
 * $staff (null = create), $departments, $userCandidates (only on create)
 */

$this->extend('layouts/admin');
$isEdit = $staff !== null;
$title = $isEdit ? 'Edit Staff' : 'New Staff Profile';
$active = 'staff';
$breadcrumbs = ['Hospital' => null, 'Staff' => url('/admin/staff'), ($isEdit ? 'Edit' : 'New') => ''];

$v = static fn (string $key) => old($key, (string) ($staff[$key] ?? ''));
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit ' . e($staff['user_name'] ?? 'Staff') : 'New Staff Profile' ?>
            <?php if ($isEdit): ?><span class="ml-2 font-mono text-sm font-normal text-teal-600 dark:text-teal-400"><?= e($staff['employee_id']) ?></span><?php endif; ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isEdit ? 'Changes are audit-logged.' : 'Link a user account to an employment profile.' ?>
        </p>
    </div>
    <a href="<?= url('/admin/staff') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to directory
    </a>
</div>

<form method="post" action="<?= $isEdit ? url('/admin/staff/' . (int) $staff['id']) : url('/admin/staff') ?>" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>

    <?php if (!$isEdit): ?>
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300"><i data-lucide="user-cog" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Account link</h2>
        </div>
        <div class="p-5">
            <label for="user_id" class="label">Staff account <span class="text-rose-500">*</span></label>
            <select id="user_id" name="user_id" class="input" required>
                <option value="">Select a user account…</option>
                <?php foreach ($userCandidates as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= old('user_id', '') === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?> · <?= e($u['email']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($userCandidates === []): ?>
                <p class="mt-1.5 text-[11px] text-amber-600 dark:text-amber-400">No unlinked accounts available. Create a user first.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="briefcase" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Employment details</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div>
                <label for="job_title" class="label">Job title <span class="text-rose-500">*</span></label>
                <input type="text" id="job_title" name="job_title" value="<?= $v('job_title') ?>" class="input <?= error('job_title') ? 'input-error' : '' ?>" placeholder="e.g. Senior Nurse" required>
                <?php if (error('job_title')): ?><p class="error-text"><?= e(error('job_title')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="department_id" class="label">Department</label>
                <select id="department_id" name="department_id" class="input">
                    <option value="">—</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= old('department_id', (string) ($staff['department_id'] ?? '')) === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="employment_type" class="label">Employment type</label>
                <select id="employment_type" name="employment_type" class="input">
                    <?php foreach (['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'visiting' => 'Visiting', 'intern' => 'Intern'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= old('employment_type', (string) ($staff['employment_type'] ?? 'full_time')) === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <?php foreach (['active' => 'Active', 'on_leave' => 'On leave', 'inactive' => 'Inactive', 'terminated' => 'Terminated'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= old('status', (string) ($staff['status'] ?? 'active')) === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="hire_date" class="label">Hire date</label>
                <input type="date" id="hire_date" name="hire_date" value="<?= $v('hire_date') ?>" class="input">
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
            Audit-logged · employee ID auto-generated.
        </p>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Save changes' : 'Create profile' ?>
        </button>
    </div>
</form>
<?php $this->end(); ?>
