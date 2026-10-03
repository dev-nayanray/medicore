<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Doctor profiles for the seeded doctor-role users, with realistic
 * specializations, qualifications, weekly schedules, and leave records.
 */
final class DoctorSeeder extends Seeder
{
    public static function label(): string { return 'Doctors'; }
    public static function order(): int { return 160; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'doctor_leaves', 'doctor_schedules', 'doctors');

        // Resolve the doctor user accounts seeded by UserSeeder.
        $users = [];
        foreach ($db->query('SELECT id, email, name FROM users')->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $users[$u['email']] = $u;
        }

        // Resolve department ids by slug.
        $depts = [];
        foreach ($db->query('SELECT id, slug FROM departments')->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $depts[$d['slug']] = (int) $d['id'];
        }

        $year = (int) date('Y');
        $now = strtotime('-2 hours');

        $doctors = [
            [
                'email' => 'sarah.chen@medicore.test',
                'specialization' => 'Cardiology',
                'qualifications' => 'MBBS, MD (Cardiology), Fellowship Interventional Cardiology',
                'reg' => 'BMC-2008-CC-4451',
                'bio' => 'Interventional cardiologist with 15+ years in cath lab procedures. Leads the chest-pain fast-track pathway.',
                'fee' => '1500.00',
                'dept_slug' => 'cardiology',
                'room' => 'OPD-Cabin 3',
                'hired' => '2010-08-15',
                'schedule' => [
                    [1, '09:00', '13:00', 20, 'OPD-1'],
                    [3, '09:00', '13:00', 20, 'OPD-1'],
                    [5, '14:00', '17:00', 15, 'Cath Lab'],
                ],
            ],
            [
                'email' => 'imran@medicore.test',
                'specialization' => 'Neurology',
                'qualifications' => 'MBBS, FCPS (Neurology), Stroke Fellowship (Singapore)',
                'reg' => 'BMC-2011-NE-2278',
                'bio' => 'Stroke specialist and epilepsy management. Runs the weekly neuro clinic on Tuesdays and Thursdays.',
                'fee' => '1200.00',
                'dept_slug' => 'neurology',
                'room' => 'OPD-Cabin 7',
                'hired' => '2013-03-01',
                'schedule' => [
                    [2, '10:00', '14:00', 18, 'OPD-2'],
                    [4, '10:00', '14:00', 18, 'OPD-2'],
                    [6, '09:00', '12:00', 10, 'EEG Room'],
                ],
            ],
        ];

        $insert = $db->prepare(
            'INSERT INTO doctors (user_id, doctor_code, specialization, qualifications, registration_number,
                bio, consultation_fee, department_id, room_number, status, hired_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $doctorIds = [];
        foreach ($doctors as $i => $doc) {
            $user = $users[$doc['email']] ?? null;
            if ($user === null) {
                continue;
            }
            $code = sprintf('MCD-%s-%05d', $year, $i + 1);
            $deptId = $depts[$doc['dept_slug']] ?? null;
            $createdAt = date('Y-m-d H:i:s', $now - random_int(60, 120) * 86400);
            $insert->execute([
                (int) $user['id'], $code, $doc['specialization'], $doc['qualifications'],
                $doc['reg'], $doc['bio'], $doc['fee'], $deptId, $doc['room'], 'active', $doc['hired'], $createdAt,
            ]);
            $doctorIds[$doc['email']] = (int) $db->lastInsertId();
        }

        // Schedules.
        $sched = $db->prepare(
            'INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_patients, room, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        foreach ($doctors as $doc) {
            $doctorId = $doctorIds[$doc['email']] ?? null;
            if ($doctorId === null) {
                continue;
            }
            foreach ($doc['schedule'] as [$day, $start, $end, $max, $room]) {
                $sched->execute([$doctorId, $day, $start, $end, $max, $room]);
            }
        }

        // One approved leave for Dr. Sarah (demonstrates on_leave transition).
        $sarahId = $doctorIds['sarah.chen@medicore.test'] ?? null;
        $adminId = $users['admin@medicore.test']['id'] ?? null;
        if ($sarahId !== null && $adminId !== null) {
            $start = date('Y-m-d', strtotime('+10 days'));
            $end = date('Y-m-d', strtotime('+14 days'));
            $db->prepare(
                'INSERT INTO doctor_leaves (doctor_id, start_date, end_date, reason, status, approved_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$sarahId, $start, $end, 'Annual conference — ESC Acute Cardiovascular Care', 'approved', $adminId]);
            // Mark the doctor on leave for the upcoming window.
            $db->prepare("UPDATE doctors SET status = 'on_leave' WHERE id = ?")->execute([$sarahId]);
        }
    }
}
