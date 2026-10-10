<?php

declare(strict_types=1);

/**
 * Dashboard — every number is a real query result (DashboardService).
 * Layout matches the MediCore reference image:
 *   1. Welcome hero (blue gradient + stethoscope overlay) + 4 quick-action buttons
 *   2. 6 KPI stat tiles (pastel backgrounds, trend arrows)
 *   3. Appointments Overview chart (blue line + gradient fill) | Recent Activity timeline
 *   4. Upcoming Appointments table | Operational Alerts panel
 *   5. Revenue chart (dark navy card with blue-violet bars) | System Health 2×3 grid | Promo CTA card
 *
 * $stats, $charts, $recentActivity, $modules, $system,
 * $upcomingAppointments, $operationalAlerts provided by the controller.
 */

$this->extend('layouts/admin');
$title = 'Dashboard';
$active = 'dashboard';
$breadcrumbs = [];

$user = auth_user();
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$emoji = $hour < 12 ? '☀️' : ($hour < 17 ? '☀️' : '🌙');

// Status badge colour map for upcoming-appointments table
$statusBadgeMap = [
    'scheduled'        => ['label' => 'Scheduled', 'cls' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'],
    'confirmed'        => ['label' => 'Confirmed', 'cls' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'],
    'checked_in'       => ['label' => 'Checked in', 'cls' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'],
    'in_consultation'  => ['label' => 'In consultation', 'cls' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300'],
    'completed'        => ['label' => 'Completed', 'cls' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
    'cancelled'        => ['label' => 'Cancelled', 'cls' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'],
    'no_show'          => ['label' => 'No-show', 'cls' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'],
];

// Tone map for operational-alerts panel
$alertToneMap = [
    'amber'   => 'text-amber-600 dark:text-amber-400',
    'rose'    => 'text-rose-600 dark:text-rose-400',
    'violet'  => 'text-violet-600 dark:text-violet-400',
    'navy'    => 'text-navy-600 dark:text-navy-300',
    'slate'   => 'text-slate-400 dark:text-slate-500',
];
$alertBgMap = [
    'amber'   => 'bg-amber-50 dark:bg-amber-950/30',
    'rose'    => 'bg-rose-50 dark:bg-rose-950/30',
    'violet'  => 'bg-violet-50 dark:bg-violet-950/30',
    'navy'    => 'bg-navy-50 dark:bg-navy-950/30',
    'slate'   => 'bg-slate-50 dark:bg-slate-800/50',
];
?>

<?php $this->section('content'); ?>

<!-- ============================================================ -->
<!-- 1. WELCOME HERO with stethoscope overlay + quick actions      -->
<!-- ============================================================ -->
<section class="relative mb-6 overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-brand-100/60 dark:border-slate-700 dark:from-slate-900 dark:via-slate-900 dark:to-slate-800">
    <!-- Decorative blob (stethoscope image substitute — uses inline SVG pattern for resilience) -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute right-0 top-0 h-full w-1/3 bg-gradient-to-l from-brand-200/40 via-brand-100/20 to-transparent dark:from-brand-700/20 dark:via-brand-900/10"></div>
        <!-- Stethoscope-style decorative SVG (subtle, medical-feel) -->
        <svg class="absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-300/50 dark:text-brand-400/20 lg:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
            <path d="M6 3v6a4 4 0 0 0 8 0V3"/>
            <path d="M6 3H4v6a6 6 0 0 0 12 0V3h-2"/>
            <path d="M10 13v3a4 4 0 0 0 8 0v-1"/>
            <circle cx="18" cy="11" r="2"/>
        </svg>
        <!-- Cursive tagline (script-style) -->
        <p class="absolute right-8 top-1/2 hidden -translate-y-1/2 text-center text-sm font-serif italic text-brand-700/40 dark:text-brand-300/30 lg:block" style="font-family: 'Brush Script MT', cursive;">
            Better Care,<br>Healthier Tomorrow
        </p>
    </div>

    <div class="relative flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-brand-700 dark:text-brand-300">
                <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                <?= e(date('l, F j, Y')) ?>
            </p>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                <?= e($greeting) ?>, <?= e(explode(' ', (string) ($user['name'] ?? ''))[0]) ?> <span class="text-brand-500"><?= $emoji ?></span>
            </h1>
            <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400">
                Here's what's happening at <?= e(setting('hospital_name', 'your hospital')) ?> today.
            </p>
        </div>

        <!-- 4 quick-action buttons -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center lg:flex-col lg:items-stretch lg:gap-2">
            <div class="flex flex-wrap gap-2">
                <?php if (can('appointments.create')): ?>
                <a href="<?= url('/admin/appointments/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-glow-brand transition-all hover:-translate-y-0.5 hover:shadow-lg">
                    <i data-lucide="calendar-plus" class="h-4 w-4"></i>
                    New Appointment
                </a>
                <?php endif; ?>
                <?php if (can('patients.create')): ?>
                <a href="<?= url('/admin/patients/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-emerald-500/30 transition-all hover:-translate-y-0.5">
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                    Register Patient
                </a>
                <?php endif; ?>
                <?php if (can('doctors.create')): ?>
                <a href="<?= url('/admin/doctors/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-violet-500 to-violet-700 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-violet-500/30 transition-all hover:-translate-y-0.5">
                    <i data-lucide="stethoscope" class="h-4 w-4"></i>
                    Add Doctor
                </a>
                <?php endif; ?>
            </div>
            <?php if (can('billing.create')): ?>
            <a href="<?= url('/admin/billing/create') ?>" class="inline-flex items-center justify-between gap-2 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-amber-500/30 transition-all hover:-translate-y-0.5">
                <span class="flex items-center gap-2">
                    <i data-lucide="receipt-text" class="h-4 w-4"></i>
                    New Invoice
                </span>
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- 2. KPI STAT TILES — 6 cards with pastel backgrounds            -->
<!-- ============================================================ -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    <div class="mc-fade-in" style="animation-delay:0ms">
        <?= $this->insert('components/stat-card', ['key' => 'patients', 'label' => 'Registered Patients', 'icon' => 'users', 'stat' => $stats['patients'], 'tone' => 'blue']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:60ms">
        <?= $this->insert('components/stat-card', ['key' => 'appointments', 'label' => "Today's Appointments", 'icon' => 'calendar-days', 'stat' => $stats['appointments'], 'tone' => 'violet']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:120ms">
        <?= $this->insert('components/stat-card', ['key' => 'staff', 'label' => 'Doctors & Staff', 'icon' => 'stethoscope', 'stat' => $stats['staff'], 'tone' => 'teal']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:180ms">
        <?= $this->insert('components/stat-card', ['key' => 'revenue', 'label' => "Today's Revenue", 'icon' => 'banknote', 'stat' => $stats['revenue'], 'tone' => 'rose']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:240ms">
        <?= $this->insert('components/stat-card', ['key' => 'pending', 'label' => 'Pending Payments', 'icon' => 'receipt-text', 'stat' => $stats['pending_bills'], 'tone' => 'amber']) ?>
    </div>
    <div class="mc-fade-in" style="animation-delay:300ms">
        <?= $this->insert('components/stat-card', ['key' => 'beds', 'label' => 'Available Beds', 'icon' => 'bed-double', 'stat' => $stats['beds'], 'tone' => 'emerald']) ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- 3. APPOINTMENTS OVERVIEW CHART  |  RECENT ACTIVITY             -->
<!-- ============================================================ -->
<div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <!-- Appointments chart card (2/3 width) -->
    <div class="card xl:col-span-2 mc-slide-up">
        <div class="flex items-center justify-between border-b border-canvas-200 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Appointments Overview</h2>
                <p class="text-xs text-slate-400">Daily volume across all departments</p>
            </div>
            <div class="flex items-center gap-2">
                <select class="input !w-auto !py-1 text-xs" onchange="window.location.href='<?= url('/') ?>?range='+this.value">
                    <option value="7" selected>Last 7 days</option>
                    <option value="14">Last 14 days</option>
                </select>
                <?php if ($charts['appointments']['available']): ?>
                    <span class="badge badge-emerald"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Live</span>
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

    <!-- Recent activity (1/3 width) -->
    <div class="card mc-slide-up" style="animation-delay:80ms">
        <div class="flex items-center justify-between border-b border-canvas-200 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Recent Activity</h2>
                <p class="text-xs text-slate-400">Latest updates from the audit trail</p>
            </div>
            <?php if (can('audit.view')): ?>
                <a href="<?= url('/admin/audit-logs') ?>" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">View all</a>
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
                        'navy'  => 'bg-brand-500/10 text-brand-600 dark:text-brand-300',
                    ][$tone];
                    $icon = $isFail ? 'shield-alert' : ($isLogin ? 'log-in' : (str_starts_with((string) $event['event'], 'settings') ? 'settings' : 'server'));
                    ?>
                    <li class="flex items-start gap-3 px-5 py-3.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg <?= $toneClass ?> ring-1 ring-current/10">
                            <i data-lucide="<?= e($icon) ?>" class="h-4 w-4"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] leading-snug text-slate-700 dark:text-slate-200">
                                <?= e($event['description'] ?? $event['event']) ?>
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                <span class="font-medium text-slate-500 dark:text-slate-400"><?= e($event['actor_name'] ?? 'System') ?></span>
                                · <?= e(time_ago((string) $event['created_at'])) ?>
                            </p>
                        </div>
                        <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 dark:text-slate-600"></i>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- 4. UPCOMING APPOINTMENTS  |  OPERATIONAL ALERTS               -->
<!-- ============================================================ -->
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">

    <!-- Upcoming appointments table -->
    <div class="card mc-slide-up">
        <div class="flex items-center justify-between border-b border-canvas-200 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Upcoming Appointments</h2>
                <p class="text-xs text-slate-400">Today's schedule</p>
            </div>
            <?php if (can('appointments.view')): ?>
                <a href="<?= url('/admin/appointments') ?>" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">View calendar</a>
            <?php endif; ?>
        </div>

        <?php if ($upcomingAppointments === []): ?>
            <div class="p-5">
                <?= $this->insert('components/empty-state', [
                    'icon'    => 'calendar-x',
                    'compact' => true,
                    'title'   => 'No upcoming appointments today',
                    'message' => 'Appointments scheduled for today will appear here as they are booked.',
                ]) ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto scrollbar-thin">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Dept</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAppointments as $apt): ?>
                            <?php
                            $pFirst = (string) ($apt['p_first'] ?? '');
                            $pLast  = (string) ($apt['p_last'] ?? '');
                            $pInitials = strtoupper(substr($pFirst, 0, 1) . substr($pLast, 0, 1));
                            $gender = (string) ($apt['gender'] ?? 'male');
                            $avatarBg = $gender === 'female'
                                ? 'bg-gradient-to-br from-rose-400 to-rose-600'
                                : 'bg-gradient-to-br from-brand-500 to-brand-700';
                            $status = (string) ($apt['status'] ?? 'scheduled');
                            $badge = $statusBadgeMap[$status] ?? $statusBadgeMap['scheduled'];
                            ?>
                            <tr class="transition-colors">
                                <td class="whitespace-nowrap text-[12.5px] font-semibold text-slate-700 dark:text-slate-200">
                                    <?= e(date('h:i A', strtotime((string) $apt['start_time']))) ?>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[10px] font-semibold text-white ring-2 ring-white dark:ring-slate-900 <?= $avatarBg ?>">
                                            <?= e($pInitials ?: '?') ?>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-[12.5px] font-medium text-slate-700 dark:text-slate-200"><?= e(trim($pFirst . ' ' . $pLast)) ?: '—' ?></p>
                                            <p class="truncate text-[10.5px] text-slate-400"><?= e($apt['patient_code'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap text-[12.5px] text-slate-600 dark:text-slate-300">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i data-lucide="stethoscope" class="h-3 w-3 text-slate-400"></i>
                                        <?= e($apt['doctor_name'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap text-[12px] text-slate-500 dark:text-slate-400">
                                    <?= e($apt['department_name'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $badge['cls'] ?>">
                                        <?= e($badge['label']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Operational alerts -->
    <div class="card mc-slide-up" style="animation-delay:80ms">
        <div class="flex items-center justify-between border-b border-canvas-200 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Operational Alerts</h2>
                <p class="text-xs text-slate-400">Live conditions requiring attention</p>
            </div>
            <span class="badge badge-slate"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> monitoring</span>
        </div>

        <?php if ($operationalAlerts === []): ?>
            <div class="p-5">
                <?= $this->insert('components/empty-state', [
                    'icon'    => 'shield-check',
                    'compact' => true,
                    'title'   => 'No operational alerts configured',
                    'message' => 'Alerts will appear here as the underlying modules are migrated.',
                ]) ?>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
                <?php foreach ($operationalAlerts as $key => $alert):
                    $tone = $alert['tone'] ?? 'slate';
                    $alertToneClass = $alertToneMap[$tone] ?? $alertToneMap['slate'];
                    $alertBgClass = $alertBgMap[$tone] ?? $alertBgMap['slate'];
                ?>
                    <li>
                        <a href="<?= e($alert['href'] ?? '#') ?>" class="flex items-center gap-3 py-3.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40 -mx-2 px-2 rounded-lg">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg <?= $alertBgClass ?> <?= $alertToneClass ?> ring-1 ring-current/10">
                                <i data-lucide="<?= e($alert['icon']) ?>" class="h-[18px] w-[18px]"></i>
                            </span>
                            <span class="flex-1 text-[13px] font-medium text-slate-700 dark:text-slate-200">
                                <?= e($alert['label']) ?>
                            </span>
                            <span class="text-[20px] font-bold <?= $alertToneClass ?>">
                                <?= (int) $alert['count'] ?>
                            </span>
                            <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 dark:text-slate-600"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- 5. REVENUE (dark) | SYSTEM HEALTH | PROMO CTA                  -->
<!-- ============================================================ -->
<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">

    <!-- Revenue chart — DARK NAVY CARD (matches reference) -->
    <div class="relative overflow-hidden rounded-2xl border border-navy-900 bg-gradient-to-br from-navy-950 via-navy-900 to-navy-800 p-5 shadow-pop lg:col-span-2 mc-slide-up">
        <!-- decorative blobs -->
        <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-10 h-64 w-64 rounded-full bg-violet-500/15 blur-3xl"></div>

        <div class="relative flex items-center justify-between mb-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Revenue — last 12 months</p>
                <p class="mt-2 text-3xl font-bold text-white">
                    <?php
                    $totalRevenue = 0;
                    if (!empty($charts['revenue']['data'])) {
                        $totalRevenue = array_sum($charts['revenue']['data']);
                    }
                    echo e(setting('currency', 'BDT') === 'BDT' ? '৳' : '') . number_format((float) $totalRevenue, 2);
                    ?>
                </p>
                <?php if ($totalRevenue > 0): ?>
                    <p class="mt-1 flex items-center gap-1.5 text-xs font-medium text-emerald-400">
                        <i data-lucide="trending-up" class="h-3 w-3"></i>
                        Collected this year
                    </p>
                <?php else: ?>
                    <p class="mt-1 text-xs text-slate-400">No payments collected yet</p>
                <?php endif; ?>
            </div>
            <select class="rounded-lg border border-slate-700 bg-slate-800/50 px-3 py-1.5 text-xs font-medium text-slate-200 backdrop-blur-sm">
                <option>12 months</option>
                <option>6 months</option>
            </select>
        </div>

        <div class="relative">
            <?php if ($charts['revenue']['available']): ?>
                <canvas id="revenueChart" height="160"
                        data-labels="<?= e(json_encode($charts['revenue']['labels'])) ?>"
                        data-values="<?= e(json_encode($charts['revenue']['data'])) ?>"></canvas>
            <?php else: ?>
                <div class="py-12 text-center">
                    <i data-lucide="chart-column" class="mx-auto h-12 w-12 text-slate-600"></i>
                    <p class="mt-3 text-sm font-medium text-slate-300">Revenue analytics will appear here</p>
                    <p class="mt-1 text-xs text-slate-500">The billing &amp; payments module is not installed yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Health -->
    <div class="card mc-slide-up" style="animation-delay:120ms">
        <div class="flex items-center justify-between border-b border-canvas-200 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">System Health</h2>
                <p class="text-xs text-slate-400">Live diagnostics — no cached values</p>
            </div>
            <span class="badge <?= $system['db_ok'] && $system['storage_logs'] ? 'badge-emerald' : 'badge-rose' ?>">
                <span class="h-1.5 w-1.5 rounded-full <?= $system['db_ok'] && $system['storage_logs'] ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                <?= $system['db_ok'] && $system['storage_logs'] ? 'All systems operational' : 'Attention required' ?>
            </span>
        </div>

        <div class="grid grid-cols-2 gap-px overflow-hidden bg-canvas-200 dark:bg-slate-800">
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
                <div class="bg-white px-4 py-3.5 dark:bg-slate-900">
                    <p class="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">
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
                    <p class="mt-1 truncate text-[14px] font-bold text-slate-800 dark:text-slate-100"><?= e((string) $check['value']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- 6. PROMO CTA + MODULE STATUS                                    -->
<!-- ============================================================ -->
<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">

    <!-- Promo CTA card (lavender gradient) -->
    <div class="relative overflow-hidden rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 via-white to-brand-50 p-6 dark:border-violet-900/40 dark:from-violet-950/30 dark:via-slate-900 dark:to-brand-950/30 lg:col-span-1 mc-slide-up">
        <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-gradient-to-br from-violet-300/40 to-brand-300/40 blur-2xl"></div>
        <div class="relative">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                <i data-lucide="sparkles" class="h-3 w-3"></i>
                Smarter Hospital Management
            </span>
            <h3 class="mt-3 text-base font-bold text-slate-900 dark:text-white">
                Streamline every workflow<br>from registration to discharge.
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                Explore MediCore's twelve integrated modules — built on raw PHP, secured with role-based access control, and audited at every step.
            </p>
            <a href="<?= url('/admin/reports') ?>" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 px-4 py-2 text-xs font-semibold text-white shadow-glow-brand transition-all hover:-translate-y-0.5">
                Explore Features
                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
            </a>
        </div>
    </div>

    <!-- Module status (occupies 2/3) -->
    <div class="card lg:col-span-2 mc-slide-up" style="animation-delay:80ms">
        <div class="border-b border-canvas-200 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Module Status</h2>
            <p class="text-xs text-slate-400">Detected live from the database schema</p>
        </div>
        <div class="grid grid-cols-2 gap-px overflow-hidden bg-canvas-200 sm:grid-cols-3 dark:bg-slate-800">
            <?php foreach ($modules as $module): ?>
                <div class="bg-white px-4 py-2.5 dark:bg-slate-900">
                    <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-600 dark:text-slate-300">
                        <span class="relative flex h-1.5 w-1.5">
                            <?php if ($module['ready']): ?>
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            <?php else: ?>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            <?php endif; ?>
                        </span>
                        <span class="truncate"><?= e($module['name']) ?></span>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($charts['appointments']['available'] || $charts['revenue']['available']): ?>
<script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
<?php endif; ?>
<?php $this->end(); ?>
