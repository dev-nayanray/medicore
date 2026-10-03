<?php

declare(strict_types=1);

/**
 * Role create/edit with configurable permission matrix.
 * $role (null on create), $rolePerms ([permission ids]), $permissions (grouped)
 */

$this->extend('layouts/admin');
$isEdit = $role !== null;
$isSuper = $isEdit && $role['slug'] === 'super-admin';
$title = $isEdit ? 'Edit Role' : 'Create Role';
$active = 'roles';
$breadcrumbs = ['Administration' => null, 'Roles & Permissions' => url('/admin/roles'), ($isEdit ? 'Edit' : 'Create') => ''];

$rolePerms = array_fill_keys(array_map('intval', $rolePerms ?? []), true);
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit role · ' . e($role['name']) : 'Create Role' ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isSuper ? 'The Super Admin role bypasses every check and cannot be modified.' : 'Define the role and tick the abilities it grants.' ?>
        </p>
    </div>
    <a href="<?= url('/admin/roles') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to roles
    </a>
</div>

<?php if ($isSuper): ?>
    <div class="card p-6">
        <?= $this->insert('components/empty-state', [
            'icon'    => 'crown',
            'title'   => 'Super Admin is immutable',
            'message' => 'This role grants unrestricted access by design and cannot be edited or deleted. Assign it only to trusted personnel.',
        ]) ?>
    </div>
<?php else: ?>

<form method="post" action="<?= $isEdit ? url('/admin/roles/' . (int) $role['id']) : url('/admin/roles') ?>" class="space-y-4">

    <?= csrf_field() ?>

    <!-- Details -->
    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                <i data-lucide="shield" class="h-4 w-4"></i>
            </span>
            <h2 class="text-sm font-semibold">Role details</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Role name</label>
                <input type="text" id="name" name="name" value="<?= old('name', (string) ($role['name'] ?? '')) ?>"
                       class="input <?= error('name') ? 'input-error' : '' ?>" required placeholder="e.g. Ward Manager">
                <?php if (error('name')): ?><p class="error-text"><?= e(error('name')) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="description" class="label">Description</label>
                <input type="text" id="description" name="description" value="<?= old('description', (string) ($role['description'] ?? '')) ?>"
                       class="input <?= error('description') ? 'input-error' : '' ?>" placeholder="What this role is for">
                <?php if (error('description')): ?><p class="error-text"><?= e(error('description')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Permission matrix editor -->
    <section class="card">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center">
            <div class="flex items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300">
                    <i data-lucide="key-round" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold">Permissions</h2>
                    <p class="text-xs text-slate-400">Changes apply to holders within 5 minutes (or on their next sign-in)</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-secondary !py-1.5 !text-xs select-all" data-scope="perm-matrix">
                    <i data-lucide="check-check" class="h-3.5 w-3.5"></i>Select all
                </button>
                <button type="button" class="btn btn-secondary !py-1.5 !text-xs select-none-btn">
                    <i data-lucide="eraser" class="h-3.5 w-3.5"></i>Clear
                </button>
            </div>
        </div>

        <div id="perm-matrix" class="max-h-[560px] overflow-y-auto scrollbar-thin p-5">
            <?php foreach ($permissions as $module => $perms): ?>
                <div class="mb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-400"><?= e(ucfirst($module)) ?> module</p>
                        <button type="button" class="select-all text-[10.5px] font-medium text-teal-600 hover:underline dark:text-teal-400" data-scope="mod-<?= e($module) ?>">toggle module</button>
                    </div>
                    <div id="mod-<?= e($module) ?>" class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($perms as $p): ?>
                            <label class="perm-option">
                                <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>"
                                       <?= isset($rolePerms[(int) $p['id']]) ? 'checked' : '' ?> class="checkbox !static">
                                <span class="min-w-0">
                                    <span class="block truncate text-[12.5px] font-medium text-slate-700 dark:text-slate-200"><?= e($p['label']) ?></span>
                                    <span class="block truncate font-mono text-[10.5px] text-slate-400"><?= e($p['name']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="card flex items-center justify-between gap-3 p-5">
        <p class="text-xs text-slate-400"><i data-lucide="info" class="mr-1 inline h-3.5 w-3.5"></i>Users holding this role re-sync permissions automatically.</p>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Save role' : 'Create role' ?>
        </button>
    </div>
</form>
<?php endif; ?>
<?php $this->end(); ?>
