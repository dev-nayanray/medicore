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
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AuditService;
use App\Services\ConsultationService;
use App\Services\PrescriptionService;
use App\Services\SettingService;

/**
 * Consultation management — doctor workspace, create from appointment
 * or walk-in, draft/finalize lifecycle, amendments, attachments.
 */
final class ConsultationController extends Controller
{
    /**
     * Doctor workspace — shows today's consultations + quick actions.
     */
    public function workspace(Request $request): string
    {
        $doctor = $this->currentDoctor();
        $today = date('Y-m-d');

        $consultations = $doctor !== null
            ? Consultation::forDoctorOnDate($doctor['id'], $today)
            : Consultation::directory(['date_from' => $today, 'date_to' => $today], 1, 50)['rows'];

        $counts = Consultation::counts();
        $rxCounts = Database::tableExists('prescriptions') ? \App\Models\Prescription::counts() : ['drafts' => 0, 'today' => 0];

        return Response::html(view('admin/consultations/workspace', [
            'doctor'        => $doctor,
            'today'         => $today,
            'consultations' => $consultations,
            'counts'        => $counts,
            'rxCounts'      => $rxCounts,
        ]));
    }

    /**
     * Consultation directory — filters, sorting, pagination.
     */
    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 15)));

        $data = Consultation::directory($filters, $page, $perPage);
        $counts = Consultation::counts();
        $doctors = Doctor::options();

        $query = http_build_query(array_filter([
            'q' => $filters['search'], 'status' => $filters['status'],
            'patient_id' => $filters['patient_id'], 'doctor_id' => $filters['doctor_id'],
            'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
        ], static fn ($v) => $v !== '' && $v !== null));
        $baseUrl = url('/admin/consultations') . ($query !== '' ? '?' . $query . '&' : '?');

        return Response::html(view('admin/consultations/index', [
            'consultations' => $data['rows'],
            'total'         => $data['total'],
            'page'          => $data['page'],
            'pages'         => $data['pages'],
            'perPage'       => $data['perPage'],
            'filters'       => $filters,
            'counts'        => $counts,
            'doctors'       => $doctors,
            'baseUrl'       => $baseUrl,
        ]));
    }

    public function create(Request $request): string
    {
        $appointmentId = (int) $request->query('appointment_id', 0) ?: null;
        $patientId = (int) $request->query('patient_id', 0) ?: null;

        $apt = null;
        if ($appointmentId !== null) {
            $apt = Appointment::find($appointmentId);
            if ($apt !== null) {
                $patientId = (int) $apt['patient_id'];
            }
        }

        $patient = $patientId > 0 ? Patient::find($patientId) : null;
        $doctor = $this->currentDoctor();

        return Response::html(view('admin/consultations/form', [
            'consultation'  => null,
            'appointment'   => $apt,
            'patient'       => $patient,
            'doctor'        => $doctor,
            'doctors'       => Doctor::options(),
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'patient_id'       => 'required|integer',
            'doctor_id'        => 'required|integer',
            'appointment_id'   => 'nullable|integer',
            'chief_complaint'  => 'nullable|max:500',
            'history_presenting' => 'nullable|max:5000',
            'symptoms'         => 'nullable|max:5000',
            'observations'     => 'nullable|max:5000',
            'clinical_notes'   => 'nullable|max:10000',
            'diagnoses'        => 'nullable|max:5000',
            'follow_up_date'   => 'nullable|date',
            'referral_to'      => 'nullable|max:150',
            'referral_reason'  => 'nullable|max:1000',
        ]);
        $r = ConsultationService::create($result['data'], $request);
        if (!$r['ok']) {
            Session::flash('error', $r['error'] ?? 'Could not create consultation.');
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/consultations/create'));
        }
        Session::flash('success', "Consultation {$r['code']} created as draft.");
        return Response::redirect(url('/admin/consultations/' . $r['id']));
    }

    public function show(Request $request, string $id): string
    {
        $con = Consultation::profile((int) $id);
        if ($con === null) {
            throw HttpException::notFound('Consultation not found.');
        }
        $age = $con['date_of_birth'] !== null ? (int) Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$con['date_of_birth']]) : null;

        return Response::html(view('admin/consultations/show', [
            'consultation' => $con,
            'age'          => $age,
            'patientHistory' => Consultation::forPatient((int) $con['patient_id']),
        ]));
    }

    public function edit(Request $request, string $id): string
    {
        $con = Consultation::profile((int) $id);
        if ($con === null) {
            throw HttpException::notFound('Consultation not found.');
        }
        if ($con['status'] !== 'draft') {
            Session::flash('error', 'Finalized consultations cannot be edited. Use the amend action for corrections.');
            return Response::redirect(url('/admin/consultations/' . $id));
        }
        $age = $con['date_of_birth'] !== null ? (int) Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$con['date_of_birth']]) : null;

        return Response::html(view('admin/consultations/form', [
            'consultation'  => $con,
            'appointment'   => null,
            'patient'       => $con,
            'doctor'        => $this->currentDoctor(),
            'doctors'       => Doctor::options(),
            'age'           => $age,
        ]));
    }

    public function update(Request $request, string $id): string
    {
        $result = $this->validate($request, [
            'chief_complaint'  => 'nullable|max:500',
            'history_presenting' => 'nullable|max:5000',
            'symptoms'         => 'nullable|max:5000',
            'observations'     => 'nullable|max:5000',
            'clinical_notes'   => 'nullable|max:10000',
            'diagnoses'        => 'nullable|max:5000',
            'follow_up_date'   => 'nullable|date',
            'referral_to'      => 'nullable|max:150',
            'referral_reason'  => 'nullable|max:1000',
        ]);
        $r = ConsultationService::update((int) $id, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Consultation updated.' : ($r['error'] ?? 'Update failed.'));
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    public function finalize(Request $request, string $id): string
    {
        $r = ConsultationService::finalize((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Consultation finalized. Record is now locked.' : (string) $r['error']);
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    public function amend(Request $request, string $id): string
    {
        $result = $this->validate($request, [
            'field'   => 'required|max:100',
            'value'   => 'nullable|max:10000',
            'reason'  => 'required|min:5|max:500',
        ]);
        $r = ConsultationService::amend((int) $id, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Amendment recorded.' : (string) $r['error']);
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    // ------------------------------------------------------------------
    // Attachments
    // ------------------------------------------------------------------
    public function uploadAttachment(Request $request, string $id): string
    {
        $r = ConsultationService::storeAttachment(
            (int) $id,
            $_FILES['attachment'] ?? [],
            (string) ($request->input('title') ?? ''),
            $request
        );
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Attachment uploaded.' : (string) $r['error']);
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    public function downloadAttachment(Request $request, string $id, string $attId): string
    {
        $att = ConsultationService::attachmentDownloadPath((int) $attId);
        if (!isset($att['file'])) {
            throw HttpException::notFound('Attachment not found.');
        }
        AuditService::log('consultation.attachment_downloaded', 'consultations', 'view', "Downloaded attachment \"{$att['name']}\".", [], $request);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: ' . $att['mime']);
        header('Content-Disposition: inline; filename="' . rawurlencode($att['name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($att['file']);
        exit;
    }

    public function deleteAttachment(Request $request, string $id, string $attId): string
    {
        $r = ConsultationService::deleteAttachment((int) $attId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Attachment deleted.' : (string) $r['error']);
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    // ------------------------------------------------------------------
    // Prescriptions (inline create/update for the consultation page)
    // ------------------------------------------------------------------
    public function storePrescription(Request $request, string $id): string
    {
        $items = $this->collectPrescriptionItems($request);
        $notes = (string) $request->input('rx_notes', '');
        $r = PrescriptionService::create((int) $id, $items, $notes, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Prescription {$r['code']} created." : (string) $r['error']);
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    public function updatePrescription(Request $request, string $id, string $rxId): string
    {
        $items = $this->collectPrescriptionItems($request);
        $notes = (string) $request->input('rx_notes', '');
        $r = PrescriptionService::update((int) $rxId, $items, $notes, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Prescription updated.' : (string) $r['error']);
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    public function finalizePrescription(Request $request, string $id, string $rxId): string
    {
        $r = PrescriptionService::finalize((int) $rxId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Prescription finalized.' : (string) $r['error']);
        return Response::redirect(url('/admin/consultations/' . $id));
    }

    /** @return array<int, array<string, mixed>> */
    private function collectPrescriptionItems(Request $request): array
    {
        $names = (array) $request->input('medicine_name', []);
        $dosages = (array) $request->input('dosage', []);
        $frequencies = (array) $request->input('frequency', []);
        $durations = (array) $request->input('duration', []);
        $quantities = (array) $request->input('quantity', []);
        $instructions = (array) $request->input('instructions', []);

        $items = [];
        for ($i = 0; $i < count($names); $i++) {
            if (empty($names[$i])) continue;
            $items[] = [
                'medicine_name' => $names[$i],
                'dosage'        => $dosages[$i] ?? '',
                'frequency'     => $frequencies[$i] ?? '',
                'duration'      => $durations[$i] ?? '',
                'quantity'      => !empty($quantities[$i]) ? (int) $quantities[$i] : null,
                'instructions'  => $instructions[$i] ?? '',
            ];
        }
        return $items;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    /** @return array<string, mixed>|null */
    private function currentDoctor(): ?array
    {
        $userId = Auth::id();
        if ($userId === null) return null;
        return Database::queryOne('SELECT * FROM doctors WHERE user_id = ? AND archived_at IS NULL', [$userId]);
    }

    /** @return array<string, mixed> */
    private function collectFilters(Request $request): array
    {
        return [
            'search'      => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'status'      => in_array($request->query('status', ''), Consultation::STATUSES, true) ? (string) $request->query('status') : '',
            'patient_id'  => (int) $request->query('patient_id', 0) ?: '',
            'doctor_id'   => (int) $request->query('doctor_id', 0) ?: '',
            'date_from'   => (string) $request->query('date_from', ''),
            'date_to'     => (string) $request->query('date_to', ''),
        ];
    }
}
