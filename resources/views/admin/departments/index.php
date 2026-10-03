<?php

declare(strict_types=1);

/**
 * Department directory — searchable, filterable, with member counts.
 * $departments, $total, $page, $pages, $perPage, $filters, $counts, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Departments';
$active = 'departments';
$breadcrumbs = ['Hospital' => null, 'Departments' => ''];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Departments</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $counts['active'] ?> active · <?= (int) $counts['archived'] ?> archived
        </p>
    </div>
    <?php if (can('departments.create')): ?>
        <a href="<?= url('/admin/departments/create') ?>" class="btn btn-primary">
            <i data-lucide="plus" class="h-4 w-4"></i>New department
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= url('/admin/departments') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search name, location, email…" class="input pl-9 text-sm">
        </div>
        <select name="status" class="input w-auto text-sm">
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All</option>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['status'] !== 'active'): ?>
            <a href="<?= url('/admin/departments') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($departments === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $filters['search'] !== '' ? 'search-x' : 'building-2',
                'title'   => $filters['search'] !== '' ? 'No departments match your filters' : 'No departments yet',
                'message' => $filters['search'] !== '' ? 'Try widening the search.' : 'Create the first department to get started.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Department</th>
                    <th>Head</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th class="text-center">Doctors</th>
                    <th class="text-center">Staff</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($departments as $d): ?>
                    <tr class="<?= $d['archived_at'] !== null ? 'opacity-60' : '' ?>">
                        <td>
                            <a href="<?= url('/admin/departments/' . (int) $d['id']) ?>" class="font-medium text-slate-800 hover:underline dark:text-slate-100">
                                <?= e($d['name']) ?>
                            </a>
                            <span class="block font-mono text-[11px] text-slate-400"><?= e($d['slug']) ?></span>
                        </td>
                        <td class="text-[13px] text-slate-600 dark:text-slate-300"><?= e($d['head_doctor_name'] ?? '—') ?></td>
                        <td class="text-[13px] text-slate-500 dark:text-slate-400"><?= e($d['location'] ?? '—') ?></td>
                        <td class="text-[13px] text-slate-500 dark:text-slate-400">
                            <?php if ($d['phone']): ?><span class="block"><?= e($d['phone']) ?></span><?php endif; ?>
                            <?php if ($d['email']): ?><span class="block text-teal-600 dark:text-teal-400"><?= e($d['email']) ?></span><?php endif; ?>
                            <?php if (!$d['phone'] && !$d['email']): ?>—<?php endif; ?>
                        </td>
                        <td class="text-center font-medium text-slate-700 dark:text-slate-200"><?= (int) $d['doctor_count'] ?></td>
                        <td class="text-center font-medium text-slate-700 dark:text-slate-200"><?= (int) $d['staff_count'] ?></td>
                        <td>
                            <?php if ($d['archived_at'] !== null): ?>
                                <span class="badge badge-slate">Archived</span>
                            <?php elseif ((int) $d['is_active'] === 1): ?>
                                <span class="badge badge-emerald">Active</span>
                            <?php else: ?>
                                <span class="badge badge-amber">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->insert('components/pagination', [
            'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage,
            'baseUrl' => $baseUrl,
        ]) ?>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
