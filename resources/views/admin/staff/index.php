<?php

declare(strict_types=1);

/**
 * Staff directory — searchable, filterable, with today's shift indicator.
 * $staff, $total, $page, $pages, $perPage, $filters, $counts, $departments, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Staff';
$active = 'staff';
$breadcrumbs = ['Hospital' => null, 'Staff' => ''];

$statusTone = ['active' => 'badge-emerald', 'on_leave' => 'badge-amber', 'inactive' => 'badge-slate', 'terminated' => 'badge-rose'];
$employmentTone = ['full_time' => 'badge-teal', 'part_time' => 'badge-slate', 'contract' => 'badge-navy', 'visiting' => 'badge-amber', 'intern' => 'badge-slate'];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Staff</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $counts['active'] ?> active
            <?php if ($counts['on_leave'] > 0): ?>· <span class="text-amber-600 dark:text-amber-400"><?= (int) $counts['on_leave'] ?> on leave</span><?php endif; ?>
        </p>
    </div>
    <?php if (can('staff.create')): ?>
        <a href="<?= url('/admin/staff/create') ?>" class="btn btn-primary">
            <i data-lucide="user-round-plus" class="h-4 w-4"></i>New staff profile
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= url('/admin/staff') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search name, employee ID, job title, email…" class="input pl-9 text-sm">
        </div>
        <select name="department_id" class="input w-auto text-sm">
            <option value="">All departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (string) $filters['department_id'] === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="input w-auto text-sm">
            <option value="">All statuses</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="on_leave" <?= $filters['status'] === 'on_leave' ? 'selected' : '' ?>>On leave</option>
            <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="terminated" <?= $filters['status'] === 'terminated' ? 'selected' : '' ?>>Terminated</option>
        </select>
        <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
        <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['department_id'] !== '' || $filters['status'] !== ''): ?>
            <a href="<?= url('/admin/staff') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($staff === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $filters['search'] !== '' ? 'search-x' : 'users',
                'title'   => $filters['search'] !== '' ? 'No staff match your filters' : 'No staff profiles yet',
                'message' => $filters['search'] !== '' ? 'Try widening the search.' : 'Create staff profiles to manage employment details, shifts and leaves.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Job title</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Hire date</th>
                    <th>Today</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($staff as $s): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-500/80 text-[11px] font-semibold text-white">
                                    <?= e(strtoupper(substr((string) $s['user_name'], 0, 1))) ?>
                                </span>
                                <div class="min-w-0">
                                    <a href="<?= url('/admin/staff/' . (int) $s['id']) ?>" class="truncate text-[13.5px] font-medium text-slate-800 hover:underline dark:text-slate-100"><?= e($s['user_name']) ?></a>
                                    <p class="font-mono text-[11px] text-slate-400"><?= e($s['employee_id']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="text-[13px] text-slate-600 dark:text-slate-300"><?= e($s['job_title']) ?></td>
                        <td class="text-[13px] text-slate-500 dark:text-slate-400"><?= e($s['department_name'] ?? '—') ?></td>
                        <td><span class="badge <?= $employmentTone[$s['employment_type']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $s['employment_type']))) ?></span></td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400"><?= e($s['hire_date'] ? format_date($s['hire_date'], 'M j, Y') : '—') ?></td>
                        <td>
                            <?php if ((int) $s['shifts_today'] > 0): ?>
                                <span class="badge badge-emerald"><?= (int) $s['shifts_today'] ?> shift<?= (int) $s['shifts_today'] > 1 ? 's' : '' ?></span>
                            <?php else: ?>
                                <span class="text-[12px] text-slate-300 dark:text-slate-600">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $statusTone[$s['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span></td>
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
