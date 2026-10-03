<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Global search — returns REAL records for implemented modules and
 * honestly lists not-yet-implemented modules as "coming soon" instead
 * of fabricating results.
 */
final class SearchService
{
    private const MODULE_INDEX = [
        // All planned modules are now live — this list is empty.
    ];

    /**
     * @return array{
     *   query: string,
     *   groups: array<int, array{label: string, icon: string, results: array<int, array{title: string, subtitle: string, url: string}>}>,
     *   modules: array<int, array{label: string, icon: string}>,
     *   total: int
     * }
     */
    public static function search(string $query): array
    {
        $query = trim(mb_substr($query, 0, 60));
        $groups = [];
        $modules = [];
        $total = 0;

        if ($query === '') {
            return ['query' => '', 'groups' => [], 'modules' => [], 'total' => 0];
        }

        // --- Consultations (live module, permission-gated) -------------------
        if (Database::tableExists('consultations') && \App\Core\Auth::can('consultations.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT c.id, c.consultation_code, c.consultation_date, c.chief_complaint,
                        CONCAT(p.first_name, ' ', p.last_name) AS patient_name
                 FROM consultations c
                 LEFT JOIN patients p ON p.id = c.patient_id
                 WHERE c.consultation_code LIKE ? OR c.chief_complaint LIKE ? OR c.diagnoses LIKE ?
                    OR p.first_name LIKE ? OR p.last_name LIKE ?
                 ORDER BY c.consultation_date DESC LIMIT 5",
                [$like, $like, $like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['consultation_code']} · {$row['patient_name']}",
                        'subtitle' => 'Consultation · ' . ($row['chief_complaint'] ?: format_date(substr((string) $row['consultation_date'], 0, 10), 'M j, Y')),
                        'url'      => url('/admin/consultations/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Consultations', 'icon' => 'clipboard-list', 'results' => $results];
            }
        }

        // --- Appointments (live module, permission-gated) -------------------
        if (Database::tableExists('appointments') && \App\Core\Auth::can('appointments.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT a.id, a.appointment_code, a.appointment_date, a.start_time,
                        CONCAT(p.first_name, ' ', p.last_name) AS patient_name
                 FROM appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id
                 WHERE a.appointment_code LIKE ? OR a.queue_token LIKE ?
                    OR p.first_name LIKE ? OR p.last_name LIKE ?
                    OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?
                 ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 5",
                [$like, $like, $like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['appointment_code']} · {$row['patient_name']}",
                        'subtitle' => 'Appointment · ' . format_date($row['appointment_date'], 'M j, g:i A'),
                        'url'      => url('/admin/appointments/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Appointments', 'icon' => 'calendar-days', 'results' => $results];
            }
        }

        // --- Patients (live module, permission-gated) -------------------
        if (Database::tableExists('patients') && \App\Core\Auth::can('patients.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT id, patient_code, first_name, last_name, phone
                 FROM patients
                 WHERE archived_at IS NULL
                   AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?
                        OR patient_code LIKE ? OR phone LIKE ?)
                 ORDER BY created_at DESC
                 LIMIT 5",
                [$like, $like, $like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['first_name']} {$row['last_name']} · {$row['patient_code']}",
                        'subtitle' => 'Patient · ' . $row['phone'],
                        'url'      => url('/admin/patients/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Patients', 'icon' => 'user-round', 'results' => $results];
            }
        }

        // --- Doctors (live module, permission-gated) -------------------
        if (Database::tableExists('doctors') && \App\Core\Auth::can('doctors.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT doc.id, doc.doctor_code, doc.specialization, u.name, u.phone
                 FROM doctors doc
                 INNER JOIN users u ON u.id = doc.user_id
                 WHERE doc.archived_at IS NULL
                   AND (u.name LIKE ? OR doc.doctor_code LIKE ? OR doc.specialization LIKE ?
                        OR doc.registration_number LIKE ? OR u.phone LIKE ?)
                 ORDER BY doc.created_at DESC LIMIT 5",
                [$like, $like, $like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['name']} · {$row['doctor_code']}",
                        'subtitle' => 'Doctor · ' . $row['specialization'],
                        'url'      => url('/admin/doctors/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Doctors', 'icon' => 'stethoscope', 'results' => $results];
            }
        }

        // --- Staff (live module, permission-gated) -------------------
        if (Database::tableExists('staff_profiles') && \App\Core\Auth::can('staff.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT sp.id, sp.employee_id, sp.job_title, u.name, u.phone
                 FROM staff_profiles sp
                 INNER JOIN users u ON u.id = sp.user_id
                 WHERE sp.archived_at IS NULL
                   AND (u.name LIKE ? OR sp.employee_id LIKE ? OR sp.job_title LIKE ? OR u.phone LIKE ?)
                 ORDER BY sp.created_at DESC LIMIT 5",
                [$like, $like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['name']} · {$row['employee_id']}",
                        'subtitle' => 'Staff · ' . $row['job_title'],
                        'url'      => url('/admin/staff/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Staff', 'icon' => 'id-card', 'results' => $results];
            }
        }

        // --- Departments (live module, permission-gated) -------------------
        if (Database::tableExists('departments') && \App\Core\Auth::can('departments.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT id, name, slug, location FROM departments
                 WHERE archived_at IS NULL AND (name LIKE ? OR slug LIKE ? OR location LIKE ?)
                 ORDER BY name LIMIT 5",
                [$like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => $row['name'],
                        'subtitle' => 'Department · ' . ($row['location'] ?? $row['slug']),
                        'url'      => url('/admin/departments/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Departments', 'icon' => 'building-2', 'results' => $results];
            }
        }

        // --- Invoices (live module, permission-gated) -------------------
        if (Database::tableExists('invoices') && \App\Core\Auth::can('billing.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                'SELECT id, invoice_code, invoice_date, total, status,
                        CONCAT(p.first_name, " ", p.last_name) AS patient_name
                 FROM invoices i
                 LEFT JOIN patients p ON p.id = i.patient_id
                 WHERE i.invoice_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                 ORDER BY i.invoice_date DESC LIMIT 5',
                [$like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['invoice_code']} · {$row['patient_name']}",
                        'subtitle' => 'Invoice · ' . format_money($row['total'], (string) setting('currency', 'BDT')) . ' · ' . ucfirst($row['status']),
                        'url'      => url('/admin/billing/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Invoices', 'icon' => 'receipt-text', 'results' => $results];
            }
        }

        // --- Medicines (live module, permission-gated) -------------------
        if (Database::tableExists('medicines') && \App\Core\Auth::can('pharmacy.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                'SELECT id, name, generic_name, dosage_form FROM medicines WHERE name LIKE ? OR generic_name LIKE ? OR brand_name LIKE ? ORDER BY name LIMIT 5',
                [$like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => $row['name'],
                        'subtitle' => 'Medicine · ' . ($row['generic_name'] ?: ucfirst($row['dosage_form'])),
                        'url'      => url('/admin/pharmacy/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Medicines', 'icon' => 'pill', 'results' => $results];
            }
        }

        // --- Lab orders (live module, permission-gated) -------------------
        if (Database::tableExists('lab_orders') && \App\Core\Auth::can('laboratory.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                'SELECT o.id, o.order_code, o.status, o.created_at,
                        CONCAT(p.first_name, " ", p.last_name) AS patient_name
                 FROM lab_orders o
                 LEFT JOIN patients p ON p.id = o.patient_id
                 WHERE o.order_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                 ORDER BY o.created_at DESC LIMIT 5',
                [$like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['order_code']} · {$row['patient_name']}",
                        'subtitle' => 'Lab · ' . ucfirst($row['status']),
                        'url'      => url('/admin/laboratory/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Lab Orders', 'icon' => 'flask-conical', 'results' => $results];
            }
        }

        // --- Admissions (live module, permission-gated) -------------------
        if (Database::tableExists('admissions') && \App\Core\Auth::can('admissions.view')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                'SELECT a.id, a.admission_code, a.status, a.admission_date,
                        CONCAT(p.first_name, " ", p.last_name) AS patient_name
                 FROM admissions a
                 LEFT JOIN patients p ON p.id = a.patient_id
                 WHERE a.admission_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                 ORDER BY a.admission_date DESC LIMIT 5',
                [$like, $like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => "{$row['admission_code']} · {$row['patient_name']}",
                        'subtitle' => 'Admission · ' . ucfirst(str_replace('_', ' ', $row['status'])) . ' · ' . format_date(substr((string) $row['admission_date'], 0, 10), 'M j, Y'),
                        'url'      => url('/admin/admissions/' . (int) $row['id']),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Admissions', 'icon' => 'door-open', 'results' => $results];
            }
        }

        // --- Users (live module) -------------------------------------
        if (Database::tableExists('users')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT u.id, u.name, u.email,
                        COALESCE((SELECT GROUP_CONCAT(r.name SEPARATOR ', ')
                                  FROM roles r INNER JOIN role_user ru ON ru.role_id = r.id
                                  WHERE ru.user_id = u.id), '') AS roles_label
                 FROM users u
                 WHERE u.name LIKE ? OR u.email LIKE ?
                 ORDER BY u.name ASC
                 LIMIT 6",
                [$like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => (string) $row['name'],
                        'subtitle' => trim(((string) $row['roles_label'] !== '' ? $row['roles_label'] . ' · ' : '') . (string) $row['email']),
                        'url'      => url('/admin/users?q=' . urlencode($query)),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Staff', 'icon' => 'user-round', 'results' => $results];
            }
        }

        // --- Audit trail (live module) --------------------------------
        if (Database::tableExists('audit_logs')) {
            $like = '%' . $query . '%';
            $rows = Database::query(
                "SELECT a.id, a.event, a.description, a.created_at
                 FROM audit_logs a
                 WHERE a.description LIKE ? OR a.event LIKE ?
                 ORDER BY a.created_at DESC
                 LIMIT 4",
                [$like, $like]
            );
            if ($rows !== []) {
                $results = [];
                foreach ($rows as $row) {
                    $results[] = [
                        'title'    => (string) $row['event'],
                        'subtitle' => str_limit((string) ($row['description'] ?? ''), 70),
                        'url'      => url('/admin/audit-logs?q=' . urlencode($query)),
                    ];
                }
                $total += count($results);
                $groups[] = ['label' => 'Activity', 'icon' => 'history', 'results' => $results];
            }
        }

        // --- Future modules — surfaced honestly ------------------------
        foreach (self::MODULE_INDEX as $module) {
            if (!Database::tableExists($module['table'])) {
                $modules[] = ['label' => $module['label'], 'icon' => $module['icon']];
            }
        }

        return ['query' => $query, 'groups' => $groups, 'modules' => $modules, 'total' => $total];
    }
}
