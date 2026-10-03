<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Patient;
use App\Services\AuditService;
use App\Services\PatientService;
use App\Services\SettingService;

/**
 * Patient management — directory, profile, CRUD, documents, visits,
 * CSV export and printable summaries. Every route is permission-gated
 * (patients.view / create / update) via route middleware.
 */
final class PatientController extends Controller
{
    private const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 10)));

        $data = Patient::directory($filters, $page, $perPage);
        $counts = Patient::counts();

        return Response::html(view('admin/patients/index', [
            'patients'    => $data['rows'],
            'total'       => $data['total'],
            'page'        => $data['page'],
            'pages'       => $data['pages'],
            'perPage'     => $data['perPage'],
            'filters'     => $filters,
            'counts'      => $counts,
            'bloodGroups' => self::BLOOD_GROUPS,
            'baseUrl'     => url('/admin/patients') . '?' . http_build_query(array_filter([
                'search' => $filters['search'], 'gender' => $filters['gender'],
                'blood_group' => $filters['blood_group'], 'status' => $filters['status'],
                'sort' => $filters['sort'], 'dir' => $filters['dir'],
            ], static fn ($v) => $v !== '' && $v !== null)) . '&',
        ]));
    }

    // ------------------------------------------------------------------
    // Create / edit
    // ------------------------------------------------------------------
    public function create(Request $request): string
    {
        return Response::html(view('admin/patients/form', [
            'patient'     => null,
            'bloodGroups' => self::BLOOD_GROUPS,
            'documentTypes' => PatientService::DOCUMENT_TYPES,
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, $this->rules());
        $data = $this->normalize($result['data']);

        // Duplicate gate — warn before the first insert (ignore on explicit override).
        $duplicates = Patient::findPossibleDuplicates($data);
        if ($duplicates !== [] && $request->input('confirm_dupes') !== '1') {
            Session::put('_errors', ['duplicates' => count($duplicates)]);
            Session::put('_patient_duplicates', $duplicates);
            Session::flashInput($request->all());
            Session::flash('error', 'Possible duplicate patient detected — review the warnings below.');
            return Response::redirect(url('/admin/patients/create'));
        }

        $created = PatientService::create($data, $request);
        Session::flash('success', "Patient registered with ID {$created['code']}.");

        // Optional immediate document upload on create.
        if (!empty($_FILES['document']['name'])) {
            $docResult = PatientService::storeDocument(
                (int) $created['id'],
                $_FILES['document'],
                (string) ($request->input('document_type') ?? 'other'),
                (string) ($request->input('document_title') ?? ''),
                $request
            );
            if (!$docResult['ok']) {
                Session::flash('error', 'Patient saved, but the document was rejected: ' . $docResult['error']);
            }
        }

        return Response::redirect(url('/admin/patients/' . $created['id']));
    }

    public function edit(Request $request, string $id): string
    {
        $patient = Patient::find((int) $id);
        if ($patient === null) {
            throw HttpException::notFound('Patient not found.');
        }

        return Response::html(view('admin/patients/form', [
            'patient'     => $patient,
            'bloodGroups' => self::BLOOD_GROUPS,
            'documentTypes' => PatientService::DOCUMENT_TYPES,
        ]));
    }

    public function update(Request $request, string $id): string
    {
        $patientId = (int) $id;
        if (Patient::find($patientId) === null) {
            throw HttpException::notFound('Patient not found.');
        }

        $result = $this->validate($request, $this->rules($patientId));
        $data = $this->normalize($result['data']);

        $duplicates = Patient::findPossibleDuplicates($data, $patientId);
        if ($duplicates !== [] && $request->input('confirm_dupes') !== '1') {
            Session::put('_errors', ['duplicates' => count($duplicates)]);
            Session::put('_patient_duplicates', $duplicates);
            Session::flashInput($request->all());
            Session::flash('error', 'Possible duplicate detected — review the warnings below.');
            return Response::redirect(url('/admin/patients/' . $patientId . '/edit'));
        }

        $updated = PatientService::update($patientId, $data, $request);
        if (!$updated['ok']) {
            Session::flash('error', $updated['error'] ?? 'Update failed.');
            return Response::redirect(url('/admin/patients/' . $patientId . '/edit'));
        }

        Session::flash('success', 'Patient record updated.');
        return Response::redirect(url('/admin/patients/' . $patientId));
    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------
    public function show(Request $request, string $id): string
    {
        $patientId = (int) $id;
        $patient = Patient::find($patientId);
        if ($patient === null) {
            throw HttpException::notFound('Patient not found.');
        }

        return Response::html(view('admin/patients/show', [
            'patient'       => $patient,
            'age'           => (int) Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$patient['date_of_birth']]),
            'visits'        => Patient::visits($patientId),
            'documents'     => Patient::documents($patientId),
            'doctorOptions' => $this->doctorOptions(),
            'documentTypes' => PatientService::DOCUMENT_TYPES,
            'visitTypes'    => ['outpatient', 'inpatient', 'emergency', 'follow-up', 'telemedicine'],
            'tab'           => $this->validTab((string) $request->query('tab', 'overview')),
        ]));
    }

    public function printSummary(Request $request, string $id): string
    {
        $patientId = (int) $id;
        $patient = Patient::find($patientId);
        if ($patient === null) {
            throw HttpException::notFound('Patient not found.');
        }

        AuditService::log('patient.summary_printed', 'patients', 'view', "Printed summary for {$patient['patient_code']}.", [
            'patient_id' => $patientId,
        ], $request);

        return Response::html(view('admin/patients/print', [
            'patient' => $patient,
            'age'     => (int) Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$patient['date_of_birth']]),
            'visits'  => Patient::visits($patientId),
        ]));
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------
    public function archive(Request $request, string $id): string
    {
        $r = PatientService::archive((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Patient archived — history preserved.' : (string) $r['error']);
        return Response::redirect(url('/admin/patients'));
    }

    public function restore(Request $request, string $id): string
    {
        $r = PatientService::restore((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Patient restored.' : (string) $r['error']);
        return Response::redirect(url('/admin/patients/' . $id));
    }

    // ------------------------------------------------------------------
    // Visits & documents
    // ------------------------------------------------------------------
    public function storeVisit(Request $request, string $id): string
    {
        $patientId = (int) $id;
        $result = $this->validate($request, [
            'visited_at'       => 'required|date',
            'visit_type'       => 'required|in:outpatient,inpatient,emergency,follow-up,telemedicine',
            'chief_complaint'  => 'required|min:3|max:500',
            'diagnosis'        => 'nullable|max:500',
            'notes'            => 'nullable|max:2000',
            'status'           => 'required|in:scheduled,completed,cancelled',
        ]);
        $data = $result['data'];
        $data['doctor_id'] = $request->input('doctor_id');

        $r = PatientService::addVisit($patientId, $data, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Visit recorded.' : (string) $r['error']);
        return Response::redirect(url('/admin/patients/' . $patientId . '?tab=visits'));
    }

    public function uploadDocument(Request $request, string $id): string
    {
        $r = PatientService::storeDocument(
            (int) $id,
            $_FILES['document'] ?? [],
            (string) ($request->input('document_type') ?? 'other'),
            (string) ($request->input('document_title') ?? ''),
            $request
        );
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Document uploaded.' : (string) $r['error']);
        return Response::redirect(url('/admin/patients/' . $id . '?tab=documents'));
    }

    /** Streams the stored file — never a public URL. */
    public function downloadDocument(Request $request, string $id, string $docId): string
    {
        $doc = PatientService::documentDownloadPath((int) $docId);
        if (!isset($doc['file'])) {
            throw HttpException::notFound('Document not found.');
        }

        AuditService::log('patient.document_downloaded', 'patients', 'view', "Downloaded document \"{$doc['name']}\".", [], $request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $doc['mime']);
        header('Content-Length: ' . (string) $doc['size']);
        header('Content-Disposition: inline; filename="' . rawurlencode($doc['name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($doc['file']);
        exit;
    }

    public function deleteDocument(Request $request, string $id, string $docId): string
    {
        $r = PatientService::deleteDocument((int) $docId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Document deleted.' : (string) $r['error']);
        return Response::redirect(url('/admin/patients/' . $id . '?tab=documents'));
    }

    // ------------------------------------------------------------------
    // Export
    // ------------------------------------------------------------------
    public function export(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $csv = Patient::directoryCsv($filters);

        AuditService::log('patient.exported', 'patients', 'export', 'Exported patient directory (CSV).', [
            'filters' => array_filter($filters, static fn ($v) => $v !== '' && $v !== null),
        ], $request);

        return Response::with(200, 'text/csv; charset=utf-8', $csv, [
            'Content-Disposition' => 'attachment; filename="patients-' . date('Y-m-d-Hi') . '.csv"',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function rules(int $ignoreId = 0): array
    {
        $nidUnique = $ignoreId > 0 ? 'nullable|max:40|unique:patients,national_id,' . $ignoreId : 'nullable|max:40|unique:patients,national_id';
        return [
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:1|max:80',
            'gender'     => 'required|in:male,female,other',
            'date_of_birth' => 'required|date',
            'blood_group'   => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'national_id' => $nidUnique,
            'phone'      => 'required|min:6|max:30',
            'email'      => 'nullable|email|max:190',
            'address'    => 'nullable|max:255',
            'city'       => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'emergency_contact_name'  => 'nullable|max:150',
            'emergency_contact_phone' => 'nullable|max:30',
            'emergency_contact_relation' => 'nullable|max:50',
            'medical_history' => 'nullable|max:3000',
            'allergies'       => 'nullable|max:1000',
            'notes'           => 'nullable|max:3000',
        ];
    }

    /** @return array<string, mixed> */
    private function normalize(array $data): array
    {
        $nullable = ['blood_group', 'marital_status', 'national_id', 'email', 'address', 'city', 'postal_code',
                     'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
                     'medical_history', 'allergies', 'notes', 'country'];
        foreach ($nullable as $key) {
            if (($data[$key] ?? '') === '') {
                $data[$key] = null;
            }
        }
        return $data;
    }

    /** @return array<string, mixed> sanitized filter set */
    private function collectFilters(Request $request): array
    {
        return [
            'search'      => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'gender'      => in_array($request->query('gender', ''), ['male', 'female', 'other'], true) ? (string) $request->query('gender') : '',
            'blood_group' => in_array($request->query('blood_group', ''), self::BLOOD_GROUPS, true) ? (string) $request->query('blood_group') : '',
            'status'      => in_array($request->query('status', ''), ['not_archived', 'archived', 'all'], true) ? (string) $request->query('status') : 'not_archived',
            'sort'        => (string) $request->query('sort', 'created_at'),
            'dir'         => strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** @return array<int, array{id:int, name:string}> active doctors for the visit form */
    private function doctorOptions(): array
    {
        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
            Database::query(
                "SELECT DISTINCT u.id, u.name FROM users u
                 INNER JOIN role_user ru ON ru.user_id = u.id
                 INNER JOIN roles r ON r.id = ru.role_id
                 WHERE r.slug IN ('doctor','administrator') AND u.is_active = 1
                   AND u.archived_at IS NULL
                 ORDER BY u.name"
            )
        );
    }

    private function validTab(string $tab): string
    {
        return in_array($tab, ['overview', 'visits', 'documents', 'prescriptions', 'lab-reports', 'invoices'], true) ? $tab : 'overview';
    }
}
