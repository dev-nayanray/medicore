<?php

declare(strict_types=1);

/**
 * Invoice detail — line items, payment history, action buttons.
 * $invoice (with items + payments), $age
 */

$this->extend('layouts/admin');
$title = 'Invoice ' . $invoice['invoice_code'];
$active = 'billing';
$breadcrumbs = ['Finance' => null, 'Invoices' => url('/admin/billing'), $invoice['invoice_code'] => ''];

$currency = (string) setting('currency', 'BDT');
$statusTone = ['draft' => 'badge-slate', 'sent' => 'badge-amber', 'partially_paid' => 'badge-navy', 'paid' => 'badge-emerald', 'cancelled' => 'badge-rose', 'refunded' => 'badge-violet'];
$isCancelled = $invoice['status'] === 'cancelled';
$isPaid = $invoice['status'] === 'paid';
$hasBalance = (float) $invoice['balance_due'] > 0 && !$isCancelled;
$canPay = can('payments.create') && $hasBalance;
$canRefund = can('payments.approve') && (float) $invoice['paid_amount'] > 0 && !$isCancelled;
$canCancel = can('billing.update') && !$isCancelled && !$isPaid;
?>

<?php $this->section('content'); ?>

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
            <span class="font-mono"><?= e($invoice['invoice_code']) ?></span>
            <span class="badge <?= $statusTone[$invoice['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', $invoice['status']))) ?></span>
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            <a href="<?= url('/admin/patients/' . (int) $invoice['patient_id']) ?>" class="font-medium text-teal-600 hover:underline dark:text-teal-400"><?= e($invoice['patient_name']) ?></a>
            <span class="font-mono text-xs"><?= e($invoice['patient_code']) ?></span>
            · <?= e(format_date($invoice['invoice_date'], 'M j, Y')) ?>
            <?php if ($invoice['doctor_name']): ?>· Dr. <?= e($invoice['doctor_name']) ?><?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('/admin/billing/' . (int) $invoice['id'] . '/print') ?>" target="_blank" class="btn btn-secondary"><i data-lucide="printer" class="h-4 w-4"></i>Print</a>
        <?php if ($canCancel): ?>
            <form method="post" action="<?= url('/admin/billing/' . (int) $invoice['id'] . '/cancel') ?>" data-confirm="Cancel invoice|Provide a cancellation reason. Cancelled invoices cannot be reactivated.">
                <?= csrf_field() ?>
                <input type="text" name="cancellation_reason" placeholder="Reason…" class="input !inline-block !w-40 !py-1.5 !text-xs" required>
                <button type="submit" class="btn btn-danger"><i data-lucide="x-circle" class="h-4 w-4"></i>Cancel</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($isCancelled): ?>
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/60 dark:bg-rose-950/30">
        <p class="flex items-center gap-2 text-sm font-semibold text-rose-700 dark:text-rose-400"><i data-lucide="ban" class="h-4 w-4"></i>This invoice was cancelled by <?= e($invoice['cancelled_by_name'] ?? 'system') ?> on <?= e(format_date($invoice['cancelled_at'], 'M j, Y — g:i A')) ?></p>
        <?php if ($invoice['cancellation_reason']): ?><p class="mt-1 text-xs text-rose-600 dark:text-rose-400">Reason: <?= e($invoice['cancellation_reason']) ?></p><?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <!-- Line items + summary -->
    <div class="card lg:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Line items</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit price</th><th class="text-right">Disc</th><th class="text-right">Total</th></tr>
                </thead>
                <tbody>
                <?php foreach ($invoice['items'] as $item): ?>
                    <tr>
                        <td>
                            <?= e($item['description']) ?>
                            <?php if ($item['service_category']): ?><span class="badge badge-slate ml-1"><?= e($item['service_category']) ?></span><?php endif; ?>
                        </td>
                        <td class="text-right font-mono"><?= e((string) $item['quantity']) ?></td>
                        <td class="text-right font-mono"><?= e(format_money($item['unit_price'], $currency)) ?></td>
                        <td class="text-right font-mono text-rose-500"><?= $item['discount_amount'] > 0 ? e(format_money($item['discount_amount'], $currency)) : '—' ?></td>
                        <td class="text-right font-mono font-medium"><?= e(format_money($item['line_total'], $currency)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
            <dl class="ml-auto max-w-xs space-y-1.5 text-[13px]">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-mono"><?= e(format_money($invoice['subtotal'], $currency)) ?></dd></div>
                <?php if ((float) $invoice['discount_amount'] > 0): ?>
                <div class="flex justify-between"><dt class="text-slate-500">Discount (<?= e((string) $invoice['discount_percentage']) ?>%)</dt><dd class="font-mono text-rose-500">-<?= e(format_money($invoice['discount_amount'], $currency)) ?></dd></div>
                <?php endif; ?>
                <?php if ((float) $invoice['tax_amount'] > 0): ?>
                <div class="flex justify-between"><dt class="text-slate-500">Tax (<?= e((string) $invoice['tax_percentage']) ?>%)</dt><dd class="font-mono text-amber-600"><?= e(format_money($invoice['tax_amount'], $currency)) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between border-t-2 border-teal-500 pt-2 text-[15px] font-bold"><dt>Total</dt><dd class="font-mono text-teal-600 dark:text-teal-400"><?= e(format_money($invoice['total'], $currency)) ?></dd></div>
                <div class="flex justify-between"><dt class="text-emerald-600">Paid</dt><dd class="font-mono text-emerald-600 dark:text-emerald-400"><?= e(format_money($invoice['paid_amount'], $currency)) ?></dd></div>
                <div class="flex justify-between text-[14px] font-bold"><dt class="<?= $hasBalance ? 'text-amber-600' : 'text-slate-400' ?>">Balance due</dt><dd class="font-mono <?= $hasBalance ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' ?>"><?= e(format_money($invoice['balance_due'], $currency)) ?></dd></div>
            </dl>
        </div>
    </div>

    <!-- Actions sidebar -->
    <div class="space-y-4">
        <?php if ($canPay): ?>
        <div class="card" x-data="{ showPay: false }">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Record payment</h2></div>
            <button @click="showPay = !showPay" class="btn btn-primary w-full justify-center m-4 !w-[calc(100%-2rem)]"><i data-lucide="credit-card" class="h-4 w-4"></i>Record payment</button>
            <form x-show="showPay" x-cloak method="post" action="<?= url('/admin/billing/' . (int) $invoice['id'] . '/payment') ?>" class="space-y-3 px-5 pb-5">
                <?= csrf_field() ?>
                <div>
                    <label class="label">Amount <?= e($currency) ?></label>
                    <input type="number" step="0.01" min="0.01" max="<?= e((string) $invoice['balance_due']) ?>" name="amount" value="<?= e((string) $invoice['balance_due']) ?>" class="input" required>
                </div>
                <div>
                    <label class="label">Method</label>
                    <select name="payment_method" class="input">
                        <?php foreach (App\Models\Payment::METHODS as $m): ?>
                            <option value="<?= e($m) ?>"><?= e(ucfirst(str_replace('_', ' ', $m))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label">Reference (optional)</label>
                    <input type="text" name="reference_number" class="input" placeholder="Transaction ID / check no.">
                </div>
                <button type="submit" class="btn btn-primary w-full justify-center"><i data-lucide="check" class="h-4 w-4"></i>Confirm payment</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($canRefund): ?>
        <div class="card" x-data="{ showRefund: false }">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold text-rose-600">Record refund</h2></div>
            <button @click="showRefund = !showRefund" class="btn btn-danger w-full justify-center m-4 !w-[calc(100%-2rem)]"><i data-lucide="undo-2" class="h-4 w-4"></i>Issue refund</button>
            <form x-show="showRefund" x-cloak method="post" action="<?= url('/admin/billing/' . (int) $invoice['id'] . '/refund') ?>" class="space-y-3 px-5 pb-5" data-confirm="Confirm refund|This will reduce the paid amount and may change the invoice status.|danger">
                <?= csrf_field() ?>
                <div>
                    <label class="label">Refund amount <?= e($currency) ?></label>
                    <input type="number" step="0.01" min="0.01" max="<?= e((string) $invoice['paid_amount']) ?>" name="amount" required class="input">
                </div>
                <div>
                    <label class="label">Method</label>
                    <select name="payment_method" class="input">
                        <?php foreach (App\Models\Payment::METHODS as $m): ?>
                            <option value="<?= e($m) ?>"><?= e(ucfirst(str_replace('_', ' ', $m))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label">Reason <span class="text-rose-500">*</span></label>
                    <input type="text" name="refund_reason" required class="input" placeholder="Reason for refund" minlength="5">
                </div>
                <button type="submit" class="btn btn-danger w-full justify-center"><i data-lucide="undo-2" class="h-4 w-4"></i>Confirm refund</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment history -->
<div class="card mt-4">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Payment history</h2>
        <span class="badge badge-slate"><?= count($invoice['payments']) ?></span>
    </div>
    <?php if ($invoice['payments'] === []): ?>
        <p class="px-5 py-6 text-center text-[12.5px] text-slate-400">No payments recorded yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Code</th><th>Date / time</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th><th>Type</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($invoice['payments'] as $pay): ?>
                    <tr class="<?= (int) $pay['is_refund'] === 1 ? 'bg-rose-50/50 dark:bg-rose-950/20' : '' ?>">
                        <td class="font-mono text-[12px] text-teal-600 dark:text-teal-400"><?= e($pay['payment_code']) ?></td>
                        <td class="whitespace-nowrap text-[12.5px] text-slate-500"><?= e(format_date($pay['recorded_at'], 'M j, Y — g:i A')) ?></td>
                        <td class="text-[12.5px]"><?= e(ucfirst(str_replace('_', ' ', $pay['payment_method']))) ?></td>
                        <td class="font-mono text-[12px] text-slate-400"><?= e($pay['reference_number'] ?? '—') ?></td>
                        <td class="text-right font-mono font-medium <?= (int) $pay['is_refund'] === 1 ? 'text-rose-600' : 'text-emerald-600' ?>">
                            <?= (int) $pay['is_refund'] === 1 ? '-' : '' ?><?= e(format_money(abs((float) $pay['amount']), $currency)) ?>
                        </td>
                        <td>
                            <?php if ((int) $pay['is_refund'] === 1): ?><span class="badge badge-rose">Refund</span>
                            <?php elseif ($pay['status'] === 'void'): ?><span class="badge badge-slate">Void</span>
                            <?php else: ?><span class="badge badge-emerald">Payment</span><?php endif; ?>
                        </td>
                        <td class="text-[12.5px] text-slate-500"><?= e($pay['recorded_by_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($invoice['notes'])): ?>
<div class="card mt-4">
    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-semibold">Notes</h2></div>
    <div class="p-5 text-[13px] leading-relaxed text-slate-600 dark:text-slate-300 whitespace-pre-line"><?= e((string) $invoice['notes']) ?></div>
</div>
<?php endif; ?>
<?php $this->end(); ?>
