<?php

declare(strict_types=1);

/**
 * Auth layout — shared shell for login / forgot / reset / change-password.
 * View data: $hospitalName, $title (optional), $narrow (optional)
 */

use App\Core\Session;

$flashes = Session::pullFlashes();
$devLink = null;
foreach ($flashes as $i => $flash) {
    if ($flash['type'] === 'dev_link') {
        $devLink = $flash['message'];
        unset($flashes[$i]);
    }
}
$title = $title ?? 'Sign in';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> · <?= e($hospitalName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<script src="<?= asset('vendor/tailwind/tailwind.js') ?>"></script>
<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                navy: {50:'#f0f5fa',100:'#dbe7f3',200:'#bcd3e8',300:'#8db5d6',400:'#5a90bf',500:'#3a73a6',600:'#2a5a88',700:'#234a70',800:'#1b3a5a',900:'#122a47',950:'#0b1f3a'}
            },
            fontFamily: { sans: ['Inter','Segoe UI','system-ui','-apple-system','sans-serif'] }
        }
    }
};
</script>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>

<body class="h-full font-sans antialiased bg-slate-100 dark:bg-slate-950">
<div class="flex min-h-full">

    <!-- Brand panel (desktop) -->
    <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-navy-950 p-12 lg:flex">
        <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.07]" aria-hidden="true">
            <defs>
                <pattern id="grid" width="32" height="32" patternUnits="userSpaceOnUse">
                    <path d="M32 0H0v32" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)"/>
        </svg>
        <div class="pointer-events-none absolute -right-40 -top-40 h-96 w-96 rounded-full bg-teal-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-52 -left-32 h-[28rem] w-[28rem] rounded-full bg-navy-500/30 blur-3xl"></div>

        <div class="relative flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-teal-400 to-teal-600 shadow-xl shadow-teal-950/50">
                <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3z"/></svg>
            </span>
            <div>
                <p class="text-lg font-semibold tracking-tight text-white">MediCore</p>
                <p class="text-xs text-slate-400">Hospital Management Suite</p>
            </div>
        </div>

        <div class="relative max-w-md">
            <?= $this->yield('brand') ?>
        </div>

        <p class="relative text-xs text-slate-500">© <?= date('Y') ?> <?= e($hospitalName) ?> · v<?= e(config('app.version')) ?></p>
    </div>

    <!-- Form panel -->
    <div class="flex w-full flex-col justify-center px-6 py-12 sm:mx-auto sm:max-w-md sm:px-0 lg:w-1/2">
        <div class="card p-8 sm:p-10">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-teal-400 to-teal-600">
                    <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3z"/></svg>
                </span>
                <div>
                    <p class="font-semibold tracking-tight">MediCore</p>
                    <p class="text-xs text-slate-400">Hospital Management Suite</p>
                </div>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <?php if ($flash['type'] === 'dev_link') { continue; } ?>
                <div class="mt-5 flex items-start gap-2.5 rounded-xl border p-3 text-sm first:mt-0
                     <?= in_array($flash['type'], ['error', 'warning'], true) ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300' : 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-900/50 dark:bg-teal-950/40 dark:text-teal-300' ?>">
                    <i data-lucide="<?= in_array($flash['type'], ['error', 'warning'], true) ? 'alert-circle' : 'info' ?>" class="mt-0.5 h-4 w-4 shrink-0"></i>
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ($devLink !== null): ?>
                <div class="mt-5 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-4 text-[13px] leading-relaxed text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                    <p class="mb-1.5 flex items-center gap-1.5 font-semibold">
                        <i data-lucide="flask-conical" class="h-4 w-4"></i>Development mailer — reset link
                    </p>
                    <a href="<?= e($devLink) ?>" class="break-all font-medium underline underline-offset-2"><?= e($devLink) ?></a>
                    <p class="mt-1.5 text-[11.5px] text-amber-700/80 dark:text-amber-400/70">Also written to storage/logs/mail.log. Production sends real email.</p>
                </div>
            <?php endif; ?>

            <?= $this->yield('content') ?>
        </div>

        <?= $this->yield('below') ?>
    </div>
</div>

<script src="<?= asset('vendor/lucide/lucide.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
