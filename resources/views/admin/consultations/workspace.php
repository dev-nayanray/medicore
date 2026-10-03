<?php

declare(strict_types=1);

/**
 * Doctor workspace — today's consultations, quick actions, summary tiles.
 * $doctor, $today, $consultations, $counts, $rxCounts
 */

$this->extend('layouts/admin');
$title = 'Clinical Workspace';
$active = 'consultations';
$breadcrumbs = ['Clinical' => null, 'Workspace' => ''];

$statusTone = ['draft' => 'badge-amber', 'finalized' => 'badge-emerald', 'amended' => 'badge-violet'];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            Clinical Workspace
            <?php if ($doctor): ?>
                <span class="ml-2 text-sm font-normal text-slate-400">Dr. <?= e($doctor['user_name'] ?? '') ?></span>
            <?php endif; ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= e(format_date($today, 'l, F j, Y')) ?> · <?= (int) $counts['today'] ?> consultation<?= $counts['today'] !== 1 ? 's' : '' ?> today
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/consultations') ?>" class="btn btn-secondary">
            <i data-lucide="list" class="h-4 w-4"></i><span class="hidden sm:inline">All records</span>
        </a>
        <?php if (can('consultations.create')): ?>
            <a href="<?= url('/admin/consultations/create') ?>" class="btn btn-primary">
                <i data-lucide="stethoscope" class="h-4 w-4"></i>New consultation
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary tiles -->
<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="clipboard-list" class="h-7 w-7 text-amber-600 dark:text-amber-400"></i>
        <div><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= (int) $counts['drafts'] ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Drafts</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="check-check" class="h-7 w-7 text-emerald-600 dark:text-emerald-400"></i>
        <div><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= (int) $counts['finalized'] ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Finalized</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="calendar-days" class="h-7 w-7 text-teal-600 dark:text-teal-400"></i>
        <div><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= (int) $counts['today'] ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Today</p></div>
    </div>
    <div class="card flex items-center gap-3 px-4 py-3">
        <i data-lucide="prescription" class="h-7 w-7 text-violet-600 dark:text-violet-400"></i>
        <div><p class="text-2xl font-bold text-slate-800 dark:text-slate-100"><?= (int) ($rxCounts['today'] ?? 0) ?></p><p class="text-[10px] uppercase tracking-wide text-slate-400">Rx today</p></div>
    </div>
</div>

<!-- Today's consultations -->
<div class="card">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Today's consultations</h2>
        <span class="badge badge-slate"><?= count($consultations) ?></span>
    </div>
    <?php if ($consultations === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => 'stethoscope',
                'title'   => 'No consultations recorded today',
                'message' => 'Start a consultation from the appointment queue or a walk-in visit.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Patient</th>
                    <th>Complaint</th>
                    <th>Time</th>
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
                        <td class="truncate text-[13px] text-slate-500 dark:text-slate-400" style="max-width: 200px;"><?= e($c['chief_complaint'] ?? '—') ?></td>
                        <td class="whitespace-nowrap font-mono text-[12px] text-slate-500 dark:text-slate-400"><?= e(substr((string) $c['consultation_date'], 11, 5)) ?></td>
                        <td><span class="badge <?= $statusTone[$c['status']] ?? 'badge-slate' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
