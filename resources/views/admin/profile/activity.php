<?php

declare(strict_types=1);

/**
 * My activity — login history + security events for the signed-in account.
 * $user, $attempts, $events, $stats
 */

$this->extend('layouts/admin');
$title = 'My Activity';
$active = 'profile';
$breadcrumbs = ['Administration' => null, 'My Profile' => url('/admin/profile'), 'Activity' => ''];
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Account Activity</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Sign-in history and security events for <?= e($user['email']) ?>.</p>
    </div>
    <a href="<?= url('/admin/profile') ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to profile
    </a>
</div>

<!-- Summary tiles -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card p-5">
        <p class="text-[12px] font-medium uppercase tracking-wide text-slate-400">Successful sign-ins</p>
        <p class="mt-2 text-[28px] font-bold leading-none text-emerald-600 dark:text-emerald-400"><?= (int) $stats['success'] ?></p>
    </div>
    <div class="card p-5">
        <p class="text-[12px] font-medium uppercase tracking-wide text-slate-400">Failed attempts</p>
        <p class="mt-2 text-[28px] font-bold leading-none text-rose-600 dark:text-rose-400"><?= (int) $stats['failed'] ?></p>
    </div>
    <div class="card p-5">
        <p class="text-[12px] font-medium uppercase tracking-wide text-slate-400">Password last changed</p>
        <p class="mt-2 truncate text-[17px] font-bold leading-none text-slate-800 dark:text-slate-100"><?= e(format_date($stats['last_pw'], 'M j, Y g:i A')) ?></p>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <!-- Login attempts -->
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Login history</h2>
            <p class="text-xs text-slate-400">Every authentication attempt on this account</p>
        </div>
        <?php if ($attempts === []): ?>
            <div class="p-5">
                <?= $this->insert('components/empty-state', [
                    'icon' => 'door-open', 'compact' => true,
                    'title' => 'No sign-in history yet',
                    'message' => 'Attempts appear here as they happen.',
                ]) ?>
            </div>
        <?php else: ?>
            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto scrollbar-thin dark:divide-slate-800">
                <?php foreach ($attempts as $a): ?>
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg <?= (int) $a['successful'] === 1 ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600' ?>">
                            <i data-lucide="<?= (int) $a['successful'] === 1 ? 'log-in' : 'shield-alert' ?>" class="h-4 w-4"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                <?= (int) $a['successful'] === 1 ? 'Signed in' : 'Failed (' . e(str_replace('_', ' ', (string) ($a['failure_reason'] ?? 'unknown'))) . ')' ?>
                            </p>
                            <p class="font-mono text-[11px] text-slate-400"><?= e($a['ip_address'] ?? '') ?> · <?= e(format_date($a['created_at'])) ?></p>
                        </div>
                        <span class="shrink-0 text-[11px] text-slate-400"><?= e(time_ago((string) $a['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Security events -->
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold">Security events</h2>
            <p class="text-xs text-slate-400">Audit trail entries for this account</p>
        </div>
        <?php if ($events === []): ?>
            <div class="p-5">
                <?= $this->insert('components/empty-state', [
                    'icon' => 'scroll-text', 'compact' => true,
                    'title' => 'No events yet',
                    'message' => 'Password changes, profile edits and settings actions will appear here.',
                ]) ?>
            </div>
        <?php else: ?>
            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto scrollbar-thin dark:divide-slate-800">
                <?php foreach ($events as $ev): ?>
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300">
                            <i data-lucide="<?= str_starts_with((string) $ev['event'], 'password') ? 'key-round' : (str_starts_with((string) $ev['event'], 'login') ? 'log-in' : 'activity') ?>" class="h-4 w-4"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] text-slate-700 dark:text-slate-200"><?= e($ev['description'] ?? $ev['event']) ?></p>
                            <p class="font-mono text-[11px] text-slate-400"><?= e($ev['event']) ?></p>
                        </div>
                        <span class="shrink-0 text-[11px] text-slate-400"><?= e(time_ago((string) $ev['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php $this->end(); ?>
