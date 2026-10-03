<?php

declare(strict_types=1);

/**
 * Own profile — details + password change. $user, $roles
 */

$this->extend('layouts/admin');
$title = 'My Profile';
$active = 'profile';
$breadcrumbs = ['Administration' => null, 'My Profile' => ''];
?>
<?php $this->section('content'); ?>

<div class="mb-6">
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">My Profile</h1>
    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Manage your account details and password.</p>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    <!-- Identity card -->
    <div class="card p-6 text-center">
        <span class="mx-auto grid h-20 w-20 place-items-center rounded-2xl bg-gradient-to-br from-navy-800 to-navy-950 text-2xl font-bold text-white">
            <?= e(initials((string) $user['name'])) ?>
        </span>
        <p class="mt-4 text-lg font-semibold text-slate-900 dark:text-white"><?= e($user['name']) ?></p>
        <p class="text-sm text-slate-400"><?= e($user['email']) ?></p>

        <div class="mt-4 flex flex-wrap justify-center gap-1.5">
            <?php foreach ($roles as $slug): ?>
                <span class="badge badge-teal"><?= e(str_replace('-', ' ', $slug)) ?></span>
            <?php endforeach; ?>
        </div>

        <dl class="mt-6 space-y-3 border-t border-slate-100 pt-5 text-left dark:border-slate-800">
            <div class="flex items-center justify-between text-[13px]">
                <dt class="text-slate-400">Status</dt>
                <dd><span class="badge <?= ((int) $user['is_active'] === 1) ? 'badge-emerald' : 'badge-slate' ?>"><?= ((int) $user['is_active'] === 1) ? 'Active' : 'Deactivated' ?></span></dd>
            </div>
            <div class="flex items-center justify-between text-[13px]">
                <dt class="text-slate-400">Phone</dt>
                <dd class="text-slate-600 dark:text-slate-300"><?= e($user['phone'] ?? '—') ?></dd>
            </div>
            <div class="flex items-center justify-between text-[13px]">
                <dt class="text-slate-400">Member since</dt>
                <dd class="text-slate-600 dark:text-slate-300"><?= e(format_date($user['created_at'], 'M j, Y')) ?></dd>
            </div>
            <div class="flex items-center justify-between text-[13px]">
                <dt class="text-slate-400">Last sign-in</dt>
                <dd class="text-slate-600 dark:text-slate-300"><?= e(time_ago((string) $user['last_login_at'])) ?></dd>
            </div>
        </dl>
    </div>

    <div class="space-y-4 lg:col-span-2">
        <!-- Details form -->
        <form method="post" action="<?= url('/admin/profile') ?>" class="card">
            <?= csrf_field() ?>
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Profile details</h2>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="label">Full name</label>
                    <input type="text" id="name" name="name" value="<?= old('name', (string) $user['name']) ?>" class="input <?= error('name') ? 'input-error' : '' ?>" required>
                    <?php if (error('name')): ?><p class="error-text"><?= e(error('name')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="phone" class="label">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= old('phone', (string) ($user['phone'] ?? '')) ?>" class="input <?= error('phone') ? 'input-error' : '' ?>" placeholder="+880 …">
                    <?php if (error('phone')): ?><p class="error-text"><?= e(error('phone')) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">
                <button type="submit" class="btn btn-primary"><i data-lucide="save" class="h-4 w-4"></i>Save profile</button>
            </div>
        </form>

        <!-- Password form -->
        <form method="post" action="<?= url('/admin/profile/password') ?>" class="card">
            <?= csrf_field() ?>
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Change password</h2>
                <p class="text-xs text-slate-400">Minimum 8 characters · all other sessions stay signed in</p>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label for="current_password" class="label">Current</label>
                    <input type="password" id="current_password" name="current_password" class="input <?= error('current_password') ? 'input-error' : '' ?>" required autocomplete="current-password">
                    <?php if (error('current_password')): ?><p class="error-text"><?= e(error('current_password')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="password" class="label">New</label>
                    <input type="password" id="password" name="password" class="input <?= error('password') ? 'input-error' : '' ?>" required minlength="8" autocomplete="new-password">
                    <?php if (error('password')): ?><p class="error-text"><?= e(error('password')) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirm new</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="input" required autocomplete="new-password">
                </div>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">
                <button type="submit" class="btn btn-secondary"><i data-lucide="key-round" class="h-4 w-4"></i>Update password</button>
            </div>
        </form>
    </div>
</div>
<?php $this->end(); ?>
