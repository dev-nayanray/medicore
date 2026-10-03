<?php

declare(strict_types=1);

/**
 * Audit log browser. $logs, $total, $page, $pages, $perPage, $search, $event, $events
 */

$this->extend('layouts/admin');
$title = 'Audit Logs';
$active = 'audit';
$breadcrumbs = ['Administration' => null, 'Audit Logs' => ''];

$eventTones = [
    'login.success'   => 'emerald',
    'login.failed'    => 'rose',
    'logout'          => 'slate',
    'settings.updated'=> 'amber',
    'system.seeded'   => 'navy',
    'role.created'    => 'teal',
    'profile.updated' => 'teal',
    'password.changed'=> 'amber',
];
?>
<?php $this->section('content'); ?>

<div class="mb-6">
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Audit Logs</h1>
    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
        Append-only trail · <?= (int) $total ?> record<?= (int) $total === 1 ? '' : 's' ?> match the current filter
    </p>
</div>

<div class="card">
    <!-- Filter toolbar -->
    <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800 lg:flex-row lg:items-center">
        <form method="get" action="<?= url('/admin/audit-logs') ?>" class="flex flex-wrap items-center gap-2">
            <div class="relative w-full max-w-xs">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search description, actor…" class="input pl-9 text-sm">
            </div>
            <select name="event" class="input w-auto text-sm">
                <option value="">All events</option>
                <?php foreach ($events as $evt): ?>
                    <option value="<?= e($evt) ?>" <?= $event === $evt ? 'selected' : '' ?>><?= e($evt) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($search !== '' || $event !== ''): ?>
                <a href="<?= url('/admin/audit-logs') ?>" class="text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($logs === []): ?>
        <div class="p-6">
            <?= $this->insert('components/empty-state', [
                'icon'    => 'scroll-text',
                'title'   => 'No audit records match',
                'message' => 'Adjust the search or event filter. New sign-ins, settings changes and administrative actions are recorded automatically.',
            ]) ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Event</th>
                    <th>Actor</th>
                    <th>Description</th>
                    <th>IP</th>
                    <th>When</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php $tone = $eventTones[(string) $log['event']] ?? 'slate'; ?>
                    <tr>
                        <td><span class="badge badge-<?= e($tone) ?> font-mono text-[11px]"><?= e($log['event']) ?></span></td>
                        <td>
                            <?php if ($log['actor_name'] !== null): ?>
                                <div class="flex items-center gap-2">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-navy-900/90 text-[10px] font-semibold text-white"><?= e(initials((string) $log['actor_name'])) ?></span>
                                    <span class="text-[13px] text-slate-600 dark:text-slate-300"><?= e($log['actor_name']) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 text-[13px] text-slate-400"><i data-lucide="server" class="h-3.5 w-3.5"></i>System</span>
                            <?php endif; ?>
                        </td>
                        <td class="max-w-md text-[13px] text-slate-600 dark:text-slate-300"><?= e($log['description'] ?? '—') ?></td>
                        <td class="whitespace-nowrap font-mono text-xs text-slate-400"><?= e($log['ip_address'] ?? '—') ?></td>
                        <td class="whitespace-nowrap text-[13px] text-slate-500 dark:text-slate-400" title="<?= e(format_date($log['created_at'])) ?>"><?= e(time_ago((string) $log['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= $this->insert('components/pagination', [
            'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage,
            'baseUrl' => url('/admin/audit-logs') . ($search !== '' || $event !== '' ? '?' . http_build_query(array_filter(['q' => $search, 'event' => $event])) . '&' : '?'),
        ]) ?>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
