<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Ward & Room Configuration'; $active = 'beds';
$breadcrumbs = ['Operations' => null, 'Beds' => url('/admin/beds'), 'Wards' => ''];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Ward & Room Configuration</h1></div>
    <a href="<?= url('/admin/beds') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back to beds</a>
</div>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Add ward</h2></div>
        <form method="post" action="<?= url('/admin/beds/wards') ?>" class="space-y-3 p-5"><?= csrf_field() ?>
            <div><label class="label">Name <span class="text-rose-500">*</span></label><input type="text" name="name" class="input" required placeholder="e.g. Cardiology Ward"></div>
            <div><label class="label">Floor</label><input type="text" name="floor" class="input" placeholder="e.g. 3rd"></div>
            <div><label class="label">Description</label><textarea name="description" rows="2" class="input"></textarea></div>
            <button type="submit" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Create ward</button>
        </form>
    </div>
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Add room + beds</h2></div>
        <form method="post" action="<?= url('/admin/beds/rooms') ?>" class="space-y-3 p-5"><?= csrf_field() ?>
            <div><label class="label">Ward</label><select name="ward_id" class="input" required><?php foreach ($wards as $w): ?><option value="<?= (int) $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Room number <span class="text-rose-500">*</span></label><input type="text" name="room_number" class="input" required placeholder="e.g. 301"></div>
                <div><label class="label">Type</label><select name="room_type" class="input"><?php foreach (App\Models\Room::TYPES as $t): ?><option value="<?= e($t) ?>"><?= e(ucfirst(str_replace('_', ' ', $t))) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Daily rate</label><input type="number" step="0.01" name="daily_rate" class="input" value="0"></div>
                <div><label class="label">Bed count</label><input type="number" name="bed_count" class="input" value="1" min="1" max="10"></div>
            </div>
            <button type="submit" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Create room</button>
        </form>
    </div>
</div>
<div class="mt-4 space-y-3">
    <?php foreach ($wards as $w): ?>
    <div class="card"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-800"><h3 class="text-sm font-semibold"><?= e($w['name']) ?><?php if ($w['floor']): ?> <span class="text-slate-400 font-normal">· Floor <?= e($w['floor']) ?></span><?php endif; ?></h3><span class="badge badge-slate"><?= (int) $w['room_count'] ?> rooms · <?= (int) $w['bed_count'] ?> beds</span></div>
        <?php $rooms = App\Models\Room::forWard((int) $w['id']); if ($rooms): ?>
        <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-4 lg:grid-cols-6">
            <?php foreach ($rooms as $r): for ($bi = 1; $bi <= (int) $r['bed_count']; $bi++): ?>
            <div class="rounded-lg border p-2 text-center text-[11px] <?= $bi <= (int) $r['occupied'] ? 'border-navy-300 bg-navy-50 dark:border-navy-700 dark:bg-navy-950/20' : 'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/20' ?>">
                <p class="font-mono text-slate-400"><?= e($r['room_number']) ?>/<?= $bi ?></p><p class="capitalize text-slate-500"><?= e(str_replace('_', ' ', $r['room_type'])) ?></p><p class="text-slate-400"><?= e(format_money($r['daily_rate'], (string) setting('currency', 'BDT'))) ?>/d</p>
            </div>
            <?php endfor; endforeach; ?>
        </div>
        <?php else: ?><p class="px-5 py-3 text-[12px] text-slate-400">No rooms yet.</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php $this->end(); ?>
