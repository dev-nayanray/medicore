<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\AuditService;
use App\Services\DoctorService;
use App\Services\SettingService;

/**
 * Doctor management — directory, profile CRUD, weekly schedule editor,
 * leave approval, and consultation stats.
 */
final class DoctorController extends Controller
{
    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 10)));

        $data = Doctor::directory($filters, $page, $perPage);
        $counts = Doctor::counts();
        $departments = Department::options();

        $query = http_build_query(array_filter([
            'q' => $filters['search'], 'department_id' => $filters['department_id'],
            'status' => $filters['status'], 'sort' => $filters['sort'], 'dir' => $filters['dir'],
        ], static fn ($v) => $v !== '' && $v !== null));
        $baseUrl = url('/admin/doctors') . ($query !== '' ? '?' . $query . '&' : '?');

        return Response::html(view('admin/doctors/index', [
            'doctors'     => $data['rows'],
            'total'       => $data['total'],
            'page'        => $data['page'],
            'pages'       => $data['pages'],
            'perPage'     => $data['perPage'],
            'filters'     => $filters,
            'counts'      => $counts,
            'departments' => $departments,
            'baseUrl'     => $baseUrl,
        ]));
    }

    public function create(Request $request): string
    {
        // Candidate user accounts that have a doctor role but no profile yet.
        $candidates = Database::query(
            "SELECT u.id, u.name, u.email
             FROM users u
             INNER JOIN role_user ru ON ru.user_id = u.id
             INNER JOIN roles r ON r.id = ru.role_id
             WHERE r.slug = 'doctor' AND u.is_active = 1 AND u.archived_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.id)
             ORDER BY u.name"
        );

        return Response::html(view('admin/doctors/form', [
            'doctor'       => null,
            'departments'  => Department::options(),
            'userCandidates' => $candidates,
            'days'         => DoctorSchedule::DAYS,
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'user_id'           => 'required|integer',
            'specialization'    => 'required|min:2|max:100',
            'qualifications'    => 'nullable|max:500',
            'registration_number' => 'nullable|max:50|unique:doctors,registration_number',
            'bio'               => 'nullable|max:2000',
            'consultation_fee' => 'nullable|numeric',
            'department_id'    => 'nullable|integer',
            'room_number'       => 'nullable|max:20',
            'status'            => 'nullable|in:active,inactive,on_leave',
            'hired_at'          => 'nullable|date',
        ]);
        $data = $result['data'];

        $r = DoctorService::create($data, $request);
        if (!$r['ok']) {
            Session::flash('error', $r['error'] ?? 'Could not create doctor profile.');
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/doctors/create'));
        }
        Session::flash('success', "Doctor profile created with code {$r['code']}.");
        return Response::redirect(url('/admin/doctors/' . $r['id']));
    }

    public function show(Request $request, string $id): string
    {
        $doctor = Doctor::profile((int) $id);
        if ($doctor === null) {
            throw HttpException::notFound('Doctor not found.');
        }
        $tab = in_array((string) $request->query('tab', 'overview'), ['overview', 'schedule', 'leaves', 'visits'], true)
            ? (string) $request->query('tab', 'overview') : 'overview';

        return Response::html(view('admin/doctors/show', [
            'doctor'      => $doctor,
            'departments' => Department::options(),
            'days'        => DoctorSchedule::DAYS,
            'tab'         => $tab,
        ]));
    }

    public function edit(Request $request, string $id): string
    {
        $doctor = Doctor::find((int) $id);
        if ($doctor === null) {
            throw HttpException::notFound('Doctor not found.');
        }
        $doctor['user_name'] = (string) Database::scalar('SELECT name FROM users WHERE id = ?', [$doctor['user_id']]);
        $doctor['user_email'] = (string) Database::scalar('SELECT email FROM users WHERE id = ?', [$doctor['user_id']]);
        $doctor['user_phone'] = (string) Database::scalar('SELECT phone FROM users WHERE id = ?', [$doctor['user_id']]);

        return Response::html(view('admin/doctors/form', [
            'doctor'       => $doctor,
            'departments'  => Department::options(),
            'userCandidates' => [],
            'days'         => DoctorSchedule::DAYS,
        ]));
    }

    public function update(Request $request, string $id): string
    {
        $doctorId = (int) $id;
        if (Doctor::find($doctorId) === null) {
            throw HttpException::notFound('Doctor not found.');
        }
        $result = $this->validate($request, [
            'specialization'    => 'required|min:2|max:100',
            'qualifications'    => 'nullable|max:500',
            'registration_number' => 'nullable|max:50|unique:doctors,registration_number,' . $doctorId,
            'bio'               => 'nullable|max:2000',
            'consultation_fee' => 'nullable|numeric',
            'department_id'    => 'nullable|integer',
            'room_number'       => 'nullable|max:20',
            'status'            => 'nullable|in:active,inactive,on_leave',
            'hired_at'          => 'nullable|date',
        ]);
        $data = $result['data'];

        $r = DoctorService::update($doctorId, $data, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Doctor profile updated.' : ($r['error'] ?? 'Update failed.'));
        return Response::redirect(url('/admin/doctors/' . $doctorId));
    }

    public function archive(Request $request, string $id): string
    {
        $r = DoctorService::archive((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Doctor archived.' : (string) $r['error']);
        return Response::redirect(url('/admin/doctors'));
    }

    public function restore(Request $request, string $id): string
    {
        $r = DoctorService::restore((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Doctor restored.' : (string) $r['error']);
        return Response::redirect(url('/admin/doctors/' . $id));
    }

    // ------------------------------------------------------------------
    // Schedule slots
    // ------------------------------------------------------------------
    public function storeSchedule(Request $request, string $id): string
    {
        $doctorId = (int) $id;
        $result = $this->validate($request, [
            'day_of_week'  => 'required|integer',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'max_patients' => 'nullable|integer',
            'room'         => 'nullable|max:20',
        ]);
        $data = $result['data'];

        $r = DoctorService::saveScheduleSlot($doctorId, $data, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Schedule slot saved.' : (string) $r['error']);
        return Response::redirect(url('/admin/doctors/' . $doctorId . '?tab=schedule'));
    }

    public function deleteSchedule(Request $request, string $id, string $slotId): string
    {
        $r = DoctorService::deleteScheduleSlot((int) $id, (int) $slotId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Schedule slot removed.' : (string) $r['error']);
        return Response::redirect(url('/admin/doctors/' . $id . '?tab=schedule'));
    }

    // ------------------------------------------------------------------
    // Leaves
    // ------------------------------------------------------------------
    public function storeLeave(Request $request, string $id): string
    {
        $doctorId = (int) $id;
        $result = $this->validate($request, [
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'reason'     => 'nullable|max:300',
            'status'     => 'nullable|in:pending,approved,rejected',
        ]);
        $r = DoctorService::createLeave($doctorId, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Leave recorded.' : (string) $r['error']);
        return Response::redirect(url('/admin/doctors/' . $doctorId . '?tab=leaves'));
    }

    public function decideLeave(Request $request, string $id, string $leaveId): string
    {
        $decision = (string) $request->input('decision', '');
        $r = DoctorService::decideLeave((int) $leaveId, $decision, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Leave ' . $decision . '.' : (string) $r['error']);
        return Response::redirect(url('/admin/doctors/' . $id . '?tab=leaves'));
    }

    /** @return array<string, mixed> */
    private function collectFilters(Request $request): array
    {
        return [
            'search'       => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'department_id'=> (int) $request->query('department_id', 0) ?: '',
            'status'       => in_array($request->query('status', ''), ['active', 'inactive', 'on_leave'], true)
                ? (string) $request->query('status') : '',
            'sort'         => (string) $request->query('sort', 'created_at'),
            'dir'          => strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
