<?php

declare(strict_types=1);

/**
 * Reset password via token. $email is NULL when the token is invalid/expired.
 */

$this->extend('layouts/auth');
$title = 'Reset password';
$valid = $email !== null;
?>

<?php $this->section('brand'); ?>
<h1 class="text-3xl font-bold leading-tight tracking-tight text-white">
    Set a new password.
</h1>
<p class="mt-4 text-[15px] leading-relaxed text-slate-400">
    Choose a strong password — at least 8 characters with an uppercase letter,
    a lowercase letter and a number.
</p>
<?php $this->end(); ?>

<?php $this->section('content'); ?>

<?php if (!$valid): ?>
    <div class="text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
            <i data-lucide="link-2-off" class="h-7 w-7"></i>
        </span>
        <h2 class="mt-4 text-xl font-bold tracking-tight text-slate-900 dark:text-white">Link expired or invalid</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            This reset link has already been used or has expired.
            Request a fresh one to continue.
        </p>
        <a href="<?= url('/forgot-password') ?>" class="btn btn-primary mt-6 inline-flex">
            <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Request new link
        </a>
    </div>
<?php else: ?>
    <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Choose a new password</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Resetting for <span class="font-medium text-slate-700 dark:text-slate-300"><?= e($email) ?></span>
    </p>

    <form method="post" action="<?= url('/reset-password/' . urlencode($token)) ?>" class="mt-7 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label for="password" class="label">New password</label>
            <div class="relative">
                <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                       class="input pr-10 <?= error('password') ? 'input-error' : '' ?>" placeholder="At least 8 characters">
                <button type="button" class="password-toggle" data-target="password" tabindex="-1" aria-label="Show password">
                    <i data-lucide="eye" class="h-4 w-4"></i>
                </button>
            </div>
            <?php if (error('password')): ?><p class="error-text"><?= e(error('password')) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="password_confirmation" class="label">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="input <?= error('password') ? 'input-error' : '' ?>" placeholder="Repeat password">
        </div>

        <button type="submit" class="btn btn-primary w-full justify-center">
            <i data-lucide="shield-check" class="h-4 w-4"></i> Reset password
        </button>
    </form>
<?php endif; ?>
<?php $this->end(); ?>

<?php $this->section('below'); ?>
<p class="mt-6 text-center text-sm text-slate-400">
    <a href="<?= url('/login') ?>" class="inline-flex items-center gap-1.5 font-medium text-teal-600 hover:underline dark:text-teal-400">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>Back to sign in
    </a>
</p>
<?php $this->end(); ?>
