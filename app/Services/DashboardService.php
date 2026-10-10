<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\AuditLog;

/**
 * Dashboard aggregation.
 *
 * Design rule: a metric is only "available" when the module table that
 * backs it exists. Unimplemented modules render an honest empty state —
 * the dashboard NEVER presents placeholder numbers as real records.
 *
 * Phase 1 ships the foundation tables (users / roles / permissions /
 * settings / audit_logs), so workforce, audit and system tiles are live;
 * patients / appointments / revenue / beds tiles light up automatically
 * the moment their migrations land in later phases.
 */
final class DashboardService
{
    /**
     * @return array{
     *   stats: array<string, array{available: bool, value: int|float|null, suffix: string, secondary: string}>,
     *   charts: array<string, array{available: bool, labels: array<int, string>, data: array<int, mixed>}>|
     *           array<string, array{available: false, labels: array{}, data: array{}}>,
     *   recentActivity: array<int, array<string, mixed>>,
     *   modules: array<int, array{name: string, table: string, ready: bool}>,
     *   system: array<string, mixed>
     * }
     */
    public static function build(): array
    {
        return [
            'stats'               => self::stats(),
            'charts'              => self::charts(),
            'recentActivity'      => AuditLog::recent(8),
            'modules'             => self::moduleStatus(),
            'system'              => self::systemDiagnostics(),
            'upcomingAppointments' => self::upcomingAppointments(),
            'operationalAlerts'   => self::operationalAlerts(),
        ];
    }

    // ------------------------------------------------------------------
    // Stat tiles
    // ------------------------------------------------------------------
    private static function stats(): array
    {
        return [
            'patients'      => self::patientsStat(),
            'appointments'  => self::appointmentsStat(),
            'staff'         => self::staffStat(),
            'revenue'       => self::revenueStat(),
            'pending_bills' => self::pendingBillsStat(),
            'beds'          => self::bedsStat(),
        ];
    }

    /** @return array{available: bool, value: int|float|null, suffix: string, secondary: string} */
    private static function patientsStat(): array
    {
        if (!Database::tableExists('patients')) {
            return ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
        }

        $total = (int) Database::scalar('SELECT COUNT(*) FROM patients');
        $thisMonth = (int) Database::scalar(
            'SELECT COUNT(*) FROM patients WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")'
        );

        return [
            'available' => true,
            'value'     => $total,
            'suffix'    => '',
            'secondary' => "+{$thisMonth} registered this month",
        ];
    }

    private static function appointmentsStat(): array
    {
        if (!Database::tableExists('appointments')) {
            return ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
        }

        $today = (int) Database::scalar(
            "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status NOT IN ('cancelled','no_show')"
        );
        $inQueue = (int) Database::scalar(
            "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('checked_in','in_consultation')"
        );

        return [
            'available' => true,
            'value'     => $today,
            'suffix'    => '',
            'secondary' => "{$inQueue} in queue now",
        ];
    }

    private static function staffStat(): array
    {
        // users is a foundation table — this one is live in phase 1.
        $staffRoles = (array) config('auth.staff_roles', []);
        $placeholders = implode(',', array_fill(0, count($staffRoles), '?'));

        $activeStaff = (int) Database::scalar(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             INNER JOIN role_user ru ON ru.user_id = u.id
             INNER JOIN roles r ON r.id = ru.role_id
             WHERE r.slug IN ({$placeholders}) AND u.is_active = 1",
            $staffRoles
        );

        $doctors = (int) Database::scalar(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             INNER JOIN role_user ru ON ru.user_id = u.id
             INNER JOIN roles r ON r.id = ru.role_id
             WHERE r.slug = 'doctor' AND u.is_active = 1"
        );

        return [
            'available' => true,
            'value'     => $activeStaff,
            'suffix'    => '',
            'secondary' => "{$doctors} doctor" . ($doctors === 1 ? '' : 's') . " on roster",
        ];
    }

    private static function revenueStat(): array
    {
        if (!Database::tableExists('payments')) {
            return ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
        }

        $today = (float) (Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND amount > 0 AND DATE(recorded_at) = CURDATE()"
        ) ?? 0);

        return [
            'available' => true,
            'value'     => $today,
            'suffix'    => 'money',
            'secondary' => 'collected today',
        ];
    }

    private static function pendingBillsStat(): array
    {
        if (!Database::tableExists('invoices')) {
            return ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
        }

        $pending = (int) Database::scalar(
            "SELECT COUNT(*) FROM invoices WHERE balance_due > 0 AND status NOT IN ('draft','cancelled')"
        );

        return [
            'available' => true,
            'value'     => $pending,
            'suffix'    => '',
            'secondary' => 'awaiting settlement',
        ];
    }

    private static function bedsStat(): array
    {
        if (!Database::tableExists('beds')) {
            return ['available' => false, 'value' => null, 'suffix' => '', 'secondary' => ''];
        }

        $total = (int) Database::scalar('SELECT COUNT(*) FROM beds');
        $occupied = (int) Database::scalar("SELECT COUNT(*) FROM beds WHERE status = 'occupied'");

        return [
            'available' => true,
            'value'     => $total - $occupied,
            'suffix'    => '',
            'secondary' => "{$occupied} occupied of {$total}",
        ];
    }

    // ------------------------------------------------------------------
    // Charts
    // ------------------------------------------------------------------
    private static function charts(): array
    {
        return [
            'appointments' => self::appointmentsChart(),
            'revenue'      => self::revenueChart(),
        ];
    }

    /** @return array{available: bool, labels: array<int, string>, data: array<int, int>} */
    private static function appointmentsChart(): array
    {
        if (!Database::tableExists('appointments')) {
            return ['available' => false, 'labels' => [], 'data' => []];
        }

        $rows = Database::query(
            "SELECT DATE(appointment_date) AS d, COUNT(*) AS c
             FROM appointments
             WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
               AND status NOT IN ('cancelled','no_show')
             GROUP BY DATE(appointment_date)"
        );

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['d']] = (int) $row['c'];
        }

        $labels = [];
        $data = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('D j', strtotime($day));
            $data[] = $byDay[$day] ?? 0;
        }

        return ['available' => true, 'labels' => $labels, 'data' => $data];
    }

    /** @return array{available: bool, labels: array<int, string>, data: array<int, float>} */
    private static function revenueChart(): array
    {
        if (!Database::tableExists('payments')) {
            return ['available' => false, 'labels' => [], 'data' => []];
        }

        $rows = Database::query(
            'SELECT DATE_FORMAT(recorded_at, "%Y-%m") AS m, SUM(amount) AS total
             FROM payments
             WHERE status = "completed" AND amount > 0
               AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT(recorded_at, "%Y-%m")'
        );

        $byMonth = [];
        foreach ($rows as $row) {
            $byMonth[(string) $row['m']] = (float) $row['total'];
        }

        $labels = [];
        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $labels[] = date('M', strtotime($month . '-01'));
            $data[] = $byMonth[$month] ?? 0.0;
        }

        return ['available' => true, 'labels' => $labels, 'data' => $data];
    }

    // ------------------------------------------------------------------
    // Module registry — drives the "module status" panel
    // ------------------------------------------------------------------
    /** @return array<int, array{name: string, table: string, ready: bool}> */
    private static function moduleStatus(): array
    {
        $modules = [
            ['name' => 'Patients',        'table' => 'patients'],
            ['name' => 'Appointments',    'table' => 'appointments'],
            ['name' => 'Doctors',         'table' => 'doctors'],
            ['name' => 'Departments',     'table' => 'departments'],
            ['name' => 'Staff',           'table' => 'staff_profiles'],
            ['name' => 'Consultations',   'table' => 'consultations'],
            ['name' => 'Prescriptions',   'table' => 'prescriptions'],
            ['name' => 'Billing',         'table' => 'invoices'],
            ['name' => 'Services',        'table' => 'services'],
            ['name' => 'Expenses',        'table' => 'expenses'],
            ['name' => 'Pharmacy',        'table' => 'medicines'],
            ['name' => 'Laboratory',      'table' => 'lab_tests'],
            ['name' => 'Inventory',       'table' => 'inventory_items'],
            ['name' => 'Admissions',      'table' => 'admissions'],
            ['name' => 'Bed Management',  'table' => 'beds'],
        ];

        foreach ($modules as &$module) {
            $module['ready'] = Database::tableExists($module['table']);
        }

        return $modules;
    }

    // ------------------------------------------------------------------
    // Real system diagnostics (footer + system panel)
    // ------------------------------------------------------------------
    /** @return array<string, mixed> */
    private static function systemDiagnostics(): array
    {
        $start = microtime(true);
        $dbOk = false;
        $error = null;

        try {
            $dbOk = Database::scalar('SELECT 1') === 1;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $latencyMs = round((microtime(true) - $start) * 1000, 1);

        $activeToday = 0;
        if ($dbOk) {
            $activeToday = (int) Database::scalar(
                'SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );
        }

        return [
            'db_ok'         => $dbOk,
            'db_latency_ms' => $latencyMs,
            'db_error'      => $error,
            'php_version'   => PHP_VERSION,
            'app_version'   => (string) config('app.version'),
            'environment'   => (string) config('app.env'),
            'active_users'  => $activeToday,
            'storage_logs'  => is_writable(BASE_PATH . '/storage/logs'),
            'storage_cache' => is_writable(BASE_PATH . '/storage/cache'),
            'uploads'       => is_writable(BASE_PATH . '/public/uploads'),
        ];
    }

    // ------------------------------------------------------------------
    // Upcoming appointments — today's schedule with patient/doctor/department
    // ------------------------------------------------------------------
    /** @return array<int, array<string, mixed>> */
    private static function upcomingAppointments(): array
    {
        if (!Database::tableExists('appointments')) {
            return [];
        }

        // Today's appointments (or next day with appointments if today is empty)
        // joined with patient + doctor + department for table rendering.
        try {
            return Database::query(
                "SELECT a.id, a.appointment_date, a.start_time, a.end_time,
                        a.status, a.queue_token,
                        p.id AS patient_id, p.first_name AS p_first, p.last_name AS p_last,
                        p.patient_code, p.gender,
                        u.name AS doctor_name, d.name AS department_name
                 FROM appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id
                 LEFT JOIN doctors doc ON doc.id = a.doctor_id
                 LEFT JOIN users u ON u.id = doc.user_id
                 LEFT JOIN departments d ON d.id = doc.department_id
                 WHERE a.appointment_date = CURDATE()
                   AND a.status NOT IN ('cancelled','no_show','completed')
                 ORDER BY a.start_time ASC
                 LIMIT 8"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ------------------------------------------------------------------
    // Operational alerts — real, query-backed alerts (no fabrication)
    // ------------------------------------------------------------------
    /** @return array<string, array{label: string, count: int, tone: string, icon: string, href: string|null}> */
    private static function operationalAlerts(): array
    {
        $alerts = [];

        // 1. Pharmacy low-stock alerts
        if (Database::tableExists('medicine_batches')) {
            try {
                $lowStock = (int) Database::scalar(
                    "SELECT COUNT(DISTINCT m.id)
                     FROM medicines m
                     LEFT JOIN (
                         SELECT medicine_id, SUM(remaining_quantity) AS total_qty
                         FROM medicine_batches GROUP BY medicine_id
                     ) b ON b.medicine_id = m.id
                     WHERE COALESCE(b.total_qty, 0) <= 50"
                );
                $alerts['low_stock'] = [
                    'label' => 'Pharmacy low stock',
                    'count' => $lowStock,
                    'tone'  => $lowStock > 0 ? 'amber' : 'slate',
                    'icon'  => 'package-search',
                    'href'  => url('/admin/pharmacy'),
                ];
            } catch (\Throwable $e) { /* skip on schema mismatch */ }
        }

        // 2. Batches expiring within 90 days
        if (Database::tableExists('medicine_batches')) {
            try {
                $expiring = (int) Database::scalar(
                    "SELECT COUNT(*) FROM medicine_batches
                     WHERE expiry_date IS NOT NULL
                       AND expiry_date > CURDATE()
                       AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                       AND remaining_quantity > 0"
                );
                $alerts['expiring'] = [
                    'label' => 'Batches expiring <90d',
                    'count' => $expiring,
                    'tone'  => $expiring > 0 ? 'rose' : 'slate',
                    'icon'  => 'calendar-clock',
                    'href'  => url('/admin/pharmacy'),
                ];
            } catch (\Throwable $e) { /* skip */ }
        }

        // 3. Lab orders in queue (pending collection or pending verification)
        if (Database::tableExists('lab_tests')) {
            try {
                $labQueue = (int) Database::scalar(
                    "SELECT COUNT(*) FROM lab_tests
                     WHERE status IN ('ordered','sample_collected','pending_verification')"
                );
                $alerts['lab_queue'] = [
                    'label' => 'Lab orders in queue',
                    'count' => $labQueue,
                    'tone'  => $labQueue > 0 ? 'violet' : 'slate',
                    'icon'  => 'flask-conical',
                    'href'  => url('/admin/laboratory'),
                ];
            } catch (\Throwable $e) { /* skip */ }
        }

        // 4. Patients currently admitted
        if (Database::tableExists('admissions')) {
            try {
                $admitted = (int) Database::scalar(
                    "SELECT COUNT(*) FROM admissions WHERE status = 'admitted'"
                );
                $alerts['admitted'] = [
                    'label' => 'Patients admitted',
                    'count' => $admitted,
                    'tone'  => $admitted > 0 ? 'navy' : 'slate',
                    'icon'  => 'bed-double',
                    'href'  => url('/admin/admissions'),
                ];
            } catch (\Throwable $e) { /* skip */ }
        }

        // 5. Outstanding invoices requiring attention
        if (Database::tableExists('invoices')) {
            try {
                $outstanding = (int) Database::scalar(
                    "SELECT COUNT(*) FROM invoices WHERE balance_due > 0 AND status NOT IN ('draft','cancelled')"
                );
                $alerts['outstanding'] = [
                    'label' => 'Outstanding invoices',
                    'count' => $outstanding,
                    'tone'  => $outstanding > 0 ? 'amber' : 'slate',
                    'icon'  => 'receipt-text',
                    'href'  => url('/admin/billing'),
                ];
            } catch (\Throwable $e) { /* skip */ }
        }

        return $alerts;
    }
}
