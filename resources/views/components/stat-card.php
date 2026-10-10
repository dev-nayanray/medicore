<?php

declare(strict_types=1);

/**
 * Stat tile — premium pastel variant matching MediCore dashboard reference.
 * Shows the REAL queried number, or an honest "module pending"
 * state when the backing module does not exist yet.
 *
 * Expected: $key, $label, $icon, $stat (from DashboardService), optional $tone
 * Each tone maps to a pastel background + accent text + dot indicator.
 */

$stat = $stat ?? ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
$icon = $icon ?? 'activity';
$tone = $tone ?? 'blue';

// Pastel tone map — soft icon container backgrounds with matching accent text
$toneMap = [
    'blue'    => ['bg' => 'from-blue-50 to-blue-100/60',   'text' => 'text-blue-600 dark:text-blue-400',   'dot' => 'bg-blue-500',   'wave' => 'from-blue-200/40'],
    'violet'  => ['bg' => 'from-violet-50 to-violet-100/60','text' => 'text-violet-600 dark:text-violet-400','dot' => 'bg-violet-500', 'wave' => 'from-violet-200/40'],
    'teal'    => ['bg' => 'from-teal-50 to-teal-100/60',   'text' => 'text-teal-600 dark:text-teal-400',   'dot' => 'bg-teal-500',   'wave' => 'from-teal-200/40'],
    'rose'    => ['bg' => 'from-rose-50 to-rose-100/60',   'text' => 'text-rose-600 dark:text-rose-400',   'dot' => 'bg-rose-500',   'wave' => 'from-rose-200/40'],
    'amber'   => ['bg' => 'from-amber-50 to-amber-100/60', 'text' => 'text-amber-600 dark:text-amber-400', 'dot' => 'bg-amber-500',  'wave' => 'from-amber-200/40'],
    'emerald' => ['bg' => 'from-emerald-50 to-emerald-100/60','text' => 'text-emerald-600 dark:text-emerald-400','dot' => 'bg-emerald-500','wave' => 'from-emerald-200/40'],
    'navy'    => ['bg' => 'from-navy-50 to-navy-100/60',   'text' => 'text-navy-600 dark:text-navy-300',    'dot' => 'bg-navy-500',   'wave' => 'from-navy-200/40'],
];
$toneConfig = $toneMap[$tone] ?? $toneMap['blue'];

// Build a directional delta indicator from $stat['secondary'] if it starts with +/-
$secondary = $stat['secondary'] ?? '';
$deltaUp = str_starts_with($secondary, '+');
$deltaDown = str_starts_with($secondary, '-');
$deltaNeutral = !$deltaUp && !$deltaDown;
$deltaArrow = $deltaUp ? '↑' : ($deltaDown ? '↓' : '—');
$deltaTone = $deltaUp ? 'text-emerald-600 dark:text-emerald-400'
    : ($deltaDown ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400 dark:text-slate-500');
?>
<div class="stat-tile group relative overflow-hidden rounded-2xl border border-canvas-200 bg-white p-5 transition-all hover:-translate-y-0.5 hover:shadow-soft dark:border-slate-700 dark:bg-slate-900">
    <!-- Decorative wave at bottom-right -->
    <div class="pointer-events-none absolute -bottom-6 -right-6 h-24 w-24 rounded-full bg-gradient-to-br <?= $toneConfig['wave'] ?> to-transparent opacity-60 transition-transform group-hover:scale-125"></div>

    <div class="relative flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                <span class="h-1.5 w-1.5 rounded-full <?= $toneConfig['dot'] ?>"></span>
                <?= e($label) ?>
            </p>

            <?php if ($stat['available']): ?>
                <p class="mt-2 truncate text-[28px] font-bold leading-none tracking-tight text-slate-900 dark:text-white">
                    <?= $stat['suffix'] === 'money' ? e(format_money($stat['value'])) : e(number_format((float) $stat['value'])) ?>
                </p>
                <?php if ($secondary !== ''): ?>
                    <p class="mt-2.5 flex items-center gap-1 text-[12px] font-medium <?= $deltaTone ?>">
                        <span class="text-[13px]"><?= $deltaArrow ?></span>
                        <span><?= e(ltrim($secondary, '+-')) ?></span>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="mt-2.5 text-[18px] font-bold text-slate-300 dark:text-slate-600">—</p>
                <p class="mt-1 text-[11.5px] text-slate-400 dark:text-slate-500">module pending</p>
            <?php endif; ?>
        </div>

        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br <?= $toneConfig['bg'] ?> <?= $toneConfig['text'] ?> ring-1 ring-current/10 transition-transform group-hover:scale-105 <?= $stat['available'] ? '' : 'opacity-40' ?>">
            <i data-lucide="<?= e($icon) ?>" class="h-5 w-5"></i>
        </span>
    </div>

    <?php if (!$stat['available']): ?>
        <p class="relative mt-4 border-t border-dashed border-slate-200 pt-3 text-[11px] leading-relaxed text-slate-400 dark:border-slate-700 dark:text-slate-500">
            Lights up automatically once the <span class="font-medium"><?= e($label) ?></span> module tables are migrated.
        </p>
    <?php endif; ?>
</div>
