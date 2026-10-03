<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\AuditService;
use App\Services\SettingService;

/**
 * Appointment management — directory, calendar (day/week/month), live queue,
 * booking, status transitions, reschedule, cancel, reports, export.
 */
final class AppointmentController extends Controller
{
    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 15)));

        $data = Appointment::directory($filters, $page, $perPage);
        $counts = Appointment::counts();
        $doctors = Doctor::options();
        $departments = Department::options();

        $query = http_build_query(array_filter([
            'q' => $filters['search'], 'status' => $filters['status'], 'type' => $filters['type'],
            'doctor_id' => $filters['doctor_id'], 'department_id' => $filters['department_id'],
            'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
            'sort' => $filters['sort'], 'dir' => $filters['dir'],
        ], static fn ($v) => $v !== '' && $v !== null));
        $baseUrl = url('/admin/appointments') . ($query !== '' ? '?' . $query . '&' : '?');

        return Response::html(view('admin/appointments/index', [
            'appointments' => $data['rows'],
            'total'        => $data['total'],
            'page'         => $data['page'],
            'pages'        => $data['pages'],
            'perPage'      => $data['perPage'],
            'filters'      => $filters,
            'counts'       => $counts,
            'doctors'      => $doctors,
            'departments'  => $departments,
            'baseUrl'      => $baseUrl,
        ]));
    }

    public function calendar(Request $request): string
    {
        $view = in_array((string) $request->query('view', 'day'), ['day', 'week', 'month'], true)
            ? (string) $request->query('view', 'day') : 'day';

        $dateParam = (string) $request->query('date', date('Y-m-d'));
        $date = strtotime($dateParam) ? date('Y-m-d', strtotime($dateParam)) : date('Y-m-d');

        $doctorId = (int) $request->query('doctor_id', 0) ?: null;
        $departmentId = (int) $request->query('department_id', 0) ?: null;

        $appointments = match ($view) {
            'day'   => Appointment::forDate($date, $departmentId, $doctorId),
            'week'  => Appointment::forRange(date('Y-m-d', strtotime("{$date} Sunday this week")), date('Y-m-d', strtotime("{$date} Saturday this week")), $doctorId, $departmentId),
            default => Appointment::forRange(date('Y-m-01', strtotime($date)), date('Y-m-t', strtotime($date)), $doctorId, $departmentId),
        };

        return Response::html(view('admin/appointments/calendar', [
            'view'         => $view,
            'date'         => $date,
            'appointments' => $appointments,
            'doctors'      => Doctor::options(),
            'departments'  => Department::options(),
            'filters'      => ['doctor_id' => $doctorId, 'department_id' => $departmentId],
        ]));
    }

    public function queue(Request $request): string
    {
        $date = (string) $request->query('date', date('Y-m-d'));
        $departmentId = (int) $request->query('department_id', 0) ?: null;

        $queue = Appointment::queueForDate($date, $departmentId);
        $counts = Appointment::counts();

        return Response::html(view('admin/appointments/queue', [
            'date'         => $date,
            'departmentId' => $departmentId,
            'departments'  => Department::options(),
            'queue'        => $queue['rows'],
            'counts'       => $queue['counts'],
            'todayCounts'  => $counts,
        ]));
    }

    public function create(Request $request): string
    {
        $prefillPatient = (int) $request->query('patient_id', 0) ?: null;
        $prefillDoctor = (int) $request->query('doctor_id', 0) ?: null;
        $prefillDate = (string) $request->query('date', date('Y-m-d'));

        return Response::html(view('admin/appointments/form', [
            'appointment'   => null,
            'patients'       => $prefillPatient !== null ? Patient::find($prefillPatient) : null,
            'doctors'        => Doctor::options(),
            'departments'    => Department::options(),
            'prefillDoctor'  => $prefillDoctor,
            'prefillDate'    => $prefillDate,
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'patient_id'       => 'required|integer',
            'doctor_id'        => 'nullable|integer',
            'department_id'    => 'nullable|integer',
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
            'appointment_type' => 'nullable|in:scheduled,walk_in,follow_up,telemedicine',
            'reason'           => 'nullable|max:500',
            'notes'            => 'nullable|max:2000',
        ]);
        $r = AppointmentService::book($result['data'], $request);
        if (!$r['ok']) {
            Session::flash('error', $r['error'] ?? 'Could not book appointment.');
            Session::flashInput($request->all());
            return Response::redirect(url('/admin/appointments/create'));
        }
        $msg = "Appointment booked with code {$r['code']}.";
        if (!empty($r['queue_token'])) {
            $msg .= " Queue token: {$r['queue_token']}.";
        }
        Session::flash('success', $msg);
        return Response::redirect(url('/admin/appointments/' . $r['id']));
    }

    public function show(Request $request, string $id): string
    {
        $apt = Appointment::profile((int) $id);
        if ($apt === null) {
            throw HttpException::notFound('Appointment not found.');
        }
        return Response::html(view('admin/appointments/show', [
            'appointment' => $apt,
        ]));
    }

    public function edit(Request $request, string $id): string
    {
        $apt = Appointment::find((int) $id);
        if ($apt === null) {
            throw HttpException::notFound('Appointment not found.');
        }
        $apt['patient_name'] = (string) \App\Core\Database::scalar('SELECT CONCAT(first_name, " ", last_name) FROM patients WHERE id = ?', [$apt['patient_id']]);
        $apt['patient_code'] = (string) \App\Core\Database::scalar('SELECT patient_code FROM patients WHERE id = ?', [$apt['patient_id']]);

        return Response::html(view('admin/appointments/form', [
            'appointment'   => $apt,
            'patients'       => null,
            'doctors'        => Doctor::options(),
            'departments'    => Department::options(),
            'prefillDoctor'  => 0,
            'prefillDate'    => $apt['appointment_date'],
        ]));
    }

    public function reschedule(Request $request, string $id): string
    {
        $result = $this->validate($request, [
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);
        $r = AppointmentService::reschedule((int) $id, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Appointment rescheduled.' : ($r['error'] ?? 'Reschedule failed.'));
        return Response::redirect(url('/admin/appointments/' . $id));
    }

    public function cancel(Request $request, string $id): string
    {
        $reason = (string) $request->input('cancellation_reason', '');
        $r = AppointmentService::cancel((int) $id, $reason, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Appointment cancelled.' : (string) $r['error']);
        return Response::redirect(url('/admin/appointments/' . $id));
    }

    public function updateNotes(Request $request, string $id): string
    {
        $notes = (string) $request->input('notes', '');
        $r = AppointmentService::updateNotes((int) $id, $notes, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Notes saved.' : (string) $r['error']);
        return Response::redirect(url('/admin/appointments/' . $id));
    }

    // ------------------------------------------------------------------
    // Status transitions (queue workflow)
    // ------------------------------------------------------------------
    public function checkIn(Request $request, string $id): string
    {
        $r = AppointmentService::transition((int) $id, 'checked_in', $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Patient checked in — added to queue.' : (string) $r['error']);
        return Response::redirect(url('/admin/appointments/queue'));
    }

    public function confirm(Request $request, string $id): string
    {
        $r = AppointmentService::transition((int) $id, 'confirmed', $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Appointment confirmed.' : (string) $r['error']);
        return Response::redirect(url('/admin/appointments/' . $id));
    }

    public function startConsultation(Request $request, string $id): string
    {
        $r = AppointmentService::transition((int) $id, 'in_consultation', $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Consultation started.' : (string) $r['error']);
        return Response::redirect(url('/admin/appointments/queue'));
    }

    public function complete(Request $request, string $id): string
    {
        $r = AppointmentService::transition((int) $id, 'completed', $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Consultation completed.' : (string) $r['error']);
        return Response::redirect(url('/admin/appointments/' . $id));
    }

    public function markNoShow(Request $request, string $id): string
    {
        $r = AppointmentService::transition((int) $id, 'no_show', $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Marked as no-show.' : (string) $r['error']);
        return Response::redirect(url('/admin/appointments/' . $id));
    }

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------
    public function reports(Request $request): string
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));

        $report = Appointment::reportForRange($from, $to);
        $noShow = Appointment::noShowRate($from, $to);
        $volume = Appointment::dailyVolume(14);

        return Response::html(view('admin/appointments/reports', [
            'from'   => $from,
            'to'     => $to,
            'report' => $report,
            'noShow' => $noShow,
            'volume' => $volume,
        ]));
    }

    public function export(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $rows = Appointment::directory($filters, 1, 100000)['rows'];

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Code', 'Date', 'Start', 'End', 'Patient', 'Patient ID', 'Doctor', 'Department', 'Type', 'Status', 'Queue', 'Reason', 'Created']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['appointment_code'], $r['appointment_date'],
                substr((string) $r['start_time'], 0, 5), substr((string) $r['end_time'], 0, 5),
                $r['patient_name'] ?? '—', $r['patient_code'] ?? '—',
                $r['doctor_name'] ?? '—', $r['department_name'] ?? '—',
                $r['appointment_type'], $r['status'], $r['queue_token'] ?? '',
                $r['reason'] ?? '',
                date('Y-m-d H:i', strtotime((string) $r['created_at'])),
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        AuditService::log('appointment.exported', 'appointments', 'export', 'Exported appointment directory (CSV).', [
            'filters' => array_filter($filters, static fn ($v) => $v !== '' && $v !== null),
        ], $request);

        return Response::with(200, 'text/csv; charset=utf-8', $csv, [
            'Content-Disposition' => 'attachment; filename="appointments-' . date('Y-m-d-Hi') . '.csv"',
        ]);
    }

    /** @return array<string, mixed> */
    private function collectFilters(Request $request): array
    {
        return [
            'search'       => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'status'       => in_array($request->query('status', ''), Appointment::STATUSES, true) ? (string) $request->query('status') : '',
            'type'         => in_array($request->query('type', ''), Appointment::TYPES, true) ? (string) $request->query('type') : '',
            'doctor_id'    => (int) $request->query('doctor_id', 0) ?: '',
            'department_id'=> (int) $request->query('department_id', 0) ?: '',
            'date_from'    => (string) $request->query('date_from', ''),
            'date_to'      => (string) $request->query('date_to', ''),
            'sort'         => (string) $request->query('sort', 'appointment_date'),
            'dir'          => strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
