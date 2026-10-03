<?php

declare(strict_types=1);

/**
 * Printable prescription — standalone print layout with hospital branding.
 * Opens via target="_blank" and auto-triggers the print dialog.
 * $rx (with items, patient, doctor, consultation, hospital settings)
 */

$hospitalName = (string) setting('hospital_name', 'MediCore General Hospital');
$hospitalAddress = (string) setting('hospital_address', '');
$hospitalPhone = (string) setting('hospital_phone', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prescription · <?= e($rx['prescription_code']) ?></title>
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
    .rx-meta { text-align: right; font-size: 11.5px; color: #64748b; }
    .code { font-family: ui-monospace, monospace; color: #0f766e; font-weight: 600; }
    h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #0f766e; margin: 22px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
    .patient-row { display: flex; justify-content: space-between; gap: 24px; margin-top: 8px; font-size: 12px; }
    .patient-row > div { flex: 1; }
    .patient-row dt { color: #64748b; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; }
    .patient-row dd { font-weight: 500; margin-top: 1px; }
    .allergy-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 6px; padding: 8px 12px; margin-top: 12px; font-size: 12.5px; font-weight: 500; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #f0fdfa; color: #0f766e; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; text-align: left; padding: 8px 10px; border-bottom: 2px solid #ccfbf1; }
    td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 12.5px; }
    td.num { color: #94a3b8; font-family: ui-monospace, monospace; width: 30px; }
    td.medicine { font-weight: 600; }
    .notes { margin-top: 16px; background: #f8fafc; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #475569; }
    .footer { margin-top: 36px; display: flex; justify-content: space-between; gap: 24px; }
    .sign { text-align: center; }
    .sign-line { border-top: 1px solid #475569; margin-top: 32px; padding-top: 4px; font-size: 11px; color: #64748b; }
    .footer-info { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; color: #94a3b8; font-size: 10.5px; }
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
        <div class="rx-meta">
            <p><strong>℞ Prescription</strong></p>
            <p class="code"><?= e($rx['prescription_code']) ?></p>
            <p>Issued <?= e(date('M j, Y')) ?></p>
            <p>Status: <?= e(ucfirst($rx['status'])) ?></p>
        </div>
    </header>

    <h2>Patient</h2>
    <dl class="patient-row">
        <div><dt>Name</dt><dd><?= e($rx['patient_name']) ?></dd></div>
        <div><dt>Patient ID</dt><dd class="code"><?= e($rx['patient_code']) ?></dd></div>
        <div><dt>Age / Gender</dt><dd><?= e($rx['gender']) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($rx['phone'] ?? '—') ?></dd></div>
    </dl>
    <?php if (!empty($rx['allergies']) && strtolower(trim((string) $rx['allergies'])) !== 'none known'): ?>
        <div class="allergy-banner">⚠ Known allergies: <?= e($rx['allergies']) ?></div>
    <?php endif; ?>

    <h2>Diagnosis</h2>
    <p style="font-size:12.5px; margin-top:4px;"><?= e($rx['diagnoses'] ?: 'Not specified') ?></p>

    <h2>Prescribed medicines</h2>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Medicine</th>
            <th>Dosage</th>
            <th>Frequency</th>
            <th>Duration</th>
            <th>Qty</th>
            <th>Instructions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rx['items'] as $i => $item): ?>
            <tr>
                <td class="num"><?= (int) $i + 1 ?></td>
                <td class="medicine"><?= e($item['medicine_name']) ?></td>
                <td><?= e($item['dosage']) ?></td>
                <td><?= e($item['frequency']) ?></td>
                <td><?= e($item['duration']) ?></td>
                <td><?= $item['quantity'] !== null ? (int) $item['quantity'] : '—' ?></td>
                <td><?= e($item['instructions'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($rx['notes']): ?>
        <div class="notes"><strong>Notes:</strong> <?= e($rx['notes']) ?></div>
    <?php endif; ?>

    <div class="footer">
        <div>
            <p class="muted">Consultation: <span class="code"><?= e($rx['consultation_code']) ?></span></p>
            <p class="muted">Date: <?= e(format_date(substr((string) $rx['consultation_date'], 0, 10), 'M j, Y')) ?></p>
        </div>
        <div class="sign">
            <p style="font-weight:600; color:#0b1f3a;">Dr. <?= e($rx['doctor_name']) ?></p>
            <p class="muted"><?= e($rx['specialization']) ?></p>
            <p class="muted">Reg. <?= e($rx['registration_number'] ?? '—') ?></p>
            <div class="sign-line">Signature & stamp</div>
        </div>
    </div>

    <div class="footer-info">
        <span><?= e($hospitalName) ?> · confidential prescription</span>
        <span><?= e($rx['prescription_code']) ?> · generated by MediCore</span>
    </div>
</div>

<p class="no-print"><button onclick="window.print()">Print this prescription</button></p>
</body>
</html>
