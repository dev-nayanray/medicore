<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Admissions'; $active = 'admissions';
$breadcrumbs = ['Operations' => null, 'Admissions' => ''];
$statusTone = ['admitted' => 'badge-teal', 'discharged' => 'badge-slate', 'transferred_out' => 'badge-navy'];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Admissions</h1>
        <p class="mt-0.5 text-sm text-slate-500"><?= (int) $counts['admitted'] ?> currently admitted · <?= (int) $counts['today'] ?> admitted today · <?= (int) $counts['discharged_today'] ?> discharged today</p></div>
    <?php if (can('admissions.create')): ?><a href="<?= url('/admin/admissions/create') ?>" class="btn btn-primary"><i data-lucide="door-open" class="h-4 w-4"></i>Admit patient</a><?php endif; ?>
</div>
<div class="card">
    <form method="get" action="<?= url('/admin/admissions') ?>" class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="relative w-full max-w-xs sm:flex-1"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i><input type="text" name="q" value="<?= e($filters['search']) ?>" placeholder="Search code, patient…" class="input pl-9 text-sm"></div>
        <select name="status" class="input w-auto text-sm"><?php foreach (App\Models\Admission::STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?><option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>All</option></select>
        <select name="ward_id" class="input w-auto text-sm"><option value="">All wards</option><?php foreach ($wards as $w): ?><option value="<?= (int) $w['id'] ?>" <?= (string) $filters['ward_id'] === (string) $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="input w-auto text-sm"><input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="input w-auto text-sm"><button type="submit" class="btn btn-secondary">Filter</button>
    </form>
    <?php if ($rows === []): ?><div class="p-6"><?= $this->insert('components/empty-state', ['icon' => 'door-closed', 'title' => 'No admissions', 'message' => 'Admit a patient to start.']) ?></div>
    <?php else: ?>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>Code</th><th>Patient</th><th>Ward/Bed</th><th>Admitted</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $a): ?>
        <tr><td><a href="<?= url('/admin/admissions/' . (int) $a['id']) ?>" class="font-mono text-[12px] text-teal-600 hover:underline"><?= e($a['admission_code']) ?></a></td>
        <td><span class="font-medium"><?= e($a['patient_name']) ?></span> <span class="font-mono text-[11px] text-slate-400"><?= e($a['patient_code']) ?></span></td>
        <td class="text-[13px] text-slate-500"><?= e($a['ward_name'] ?? '—') ?> / <?= e($a['room_number'] ?? '—') ?> / <?= e($a['bed_number'] ?? '—') ?></td>
        <td class="text-[13px] text-slate-500"><?= e(format_date(substr((string) $a['admission_date'], 0, 10), 'M j, Y')) ?></td>
        <td><span class="badge <?= $statusTone[$a['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $a['status']))) ?></span></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?= $this->insert('components/pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage, 'baseUrl' => $baseUrl]) ?>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
