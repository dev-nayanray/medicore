<?php

declare(strict_types=1);

/**
 * Consultation directory — filters, sorting, pagination.
 * $consultations, $total, $page, $pages, $perPage, $filters, $counts, $doctors, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Consultations';
$active = 'consultations';
$breadcrumbs = ['Clinical' => null, 'Consultations' => ''];

$statusTone = ['draft' => 'badge-amber', 'finalized' => 'badge-emerald', 'amended' => 'badge-violet'];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Consultations</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $counts['total'] ?> total · <?= (int) $counts['drafts'] ?> drafts · <?= (int) $counts['finalized'] ?> finalized
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/consultations/workspace') ?>" class="btn btn-secondary">
            <i data-lucide="layout-dashboard" class="h-4 w-4"></i><span class="hidden sm:inline">Workspace</span>
        </a>
        <?php if (can('consultations.create')): ?>
            <a href="<?= url('/admin/consultations/create') ?>" class="btn btn-primary">
                <i data-lucide="stethoscope" class="h-4 w-4"></i>New consultation
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= url('/admin/consultations') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search code, patient, complaint, diagnosis…" class="input pl-9 text-sm">
        </div>
        <select name="status" class="input w-auto text-sm">
            <option value="">All statuses</option>
            <?php foreach (App\Models\Consultation::STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="doctor_id" class="input w-auto text-sm">
            <option value="">All doctors</option>
            <?php foreach ($doctors as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>" <?= (string) $filters['doctor_id'] === (string) $doc['id'] ? 'selected' : '' ?>><?= e($doc['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="input w-auto text-sm" title="From">
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="input w-auto text-sm" title="To">
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <?php if ($consultations === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $filters['search'] !== '' ? 'search-x' : 'clipboard-list',
                'title'   => $filters['search'] !== '' ? 'No consultations match your filters' : 'No consultations yet',
                'message' => $filters['search'] !== '' ? 'Try widening the filters.' : 'Create the first consultation from the workspace.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Complaint</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($consultations as $c): ?>
                    <tr>
                        <td>
                            <a href="<?= url('/admin/consultations/' . (int) $c['id']) ?>" class="font-mono text-[12px] font-medium text-teal-700 hover:underline dark:text-teal-400">
                                <?= e($c['consultation_code']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="text-[13px] font-medium text-slate-800 dark:text-slate-100"><?= e($c['patient_name']) ?></span>
                            <span class="font-mono text-[11px] text-slate-400"><?= e($c['patient_code']) ?></span>
                        </td>
                        <td class="text-[13px] text-slate-600 dark:text-slate-300"><?= e($c['doctor_name'] ?? '—') ?></td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400"><?= e(format_date(substr((string) $c['consultation_date'], 0, 10), 'M j, Y')) ?></td>
                        <td class="truncate text-[13px] text-slate-500 dark:text-slate-400" style="max-width: 180px;"><?= e($c['chief_complaint'] ?? '—') ?></td>
                        <td><span class="badge <?= $statusTone[$c['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
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
