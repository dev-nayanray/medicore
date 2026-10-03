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
    theme: { extend: { colors: { navy: { 50:'#f0f5fa',100:'#dbe7f3',200:'#bcd3e8',300:'#8db5d6',400:'#5a90bf',500:'#3a73a6',600:'#2a5a88',700:'#234a70',800:'#1b3a5a',900:'#122a47',950:'#0b1f3a' }, teal: { 450:'#10b3a4' } }, fontFamily: { sans: ['Inter','Segoe UI','system-ui','-apple-system','sans-serif'] }, boxShadow: { card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)', glow: '0 0 40px -10px rgb(13 148 136 / 0.3)' } } }
};
</script>
<link rel="stylesheet" href="<?= asset('css/marketing.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="<?= asset('vendor/lucide/lucide.min.js') ?>" defer></script>
</head>
<body class="bg-white font-sans text-slate-700 antialiased selection:bg-teal-500/20">

<!-- Navigation -->
<nav id="navbar" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <a href="<?= url('/') ?>" class="flex items-center gap-2">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 text-white shadow-lg shadow-teal-900/20">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3z"/></svg>
                </span>
                <span class="text-lg font-bold tracking-tight text-navy-950">MediCore</span>
            </a>
            <div class="hidden items-center gap-1 md:flex">
                <a href="<?= url('/#features') ?>" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-teal-600 transition-colors">Features</a>
                <a href="<?= url('/#showcase') ?>" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-teal-600 transition-colors">Showcase</a>
                <a href="<?= url('/#benefits') ?>" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-teal-600 transition-colors">Benefits</a>
                <a href="<?= url('/pricing') ?>" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-teal-600 transition-colors">Pricing</a>
                <a href="<?= url('/contact') ?>" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-teal-600 transition-colors">Contact</a>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= url('/login') ?>" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-block transition-colors">Login</a>
                <a href="<?= url('/contact') ?>" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 transition-colors">Get Started</a>
                <button id="mobile-menu-btn" class="md:hidden rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>
    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden border-t border-slate-200 bg-white md:hidden">
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
<div class="fixed right-4 top-20 z-50 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-lg" x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)">
    <?= e($flashMsg) ?>
</div>
<?php endif; ?>

<!-- Yielded content -->
<main><?= $this->yield('content') ?></main>

<!-- Footer -->
<footer class="border-t border-slate-200 bg-navy-950 text-slate-300">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-teal-400 to-teal-600 text-white">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3z"/></svg>
                    </span>
                    <span class="text-lg font-bold text-white">MediCore</span>
                </div>
                <p class="mt-3 text-sm text-slate-400">A modern hospital management platform built on raw PHP, MySQL, and Tailwind CSS. Streamline operations, manage patients, and improve care.</p>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-white">Product</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= url('/#features') ?>" class="text-slate-400 hover:text-teal-400">Features</a></li>
                    <li><a href="<?= url('/pricing') ?>" class="text-slate-400 hover:text-teal-400">Pricing</a></li>
                    <li><a href="<?= url('/#showcase') ?>" class="text-slate-400 hover:text-teal-400">Showcase</a></li>
                    <li><a href="<?= url('/login') ?>" class="text-slate-400 hover:text-teal-400">Login</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-white">Company</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= url('/#benefits') ?>" class="text-slate-400 hover:text-teal-400">Benefits</a></li>
                    <li><a href="<?= url('/#how-it-works') ?>" class="text-slate-400 hover:text-teal-400">How It Works</a></li>
                    <li><a href="<?= url('/#solutions') ?>" class="text-slate-400 hover:text-teal-400">Solutions</a></li>
                    <li><a href="<?= url('/#security') ?>" class="text-slate-400 hover:text-teal-400">Security</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-white">Support</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="<?= url('/contact') ?>" class="text-slate-400 hover:text-teal-400">Contact</a></li>
                    <li><a href="<?= url('/#faq') ?>" class="text-slate-400 hover:text-teal-400">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-8 border-t border-slate-800 pt-6 text-center text-xs text-slate-500">
            &copy; <?= e($year) ?> MediCore Hospital Management System. All rights reserved.
        </div>
    </div>
</footer>

<script src="<?= asset('js/marketing.js') ?>" defer></script>
</body>
</html>
