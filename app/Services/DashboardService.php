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
            'stats'          => self::stats(),
            'charts'         => self::charts(),
            'recentActivity' => AuditLog::recent(8),
            'modules'        => self::moduleStatus(),
            'system'         => self::systemDiagnostics(),
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
            'SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()'
        );

        return [
            'available' => true,
            'value'     => $today,
            'suffix'    => '',
            'secondary' => 'scheduled today',
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
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(paid_at) = CURDATE()'
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
            "SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid', 'partial')"
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
            'SELECT DATE(appointment_date) AS d, COUNT(*) AS c
             FROM appointments
             WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(appointment_date)'
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
            'SELECT DATE_FORMAT(paid_at, "%Y-%m") AS m, SUM(amount) AS total
             FROM payments
             WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT(paid_at, "%Y-%m")'
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
            ['name' => 'Bed management',  'table' => 'beds'],
            ['name' => 'Laboratory',      'table' => 'lab_tests'],
            ['name' => 'Pharmacy',        'table' => 'medicines'],
            ['name' => 'Billing & payments', 'table' => 'invoices'],
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
}
