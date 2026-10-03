<?php

declare(strict_types=1);

/**
 * Honest empty state — used when a module/feature has no real data yet.
 * Expected: $title, $message, $icon, optional $compact
 */

$icon = $icon ?? 'inbox';
$compact = $compact ?? false;
?>
<div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-6 text-center dark:border-slate-700 dark:bg-slate-800/40 <?= $compact ? 'py-8' : 'py-14' ?>">
    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
        <i data-lucide="<?= e($icon) ?>" class="h-6 w-6"></i>
    </span>
    <p class="mt-4 text-sm font-semibold text-slate-600 dark:text-slate-300"><?= e($title) ?></p>
    <p class="mt-1 max-w-sm text-[13px] leading-relaxed text-slate-400"><?= e($message) ?></p>
</div>
