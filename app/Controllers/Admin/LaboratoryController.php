<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Services\AuditService;
use App\Services\LaboratoryService;

/**
 * Laboratory controller — work queue, order detail, result entry,
 * verification, release, report printing, critical alerts.
 */
final class LaboratoryController extends Controller
{
    public function index(Request $request): string
    {
        $status = (string) $request->query('status', 'all');
        $orders = LabOrder::workQueue($status);
        $counts = LabOrder::counts();
        $criticalAlerts = $status === 'all' || $status === 'critical'
            ? Database::query("SELECT lca.*, t.name AS test_name FROM lab_critical_alerts lca INNER JOIN lab_order_items oi ON oi.id = lca.order_item_id INNER JOIN lab_tests t ON t.id = oi.test_id WHERE lca.alert_status = 'pending' ORDER BY lca.created_at DESC")
            : [];

        return Response::html(view('admin/laboratory/index', [
            'orders' => $orders, 'counts' => $counts, 'status' => $status, 'criticalAlerts' => $criticalAlerts,
        ]));
    }

    public function create(Request $request): string
    {
        $prefillPatient = (int) $request->query('patient_id', 0) ?: null;
        $prefillConsultation = (int) $request->query('consultation_id', 0) ?: null;
        $tests = LabTest::groupedByCategory();
        return Response::html(view('admin/laboratory/create', ['tests' => $tests, 'patientId' => $prefillPatient, 'consultationId' => $prefillConsultation]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, ['patient_id' => 'required|integer', 'consultation_id' => 'nullable|integer', 'doctor_id' => 'nullable|integer', 'notes' => 'nullable|max:500']);
        $testIds = array_filter((array) $request->input('test_ids', []), static fn($v) => (int) $v > 0);
        if ($testIds === []) { Session::flash('error', 'Select at least one test.'); return Response::redirect(url('/admin/laboratory/create')); }
        $r = LaboratoryService::createOrder($result['data'], array_map('intval', $testIds), $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Lab order {$r['code']} created with invoice." : ($r['error'] ?? 'Failed.'));
        return Response::redirect($r['ok'] ? url('/admin/laboratory/' . $r['id']) : url('/admin/laboratory/create'));
    }

    public function show(Request $request, string $id): string
    {
        $order = LabOrder::profile((int) $id);
        if ($order === null) throw HttpException::notFound('Lab order not found.');
        return Response::html(view('admin/laboratory/show', ['order' => $order]));
    }

    public function collectSample(Request $request, string $id, string $itemId): string
    {
        $r = LaboratoryService::collectSample((int) $itemId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Sample collected.' : (string) $r['error']);
        return Response::redirect(url('/admin/laboratory/' . $id));
    }

    public function enterResult(Request $request, string $id, string $itemId): string
    {
        $result = $this->validate($request, ['result_value' => 'nullable|max:500', 'result_unit' => 'nullable|max:50', 'reference_range' => 'nullable|max:200', 'notes' => 'nullable|max:2000', 'is_critical' => 'nullable']);
        $r = LaboratoryService::enterResult((int) $itemId, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Result entered.' : (string) $r['error']);
        return Response::redirect(url('/admin/laboratory/' . $id));
    }

    public function verifyResult(Request $request, string $id, string $itemId): string
    {
        $r = LaboratoryService::verifyResult((int) $itemId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Result verified.' : (string) $r['error']);
        return Response::redirect(url('/admin/laboratory/' . $id));
    }

    public function releaseResult(Request $request, string $id, string $itemId): string
    {
        $r = LaboratoryService::releaseResult((int) $itemId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Result released.' : (string) $r['error']);
        return Response::redirect(url('/admin/laboratory/' . $id));
    }

    public function acknowledgeAlert(Request $request, string $id, string $alertId): string
    {
        $r = LaboratoryService::acknowledgeAlert((int) $alertId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Alert acknowledged.' : (string) $r['error']);
        return Response::redirect(url('/admin/laboratory/' . $id));
    }

    public function printReport(Request $request, string $id): string
    {
        $order = LabOrder::profile((int) $id);
        if ($order === null) throw HttpException::notFound('Lab order not found.');
        AuditService::log('lab.report_printed', 'laboratory', 'view', "Printed lab report {$order['order_code']}.", ['lab_order_id' => $id], $request);
        return Response::html(view('admin/laboratory/print', ['order' => $order]));
    }
}
