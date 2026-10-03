<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Realistic consultations + prescriptions for seeded patients and doctors.
 * Creates finalized consultations with vitals, diagnoses, prescriptions,
 * and one draft for the workflow demo.
 */
final class ConsultationSeeder extends Seeder
{
    public static function label(): string { return 'Consultations'; }
    public static function order(): int { return 175; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'prescription_items', 'prescriptions', 'consultation_amendments', 'consultation_attachments', 'consultations');

        // Resolve patients + doctors.
        $patients = $db->query('SELECT id, first_name, last_name, date_of_birth, gender, blood_group, allergies FROM patients WHERE archived_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $doctors = $db->query("SELECT doc.id, doc.user_id, doc.department_id, doc.doctor_code, doc.specialization FROM doctors doc WHERE doc.archived_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);

        if (count($patients) < 5 || count($doctors) < 1) {
            return;
        }

        $year = (int) date('Y');
        $seq = 0;
        $rxSeq = 0;
        $now = time();

        $conInsert = $db->prepare(
            'INSERT INTO consultations
                (consultation_code, patient_id, doctor_id, appointment_id, department_id, consultation_date,
                 status, chief_complaint, history_presenting, symptoms, observations,
                 temperature, bp_systolic, bp_diastolic, pulse, respiratory_rate, spo2, weight, height, bmi,
                 clinical_notes, diagnoses, follow_up_date, referral_to, referral_reason,
                 finalized_at, finalized_by, created_by, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $rxInsert = $db->prepare(
            'INSERT INTO prescriptions (prescription_code, consultation_id, patient_id, doctor_id, status, notes, finalized_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $itemInsert = $db->prepare(
            'INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, quantity, instructions, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $adminId = (int) $db->query("SELECT id FROM users WHERE email = 'admin@medicore.test'")->fetchColumn() ?: null;

        $consultations = [
            // [patient_idx, doctor_idx, days_ago, complaint, hpi, symptoms, observations, temp, bp_sys, bp_dia, pulse, resp, spo2, weight, height, notes, dx, follow_up, referral_to, referral_reason, status, prescription?]
            [0, 0, 3, 'Routine diabetes follow-up', 'Type 2 DM diagnosed 2019. On metformin 500mg BD. Reports good glucose control. No polyuria or blurred vision.',
             'Occasional fatigue. No polydipsia.', 'General exam normal. No pedal edema. BMI elevated.',
             36.7, 130, 82, 76, 18, 98, 78.5, 170, 27.2,
             'HbA1c 6.8% (good control). Continue metformin. Dietary counselling reinforced. Daily 30-min walk advised.',
             'Type 2 Diabetes Mellitus, controlled', '+90 days', null, null, 'finalized',
             [['Metformin', '500mg', 'BD', '90 days', 180, 'After meals'],
              ['Aspirin', '75mg', 'OD', '90 days', 90, 'After meals, EC']]],

            [1, 0, 2, 'Chest pain and breathlessness', 'Sudden onset chest tightness 2 hours ago, radiating to left arm. Sweating and nausea.',
             'Diaphoresis, anxiety, breathlessness.', 'BP elevated. Heart sounds normal. No murmurs. Lungs clear.',
             37.1, 150, 95, 92, 20, 96, 70, 165, 25.7,
             'ECG: ST elevation in leads II, III, aVF → inferior STEMI. Shifted to cath lab for primary PCI. Aspirin + clopidogrel loading.',
             'Acute inferior STEMI', '+7 days', 'Cardiology — Interventional', 'Primary PCI planned', 'finalized',
             [['Aspirin', '300mg', 'STAT', '1 dose', 1, 'Chew then swallow'],
              ['Atorvastatin', '80mg', 'OD', '30 days', 30, 'Night']]],

            [2, 0, 5, 'Headache and dizziness for 1 week', 'Gradual onset occipital headache, worse in mornings. No visual disturbance. Dizziness on standing.',
             'Morning headache, dizziness.', 'BP 150/96. Fundoscopy: AV nicking. No focal neurological deficit.',
             36.5, 152, 98, 80, 16, 97, 85, 172, 28.7,
             'Started on amlodipine 5mg OD. Advised home BP monitoring. Low-sodium diet. Review in 2 weeks.',
             'Hypertension Stage 1', '+14 days', null, null, 'finalized',
             [['Amlodipine', '5mg', 'OD', '14 days', 14, 'Morning']]],

            [3, 1, 1, 'Acute asthma exacerbation', 'Known asthmatic. Exposed to dust yesterday. Woke up with wheezing and chest tightness. Used salbutamol inhaler 3 times overnight with partial relief.',
             'Wheezing, chest tightness, dry cough.', 'Bilateral expiratory wheeze. PEF 60% predicted. SpO2 94%. Accessory muscle use.',
             37.0, 120, 78, 98, 24, 94, 58, 160, 22.7,
             'Nebulized salbutamol + ipratropium. IV hydrocortisone. Oxygen 4L/min. Improved in 1 hour → PEF 80%. Discharged on oral prednisolone + inhaler.',
             'Acute asthma exacerbation (moderate)', '+7 days', null, null, 'finalized',
             [['Salbutamol inhaler', '100mcg', '2 puffs QID', '14 days', 1, 'PRN also for wheeze'],
              ['Prednisolone', '30mg', 'OD', '5 days', 5, 'Morning, with food'],
              ['Budesonide inhaler', '200mcg', '2 puffs BD', '30 days', 1, 'Rinse mouth after']]],

            [4, 0, 7, 'Knee injury — football', 'Twisted right knee during football 3 days ago. Heard a pop. Immediate pain and swelling. Unable to continue playing.',
             'Right knee pain, swelling, instability.', 'Right knee swollen, tender medial joint line. Lachman test positive. McMurray positive.',
             36.6, 128, 80, 72, 16, 99, 75, 178, 23.7,
             'MRI ordered: ACL sprain Grade II. RICE protocol. Knee brace fitted. Physiotherapy referral. Review with MRI in 2 weeks.',
             'Right ACL sprain Grade II', '+14 days', 'Physiotherapy', 'Rehab program', 'finalized',
             [['Ibuprofen', '400mg', 'TDS', '7 days', 21, 'After meals'],
              ['Paracetamol', '500mg', 'PRN', '7 days', 10, 'For breakthrough pain']]],

            [5, 1, 0, 'Fatigue and weight gain', 'Increasing tiredness over 6 months. Weight gain of 5kg. Cold intolerance. Constipation.',
             'Fatigue, cold intolerance, dry skin, constipation.', 'Pulse 58. Dry skin. Delayed ankle reflexes. No thyroid enlargement.',
             35.8, 118, 75, 58, 14, 97, 82, 168, 29.1,
             'TSH 12.4 mIU/L, T4 low. Started levothyroxine 50mcg OD. Thyroid antibody panel ordered. Review in 6 weeks with TFTs.',
             'Primary hypothyroidism', '+42 days', null, null, 'finalized',
             [['Levothyroxine', '50mcg', 'OD', '42 days', 42, 'Empty stomach, morning']]],

            [6, 1, 0, 'Recurrent sore throat', '3rd episode this year. Pain on swallowing, low-grade fever 2 days.',
             'Sore throat, odynophagia, mild fever.', 'Pharynx inflamed. Tonsils enlarged, no exudate. Cervical lymphadenopathy.',
             37.8, 122, 78, 84, 18, 98, 68, 175, 22.2,
             'Pending culture. Symptomatic treatment. Amoxicillin AVOIDED — documented allergy. Review if worse.',
             'Recurrent tonsillitis (viral)', '+5 days', null, null, 'draft',
             null], // draft has no prescription yet
        ];

        foreach ($consultations as $c) {
            [$pIdx, $dIdx, $daysAgo, $complaint, $hpi, $symptoms, $observations,
             $temp, $bpSys, $bpDia, $pulse, $resp, $spo2, $weight, $height, $bmi,
             $notes, $dx, $followUp, $refTo, $refReason, $status, $rxItems] = $c;

            $patient = $patients[$pIdx] ?? null;
            $doctor = $doctors[$dIdx] ?? null;
            if ($patient === null || $doctor === null) continue;

            $seq++;
            $code = sprintf('CON-%s-%05d', $year, $seq);
            $date = date('Y-m-d H:i:s', $now - $daysAgo * 86400 + 54000); // afternoon consultation
            $finalizedAt = $status === 'finalized' ? date('Y-m-d H:i:s', $now - $daysAgo * 86400 + 57600) : null;
            $finalizedBy = $status === 'finalized' ? $adminId : null;

            $conInsert->execute([
                $code, (int) $patient['id'], (int) $doctor['id'], (int) $doctor['department_id'], $date,
                $status, $complaint, $hpi, $symptoms, $observations,
                $temp, $bpSys, $bpDia, $pulse, $resp, $spo2, $weight, $height, $bmi,
                $notes, $dx, $followUp !== null ? date('Y-m-d', $now + (int) $followUp * 86400) : null, $refTo, $refReason,
                $finalizedAt, $finalizedBy, $adminId, date('Y-m-d H:i:s', $now - $daysAgo * 86400 + 43200),
            ]);
            $conId = (int) $db->lastInsertId();

            if ($rxItems !== null) {
                $rxSeq++;
                $rxCode = sprintf('RX-%s-%05d', $year, $rxSeq);
                $rxStatus = $status === 'finalized' ? 'finalized' : 'draft';
                $rxFinalized = $rxStatus === 'finalized' ? $finalizedAt : null;

                $rxInsert->execute([
                    $rxCode, $conId, (int) $patient['id'], (int) $doctor['id'], $rxStatus, null, $rxFinalized, date('Y-m-d H:i:s'),
                ]);
                $rxId = (int) $db->lastInsertId();

                $sort = 0;
                foreach ($rxItems as $item) {
                    [$name, $dosage, $freq, $dur, $qty, $instr] = $item;
                    $itemInsert->execute([$rxId, $name, $dosage, $freq, $dur, $qty, $instr, $sort++]);
                }
            }
        }
    }
}
