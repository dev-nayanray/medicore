<?php

declare(strict_types=1);

/** 404 — standalone page (no layout dependency). */
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 · Not Found</title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<script src="<?= asset('vendor/tailwind/tailwind.js') ?>"></script>
<script>
tailwind.config = { theme: { extend: { colors: { navy: {950:'#0b1f3a', 900:'#122a47'} },
    fontFamily: { sans: ['Inter','Segoe UI','system-ui','sans-serif'] } } } };
</script>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="grid h-full place-items-center bg-slate-100 px-6 font-sans dark:bg-slate-950">
<div class="w-full max-w-md text-center">
    <div class="relative mx-auto mb-8 h-28 w-28">
        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-teal-400 to-teal-600 opacity-20 blur-xl"></div>
        <div class="relative grid h-28 w-28 place-items-center rounded-3xl bg-white shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
            <span class="text-4xl font-bold tracking-tight text-navy-900 dark:text-white">404</span>
        </div>
    </div>
    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Page not found</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
        <?= e($message ?? 'The page you are looking for could not be found.') ?>
    </p>
    <a href="<?= url('/') ?>" class="btn btn-primary mt-6 inline-flex">
        <i data-lucide="layout-dashboard" class="h-4 w-4"></i>Back to dashboard
    </a>
</div>
<script src="<?= asset('vendor/lucide/lucide.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
