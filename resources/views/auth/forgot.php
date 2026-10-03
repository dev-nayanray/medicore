<?php

declare(strict_types=1);

/**
 * Forgot password — request a reset link.
 */

$this->extend('layouts/auth');
$title = 'Forgot password';
?>

<?php $this->section('brand'); ?>
<h1 class="text-3xl font-bold leading-tight tracking-tight text-white">
    Recover your access.
</h1>
<p class="mt-4 text-[15px] leading-relaxed text-slate-400">
    Enter your account email and we'll send a single-use recovery link.
    Reset links expire after 60 minutes and can only be used once.
</p>
<ul class="mt-8 space-y-3 text-sm text-slate-300">
    <li class="flex items-center gap-3"><i data-lucide="timer" class="h-4 w-4 text-teal-400"></i>Links expire in 60 minutes</li>
    <li class="flex items-center gap-3"><i data-lucide="one-time" class="h-4 w-4 text-teal-400"></i>Single-use tokens</li>
    <li class="flex items-center gap-3"><i data-lucide="incognito" class="h-4 w-4 text-teal-400"></i>No account enumeration</li>
</ul>
<?php $this->end(); ?>

<?php $this->section('content'); ?>
<h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Forgot your password?</h2>
<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">We'll email you a secure reset link.</p>

<form method="post" action="<?= url('/forgot-password') ?>" class="mt-7 space-y-5">
    <?= csrf_field() ?>

    <div>
        <label for="email" class="label">Email address</label>
        <input id="email" type="email" name="email" value="<?= old('email') ?>" required autofocus autocomplete="username"
               class="input <?= error('email') ? 'input-error' : '' ?>" placeholder="you@medicore.test">
        <?php if (error('email')): ?><p class="error-text"><?= e(error('email')) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full justify-center">
        <i data-lucide="mail" class="h-4 w-4"></i> Send reset link
    </button>
</form>
<?php $this->end(); ?>

<?php $this->section('below'); ?>
<p class="mt-6 text-center text-sm text-slate-400">
    <a href="<?= url('/login') ?>" class="inline-flex items-center gap-1.5 font-medium text-teal-600 hover:underline dark:text-teal-400">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>Back to sign in
    </a>
</p>
<?php $this->end(); ?>
