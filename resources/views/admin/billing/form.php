<?php

declare(strict_types=1);

/**
 * Invoice creation form — dynamic line items with Alpine.js,
 * service picker, discount + tax percentage inputs, live total preview.
 * $invoice (null=create), $patient, $services (grouped by category), $taxRate, $prefillConsultation
 */

$this->extend('layouts/admin');
$title = 'New Invoice';
$active = 'billing';
$breadcrumbs = ['Finance' => null, 'Invoices' => url('/admin/billing'), 'New' => ''];

$currency = (string) setting('currency', 'BDT');

// Build a JSON map of services for the Alpine.js picker.
$servicesJson = [];
foreach ($services as $cat => $items) {
    foreach ($items as $s) {
        $servicesJson[(int) $s['id']] = ['name' => $s['name'], 'price' => (float) $s['price'], 'category' => $cat];
    }
}
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">New Invoice</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Select a patient and add billable line items. Totals calculate automatically.</p>
    </div>
    <a href="<?= url('/admin/billing') ?>" class="btn btn-secondary"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</a>
</div>

<form method="post" action="<?= url('/admin/billing') ?>" class="space-y-4" x-data="invoiceForm(<?= e(json_encode($servicesJson, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>, <?= (float) $taxRate ?>)">
    <?= csrf_field() ?>

    <?php if ($patient): ?>
    <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
    <section class="card">
        <div class="flex items-center gap-3 p-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-navy-600/90 text-[11px] font-semibold text-white">
                <?= e(strtoupper(substr((string) $patient['first_name'], 0, 1))) ?>
            </span>
            <div><p class="text-[14px] font-semibold text-slate-800 dark:text-slate-100"><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></p><p class="font-mono text-[11px] text-slate-400"><?= e($patient['patient_code']) ?></p></div>
        </div>
    </section>
    <?php else: ?>
    <section class="card">
        <div class="p-5">
            <label for="patient_search" class="label">Patient <span class="text-rose-500">*</span></label>
            <div class="relative" x-data="{ open: false, results: [], loading: false, timer: null, search: '' }">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="patient_search" autocomplete="off" x-model="search"
                       @input.debounce.350ms="timer && clearTimeout(timer); timer = setTimeout(function(){ if(search.trim().length < 2){ results = []; open = false; return; } loading = true; fetch(MEDICORE_BASE + '/api/search?q=' + encodeURIComponent(search), {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()}).then(function(j){ var g = (j.data && j.data.groups) || []; var p = (g.find(function(x){return x.label==='Patients'}) || {results:[]}).results; results = p; open = true; loading = false; }).catch(function(){ loading = false; }) }, 350)"
                       @blur="setTimeout(function(){ open = false }, 200)" placeholder="Type patient name, code or phone…" class="input pl-9" required>
                <div x-show="open && results.length > 0" x-cloak class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    <template x-for="r in results" :key="r.url">
                        <button type="button" @click="search = r.title; open = false; document.getElementById('patient_id').value = r.url.split('/').pop()" class="block w-full px-3 py-2 text-left text-[13px] hover:bg-slate-50 dark:hover:bg-slate-800" x-text="r.title"></button>
                    </template>
                </div>
            </div>
            <input type="hidden" id="patient_id" name="patient_id" value="" >
        </div>
    </section>
    <?php endif; ?>

    <?php if ($prefillConsultation): ?>
    <input type="hidden" name="consultation_id" value="<?= (int) $prefillConsultation ?>">
    <?php endif; ?>

    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"><i data-lucide="list" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Line items</h2>
        </div>
        <div class="p-5 space-y-2">
            <table class="w-full text-[13px]">
                <thead>
                <tr class="text-left text-[10px] uppercase tracking-wide text-slate-400">
                    <th class="pb-2">Service / description</th>
                    <th class="pb-2 w-20">Qty</th>
                    <th class="pb-2 w-28">Unit price</th>
                    <th class="pb-2 w-24">Discount</th>
                    <th class="pb-2 w-28">Line total</th>
                    <th class="pb-2 w-8"></th>
                </tr>
                </thead>
                <tbody>
                    <template x-for="(row, i) in rows" :key="i">
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-2 pr-2">
                                <select x-model="row.service_id" @change="row.service_id ? (row.description = services[row.service_id].name, row.unit_price = services[row.service_id].price) : ''" class="input !py-1.5 !text-xs">
                                    <option value="">Custom</option>
                                    <template x-for="(s, sid) in services" :key="sid">
                                        <option :value="sid" x-text="s.name + ' — ' + s.category"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="service_id[]" :value="row.service_id">
                                <input type="text" x-model="row.description" name="description[]" placeholder="Description" class="input !py-1.5 !text-xs mt-1" required>
                            </td>
                            <td class="py-2 pr-2"><input type="number" step="0.01" min="0" x-model.number="row.quantity" name="quantity[]" class="input !py-1.5 !text-xs" required></td>
                            <td class="py-2 pr-2"><input type="number" step="0.01" min="0" x-model.number="row.unit_price" name="unit_price[]" class="input !py-1.5 !text-xs" required></td>
                            <td class="py-2 pr-2"><input type="number" step="0.01" min="0" x-model.number="row.item_discount" name="item_discount[]" class="input !py-1.5 !text-xs"></td>
                            <td class="py-2 pr-2 font-mono font-medium text-slate-700 dark:text-slate-200" x-text="lineTotal(row).toFixed(2)"></td>
                            <td class="py-2"><button type="button" @click="rows.splice(i, 1)" x-show="rows.length > 1" class="text-rose-500"><i data-lucide="x" class="h-4 w-4"></i></button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="rows.push({service_id:'',description:'',quantity:1,unit_price:0,item_discount:0})" class="btn btn-secondary !py-1.5 !text-xs">
                <i data-lucide="plus" class="h-3.5 w-3.5"></i>Add line
            </button>
        </div>
    </section>

    <section class="card">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-500/10 text-navy-600 dark:text-navy-300"><i data-lucide="calculator" class="h-4 w-4"></i></span>
            <h2 class="text-sm font-semibold">Summary</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div class="space-y-3">
                <div>
                    <label for="invoice_date" class="label">Invoice date</label>
                    <input type="date" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" class="input" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="discount_percentage" class="label">Discount %</label>
                        <input type="number" step="0.01" min="0" max="100" id="discount_percentage" name="discount_percentage" value="0" x-model.number="discountPct" class="input">
                    </div>
                    <div>
                        <label for="tax_percentage" class="label">Tax %</label>
                        <input type="number" step="0.01" min="0" max="100" id="tax_percentage" name="tax_percentage" value="<?= e((string) $taxRate) ?>" x-model.number="taxPct" class="input">
                    </div>
                </div>
                <div>
                    <label for="notes" class="label">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="input" placeholder="Optional notes…"></textarea>
                </div>
            </div>
            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-mono font-medium" x-text="subtotal().toFixed(2)"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount ({{ discountPct }}%)</dt><dd class="font-mono text-rose-500" x-text="'-' + discountAmount().toFixed(2)"></dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 dark:border-slate-700"><dt class="text-slate-500">After discount</dt><dd class="font-mono" x-text="afterDiscount().toFixed(2)"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tax ({{ taxPct }}%)</dt><dd class="font-mono text-amber-600" x-text="taxAmount().toFixed(2)"></dd></div>
                    <div class="flex justify-between border-t-2 border-teal-500 pt-2 text-[15px] font-bold"><dt>Total</dt><dd class="font-mono text-teal-600 dark:text-teal-400" x-text="total().toFixed(2) + ' ' + '<?= e($currency) ?>'"></dd></div>
                </dl>
            </div>
        </div>
    </section>

    <div class="card flex flex-col items-center justify-between gap-3 p-5 sm:flex-row">
        <p class="flex items-center gap-1.5 text-xs text-slate-400">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Invoice starts as draft — send it from the detail page to make it payable.
        </p>
        <button type="submit" class="btn btn-primary"><i data-lucide="save" class="h-4 w-4"></i>Create invoice</button>
    </div>
</form>

<script>
function invoiceForm(services, defaultTax) {
    return {
        services: services,
        rows: [{service_id:'',description:'',quantity:1,unit_price:0,item_discount:0}],
        discountPct: 0,
        taxPct: defaultTax,
        lineTotal: function(row) {
            var lt = (parseFloat(row.quantity) || 0) * (parseFloat(row.unit_price) || 0) - (parseFloat(row.item_discount) || 0);
            return lt < 0 ? 0 : lt;
        },
        subtotal: function() { return this.rows.reduce(function(s, r) { return s + this.lineTotal(r); }.bind(this), 0); },
        discountAmount: function() { return this.subtotal() * (this.discountPct / 100); },
        afterDiscount: function() { return this.subtotal() - this.discountAmount(); },
        taxAmount: function() { return this.afterDiscount() * (this.taxPct / 100); },
        total: function() { return this.afterDiscount() + this.taxAmount(); },
    };
}
</script>
<?php $this->end(); ?>
