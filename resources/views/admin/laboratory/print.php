<?php
declare(strict_types=1);
$hospitalName = (string) setting('hospital_name', 'MediCore General Hospital');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Lab Report · <?= e($order['order_code']) ?></title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Segoe UI',Inter,system-ui,sans-serif;color:#1e293b;background:#f1f5f9;padding:24px;font-size:13px}.sheet{max-width:800px;margin:0 auto;background:#fff;padding:40px 48px;border-radius:8px;box-shadow:0 2px 12px rgb(15 23 42/.08)}header{display:flex;justify-content:space-between;border-bottom:3px solid #0d9488;padding-bottom:16px}.mark{width:42px;height:42px;border-radius:10px;background:#0b1f3a;display:grid;place-items:center;color:#2dd4bf;font-weight:800;font-size:20px}h1{font-size:18px;color:#0b1f3a}.muted{color:#64748b;font-size:11.5px}.meta{text-align:right;font-size:11.5px;color:#64748b}.code{font-family:ui-monospace,monospace;color:#0f766e;font-weight:600}h2{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#0f766e;margin:22px 0 8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px}table{width:100%;border-collapse:collapse;margin-top:8px}th{background:#f0fdfa;color:#0f766e;font-size:10.5px;text-transform:uppercase;text-align:left;padding:8px;border-bottom:2px solid #ccfbf1}td{padding:8px;border-bottom:1px solid #f1f5f9;font-size:12.5px;vertical-align:top}.critical{color:#be123c;font-weight:700}.footer{margin-top:36px;padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;color:#94a3b8;font-size:10.5px}.no-print{text-align:center;margin:20px 0 0}.no-print button{background:#0d9488;color:#fff;border:0;border-radius:8px;padding:10px 22px;font-size:13px;font-weight:600;cursor:pointer}@media print{body{background:#fff;padding:0}.sheet{box-shadow:none;max-width:none}.no-print{display:none}}</style>
</head><body><div class="sheet">
<header><div style="display:flex;gap:12px;align-items:center"><div class="mark">+</div><div><h1><?= e($hospitalName) ?></h1><p class="muted">Laboratory Report</p></div></div><div class="meta"><p><strong>Report</strong></p><p class="code"><?= e($order['order_code']) ?></p><p>Date: <?= e(date('M j, Y')) ?></p></div></header>
<h2>Patient</h2><p style="font-size:12.5px;margin-top:4px;"><strong><?= e($order['patient_name']) ?></strong> · <?= e($order['patient_code']) ?> · <?= e($order['gender']) ?><?php if ($order['date_of_birth']): ?> · DOB: <?= e(format_date($order['date_of_birth'], 'M j, Y')) ?><?php endif; ?><?php if ($order['doctor_name']): ?> · Ref by: Dr. <?= e($order['doctor_name']) ?><?php endif; ?></p>
<h2>Test results</h2>
<table><thead><tr><th>Test</th><th>Result</th><th>Unit</th><th>Reference range</th></tr></thead><tbody>
<?php foreach ($order['items'] as $item): ?>
<tr><td style="font-weight:600"><?= e($item['test_name']) ?></td><td class="<?= (int) $item['is_critical'] === 1 ? 'critical' : '' ?>"><?= e($item['result_value'] ?? 'Pending') ?></td><td><?= e($item['result_unit'] ?? '—') ?></td><td><?= e($item['reference_range'] ?? '—') ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<div class="footer"><span><?= e($hospitalName) ?> · confidential laboratory report</span><span><?= e($order['order_code']) ?> · MediCore</span></div>
</div><p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>
</body></html>
