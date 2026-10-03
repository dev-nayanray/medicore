<?php
$this->extend('layouts/marketing');
$title = 'Pricing — MediCore';
?>
<?php $this->section('content'); ?>
<section class="pt-20">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="text-center reveal">
            <h1 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Pricing</h1>
            <p class="mt-3 text-lg text-slate-600">Choose the plan that fits your hospital. <span class="block text-xs text-slate-400 sm:inline">Example pricing — contact us for a custom quote.</span></p>
        </div>
        <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 reveal">
                <h3 class="text-lg font-bold text-navy-950">Starter</h3>
                <p class="mt-2 text-3xl font-extrabold text-navy-950">৳5,000<span class="text-base font-normal text-slate-400">/mo</span></p>
                <ul class="mt-4 space-y-2">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Up to 50 staff users</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Patient & appointment management</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Basic billing & invoicing</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Pharmacy essentials</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Email support</li>
                </ul>
                <a href="<?= url('/contact') ?>" class="mt-6 block rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition-colors hover:border-teal-400 hover:text-teal-600">Request a Quote</a>
            </div>
            <div class="rounded-2xl border-2 border-teal-400 bg-white p-6 reveal lg:-translate-y-4 shadow-xl">
                <span class="float-right rounded-full bg-teal-500 px-2 py-0.5 text-[10px] font-bold text-white">POPULAR</span>
                <h3 class="text-lg font-bold text-navy-950">Professional</h3>
                <p class="mt-2 text-3xl font-extrabold text-navy-950">৳15,000<span class="text-base font-normal text-slate-400">/mo</span></p>
                <ul class="mt-4 space-y-2">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Up to 200 staff users</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>All 12 modules</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Lab result verification workflow</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Bed management & admissions</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Priority support</li>
                </ul>
                <a href="<?= url('/contact') ?>" class="mt-6 block rounded-xl bg-teal-600 px-4 py-2.5 text-center text-sm font-semibold text-white transition-colors hover:bg-teal-700">Request a Quote</a>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 reveal">
                <h3 class="text-lg font-bold text-navy-950">Enterprise</h3>
                <p class="mt-2 text-3xl font-extrabold text-navy-950">Custom</p>
                <ul class="mt-4 space-y-2">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Unlimited users</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>All Professional features</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Custom module development</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>On-premise deployment</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Dedicated account manager</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>24/7 support</li>
                </ul>
                <a href="<?= url('/contact') ?>" class="mt-6 block rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition-colors hover:border-teal-400 hover:text-teal-600">Contact Sales</a>
            </div>
        </div>
    </div>
</section>
<?php $this->end(); ?>
