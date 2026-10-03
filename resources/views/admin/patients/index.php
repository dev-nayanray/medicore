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
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Patients</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $counts['active'] ?> active · <?= (int) $counts['archived'] ?> archived ·
            <span class="text-teal-600 dark:text-teal-400">+<?= (int) $counts['this_month'] ?> this month</span>
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
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['gender'] !== '' || $filters['blood_group'] !== '' || $filters['status'] !== 'not_archived'): ?>
            <a href="<?= url('/admin/patients') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($patients === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $filters['search'] !== '' || $filters['gender'] !== '' || $filters['blood_group'] !== '' ? 'search-x' : 'user-round',
                'title'   => $filters['search'] !== '' || $filters['gender'] !== '' || $filters['blood_group'] !== '' ? 'No patients match your filters' : 'No patients registered yet',
                'message' => $filters['search'] !== '' || $filters['gender'] !== '' || $filters['blood_group'] !== ''
                    ? 'Try widening the search or clearing the filters.'
                    : 'Register the first patient with the "Register patient" button.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
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
                    <tr class="<?= $p['archived_at'] !== null ? 'opacity-60' : '' ?>">
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
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-[11px] font-semibold text-white <?= $p['gender'] === 'female' ? 'bg-rose-400/90' : 'bg-navy-600/90' ?>">
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
