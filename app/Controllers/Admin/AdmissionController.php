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
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Room;
use App\Models\Ward;
use App\Services\AdmissionService;
use App\Services\AuditService;
use App\Services\SettingService;

final class AdmissionController extends Controller
{
    public function bedsIndex(Request $request): string
    {
        $wards = Ward::withCounts();
        $beds = Bed::allWithDetails();
        $counts = Bed::counts();
        $admissionCounts = Admission::counts();
        return Response::html(view('admin/beds/index', ['wards' => $wards, 'beds' => $beds, 'counts' => $counts, 'admissionCounts' => $admissionCounts]));
    }

    public function wardsIndex(Request $request): string
    {
        $wards = Ward::withCounts();
        return Response::html(view('admin/beds/wards', ['wards' => $wards]));
    }

    public function storeWard(Request $request): string
    {
        $result = $this->validate($request, ['name' => 'required|min:2|max:100', 'floor' => 'nullable|max:20', 'description' => 'nullable|max:500']);
        Database::execute('INSERT INTO wards (name, floor, description, is_active) VALUES (?, ?, ?, 1)', [$result['data']['name'], $result['data']['floor'] ?? null, $result['data']['description'] ?? null]);
        $id = Database::lastInsertId();
        AuditService::log('ward.created', 'beds', 'create', "Created ward {$result['data']['name']}.", ['ward_id' => $id], $request);
        Session::flash('success', 'Ward created.');
        return Response::redirect(url('/admin/beds/wards'));
    }

    public function storeRoom(Request $request): string
    {
        $result = $this->validate($request, ['ward_id' => 'required|integer', 'room_number' => 'required|max:20', 'room_type' => 'nullable|in:general,semi_private,private,icu,nicu,isolation', 'daily_rate' => 'nullable|numeric', 'bed_count' => 'nullable|integer']);
        $d = $result['data'];
        Database::execute('INSERT INTO rooms (ward_id, room_number, room_type, daily_rate, is_active) VALUES (?, ?, ?, ?, 1)', [(int) $d['ward_id'], $d['room_number'], $d['room_type'] ?? 'general', round((float) ($d['daily_rate'] ?? 0), 2)]);
        $roomId = (int) Database::lastInsertId();
        $bedCount = (int) ($d['bed_count'] ?? 1);
        for ($i = 1; $i <= $bedCount; $i++) {
            Database::execute('INSERT INTO beds (room_id, bed_number, status) VALUES (?, ?, "available")', [$roomId, (string) $i]);
        }
        AuditService::log('room.created', 'beds', 'create', "Created room {$d['room_number']} with {$bedCount} bed(s).", ['room_id' => $roomId], $request);
        Session::flash('success', "Room created with {$bedCount} bed(s).");
        return Response::redirect(url('/admin/beds/wards'));
    }

    public function setBedStatus(Request $request, string $id): string
    {
        $status = (string) $request->input('status', '');
        $r = AdmissionService::setBedStatus((int) $id, $status, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Bed status updated.' : (string) $r['error']);
        return Response::redirect(url('/admin/beds'));
    }

    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 15)));
        $data = Admission::directory($filters, $page, $perPage);
        $counts = Admission::counts();
        $wards = Ward::options();
        $query = http_build_query(array_filter($filters, static fn ($v) => $v !== '' && $v !== null));
        $baseUrl = url('/admin/admissions') . ($query !== '' ? '?' . $query . '&' : '?');
        return Response::html(view('admin/admissions/index', array_merge($data, ['filters' => $filters, 'counts' => $counts, 'wards' => $wards, 'baseUrl' => $baseUrl])));
    }

    public function create(Request $request): string
    {
        $prefillPatient = (int) $request->query('patient_id', 0) ?: null;
        $wards = Ward::withCounts();
        $availableBeds = Bed::available();
        return Response::html(view('admin/admissions/form', ['admission' => null, 'patientId' => $prefillPatient, 'wards' => $wards, 'availableBeds' => $availableBeds]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, ['patient_id' => 'required|integer', 'doctor_id' => 'nullable|integer', 'ward_id' => 'nullable|integer', 'room_id' => 'nullable|integer', 'bed_id' => 'nullable|integer', 'admission_type' => 'nullable|in:emergency,scheduled,transfer_in', 'admission_date' => 'nullable', 'expected_discharge' => 'nullable|date', 'admission_reason' => 'nullable|max:500', 'diagnosis_at_admission' => 'nullable|max:500']);
        $r = AdmissionService::admit($result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Patient admitted ({$r['code']})." : ($r['error'] ?? 'Admission failed.'));
        return Response::redirect($r['ok'] ? url('/admin/admissions/' . $r['id']) : url('/admin/admissions/create'));
    }

    public function show(Request $request, string $id): string
    {
        $adm = Admission::profile((int) $id);
        if ($adm === null) throw HttpException::notFound('Admission not found.');
        $age = $adm['date_of_birth'] !== null ? (int) Database::scalar('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())', [$adm['date_of_birth']]) : null;
        $availableBeds = $adm['status'] === 'admitted' ? Bed::available() : [];
        return Response::html(view('admin/admissions/show', ['admission' => $adm, 'age' => $age, 'availableBeds' => $availableBeds]));
    }

    public function transfer(Request $request, string $id): string
    {
        $toBedId = (int) $request->input('to_bed_id', 0);
        $reason = (string) $request->input('reason', '');
        $r = AdmissionService::transfer((int) $id, $toBedId, $reason, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Patient transferred to new bed.' : (string) $r['error']);
        return Response::redirect(url('/admin/admissions/' . $id));
    }

    public function discharge(Request $request, string $id): string
    {
        $result = $this->validate($request, ['discharge_summary' => 'nullable|max:5000', 'discharge_diagnosis' => 'nullable|max:500', 'discharge_instructions' => 'nullable|max:5000']);
        $r = AdmissionService::discharge((int) $id, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Patient discharged. Bed marked for cleaning.' : (string) $r['error']);
        return Response::redirect(url('/admin/admissions/' . $id));
    }

    private function collectFilters(Request $request): array
    {
        return ['search' => trim(mb_substr((string) $request->query('q', ''), 0, 60)), 'status' => in_array($request->query('status', ''), Admission::STATUSES, true) ? (string) $request->query('status') : '', 'ward_id' => (int) $request->query('ward_id', 0) ?: '', 'date_from' => (string) $request->query('date_from', ''), 'date_to' => (string) $request->query('date_to', '')];
    }
}
