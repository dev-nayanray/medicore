<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Realistic appointments across all statuses + walk-ins. Spreads across
 * today and the last 7 days so the calendar, queue dashboard, and
 * reports all have meaningful data on first load.
 */
final class AppointmentSeeder extends Seeder
{
    public static function label(): string { return 'Appointments'; }
    public static function order(): int { return 170; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'appointment_reminders', 'appointments');

        // Resolve patient + doctor + department ids.
        $patients = $db->query('SELECT id, first_name, last_name, phone FROM patients WHERE archived_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $doctors = $db->query("SELECT doc.id, doc.user_id, doc.department_id, doc.doctor_code, doc.specialization FROM doctors doc WHERE doc.archived_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        $depts = $db->query("SELECT id, slug FROM departments WHERE archived_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        $registrar = (int) $db->query("SELECT id FROM users WHERE email = 'rahim@medicore.test'")->fetchColumn() ?: null;

        if (count($patients) < 4 || count($doctors) < 1) {
            return; // not enough base data to seed meaningfully
        }

        $year = (int) date('Y');
        $seq = 0;
        $now = time();

        $insert = $db->prepare(
            'INSERT INTO appointments
                (appointment_code, patient_id, doctor_id, department_id, appointment_date,
                 start_time, end_time, appointment_type, status, queue_token, reason, notes,
                 checked_in_at, consultation_started_at, completed_at, cancellation_reason, cancelled_by,
                 created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        // Helper: generate a code.
        $code = static function () use (&$seq, $year): string {
            $seq++;
            return sprintf('APT-%s-%05d', $year, $seq);
        };

        $doctor0 = (int) $doctors[0]['id'];
        $doctor1 = count($doctors) > 1 ? (int) $doctors[1]['id'] : $doctor0;
        $dept0 = (int) $doctors[0]['department_id'];
        $dept1 = count($depts) > 1 ? (int) array_column($depts, 'id', 'slug')['cardiology'] ?? $dept0 : $dept0;

        // --- TODAY: full lifecycle across the queue lanes ---
        $today = date('Y-m-d');

        // 09:00 — completed consultation (Dr Sarah / Cardiology).
        $insert->execute([
            $code(), (int) $patients[0]['id'], $doctor0, $dept0, $today,
            '09:00', '09:30', 'scheduled', 'completed', 'Q-001',
            'Routine diabetes follow-up', 'HbA1c reviewed; metformin continued.',
            date('Y-m-d 08:55:00'), date('Y-m-d 09:00:00'), date('Y-m-d 09:28:00'),
            null, null, $registrar, date('Y-m-d H:i:s', $now - 86400 * 2),
        ]);

        // 10:00 — in consultation right now.
        $insert->execute([
            $code(), (int) $patients[1]['id'], $doctor0, $dept0, $today,
            '10:00', '10:30', 'scheduled', 'in_consultation', 'Q-002',
            'Asthma review after ER visit', null,
            date('Y-m-d 09:50:00'), date('Y-m-d 10:02:00'), null,
            null, null, $registrar, date('Y-m-d H:i:s', $now - 86400),
        ]);

        // 10:30 — checked in, waiting.
        $insert->execute([
            $code(), (int) $patients[2]['id'], $doctor0, $dept0, $today,
            '10:30', '11:00', 'scheduled', 'checked_in', 'Q-003',
            'Headache and dizziness — BP check', null,
            date('Y-m-d 10:25:00'), null, null,
            null, null, $registrar, date('Y-m-d H:i:s', $now - 3600),
        ]);

        // 11:00 — confirmed (hasn't checked in yet).
        $insert->execute([
            $code(), (int) $patients[3]['id'], $doctor0, $dept0, $today,
            '11:00', '11:30', 'scheduled', 'confirmed', null,
            'Antenatal check-up', null, null, null, null,
            null, null, $registrar, date('Y-m-d H:i:s', $now - 7200),
        ]);

        // 11:30 — pending (not yet confirmed).
        $insert->execute([
            $code(), (int) $patients[4]['id'], $doctor0, $dept0, $today,
            '11:30', '12:00', 'follow_up', 'pending', null,
            'Knee physio progress review', null, null, null, null,
            null, null, $registrar, date('Y-m-d H:i:s', $now - 1800),
        ]);

        // 14:00 — walk-in (auto checked-in with queue token).
        $insert->execute([
            $code(), (int) $patients[5]['id'], $doctor1, (int) ($doctors[1]['department_id'] ?? $dept0), $today,
            '14:00', '14:30', 'walk_in', 'checked_in', 'Q-004',
            'Acute sore throat', null,
            date('Y-m-d 13:40:00'), null, null,
            null, null, $registrar, date('Y-m-d H:i:s', $now - 1200),
        ]);

        // 14:30 — cancelled today.
        $insert->execute([
            $code(), (int) $patients[6]['id'], $doctor0, $dept0, $today,
            '14:30', '15:00', 'scheduled', 'cancelled', null,
            'Medication refill', null, null, null, null,
            'Patient called to cancel — rescheduled to next week.', $registrar,
            $registrar, date('Y-m-d H:i:s', $now - 5400),
        ]);

        // 09:00 — no-show (was scheduled, didn't show).
        $insert->execute([
            $code(), (int) $patients[7]['id'], $doctor1, (int) ($doctors[1]['department_id'] ?? $dept0), $today,
            '09:00', '09:30', 'scheduled', 'no_show', null,
            'Blurred vision — refractive check', null, null, null, null,
            null, null, $registrar, date('Y-m-d H:i:s', $now - 86400),
        ]);

        // --- PAST 7 DAYS: historical volume for charts/reports ---
        $pastReasons = ['Fever and body ache','Blood pressure check','Skin rash','Back pain','Routine check-up','Lab result review','Vaccination','Chest X-ray follow-up','Migraine','Stomach pain'];
        for ($d = 1; $d <= 7; $d++) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $count = random_int(2, 4);
            for ($i = 0; $i < $count; $i++) {
                $p = $patients[array_rand($patients)];
                $doc = $doctors[array_rand($doctors)];
                $hour = 9 + $i * 2;
                $start = sprintf('%02d:00', $hour);
                $end = sprintf('%02d:30', $hour);
                $statuses = ['completed', 'completed', 'completed', 'cancelled', 'no_show', 'completed'];
                $status = $statuses[array_rand($statuses)];

                $checkedIn = $status === 'completed' || $status === 'in_consultation' ? "{$date} {$start}:00" : null;
                $consultStart = $status === 'completed' || $status === 'in_consultation' ? "{$date} {$start}:02" : null;
                $completed = $status === 'completed' ? "{$date} {$end}:00" : null;

                $insert->execute([
                    $code(), (int) $p['id'], (int) $doc['id'], (int) $doc['department_id'], $date,
                    $start, $end, 'scheduled', $status, null,
                    $pastReasons[array_rand($pastReasons)], null,
                    $checkedIn, $consultStart, $completed,
                    $status === 'cancelled' ? 'Patient unavailable' : null,
                    $status === 'cancelled' ? $registrar : null,
                    $registrar, date('Y-m-d H:i:s', $now - $d * 86400 - $i * 3600),
                ]);
            }
        }

        // --- FUTURE: a couple of confirmed appointments for tomorrow ---
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $insert->execute([
            $code(), (int) $patients[0]['id'], $doctor0, $dept0, $tomorrow,
            '10:00', '10:30', 'follow_up', 'confirmed', null,
            'Diabetes 3-month review', null, null, null, null,
            null, null, $registrar, date('Y-m-d H:i:s'),
        ]);
        $insert->execute([
            $code(), (int) $patients[3]['id'], $doctor1, (int) ($doctors[1]['department_id'] ?? $dept0), $tomorrow,
            '11:00', '11:30', 'telemedicine', 'pending', null,
            'Telehealth medication review', null, null, null, null,
            null, null, $registrar, date('Y-m-d H:i:s'),
        ]);
    }
}
