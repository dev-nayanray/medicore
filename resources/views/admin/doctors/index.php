<?php

declare(strict_types=1);

/**
 * Doctor directory — cards with department filter, status filter, search.
 * $doctors, $total, $page, $pages, $perPage, $filters, $counts, $departments, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Doctors';
$active = 'doctors';
$breadcrumbs = ['Hospital' => null, 'Doctors' => ''];

$statusTone = ['active' => 'badge-emerald', 'on_leave' => 'badge-amber', 'inactive' => 'badge-slate'];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Doctors</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $counts['active'] ?> active
            <?php if ($counts['on_leave'] > 0): ?>· <span class="text-amber-600 dark:text-amber-400"><?= (int) $counts['on_leave'] ?> on leave</span><?php endif; ?>
        </p>
    </div>
    <?php if (can('doctors.create')): ?>
        <a href="<?= url('/admin/doctors/create') ?>" class="btn btn-primary">
            <i data-lucide="user-round-plus" class="h-4 w-4"></i>New doctor profile
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= url('/admin/doctors') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search name, code, specialization, phone…" class="input pl-9 text-sm">
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
        </select>
        <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
        <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filters['search'] !== '' || $filters['department_id'] !== '' || $filters['status'] !== ''): ?>
            <a href="<?= url('/admin/doctors') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($doctors === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $filters['search'] !== '' ? 'search-x' : 'stethoscope',
                'title'   => $filters['search'] !== '' ? 'No doctors match your filters' : 'No doctor profiles yet',
                'message' => $filters['search'] !== '' ? 'Try widening the search.' : 'Create doctor profiles for users with the doctor role.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($doctors as $doc): ?>
                <a href="<?= url('/admin/doctors/' . (int) $doc['id']) ?>" class="card !rounded-xl p-4 transition-shadow hover:shadow-md dark:bg-slate-900/60">
                    <div class="flex items-start gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-navy-700 to-navy-900 text-sm font-bold text-white">
                            <?= e(strtoupper(substr((string) $doc['user_name'], 0, 1))) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[14px] font-semibold text-slate-800 dark:text-slate-100"><?= e($doc['user_name']) ?></p>
                            <p class="font-mono text-[11px] text-teal-600 dark:text-teal-400"><?= e($doc['doctor_code']) ?></p>
                            <p class="mt-0.5 truncate text-[12.5px] text-slate-500 dark:text-slate-400"><?= e($doc['specialization']) ?></p>
                        </div>
                        <span class="badge <?= $statusTone[$doc['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $doc['status']))) ?></span>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-[11.5px] text-slate-400 dark:border-slate-800">
                        <span class="flex items-center gap-1"><i data-lucide="building-2" class="h-3 w-3"></i><?= e($doc['department_name'] ?? 'Unassigned') ?></span>
                        <span class="flex items-center gap-1"><i data-lucide="calendar-days" class="h-3 w-3"></i><?= (int) $doc['visit_count'] ?> visits</span>
                        <span class="flex items-center gap-1"><i data-lucide="clock" class="h-3 w-3"></i><?= (int) $doc['schedule_slots'] ?> slots</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?= $this->insert('components/pagination', [
            'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage,
            'baseUrl' => $baseUrl,
        ]) ?>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
