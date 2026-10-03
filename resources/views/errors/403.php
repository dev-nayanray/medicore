<?php

declare(strict_types=1);

/** 403 — Forbidden. Authenticated but lacks permission. */

$debug = (bool) ($debug ?? false);
$permission = $message ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 · Access Denied</title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<script src="<?= asset('vendor/tailwind/tailwind.js') ?>"></script>
<script>
tailwind.config = { theme: { extend: { colors: { navy: {950:'#0b1f3a', 900:'#122a47'} },
    fontFamily: { sans: ['Inter','Segoe UI','system-ui','sans-serif'] } } } };
</script>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="grid h-full place-items-center bg-slate-100 px-6 font-sans dark:bg-slate-950">
<div class="w-full max-w-lg">
    <div class="card p-8 text-center">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <h1 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">Access Denied</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            You do not have permission to access this page.<?php if ($permission !== ''): ?><br><span class="font-mono text-xs text-amber-600 dark:text-amber-400"><?= e($permission) ?></span><?php endif; ?>
        </p>
        <p class="mt-1 text-xs text-slate-400">
            If you believe this is an error, please contact your system administrator.
        </p>
        <div class="mt-6 flex items-center justify-center gap-2">
            <a href="<?= url('/') ?>" class="btn btn-secondary">
                <i data-lucide="layout-dashboard" class="h-4 w-4"></i>Dashboard
            </a>
            <a href="<?= url('/login') ?>" class="btn btn-primary">
                <i data-lucide="log-out" class="h-4 w-4"></i>Switch Account
            </a>
        </div>
    </div>
</div>
<script src="<?= asset('vendor/lucide/lucide.min.js') ?>"></script>
<script>if (window.lucide) window.lucide.createIcons();</script>
</body>
</html>
