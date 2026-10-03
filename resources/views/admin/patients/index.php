<?php

declare(strict_types=1);

/**
 * Patient directory — search, filters, sortable columns, pagination, export.
 * $patients, $total, $page, $pages, $perPage, $filters, $counts, $bloodGroups, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Patients';
$active = 'patients';
$breadcrumbs = ['Hospital' => null, 'Patients' => ''];

// Sorting link helper: toggles direction per column.
if (!function_exists('sort_link')) {
    function sort_link(array $filters, string $column, string $label, string $baseUrl): string
    {
        $active = ($filters['sort'] ?? '') === $column;
        $nextDir = ($active && ($filters['dir'] ?? '') === 'asc') ? 'desc' : 'asc';
        $arrow = $active ? (($filters['dir'] ?? '') === 'asc' ? '▲' : '▼') : '';
        $cls = $active ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300';
        return '<a href="' . e($baseUrl . 'sort=' . $column . '&dir=' . $nextDir) . '" class="inline-flex items-center gap-1 ' . $cls . '">'
            . e($label) . ($arrow !== '' ? '<span class="text-[9px]">' . $arrow . '</span>' : '') . '</a>';
    }
}

$exportQuery = http_build_query(array_filter([
    'q' => $filters['search'], 'gender' => $filters['gender'], 'blood_group' => $filters['blood_group'],
    'status' => $filters['status'] !== 'not_archived' ? $filters['status'] : '',
]));

$hasFilters = $filters['search'] !== '' || $filters['gender'] !== '' || $filters['blood_group'] !== '' || $filters['status'] !== 'not_archived';
?>
<?php $this->section('content'); ?>

<!-- Page header -->
<div class="page-header mb-6">
    <div>
        <h1 class="page-title">Patients</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            <span class="font-medium text-slate-700 dark:text-slate-300"><?= (int) $counts['active'] ?></span> active
            · <span class="text-slate-500 dark:text-slate-400"><?= (int) $counts['archived'] ?> archived</span>
            · <span class="text-teal-600 dark:text-teal-400 font-medium">+<?= (int) $counts['this_month'] ?> this month</span>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/patients/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>" class="btn btn-secondary">
            <i data-lucide="download" class="h-4 w-4"></i><span class="hidden sm:inline">Export CSV</span>
        </a>
        <?php if (can('patients.create')): ?>
            <a href="<?= url('/admin/patients/create') ?>" class="btn btn-primary">
                <i data-lucide="user-round-plus" class="h-4 w-4"></i>Register patient
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Quick stat strip -->
<div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Total</p>
        <p class="mt-1 text-xl font-bold text-slate-800 dark:text-slate-100"><?= number_format((int) $total) ?></p>
    </div>
    <div class="rounded-xl border border-emerald-200/60 bg-emerald-50/50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Active</p>
        <p class="mt-1 text-xl font-bold text-emerald-800 dark:text-emerald-300"><?= (int) $counts['active'] ?></p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Archived</p>
        <p class="mt-1 text-xl font-bold text-slate-600 dark:text-slate-400"><?= (int) $counts['archived'] ?></p>
    </div>
    <div class="rounded-xl border border-teal-200/60 bg-teal-50/50 p-3 dark:border-teal-900/40 dark:bg-teal-950/20">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-400">New this month</p>
        <p class="mt-1 text-xl font-bold text-teal-800 dark:text-teal-300">+<?= (int) $counts['this_month'] ?></p>
    </div>
    <div class="hidden rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900 lg:block">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Filtered</p>
        <p class="mt-1 text-xl font-bold text-slate-600 dark:text-slate-400"><?= number_format((int) $total) ?></p>
    </div>
    <div class="hidden rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900 lg:block">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Per page</p>
        <p class="mt-1 text-xl font-bold text-slate-600 dark:text-slate-400"><?= (int) $perPage ?></p>
    </div>
</div>

<div class="card">
    <!-- Filter toolbar -->
    <form method="get" action="<?= url('/admin/patients') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search name, patient ID, phone, NID…" class="input pl-9 text-sm">
        </div>
        <select name="gender" class="input w-auto text-sm">
            <option value="">All genders</option>
            <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= $filters['gender'] === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <select name="blood_group" class="input w-auto text-sm">
            <option value="">All blood groups</option>
            <?php foreach ($bloodGroups as $bg): ?>
                <option value="<?= e($bg) ?>" <?= $filters['blood_group'] === $bg ? 'selected' : '' ?>><?= e($bg) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="input w-auto text-sm">
            <option value="not_archived" <?= $filters['status'] === 'not_archived' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All</option>
        </select>
        <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
        <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">
        <button type="submit" class="btn btn-secondary">
            <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>Filter
        </button>
        <?php if ($hasFilters): ?>
            <a href="<?= url('/admin/patients') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($patients === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $hasFilters ? 'search-x' : 'user-round',
                'title'   => $hasFilters ? 'No patients match your filters' : 'No patients registered yet',
                'message' => $hasFilters
                    ? 'Try widening the search or clearing the filters.'
                    : 'Register the first patient with the "Register patient" button.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto scrollbar-thin">
            <table class="table">
                <thead>
                <tr>
                    <th><?= sort_link($filters, 'patient_code', 'Patient ID', $baseUrl) ?></th>
                    <th><?= sort_link($filters, 'name', 'Patient', $baseUrl) ?></th>
                    <th><?= sort_link($filters, 'date_of_birth', 'Age / Gender', $baseUrl) ?></th>
                    <th>Blood</th>
                    <th>Contact</th>
                    <th><?= sort_link($filters, 'visits', 'Visits', $baseUrl) ?></th>
                    <th><?= sort_link($filters, 'created_at', 'Registered', $baseUrl) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($patients as $p): ?>
                    <tr class="<?= $p['archived_at'] !== null ? 'opacity-60' : '' ?> transition-colors">
                        <td>
                            <a href="<?= url('/admin/patients/' . (int) $p['id']) ?>" class="font-mono text-[12.5px] font-medium text-teal-700 hover:underline dark:text-teal-400">
                                <?= e($p['patient_code']) ?>
                            </a>
                            <?php if ($p['archived_at'] !== null): ?>
                                <span class="badge badge-slate ml-1">Archived</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-[11px] font-semibold text-white ring-2 ring-white dark:ring-slate-900 <?= $p['gender'] === 'female' ? 'bg-gradient-to-br from-rose-400 to-rose-600' : 'bg-gradient-to-br from-navy-500 to-navy-700' ?>">
                                    <?= e(strtoupper(substr((string) $p['first_name'], 0, 1) . substr((string) $p['last_name'], 0, 1))) ?>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-[13.5px] font-medium text-slate-800 dark:text-slate-100"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></p>
                                    <p class="truncate text-xs text-slate-400"><?= e($p['city'] ?? '—') ?><?= (int) $p['document_count'] > 0 ? ' · ' . (int) $p['document_count'] . ' doc' . ((int) $p['document_count'] > 1 ? 's' : '') : '' ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400">
                            <?= (int) $p['age'] ?> yrs · <?= e(ucfirst((string) $p['gender'])) ?>
                        </td>
                        <td><?= $p['blood_group'] !== null ? '<span class="badge badge-rose">' . e($p['blood_group']) . '</span>' : '<span class="text-slate-300">—</span>' ?></td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400"><?= e($p['phone']) ?></td>
                        <td class="whitespace-nowrap">
                            <span class="text-[13px] font-medium text-slate-700 dark:text-slate-200"><?= (int) $p['visit_count'] ?></span>
                            <?php if ($p['last_visit'] !== null): ?>
                                <span class="block text-[11px] text-slate-400">last <?= e(format_date($p['last_visit'], 'M j, Y')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400"><?= e(format_date($p['created_at'], 'M j, Y')) ?></td>
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
