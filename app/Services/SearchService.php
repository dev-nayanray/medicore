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
        ['label' => 'Appointments', 'icon' => 'calendar-days', 'route' => null, 'table' => 'appointments'],
        ['label' => 'Billing',      'icon' => 'receipt',    'route' => null, 'table' => 'invoices'],
        ['label' => 'Bed manager',  'icon' => 'bed-double', 'route' => null, 'table' => 'beds'],
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
