<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Realistic demo patients + encounter history. One patient is archived
 * to demonstrate preserved history.
 */
final class PatientSeeder extends Seeder
{
    public static function label(): string { return 'Patients'; }
    public static function order(): int { return 155; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'patient_documents', 'patient_visits', 'patients');

        // Resolve staff user ids for registrar/doctor references.
        $users = [];
        foreach ($db->query('SELECT id, email FROM users')->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $users[$u['email']] = (int) $u['id'];
        }
        $registrar = $users['rahim@medicore.test'] ?? null;
        $doctors = [$users['sarah.chen@medicore.test'] ?? null, $users['imran@medicore.test'] ?? null];
        $nurse = $users['farhana@medicore.test'] ?? null;

        // first, last, gender, dob, blood, marital, nid, phone, email, city,
        // history, allergies, emergency(name/relation/phone), archived
        $patients = [
            ['Kamal', 'Hossain', 'male', '1978-03-12', 'B+', 'married', 'NID-1987-4410',
             '+880 1712 445510', 'kamal.h@example.test', 'Dhaka',
             'Type 2 diabetes (2019); appendectomy (2005)', 'Penicillin',
             ['Selina Hossain', 'Wife', '+880 1812 445511'], false],
            ['Nasrin', 'Akter', 'female', '1992-08-25', 'O+', 'single', null,
             '+880 1934 778120', 'nasrin.a@example.test', 'Chattogram',
             'Asthma since childhood', 'Dust, pollen',
             ['Mizan Akter', 'Father', '+880 1734 778121'], false],
            ['Abdullah', 'Al Mamun', 'male', '1965-01-30', 'A+', 'married', 'NID-1974-0912',
             '+880 1551 220301', null, 'Dhaka',
             'Hypertension; mild arthritis', 'None known',
             ['Rahima Mamun', 'Wife', '+880 1811 220302'], false],
            ['Taslima', 'Begum', 'female', '1987-11-04', 'AB+', 'married', 'NID-1996-3320',
             '+880 1677 909417', 'taslima.b@example.test', 'Sylhet',
             'Two normal deliveries (2015, 2018)', 'Sulfa drugs',
             ['Jakir Begum', 'Husband', '+880 1977 909418'], false],
            ['Rafiq', 'Islam', 'male', '2001-06-18', 'O-', 'single', null,
             '+880 1899 515662', null, 'Khulna',
             'Football knee injury (2022)', 'None known',
             ['Shahida Islam', 'Mother', '+880 1699 515663'], false],
            ['Shirin', 'Sultana', 'female', '1970-09-09', 'B-', 'widowed', 'NID-1979-7701',
             '+880 1745 330287', 'shirin.s@example.test', 'Dhaka',
             'Hypothyroidism (2011); cholecystectomy (2016)', 'Iodine contrast (rash)',
             ['Farhana Sultana', 'Daughter', '+880 1845 330288'], false],
            ['Mehedi', 'Hasan', 'male', '1995-02-14', 'A-', 'single', 'NID-2004-5583',
             '+880 1622 808974', 'mehedi.h@example.test', 'Rajshahi',
             'None significant', 'None known',
             ['Ruma Hasan', 'Mother', '+880 1922 808975'], false],
            ['Farzana', 'Rahman', 'female', '2015-07-21', 'O+', null, null,
             '+880 1533 117799 (guardian)', null, 'Dhaka',
             'Recurrent tonsillitis', 'Amoxicillin rash',
             ['Kamrul Rahman', 'Father', '+880 1733 117800'], false],
            ['Jahangir', 'Alam', 'male', '1958-12-02', 'AB-', 'married', 'NID-1967-2045',
             '+880 1719 664230', null, 'Barishal',
             'COPD; former smoker (quit 2015)', 'None known',
             ['Roushan Alam', 'Wife', '+880 1819 664231'], false],
            ['Sabbir', 'Ahmed', 'male', '1989-04-27', 'B+', 'married', 'NID-1998-8817',
             '+880 1886 254708', 'sabbir.a@example.test', 'Dhaka',
             'GERD (2020)', 'Aspirin',
             ['Nusrat Ahmed', 'Wife', '+880 1686 254709'], false],
            ['Ruma', 'Khatun', 'female', '1982-10-16', 'O+', 'divorced', null,
             '+880 1758 403361', null, 'Rangpur',
             'Iron-deficiency anemia', 'None known',
             ['Anwar Khatun', 'Brother', '+880 1858 403362'], false],
            ['Arif', 'Chowdhury', 'male', '1974-05-08', 'A+', 'married', 'NID-1983-6129',
             '+880 1930 771543', 'arif.c@example.test', 'Dhaka',
             'Migraine; LASIK (2018)', 'Codeine',
             ['Shila Chowdhury', 'Wife', '+880 1630 771544'], true], // archived — history preserved
        ];

        $insert = $db->prepare(
            'INSERT INTO patients (patient_code, first_name, last_name, gender, date_of_birth, blood_group,
                marital_status, national_id, phone, email, city, country, medical_history, allergies,
                emergency_contact_name, emergency_contact_relation, emergency_contact_phone,
                registered_by, archived_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Bangladesh", ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $year = (int) date('Y');
        $sequence = 0;
        $patientIds = [];
        $now = strtotime('-2 hours');

        foreach ($patients as $i => $p) {
            [$first, $last, $gender, $dob, $blood, $marital, $nid, $phone, $email, $city,
                $history, $allergies, $emergency, $archived] = $p;

            $sequence++;
            $code = sprintf('MCP-%s-%05d', $year, $sequence);
            $createdAt = date('Y-m-d H:i:s', $now - random_int(5, 160) * 86400);
            $archivedAt = $archived ? date('Y-m-d H:i:s', $now - 10 * 86400) : null;

            $insert->execute([
                $code, $first, $last, $gender, $dob, $blood, $marital, $nid, $phone, $email, $city,
                $history, $allergies,
                $emergency[0], $emergency[2], $emergency[1],
                $registrar, $archivedAt, $createdAt,
            ]);
            $patientIds[] = (int) $db->lastInsertId();
        }

        // --- Encounter history -------------------------------------------------
        $visit = $db->prepare(
            'INSERT INTO patient_visits (patient_id, visited_at, visit_type, doctor_id, chief_complaint,
                diagnosis, notes, status, recorded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $visitPlan = [
            // patient index (0-based) => [days ago, type, doctorIdx, complaint, diagnosis, notes]
            [0, 40, 'follow-up', 0, 'Routine diabetes review', 'T2DM, controlled (HbA1c 6.8)', 'Continue metformin 500mg BD. Dietician referral given.', 'completed'],
            [0, 6, 'outpatient', 0, 'Increased thirst and fatigue', 'T2DM flare', 'Fasting glucose 11.2 mmol/L — dose review scheduled.', 'completed'],
            [1, 25, 'emergency', 1, 'Acute breathing difficulty', 'Asthma exacerbation', 'Nebulised, observed 4h, discharged stable.', 'completed'],
            [1, 3, 'follow-up', 1, 'Asthma review after ER visit', 'Improving', 'Inhaler technique corrected.', 'completed'],
            [2, 60, 'outpatient', 0, 'Headache and dizziness', 'Hypertension stage 1', 'Started amlodipine 5mg OD.', 'completed'],
            [2, 2, 'telemedicine', 0, 'Medication refill consultation', 'Stable', 'Repeat prescription issued.', 'completed'],
            [3, 15, 'outpatient', 0, 'Antenatal check-up', 'Gravida 3, 24 weeks', 'Normal progression.', 'completed'],
            [4, 90, 'emergency', 1, 'Knee injury during football', 'ACL sprain grade II', 'MRI ordered, physio started.', 'completed'],
            [4, 30, 'follow-up', 1, 'Knee physio progress review', 'Improving', 'Continue physio 4 more weeks.', 'completed'],
            [5, 120, 'outpatient', 0, 'Fatigue and weight gain', 'Hypothyroidism', 'TSH 12.4 — levothyroxine adjusted.', 'completed'],
            [6, 20, 'outpatient', 1, 'Annual health check', 'Healthy', 'Bloods within range.', 'completed'],
            [7, 12, 'outpatient', 0, 'Sore throat and fever', 'Tonsillitis (viral)', 'Amoxicillin AVOIDED — documented allergy. Supportive care.', 'completed'],
            [8, 55, 'inpatient', 1, 'Worsening breathlessness (3 days)', 'COPD exacerbation', 'Admitted 4 days, steroids + O2, discharged stable.', 'completed'],
            [9, 8, 'outpatient', 0, 'Burning chest pain after meals', 'GERD', 'PPI course, lifestyle advice.', 'completed'],
            [10, 45, 'outpatient', 0, 'Weakness and pallor', 'Iron-deficiency anemia', 'Ferrous sulfate started, diet counselling.', 'completed'],
            [11, 200, 'outpatient', 0, 'Blurred vision', 'Refractive error', 'Prescribed glasses.', 'completed'],
            [11, 180, 'follow-up', 1, 'Vision check with new glasses', 'Resolved', 'Archived before this review — visit retained.', 'completed'],
            [3, -3, 'outpatient', 0, 'Scheduled antenatal follow-up', null, null, 'scheduled'],
        ];

        foreach ($visitPlan as [$idx, $daysAgo, $type, $doctorIdx, $complaint, $diagnosis, $notes, $status]) {
            $when = $daysAgo >= 0
                ? date('Y-m-d H:i:s', $now - $daysAgo * 86400 + random_int(0, 9) * 3600)
                : date('Y-m-d H:i:s', $now + (-$daysAgo) * 86400);
            $visit->execute([
                $patientIds[$idx], $when, $type, $doctors[$doctorIdx], $complaint,
                $diagnosis, $notes, $status, $nurse ?? $registrar, $when,
            ]);
        }
    }
}
