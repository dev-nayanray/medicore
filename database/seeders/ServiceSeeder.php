<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Realistic hospital services with pricing across all categories.
 */
final class ServiceSeeder extends Seeder
{
    public static function label(): string { return 'Hospital Services'; }
    public static function order(): int { return 140; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'services');

        $services = [
            // [category, name, price, description]
            ['consultation', 'General Consultation', 500.00, 'Routine outpatient consultation'],
            ['consultation', 'Senior Consultant Visit', 1500.00, 'Specialist consultation with senior doctor'],
            ['consultation', 'Follow-up Consultation', 300.00, 'Follow-up visit within 14 days'],
            ['consultation', 'Telemedicine Consultation', 400.00, 'Video consultation'],
            ['consultation', 'Emergency Consultation', 1000.00, 'Emergency department assessment'],
            ['consultation', 'Antenatal Check-up', 600.00, 'Routine antenatal examination'],

            ['laboratory', 'Complete Blood Count (CBC)', 250.00, 'Full blood count panel'],
            ['laboratory', 'Blood Glucose (Fasting)', 100.00, 'Fasting blood sugar'],
            ['laboratory', 'HbA1c', 400.00, 'Glycated hemoglobin — 3-month average'],
            ['laboratory', 'Lipid Profile', 350.00, 'Cholesterol panel'],
            ['laboratory', 'Liver Function Test (LFT)', 450.00, 'Liver enzyme panel'],
            ['laboratory', 'Kidney Function Test (KFT)', 450.00, 'Renal function panel'],
            ['laboratory', 'Thyroid Profile (T3, T4, TSH)', 600.00, 'Thyroid hormone panel'],
            ['laboratory', 'Urine Routine', 80.00, 'Urinalysis'],
            ['laboratory', 'Stool Routine', 80.00, 'Stool analysis'],
            ['laboratory', 'ECG (12-lead)', 300.00, 'Electrocardiogram'],
            ['laboratory', 'Chest X-ray', 500.00, 'PA chest radiograph'],
            ['laboratory', 'Ultrasound (Abdomen)', 800.00, 'Abdominal sonography'],
            ['laboratory', 'MRI (Brain)', 4000.00, 'Magnetic resonance imaging — brain'],
            ['laboratory', 'CT Scan (Thorax)', 3500.00, 'Computed tomography — chest'],

            ['procedure', 'Wound Dressing', 200.00, 'Simple wound care'],
            ['procedure', 'Suture Removal', 100.00, 'Staple/suture removal'],
            ['procedure', 'IV Cannulation', 150.00, 'IV line insertion'],
            ['procedure', 'Nebulization', 200.00, 'Nebulized medication'],
            ['procedure', 'Intramuscular Injection', 100.00, 'IM injection administration'],
            ['procedure', 'Minor Surgery (Local)', 2500.00, 'Minor surgical procedure under local anesthesia'],
            ['procedure', 'Appendectomy', 25000.00, 'Laparoscopic appendectomy'],
            ['procedure', 'Cesarean Section', 35000.00, 'Elective C-section'],

            ['medicine', 'Paracetamol 500mg (strip)', 30.00, 'Analgesic — 10 tablets'],
            ['medicine', 'Amoxicillin 500mg (strip)', 80.00, 'Antibiotic — 10 capsules'],
            ['medicine', 'Omeprazole 20mg (strip)', 120.00, 'PPI — 14 capsules'],
            ['medicine', 'Metformin 500mg (strip)', 45.00, 'Antidiabetic — 10 tablets'],
            ['medicine', 'Salbutamol Inhaler', 200.00, 'Bronchodilator inhaler'],
            ['medicine', 'IV Fluid (Normal Saline 1L)', 150.00, 'Intravenous fluid'],

            ['admission', 'General Ward (per day)', 2000.00, 'General ward bed + meals + nursing'],
            ['admission', 'Semi-Private Room (per day)', 3500.00, 'Semi-private room with amenities'],
            ['admission', 'Private Room (per day)', 6000.00, 'Private AC room'],
            ['admission', 'ICU (per day)', 12000.00, 'Intensive care unit'],
            ['admission', 'NICU (per day)', 8000.00, 'Neonatal intensive care'],

            ['other', 'Ambulance (Local)', 1000.00, 'Local ambulance transfer'],
            ['other', 'Ambulance (Long distance)', 3000.00, 'Inter-city ambulance'],
            ['other', 'Medical Certificate', 100.00, 'Medical fitness certificate'],
        ];

        $insert = $db->prepare('INSERT INTO services (name, category, description, price, is_active) VALUES (?, ?, ?, ?, 1)');
        foreach ($services as $svc) {
            [$cat, $name, $price, $desc] = $svc;
            $insert->execute([$name, $cat, $desc, $price]);
        }
    }
}
