<?php

declare(strict_types=1);

/** 500 — standalone page. In debug mode it shows exception details. */

$debug = (bool) ($debug ?? false);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= (int) ($code ?? 500) ?> · Server Error</title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<script src="<?= asset('vendor/tailwind/tailwind.js') ?>"></script>
<script>
tailwind.config = { theme: { extend: { colors: { navy: {950:'#0b1f3a', 900:'#122a47'} },
    fontFamily: { sans: ['Inter','Segoe UI','system-ui','sans-serif'] } } } };
</script>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="grid h-full place-items-center bg-slate-100 px-6 font-sans dark:bg-slate-950">
<div class="w-full max-w-2xl">
    <div class="card p-8 text-center">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <h1 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">Something went wrong</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            <?= $debug ? '' : 'The error has been logged and the team notified. Please try again in a moment.' ?>
        </p>

        <?php if ($debug): ?>
            <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-left dark:border-rose-900/50 dark:bg-rose-950/30">
                <p class="font-mono text-[13px] font-semibold text-rose-700 dark:text-rose-300"><?= e($message ?? 'Unknown error') ?></p>
                <p class="mt-1 font-mono text-[11.5px] text-rose-500 dark:text-rose-400/80"><?= e(($file ?? '') . ':' . ($line ?? '')) ?></p>
                <?php if (!empty($trace)): ?>
                    <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-white/70 p-3 font-mono text-[11px] leading-relaxed text-slate-600 dark:bg-slate-900/70 dark:text-slate-400"><?= e(implode("\n", array_slice((array) $trace, 0, 15))) ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a href="<?= url('/') ?>" class="btn btn-primary mt-6 inline-flex">
            <i data-lucide="layout-dashboard" class="h-4 w-4"></i>Back to dashboard
        </a>
    </div>
</div>
<script src="<?= asset('vendor/lucide/lucide.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
