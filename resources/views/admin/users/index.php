<?php

declare(strict_types=1);

/**
 * User management directory with search + role/status filters.
 * $users, $total, $page, $pages, $perPage, $search, $role, $status, $roles, $baseUrl
 */

$this->extend('layouts/admin');
$title = 'Staff Users';
$active = 'users';
$breadcrumbs = ['Administration' => null, 'Staff Users' => ''];

$statusLabels = [
    'not_archived' => 'Active & deactivated',
    'active'       => 'Active',
    'inactive'     => 'Deactivated',
    'archived'     => 'Archived',
    'all'          => 'All',
];
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Staff Users</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= (int) $total ?> account<?= (int) $total === 1 ? '' : 's' ?> · create, edit, deactivate, archive
        </p>
    </div>
    <?php if (can('users.create')): ?>
        <a href="<?= url('/admin/users/create') ?>" class="btn btn-primary">
            <i data-lucide="user-round-plus" class="h-4 w-4"></i>Add staff user
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <!-- Filter toolbar -->
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <form method="get" action="<?= url('/admin/users') ?>" class="flex flex-wrap items-center gap-2">
            <div class="relative w-full max-w-xs sm:w-auto sm:flex-1">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone…" class="input pl-9 text-sm">
            </div>
            <select name="role" class="input w-auto text-sm">
                <option value="">All roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['slug']) ?>" <?= $role === $r['slug'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="input w-auto text-sm">
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($search !== '' || $role !== '' || $status !== 'not_archived'): ?>
                <a href="<?= url('/admin/users') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($users === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => $search !== '' || $role !== '' ? 'search-x' : 'user-round',
                'title'   => $search !== '' || $role !== '' ? 'No accounts match your filters' : 'No staff accounts yet',
                'message' => $search !== '' || $role !== ''
                    ? 'Nothing matched the current search / filter combination. Try widening the filters.'
                    : 'Create the first staff account with the "Add staff user" button.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Staff member</th>
                    <th>Phone</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $isSelf = (int) $u['id'] === (int) (auth_user()['id'] ?? 0);
                    $isArchived = $u['archived_at'] !== null;
                    ?>
                    <tr class="<?= $isArchived ? 'opacity-60' : '' ?>">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-[11px] font-semibold text-white <?= $isArchived ? 'bg-slate-400' : 'bg-navy-900/90' ?>">
                                    <?= e(initials((string) $u['name'])) ?>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-[13.5px] font-medium text-slate-800 dark:text-slate-100">
                                        <?= e($u['name']) ?><?= $isSelf ? ' <span class="text-[10px] font-semibold text-teal-600 dark:text-teal-400">(you)</span>' : '' ?>
                                    </p>
                                    <p class="truncate text-xs text-slate-400"><?= e($u['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400"><?= e($u['phone'] ?? '—') ?></td>
                        <td>
                            <?php if ((int) $u['roles_count'] === 0): ?>
                                <span class="text-[13px] text-slate-400">—</span>
                            <?php else: ?>
                                <span class="badge badge-navy"><?= e($u['roles_label']) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $u['overrides_count'] > 0): ?>
                                <span class="badge badge-amber" title="Has direct permission overrides"><?= (int) $u['overrides_count'] ?> override<?= (int) $u['overrides_count'] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isArchived): ?>
                                <span class="badge badge-slate"><i data-lucide="archive" class="h-3 w-3"></i>Archived</span>
                            <?php elseif ((int) $u['is_active'] === 1): ?>
                                <span class="badge badge-emerald">Active</span>
                            <?php else: ?>
                                <span class="badge badge-rose">Deactivated</span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400"><?= e(format_date($u['last_login_at'], 'M j, g:i A')) ?></td>
                        <td>
                            <div class="flex items-center justify-end gap-1" x-data="{ open: false }" @click.outside="open = false">
                                <?php if (can('users.update')): ?>
                                    <a href="<?= url('/admin/users/' . (int) $u['id'] . '/edit') ?>" class="icon-btn !p-1.5" title="Edit">
                                        <i data-lucide="pencil" class="h-4 w-4"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (!$isSelf && can('users.update') && !$isArchived): ?>
                                    <form method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/' . ((int) $u['is_active'] === 1 ? 'deactivate' : 'activate')) ?>"
                                          data-confirm="<?= (int) $u['is_active'] === 1 ? 'Deactivate account|' . e($u['name']) . ' will no longer be able to sign in.' : 'Activate account|' . e($u['name']) . ' will be able to sign in again.' ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn !p-1.5" title="<?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>">
                                            <i data-lucide="<?= (int) $u['is_active'] === 1 ? 'user-round-x' : 'user-round-check' ?>" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$isSelf && !$isArchived && can('users.archive')): ?>
                                    <form method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/archive') ?>"
                                          data-confirm="Archive account|<?= e($u['name']) ?> will be hidden from lists and blocked from sign-in. This is reversible.">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn !p-1.5" title="Archive">
                                            <i data-lucide="archive" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                <?php elseif (!$isSelf && $isArchived && can('users.archive')): ?>
                                    <form method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/restore') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn !p-1.5" title="Restore">
                                            <i data-lucide="archive-restore" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$isSelf && can('users.delete')): ?>
                                    <form method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/delete') ?>"
                                          data-confirm="Delete account permanently|<?= e($u['name']) ?> will be permanently deleted along with their role assignments. Consider archiving instead.|danger">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn !p-1.5 hover:!text-rose-600" title="Delete permanently">
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
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
