<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Comprehensive demo data seeder — populates the database with 200+ users,
 * 200+ patients, 300+ appointments, 150+ consultations, 200+ invoices,
 * 100+ lab orders, 75+ admissions, 100+ notifications, and audit logs.
 *
 * Idempotent: truncates all demo tables and re-inserts fresh data on each run.
 * All demo users share the password "Staff@12345" (admin uses "Admin@12345").
 *
 * Run:  php console demo:data
 * Or:   php console seed  (included in the standard seeder chain)
 */
final class DemoDataSeeder extends Seeder
{
    public static function label(): string { return 'Comprehensive Demo Data'; }
    public static function order(): int { return 200; }

    // Data arrays built during run()
    private static array $roleIds = [];
    private static array $userIds = [];
    private static array $patientIds = [];
    private static array $doctorIds = [];
    private static array $departmentIds = [];
    private static array $serviceIds = [];
    private static array $bedIds = [];
    private static array $wardIds = [];
    private static array $medicineIds = [];
    private static array $labTestIds = [];

    public static function run(PDO $db): void
    {
        // Clean all demo data (preserve roles, permissions, settings, services, wards/rooms/beds, lab tests, medicines).
        self::truncate($db, 'notifications', 'demo_requests', 'lab_critical_alerts', 'lab_order_items', 'lab_orders',
            'medicine_returns', 'medicine_dispensing_items', 'medicine_dispensings',
            'medicine_purchase_items', 'medicine_purchases',
            'inventory_adjustments', 'inventory_purchase_items', 'inventory_purchases', 'stock_movements',
            'bed_transfers', 'admissions',
            'expenses', 'payments', 'invoice_items', 'invoices',
            'consultation_amendments', 'consultation_attachments', 'prescription_items', 'prescriptions', 'consultations',
            'appointment_reminders', 'appointments',
            'medicine_batches',  // will re-create
        );

        // Re-seed medicine batches (they were truncated above but medicines table is preserved)
        self::seedMedicineBatches($db);

        // Load reference data
        self::loadRoleIds($db);
        self::loadReferenceData($db);

        // Generate users (200+)
        self::seedUsers($db);

        // Generate patients (200+)
        self::seedPatients($db);

        // Generate appointments (300+)
        self::seedAppointments($db);

        // Generate consultations + prescriptions (150+)
        self::seedConsultations($db);

        // Generate billing (200+ invoices, 150+ payments, expenses)
        self::seedBilling($db);

        // Generate lab orders (100+)
        self::seedLabOrders($db);

        // Generate pharmacy dispensing (50+)
        self::seedPharmacyDispensing($db);

        // Generate admissions (75+)
        self::seedAdmissions($db);

        // Generate notifications (100+)
        self::seedNotifications($db);

        // Generate audit logs
        self::seedAuditLogs($db);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private static function loadRoleIds(PDO $db): void
    {
        foreach ($db->query('SELECT id, slug FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            self::$roleIds[$r['slug']] = (int) $r['id'];
        }
    }

    private static function loadReferenceData(PDO $db): void
    {
        // Departments
        foreach ($db->query('SELECT id FROM departments WHERE archived_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $d) {
            self::$departmentIds[] = (int) $d['id'];
        }
        // Services
        foreach ($db->query('SELECT id FROM services WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $s) {
            self::$serviceIds[] = (int) $s['id'];
        }
        // Existing doctors (from DoctorSeeder)
        foreach ($db->query('SELECT id, user_id, department_id FROM doctors WHERE archived_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $d) {
            self::$doctorIds[] = ['id' => (int) $d['id'], 'user_id' => (int) $d['user_id'], 'dept' => (int) ($d['department_id'] ?? 0)];
        }
        // Beds
        foreach ($db->query('SELECT id FROM beds')->fetchAll(PDO::FETCH_ASSOC) as $b) {
            self::$bedIds[] = (int) $b['id'];
        }
        // Wards
        foreach ($db->query('SELECT id FROM wards WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $w) {
            self::$wardIds[] = (int) $w['id'];
        }
        // Medicines
        foreach ($db->query('SELECT id FROM medicines WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $m) {
            self::$medicineIds[] = (int) $m['id'];
        }
        // Lab tests
        foreach ($db->query('SELECT id, price FROM lab_tests WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $t) {
            self::$labTestIds[] = ['id' => (int) $t['id'], 'price' => (float) $t['price']];
        }
    }

    private static function seedMedicineBatches(PDO $db): void
    {
        if (empty(self::$medicineIds)) return;
        $suppliers = $db->query('SELECT id FROM suppliers WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
        $supplierIds = array_map(fn($s) => (int) $s['id'], $suppliers);
        $insert = $db->prepare(
            'INSERT INTO medicine_batches (medicine_id, batch_number, expiry_date, quantity_received, quantity_remaining, cost_price, sell_price, supplier_id, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $batchSeq = 0;
        foreach (self::$medicineIds as $medId) {
            $batches = random_int(2, 4);
            for ($b = 0; $b < $batches; $b++) {
                $batchSeq++;
                $expiry = date('Y-m-d', strtotime('+' . random_int(-3, 24) . ' months'));
                $qty = random_int(20, 200);
                $cost = random_int(5, 500) / 10;
                $sell = round($cost * 1.25, 2);
                $supplierId = $supplierIds[array_rand($supplierIds)] ?? null;
                $insert->execute([$medId, 'BAT-' . date('Y') . '-' . str_pad((string)$batchSeq, 5, '0', STR_PAD_LEFT), $expiry, $qty, $qty, $cost, $sell, $supplierId, date('Y-m-d', strtotime('-' . random_int(1, 60) . ' days'))]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Users (200+)
    // ------------------------------------------------------------------
    private static function seedUsers(PDO $db): void
    {
        $hash = password_hash('Staff@12345', PASSWORD_BCRYPT, ['cost' => 10]);
        $adminHash = password_hash('Admin@12345', PASSWORD_BCRYPT, ['cost' => 10]);

        // Keep existing users (from UserSeeder) — add more
        $existingCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $needed = max(0, 210 - $existingCount);

        $distribution = [
            ['super-admin', 3], ['administrator', 8], ['doctor', 45], ['receptionist', 45],
            ['accountant', 30], ['pharmacist', 22], ['lab-technician', 22], ['nurse', 35],
        ];

        $insertUser = $db->prepare('INSERT INTO users (name, email, password_hash, phone, is_active, last_login_at, created_at) VALUES (?, ?, ?, ?, 1, ?, ?)');
        $insertRole = $db->prepare('INSERT IGNORE INTO role_user (user_id, role_id) VALUES (?, ?)');

        $firstNames = ['Aarav','Aisha','Akash','Arman','Ayesha','Bilal','Farhana','Imran','Jakir','Kamal','Lina','Mehedi','Nasrin','Omar','Priya','Rafi','Sabina','Tanvir','Yasmin','Zahir','Nadia','Rashid','Sadia','Tariq','Umme','Wahid','Xenia','Yusuf','Zara','Anwar','Belal','Champa','Dilara','Ehsan','Fariha','Gulshan','Habib','Irfan','Jui','Karim','Lubna','Mamun','Nilima','Onil','Parvez','Rukhsana','Shahid','Tahseen','Umar','Vidia'];
        $lastNames = ['Ahmed','Akter','Begum','Chowdhury','Hossain','Islam','Khan','Rahman','Sultana','Uddin','Alam','Das','Farooq','Hashem','Jahan','Karim','Miah','Nazrul','Patwary','Quayyum'];

        $generated = 0;
        foreach ($distribution as [$role, $count]) {
            for ($i = 0; $i < $count && $generated < $needed; $i++) {
                $generated++;
                $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
                $email = 'demo' . $generated . '@medicore.test';
                $phone = '+880 1' . random_int(3, 9) . random_int(100, 999) . ' ' . random_int(100000, 999999);
                $lastLogin = random_int(0, 10) > 3 ? date('Y-m-d H:i:s', strtotime('-' . random_int(0, 5) . ' days')) : null;
                $createdAt = date('Y-m-d H:i:s', strtotime('-' . random_int(30, 200) . ' days'));
                $pass = $role === 'super-admin' ? $adminHash : $hash;

                $insertUser->execute([$name, $email, $pass, $phone, $lastLogin, $createdAt]);
                $userId = (int) $db->lastInsertId();
                self::$userIds[] = $userId;

                if (isset(self::$roleIds[$role])) {
                    $insertRole->execute([$userId, self::$roleIds[$role]]);
                }

                // For doctors, create doctor profiles
                if ($role === 'doctor' && !empty(self::$departmentIds)) {
                    $deptId = self::$departmentIds[array_rand(self::$departmentIds)];
                    $specializations = ['Cardiology','Neurology','Orthopedics','Pediatrics','General Medicine','Dermatology','ENT','Gynecology','Urology','Psychiatry'];
                    $code = 'MCD-' . date('Y') . '-' . str_pad((string)($generated + 10), 5, '0', STR_PAD_LEFT);
                    $db->prepare('INSERT INTO doctors (user_id, doctor_code, specialization, consultation_fee, department_id, status, hired_at) VALUES (?, ?, ?, ?, ?, "active", ?)')
                        ->execute([$userId, $code, $specializations[array_rand($specializations)], random_int(500, 2000), $deptId, date('Y-m-d', strtotime('-' . random_int(60, 365) . ' days'))]);
                    $doctorId = (int) $db->lastInsertId();
                    self::$doctorIds[] = ['id' => $doctorId, 'user_id' => $userId, 'dept' => $deptId];

                    // Add weekly schedule
                    $days = [1, 2, 3, 4, 5]; // Mon-Fri
                    foreach ($days as $day) {
                        $db->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_patients, is_active) VALUES (?, ?, ?, ?, ?, 1)')
                            ->execute([$doctorId, $day, '09:00', '17:00', 20]);
                    }
                }

                // For non-doctor staff, create staff profiles
                if (in_array($role, ['receptionist', 'nurse', 'pharmacist', 'lab-technician', 'accountant', 'administrator']) && !empty(self::$departmentIds)) {
                    $deptId = self::$departmentIds[array_rand(self::$departmentIds)];
                    $titles = ['Junior Staff', 'Senior Staff', 'Officer', 'Supervisor', 'Coordinator'];
                    $code = 'MCS-' . date('Y') . '-' . str_pad((string)($generated + 10), 5, '0', STR_PAD_LEFT);
                    $empTypes = ['full_time', 'part_time', 'contract'];
                    $db->prepare('INSERT INTO staff_profiles (user_id, employee_id, job_title, department_id, employment_type, status) VALUES (?, ?, ?, ?, ?, "active")')
                        ->execute([$userId, $code, $titles[array_rand($titles)], $deptId, $empTypes[array_rand($empTypes)]]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Patients (200+)
    // ------------------------------------------------------------------
    private static function seedPatients(PDO $db): void
    {
        $existingCount = (int) $db->query('SELECT COUNT(*) FROM patients')->fetchColumn();
        $needed = max(0, 220 - $existingCount);

        $insert = $db->prepare(
            'INSERT INTO patients (patient_code, first_name, last_name, gender, date_of_birth, blood_group, marital_status, national_id, phone, email, address, city, country, emergency_contact_name, emergency_contact_phone, emergency_contact_relation, medical_history, allergies, registered_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Bangladesh", ?, ?, ?, ?, ?, 1, ?)'
        );

        $firstNames = ['Kamal','Nasrin','Abdullah','Taslima','Rafiq','Shirin','Mehedi','Farzana','Jahangir','Sabbir','Ruma','Arif','Selina','Belal','Champa','Dilara','Ehsan','Fariha','Gulshan','Habib','Irfan','Jui','Karim','Lubna','Mamun','Nilima','Onil','Parvez','Rukhsana','Shahid','Tahseen','Umar','Vidia','Wahid','Xenia','Yusuf','Zara','Anwar'];
        $lastNames = ['Ahmed','Akter','Begum','Chowdhury','Hossain','Islam','Khan','Rahman','Sultana','Uddin','Alam','Das','Farooq','Hashem','Jahan'];
        $cities = ['Dhaka','Chattogram','Sylhet','Khulna','Rajshahi','Barishal','Rangpur','Cumilla','Mymensingh','Narayanganj'];
        $bloodGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
        $genders = ['male','female'];
        $allergies = [null, null, null, 'Penicillin', 'Dust, pollen', 'Aspirin', 'Sulfa drugs', 'Amoxicillin rash', 'None known'];

        for ($i = 0; $i < $needed; $i++) {
            $code = 'MCP-' . date('Y') . '-' . str_pad((string)($existingCount + $i + 1), 5, '0', STR_PAD_LEFT);
            $fn = $firstNames[array_rand($firstNames)];
            $ln = $lastNames[array_rand($lastNames)];
            $gender = $genders[array_rand($genders)];
            $dob = date('Y-m-d', strtotime('-' . random_int(1, 85) . ' years'));
            $bg = $bloodGroups[array_rand($bloodGroups)];
            $phone = '+880 1' . random_int(3, 9) . random_int(100, 999) . ' ' . random_int(100000, 999999);
            $city = $cities[array_rand($cities)];
            $allergy = $allergies[array_rand($allergies)];
            $createdAt = date('Y-m-d H:i:s', strtotime('-' . random_int(1, 200) . ' days'));

            $insert->execute([
                $code, $fn, $ln, $gender, $dob, $bg, random_int(0, 1) ? 'married' : 'single',
                random_int(0, 1) ? 'NID-' . random_int(1000000, 9999999) : null,
                $phone, random_int(0, 1) ? strtolower($fn) . '.' . $i . '@example.test' : null,
                random_int(100, 999) . ' ' . $city, $city,
                $ln . ' ' . $fn, '+880 1' . random_int(3, 9) . random_int(100, 999) . ' ' . random_int(100000, 999999),
                random_int(0, 1) ? 'Spouse' : 'Parent',
                random_int(0, 1) ? 'Hypertension; Type 2 Diabetes' : null, $allergy, $createdAt
            ]);
            self::$patientIds[] = (int) $db->lastInsertId();
        }
    }

    // ------------------------------------------------------------------
    // Appointments (300+)
    // ------------------------------------------------------------------
    private static function seedAppointments(PDO $db): void
    {
        if (empty(self::$patientIds) || empty(self::$doctorIds)) return;

        $statuses = ['pending', 'confirmed', 'checked_in', 'in_consultation', 'completed', 'cancelled', 'no_show'];
        $statusWeights = [10, 15, 5, 5, 40, 15, 10];
        $types = ['scheduled', 'walk_in', 'follow_up', 'telemedicine'];

        $insert = $db->prepare(
            'INSERT INTO appointments (appointment_code, patient_id, doctor_id, department_id, appointment_date, start_time, end_time, appointment_type, status, queue_token, reason, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );

        $count = 0;
        $seq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(appointment_code, '-', -1) AS UNSIGNED)), 0) FROM appointments")->fetchColumn();
        $target = 350;

        while ($count < $target) {
            $count++;
            $seq++;
            $patientId = self::$patientIds[array_rand(self::$patientIds)];
            $doc = self::$doctorIds[array_rand(self::$doctorIds)];
            $daysAgo = random_int(-5, 30);
            $date = $daysAgo >= 0 ? date('Y-m-d', strtotime("-{$daysAgo} days")) : date('Y-m-d', strtotime('+' . abs($daysAgo) . ' days'));
            $hour = random_int(9, 16);
            $start = sprintf('%02d:00', $hour);
            $end = sprintf('%02d:30', $hour);

            // Weighted status
            $rand = random_int(1, 100);
            $cum = 0;
            $status = 'pending';
            foreach ($statusWeights as $i => $w) {
                $cum += $w;
                if ($rand <= $cum) { $status = $statuses[$i]; break; }
            }

            $type = $types[array_rand($types)];
            $reasons = ['Fever and body ache','Blood pressure check','Routine check-up','Chest pain','Headache','Skin rash','Follow-up review','Medication refill','Stomach pain','Breathing difficulty','Back pain','Eye irritation','Joint pain','Dizziness'];
            $token = $status !== 'pending' && $status !== 'cancelled' ? 'Q-' . str_pad((string)random_int(1, 99), 3, '0', STR_PAD_LEFT) : null;

            $code = 'APT-' . date('Y') . '-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
            try {
                $insert->execute([$code, $patientId, $doc['id'], $doc['dept'] ?: null, $date, $start, $end, $type, $status, $token, $reasons[array_rand($reasons)], date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), '1062')) {
                    continue; // Skip duplicate (doctor_id, date, start_time) — unique constraint
                }
                throw $e;
            }
        }
    }

    // ------------------------------------------------------------------
    // Consultations + Prescriptions (150+)
    // ------------------------------------------------------------------
    private static function seedConsultations(PDO $db): void
    {
        if (empty(self::$patientIds) || empty(self::$doctorIds)) return;

        $complaints = ['Chest pain and breathlessness','Routine diabetes follow-up','Acute asthma exacerbation','Headache and dizziness','Knee injury — football','Fatigue and weight gain','Recurrent sore throat','Hypertension stage 1','Migraine attack','GERD with heartburn','Lower back pain','Chronic cough','Abdominal pain','Anxiety and insomnia'];
        $diagnoses = ['Type 2 DM, controlled','Acute inferior STEMI','Asthma exacerbation (moderate)','Hypertension Stage 1','Right ACL sprain Grade II','Primary hypothyroidism','Recurrent tonsillitis','Migraine with aura','GERD','Iron-deficiency anemia','Lower respiratory tract infection','Generalized anxiety disorder'];
        $medicines = ['Metformin 500mg','Amoxicillin 500mg','Omeprazole 20mg','Salbutamol inhaler','Atorvastatin 20mg','Paracetamol 500mg','Cefixime 400mg','Ranitidine 150mg','Prednisolone 5mg','Amlodipine 5mg','Ibuprofen 400mg','Levothyroxine 50mcg'];
        $freqs = ['OD','BD','TDS','QID','PRN'];
        $durations = ['5 days','7 days','10 days','14 days','30 days','90 days'];

        $conInsert = $db->prepare(
            'INSERT INTO consultations (consultation_code, patient_id, doctor_id, department_id, consultation_date, status, chief_complaint, history_presenting, symptoms, observations, clinical_notes, diagnoses, follow_up_date, created_by, finalized_at, finalized_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?)'
        );

        $rxInsert = $db->prepare('INSERT INTO prescriptions (prescription_code, consultation_id, patient_id, doctor_id, status, finalized_at) VALUES (?, ?, ?, ?, ?, ?)');
        $itemInsert = $db->prepare('INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, quantity, instructions, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        $conSeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(consultation_code, '-', -1) AS UNSIGNED)), 0) FROM consultations")->fetchColumn();
        $rxSeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(prescription_code, '-', -1) AS UNSIGNED)), 0) FROM prescriptions")->fetchColumn();

        for ($i = 0; $i < 180; $i++) {
            $conSeq++;
            $rxSeq++;
            $patientId = self::$patientIds[array_rand(self::$patientIds)];
            $doc = self::$doctorIds[array_rand(self::$doctorIds)];
            $daysAgo = random_int(0, 30);
            $date = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
            $isFinalized = random_int(0, 4) > 0; // 80% finalized
            $status = $isFinalized ? 'finalized' : 'draft';
            $finalizedAt = $isFinalized ? date('Y-m-d H:i:s', strtotime("-{$daysAgo} days +30 minutes")) : null;

            $complaint = $complaints[array_rand($complaints)];
            $diagnosis = $diagnoses[array_rand($diagnoses)];

            $conCode = 'CON-' . date('Y') . '-' . str_pad((string)$conSeq, 5, '0', STR_PAD_LEFT);
            $conInsert->execute([
                $conCode, $patientId, $doc['id'], $doc['dept'] ?: null, $date, $status, $complaint,
                'Patient reports ' . strtolower($complaint) . ' for ' . random_int(2, 14) . ' days.',
                $complaint, 'Vital signs stable. General examination unremarkable.',
                'Plan: medication review, lifestyle advice. Follow-up in 2 weeks.',
                $diagnosis, date('Y-m-d', strtotime('+' . random_int(7, 30) . ' days')),
                $finalizedAt, $date
            ]);
            $conId = (int) $db->lastInsertId();

            // Create prescription (only for finalized consultations)
            if ($isFinalized) {
                $rxCode = 'RX-' . date('Y') . '-' . str_pad((string)$rxSeq, 5, '0', STR_PAD_LEFT);
                $rxInsert->execute([$rxCode, $conId, $patientId, $doc['id'], 'finalized', $finalizedAt]);
                $rxId = (int) $db->lastInsertId();

                $numItems = random_int(2, 4);
                for ($j = 0; $j < $numItems; $j++) {
                    $med = $medicines[array_rand($medicines)];
                    $itemInsert->execute([$rxId, $med, (random_int(250, 1000) . 'mg'), $freqs[array_rand($freqs)], $durations[array_rand($durations)], random_int(10, 60), 'After meals', $j]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Billing (200+ invoices, 150+ payments, expenses)
    // ------------------------------------------------------------------
    private static function seedBilling(PDO $db): void
    {
        if (empty(self::$patientIds)) return;

        $invInsert = $db->prepare(
            'INSERT INTO invoices (invoice_code, patient_id, invoice_date, subtotal, discount_amount, discount_percentage, tax_percentage, tax_amount, total, paid_amount, balance_due, status, created_by, created_at)
             VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?, ?, ?, ?, 1, ?)'
        );
        $itemInsert = $db->prepare('INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount_amount, line_total, sort_order) VALUES (?, ?, ?, 1, ?, 0, ?, 0)');
        $payInsert = $db->prepare('INSERT INTO payments (payment_code, invoice_id, patient_id, amount, payment_method, reference_number, status, recorded_by, recorded_at) VALUES (?, ?, ?, ?, ?, ?, "completed", 1, ?)');

        $invSeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_code, '-', -1) AS UNSIGNED)), 0) FROM invoices")->fetchColumn();
        $paySeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(payment_code, '-', -1) AS UNSIGNED)), 0) FROM payments")->fetchColumn();

        $methods = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'insurance'];
        $statuses = ['paid', 'partially_paid', 'sent', 'cancelled'];

        for ($i = 0; $i < 220; $i++) {
            $invSeq++;
            $patientId = self::$patientIds[array_rand(self::$patientIds)];
            $daysAgo = random_int(0, 60);
            $date = date('Y-m-d', strtotime("-{$daysAgo} days"));

            // Random services
            $numItems = random_int(1, 3);
            $subtotal = 0;
            $itemRows = [];
            for ($j = 0; $j < $numItems; $j++) {
                $svcId = !empty(self::$serviceIds) ? self::$serviceIds[array_rand(self::$serviceIds)] : null;
                $price = random_int(100, 5000);
                $subtotal += $price;
                $itemRows[] = ['service_id' => $svcId, 'desc' => 'Service #' . ($j + 1), 'price' => $price];
            }
            $total = $subtotal;
            $status = $statuses[array_rand($statuses)];
            $paid = $status === 'paid' ? $total : ($status === 'partially_paid' ? round($total * random_int(30, 70) / 100, 2) : 0);
            $balance = round($total - $paid, 2);

            if ($status === 'cancelled') { $paid = 0; $balance = 0; }

            $code = 'INV-' . date('Y') . '-' . str_pad((string)$invSeq, 5, '0', STR_PAD_LEFT);
            $invInsert->execute([$code, $patientId, $date, $subtotal, $total, $paid, $balance, $status, date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
            $invId = (int) $db->lastInsertId();

            foreach ($itemRows as $item) {
                $itemInsert->execute([$invId, $item['service_id'], $item['desc'], $item['price'], $item['price']]);
            }

            // Payments for non-cancelled invoices
            if ($status !== 'cancelled' && $status !== 'sent' && $paid > 0) {
                $paySeq++;
                $payCode = 'PAY-' . date('Y') . '-' . str_pad((string)$paySeq, 5, '0', STR_PAD_LEFT);
                $method = $methods[array_rand($methods)];
                $ref = $method !== 'cash' ? 'TXN-' . random_int(100000, 999999) : null;
                $payInsert->execute([$payCode, $invId, $patientId, $paid, $method, $ref, date('Y-m-d H:i:s', strtotime("-{$daysAgo} days +2 hours"))]);
            }
        }

        // Expenses
        $expSeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(expense_code, '-', -1) AS UNSIGNED)), 0) FROM expenses")->fetchColumn();
        $expCats = ['salaries','utilities','supplies','maintenance','equipment','rent','other'];
        $expInsert = $db->prepare('INSERT INTO expenses (expense_code, category, description, amount, expense_date, paid_to, payment_method, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        for ($i = 0; $i < 40; $i++) {
            $expSeq++;
            $cat = $expCats[array_rand($expCats)];
            $amount = random_int(500, 50000);
            $expInsert->execute(['EXP-' . date('Y') . '-' . str_pad((string)$expSeq, 5, '0', STR_PAD_LEFT), $cat, 'Monthly ' . $cat . ' expense', $amount, date('Y-m-d', strtotime('-' . random_int(1, 30) . ' days')), 'Vendor ' . random_int(1, 50), 'bank_transfer']);
        }
    }

    // ------------------------------------------------------------------
    // Lab Orders (100+)
    // ------------------------------------------------------------------
    private static function seedLabOrders(PDO $db): void
    {
        if (empty(self::$patientIds) || empty(self::$labTestIds)) return;

        $orderInsert = $db->prepare('INSERT INTO lab_orders (order_code, patient_id, doctor_id, status, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?)');
        $itemInsert = $db->prepare('INSERT INTO lab_order_items (order_id, test_id, status) VALUES (?, ?, ?)');
        $resultUpdate = $db->prepare('UPDATE lab_order_items SET result_value = ?, result_unit = ?, reference_range = ?, is_critical = ?, status = ?, resulted_at = NOW(), resulted_by = 1, collected_at = NOW(), collected_by = 1 WHERE id = ?');
        $verifyUpdate = $db->prepare('UPDATE lab_order_items SET status = ?, verified_at = NOW(), verified_by = 1 WHERE id = ?');
        $releaseUpdate = $db->prepare('UPDATE lab_order_items SET status = ?, released_at = NOW(), released_by = 1 WHERE id = ?');
        $invInsert = $db->prepare('INSERT INTO invoices (invoice_code, patient_id, invoice_date, subtotal, discount_amount, discount_percentage, tax_percentage, tax_amount, total, paid_amount, balance_due, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?, 0, ?, "sent", ?, 1, ?)');
        $invItemInsert = $db->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, line_total, sort_order) VALUES (?, ?, 1, ?, 0, ?, 0)');

        $seq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(order_code, '-', -1) AS UNSIGNED)), 0) FROM lab_orders")->fetchColumn();
        $invSeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_code, '-', -1) AS UNSIGNED)), 0) FROM invoices")->fetchColumn();

        $statuses = ['ordered', 'collected', 'resulted', 'verified', 'released', 'cancelled'];
        $units = ['mg/dL', 'mmol/L', 'g/dL', 'x10^9/L', '%', 'IU/L', 'ng/mL', 'pg/mL'];

        for ($i = 0; $i < 120; $i++) {
            $seq++;
            $patientId = self::$patientIds[array_rand(self::$patientIds)];
            $doc = !empty(self::$doctorIds) ? self::$doctorIds[array_rand(self::$doctorIds)] : ['id' => null, 'user_id' => null];
            $daysAgo = random_int(0, 30);
            $orderStatus = $statuses[array_rand($statuses)];

            $code = 'LAB-' . date('Y') . '-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
            $orderInsert->execute([$code, $patientId, $doc['id'], $orderStatus, date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
            $orderId = (int) $db->lastInsertId();

            // Add 1-3 tests per order
            $numTests = random_int(1, 3);
            $totalPrice = 0;
            for ($j = 0; $j < $numTests; $j++) {
                $test = self::$labTestIds[array_rand(self::$labTestIds)];
                $itemStatus = $orderStatus === 'cancelled' ? 'ordered' : ($orderStatus === 'released' ? 'released' : ($orderStatus === 'verified' ? 'verified' : $orderStatus));
                $itemInsert->execute([$orderId, $test['id'], $itemStatus]);
                $itemId = (int) $db->lastInsertId();
                $totalPrice += $test['price'];

                // If resulted or beyond, add result data
                if (in_array($itemStatus, ['resulted', 'verified', 'released'])) {
                    $isCritical = random_int(1, 10) > 8 ? 1 : 0;
                    $resultUpdate->execute([
                        number_format(random_int(30, 200) / 10, 1),
                        $units[array_rand($units)],
                        random_int(70, 120) . ' - ' . random_int(120, 180),
                        $isCritical, 'resulted', $itemId
                    ]);

                    if (in_array($itemStatus, ['verified', 'released'])) {
                        $verifyUpdate->execute(['verified', $itemId]);
                    }
                    if ($itemStatus === 'released') {
                        $releaseUpdate->execute(['released', $itemId]);
                    }
                }
            }

            // Create invoice for non-cancelled orders
            if ($orderStatus !== 'cancelled' && $totalPrice > 0) {
                $invSeq++;
                $invCode = 'INV-' . date('Y') . '-' . str_pad((string)$invSeq, 5, '0', STR_PAD_LEFT);
                $invInsert->execute([$invCode, $patientId, date('Y-m-d', strtotime("-{$daysAgo} days")), $totalPrice, $totalPrice, $totalPrice, 'Lab order ' . $code, date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
                $invId = (int) $db->lastInsertId();
                $invItemInsert->execute([$invId, 'Lab tests', $totalPrice, $totalPrice]);
                $db->prepare('UPDATE lab_orders SET invoice_id = ? WHERE id = ?')->execute([$invId, $orderId]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Pharmacy Dispensing (50+)
    // ------------------------------------------------------------------
    private static function seedPharmacyDispensing(PDO $db): void
    {
        if (empty(self::$patientIds) || empty(self::$medicineIds)) return;

        $dspInsert = $db->prepare('INSERT INTO medicine_dispensings (dispensing_code, patient_id, status, dispensed_by, created_at) VALUES (?, ?, "dispensed", 1, ?)');
        $itemInsert = $db->prepare('INSERT INTO medicine_dispensing_items (dispensing_id, medicine_id, batch_id, quantity_dispensed, unit_price, instructions) VALUES (?, ?, ?, ?, ?, ?)');
        $invInsert = $db->prepare('INSERT INTO invoices (invoice_code, patient_id, invoice_date, subtotal, discount_amount, discount_percentage, tax_percentage, tax_amount, total, paid_amount, balance_due, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?, 0, ?, "sent", ?, 1, ?)');

        $seq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(dispensing_code, '-', -1) AS UNSIGNED)), 0) FROM medicine_dispensings")->fetchColumn();
        $invSeq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_code, '-', -1) AS UNSIGNED)), 0) FROM invoices")->fetchColumn();

        for ($i = 0; $i < 60; $i++) {
            $seq++;
            $patientId = self::$patientIds[array_rand(self::$patientIds)];
            $daysAgo = random_int(0, 20);
            $code = 'DSP-' . date('Y') . '-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
            $dspInsert->execute([$code, $patientId, date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
            $dspId = (int) $db->lastInsertId();

            $numItems = random_int(1, 3);
            $subtotal = 0;
            for ($j = 0; $j < $numItems; $j++) {
                $medId = self::$medicineIds[array_rand(self::$medicineIds)];
                // Find a batch with stock
                $batch = $db->query('SELECT id, sell_price, quantity_remaining FROM medicine_batches WHERE medicine_id = ' . $medId . ' AND quantity_remaining > 0 ORDER BY expiry_date ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                if (!$batch) continue;
                $qty = random_int(1, min(30, (int)$batch['quantity_remaining']));
                $price = (float)$batch['sell_price'];
                $subtotal += $qty * $price;
                $itemInsert->execute([$dspId, $medId, (int)$batch['id'], $qty, $price, 'After meals']);
                $db->exec('UPDATE medicine_batches SET quantity_remaining = quantity_remaining - ' . $qty . ' WHERE id = ' . (int)$batch['id']);
            }

            if ($subtotal > 0) {
                $invSeq++;
                $invCode = 'INV-' . date('Y') . '-' . str_pad((string)$invSeq, 5, '0', STR_PAD_LEFT);
                $invInsert->execute([$invCode, $patientId, date('Y-m-d', strtotime("-{$daysAgo} days")), $subtotal, $subtotal, $subtotal, 'Pharmacy dispensing ' . $code, date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
                $invId = (int) $db->lastInsertId();
                $db->prepare('UPDATE medicine_dispensings SET invoice_id = ? WHERE id = ?')->execute([$invId, $dspId]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Admissions (75+)
    // ------------------------------------------------------------------
    private static function seedAdmissions(PDO $db): void
    {
        if (empty(self::$patientIds) || empty(self::$bedIds)) return;

        $admInsert = $db->prepare(
            'INSERT INTO admissions (admission_code, patient_id, doctor_id, ward_id, room_id, bed_id, admission_type, admission_date, expected_discharge, actual_discharge_date, admission_reason, diagnosis_at_admission, discharge_summary, discharge_diagnosis, status, created_by, discharged_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $seq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(admission_code, '-', -1) AS UNSIGNED)), 0) FROM admissions")->fetchColumn();
        $reasons = ['Chest pain observation','Acute exacerbation of COPD','Post-operative recovery','Severe dehydration','Pneumonia treatment','Stroke management','Fracture immobilization','Diabetic ketoacidosis','Severe anemia','Gastroenteritis'];
        $diagnoses = ['Rule out ACS','COPD with type 2 respiratory failure','Post appendectomy','Dehydration with electrolyte imbalance','Community-acquired pneumonia','Ischemic stroke','Femoral fracture','DKA resolved','Iron-deficiency anemia','Acute gastroenteritis'];

        // Get room_id + ward_id from bed_id
        $bedInfo = [];
        foreach ($db->query('SELECT b.id, b.room_id, r.ward_id FROM beds b INNER JOIN rooms r ON r.id = b.room_id')->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $bedInfo[(int)$b['id']] = ['room' => (int)$b['room_id'], 'ward' => (int)$b['ward_id']];
        }

        $usedBeds = [];
        for ($i = 0; $i < 80; $i++) {
            $seq++;
            $patientId = self::$patientIds[array_rand(self::$patientIds)];
            $doc = !empty(self::$doctorIds) ? self::$doctorIds[array_rand(self::$doctorIds)] : ['id' => null, 'dept' => null];

            // Find an available bed
            $availableBeds = array_diff(self::$bedIds, $usedBeds);
            if (empty($availableBeds)) break;
            $bedId = $availableBeds[array_rand($availableBeds)];
            $usedBeds[] = $bedId;
            $info = $bedInfo[$bedId] ?? ['room' => null, 'ward' => null];

            $daysAgo = random_int(0, 30);
            $isDischarged = random_int(0, 3) > 0; // 75% discharged

            if ($isDischarged) {
                $status = 'discharged';
                $dischargeDate = date('Y-m-d H:i:s', strtotime('-' . max(0, $daysAgo - random_int(1, 5)) . ' days'));
                $bedStatus = 'cleaning';
                $summary = 'Patient recovered well. Discharged with medication and follow-up instructions.';
            } else {
                $status = 'admitted';
                $dischargeDate = null;
                $bedStatus = 'occupied';
                $summary = null;
            }

            $code = 'ADM-' . date('Y') . '-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
            $admInsert->execute([
                $code, $patientId, $doc['id'], $info['ward'], $info['room'], $bedId,
                random_int(0, 1) ? 'scheduled' : 'emergency',
                date('Y-m-d H:i:s', strtotime("-{$daysAgo} days")),
                date('Y-m-d', strtotime('+' . random_int(1, 7) . ' days')),
                $dischargeDate,
                $reasons[array_rand($reasons)], $diagnoses[array_rand($diagnoses)],
                $summary, $isDischarged ? $diagnoses[array_rand($diagnoses)] : null,
                $status, 1, $isDischarged ? 1 : null,
                date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))
            ]);

            // Update bed status
            $db->prepare('UPDATE beds SET status = ?, current_admission_id = ? WHERE id = ?')->execute([$bedStatus, $isDischarged ? null : (int)$db->lastInsertId(), $bedId]);
        }
    }

    // ------------------------------------------------------------------
    // Notifications (100+)
    // ------------------------------------------------------------------
    private static function seedNotifications(PDO $db): void
    {
        $types = ['appointment_reminder', 'low_stock', 'expiry_alert', 'lab_pending', 'admission_alert', 'announcement', 'system'];
        $priorities = ['low', 'medium', 'high', 'critical'];
        $titles = [
            'appointment_reminder' => ['5 appointments scheduled for tomorrow','3 appointments pending confirmation','Reminder: 8 appointments today'],
            'low_stock' => ['Low stock: Metformin 500mg','Low stock: Amoxicillin 500mg','Low stock: Salbutamol Inhaler','Low stock: Normal Saline 1L'],
            'expiry_alert' => ['Batch expiring in 30 days: Omeprazole','Expired batch found: Paracetamol','Expiry warning: Cefixime batch'],
            'lab_pending' => ['3 lab orders awaiting result entry','5 lab tests pending verification','Critical result: Troponin I elevated'],
            'admission_alert' => ['Bed transfer completed: Room 301','ICU bed 2 now available for cleaning','New emergency admission'],
            'announcement' => ['System maintenance scheduled','New hospital policy update','Staff meeting reminder'],
            'system' => ['Database backup completed','System health check passed','New user registered'],
        ];

        $insert = $db->prepare('INSERT INTO notifications (user_id, type, title, message, priority, is_read, action_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        for ($i = 0; $i < 120; $i++) {
            $type = $types[array_rand($types)];
            $titlesForType = $titles[$type];
            $title = $titlesForType[array_rand($titlesForType)];
            $priority = $priorities[array_rand($priorities)];
            $isRead = random_int(0, 3) > 0 ? 1 : 0;
            $userId = random_int(0, 1) ? null : (random_int(1, 10)); // broadcast or targeted
            $actionUrl = random_int(0, 1) ? url('/admin/' . explode('_', $type)[0]) : null;
            $daysAgo = random_int(0, 7);

            $insert->execute([$userId, $type, $title, $title . '. Please review and take action if needed.', $priority, $isRead, $actionUrl, date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
        }
    }

    // ------------------------------------------------------------------
    // Audit Logs
    // ------------------------------------------------------------------
    private static function seedAuditLogs(PDO $db): void
    {
        $events = [
            'login.success' => 'User logged in successfully',
            'patient.created' => 'Registered a new patient',
            'appointment.created' => 'Booked an appointment',
            'consultation.created' => 'Created a consultation record',
            'invoice.created' => 'Generated an invoice',
            'payment.recorded' => 'Recorded a payment',
            'lab.order_created' => 'Created a lab order',
            'admission.created' => 'Admitted a patient',
            'prescription.created' => 'Created a prescription',
            'pharmacy.dispensed' => 'Dispensed medicines',
            'settings.updated' => 'Updated hospital settings',
        ];

        $insert = $db->prepare('INSERT INTO audit_logs (user_id, event, module, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        for ($i = 0; $i < 200; $i++) {
            $event = array_rand($events);
            $userId = random_int(1, max(1, $userCount));
            $daysAgo = random_int(0, 14);
            $parts = explode('.', $event);
            $insert->execute([$userId, $event, $parts[0] ?? 'system', $parts[1] ?? 'action', $events[$event], '127.0.0.1', 'Mozilla/5.0', date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
        }
    }
}
