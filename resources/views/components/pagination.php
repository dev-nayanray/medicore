<?php

declare(strict_types=1);

/**
 * Table pagination. Expects $page, $pages, $total, $perPage, $baseUrl
 * ($baseUrl already contains the query string prefix, e.g. "/admin/users?q=x&").
 */

if (!isset($pages) || (int) $pages <= 1) {
    return;
}

$page = (int) $page;
$pages = (int) $pages;

// Window of page numbers around the current page.
$window = [];
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
for ($i = $start; $i <= $end; $i++) {
    $window[] = $i;
}

$from = ((int) $total === 0) ? 0 : (($page - 1) * (int) $perPage + 1);
$to = min($page * (int) $perPage, (int) $total);

function page_link(string $baseUrl, int $n): string
{
    return e($baseUrl . 'page=' . $n);
}
?>
<div class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-5 py-3.5 dark:border-slate-800 sm:flex-row">
    <p class="text-xs text-slate-400">
        Showing <span class="font-medium text-slate-600 dark:text-slate-300"><?= $from ?>–<?= $to ?></span>
        of <span class="font-medium text-slate-600 dark:text-slate-300"><?= (int) $total ?></span>
    </p>

    <nav class="flex items-center gap-1" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <a href="<?= page_link($baseUrl, $page - 1) ?>" class="page-btn"><i data-lucide="chevron-left" class="h-4 w-4"></i></a>
        <?php else: ?>
            <span class="page-btn cursor-not-allowed opacity-40"><i data-lucide="chevron-left" class="h-4 w-4"></i></span>
        <?php endif; ?>

        <?php if ($start > 1): ?>
            <a href="<?= page_link($baseUrl, 1) ?>" class="page-btn">1</a>
            <?php if ($start > 2): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
        <?php endif; ?>

        <?php foreach ($window as $n): ?>
            <?php if ($n === $page): ?>
                <span class="page-btn !bg-navy-900 !text-white !border-navy-900 dark:!bg-teal-500 dark:!border-teal-500"><?= $n ?></span>
            <?php else: ?>
                <a href="<?= page_link($baseUrl, $n) ?>" class="page-btn"><?= $n ?></a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($end < $pages): ?>
            <?php if ($end < $pages - 1): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
            <a href="<?= page_link($baseUrl, $pages) ?>" class="page-btn"><?= $pages ?></a>
        <?php endif; ?>

        <?php if ($page < $pages): ?>
            <a href="<?= page_link($baseUrl, $page + 1) ?>" class="page-btn"><i data-lucide="chevron-right" class="h-4 w-4"></i></a>
        <?php else: ?>
            <span class="page-btn cursor-not-allowed opacity-40"><i data-lucide="chevron-right" class="h-4 w-4"></i></span>
        <?php endif; ?>
    </nav>
</div>
