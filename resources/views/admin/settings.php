<?php

declare(strict_types=1);

/**
 * Hospital settings — renders the self-describing settings catalogue.
 * $groups: [group => [setting rows]]
 */

$this->extend('layouts/admin');
$title = 'Hospital Settings';
$active = 'settings';
$breadcrumbs = ['Administration' => null, 'Hospital Settings' => ''];

$groupIcons = [
    'general'      => 'hospital',
    'localization' => 'globe',
    'preferences'  => 'sliders-horizontal',
    'system'       => 'server-cog',
];
?>
<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Hospital Settings</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Rendered from the settings catalogue — new rows in
            <code class="rounded bg-slate-100 px-1 text-xs dark:bg-slate-800">hospital_settings</code>
            appear here automatically.
        </p>
    </div>
    <button type="submit" form="settings-form" class="btn btn-primary">
        <i data-lucide="save" class="h-4 w-4"></i>Save changes
    </button>
</div>

<form method="post" action="<?= url('/admin/settings') ?>" id="settings-form" class="space-y-6">
    <?= csrf_field() ?>

    <?php foreach ($groups as $group => $settings): ?>
        <section class="card">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                    <i data-lucide="<?= $groupIcons[$group] ?? 'settings-2' ?>" class="h-4 w-4"></i>
                </span>
                <h2 class="text-sm font-semibold"><?= e(ucfirst($group)) ?></h2>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($settings as $setting): ?>
                    <?php $key = (string) $setting['key']; ?>
                    <div class="grid grid-cols-1 gap-2 px-5 py-4 sm:grid-cols-3 sm:gap-4">
                        <div>
                            <label for="setting-<?= e($key) ?>" class="text-[13.5px] font-medium text-slate-700 dark:text-slate-200">
                                <?= e($setting['label']) ?>
                            </label>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-400"><?= e($setting['description'] ?? '') ?></p>
                            <p class="mt-1 font-mono text-[10.5px] text-slate-300 dark:text-slate-600"><?= e($key) ?></p>
                        </div>

                        <div class="sm:col-span-2">
                            <?php $current = (string) ($setting['value'] ?? ''); ?>
                            <?php if ((string) $setting['type'] === 'boolean'): ?>
                                <label class="toggle-row">
                                    <input type="hidden" name="settings[<?= e($key) ?>]" value="false">
                                    <input type="checkbox" name="settings[<?= e($key) ?>]" value="true" id="setting-<?= e($key) ?>" class="checkbox" <?= $current === 'true' ? 'checked' : '' ?>>
                                    <span class="toggle-track"><span class="toggle-knob"></span></span>
                                    <span class="text-[13px] text-slate-500 dark:text-slate-400"><?= $current === 'true' ? 'Enabled' : 'Disabled' ?></span>
                                </label>
                            <?php elseif ((string) $setting['type'] === 'text'): ?>
                                <textarea name="settings[<?= e($key) ?>]" id="setting-<?= e($key) ?>" rows="2" class="input"><?= e($current) ?></textarea>
                            <?php elseif ((string) $setting['type'] === 'number'): ?>
                                <input type="number" name="settings[<?= e($key) ?>]" id="setting-<?= e($key) ?>" value="<?= e($current) ?>" class="input max-w-40">
                            <?php else: ?>
                                <input type="text" name="settings[<?= e($key) ?>]" id="setting-<?= e($key) ?>" value="<?= e($current) ?>" class="input sm:max-w-md">
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i>Save changes
        </button>
    </div>
</form>
<?php $this->end(); ?>
