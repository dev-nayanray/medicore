<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Prescription;
use App\Services\AuditService;

/**
 * Prescription standalone — detail view + printable layout.
 * Creation/editing happens inline on the consultation page via
 * ConsultationController; this controller handles the read-only
 * detail and print views.
 */
final class PrescriptionController extends Controller
{
    public function show(Request $request, string $id): string
    {
        $rx = Prescription::profile((int) $id);
        if ($rx === null) {
            throw HttpException::notFound('Prescription not found.');
        }
        return Response::html(view('admin/prescriptions/show', [
            'rx' => $rx,
        ]));
    }

    public function print(Request $request, string $id): string
    {
        $rx = Prescription::profile((int) $id);
        if ($rx === null) {
            throw HttpException::notFound('Prescription not found.');
        }
        if ($rx['status'] === 'draft') {
            Session::flash('error', 'Finalize the prescription before printing.');
            return Response::redirect(url('/admin/consultations/' . $rx['consultation_id']));
        }

        AuditService::log('prescription.printed', 'prescriptions', 'view', "Printed prescription {$rx['prescription_code']}.", [
            'prescription_id' => $id,
        ], $request);

        return Response::html(view('admin/prescriptions/print', [
            'rx' => $rx,
        ]));
    }
}
