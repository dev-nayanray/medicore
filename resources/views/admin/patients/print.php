<?php

declare(strict_types=1);

/**
 * Printable patient summary — standalone print layout, no admin chrome.
 * Opens via target="_blank" and auto-triggers the print dialog.
 * $patient, $age, $visits
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
<title>Patient Summary · <?= e($patient['patient_code']) ?></title>
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
    .doc-meta { text-align: right; font-size: 11.5px; color: #64748b; }
    .code { font-family: ui-monospace, monospace; color: #0f766e; font-weight: 600; }
    h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #0f766e; margin: 22px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 4px 10px 4px 0; vertical-align: top; }
    td.label { width: 160px; color: #64748b; font-size: 11.5px; padding-top: 6px; }
    .banner { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; border-radius: 6px; padding: 8px 12px; margin-top: 8px; font-size: 12.5px; }
    .visit { border-left: 3px solid #14b8a6; padding: 8px 0 8px 14px; margin-bottom: 12px; }
    .visit .date { font-size: 11px; color: #64748b; }
    .visit .cc { font-weight: 600; margin-top: 2px; }
    .visit .dx { margin-top: 1px; }
    .visit .note { color: #475569; font-size: 12px; margin-top: 2px; white-space: pre-line; }
    .pill { display: inline-block; border: 1px solid #cbd5e1; border-radius: 99px; padding: 0 8px; font-size: 10.5px; color: #475569; margin-right: 4px; }
    footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; color: #94a3b8; font-size: 10.5px; }
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
        <div class="doc-meta">
            <p><strong>Patient Summary</strong></p>
            <p>Printed <?= e(date('M j, Y — g:i A')) ?></p>
            <p>by <?= e(auth_user()['name'] ?? 'system') ?></p>
        </div>
    </header>

    <h2>Patient</h2>
    <table>
        <tr>
            <td class="label">Patient ID</td><td><span class="code"><?= e($patient['patient_code']) ?></span></td>
            <td class="label">Name</td><td><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></td>
        </tr>
        <tr>
            <td class="label">Age / Gender</td><td><?= (int) $age ?> yrs · <?= e(ucfirst((string) $patient['gender'])) ?></td>
            <td class="label">Date of birth</td><td><?= e(format_date($patient['date_of_birth'], 'M j, Y')) ?></td>
        </tr>
        <tr>
            <td class="label">Blood group</td><td><?= e($patient['blood_group'] ?? '—') ?></td>
            <td class="label">Phone</td><td><?= e($patient['phone']) ?></td>
        </tr>
        <tr>
            <td class="label">Address</td>
            <td colspan="3"><?= e(trim(($patient['address'] ?? '') . ' ' . ($patient['city'] ?? '') . ' ' . ($patient['country'] ?? '')) ?: '—') ?></td>
        </tr>
        <tr>
            <td class="label">Emergency contact</td>
            <td colspan="3"><?= e(($patient['emergency_contact_name'] ?? '') !== '' ? $patient['emergency_contact_name'] . ' (' . ($patient['emergency_contact_relation'] ?? '—') . ') · ' . $patient['emergency_contact_phone'] : '—') ?></td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td colspan="3"><?= $patient['archived_at'] !== null ? 'ARCHIVED (history preserved)' : 'Active' ?> · registered <?= e(format_date($patient['created_at'], 'M j, Y')) ?></td>
        </tr>
    </table>

    <?php if (trim((string) ($patient['allergies'] ?? '')) !== ''): ?>
        <div class="banner"><strong>⚠ Allergies:</strong> <?= e($patient['allergies']) ?></div>
    <?php endif; ?>

    <h2>Medical history</h2>
    <p><?= e(trim((string) ($patient['medical_history'] ?? '')) !== '' ? (string) $patient['medical_history'] : 'None recorded.') ?></p>

    <?php if (trim((string) ($patient['notes'] ?? '')) !== ''): ?>
        <h2>Clinical notes</h2>
        <p><?= e($patient['notes']) ?></p>
    <?php endif; ?>

    <h2>Visit history (<?= count($visits) ?>)</h2>
    <?php if ($visits === []): ?>
        <p class="muted">No visits recorded.</p>
    <?php else: ?>
        <?php foreach ($visits as $visit): ?>
            <div class="visit">
                <div class="date"><?= e(format_date($visit['visited_at'], 'M j, Y — g:i A')) ?> · <span class="pill"><?= e(ucfirst((string) $visit['visit_type'])) ?></span><?= $visit['status'] !== 'completed' ? '<span class="pill">' . e(ucfirst((string) $visit['status'])) . '</span>' : '' ?><?= $visit['doctor_name'] ? '<span class="pill">Dr. ' . e($visit['doctor_name']) . '</span>' : '' ?></div>
                <div class="cc"><?= e($visit['chief_complaint']) ?></div>
                <?php if ($visit['diagnosis']): ?><div class="dx"><strong>Dx:</strong> <?= e($visit['diagnosis']) ?></div><?php endif; ?>
                <?php if ($visit['notes']): ?><div class="note"><?= e($visit['notes']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer>
        <span><?= e($hospitalName) ?> · confidential patient record</span>
        <span><?= e($patient['patient_code']) ?> · generated by MediCore</span>
    </footer>
</div>

<p class="no-print"><button onclick="window.print()">Print this summary</button></p>
</body>
</html>
