<?php

declare(strict_types=1);

/**
 * Forced password change — shown when an admin flagged the account.
 * The user cannot navigate anywhere else until this is completed.
 */

$this->extend('layouts/auth');
$title = 'Change password';
?>

<?php $this->section('brand'); ?>
<h1 class="text-3xl font-bold leading-tight tracking-tight text-white">
    Password rotation required.
</h1>
<p class="mt-4 text-[15px] leading-relaxed text-slate-400">
    An administrator has requested a password change on this account.
    The console stays locked until a new password is set.
</p>
<?php $this->end(); ?>

<?php $this->section('content'); ?>
<div class="mb-5 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
    <i data-lucide="lock-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
    <span>Set a new password to unlock the console.</span>
</div>

<h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Set a new password</h2>

<form method="post" action="<?= url('/change-password') ?>" class="mt-6 space-y-5">
    <?= csrf_field() ?>

    <div>
        <label for="current_password" class="label">Current password</label>
        <div class="relative">
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
                   class="input pr-10 <?= error('current_password') ? 'input-error' : '' ?>">
            <button type="button" class="password-toggle" data-target="current_password" tabindex="-1" aria-label="Show password">
                <i data-lucide="eye" class="h-4 w-4"></i>
            </button>
        </div>
        <?php if (error('current_password')): ?><p class="error-text"><?= e(error('current_password')) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="password" class="label">New password</label>
        <div class="relative">
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="input pr-10 <?= error('password') ? 'input-error' : '' ?>" placeholder="8+ chars, mixed case, a number">
            <button type="button" class="password-toggle" data-target="password" tabindex="-1" aria-label="Show password">
                <i data-lucide="eye" class="h-4 w-4"></i>
            </button>
        </div>
        <?php if (error('password')): ?><p class="error-text"><?= e(error('password')) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="password_confirmation" class="label">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
    </div>

    <button type="submit" class="btn btn-primary w-full justify-center">
        <i data-lucide="shield-check" class="h-4 w-4"></i> Save and continue
    </button>
</form>

<form method="post" action="<?= url('/logout') ?>" class="mt-4">
    <?= csrf_field() ?>
    <button type="submit" class="w-full text-center text-xs text-slate-400 hover:text-rose-500">Sign out instead</button>
</form>
<?php $this->end(); ?>
