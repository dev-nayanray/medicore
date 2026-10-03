<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Hospital departments with realistic clinical units. One archived department
 * demonstrates preserved member references.
 */
final class DepartmentSeeder extends Seeder
{
    public static function label(): string { return 'Departments'; }
    public static function order(): int { return 150; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'departments');

        $departments = [
            ['Cardiology',    'Diagnosis and treatment of heart and cardiovascular diseases. Cath lab, echo, stress testing, and interventional cardiology.', 'Tower A · 3rd Floor', '+880 2 555 1101', 'cardiology@medicore.test'],
            ['Neurology',     'Management of stroke, epilepsy, movement disorders and neuromuscular diseases. EEG and EMG facilities on-site.', 'Tower A · 4th Floor', '+880 2 555 1102', 'neurology@medicore.test'],
            ['Pediatrics',    'Comprehensive child health: immunisation, neonatal care, and developmental follow-up.', 'Tower B · 2nd Floor', '+880 2 555 1103', 'pediatrics@medicore.test'],
            ['Orthopedics',   'Trauma and elective orthopedic surgery, sports injury rehabilitation, joint replacement programme.', 'Tower C · Ground', '+880 2 555 1104', 'ortho@medicore.test'],
            ['Obstetrics & Gynecology', 'Antenatal care, normal and caesarean delivery, gynae oncology screening.', 'Tower B · 1st Floor', '+880 2 555 1105', 'obs-gyn@medicore.test'],
            ['General Surgery', 'Elective and emergency general surgical procedures, day-care surgery, and laparoscopic cholecystectomy.', 'Tower C · 1st Floor', '+880 2 555 1106', 'surgery@medicore.test'],
            ['Internal Medicine', 'Adult general medicine, inpatient care, and pre-operative medical clearance.', 'Tower A · 2nd Floor', '+880 2 555 1107', 'medicine@medicore.test'],
            ['Dermatology',  'Skin, hair and nail disorders, cosmetic dermatology, and minor procedures.', 'Tower B · 3rd Floor', '+880 2 555 1108', 'derm@medicore.test'],
        ];

        $insert = $db->prepare(
            'INSERT INTO departments (name, slug, description, location, phone, email, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
        );

        $now = strtotime('-2 hours');
        foreach ($departments as $i => $dept) {
            [$name, $desc, $loc, $phone, $email] = $dept;
            $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '');
            $slug = trim($slug, '-');
            $createdAt = date('Y-m-d H:i:s', $now - random_int(120, 220) * 86400);
            $insert->execute([$name, $slug, $desc, $loc, $phone, $email, $createdAt]);
        }
    }
}
