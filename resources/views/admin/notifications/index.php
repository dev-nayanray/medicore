<?php
declare(strict_types=1);
$this->extend('layouts/admin');
$title = 'Notifications'; $active = '';
$breadcrumbs = ['Notifications' => ''];
$priorityTone = ['low' => 'badge-slate', 'medium' => 'badge-teal', 'high' => 'badge-amber', 'critical' => 'badge-rose'];
$typeIcon = ['appointment_reminder' => 'calendar-clock', 'low_stock' => 'alert-triangle', 'expiry_alert' => 'calendar-x', 'lab_pending' => 'flask-conical', 'admission_alert' => 'door-open', 'announcement' => 'megaphone', 'system' => 'server'];
?>
<?php $this->section('content'); ?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Notifications</h1>
        <p class="mt-0.5 text-sm text-slate-500"><?= (int) $unread ?> unread · <?= count($notifications) ?> total</p></div>
    <?php if ($unread > 0): ?><form method="post" action="<?= url('/admin/notifications/read-all') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary"><i data-lucide="check-check" class="h-4 w-4"></i>Mark all read</button></form><?php endif; ?>
</div>
<div class="card">
    <?php if ($notifications === []): ?><div class="p-6"><?= $this->insert('components/empty-state', ['icon' => 'bell-off', 'title' => 'No notifications', 'message' => 'You are all caught up.']) ?></div>
    <?php else: ?>
    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($notifications as $n): ?>
        <li class="flex items-start gap-3 px-5 py-3.5 <?= (int) $n['is_read'] === 0 ? 'bg-teal-50/30 dark:bg-teal-950/10' : '' ?>">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg <?= (int) $n['is_read'] === 0 ? 'bg-teal-500/10 text-teal-600 dark:text-teal-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?>"><i data-lucide="<?= e($typeIcon[$n['type']] ?? 'bell') ?>" class="h-4 w-4"></i></span>
            <div class="min-w-0 flex-1">
                <p class="flex items-center gap-2 text-[13.5px] font-medium text-slate-800 dark:text-slate-100"><?= e($n['title']) ?> <span class="badge <?= $priorityTone[$n['priority']] ?? 'badge-slate' ?>"><?= e($n['priority']) ?></span><?php if ((int) $n['is_read'] === 0): ?><span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span><?php endif; ?></p>
                <p class="mt-0.5 text-[12.5px] text-slate-500 dark:text-slate-400"><?= e($n['message']) ?></p>
                <p class="mt-0.5 text-[11px] text-slate-400"><?= e(time_ago($n['created_at'])) ?></p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <?php if ($n['action_url']): ?><a href="<?= e($n['action_url']) ?>" class="icon-btn !p-1.5" title="View"><i data-lucide="external-link" class="h-3.5 w-3.5"></i></a><?php endif; ?>
                <?php if ((int) $n['is_read'] === 0): ?><form method="post" action="<?= url('/admin/notifications/' . (int) $n['id'] . '/read') ?>"><?= csrf_field() ?><button type="submit" class="icon-btn !p-1.5" title="Mark read"><i data-lucide="check" class="h-3.5 w-3.5"></i></button></form><?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
