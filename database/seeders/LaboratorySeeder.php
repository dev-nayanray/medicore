<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Seeds lab tests, categories, and a few lab orders across the workflow
 * (ordered, collected, resulted, verified, released).
 */
final class LaboratorySeeder extends Seeder
{
    public static function label(): string { return 'Laboratory'; }
    public static function order(): int { return 178; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'lab_critical_alerts', 'lab_order_items', 'lab_orders', 'lab_tests');

        $tests = [
            ['Complete Blood Count', 'Hematology', 'Full blood count panel', 250.00, 'Blood', 4],
            ['Blood Glucose (Fasting)', 'Biochemistry', 'Fasting blood sugar', 100.00, 'Blood', 2],
            ['HbA1c', 'Biochemistry', 'Glycated hemoglobin', 400.00, 'Blood', 6],
            ['Lipid Profile', 'Biochemistry', 'Cholesterol panel', 350.00, 'Blood', 6],
            ['Liver Function Test', 'Biochemistry', 'Liver enzyme panel', 450.00, 'Blood', 6],
            ['Kidney Function Test', 'Biochemistry', 'Renal function panel', 450.00, 'Blood', 6],
            ['Thyroid Profile', 'Endocrinology', 'T3 T4 TSH', 600.00, 'Blood', 8],
            ['Urine Routine', 'Urinalysis', 'Urinalysis', 80.00, 'Urine', 3],
            ['Stool Routine', 'Microbiology', 'Stool analysis', 80.00, 'Stool', 3],
            ['ECG', 'Cardiology', 'Electrocardiogram', 300.00, 'N/A', 1],
            ['Chest X-ray', 'Radiology', 'PA chest radiograph', 500.00, 'N/A', 2],
            ['Ultrasound Abdomen', 'Radiology', 'Abdominal sonography', 800.00, 'N/A', 4],
            ['COVID-19 PCR', 'Microbiology', 'SARS-CoV-2 RT-PCR', 1500.00, 'Nasal swab', 24],
            ['Dengue NS1 Antigen', 'Microbiology', 'Dengue early detection', 600.00, 'Blood', 6],
            ['Troponin I', 'Cardiology', 'Cardiac troponin', 800.00, 'Blood', 3],
        ];
        $tInsert = $db->prepare('INSERT INTO lab_tests (name, category, description, price, sample_type, turnaround_hours, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
        foreach ($tests as $t) { $tInsert->execute($t); }
    }
}
