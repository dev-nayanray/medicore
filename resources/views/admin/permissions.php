<?php

declare(strict_types=1);

/**
 * Permission catalogue browser. $permissions (grouped), $roleMap, $overrideMap, $totalCount
 */

$this->extend('layouts/admin');
$title = 'Permissions';
$active = 'permissions';
$breadcrumbs = ['Administration' => null, 'Permissions' => ''];
?>
<?php $this->section('content'); ?>

<div class="mb-6">
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Permission Catalogue</h1>
    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
        <?= (int) $totalCount ?> abilities across <?= count($permissions) ?> modules · the catalogue ships with migrations; assign them via
        <a href="<?= url('/admin/roles') ?>" class="font-medium text-teal-600 hover:underline dark:text-teal-400">roles</a> or per-user overrides
    </p>
</div>

<div class="space-y-4">
    <?php foreach ($permissions as $module => $perms): ?>
        <section class="card">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300">
                    <i data-lucide="boxes" class="h-4 w-4"></i>
                </span>
                <h2 class="text-sm font-semibold"><?= e(ucfirst($module)) ?> module</h2>
                <span class="badge badge-slate ml-auto"><?= count($perms) ?> abilities</span>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($perms as $p): ?>
                    <div class="flex flex-col gap-2 px-5 py-3.5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="font-mono text-[13px] font-medium text-slate-700 dark:text-slate-200"><?= e($p['name']) ?></p>
                            <p class="text-xs text-slate-400"><?= e($p['label']) ?> — <?= e($p['description'] ?? '') ?></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 lg:max-w-md lg:justify-end">
                            <?php foreach ($roleMap[(int) $p['id']] ?? [] as $roleBadge): ?>
                                <span class="badge <?= $roleBadge['slug'] === 'super-admin' ? 'badge-teal' : 'badge-navy' ?>"><?= e($roleBadge['name']) ?></span>
                            <?php endforeach; ?>
                            <?php if (($overrideMap[(int) $p['id']] ?? 0) > 0): ?>
                                <span class="badge badge-amber" title="Users with direct overrides"><?= (int) $overrideMap[(int) $p['id']] ?> override<?= (int) $overrideMap[(int) $p['id']] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                            <?php if (($roleMap[(int) $p['id']] ?? []) === [] && ($overrideMap[(int) $p['id']] ?? 0) === 0): ?>
                                <span class="badge badge-slate">unassigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php $this->end(); ?>
