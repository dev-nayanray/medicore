<?php

declare(strict_types=1);

/**
 * Admin layout — sidebar + topbar chrome shared by every admin page.
 *
 * Expected view data:
 *   $title        page title (string)
 *   $breadcrumbs  ['Label' => 'url'|null, ...]  (optional)
 *   $active       sidebar key: dashboard|users|roles|audit|settings|profile
 *   $content      (yielded)
 */

use App\Core\Session;

$user = auth_user();
$hospitalName = (string) setting('hospital_name', 'MediCore General Hospital');
$flashes = Session::pullFlashes();
$breadcrumbs = $breadcrumbs ?? [];
$title = $title ?? 'Admin';
$active = $active ?? '';
$currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

/** Sidebar section definition. 'soon' items are planned modules. */
$menu = [
    [
        'label' => 'Overview', 'items' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => url('/'), 'icon' => 'layout-dashboard', 'can' => null, 'soon' => false],
        ],
    ],
    [
        'label' => 'Hospital', 'items' => [
            ['key' => 'patients',    'label' => 'Patients',     'href' => url('/admin/patients'), 'icon' => 'users', 'can' => 'patients.view', 'soon' => false],
            ['key' => 'appointments','label' => 'Appointments', 'href' => '#', 'icon' => 'calendar-days',  'can' => null, 'soon' => true],
            ['key' => 'doctors',    'label' => 'Doctors',       'href' => url('/admin/doctors'), 'icon' => 'stethoscope', 'can' => 'doctors.view', 'soon' => false],
            ['key' => 'departments','label' => 'Departments',   'href' => url('/admin/departments'), 'icon' => 'building-2', 'can' => 'departments.view', 'soon' => false],
            ['key' => 'staff',     'label' => 'Staff',          'href' => url('/admin/staff'), 'icon' => 'id-card', 'can' => 'staff.view', 'soon' => false],
        ],
    ],
    [
        'label' => 'Operations', 'items' => [
            ['key' => 'beds',   'label' => 'Bed Management', 'href' => '#', 'icon' => 'bed-double',  'can' => null, 'soon' => true],
            ['key' => 'lab',    'label' => 'Laboratory',     'href' => '#', 'icon' => 'flask-conical','can' => null, 'soon' => true],
            ['key' => 'pharma', 'label' => 'Pharmacy',       'href' => '#', 'icon' => 'pill',        'can' => null, 'soon' => true],
        ],
    ],
    [
        'label' => 'Finance', 'items' => [
            ['key' => 'billing', 'label' => 'Billing & Invoices', 'href' => '#', 'icon' => 'receipt-text', 'can' => null, 'soon' => true],
            ['key' => 'payments','label' => 'Payments',           'href' => '#', 'icon' => 'credit-card',  'can' => null, 'soon' => true],
            ['key' => 'reports', 'label' => 'Reports',            'href' => '#', 'icon' => 'chart-pie',    'can' => null, 'soon' => true],
        ],
    ],
    [
        'label' => 'Administration', 'items' => array_values(array_filter([
            ['key' => 'users',    'label' => 'Staff Users',    'href' => url('/admin/users'),    'icon' => 'user-round-cog',  'can' => 'users.view',    'soon' => false],
            ['key' => 'roles',    'label' => 'Roles & Permissions', 'href' => url('/admin/roles'), 'icon' => 'shield-check', 'can' => 'roles.view', 'soon' => false],
            ['key' => 'permissions', 'label' => 'Permission Catalogue', 'href' => url('/admin/permissions'), 'icon' => 'key-round', 'can' => 'roles.view', 'soon' => false],
            ['key' => 'audit',    'label' => 'Audit Logs',     'href' => url('/admin/audit-logs'), 'icon' => 'scroll-text', 'can' => 'audit.view',  'soon' => false],
            ['key' => 'settings', 'label' => 'Hospital Settings', 'href' => url('/admin/settings'), 'icon' => 'settings-2', 'can' => 'settings.view', 'soon' => false],
        ], static fn (array $item): bool => $item['can'] === null || can($item['can']))),
    ],
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.MEDICORE_BASE = <?= json_encode(rtrim(url('/'), '/')) ?>;</script>
<title><?= e($title) ?> · <?= e($hospitalName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">

<script>
/* Theme bootstrap — must run before first paint to avoid a flash. */
(function () {
    var t = localStorage.getItem('medicore-theme');
    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
})();
</script>

<script src="<?= asset('vendor/tailwind/tailwind.js') ?>"></script>
<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                navy: {
                    50: '#f0f5fa', 100: '#dbe7f3', 200: '#bcd3e8', 300: '#8db5d6', 400: '#5a90bf',
                    500: '#3a73a6', 600: '#2a5a88', 700: '#234a70', 800: '#1b3a5a', 900: '#122a47',
                    950: '#0b1f3a'
                },
                teal: { 450: '#10b3a4' }
            },
            fontFamily: {
                sans: ['Inter', 'Segoe UI', 'system-ui', '-apple-system', 'sans-serif']
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)'
            }
        }
    }
};
</script>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>

<body class="h-full font-sans bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-teal-500/20">

<div x-data="layout" class="min-h-full">

    <!-- ============================== Sidebar ============================== -->
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-navy-950 text-slate-300 transition-all duration-200 dark:bg-slate-900 dark:border-r dark:border-slate-800
                  lg:translate-x-0"
           :class="[
               collapsed ? 'lg:w-[76px]' : 'lg:w-72',
               sidebarOpen ? 'translate-x-0 shadow-2xl shadow-navy-950/60' : '-translate-x-full'
           ]">

        <!-- Brand -->
        <div class="flex h-16 items-center gap-3 px-5 shrink-0" :class="collapsed ? 'lg:justify-center lg:px-2' : ''">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 shadow-lg shadow-teal-900/40">
                <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3z"/>
                </svg>
            </span>
            <div class="leading-tight" :class="collapsed ? 'lg:hidden' : ''">
                <p class="text-[15px] font-semibold tracking-tight text-white">MediCore</p>
                <p class="text-[11px] text-slate-400">Hospital Suite</p>
            </div>
            <button type="button" class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden"
                    @click="sidebarOpen = false" aria-label="Close menu">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4 scrollbar-thin">
            <?php foreach ($menu as $section): ?>
                <div>
                    <p class="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500"
                       :class="collapsed ? 'lg:hidden' : ''"><?= e($section['label']) ?></p>
                    <ul class="space-y-0.5">
                        <?php foreach ($section['items'] as $item): ?>
                            <?php $isActive = $active === $item['key']; ?>
                            <li>
                                <?php if ($item['soon']): ?>
                                    <button type="button"
                                            @click="soon(<?= e(json_encode($item['label'])) ?>)"
                                            class="nav-link cursor-default">
                                <?php else: ?>
                                    <a href="<?= e($item['href']) ?>"
                                       class="nav-link <?= $isActive ? 'is-active' : '' ?>"
                                       title="<?= e($item['label']) ?>">
                                <?php endif; ?>
                                        <i data-lucide="<?= e($item['icon']) ?>" class="h-[18px] w-[18px] shrink-0"></i>
                                        <span class="truncate" :class="collapsed ? 'lg:hidden' : ''"><?= e($item['label']) ?></span>
                                        <?php if ($item['soon']): ?>
                                            <span class="ml-auto rounded-full bg-white/5 px-2 py-0.5 text-[10px] font-medium text-slate-500"
                                                  :class="collapsed ? 'lg:hidden' : ''">Soon</span>
                                        <?php elseif ($isActive): ?>
                                            <span class="ml-auto h-1.5 w-1.5 rounded-full bg-teal-400" :class="collapsed ? 'lg:hidden' : ''"></span>
                                        <?php endif; ?>
                                <?php if ($item['soon']): ?></button><?php else: ?></a><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>

        <!-- Sidebar footer: signed-in identity -->
        <div class="border-t border-white/5 p-3 shrink-0">
            <div class="flex items-center gap-3 rounded-xl px-2 py-2">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-teal-500/15 text-[12px] font-semibold text-teal-300 ring-1 ring-teal-500/30">
                    <?= e(initials((string) ($user['name'] ?? 'U'))) ?>
                </span>
                <div class="min-w-0" :class="collapsed ? 'lg:hidden' : ''">
                    <p class="truncate text-[13px] font-medium text-white"><?= e($user['name'] ?? '') ?></p>
                    <p class="truncate text-[11px] capitalize text-slate-400"><?= e(str_replace('-', ' ', (string) (($user['roles'][0] ?? 'member')))) ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Mobile overlay -->
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-navy-950/60 backdrop-blur-sm lg:hidden"
         @click="sidebarOpen = false" x-cloak></div>

    <!-- ============================== Main column ============================== -->
    <div class="flex min-h-full flex-col transition-all duration-200 lg:pl-72" :class="collapsed ? 'lg:pl-[76px]' : 'lg:pl-72'">

        <!-- Topbar -->
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200/80 bg-white/85 px-4 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/85 sm:px-6">
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 lg:hidden"
                    @click="sidebarOpen = true" aria-label="Open menu">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
            <button type="button" class="hidden rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200 lg:block"
                    @click="toggleCollapse()" aria-label="Toggle sidebar" title="Toggle sidebar ( [ )">
                <i data-lucide="panel-left-close" class="h-5 w-5 transition-transform" :class="collapsed ? 'rotate-180' : ''"></i>
            </button>

            <!-- Global search -->
            <div class="relative flex-1 max-w-md" x-data="globalSearch">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="global-search" autocomplete="off" x-model="query" @focus="open = true" @input.debounce.300ms="search()"
                       @keydown.escape.window="open = false"
                       placeholder="Search staff, activity…"
                       class="input pl-9 pr-14 text-sm">
                <kbd class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-400 dark:border-slate-700 dark:bg-slate-800">Ctrl K</kbd>

                <div x-show="open && query.length > 0" @click.outside="open = false" x-cloak
                     class="absolute left-0 right-0 top-full z-30 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900">
                    <div class="max-h-96 overflow-y-auto p-2">
                        <template x-if="loading">
                            <p class="px-3 py-6 text-center text-sm text-slate-400">Searching…</p>
                        </template>
                        <template x-if="!loading && results.length === 0 && modules.length === 0">
                            <p class="px-3 py-6 text-center text-sm text-slate-400">No matches found.</p>
                        </template>
                        <template x-for="group in results" :key="group.label">
                            <div class="mb-1">
                                <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400" x-text="group.label"></p>
                                <template x-for="item in group.results" :key="item.title">
                                    <a :href="item.url" class="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800">
                                        <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                                            <i data-lucide="corner-down-right" class="h-3.5 w-3.5"></i>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-slate-700 dark:text-slate-200" x-text="item.title"></span>
                                            <span class="block truncate text-xs text-slate-400" x-text="item.subtitle"></span>
                                        </span>
                                    </a>
                                </template>
                            </div>
                        </template>
                        <template x-if="modules.length > 0">
                            <div class="border-t border-slate-100 px-3 py-2 dark:border-slate-800">
                                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Planned modules</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="m in modules" :key="m.label">
                                        <span class="badge badge-slate"><span x-text="m.label"></span> · soon</span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <!-- Theme toggle -->
                <button type="button" @click="toggleTheme()" class="icon-btn" aria-label="Toggle theme" title="Toggle theme">
                    <i data-lucide="sun" class="h-[18px] w-[18px] dark:hidden"></i>
                    <i data-lucide="moon" class="hidden h-[18px] w-[18px] dark:block"></i>
                </button>

                <!-- Environment badge (dev visibility) -->
                <?php if (config('app.env') !== 'production'): ?>
                    <span class="badge badge-amber hidden sm:inline-flex" title="APP_ENV from .env — never show this badge in production"><?= e(strtoupper((string) config('app.env'))) ?></span>
                <?php endif; ?>

                <!-- Notifications -->
                <div class="relative" x-data="notifications" x-init="load()">
                    <button type="button" @click="toggle()" class="icon-btn relative" aria-label="Notifications">
                        <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                        <span x-show="unread > 0" x-cloak
                              class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white"
                              x-text="unread"></span>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-transition.origin.top.right x-cloak
                         class="absolute right-0 top-full z-30 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                            <p class="text-sm font-semibold">Notifications</p>
                            <span class="text-[11px] text-slate-400">from audit trail</span>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <template x-if="items.length === 0">
                                <p class="px-4 py-8 text-center text-sm text-slate-400">No activity yet.</p>
                            </template>
                            <template x-for="(n, i) in items" :key="i">
                                <div class="flex gap-3 border-b border-slate-50 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800/60 dark:hover:bg-slate-800/50">
                                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg"
                                          :class="{
                                              'bg-teal-500/10 text-teal-600 dark:text-teal-400': n.tone === 'teal',
                                              'bg-amber-500/10 text-amber-600 dark:text-amber-400': n.tone === 'amber',
                                              'bg-rose-500/10 text-rose-600 dark:text-rose-400': n.tone === 'red',
                                              'bg-navy-500/10 text-navy-600 dark:text-navy-300': n.tone === 'navy',
                                              'bg-slate-500/10 text-slate-500': n.tone === 'slate'
                                          }">
                                        <i :data-lucide="n.icon" class="h-4 w-4"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] leading-snug text-slate-700 dark:text-slate-200" x-text="n.description"></p>
                                        <p class="mt-0.5 text-[11px] text-slate-400"><span x-text="n.actor"></span> · <span x-text="n.when"></span></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Profile dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-xl p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Account menu">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-navy-900 text-[11px] font-semibold text-white ring-2 ring-white dark:ring-slate-700">
                            <?= e(initials((string) ($user['name'] ?? 'U'))) ?>
                        </span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block max-w-36 truncate text-[13px] font-medium"><?= e($user['name'] ?? '') ?></span>
                            <span class="block text-[11px] text-slate-400"><?= e($user['email'] ?? '') ?></span>
                        </span>
                        <i data-lucide="chevrons-up-down" class="hidden h-4 w-4 text-slate-400 md:block"></i>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-transition.origin.top.right x-cloak
                         class="absolute right-0 top-full z-30 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                            <p class="truncate text-sm font-semibold"><?= e($user['name'] ?? '') ?></p>
                            <p class="mt-1 flex flex-wrap gap-1">
                                <?php foreach (($user['roles'] ?? []) as $roleSlug): ?>
                                    <span class="badge badge-teal"><?= e(str_replace('-', ' ', $roleSlug)) ?></span>
                                <?php endforeach; ?>
                            </p>
                        </div>
                        <a href="<?= url('/admin/profile') ?>" class="menu-item"><i data-lucide="user-round" class="h-4 w-4"></i>My profile</a>
                        <a href="<?= url('/admin/profile/activity') ?>" class="menu-item"><i data-lucide="history" class="h-4 w-4"></i>My activity</a>
                        <a href="<?= url('/admin/settings') ?>" class="menu-item"><i data-lucide="settings-2" class="h-4 w-4"></i>Hospital settings</a>
                        <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
                        <form method="post" action="<?= url('/logout') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="menu-item w-full !text-rose-600 dark:!text-rose-400"><i data-lucide="log-out" class="h-4 w-4"></i>Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page body -->
        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Breadcrumbs -->
            <nav class="mb-4 flex items-center gap-1.5 text-[13px] text-slate-400" aria-label="Breadcrumb">
                <a href="<?= url('/') ?>" class="inline-flex items-center gap-1 hover:text-teal-600 dark:hover:text-teal-400">
                    <i data-lucide="home" class="h-3.5 w-3.5"></i>Home
                </a>
                <?php foreach ($breadcrumbs as $label => $href): ?>
                    <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                    <?php if (is_string($href) && $href !== ''): ?>
                        <a href="<?= e($href) ?>" class="hover:text-teal-600 dark:hover:text-teal-400"><?= e($label) ?></a>
                    <?php else: ?>
                        <span class="font-medium text-slate-600 dark:text-slate-300"><?= e($label) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <?= $this->yield('content') ?>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-200/80 px-4 py-3.5 sm:px-6 lg:px-8 dark:border-slate-800">
            <div class="flex flex-col items-center justify-between gap-2 text-[11.5px] text-slate-400 sm:flex-row">
                <p>
                    <span class="font-medium text-slate-500 dark:text-slate-400">MediCore</span> v<?= e(config('app.version')) ?>
                    · © <?= date('Y') ?> <?= e($hospitalName) ?>
                </p>
                <p class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        DB ready
                    </span>
                    <span class="inline-flex items-center gap-1.5">PHP <?= e(PHP_VERSION) ?></span>
                    <span class="hidden sm:inline"><?= e(ucfirst((string) config('app.env'))) ?> environment</span>
                </p>
            </div>
        </footer>
    </div>
</div>

<!-- Confirmation dialog host — forms with data-confirm="Title|Message[|danger]" route through here -->
<div x-data="confirmDialog" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="resolve(false)"></div>
        <div x-show="open" x-transition.scale.origin.center
             class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <span class="grid h-11 w-11 place-items-center rounded-xl"
                  :class="danger ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-teal-500/10 text-teal-600 dark:text-teal-400'">
                <i :data-lucide="danger ? 'alert-triangle' : 'help-circle'" class="h-5 w-5"></i>
            </span>
            <h3 class="mt-4 text-[15px] font-semibold text-slate-900 dark:text-white" x-text="title"></h3>
            <p class="mt-1.5 text-[13px] leading-relaxed text-slate-500 dark:text-slate-400" x-text="message"></p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="resolve(false)">Cancel</button>
                <button type="button" class="btn" :class="danger ? 'btn-danger' : 'btn-primary'" @click="resolve(true)" x-text="danger ? 'Delete anyway' : 'Confirm'"></button>
            </div>
        </div>
    </div>
</div>

<!-- Toast host -->
<div x-data="toasts" class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 flex-col gap-2">
    <template x-for="toast in list" :key="toast.id">
        <div x-show="toast.visible" x-transition class="pointer-events-auto flex items-start gap-3 rounded-xl border p-3.5 shadow-lg"
             :class="{
                 'border-emerald-200 bg-white dark:border-emerald-900/60 dark:bg-slate-900': toast.type === 'success',
                 'border-rose-200 bg-white dark:border-rose-900/60 dark:bg-slate-900': toast.type === 'error',
                 'border-teal-200 bg-white dark:border-teal-900/60 dark:bg-slate-900': toast.type === 'info'
             }">
            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg"
                  :class="{
                      'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400': toast.type === 'success',
                      'bg-rose-500/10 text-rose-600 dark:text-rose-400': toast.type === 'error',
                      'bg-teal-500/10 text-teal-600 dark:text-teal-400': toast.type === 'info'
                  }">
                <i :data-lucide="toast.type === 'success' ? 'check-circle-2' : (toast.type === 'error' ? 'alert-circle' : 'info')" class="h-4 w-4"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-semibold text-slate-800 dark:text-slate-100" x-text="toast.title"></p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400" x-text="toast.message"></p>
            </div>
            <button type="button" class="shrink-0 rounded p-0.5 text-slate-400 hover:text-slate-600" @click="dismiss(toast.id)" aria-label="Dismiss">
                <i data-lucide="x" class="h-3.5 w-3.5"></i>
            </button>
        </div>
    </template>
</div>

<!-- Server-side flash -> toast bridge -->
<script>
window.__flashes = <?= json_encode($flashes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<!--
   Script order matters: app.js registers its `alpine:init` listener at the
   top level. Alpine's CDN build auto-starts via queueMicrotask() at the end
   of its own script, which fires `alpine:init` BEFORE the next deferred
   script runs. If Alpine loads first, the event fires before app.js can
   register the listener — components (layout, toasts, confirmDialog, …)
   never get registered and every @click / x-show silently breaks.

   Loading app.js BEFORE Alpine guarantees the listener is in place when
   Alpine fires the event. Both are `defer`d so they still execute after
   HTML parsing, preserving the non-blocking behaviour.
-->
<script src="<?= asset('js/app.js') ?>" defer></script>
<script src="<?= asset('vendor/alpine/alpine.min.js') ?>" defer></script>
<script src="<?= asset('vendor/lucide/lucide.min.js') ?>"></script>
</body>
</html>
