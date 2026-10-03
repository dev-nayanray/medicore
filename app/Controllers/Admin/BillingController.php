<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\SettingService;

/**
 * Billing management — revenue dashboard, invoice CRUD, payment recording,
 * refunds, cancellation, reports, export.
 */
final class BillingController extends Controller
{
    /**
     * Revenue & payment dashboard.
     */
    public function dashboard(Request $request): string
    {
        $stats = Invoice::financialStats(date('Y-m-01'), date('Y-m-d'));
        $counts = Invoice::counts();
        $monthlyRevenue = Invoice::monthlyRevenue(12);
        $dailyCollection = Payment::dailyCollection(14);
        $expenseTotals = Database::tableExists('expenses') ? Expense::totalsForRange(date('Y-m-01'), date('Y-m-d')) : ['total' => 0, 'by_category' => []];
        $methodBreakdown = Payment::methodBreakdown(date('Y-m-01'), date('Y-m-d'));

        return Response::html(view('admin/billing/dashboard', [
            'stats'           => $stats,
            'counts'          => $counts,
            'monthlyRevenue'  => $monthlyRevenue,
            'dailyCollection' => $dailyCollection,
            'expenseTotals'   => $expenseTotals,
            'methodBreakdown' => $methodBreakdown,
        ]));
    }

    /**
     * Invoice directory with filters.
     */
    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 15)));

        $data = Invoice::directory($filters, $page, $perPage);
        $counts = Invoice::counts();

        $query = http_build_query(array_filter([
            'q' => $filters['search'], 'status' => $filters['status'],
            'patient_id' => $filters['patient_id'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
        ], static fn ($v) => $v !== '' && $v !== null));
        $baseUrl = url('/admin/billing') . ($query !== '' ? '?' . $query . '&' : '?');

        return Response::html(view('admin/billing/index', [
            'invoices' => $data['rows'],
            'total'    => $data['total'],
            'page'     => $data['page'],
            'pages'    => $data['pages'],
            'perPage'  => $data['perPage'],
            'filters'  => $filters,
            'counts'   => $counts,
            'baseUrl'  => $baseUrl,
        ]));
    }

    public function create(Request $request): string
    {
        $prefillPatient = (int) $request->query('patient_id', 0) ?: null;
        $prefillConsultation = (int) $request->query('consultation_id', 0) ?: null;

        $patient = $prefillPatient !== null ? Patient::find($prefillPatient) : null;

        return Response::html(view('admin/billing/form', [
            'invoice'    => null,
            'patient'    => $patient,
            'services'   => Service::groupedByCategory(),
            'taxRate'    => (float) SettingService::get('default_tax_rate', 0),
            'prefillConsultation' => $prefillConsultation,
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'patient_id'      => 'required|integer',
            'doctor_id'       => 'nullable|integer',
            'consultation_id' => 'nullable|integer',
            'appointment_id'  => 'nullable|integer',
            'invoice_date'    => 'required|date',
            'discount_percentage' => 'nullable|numeric',
            'tax_percentage'  => 'nullable|numeric',
            'notes'           => 'nullable|max:2000',
        ]);
        $items = $this->collectItems($request);
        if ($items === []) {
            Session::flash('error', 'Add at least one line item.');
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/billing/create'));
        }
        $r = BillingService::createInvoice($result['data'], $items, $request);
        if (!$r['ok']) {
            Session::flash('error', $r['error'] ?? 'Could not create invoice.');
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/billing/create'));
        }
        Session::flash('success', "Invoice {$r['code']} created.");
        return Response::redirect(url('/admin/billing/' . $r['id']));
    }

    public function show(Request $request, string $id): string
    {
        $inv = Invoice::profile((int) $id);
        if ($inv === null) {
            throw HttpException::notFound('Invoice not found.');
        }
        $age = $inv['date_of_birth'] !== null ? (int) Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$inv['date_of_birth']]) : null;

        return Response::html(view('admin/billing/show', [
            'invoice' => $inv,
            'age'     => $age,
        ]));
    }

    public function print(Request $request, string $id): string
    {
        $inv = Invoice::profile((int) $id);
        if ($inv === null) {
            throw HttpException::notFound('Invoice not found.');
        }
        AuditService::log('invoice.printed', 'billing', 'view', "Printed invoice {$inv['invoice_code']}.", [
            'invoice_id' => $id,
        ], $request);
        return Response::html(view('admin/billing/print', [
            'invoice' => $inv,
        ]));
    }

    public function recordPayment(Request $request, string $id): string
    {
        $result = $this->validate($request, [
            'amount'          => 'required|numeric',
            'payment_method'  => 'required|in:cash,card,mobile_banking,bank_transfer,insurance,other',
            'reference_number'=> 'nullable|max:100',
            'recorded_at'     => 'nullable',
        ]);
        $r = BillingService::recordPayment((int) $id, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Payment {$r['code']} recorded." : ($r['error'] ?? 'Payment failed.'));
        return Response::redirect(url('/admin/billing/' . $id));
    }

    public function recordRefund(Request $request, string $id): string
    {
        $result = $this->validate($request, [
            'amount'          => 'required|numeric',
            'payment_method'  => 'required|in:cash,card,mobile_banking,bank_transfer,insurance,other',
            'reference_number'=> 'nullable|max:100',
            'refund_reason'   => 'required|min:5|max:300',
            'recorded_at'     => 'nullable',
        ]);
        $r = BillingService::recordRefund((int) $id, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Refund {$r['code']} recorded." : ($r['error'] ?? 'Refund failed.'));
        return Response::redirect(url('/admin/billing/' . $id));
    }

    public function cancel(Request $request, string $id): string
    {
        $reason = (string) $request->input('cancellation_reason', '');
        $r = BillingService::cancelInvoice((int) $id, $reason, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Invoice cancelled.' : (string) $r['error']);
        return Response::redirect(url('/admin/billing/' . $id));
    }

    public function updateNotes(Request $request, string $id): string
    {
        $notes = (string) $request->input('notes', '');
        $r = BillingService::updateNotes((int) $id, $notes, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Notes saved.' : (string) $r['error']);
        return Response::redirect(url('/admin/billing/' . $id));
    }

    public function reports(Request $request): string
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));

        $stats = Invoice::financialStats($from, $to);
        $expenses = Expense::totalsForRange($from, $to);
        $dailyCollection = Payment::dailyCollection(14);
        $methodBreakdown = Payment::methodBreakdown($from, $to);
        $net = $stats['total_collected'] - $expenses['total'];

        return Response::html(view('admin/billing/reports', [
            'from'            => $from,
            'to'              => $to,
            'stats'           => $stats,
            'expenses'         => $expenses,
            'net'             => $net,
            'dailyCollection' => $dailyCollection,
            'methodBreakdown' => $methodBreakdown,
        ]));
    }

    public function export(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $rows = Invoice::directory($filters, 1, 100000)['rows'];

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Invoice', 'Date', 'Patient', 'Patient ID', 'Total', 'Paid', 'Balance', 'Status', 'Created']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['invoice_code'], $r['invoice_date'],
                $r['patient_name'] ?? '—', $r['patient_code'] ?? '—',
                $r['total'], $r['paid_amount'], $r['balance_due'],
                $r['status'],
                date('Y-m-d H:i', strtotime((string) $r['created_at'])),
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        AuditService::log('billing.exported', 'billing', 'export', 'Exported invoice directory (CSV).', [
            'filters' => array_filter($filters, static fn ($v) => $v !== '' && $v !== null),
        ], $request);

        return Response::with(200, 'text/csv; charset=utf-8', $csv, [
            'Content-Disposition' => 'attachment; filename="invoices-' . date('Y-m-d-Hi') . '.csv"',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function collectItems(Request $request): array
    {
        $descriptions = (array) $request->input('description', []);
        $serviceIds = (array) $request->input('service_id', []);
        $quantities = (array) $request->input('quantity', []);
        $prices = (array) $request->input('unit_price', []);
        $discounts = (array) $request->input('item_discount', []);

        $items = [];
        for ($i = 0; $i < count($descriptions); $i++) {
            if (empty($descriptions[$i])) continue;
            $items[] = [
                'service_id' => !empty($serviceIds[$i]) ? (int) $serviceIds[$i] : null,
                'description' => $descriptions[$i],
                'quantity' => $quantities[$i] ?? 1,
                'unit_price' => $prices[$i] ?? 0,
                'discount_amount' => $discounts[$i] ?? 0,
            ];
        }
        return $items;
    }

    /** @return array<string, mixed> */
    private function collectFilters(Request $request): array
    {
        return [
            'search'      => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'status'      => in_array($request->query('status', ''), Invoice::STATUSES, true) ? (string) $request->query('status') : '',
            'patient_id'  => (int) $request->query('patient_id', 0) ?: '',
            'date_from'   => (string) $request->query('date_from', ''),
            'date_to'     => (string) $request->query('date_to', ''),
        ];
    }
}
