<?php

declare(strict_types=1);

/**
 * Stat tile — premium variant.
 * Shows the REAL queried number, or an honest "module pending"
 * state when the backing module does not exist yet.
 *
 * Expected: $key, $label, $icon, $stat (from DashboardService), optional $tone
 */

$stat = $stat ?? ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
$icon = $icon ?? 'activity';
$tone = $tone ?? 'teal';

$toneMap = [
    'teal'   => ['bg' => 'from-teal-500/15 to-teal-500/5',   'text' => 'text-teal-600 dark:text-teal-400',   'accent' => 'rgba(20, 184, 166, .18)', 'dot' => 'bg-teal-500'],
    'navy'   => ['bg' => 'from-navy-500/15 to-navy-500/5',  'text' => 'text-navy-600 dark:text-navy-300',  'accent' => 'rgba(58, 115, 166, .18)', 'dot' => 'bg-navy-500'],
    'amber'  => ['bg' => 'from-amber-500/15 to-amber-500/5', 'text' => 'text-amber-600 dark:text-amber-400', 'accent' => 'rgba(245, 158, 11, .18)', 'dot' => 'bg-amber-500'],
    'rose'   => ['bg' => 'from-rose-500/15 to-rose-500/5',  'text' => 'text-rose-600 dark:text-rose-400',   'accent' => 'rgba(244, 63, 94, .18)',  'dot' => 'bg-rose-500'],
    'violet' => ['bg' => 'from-violet-500/15 to-violet-500/5','text' => 'text-violet-600 dark:text-violet-400','accent' => 'rgba(139, 92, 246, .18)','dot' => 'bg-violet-500'],
    'emerald'=> ['bg' => 'from-emerald-500/15 to-emerald-500/5','text' => 'text-emerald-600 dark:text-emerald-400','accent' => 'rgba(16, 185, 129, .18)','dot' => 'bg-emerald-500'],
];
$toneConfig = $toneMap[$tone] ?? $toneMap['teal'];
?>
<div class="stat-tile card-hover" style="--stat-accent: <?= $toneConfig['accent'] ?>;">
    <div class="relative flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 text-[12px] font-medium uppercase tracking-wide text-slate-400">
                <span class="h-1.5 w-1.5 rounded-full <?= $toneConfig['dot'] ?>"></span>
                <?= e($label) ?>
            </p>

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

        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br <?= $toneConfig['bg'] ?> <?= $toneConfig['text'] ?> <?= $stat['available'] ? '' : 'opacity-40' ?> ring-1 ring-current/10">
            <i data-lucide="<?= e($icon) ?>" class="h-5 w-5"></i>
        </span>
    </div>

    <?php if (!$stat['available']): ?>
        <p class="mt-4 border-t border-dashed border-slate-200 pt-3 text-[11.5px] leading-relaxed text-slate-400 dark:border-slate-700 dark:text-slate-500">
            Lights up automatically once the <span class="font-medium"><?= e($label) ?></span> module tables are migrated.
        </p>
    <?php endif; ?>
</div>
