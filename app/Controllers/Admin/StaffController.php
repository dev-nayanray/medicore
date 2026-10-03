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
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Services\AuditService;
use App\Services\SettingService;
use App\Services\StaffService;

/**
 * Staff management — directory, profile CRUD, shifts, attendance, leaves.
 */
final class StaffController extends Controller
{
    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 10)));

        $data = StaffProfile::directory($filters, $page, $perPage);
        $counts = StaffProfile::counts();
        $departments = Department::options();

        $query = http_build_query(array_filter([
            'q' => $filters['search'], 'department_id' => $filters['department_id'],
            'status' => $filters['status'], 'sort' => $filters['sort'], 'dir' => $filters['dir'],
        ], static fn ($v) => $v !== '' && $v !== null));
        $baseUrl = url('/admin/staff') . ($query !== '' ? '?' . $query . '&' : '?');

        return Response::html(view('admin/staff/index', [
            'staff'       => $data['rows'],
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
        // Candidate user accounts without a staff profile yet.
        $candidates = Database::query(
            "SELECT u.id, u.name, u.email
             FROM users u
             WHERE u.is_active = 1 AND u.archived_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM staff_profiles sp WHERE sp.user_id = u.id)
               AND NOT EXISTS (SELECT 1 FROM doctors doc WHERE doc.user_id = u.id)
             ORDER BY u.name"
        );

        return Response::html(view('admin/staff/form', [
            'staff'        => null,
            'departments'  => Department::options(),
            'userCandidates' => $candidates,
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'user_id'        => 'required|integer',
            'job_title'      => 'required|min:2|max:100',
            'department_id' => 'nullable|integer',
            'employment_type' => 'nullable|in:full_time,part_time,contract,visiting,intern',
            'hire_date'      => 'nullable|date',
            'status'         => 'nullable|in:active,inactive,on_leave,terminated',
        ]);
        $r = StaffService::create($result['data'], $request);
        if (!$r['ok']) {
            Session::flash('error', $r['error'] ?? 'Could not create staff profile.');
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/staff/create'));
        }
        Session::flash('success', "Staff profile created with code {$r['code']}.");
        return Response::redirect(url('/admin/staff/' . $r['id']));
    }

    public function show(Request $request, string $id): string
    {
        $staff = StaffProfile::profile((int) $id);
        if ($staff === null) {
            throw HttpException::notFound('Staff not found.');
        }
        $tab = in_array((string) $request->query('tab', 'overview'), ['overview', 'shifts', 'attendance', 'leaves'], true)
            ? (string) $request->query('tab', 'overview') : 'overview';

        return Response::html(view('admin/staff/show', [
            'staff'        => $staff,
            'departments'  => Department::options(),
            'tab'          => $tab,
        ]));
    }

    public function edit(Request $request, string $id): string
    {
        $staff = StaffProfile::find((int) $id);
        if ($staff === null) {
            throw HttpException::notFound('Staff not found.');
        }
        $staff['user_name'] = (string) Database::scalar('SELECT name FROM users WHERE id = ?', [$staff['user_id']]);
        $staff['user_email'] = (string) Database::scalar('SELECT email FROM users WHERE id = ?', [$staff['user_id']]);
        $staff['user_phone'] = (string) Database::scalar('SELECT phone FROM users WHERE id = ?', [$staff['user_id']]);

        return Response::html(view('admin/staff/form', [
            'staff'        => $staff,
            'departments'  => Department::options(),
            'userCandidates' => [],
        ]));
    }

    public function update(Request $request, string $id): string
    {
        $staffId = (int) $id;
        if (StaffProfile::find($staffId) === null) {
            throw HttpException::notFound('Staff not found.');
        }
        $result = $this->validate($request, [
            'job_title'      => 'required|min:2|max:100',
            'department_id' => 'nullable|integer',
            'employment_type' => 'nullable|in:full_time,part_time,contract,visiting,intern',
            'hire_date'      => 'nullable|date',
            'status'         => 'nullable|in:active,inactive,on_leave,terminated',
        ]);
        $r = StaffService::update($staffId, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Staff profile updated.' : ($r['error'] ?? 'Update failed.'));
        return Response::redirect(url('/admin/staff/' . $staffId));
    }

    public function archive(Request $request, string $id): string
    {
        $r = StaffService::archive((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Staff archived.' : (string) $r['error']);
        return Response::redirect(url('/admin/staff'));
    }

    public function restore(Request $request, string $id): string
    {
        $r = StaffService::restore((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Staff restored.' : (string) $r['error']);
        return Response::redirect(url('/admin/staff/' . $id));
    }

    // ------------------------------------------------------------------
    // Shifts
    // ------------------------------------------------------------------
    public function storeShift(Request $request, string $id): string
    {
        $staffId = (int) $id;
        $result = $this->validate($request, [
            'shift_date'  => 'required|date',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'shift_type'   => 'nullable|in:morning,evening,night,on_call',
            'department_id' => 'nullable|integer',
            'notes'        => 'nullable|max:300',
        ]);
        $r = StaffService::createShift($staffId, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Shift scheduled.' : (string) $r['error']);
        return Response::redirect(url('/admin/staff/' . $staffId . '?tab=shifts'));
    }

    public function deleteShift(Request $request, string $id, string $shiftId): string
    {
        $r = StaffService::deleteShift((int) $id, (int) $shiftId, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Shift removed.' : (string) $r['error']);
        return Response::redirect(url('/admin/staff/' . $id . '?tab=shifts'));
    }

    // ------------------------------------------------------------------
    // Attendance
    // ------------------------------------------------------------------
    public function storeAttendance(Request $request, string $id): string
    {
        $staffId = (int) $id;
        $result = $this->validate($request, [
            'date'      => 'required|date',
            'check_in'   => 'nullable',
            'check_out'  => 'nullable',
            'status'    => 'required|in:present,late,absent,half_day,leave',
            'notes'     => 'nullable|max:300',
        ]);
        $r = StaffService::recordAttendance($staffId, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Attendance recorded.' : (string) $r['error']);
        return Response::redirect(url('/admin/staff/' . $staffId . '?tab=attendance'));
    }

    // ------------------------------------------------------------------
    // Leaves
    // ------------------------------------------------------------------
    public function storeLeave(Request $request, string $id): string
    {
        $staffId = (int) $id;
        $result = $this->validate($request, [
            'leave_type'  => 'nullable|in:casual,sick,annual,maternity,unpaid,other',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'reason'     => 'nullable|max:500',
            'status'     => 'nullable|in:pending,approved,rejected',
        ]);
        $r = StaffService::createLeave($staffId, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Leave request recorded.' : (string) $r['error']);
        return Response::redirect(url('/admin/staff/' . $staffId . '?tab=leaves'));
    }

    public function decideLeave(Request $request, string $id, string $leaveId): string
    {
        $decision = (string) $request->input('decision', '');
        $r = StaffService::decideLeave((int) $leaveId, $decision, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Leave ' . $decision . '.' : (string) $r['error']);
        return Response::redirect(url('/admin/staff/' . $id . '?tab=leaves'));
    }

    /** @return array<string, mixed> */
    private function collectFilters(Request $request): array
    {
        return [
            'search'       => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'department_id'=> (int) $request->query('department_id', 0) ?: '',
            'status'       => in_array($request->query('status', ''), ['active', 'inactive', 'on_leave', 'terminated'], true)
                ? (string) $request->query('status') : '',
            'sort'         => (string) $request->query('sort', 'created_at'),
            'dir'          => strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
