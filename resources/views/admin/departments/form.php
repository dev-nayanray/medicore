<?php

declare(strict_types=1);

/**
 * Department create / edit form.
 * $department (null = create), $doctorOptions
 */

$this->extend('layouts/admin');
$isEdit = $department !== null;
$title = $isEdit ? 'Edit Department' : 'New Department';
$active = 'departments';
$breadcrumbs = ['Hospital' => null, 'Departments' => url('/admin/departments'), ($isEdit ? 'Edit' : 'New') => ''];

$v = static fn (string $key) => old($key, (string) ($department[$key] ?? ''));
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit ' . e($department['name']) : 'New Department' ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isEdit ? 'Changes are audit-logged.' : 'Departments organize doctors and staff into clinical units.' ?>
        </p>
    </div>
    <a href="<?= url('/admin/departments') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to directory
    </a>
</div>

<form method="post" action="<?= $isEdit ? url('/admin/departments/' . (int) $department['id']) : url('/admin/departments') ?>" class="space-y-4">
    <?= csrf_field() ?>

    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="building-2" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Department details</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Department name <span class="text-rose-500">*</span></label>
                <input type="text" id="name" name="name" value="<?= $v('name') ?>" class="input <?= error('name') ? 'input-error' : '' ?>" required>
                <?php if (error('name')): ?><p class="error-text"><?= e(error('name')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="head_doctor_id" class="label">Head of department</label>
                <select id="head_doctor_id" name="head_doctor_id" class="input">
                    <option value="">—</option>
                    <?php foreach ($doctorOptions as $doc): ?>
                        <option value="<?= (int) $doc['id'] ?>" <?= old('head_doctor_id', (string) ($department['head_doctor_id'] ?? '')) === (string) $doc['id'] ? 'selected' : '' ?>>
                            <?= e($doc['name']) ?> · <?= e($doc['specialization']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="location" class="label">Location</label>
                <input type="text" id="location" name="location" value="<?= $v('location') ?>" class="input" placeholder="Floor / wing / block">
            </div>
            <div>
                <label for="phone" class="label">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= $v('phone') ?>" class="input">
            </div>
            <div>
                <label for="email" class="label">Email</label>
                <input type="email" id="email" name="email" value="<?= $v('email') ?>" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="description" class="label">Description / services</label>
                <textarea id="description" name="description" rows="3" class="input" placeholder="What services does this department provide?"><?= $v('description') ?></textarea>
            </div>
            <?php if ($isEdit): ?>
            <div class="sm:col-span-2">
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" class="checkbox !static" <?= old('is_active', (string) ($department['is_active'] ?? '0')) === '1' || $department['is_active'] == 1 ? 'checked' : '' ?>>
                    Department is active (visible in dropdowns)
                </label>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="card flex flex-col items-center justify-between gap-3 p-5 sm:flex-row">
        <p class="flex items-center gap-1.5 text-xs text-slate-400">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Audit-logged · slug auto-generated from the name.
        </p>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Save changes' : 'Create department' ?>
        </button>
    </div>
</form>
<?php $this->end(); ?>
