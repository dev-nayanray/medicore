<?php

declare(strict_types=1);

/**
 * User create/edit form.
 * $user (null on create), $userRoles (slugs), $overrides ([id => [name,type]]),
 * $roles, $permissions (grouped), $mayManageSuper, $recentLogins (edit only)
 */

$this->extend('layouts/admin');
$isEdit = $user !== null;
$title = $isEdit ? 'Edit User' : 'Create User';
$active = 'users';
$breadcrumbs = ['Administration' => null, 'Staff Users' => url('/admin/users'), ($isEdit ? 'Edit' : 'Create') => ''];

$overrideMap = [];
foreach (($overrides ?? []) as $ov) {
    $overrideMap[(int) $ov['id']] = $ov['type'];
}
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <?= $isEdit ? 'Edit ' . e($user['name']) : 'Create Staff User' ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= $isEdit ? 'Update details, roles, status and security options.' : 'Provision a new console account with its roles.' ?>
        </p>
    </div>
    <a href="<?= url('/admin/users') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to directory
    </a>
</div>

<form method="post"
      action="<?= $isEdit ? url('/admin/users/' . (int) $user['id']) : url('/admin/users') ?>"
      class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    <?= csrf_field() ?>

    <!-- Identity & credentials -->
    <div class="space-y-4 lg:col-span-2">
        <section class="card">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                    <i data-lucide="id-card" class="h-4 w-4"></i>
                </span>
                <h2 class="text-sm font-semibold">Account details</h2>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="label">Full name</label>
                    <input type="text" id="name" name="name" value="<?= old('name', (string) ($user['name'] ?? '')) ?>"
                           class="input <?= error('name') ? 'input-error' : '' ?>" required>
                    <?php if (error('name')): ?><p class="error-text"><?= e(error('name')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="email" class="label">Email address</label>
                    <input type="email" id="email" name="email" value="<?= old('email', (string) ($user['email'] ?? '')) ?>"
                           class="input <?= error('email') ? 'input-error' : '' ?>" required>
                    <?php if (error('email')): ?><p class="error-text"><?= e(error('email')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="phone" class="label">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= old('phone', (string) ($user['phone'] ?? '')) ?>"
                           class="input <?= error('phone') ? 'input-error' : '' ?>" placeholder="+880 …">
                    <?php if (error('phone')): ?><p class="error-text"><?= e(error('phone')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="password" class="label"><?= $isEdit ? 'New password (leave blank to keep)' : 'Password' ?></label>
                    <div class="relative">
                        <input type="password" id="password" name="password" autocomplete="new-password"
                               class="input pr-10 <?= error('password') ? 'input-error' : '' ?>" <?= $isEdit ? '' : 'required' ?>
                               placeholder="8+ chars, mixed case, a number">
                        <button type="button" class="password-toggle" data-target="password" tabindex="-1" aria-label="Show password">
                            <i data-lucide="eye" class="h-4 w-4"></i>
                        </button>
                    </div>
                    <?php if (error('password')): ?><p class="error-text"><?= e(error('password')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirm password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                           class="input" <?= $isEdit ? '' : 'required' ?>>
                </div>
            </div>
        </section>

        <!-- Roles -->
        <section class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300">
                        <i data-lucide="shield-check" class="h-4 w-4"></i>
                    </span>
                    <h2 class="text-sm font-semibold">Role assignment</h2>
                </div>
                <span class="text-xs text-slate-400">A user may hold multiple roles</span>
            </div>
            <div class="grid grid-cols-1 gap-2 p-5 sm:grid-cols-2">
                <?php $oldRoles = (array) old_raw('roles', $userRoles ?? []); ?>
                <?php foreach ($roles as $r): ?>
                    <?php
                    $isSuperRole = $r['slug'] === 'super-admin';
                    $locked = $isSuperRole && !$mayManageSuper;
                    $checked = in_array($r['slug'], $oldRoles, true);
                    ?>
                    <label class="role-option <?= $locked ? 'opacity-50' : '' ?>">
                        <input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" <?= $checked ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?> class="checkbox !static">
                        <span class="min-w-0">
                            <span class="flex items-center gap-1.5 text-[13.5px] font-medium text-slate-700 dark:text-slate-200">
                                <?= e($r['name']) ?>
                                <?php if ($isSuperRole): ?><i data-lucide="crown" class="h-3.5 w-3.5 text-amber-500"></i><?php endif; ?>
                            </span>
                            <span class="block truncate text-xs text-slate-400"><?= e($r['description'] ?? '') ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (error('roles')): ?>
                <p class="error-text px-5 pb-4"><?= e(error('roles')) ?></p>
            <?php endif; ?>
        </section>

        <!-- Direct permission overrides -->
        <section class="card" x-data="{ show: <?= !empty($overrideMap) ? 'true' : 'false' ?> }">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <i data-lucide="user-cog" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold">Direct permission overrides</h2>
                        <p class="text-xs text-slate-400">Grant/deny individual abilities for this user only — rare cases</p>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary !py-1.5 !text-xs" @click="show = !show">
                    <i data-lucide="chevron-down" class="h-3.5 w-3.5 transition-transform" :class="show ? 'rotate-180' : ''"></i>
                    <span x-text="show ? 'Hide' : 'Show'"></span>
                </button>
            </div>
            <div x-show="show" x-transition.origin.top x-cloak class="max-h-[420px] overflow-y-auto scrollbar-thin p-5">
                <p class="mb-4 rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <strong>Inherit</strong> follows the user's roles · <strong>Grant</strong> adds the ability regardless of roles ·
                    <strong>Deny</strong> blocks it even when a role would allow it. Deny always wins.
                </p>
                <?php foreach ($permissions as $module => $perms): ?>
                    <div class="mb-3">
                        <p class="mb-1.5 text-[10.5px] font-semibold uppercase tracking-wider text-slate-400"><?= e(ucfirst($module)) ?></p>
                        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                            <?php foreach ($perms as $p): ?>
                                <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-800">
                                    <span class="truncate font-mono text-[11.5px] text-slate-600 dark:text-slate-300"><?= e($p['name']) ?></span>
                                    <select name="overrides[<?= (int) $p['id'] ?>]" class="input !w-auto !py-1 !text-[11px] !px-2">
                                        <option value="">Inherit</option>
                                        <option value="allow" <?= ($overrideMap[(int) $p['id']] ?? '') === 'allow' ? 'selected' : '' ?>>Grant</option>
                                        <option value="deny" <?= ($overrideMap[(int) $p['id']] ?? '') === 'deny' ? 'selected' : '' ?>>Deny</option>
                                    </select>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <!-- Side column -->
    <div class="space-y-4">
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Status &amp; security</h2>
            </div>
            <div class="space-y-4 p-5">
                <label class="toggle-row">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="checkbox"
                           <?= old('is_active', (string) ($user['is_active'] ?? '1')) === '1' ? 'checked' : '' ?>>
                    <span class="toggle-track"><span class="toggle-knob"></span></span>
                    <span class="text-[13px] text-slate-600 dark:text-slate-300">Active — allowed to sign in</span>
                </label>
                <label class="toggle-row">
                    <input type="hidden" name="must_change_password" value="0">
                    <input type="checkbox" name="must_change_password" value="1" class="checkbox"
                           <?= old('must_change_password', '0') === '1' ? 'checked' : '' ?>>
                    <span class="toggle-track"><span class="toggle-knob"></span></span>
                    <span class="text-[13px] text-slate-600 dark:text-slate-300">Force password change on next sign-in</span>
                </label>
                <p class="rounded-lg bg-slate-50 p-3 text-[11.5px] leading-relaxed text-slate-400 dark:bg-slate-800/60">
                    Deactivated and archived accounts are blocked at the door — even with valid credentials.
                </p>
            </div>
        </section>

        <div class="card p-5">
            <button type="submit" class="btn btn-primary w-full justify-center">
                <i data-lucide="save" class="h-4 w-4"></i><?= $isEdit ? 'Save changes' : 'Create user' ?>
            </button>
            <a href="<?= url('/admin/users') ?>" class="btn btn-secondary mt-2 w-full justify-center">Cancel</a>
        </div>

        <?php if ($isEdit && !empty($recentLogins)): ?>
            <section class="card">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Recent sign-ins</h2>
                    <p class="text-xs text-slate-400">Login attempt history</p>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($recentLogins as $attempt): ?>
                        <li class="flex items-center gap-2.5 px-5 py-2.5">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full <?= (int) $attempt['successful'] === 1 ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600' ?>">
                                <i data-lucide="<?= (int) $attempt['successful'] === 1 ? 'check' : 'x' ?>" class="h-3 w-3"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[12px] text-slate-600 dark:text-slate-300">
                                    <?= (int) $attempt['successful'] === 1 ? 'Signed in' : 'Failed — ' . e(str_replace('_', ' ', (string) ($attempt['failure_reason'] ?? 'unknown'))) ?>
                                </span>
                                <span class="block font-mono text-[10.5px] text-slate-400"><?= e($attempt['ip_address'] ?? '') ?> · <?= e(time_ago((string) $attempt['created_at'])) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</form>
<?php $this->end(); ?>
