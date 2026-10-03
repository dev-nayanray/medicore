<?php

declare(strict_types=1);

/**
 * Hospital services catalogue — grouped by category with add/edit form.
 * $byCategory, $categories
 */

$this->extend('layouts/admin');
$title = 'Hospital Services';
$active = 'billing';
$breadcrumbs = ['Finance' => null, 'Services' => ''];

$currency = (string) setting('currency', 'BDT');
$categoryLabels = ['consultation' => 'Consultations', 'laboratory' => 'Laboratory', 'procedure' => 'Procedures', 'medicine' => 'Medicines', 'admission' => 'Admissions', 'other' => 'Other'];
$categoryIcons = ['consultation' => 'stethoscope', 'laboratory' => 'flask-conical', 'procedure' => 'syringe', 'medicine' => 'pill', 'admission' => 'bed-double', 'other' => 'package'];
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Hospital Services</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Configurable catalogue of billable services and their pricing.</p>
    </div>
    <?php if (can('billing.update')): ?>
        <button @click="$dispatch('open-service-modal')" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Add service</button>
    <?php endif; ?>
</div>

<?php foreach ($categories as $cat): $services = $byCategory[$cat] ?? []; ?>
    <div class="card mb-4" x-data="{ editId: null }">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="<?= e($categoryIcons[$cat] ?? 'package') ?>" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold"><?= e($categoryLabels[$cat] ?? ucfirst($cat)) ?></h2>
            <span class="badge badge-slate ml-auto"><?= count($services) ?></span>
        </div>
        <?php if ($services === []): ?>
            <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No services in this category.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($services as $s): ?>
                    <div class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-[13.5px] font-medium text-slate-800 dark:text-slate-100 <?= (int) $s['is_active'] === 0 ? 'line-through opacity-50' : '' ?>"><?= e($s['name']) ?></p>
                            <?php if ($s['description']): ?><p class="text-[11.5px] text-slate-400"><?= e($s['description']) ?></p><?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-[14px] font-bold text-teal-600 dark:text-teal-400"><?= e(format_money($s['price'], $currency)) ?></span>
                            <?php if (can('billing.update')): ?>
                                <button type="button" @click="editId = editId === <?= (int) $s['id'] ?> ? null : <?= (int) $s['id'] ?>" class="icon-btn !p-1.5"><i data-lucide="pencil" class="h-3.5 w-3.5"></i></button>
                            <?php endif; ?>
                            <?php if (can('billing.delete') && (int) $s['is_active'] === 1): ?>
                                <form method="post" action="<?= url('/admin/services/' . (int) $s['id'] . '/delete') ?>" data-confirm="Deactivate service|Deactivate <?= e($s['name']) ?>? Existing invoices keep the old price.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn !p-1.5 hover:!text-rose-600"><i data-lucide="archive" class="h-3.5 w-3.5"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Inline edit form -->
                    <div x-show="editId === <?= (int) $s['id'] ?>" x-cloak class="bg-slate-50 px-5 py-4 dark:bg-slate-800/40">
                        <form method="post" action="<?= url('/admin/services/' . (int) $s['id']) ?>" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                            <?= csrf_field() ?>
                            <input type="text" name="name" value="<?= e($s['name']) ?>" class="input sm:col-span-2" required>
                            <select name="category" class="input">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $c === $s['category'] ? 'selected' : '' ?>><?= e($categoryLabels[$c] ?? ucfirst($c)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" step="0.01" min="0" name="price" value="<?= e((string) $s['price']) ?>" class="input" required>
                            <input type="text" name="description" value="<?= e($s['description'] ?? '') ?>" class="input sm:col-span-3" placeholder="Description (optional)">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= (int) $s['is_active'] === 1 ? 'checked' : '' ?> class="checkbox !static"> Active</label>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<!-- Add service modal -->
<div x-data="{ open: false }" @open-service-modal.window="open = true" x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add hospital service</h3>
        <form method="post" action="<?= url('/admin/services') ?>" class="mt-4 space-y-3">
            <?= csrf_field() ?>
            <div><label class="label">Name <span class="text-rose-500">*</span></label><input type="text" name="name" class="input" required></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Category</label>
                    <select name="category" class="input">
                        <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($categoryLabels[$c] ?? ucfirst($c)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="label">Price <?= e($currency) ?> <span class="text-rose-500">*</span></label><input type="number" step="0.01" min="0" name="price" class="input" required></div>
            </div>
            <div><label class="label">Description</label><input type="text" name="description" class="input"></div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="plus" class="h-4 w-4"></i>Add</button>
            </div>
        </form>
    </div>
</div>
<?php $this->end(); ?>
