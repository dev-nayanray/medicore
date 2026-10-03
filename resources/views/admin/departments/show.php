<?php

declare(strict_types=1);

/**
 * Department overview — member roster, stats, contact info.
 * $department (with 'doctors' and 'staff' arrays)
 */

$this->extend('layouts/admin');
$title = $department['name'];
$active = 'departments';
$breadcrumbs = ['Hospital' => null, 'Departments' => url('/admin/departments'), $department['name'] => ''];

$isArchived = $department['archived_at'] !== null;
$canEdit = can('departments.update') && !$isArchived;
?>

<?php $this->section('content'); ?>

<div class="card">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
        <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-teal-600 to-navy-700 text-2xl font-bold text-white">
            <?= e(strtoupper(substr((string) $department['name'], 0, 1))) ?>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"><?= e($department['name']) ?></h1>
                <?php if ($isArchived): ?>
                    <span class="badge badge-slate"><i data-lucide="archive" class="h-3 w-3"></i>Archived</span>
                <?php elseif ((int) $department['is_active'] === 1): ?>
                    <span class="badge badge-emerald">Active</span>
                <?php else: ?>
                    <span class="badge badge-amber">Inactive</span>
                <?php endif; ?>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                <span class="font-mono"><?= e($department['slug']) ?></span>
                <?php if ($department['location']): ?><span>· <?= e($department['location']) ?></span><?php endif; ?>
                <?php if ($department['head_doctor_name']): ?><span>· Head: <?= e($department['head_doctor_name']) ?></span><?php endif; ?>
            </p>
            <?php if ($department['description']): ?>
                <p class="mt-2 text-[13.5px] leading-relaxed text-slate-600 dark:text-slate-300"><?= e($department['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($canEdit): ?>
                <a href="<?= url('/admin/departments/' . (int) $department['id'] . '/edit') ?>" class="btn btn-primary">
                    <i data-lucide="pencil" class="h-4 w-4"></i>Edit
                </a>
                <form method="post" action="<?= url('/admin/departments/' . (int) $department['id'] . '/archive') ?>"
                      data-confirm="Archive department|<?= e($department['name']) ?> will be hidden from active lists. Doctors and staff keep their assignments.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><i data-lucide="archive" class="h-4 w-4"></i>Archive</button>
                </form>
            <?php elseif ($isArchived && can('departments.update')): ?>
                <form method="post" action="<?= url('/admin/departments/' . (int) $department['id'] . '/restore') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><i data-lucide="archive-restore" class="h-4 w-4"></i>Restore</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-px overflow-hidden bg-slate-100 sm:grid-cols-4 dark:bg-slate-800">
        <div class="bg-white px-5 py-4 dark:bg-slate-900">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Doctors</p>
            <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100"><?= count($department['doctors']) ?></p>
        </div>
        <div class="bg-white px-5 py-4 dark:bg-slate-900">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Staff</p>
            <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100"><?= count($department['staff']) ?></p>
        </div>
        <div class="bg-white px-5 py-4 dark:bg-slate-900">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Phone</p>
            <p class="mt-1 text-[13px] font-medium text-slate-700 dark:text-slate-200"><?= e($department['phone'] ?? '—') ?></p>
        </div>
        <div class="bg-white px-5 py-4 dark:bg-slate-900">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Email</p>
            <p class="mt-1 truncate text-[13px] font-medium text-teal-600 dark:text-teal-400"><?= e($department['email'] ?? '—') ?></p>
        </div>
    </div>
</div>

<!-- Doctor roster -->
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Assigned doctors</h2>
        <p class="text-xs text-slate-400"><?= count($department['doctors']) ?> doctor<?= count($department['doctors']) !== 1 ? 's' : '' ?> in this department</p>
    </div>
    <?php if ($department['doctors'] === []): ?>
        <div class="p-5">
            <?= $this->insert('components/empty-state', ['icon' => 'stethoscope', 'compact' => true, 'title' => 'No doctors assigned', 'message' => 'Assign doctors to this department from their profile pages.']) ?>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($department['doctors'] as $doc): ?>
                <li class="flex items-center gap-3.5 px-5 py-3.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-navy-600/90 text-[11px] font-semibold text-white">
                        <?= e(strtoupper(substr((string) $doc['user_name'], 0, 1))) ?>
                    </span>
                    <div class="min-w-0 flex-1">
                        <a href="<?= url('/admin/doctors/' . (int) $doc['id']) ?>" class="text-[13.5px] font-medium text-slate-800 hover:underline dark:text-slate-100"><?= e($doc['user_name']) ?></a>
                        <p class="text-xs text-slate-400"><?= e($doc['specialization']) ?> · <?= e($doc['doctor_code']) ?></p>
                    </div>
                    <span class="badge <?= $doc['status'] === 'active' ? 'badge-emerald' : ($doc['status'] === 'on_leave' ? 'badge-amber' : 'badge-slate') ?>"><?= e(ucfirst(str_replace('_', ' ', $doc['status']))) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Staff roster -->
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Assigned staff</h2>
        <p class="text-xs text-slate-400"><?= count($department['staff']) ?> staff member<?= count($department['staff']) !== 1 ? 's' : '' ?> in this department</p>
    </div>
    <?php if ($department['staff'] === []): ?>
        <div class="p-5">
            <?= $this->insert('components/empty-state', ['icon' => 'users', 'compact' => true, 'title' => 'No staff assigned', 'message' => 'Assign staff to this department from their profile pages.']) ?>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($department['staff'] as $s): ?>
                <li class="flex items-center gap-3.5 px-5 py-3.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-500/80 text-[11px] font-semibold text-white">
                        <?= e(strtoupper(substr((string) $s['user_name'], 0, 1))) ?>
                    </span>
                    <div class="min-w-0 flex-1">
                        <a href="<?= url('/admin/staff/' . (int) $s['id']) ?>" class="text-[13.5px] font-medium text-slate-800 hover:underline dark:text-slate-100"><?= e($s['user_name']) ?></a>
                        <p class="text-xs text-slate-400"><?= e($s['job_title']) ?> · <?= e($s['employee_id']) ?></p>
                    </div>
                    <span class="badge <?= $s['status'] === 'active' ? 'badge-emerald' : ($s['status'] === 'on_leave' ? 'badge-amber' : 'badge-slate') ?>"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
