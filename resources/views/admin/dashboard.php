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
?>

<?php $this->section('content'); ?>

<!-- Page header -->
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            Good <?= (int) date('H') < 12 ? 'morning' : ((int) date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', (string) ($user['name'] ?? ''))[0]) ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            <?= e(date('l, F j, Y')) ?> · here's the live pulse of the hospital.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-secondary" @click="soon('New appointment')">
            <i data-lucide="calendar-plus" class="h-4 w-4"></i><span class="hidden sm:inline">New appointment</span>
        </button>
        <?php if (can('patients.create')): ?>
            <a href="<?= url('/admin/patients/create') ?>" class="btn btn-secondary">
                <i data-lucide="user-plus" class="h-4 w-4"></i><span class="hidden sm:inline">Register patient</span>
            </a>
        <?php endif; ?>
        <?php if (can('doctors.view')): ?>
            <a href="<?= url('/admin/doctors') ?>" class="btn btn-secondary">
                <i data-lucide="stethoscope" class="h-4 w-4"></i><span class="hidden sm:inline">Doctors</span>
            </a>
        <?php endif; ?>
        <?php if (can('staff.view')): ?>
            <a href="<?= url('/admin/staff') ?>" class="btn btn-secondary">
                <i data-lucide="id-card" class="h-4 w-4"></i><span class="hidden sm:inline">Staff</span>
            </a>
        <?php endif; ?>
        <?php if (can('patients.view')): ?>
            <a href="<?= url('/admin/patients') ?>" class="btn btn-primary">
                <i data-lucide="users" class="h-4 w-4"></i><span class="hidden sm:inline">Patient directory</span>
            </a>
        <?php elseif (can('departments.view')): ?>
            <a href="<?= url('/admin/departments') ?>" class="btn btn-primary">
                <i data-lucide="building-2" class="h-4 w-4"></i><span class="hidden sm:inline">Departments</span>
            </a>
        <?php else: ?>
            <a href="<?= url('/admin/users') ?>" class="btn btn-primary">
                <i data-lucide="user-round-plus" class="h-4 w-4"></i><span class="hidden sm:inline">Staff directory</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stat tiles -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    <?= $this->insert('components/stat-card', ['key' => 'patients', 'label' => 'Registered Patients', 'icon' => 'users', 'stat' => $stats['patients'], 'tone' => 'teal']) ?>
    <?= $this->insert('components/stat-card', ['key' => 'appointments', 'label' => "Today's Appointments", 'icon' => 'calendar-days', 'stat' => $stats['appointments'], 'tone' => 'navy']) ?>
    <?= $this->insert('components/stat-card', ['key' => 'staff', 'label' => 'Doctors & Staff', 'icon' => 'stethoscope', 'stat' => $stats['staff'], 'tone' => 'violet']) ?>
    <?= $this->insert('components/stat-card', ['key' => 'revenue', 'label' => "Today's Revenue", 'icon' => 'banknote', 'stat' => $stats['revenue'], 'tone' => 'teal']) ?>
    <?= $this->insert('components/stat-card', ['key' => 'pending', 'label' => 'Pending Payments', 'icon' => 'receipt-text', 'stat' => $stats['pending_bills'], 'tone' => 'amber']) ?>
    <?= $this->insert('components/stat-card', ['key' => 'beds', 'label' => 'Available Beds', 'icon' => 'bed-double', 'stat' => $stats['beds'], 'tone' => 'rose']) ?>
</div>

<!-- Charts + activity -->
<div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <div class="card xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold">Appointments — last 14 days</h2>
                <p class="text-xs text-slate-400">Daily volume across all departments</p>
            </div>
            <span class="badge badge-slate">Chart.js</span>
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
    <div class="card">
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
                    ?>
                    <li class="flex gap-3.5 px-5 py-3.5">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg <?= $toneClass ?>">
                            <i data-lucide="<?= $isFail ? 'shield-alert' : ($isLogin ? 'log-in' : (str_starts_with((string) $event['event'], 'settings') ? 'settings' : 'server')) ?>" class="h-4 w-4"></i>
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

    <div class="card xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold">Revenue — last 12 months</h2>
                <p class="text-xs text-slate-400">Collections by month (<?= e((string) setting('currency', 'USD')) ?>)</p>
            </div>
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
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Module status</h2>
            <p class="text-xs text-slate-400">Detected live from the database schema</p>
        </div>
        <ul class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
            <?php foreach ($modules as $module): ?>
                <li class="flex items-center justify-between py-2.5">
                    <span class="flex items-center gap-2.5 text-[13px] text-slate-600 dark:text-slate-300">
                        <span class="h-1.5 w-1.5 rounded-full <?= $module['ready'] ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600' ?>"></span>
                        <?= e($module['name']) ?>
                    </span>
                    <span class="badge <?= $module['ready'] ? 'badge-emerald' : 'badge-slate' ?>"><?= $module['ready'] ? 'Installed' : 'Planned' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- System health -->
<div class="mt-4 card">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div>
            <h2 class="text-sm font-semibold">System health</h2>
            <p class="text-xs text-slate-400">Live diagnostics — no cached values</p>
        </div>
        <span class="badge <?= $system['db_ok'] && $system['storage_logs'] ? 'badge-emerald' : 'badge-rose' ?>">
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
                    <span class="h-1.5 w-1.5 rounded-full <?= $check['ok'] ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
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
