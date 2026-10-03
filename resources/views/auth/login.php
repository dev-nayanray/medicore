<?php

declare(strict_types=1);

/**
 * Sign-in screen. $hospitalName passed by controller, $sessionExpired flag.
 */

$this->extend('layouts/auth');
$title = 'Sign in';
?>

<?php $this->section('brand'); ?>
<span class="inline-flex items-center gap-2 rounded-full border border-teal-500/30 bg-teal-500/10 px-3 py-1.5 text-xs font-semibold text-teal-300">
    <span class="relative flex h-1.5 w-1.5">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-teal-400 opacity-75"></span>
        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-teal-400"></span>
    </span>
    Secure administration console
</span>

<h1 class="mt-5 text-4xl font-bold leading-[1.15] tracking-tight text-white">
    One control room for your
    <span class="block bg-gradient-to-r from-teal-300 to-teal-500 bg-clip-text text-transparent">entire hospital.</span>
</h1>
<p class="mt-5 text-[15px] leading-relaxed text-slate-400">
    Patients, appointments, laboratory, pharmacy and finance — orchestrated from a single,
    secure administration console.
</p>
<ul class="mt-8 space-y-3.5 text-sm text-slate-300">
    <li class="flex items-center gap-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-teal-500/15 text-teal-400 ring-1 ring-teal-500/20">
            <i data-lucide="shield-check" class="h-4 w-4"></i>
        </span>
        Role-based access control with audit trail
    </li>
    <li class="flex items-center gap-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-teal-500/15 text-teal-400 ring-1 ring-teal-500/20">
            <i data-lucide="activity" class="h-4 w-4"></i>
        </span>
        Real-time operational dashboard
    </li>
    <li class="flex items-center gap-3">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-teal-500/15 text-teal-400 ring-1 ring-teal-500/20">
            <i data-lucide="bed-double" class="h-4 w-4"></i>
        </span>
        Beds, billing &amp; pharmacy modules
    </li>
</ul>
<?php $this->end(); ?>

<?php $this->section('content'); ?>

<?php if (($sessionExpired ?? false)): ?>
    <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
        <i data-lucide="clock-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
        <span>Your session expired due to inactivity. Please sign in again.</span>
    </div>
<?php endif; ?>

<h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Welcome back</h2>
<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sign in to the administration console.</p>

<form method="post" action="<?= url('/login') ?>" class="mt-7 space-y-5">
    <?= csrf_field() ?>

    <div>
        <label for="email" class="label">Email address</label>
        <div class="relative">
            <i data-lucide="mail" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input id="email" type="email" name="email" value="<?= old('email') ?>" required autofocus autocomplete="username"
                   class="input pl-9 <?= error('email') ? 'input-error' : '' ?>" placeholder="admin@medicore.test">
        </div>
        <?php if (error('email')): ?><p class="error-text"><i data-lucide="alert-circle" class="h-3 w-3"></i><?= e(error('email')) ?></p><?php endif; ?>
    </div>

    <div>
        <div class="flex items-center justify-between">
            <label for="password" class="label">Password</label>
            <a href="<?= url('/forgot-password') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Forgot password?</a>
        </div>
        <div class="relative">
            <i data-lucide="lock" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="input pl-9 pr-10 <?= error('password') ? 'input-error' : '' ?>" placeholder="••••••••••">
            <button type="button" class="password-toggle" data-target="password" tabindex="-1" aria-label="Show password">
                <i data-lucide="eye" class="h-4 w-4"></i>
            </button>
        </div>
        <?php if (error('password')): ?><p class="error-text"><i data-lucide="alert-circle" class="h-3 w-3"></i><?= e(error('password')) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full justify-center">
        <span>Sign in</span>
        <i data-lucide="arrow-right" class="h-4 w-4"></i>
    </button>
</form>

<div class="mt-7 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-[13px] leading-relaxed text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
    <p class="mb-1.5 flex items-center gap-1.5 font-medium text-slate-600 dark:text-slate-300">
        <i data-lucide="key-round" class="h-3.5 w-3.5 text-teal-500"></i> Demo credentials (development)
    </p>
    <p><code class="rounded bg-white px-1.5 py-0.5 text-[12px] ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">admin@medicore.test</code> ·
       <code class="rounded bg-white px-1.5 py-0.5 text-[12px] ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">Admin@12345</code></p>
</div>
<?php $this->end(); ?>
