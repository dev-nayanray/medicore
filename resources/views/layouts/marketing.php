<?php
/**
 * Marketing layout — public-facing website shell.
 * Separate from the authenticated admin panel. No auth required.
 * $title, $content (yielded), $hospitalName
 */
$hospitalName = $hospitalName ?? 'MediCore General Hospital';
$flashType = $_SESSION['_flash']['type'] ?? null;
$flashMsg = $_SESSION['_flash']['message'] ?? null;
if ($flashType) { unset($_SESSION['_flash']); }
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'MediCore — Smarter Hospital Management') ?></title>
<meta name="description" content="MediCore is a modern hospital management platform that streamlines operations, manages patients, and improves administrative efficiency across all departments.">
<meta property="og:title" content="MediCore — Smarter Hospital Management">
<meta property="og:description" content="A complete hospital management system with patient records, appointments, billing, pharmacy, laboratory, and bed management.">
<meta property="og:type" content="website">
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<script src="<?= asset('vendor/tailwind/tailwind.js') ?>"></script>
<script>
tailwind.config = {
    darkMode: 'class',
    theme: { extend: {
        colors: { navy: { 50:'#f0f5fa',100:'#dbe7f3',200:'#bcd3e8',300:'#8db5d6',400:'#5a90bf',500:'#3a73a6',600:'#2a5a88',700:'#234a70',800:'#1b3a5a',900:'#122a47',950:'#0b1f3a' }, teal: { 450:'#10b3a4' } },
        fontFamily: { sans: ['Inter','Segoe UI','system-ui','-apple-system','sans-serif'] },
        boxShadow: { card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)', glow: '0 0 40px -10px rgb(13 148 136 / 0.3)' }
    } }
};
</script>
<link rel="stylesheet" href="<?= asset('css/marketing.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="<?= asset('vendor/lucide/lucide.min.js') ?>" defer></script>
</head>
<body class="bg-white font-sans text-slate-700 antialiased selection:bg-teal-500/20">

<!-- Navigation -->
<nav id="navbar" class="fixed top-0 left-0 right-0 z-50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <a href="<?= url('/') ?>" class="flex items-center gap-2.5 group">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 text-white shadow-lg shadow-teal-600/30 transition-transform group-hover:scale-105">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3z"/></svg>
                </span>
                <span class="text-lg font-bold tracking-tight text-navy-950 nav-link-marketing transition-colors">MediCore</span>
            </a>

            <div class="hidden items-center gap-0.5 md:flex">
                <a href="<?= url('/#features') ?>" class="nav-link-marketing rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:text-teal-600">Features</a>
                <a href="<?= url('/#showcase') ?>" class="nav-link-marketing rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:text-teal-600">Showcase</a>
                <a href="<?= url('/#benefits') ?>" class="nav-link-marketing rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:text-teal-600">Benefits</a>
                <a href="<?= url('/pricing') ?>" class="nav-link-marketing rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:text-teal-600">Pricing</a>
                <a href="<?= url('/contact') ?>" class="nav-link-marketing rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:text-teal-600">Contact</a>
            </div>

            <div class="flex items-center gap-2">
                <a href="<?= url('/login') ?>" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-block transition-colors nav-link-marketing">Login</a>
                <a href="<?= url('/contact') ?>" class="btn-glow rounded-lg bg-gradient-to-br from-teal-500 to-teal-700 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-teal-600/25 transition-all hover:-translate-y-0.5">Get Started</a>
                <button id="mobile-menu-btn" class="md:hidden rounded-lg p-2 text-slate-600 hover:bg-slate-100 nav-link-marketing transition-colors" aria-label="Menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>
    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden border-t border-slate-200 bg-white/95 backdrop-blur-md md:hidden">
        <div class="space-y-1 px-4 py-3">
            <a href="<?= url('/#features') ?>" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Features</a>
            <a href="<?= url('/#showcase') ?>" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Showcase</a>
            <a href="<?= url('/#benefits') ?>" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Benefits</a>
            <a href="<?= url('/pricing') ?>" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Pricing</a>
            <a href="<?= url('/contact') ?>" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Contact</a>
            <a href="<?= url('/login') ?>" class="block rounded-lg px-3 py-2 text-sm font-medium text-teal-600 hover:bg-slate-100">Login</a>
        </div>
    </div>
</nav>

<?php if ($flashMsg): ?>
<div class="fixed right-4 top-20 z-50 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm font-medium text-emerald-800 shadow-lg" x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)">
    <div class="flex items-center gap-2">
        <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <?= e($flashMsg) ?>
    </div>
</div>
<?php endif; ?>

<!-- Yielded content -->
<main class="overflow-hidden"><?= $this->yield('content') ?></main>

<!-- Footer -->
<footer class="relative overflow-hidden border-t border-slate-200 bg-navy-950 text-slate-300">
    <div class="absolute inset-0 bg-grid-dark opacity-30"></div>
    <div class="absolute right-0 top-0 h-72 w-72 rounded-full bg-teal-500/10 blur-3xl"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 text-white shadow-md">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3z"/></svg>
                    </span>
                    <span class="text-lg font-bold text-white">MediCore</span>
                </div>
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">A modern hospital management platform built on raw PHP, MySQL, and Tailwind CSS. Streamline operations, manage patients, and improve care.</p>
                <div class="mt-4 flex gap-2">
                    <a href="#" class="grid h-8 w-8 place-items-center rounded-lg bg-slate-800 text-slate-400 hover:bg-teal-500 hover:text-white transition-colors" aria-label="GitHub">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.418 2.865 8.166 6.839 9.489.5.092.682-.217.682-.483 0-.237-.009-.868-.014-1.703-2.782.602-3.369-1.34-3.369-1.34-.455-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.339-2.22-.253-4.555-1.111-4.555-4.945 0-1.092.39-1.984 1.031-2.684-.104-.253-.447-1.27.098-2.648 0 0 .84-.27 2.75 1.024A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.91-1.019 2.75-1.024 2.75-1.024.546 1.378.203 2.395.099 2.648.64.7 1.03 1.592 1.03 2.684 0 3.842-2.339 4.687-4.564 4.935.359.309.678.922.678 1.859 0 1.344-.012 2.431-.012 2.758 0 .269.18.581.688.481A10.005 10.005 0 0022 12.017C22 6.484 17.523 2 12 2z"/></svg>
                    </a>
                    <a href="#" class="grid h-8 w-8 place-items-center rounded-lg bg-slate-800 text-slate-400 hover:bg-teal-500 hover:text-white transition-colors" aria-label="Email">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5h18v14H3V5zm0 0l9 7 9-7"/></svg>
                    </a>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-white">Product</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= url('/#features') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Features</a></li>
                    <li><a href="<?= url('/pricing') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Pricing</a></li>
                    <li><a href="<?= url('/#showcase') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Showcase</a></li>
                    <li><a href="<?= url('/login') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Login</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-white">Company</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= url('/#benefits') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Benefits</a></li>
                    <li><a href="<?= url('/#how-it-works') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">How It Works</a></li>
                    <li><a href="<?= url('/#solutions') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Solutions</a></li>
                    <li><a href="<?= url('/#security') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Security</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-white">Support</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= url('/contact') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">Contact</a></li>
                    <li><a href="<?= url('/#faq') ?>" class="text-slate-400 hover:text-teal-400 transition-colors">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-slate-800 pt-6 text-xs text-slate-500 sm:flex-row">
            <p>&copy; <?= e($year) ?> MediCore Hospital Management System. All rights reserved.</p>
            <p class="flex items-center gap-3">
                <a href="<?= url('/login') ?>" class="hover:text-teal-400 transition-colors">Staff Login</a>
                <span class="text-slate-700">·</span>
                <a href="<?= url('/contact') ?>" class="hover:text-teal-400 transition-colors">Request Demo</a>
            </p>
        </div>
    </div>
</footer>

<script src="<?= asset('js/marketing.js') ?>" defer></script>
</body>
</html>
