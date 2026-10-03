<?php
declare(strict_types=1);
namespace Seeders;
use PDO;

/**
 * Seeds wards, rooms, beds, one active admission, and sample notifications.
 */
final class BedManagementSeeder extends Seeder
{
    public static function label(): string { return 'Bed Management & Notifications'; }
    public static function order(): int { return 142; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'notifications', 'bed_transfers', 'admissions', 'beds', 'rooms', 'wards');

        // Wards.
        $wards = [
            ['General Ward', '2nd Floor', 'General medicine inpatient ward'],
            ['Cardiology Ward', '3rd Floor', 'Cardiac care and post-procedure recovery'],
            ['ICU', '4th Floor', 'Intensive care unit'],
            ['Maternity Ward', '1st Floor', 'OB/GYN inpatient'],
            ['Pediatrics', '2nd Floor', 'Child health inpatient'],
        ];
        $wInsert = $db->prepare('INSERT INTO wards (name, floor, description, is_active) VALUES (?, ?, ?, 1)');
        $wardIds = [];
        foreach ($wards as $w) { $wInsert->execute($w); $wardIds[] = (int) $db->lastInsertId(); }

        // Rooms + beds.
        $rooms = [
            [$wardIds[0], '201', 'general', 2000, 4], [$wardIds[0], '202', 'general', 2000, 4],
            [$wardIds[0], '203', 'semi_private', 3500, 2],
            [$wardIds[1], '301', 'private', 6000, 1], [$wardIds[1], '302', 'private', 6000, 1], [$wardIds[1], '303', 'icu', 12000, 2],
            [$wardIds[2], '401', 'icu', 12000, 4], [$wardIds[2], '402', 'icu', 12000, 4],
            [$wardIds[3], '101', 'general', 2500, 3], [$wardIds[3], '102', 'private', 5000, 1],
            [$wardIds[4], '204', 'general', 2000, 3],
        ];
        $rInsert = $db->prepare('INSERT INTO rooms (ward_id, room_number, room_type, daily_rate, is_active) VALUES (?, ?, ?, ?, 1)');
        $bInsert = $db->prepare('INSERT INTO beds (room_id, bed_number, status) VALUES (?, ?, "available")');
        $bedIds = [];
        foreach ($rooms as $r) {
            $bedCount = $r[4]; // 5th element is bed count, NOT a SQL parameter
            $rInsert->execute(array_slice($r, 0, 4)); $roomId = (int) $db->lastInsertId();
            for ($i = 1; $i <= $bedCount; $i++) {
                $bInsert->execute([$roomId, (string) $i]); $bedIds[] = (int) $db->lastInsertId();
            }
        }

        // Mark some beds occupied (via admissions).
        $patients = $db->query('SELECT id FROM patients WHERE archived_at IS NULL LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
        $doctors = $db->query("SELECT id FROM doctors WHERE archived_at IS NULL LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
        $adminId = (int) $db->query("SELECT id FROM users WHERE email = 'admin@medicore.test'")->fetchColumn() ?: null;

        if (count($patients) >= 3 && count($bedIds) >= 3) {
            $year = (int) date('Y');
            $admCodes = ['ADM-' . $year . '-00001', 'ADM-' . $year . '-00002', 'ADM-' . $year . '-00003'];
            $admissionData = [
                [$patients[0]['id'], $bedIds[0], 'scheduled', 'Chest pain observation', 'Rule out ACS'],
                [$patients[1]['id'], $bedIds[4], 'emergency', 'Acute exacerbation of COPD', 'COPD with type 2 respiratory failure'],
                [$patients[2]['id'], $bedIds[6], 'scheduled', 'Post-operative recovery', 'Post appendectomy'],
            ];
            $aInsert = $db->prepare(
                'INSERT INTO admissions (admission_code, patient_id, doctor_id, ward_id, room_id, bed_id, admission_type, admission_date, expected_discharge, admission_reason, diagnosis_at_admission, status, created_by)
                 VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, "admitted", ?)'
            );
            foreach ($admissionData as $i => $a) {
                $doctorId = $doctors[0]['id'] ?? null;
                $date = date('Y-m-d H:i:s', strtotime('-' . (2 - $i) . ' days'));
                $aInsert->execute([$admCodes[$i], $a[0], $doctorId, $a[1], $a[2], $date, date('Y-m-d', strtotime('+5 days')), $a[3], $a[4], $adminId]);
                $admId = (int) $db->lastInsertId();
                $db->prepare('UPDATE beds SET status = "occupied", current_admission_id = ? WHERE id = ?')->execute([$admId, $a[1]]);
                // Fill ward/room from bed.
                $wardId = (int) $db->query('SELECT w.id FROM beds b INNER JOIN rooms r ON r.id = b.room_id INNER JOIN wards w ON w.id = r.ward_id WHERE b.id = ' . $a[1])->fetchColumn();
                $roomId = (int) $db->query('SELECT room_id FROM beds WHERE id = ' . $a[1])->fetchColumn();
                $db->prepare('UPDATE admissions SET ward_id = ?, room_id = ? WHERE id = ?')->execute([$wardId, $roomId, $admId]);
            }
            // Mark one bed as maintenance.
            if (count($bedIds) > 8) { $db->prepare('UPDATE beds SET status = "maintenance", notes = "AC repair scheduled" WHERE id = ?')->execute([$bedIds[8]]); }
            // Mark one bed as cleaning.
            if (count($bedIds) > 10) { $db->prepare('UPDATE beds SET status = "cleaning" WHERE id = ?')->execute([$bedIds[10]]); }
        }

        // Notifications.
        $notifTypes = [
            [null, 'announcement', 'System maintenance scheduled', 'MediCore will undergo scheduled maintenance on Sunday 2-4 AM. Please save your work.', 'medium', '/admin/settings'],
            [$adminId, 'lab_pending', 'Lab results pending', '3 lab orders are awaiting result entry in the work queue.', 'high', '/admin/laboratory'],
            [$adminId, 'low_stock', 'Low stock alert', 'Metformin 500mg stock is below reorder level (15 units, reorder at 80).', 'medium', '/admin/pharmacy'],
            [$adminId, 'expiry_alert', 'Medicine expiring soon', 'Batch NAPA-2024-EXP expired. Please remove from stock.', 'critical', '/admin/pharmacy'],
            [$adminId, 'appointment_reminder', 'Appointment reminder', 'Dr. Sarah Chen has 4 appointments scheduled for today.', 'low', '/admin/appointments'],
        ];
        $nInsert = $db->prepare('INSERT INTO notifications (user_id, type, title, message, priority, action_url, is_read) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($notifTypes as $n) {
            $nInsert->execute([$n[0], $n[1], $n[2], $n[3], $n[4], $n[5], $n[1] === 'expiry_alert' ? 0 : 1]);
        }
    }
}
