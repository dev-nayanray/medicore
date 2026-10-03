<?php
$this->extend('layouts/marketing');
$title = 'Contact — MediCore';
$errors = $errors ?? [];
$old = $old ?? [];
?>
<?php $this->section('content'); ?>
<section class="pt-20">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="text-center reveal">
            <h1 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Request a Demo</h1>
            <p class="mt-3 text-lg text-slate-600">Fill out the form below and we'll get back to you within 24 hours.</p>
        </div>
        <form method="post" action="<?= url('/demo-request') ?>" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm reveal">
            <?= csrf_field() ?>
            <!-- Honeypot -->
            <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="full_name" class="block text-sm font-medium text-slate-700">Full Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name'] ?? '') ?>" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 <?= isset($errors['full_name']) ? 'border-rose-400' : '' ?>" required>
                    <?php if (isset($errors['full_name'])): ?><p class="mt-1 text-xs text-rose-500"><?= e($errors['full_name']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="organization" class="block text-sm font-medium text-slate-700">Hospital / Organization <span class="text-rose-500">*</span></label>
                    <input type="text" id="organization" name="organization" value="<?= e($old['organization'] ?? '') ?>" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 <?= isset($errors['organization']) ? 'border-rose-400' : '' ?>" required>
                    <?php if (isset($errors['organization'])): ?><p class="mt-1 text-xs text-rose-500"><?= e($errors['organization']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Work Email <span class="text-rose-500">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 <?= isset($errors['email']) ? 'border-rose-400' : '' ?>" required>
                    <?php if (isset($errors['email'])): ?><p class="mt-1 text-xs text-rose-500"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500 <?= isset($errors['phone']) ? 'border-rose-400' : '' ?>">
                    <?php if (isset($errors['phone'])): ?><p class="mt-1 text-xs text-rose-500"><?= e($errors['phone']) ?></p><?php endif; ?>
                </div>
            </div>
            <div>
                <label for="hospital_size" class="block text-sm font-medium text-slate-700">Hospital Size / Staff Range</label>
                <select id="hospital_size" name="hospital_size" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                    <option value="">Select...</option>
                    <option value="1-50" <?= ($old['hospital_size'] ?? '') === '1-50' ? 'selected' : '' ?>>1-50 staff</option>
                    <option value="51-200" <?= ($old['hospital_size'] ?? '') === '51-200' ? 'selected' : '' ?>>51-200 staff</option>
                    <option value="201-500" <?= ($old['hospital_size'] ?? '') === '201-500' ? 'selected' : '' ?>>201-500 staff</option>
                    <option value="500+" <?= ($old['hospital_size'] ?? '') === '500+' ? 'selected' : '' ?>>500+ staff</option>
                </select>
            </div>
            <div>
                <label for="message" class="block text-sm font-medium text-slate-700">Message</label>
                <textarea id="message" name="message" rows="4" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-1 focus:ring-teal-500" placeholder="Tell us about your requirements..."><?= e($old['message'] ?? '') ?></textarea>
                <?php if (isset($errors['message'])): ?><p class="mt-1 text-xs text-rose-500"><?= e($errors['message']) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="w-full rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-teal-600/30 transition-colors hover:bg-teal-700">Submit Demo Request</button>
        </form>
    </div>
</section>
<?php $this->end(); ?>
