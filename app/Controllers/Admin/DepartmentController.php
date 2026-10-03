<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Department;
use App\Models\Doctor;
use App\Services\AuditService;
use App\Services\DepartmentService;
use App\Services\SettingService;

/**
 * Department management — directory, CRUD, member roster, stats.
 */
final class DepartmentController extends Controller
{
    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 10)));

        $data = Department::directory($filters, $page, $perPage);
        $counts = Department::counts();

        $baseUrl = url('/admin/departments') . '?' . http_build_query(array_filter([
            'q' => $filters['search'], 'status' => $filters['status'],
        ], static fn ($v) => $v !== '' && $v !== null)) . '&';

        return Response::html(view('admin/departments/index', [
            'departments' => $data['rows'],
            'total'        => $data['total'],
            'page'         => $data['page'],
            'pages'        => $data['pages'],
            'perPage'      => $data['perPage'],
            'filters'      => $filters,
            'counts'       => $counts,
            'baseUrl'      => $baseUrl,
        ]));
    }

    public function create(Request $request): string
    {
        return Response::html(view('admin/departments/form', [
            'department'    => null,
            'doctorOptions' => Doctor::options(),
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'name'        => 'required|min:2|max:100',
            'description' => 'nullable|max:2000',
            'head_doctor_id' => 'nullable|integer',
            'location'    => 'nullable|max:150',
            'phone'       => 'nullable|max:30',
            'email'       => 'nullable|email|max:190',
        ]);
        $data = $result['data'];

        $r = DepartmentService::create($data, $request);
        if (!$r['ok']) {
            Session::flash('error', $r['error'] ?? 'Could not create department.');
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/departments/create'));
        }
        Session::flash('success', 'Department created.');
        return Response::redirect(url('/admin/departments/' . $r['id']));
    }

    public function show(Request $request, string $id): string
    {
        $dept = Department::withMembers((int) $id);
        if ($dept === null) {
            throw HttpException::notFound('Department not found.');
        }
        return Response::html(view('admin/departments/show', [
            'department' => $dept,
        ]));
    }

    public function edit(Request $request, string $id): string
    {
        $dept = Department::find((int) $id);
        if ($dept === null) {
            throw HttpException::notFound('Department not found.');
        }
        return Response::html(view('admin/departments/form', [
            'department'    => $dept,
            'doctorOptions' => Doctor::options(),
        ]));
    }

    public function update(Request $request, string $id): string
    {
        $deptId = (int) $id;
        if (Department::find($deptId) === null) {
            throw HttpException::notFound('Department not found.');
        }
        $result = $this->validate($request, [
            'name'        => 'required|min:2|max:100',
            'description' => 'nullable|max:2000',
            'head_doctor_id' => 'nullable|integer',
            'location'    => 'nullable|max:150',
            'phone'       => 'nullable|max:30',
            'email'       => 'nullable|email|max:190',
            'is_active'   => 'nullable',
        ]);
        $data = $result['data'];

        $r = DepartmentService::update($deptId, $data, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Department updated.' : ($r['error'] ?? 'Update failed.'));
        return Response::redirect(url('/admin/departments/' . $deptId));
    }

    public function archive(Request $request, string $id): string
    {
        $r = DepartmentService::archive((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Department archived.' : (string) $r['error']);
        return Response::redirect(url('/admin/departments'));
    }

    public function restore(Request $request, string $id): string
    {
        $r = DepartmentService::restore((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Department restored.' : (string) $r['error']);
        return Response::redirect(url('/admin/departments/' . $id));
    }

    /** @return array<string, mixed> */
    private function collectFilters(Request $request): array
    {
        return [
            'search' => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'status' => in_array($request->query('status', ''), ['active', 'archived', 'all'], true)
                ? (string) $request->query('status') : 'active',
        ];
    }
}
