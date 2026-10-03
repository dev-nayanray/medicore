<?php

declare(strict_types=1);

/**
 * Roles overview + permission matrix. $roles, $permissions (grouped)
 */

$this->extend('layouts/admin');
$title = 'Roles & Permissions';
$active = 'roles';
$breadcrumbs = ['Administration' => null, 'Roles & Permissions' => ''];

// role id -> set(permission name)
$matrix = [];
foreach ($roles as $role) {
    $ids = \App\Models\Role::permissionIds((int) $role['id']);
    $names = [];
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $names = array_map(
            static fn (array $r): string => (string) $r['name'],
            \App\Core\Database::query("SELECT name FROM permissions WHERE id IN ({$placeholders})", $ids)
        );
    }
    $matrix[(int) $role['id']] = array_fill_keys($names, true);
}
$permissionCount = 0;
foreach ($permissions as $perms) {
    $permissionCount += count($perms);
}
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Roles &amp; Permissions</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= count($roles) ?> roles · <?= $permissionCount ?> permissions ·
            <a href="<?= url('/admin/permissions') ?>" class="font-medium text-teal-600 hover:underline dark:text-teal-400">browse catalogue</a>
        </p>
    </div>
    <?php if (can('roles.create')): ?>
        <a href="<?= url('/admin/roles/create') ?>" class="btn btn-primary">
            <i data-lucide="shield-plus" class="h-4 w-4"></i>Create role
        </a>
    <?php endif; ?>
</div>

<!-- Role cards -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($roles as $role): ?>
        <?php $isSuper = $role['slug'] === 'super-admin'; ?>
        <div class="card flex flex-col p-5">
            <div class="flex items-start justify-between">
                <span class="grid h-10 w-10 place-items-center rounded-xl <?= $isSuper ? 'bg-gradient-to-br from-teal-400 to-teal-600 text-white' : 'bg-navy-500/10 text-navy-600 dark:text-navy-300' ?>">
                    <i data-lucide="<?= ['super-admin' => 'crown', 'doctor' => 'stethoscope', 'nurse' => 'heart-pulse', 'pharmacist' => 'pill', 'lab-technician' => 'flask-conical', 'accountant' => 'calculator'][$role['slug']] ?? 'shield' ?>" class="h-5 w-5"></i>
                </span>
                <div class="flex items-center gap-1.5">
                    <?php if ((int) $role['is_system'] === 1): ?>
                        <span class="badge badge-slate" title="Protected — cannot be deleted">System</span>
                    <?php endif; ?>
                    <?php if (!$isSuper && can('roles.update')): ?>
                        <a href="<?= url('/admin/roles/' . (int) $role['id'] . '/edit') ?>" class="icon-btn !p-1.5" title="Edit role">
                            <i data-lucide="pencil" class="h-4 w-4"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ((int) $role['is_system'] !== 1 && can('roles.delete')): ?>
                        <form method="post" action="<?= url('/admin/roles/' . (int) $role['id'] . '/delete') ?>"
                              data-confirm="Delete role|Delete <?= e($role['name']) ?>? Only possible when no users hold it.|danger">
                            <?= csrf_field() ?>
                            <button type="submit" class="icon-btn !p-1.5 hover:!text-rose-600" title="Delete role">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <p class="mt-3 text-[15px] font-semibold text-slate-800 dark:text-slate-100"><?= e($role['name']) ?></p>
            <p class="mt-1 line-clamp-2 min-h-[40px] text-xs leading-relaxed text-slate-400"><?= e($role['description'] ?? '') ?></p>
            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                <span class="inline-flex items-center gap-1.5"><i data-lucide="key-round" class="h-3.5 w-3.5"></i><?= $isSuper ? '∞' : (int) $role['permissions_count'] ?> perms</span>
                <span class="inline-flex items-center gap-1.5"><i data-lucide="user-round" class="h-3.5 w-3.5"></i><?= (int) $role['users_count'] ?> users</span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Permission matrix -->
<div class="card mt-6">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Permission matrix</h2>
        <p class="text-xs text-slate-400">
            Live from <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">permission_role</code> —
            Super Admin bypasses all checks. <?= can('roles.update') ? 'Edit any role to change its abilities.' : '' ?>
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th class="min-w-52">Permission</th>
                <?php foreach ($roles as $role): ?>
                    <th class="text-center"><?= e($role['name']) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($permissions as $module => $perms): ?>
                <tr class="bg-slate-50/60 dark:bg-slate-800/40">
                    <td colspan="<?= count($roles) + 1 ?>" class="px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        <?= e(ucfirst($module)) ?> module
                    </td>
                </tr>
                <?php foreach ($perms as $p): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <span class="font-mono text-[12.5px] text-slate-600 dark:text-slate-300"><?= e($p['name']) ?></span>
                                <span class="hidden text-xs text-slate-400 lg:inline"><?= e($p['label']) ?></span>
                            </div>
                        </td>
                        <?php foreach ($roles as $role): ?>
                            <td class="text-center">
                                <?php if ($role['slug'] === 'super-admin'): ?>
                                    <span class="grid h-5 w-5 place-items-center rounded-full bg-teal-500/15 text-teal-600 dark:text-teal-400 mx-auto" title="Bypasses all checks">
                                        <i data-lucide="infinity" class="h-3 w-3"></i>
                                    </span>
                                <?php elseif (isset($matrix[(int) $role['id']][(string) $p['name']])): ?>
                                    <span class="grid h-5 w-5 place-items-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 mx-auto">
                                        <i data-lucide="check" class="h-3 w-3"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="grid h-5 w-5 place-items-center rounded-full bg-slate-100 text-slate-300 dark:bg-slate-800 dark:text-slate-600 mx-auto">
                                        <i data-lucide="minus" class="h-3 w-3"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->end(); ?>
