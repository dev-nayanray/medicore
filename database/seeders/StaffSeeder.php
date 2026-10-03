<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Staff employment profiles for the non-doctor seeded users (receptionist,
 * nurse, pharmacist, lab-technician, accountant, administrator). Each gets
 * a few upcoming shifts, a month of attendance, and one leave record.
 */
final class StaffSeeder extends Seeder
{
    public static function label(): string { return 'Staff'; }
    public static function order(): int { return 165; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'staff_leaves', 'staff_attendance', 'staff_shifts', 'staff_profiles');

        $users = [];
        foreach ($db->query('SELECT id, email, name FROM users')->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $users[$u['email']] = $u;
        }

        $depts = [];
        foreach ($db->query('SELECT id, slug, name FROM departments')->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $depts[$d['slug']] = ['id' => (int) $d['id'], 'name' => $d['name']];
        }

        // email => [job title, dept slug, employment type, hire_date (relative days ago)]
        $staff = [
            ['rahim@medicore.test',    'Front Desk Receptionist', 'internal-medicine', 'full_time', -420],
            ['farhana@medicore.test',  'Senior Nurse',            'pediatrics',       'full_time', -300],
            ['nusrat@medicore.test',   'Pharmacist',               'general-surgery',  'full_time', -250],
            ['tanvir@medicore.test',   'Lab Technician',          'internal-medicine','full_time', -200],
            ['mahin@medicore.test',    'Accountant',              'internal-medicine','full_time', -180],
            ['omar@medicore.test',     'Operations Manager',      'general-surgery',  'full_time', -500],
        ];

        $year = (int) date('Y');
        $now = strtotime('-2 hours');

        $insert = $db->prepare(
            'INSERT INTO staff_profiles (user_id, employee_id, job_title, department_id, employment_type, hire_date, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $staffIds = [];
        $seq = 0;
        foreach ($staff as [$email, $title, $deptSlug, $empType, $hireDays]) {
            $user = $users[$email] ?? null;
            if ($user === null) {
                continue;
            }
            $seq++;
            $code = sprintf('MCS-%s-%05d', $year, $seq);
            $deptId = $depts[$deptSlug]['id'] ?? null;
            $hireDate = date('Y-m-d', $now + $hireDays * 86400);
            $createdAt = date('Y-m-d H:i:s', $now - random_int(30, 90) * 86400);
            $insert->execute([
                (int) $user['id'], $code, $title, $deptId, $empType, $hireDate,
                $email === 'omar@medicore.test' ? 'inactive' : 'active', $createdAt,
            ]);
            $staffIds[$email] = (int) $db->lastInsertId();
        }

        // Shifts: 5 upcoming per active staff member.
        $shiftInsert = $db->prepare(
            'INSERT INTO staff_shifts (staff_id, shift_date, start_time, end_time, shift_type, department_id, notes, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $adminId = $users['admin@medicore.test']['id'] ?? null;
        foreach ($staffIds as $email => $staffId) {
            $deptSlug = array_column($staff, 2, 0)[$email] ?? null;
            $deptId = $depts[$deptSlug]['id'] ?? null;
            for ($d = 1; $d <= 5; $d++) {
                $date = date('Y-m-d', strtotime("+{$d} days"));
                $type = $d % 3 === 0 ? 'night' : ($d % 2 === 0 ? 'evening' : 'morning');
                $start = $type === 'night' ? '22:00' : ($type === 'evening' ? '14:00' : '06:00');
                $end   = $type === 'night' ? '06:00' : ($type === 'evening' ? '22:00' : '14:00');
                $shiftInsert->execute([
                    $staffId, $date, $start, $end, $type, $deptId,
                    $d === 1 ? 'On-call coverage for OPD surge' : null,
                    $adminId, date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Attendance: last 20 weekdays for each active staff.
        $attInsert = $db->prepare(
            'INSERT INTO staff_attendance (staff_id, date, check_in, check_out, status, notes, recorded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $weekdays = [];
        $day = 0;
        while (count($weekdays) < 20) {
            $date = date('Y-m-d', strtotime("-{$day} days"));
            $dow = (int) date('w', strtotime($date));
            if ($dow !== 0 && $dow !== 6) {
                $weekdays[] = $date;
            }
            $day++;
        }
        foreach ($staffIds as $email => $staffId) {
            if ($email === 'omar@medicore.test') {
                continue; // inactive — skip
            }
            foreach ($weekdays as $date) {
                $late = random_int(1, 10) > 8;
                $absent = random_int(1, 20) > 18;
                $onLeave = random_int(1, 30) > 28;
                $status = $onLeave ? 'leave' : ($absent ? 'absent' : ($late ? 'late' : 'present'));
                $checkIn = $absent || $onLeave ? null : ($late ? '09:25:00' : '09:05:00');
                $checkOut = $absent || $onLeave ? null : '17:10:00';
                $attInsert->execute([
                    $staffId, $date, $checkIn, $checkOut, $status,
                    $status === 'absent' ? 'No notice' : null,
                    $adminId, date('Y-m-d H:i:s'),
                ]);
            }
        }

        // One pending + one approved leave for the receptionist.
        $rahimId = $staffIds['rahim@medicore.test'] ?? null;
        if ($rahimId !== null) {
            $db->prepare(
                'INSERT INTO staff_leaves (staff_id, leave_type, start_date, end_date, reason, status, approved_by, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $rahimId, 'casual', date('Y-m-d', strtotime('+5 days')), date('Y-m-d', strtotime('+6 days')),
                'Family event', 'pending', null,
            ]);
            $db->prepare(
                'INSERT INTO staff_leaves (staff_id, leave_type, start_date, end_date, reason, status, approved_by, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $rahimId, 'sick', date('Y-m-d', strtotime('-8 days')), date('Y-m-d', strtotime('-7 days')),
                'Flu — medical certificate attached', 'approved', $adminId,
            ]);
        }

        // One pending leave for the nurse.
        $farhanaId = $staffIds['farhana@medicore.test'] ?? null;
        if ($farhanaId !== null) {
            $db->prepare(
                'INSERT INTO staff_leaves (staff_id, leave_type, start_date, end_date, reason, status, approved_by, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $farhanaId, 'annual', date('Y-m-d', strtotime('+20 days')), date('Y-m-d', strtotime('+27 days')),
                'Planned vacation', 'pending', null,
            ]);
        }
    }
}
