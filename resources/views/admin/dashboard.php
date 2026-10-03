<?php

declare(strict_types=1);

/**
 * Dashboard — every number is a real query result (DashboardService).
 * $stats, $charts, $recentActivity, $modules, $system provided by the controller.
 */

$this->extend('layouts/admin');
$title = 'Dashboard';
$active = 'dashboard';
$breadcrumbs = [];

$user = auth_user();
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>

<?php $this->section('content'); ?>

<!-- Hero greeting -->
<div class="relative mb-6 overflow-hidden rounded-2xl border border-navy-200/40 bg-gradient-to-br from-navy-950 via-navy-900 to-navy-800 p-6 text-white sm:p-8">
    <!-- Decorative shapes -->
    <div class="absolute inset-0 bg-grid-dark opacity-20"></div>
    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-teal-500/20 blur-3xl"></div>
    <div class="absolute -bottom-32 -left-10 h-64 w-64 rounded-full bg-navy-500/30 blur-3xl"></div>

    <div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
        <div class="min-w-0">
            <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-teal-300">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-teal-400 opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-teal-400"></span>
                </span>
                <?= e(date('l, F j, Y')) ?>
            </p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                <?= e($greeting) ?>, <?= e(explode(' ', (string) ($user['name'] ?? ''))[0]) ?>.
            </h1>
            <p class="mt-1.5 text-sm text-slate-300">
                Here's the live pulse of <?= e(setting('hospital_name', 'your hospital')) ?>.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if (can('consultations.create')): ?>
                <a href="<?= url('/admin/consultations/workspace') ?>" class="btn btn-secondary !bg-white/10 !border-white/15 !text-white hover:!bg-white/20 hover:!border-white/25">
                    <i data-lucide="stethoscope" class="h-4 w-4"></i><span class="hidden sm:inline">Clinical workspace</span>
                </a>
            <?php endif; ?>
            <?php if (can('appointments.create')): ?>
                <a href="<?= url('/admin/appointments/create') ?>" class="btn btn-primary">
                    <i data-lucide="calendar-plus" class="h-4 w-4"></i><span class="hidden sm:inline">New appointment</span>
                </a>
            <?php elseif (can('patients.create')): ?>
                <a href="<?= url('/admin/patients/create') ?>" class="btn btn-primary">
                    <i data-lucide="user-plus" class="h-4 w-4"></i><span class="hidden sm:inline">Register patient</span>
                </a>
            <?php else: ?>
                <a href="<?= url('/admin/users') ?>" class="btn btn-primary">
                    <i data-lucide="user-round-plus" class="h-4 w-4"></i><span class="hidden sm:inline">Staff directory</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick action grid -->
<div class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
    <?php
    $quickActions = [];
    if (can('appointments.view')) $quickActions[] = ['href' => url('/admin/appointments'), 'icon' => 'calendar-days', 'label' => 'Appointments', 'tone' => 'navy'];
    if (can('patients.view')) $quickActions[] = ['href' => url('/admin/patients'), 'icon' => 'users', 'label' => 'Patients', 'tone' => 'teal'];
    if (can('doctors.view')) $quickActions[] = ['href' => url('/admin/doctors'), 'icon' => 'stethoscope', 'label' => 'Doctors', 'tone' => 'teal'];
    if (can('appointments.view')) $quickActions[] = ['href' => url('/admin/appointments/queue'), 'icon' => 'list-checks', 'label' => 'Live Queue', 'tone' => 'amber'];
    if (can('billing.view')) $quickActions[] = ['href' => url('/admin/billing/dashboard'), 'icon' => 'banknote', 'label' => 'Finance', 'tone' => 'emerald'];
    if (can('beds.view')) $quickActions[] = ['href' => url('/admin/beds'), 'icon' => 'bed-double', 'label' => 'Beds', 'tone' => 'rose'];
    $quickActions = array_slice($quickActions, 0, 6);

    $toneMap = [
        'teal' => 'group-hover:text-teal-600 dark:group-hover:text-teal-400 bg-teal-500/10 text-teal-600 dark:text-teal-400',
        'navy' => 'group-hover:text-navy-600 dark:group-hover:text-navy-300 bg-navy-500/10 text-navy-600 dark:text-navy-300',
        'amber'=> 'group-hover:text-amber-600 dark:group-hover:text-amber-400 bg-amber-500/10 text-amber-600 dark:text-amber-400',
        'rose' => 'group-hover:text-rose-600 dark:group-hover:text-rose-400 bg-rose-500/10 text-rose-600 dark:text-rose-400',
        'emerald'=> 'group-hover:text-emerald-600 dark:group-hover:text-emerald-400 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    ];
    foreach ($quickActions as $i => $qa):
        $t = $toneMap[$qa['tone']] ?? $toneMap['teal'];
    ?>
        <a href="<?= e($qa['href']) ?>" class="group flex flex-col items-start gap-2 rounded-xl border border-slate-200 bg-white p-3 transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 mc-fade-in" style="animation-delay:<?= $i * 40 ?>ms">
            <span class="grid h-9 w-9 place-items-center rounded-lg <?= $t ?> transition-all">
                <i data-lucide="<?= e($qa['icon']) ?>" class="h-[18px] w-[18px]"></i>
            </span>
            <span class="text-[12px] font-medium text-slate-600 dark:text-slate-300 group-hover:text-current transition-colors"><?= e($qa['label']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Stat tiles -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    <div class="mc-fade-in" style="animation-delay:0ms">
        <?= $this->insert('components/stat-card', ['key' => 'patients', 'label' => 'Registered Patients', 'icon' => 'users', 'stat' => $stats['patients'], 'tone' => 'teal']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:60ms">
        <?= $this->insert('components/stat-card', ['key' => 'appointments', 'label' => "Today's Appointments", 'icon' => 'calendar-days', 'stat' => $stats['appointments'], 'tone' => 'navy']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:120ms">
        <?= $this->insert('components/stat-card', ['key' => 'staff', 'label' => 'Doctors & Staff', 'icon' => 'stethoscope', 'stat' => $stats['staff'], 'tone' => 'violet']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:180ms">
        <?= $this->insert('components/stat-card', ['key' => 'revenue', 'label' => "Today's Revenue", 'icon' => 'banknote', 'stat' => $stats['revenue'], 'tone' => 'emerald']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:240ms">
        <?= $this->insert('components/stat-card', ['key' => 'pending', 'label' => 'Pending Payments', 'icon' => 'receipt-text', 'stat' => $stats['pending_bills'], 'tone' => 'amber']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:300ms">
        <?= $this->insert('components/stat-card', ['key' => 'beds', 'label' => 'Available Beds', 'icon' => 'bed-double', 'stat' => $stats['beds'], 'tone' => 'rose']) ?>
    </div>
</div>

<!-- Charts + activity -->
<div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <div class="card xl:col-span-2 mc-slide-up">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold">Appointments — last 14 days</h2>
                <p class="text-xs text-slate-400">Daily volume across all departments</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-teal"><span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span> Chart.js</span>
                <?php if ($charts['appointments']['available']): ?>
                    <span class="badge badge-emerald">Live</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-5">
            <?php if ($charts['appointments']['available']): ?>
                <canvas id="appointmentsChart" height="132"
                        data-labels="<?= e(json_encode($charts['appointments']['labels'])) ?>"
                        data-values="<?= e(json_encode($charts['appointments']['data'])) ?>"></canvas>
            <?php else: ?>
                <?= $this->insert('components/empty-state', [
                    'icon'    => 'chart-line',
                    'title'   => 'Appointment analytics will appear here',
                    'message' => 'The appointments module is not installed yet. Once its migration lands, this chart renders live daily volumes — no placeholder curves in the meantime.',
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent activity (real audit trail) -->
    <div class="card mc-slide-up" style="animation-delay:80ms">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold">Recent activity</h2>
                <p class="text-xs text-slate-400">Straight from the audit trail</p>
            </div>
            <?php if (can('audit.view')): ?>
                <a href="<?= url('/admin/audit-logs') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">View all</a>
            <?php endif; ?>
        </div>

        <?php if ($recentActivity === []): ?>
            <div class="p-5">
                <?= $this->insert('components/empty-state', [
                    'icon'    => 'history',
                    'compact' => true,
                    'title'   => 'No activity recorded yet',
                    'message' => 'Sign-ins, settings changes and administrative actions will appear here as they happen.',
                ]) ?>
            </div>
        <?php else: ?>
            <ol class="relative divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($recentActivity as $event): ?>
                    <?php
                    $isLogin = str_starts_with((string) $event['event'], 'login');
                    $isFail = str_starts_with((string) $event['event'], 'login.failed');
                    $tone = $isFail ? 'rose' : ($isLogin ? 'teal' : (str_starts_with((string) $event['event'], 'settings') ? 'amber' : 'navy'));
                    $toneClass = [
                        'teal'  => 'bg-teal-500/10 text-teal-600 dark:text-teal-400',
                        'rose'  => 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
                        'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                        'navy'  => 'bg-navy-500/10 text-navy-600 dark:text-navy-300',
                    ][$tone];
                    $icon = $isFail ? 'shield-alert' : ($isLogin ? 'log-in' : (str_starts_with((string) $event['event'], 'settings') ? 'settings' : 'server'));
                    ?>
                    <li class="flex gap-3.5 px-5 py-3.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg <?= $toneClass ?> ring-1 ring-current/10">
                            <i data-lucide="<?= $icon ?>" class="h-4 w-4"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] leading-snug text-slate-700 dark:text-slate-200">
                                <?= e($event['description'] ?? $event['event']) ?>
                            </p>
                            <p class="mt-1 flex items-center gap-1.5 text-[11px] text-slate-400">
                                <span class="font-medium text-slate-500 dark:text-slate-400"><?= e($event['actor_name'] ?? 'System') ?></span>
                                · <span><?= e(time_ago((string) $event['created_at'])) ?></span>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<!-- Revenue chart + module status + system health -->
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <div class="card xl:col-span-2 mc-slide-up">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold">Revenue — last 12 months</h2>
                <p class="text-xs text-slate-400">Collections by month (<?= e((string) setting('currency', 'USD')) ?>)</p>
            </div>
            <?php if ($charts['revenue']['available']): ?>
                <span class="badge badge-emerald"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Live</span>
            <?php endif; ?>
        </div>
        <div class="p-5">
            <?php if ($charts['revenue']['available']): ?>
                <canvas id="revenueChart" height="132"
                        data-labels="<?= e(json_encode($charts['revenue']['labels'])) ?>"
                        data-values="<?= e(json_encode($charts['revenue']['data'])) ?>"></canvas>
            <?php else: ?>
                <?= $this->insert('components/empty-state', [
                    'icon'    => 'chart-column',
                    'title'   => 'Revenue analytics will appear here',
                    'message' => 'The billing & payments module is not installed yet. Real monthly collections will chart here the moment payments are recorded.',
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Module installation status -->
    <div class="card mc-slide-up" style="animation-delay:120ms">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Module status</h2>
            <p class="text-xs text-slate-400">Detected live from the database schema</p>
        </div>
        <ul class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
            <?php foreach ($modules as $module): ?>
                <li class="flex items-center justify-between py-2.5">
                    <span class="flex items-center gap-2.5 text-[13px] text-slate-600 dark:text-slate-300">
                        <span class="relative flex h-1.5 w-1.5">
                            <?php if ($module['ready']): ?>
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            <?php else: ?>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            <?php endif; ?>
                        </span>
                        <?= e($module['name']) ?>
                    </span>
                    <span class="badge <?= $module['ready'] ? 'badge-emerald' : 'badge-slate' ?>"><?= $module['ready'] ? 'Installed' : 'Planned' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- System health -->
<div class="mt-4 card mc-slide-up">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div>
            <h2 class="text-sm font-semibold">System health</h2>
            <p class="text-xs text-slate-400">Live diagnostics — no cached values</p>
        </div>
        <span class="badge <?= $system['db_ok'] && $system['storage_logs'] ? 'badge-emerald' : 'badge-rose' ?>">
            <span class="h-1.5 w-1.5 rounded-full <?= $system['db_ok'] && $system['storage_logs'] ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
            <?= $system['db_ok'] && $system['storage_logs'] ? 'All systems operational' : 'Attention required' ?>
        </span>
    </div>

    <div class="grid grid-cols-2 gap-px overflow-hidden bg-slate-100 sm:grid-cols-3 lg:grid-cols-6 dark:bg-slate-800">
        <?php
        $checks = [
            ['label' => 'Database',    'ok' => $system['db_ok'],    'value' => $system['db_ok'] ? $system['db_latency_ms'] . ' ms' : 'down'],
            ['label' => 'PHP',         'ok' => true,                'value' => $system['php_version']],
            ['label' => 'Environment', 'ok' => $system['environment'] !== 'production', 'value' => ucfirst($system['environment'])],
            ['label' => 'Active 24h',  'ok' => true,                'value' => (int) $system['active_users'] . ' users'],
            ['label' => 'Logs dir',    'ok' => $system['storage_logs'], 'value' => $system['storage_logs'] ? 'writable' : 'locked'],
            ['label' => 'Uploads dir', 'ok' => $system['uploads'],  'value' => $system['uploads'] ? 'writable' : 'locked'],
        ];
        ?>
        <?php foreach ($checks as $check): ?>
            <div class="bg-white px-5 py-4 dark:bg-slate-900">
                <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                    <span class="relative flex h-1.5 w-1.5">
                        <?php if ($check['ok']): ?>
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <?php else: ?>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <?php endif; ?>
                    </span>
                    <?= e($check['label']) ?>
                </p>
                <p class="mt-1 truncate text-[15px] font-semibold text-slate-800 dark:text-slate-100"><?= e((string) $check['value']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($charts['appointments']['available'] || $charts['revenue']['available']): ?>
<script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
<?php endif; ?>
<?php $this->end(); ?>
