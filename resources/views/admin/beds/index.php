<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Bed Management'; $active = 'beds';
$breadcrumbs = ['Operations' => null, 'Beds' => ''];
$statusTone = ['available' => 'badge-emerald', 'occupied' => 'badge-navy', 'maintenance' => 'badge-amber', 'cleaning' => 'badge-slate'];
$statusColor = ['available' => 'bg-emerald-500', 'occupied' => 'bg-navy-500', 'maintenance' => 'bg-amber-500', 'cleaning' => 'bg-slate-400'];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Bed Management</h1>
        <p class="mt-0.5 text-sm text-slate-500"><?= (int) $counts['total'] ?> total beds · <span class="text-emerald-600"><?= (int) $counts['available'] ?> available</span> · <span class="text-navy-600"><?= (int) $counts['occupied'] ?> occupied</span> · <span class="text-amber-600"><?= (int) $counts['maintenance'] ?> maintenance</span> · <?= (int) $admissionCounts['admitted'] ?> patients admitted</p></div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/beds/wards') ?>" class="btn btn-secondary"><i data-lucide="building-2" class="h-4 w-4"></i>Ward config</a>
        <?php if (can('admissions.create')): ?><a href="<?= url('/admin/admissions/create') ?>" class="btn btn-primary"><i data-lucide="door-open" class="h-4 w-4"></i>Admit patient</a><?php endif; ?>
    </div>
</div>
<div class="space-y-4">
    <?php foreach ($wards as $w): ?>
    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <h2 class="text-sm font-semibold"><?= e($w['name']) ?><?php if ($w['floor']): ?> <span class="text-slate-400 font-normal">· Floor <?= e($w['floor']) ?></span><?php endif; ?></h2>
            <div class="flex items-center gap-3 text-[12px]">
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> <?= (int) $w['available_beds'] ?> available</span>
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-navy-500"></span> <?= (int) $w['occupied_beds'] ?> occupied</span>
                <span class="text-slate-400"><?= (int) $w['bed_count'] ?> total</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
            <?php
            $wardBeds = array_filter($beds, fn($b) => (int) $b['ward_name'] === 0 || $b['ward_name'] === $w['name']);
            // Actually filter by matching ward name
            $wardBeds = array_filter($beds, fn($b) => $b['ward_name'] === $w['name']);
            foreach ($wardBeds as $b): ?>
                <a href="<?= url('/admin/admissions?' . http_build_query(['q' => $b['patient_code'] ?? ''])) ?>" class="block rounded-lg border-l-4 p-2.5 transition-shadow hover:shadow-md <?= $b['status'] === 'available' ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/20' : ($b['status'] === 'occupied' ? 'border-navy-400 bg-navy-50 dark:bg-navy-950/20' : ($b['status'] === 'maintenance' ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/20' : 'border-slate-300 bg-slate-50 dark:bg-slate-800/40')) ?>">
                    <p class="font-mono text-[11px] text-slate-400"><?= e($b['room_number']) ?>/<?= e($b['bed_number']) ?></p>
                    <p class="text-[11px] font-medium <?= $b['status'] === 'occupied' ? 'text-navy-700 dark:text-navy-300' : 'text-slate-600 dark:text-slate-300' ?> truncate"><?= $b['status'] === 'occupied' ? e($b['patient_name'] ?? '—') : e(ucfirst($b['status'])) ?></p>
                    <?php if ($b['status'] === 'occupied' && $b['patient_code']): ?><p class="font-mono text-[9px] text-slate-400"><?= e($b['patient_code']) ?></p><?php endif; ?>
                    <p class="text-[9px] capitalize text-slate-400"><?= e($b['room_type']) ?> · <?= e(format_money($b['daily_rate'], (string) setting('currency', 'BDT'))) ?>/day</p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php $this->end(); ?>
