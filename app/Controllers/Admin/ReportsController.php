<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;

final class ReportsController extends Controller
{
    public function index(Request $request): string
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        $reportType = (string) $request->query('type', 'overview');

        $data = match ($reportType) {
            'patients' => $this->patientReport($from, $to),
            'appointments' => $this->appointmentReport($from, $to),
            'revenue' => $this->revenueReport($from, $to),
            'pharmacy' => $this->pharmacyReport($from, $to),
            'laboratory' => $this->labReport($from, $to),
            'admissions' => $this->admissionReport($from, $to),
            default => $this->overviewReport($from, $to),
        };

        return Response::html(view('admin/reports/index', ['from' => $from, 'to' => $to, 'type' => $reportType, 'data' => $data]));
    }

    public function export(Request $request): string
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        $type = (string) $request->query('type', 'overview');
        $data = match ($type) {
            'patients' => $this->patientReport($from, $to),
            'appointments' => $this->appointmentReport($from, $to),
            'revenue' => $this->revenueReport($from, $to),
            'pharmacy' => $this->pharmacyReport($from, $to),
            'laboratory' => $this->labReport($from, $to),
            'admissions' => $this->admissionReport($from, $to),
            default => $this->overviewReport($from, $to),
        };

        $out = fopen('php://temp', 'r+');
        if (!empty($data['rows'])) {
            $headers = array_keys($data['rows'][0]);
            fputcsv($out, $headers);
            foreach ($data['rows'] as $row) fputcsv($out, array_values($row));
        } else {
            fputcsv($out, ['No data for this report type in the selected range.']);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        AuditService::log('report.exported', 'reports', 'export', "Exported {$type} report (CSV) for {$from} to {$to}.", ['type' => $type, 'from' => $from, 'to' => $to], $request);
        return Response::with(200, 'text/csv; charset=utf-8', $csv, ['Content-Disposition' => 'attachment; filename="report-' . $type . '-' . date('Y-m-d') . '.csv"']);
    }

    private function overviewReport(string $from, string $to): array
    {
        $patients = (int) Database::scalar('SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN ? AND ?', [$from, $to]);
        $appointments = (int) Database::scalar("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status NOT IN ('cancelled')", [$from, $to]);
        $consultations = (int) Database::scalar('SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) BETWEEN ? AND ?', [$from, $to]);
        $revenue = (float) Database::scalar("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE invoice_date BETWEEN ? AND ? AND status NOT IN ('draft','cancelled')", [$from, $to]);
        $expenses = Database::tableExists('expenses') ? (float) Database::scalar('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?', [$from, $to]) : 0;
        $admissions = Database::tableExists('admissions') ? (int) Database::scalar('SELECT COUNT(*) FROM admissions WHERE DATE(admission_date) BETWEEN ? AND ?', [$from, $to]) : 0;
        return ['summary' => compact('patients', 'appointments', 'consultations', 'revenue', 'expenses', 'admissions'), 'rows' => []];
    }

    private function patientReport(string $from, string $to): array
    {
        $rows = Database::query('SELECT patient_code, first_name, last_name, gender, date_of_birth, phone, city, created_at FROM patients WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC', [$from, $to]);
        return ['rows' => $rows, 'title' => 'Patient registrations'];
    }

    private function appointmentReport(string $from, string $to): array
    {
        $rows = Database::query(
            'SELECT a.appointment_code, a.appointment_date, a.start_time, a.status, a.appointment_type,
                    CONCAT(p.first_name, " ", p.last_name) AS patient_name, u.name AS doctor_name
             FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN doctors doc ON doc.id = a.doctor_id
             LEFT JOIN users u ON u.id = doc.user_id
             WHERE a.appointment_date BETWEEN ? AND ?
             ORDER BY a.appointment_date DESC, a.start_time DESC', [$from, $to]
        );
        return ['rows' => $rows, 'title' => 'Appointments'];
    }

    private function revenueReport(string $from, string $to): array
    {
        $rows = Database::query(
            'SELECT i.invoice_code, i.invoice_date, i.total, i.paid_amount, i.balance_due, i.status,
                    CONCAT(p.first_name, " ", p.last_name) AS patient_name
             FROM invoices i
             LEFT JOIN patients p ON p.id = i.patient_id
             WHERE i.invoice_date BETWEEN ? AND ? AND i.status NOT IN ("draft","cancelled")
             ORDER BY i.invoice_date DESC', [$from, $to]
        );
        return ['rows' => $rows, 'title' => 'Revenue (invoices)'];
    }

    private function pharmacyReport(string $from, string $to): array
    {
        if (!Database::tableExists('medicines')) return ['rows' => [], 'title' => 'Pharmacy — module not installed'];
        $rows = Database::query(
            'SELECT d.dispensing_code, d.created_at, d.status,
                    CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    m.name AS medicine_name, di.quantity_dispensed, di.unit_price, di.quantity_returned
             FROM medicine_dispensings d
             LEFT JOIN patients p ON p.id = d.patient_id
             LEFT JOIN medicine_dispensing_items di ON di.dispensing_id = d.id
             LEFT JOIN medicines m ON m.id = di.medicine_id
             WHERE DATE(d.created_at) BETWEEN ? AND ?
             ORDER BY d.created_at DESC', [$from, $to]
        );
        return ['rows' => $rows, 'title' => 'Pharmacy dispensing'];
    }

    private function labReport(string $from, string $to): array
    {
        if (!Database::tableExists('lab_orders')) return ['rows' => [], 'title' => 'Laboratory — module not installed'];
        $rows = Database::query(
            'SELECT o.order_code, o.created_at, o.status,
                    CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    t.name AS test_name, oi.status AS test_status, oi.result_value, oi.is_critical
             FROM lab_orders o
             LEFT JOIN patients p ON p.id = o.patient_id
             LEFT JOIN lab_order_items oi ON oi.order_id = o.id
             LEFT JOIN lab_tests t ON t.id = oi.test_id
             WHERE DATE(o.created_at) BETWEEN ? AND ?
             ORDER BY o.created_at DESC', [$from, $to]
        );
        return ['rows' => $rows, 'title' => 'Laboratory tests'];
    }

    private function admissionReport(string $from, string $to): array
    {
        if (!Database::tableExists('admissions')) return ['rows' => [], 'title' => 'Admissions — module not installed'];
        $rows = Database::query(
            'SELECT a.admission_code, a.admission_date, a.status, a.admission_type,
                    CONCAT(p.first_name, " ", p.last_name) AS patient_name,
                    w.name AS ward_name, r.room_number, b.bed_number
             FROM admissions a
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN wards w ON w.id = a.ward_id
             LEFT JOIN rooms r ON r.id = a.room_id
             LEFT JOIN beds b ON b.id = a.bed_id
             WHERE DATE(a.admission_date) BETWEEN ? AND ?
             ORDER BY a.admission_date DESC', [$from, $to]
        );
        return ['rows' => $rows, 'title' => 'Admissions'];
    }
}
