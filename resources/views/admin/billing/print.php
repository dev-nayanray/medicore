<?php

declare(strict_types=1);

/**
 * Printable invoice — standalone print layout with hospital branding.
 * Opens via target="_blank" and auto-triggers the print dialog.
 * $invoice (with items + payments)
 */

$hospitalName = (string) setting('hospital_name', 'MediCore General Hospital');
$hospitalAddress = (string) setting('hospital_address', '');
$hospitalPhone = (string) setting('hospital_phone', '');
$currency = (string) setting('currency', 'BDT');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice · <?= e($invoice['invoice_code']) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Inter, system-ui, sans-serif; color: #1e293b; background: #f1f5f9; padding: 24px; font-size: 13px; line-height: 1.5; }
    .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px 48px; border-radius: 8px; box-shadow: 0 2px 12px rgb(15 23 42 / .08); }
    header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0d9488; padding-bottom: 16px; }
    .brand { display: flex; gap: 12px; align-items: center; }
    .mark { width: 42px; height: 42px; border-radius: 10px; background: #0b1f3a; display: grid; place-items: center; color: #2dd4bf; font-weight: 800; font-size: 20px; }
    h1 { font-size: 18px; color: #0b1f3a; }
    .muted { color: #64748b; font-size: 11.5px; }
    .inv-meta { text-align: right; font-size: 11.5px; color: #64748b; }
    .code { font-family: ui-monospace, monospace; color: #0f766e; font-weight: 600; }
    .status-paid { color: #059669; font-weight: 600; }
    .status-partial { color: #c2410c; font-weight: 600; }
    .status-cancelled { color: #be123c; font-weight: 600; text-decoration: line-through; }
    h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #0f766e; margin: 22px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
    .bill-to { display: flex; gap: 24px; margin-top: 8px; }
    .bill-to > div { flex: 1; }
    .bill-to dt { color: #64748b; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; }
    .bill-to dd { font-weight: 500; margin-top: 1px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #f0fdfa; color: #0f766e; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; text-align: left; padding: 8px 10px; border-bottom: 2px solid #ccfbf1; }
    td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 12.5px; }
    .num { text-align: right; font-family: ui-monospace, monospace; }
    .totals { margin-top: 12px; margin-left: auto; width: 280px; }
    .totals .row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 12.5px; }
    .totals .grand { border-top: 2px solid #0d9488; margin-top: 6px; padding-top: 8px; font-weight: 700; font-size: 15px; color: #0f766e; }
    .totals .paid { color: #059669; }
    .totals .balance { font-weight: 700; color: #c2410c; }
    .footer { margin-top: 36px; padding-top: 12px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; color: #94a3b8; font-size: 10.5px; }
    .no-print { text-align: center; margin: 20px 0 0; }
    .no-print button { background: #0d9488; color: #fff; border: 0; border-radius: 8px; padding: 10px 22px; font-size: 13px; font-weight: 600; cursor: pointer; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; border-radius: 0; max-width: none; } .no-print { display: none; } }
</style>
</head>
<body>
<div class="sheet">
    <header>
        <div class="brand">
            <div class="mark">+</div>
            <div>
                <h1><?= e($hospitalName) ?></h1>
                <p class="muted"><?= e($hospitalAddress) ?><?= $hospitalAddress !== '' ? ' · ' : '' ?><?= e($hospitalPhone) ?></p>
            </div>
        </div>
        <div class="inv-meta">
            <p><strong>Invoice</strong></p>
            <p class="code"><?= e($invoice['invoice_code']) ?></p>
            <p>Date: <?= e(format_date($invoice['invoice_date'], 'M j, Y')) ?></p>
            <p>Status: <span class="status-<?= $invoice['status'] === 'paid' ? 'paid' : ($invoice['status'] === 'partially_paid' ? 'partial' : ($invoice['status'] === 'cancelled' ? 'cancelled' : '')) ?>"><?= e(ucfirst(str_replace('_', ' ', $invoice['status']))) ?></span></p>
        </div>
    </header>

    <h2>Bill to</h2>
    <dl class="bill-to">
        <div><dt>Patient</dt><dd><?= e($invoice['patient_name']) ?></dd></div>
        <div><dt>Patient ID</dt><dd class="code"><?= e($invoice['patient_code']) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($invoice['patient_phone'] ?? '—') ?></dd></div>
    </dl>

    <h2>Items</h2>
    <table>
        <thead>
        <tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Disc</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($invoice['items'] as $item): ?>
            <tr>
                <td><?= e($item['description']) ?></td>
                <td class="num"><?= e((string) $item['quantity']) ?></td>
                <td class="num"><?= e(format_money($item['unit_price'], $currency)) ?></td>
                <td class="num"><?= $item['discount_amount'] > 0 ? e(format_money($item['discount_amount'], $currency)) : '—' ?></td>
                <td class="num"><?= e(format_money($item['line_total'], $currency)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>Subtotal</span><span class="num"><?= e(format_money($invoice['subtotal'], $currency)) ?></span></div>
        <?php if ((float) $invoice['discount_amount'] > 0): ?>
        <div class="row"><span>Discount (<?= e((string) $invoice['discount_percentage']) ?>%)</span><span class="num">-<?= e(format_money($invoice['discount_amount'], $currency)) ?></span></div>
        <?php endif; ?>
        <?php if ((float) $invoice['tax_amount'] > 0): ?>
        <div class="row"><span>Tax (<?= e((string) $invoice['tax_percentage']) ?>%)</span><span class="num"><?= e(format_money($invoice['tax_amount'], $currency)) ?></span></div>
        <?php endif; ?>
        <div class="row grand"><span>Total</span><span class="num"><?= e(format_money($invoice['total'], $currency)) ?></span></div>
        <div class="row paid"><span>Paid</span><span class="num"><?= e(format_money($invoice['paid_amount'], $currency)) ?></span></div>
        <div class="row balance"><span>Balance due</span><span class="num"><?= e(format_money($invoice['balance_due'], $currency)) ?></span></div>
    </div>

    <?php if (!empty($invoice['notes'])): ?>
        <h2>Notes</h2>
        <p style="font-size:12px; margin-top:4px; color:#475569;"><?= e($invoice['notes']) ?></p>
    <?php endif; ?>

    <div class="footer">
        <span><?= e($hospitalName) ?> · financial document</span>
        <span><?= e($invoice['invoice_code']) ?> · generated by MediCore</span>
    </div>
</div>

<p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>
</body>
</html>
