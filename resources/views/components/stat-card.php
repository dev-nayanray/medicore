<?php

declare(strict_types=1);

/**
 * Stat tile. Shows the REAL queried number, or an honest "module pending"
 * state when the backing module does not exist yet.
 *
 * Expected: $key, $label, $icon, $stat (from DashboardService), optional $tone
 */

$stat = $stat ?? ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
$icon = $icon ?? 'activity';
$tone = $tone ?? 'teal';

$toneMap = [
    'teal'  => 'bg-teal-500/10 text-teal-600 dark:text-teal-400',
    'navy'  => 'bg-navy-500/10 text-navy-600 dark:text-navy-300',
    'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'rose'  => 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    'violet'=> 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
];
?>
<div class="card p-5">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[12px] font-medium uppercase tracking-wide text-slate-400"><?= e($label) ?></p>

            <?php if ($stat['available']): ?>
                <p class="mt-2 truncate text-[28px] font-bold leading-none tracking-tight text-slate-900 dark:text-white">
                    <?= $stat['suffix'] === 'money' ? e(format_money($stat['value'])) : e(number_format((float) $stat['value'])) ?>
                </p>
                <?php if ($stat['secondary'] !== ''): ?>
                    <p class="mt-2 truncate text-[12.5px] text-slate-400"><?= e($stat['secondary']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="mt-2.5 text-[15px] font-semibold text-slate-400 dark:text-slate-500">Module pending</p>
                <p class="mt-1 text-[12.5px] text-slate-400">No records — module not installed</p>
            <?php endif; ?>
        </div>

        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl <?= $toneMap[$tone] ?? $toneMap['teal'] ?> <?= $stat['available'] ? '' : 'opacity-40' ?>">
            <i data-lucide="<?= e($icon) ?>" class="h-5 w-5"></i>
        </span>
    </div>

    <?php if (!$stat['available']): ?>
        <p class="mt-4 border-t border-dashed border-slate-200 pt-3 text-[11.5px] leading-relaxed text-slate-400 dark:border-slate-700 dark:text-slate-500">
            Lights up automatically once the <span class="font-medium"><?= e($label) ?></span> module tables are migrated.
        </p>
    <?php endif; ?>
</div>
