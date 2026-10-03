<?php

declare(strict_types=1);

/**
 * Web route table. Convention:
 *   /                -> authenticated dashboard
 *   /admin/*         -> permission-gated administration
 *   /api/*           -> JSON endpoints for the admin UI
 *
 * Every state-changing route is POST and therefore CSRF-verified by the
 * global middleware. `can:` gates enforce server-side authorization.
 */

use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\AppointmentController;
use App\Controllers\Admin\DepartmentController;
use App\Controllers\Admin\DoctorController;
use App\Controllers\Admin\StaffController;
use App\Controllers\Admin\PatientController;
use App\Controllers\Admin\PermissionController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\UserController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Core\Router;

$router->get('/', [DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware('auth', 'password_current');

// ---------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
$router->post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('guest');

$router->get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request')->middleware('guest');
$router->post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('guest');
$router->get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset')->middleware('guest');
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('guest');

$router->get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change')->middleware('auth');
$router->post('/change-password', [AuthController::class, 'changePassword'])->name('password.change.save')->middleware('auth');

$router->post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ---------------------------------------------------------------------
// Administration (permission gated)
// ---------------------------------------------------------------------
$router->group(['prefix' => '/admin', 'middleware' => ['auth', 'password_current']], static function (Router $r): void {

    // --- Users -----------------------------------------------------------
    $r->get('/users', [UserController::class, 'index'])
        ->name('users.index')->middleware('can:users.view');

    $r->get('/users/create', [UserController::class, 'create'])
        ->name('users.create')->middleware('can:users.create');

    $r->post('/users', [UserController::class, 'store'])
        ->name('users.store')->middleware('can:users.create');

    $r->get('/users/{id}/edit', [UserController::class, 'edit'])
        ->name('users.edit')->middleware('can:users.update');

    $r->post('/users/{id}', [UserController::class, 'update'])
        ->name('users.update')->middleware('can:users.update');

    $r->post('/users/{id}/activate', [UserController::class, 'activate'])
        ->name('users.activate')->middleware('can:users.update');

    $r->post('/users/{id}/deactivate', [UserController::class, 'deactivate'])
        ->name('users.deactivate')->middleware('can:users.update');

    $r->post('/users/{id}/archive', [UserController::class, 'archive'])
        ->name('users.archive')->middleware('can:users.archive');

    $r->post('/users/{id}/restore', [UserController::class, 'restore'])
        ->name('users.restore')->middleware('can:users.archive');

    $r->post('/users/{id}/delete', [UserController::class, 'destroy'])
        ->name('users.delete')->middleware('can:users.delete');

    // --- Patients ---------------------------------------------------------
    $r->get('/patients', [PatientController::class, 'index'])
        ->name('patients.index')->middleware('can:patients.view');

    $r->get('/patients/export', [PatientController::class, 'export'])
        ->name('patients.export')->middleware('can:patients.view');

    $r->get('/patients/create', [PatientController::class, 'create'])
        ->name('patients.create')->middleware('can:patients.create');

    $r->post('/patients', [PatientController::class, 'store'])
        ->name('patients.store')->middleware('can:patients.create');

    $r->get('/patients/{id}', [PatientController::class, 'show'])
        ->name('patients.show')->middleware('can:patients.view');

    $r->get('/patients/{id}/print', [PatientController::class, 'printSummary'])
        ->name('patients.print')->middleware('can:patients.view');

    $r->get('/patients/{id}/edit', [PatientController::class, 'edit'])
        ->name('patients.edit')->middleware('can:patients.update');

    $r->post('/patients/{id}', [PatientController::class, 'update'])
        ->name('patients.update')->middleware('can:patients.update');

    $r->post('/patients/{id}/archive', [PatientController::class, 'archive'])
        ->name('patients.archive')->middleware('can:patients.update');

    $r->post('/patients/{id}/restore', [PatientController::class, 'restore'])
        ->name('patients.restore')->middleware('can:patients.update');

    $r->post('/patients/{id}/visits', [PatientController::class, 'storeVisit'])
        ->name('patients.visits.store')->middleware('can:patients.update');

    $r->post('/patients/{id}/documents', [PatientController::class, 'uploadDocument'])
        ->name('patients.documents.store')->middleware('can:patients.update');

    $r->get('/patients/{id}/documents/{docId}', [PatientController::class, 'downloadDocument'])
        ->name('patients.documents.download')->middleware('can:patients.view');

    $r->post('/patients/{id}/documents/{docId}/delete', [PatientController::class, 'deleteDocument'])
        ->name('patients.documents.delete')->middleware('can:patients.update');

    // --- Departments ----------------------------------------------------
    $r->get('/departments', [DepartmentController::class, 'index'])
        ->name('departments.index')->middleware('can:departments.view');

    $r->get('/departments/create', [DepartmentController::class, 'create'])
        ->name('departments.create')->middleware('can:departments.create');

    $r->post('/departments', [DepartmentController::class, 'store'])
        ->name('departments.store')->middleware('can:departments.create');

    $r->get('/departments/{id}', [DepartmentController::class, 'show'])
        ->name('departments.show')->middleware('can:departments.view');

    $r->get('/departments/{id}/edit', [DepartmentController::class, 'edit'])
        ->name('departments.edit')->middleware('can:departments.update');

    $r->post('/departments/{id}', [DepartmentController::class, 'update'])
        ->name('departments.update')->middleware('can:departments.update');

    $r->post('/departments/{id}/archive', [DepartmentController::class, 'archive'])
        ->name('departments.archive')->middleware('can:departments.update');

    $r->post('/departments/{id}/restore', [DepartmentController::class, 'restore'])
        ->name('departments.restore')->middleware('can:departments.update');

    // --- Doctors --------------------------------------------------------
    $r->get('/doctors', [DoctorController::class, 'index'])
        ->name('doctors.index')->middleware('can:doctors.view');

    $r->get('/doctors/create', [DoctorController::class, 'create'])
        ->name('doctors.create')->middleware('can:doctors.create');

    $r->post('/doctors', [DoctorController::class, 'store'])
        ->name('doctors.store')->middleware('can:doctors.create');

    $r->get('/doctors/{id}', [DoctorController::class, 'show'])
        ->name('doctors.show')->middleware('can:doctors.view');

    $r->get('/doctors/{id}/edit', [DoctorController::class, 'edit'])
        ->name('doctors.edit')->middleware('can:doctors.update');

    $r->post('/doctors/{id}', [DoctorController::class, 'update'])
        ->name('doctors.update')->middleware('can:doctors.update');

    $r->post('/doctors/{id}/archive', [DoctorController::class, 'archive'])
        ->name('doctors.archive')->middleware('can:doctors.update');

    $r->post('/doctors/{id}/restore', [DoctorController::class, 'restore'])
        ->name('doctors.restore')->middleware('can:doctors.update');

    $r->post('/doctors/{id}/schedule', [DoctorController::class, 'storeSchedule'])
        ->name('doctors.schedule.store')->middleware('can:doctors.update');

    $r->post('/doctors/{id}/schedule/{slotId}/delete', [DoctorController::class, 'deleteSchedule'])
        ->name('doctors.schedule.delete')->middleware('can:doctors.update');

    $r->post('/doctors/{id}/leaves', [DoctorController::class, 'storeLeave'])
        ->name('doctors.leaves.store')->middleware('can:doctors.update');

    $r->post('/doctors/{id}/leaves/{leaveId}', [DoctorController::class, 'decideLeave'])
        ->name('doctors.leaves.decide')->middleware('can:doctors.update');

    // --- Staff ----------------------------------------------------------
    $r->get('/staff', [StaffController::class, 'index'])
        ->name('staff.index')->middleware('can:staff.view');

    $r->get('/staff/create', [StaffController::class, 'create'])
        ->name('staff.create')->middleware('can:staff.create');

    $r->post('/staff', [StaffController::class, 'store'])
        ->name('staff.store')->middleware('can:staff.create');

    $r->get('/staff/{id}', [StaffController::class, 'show'])
        ->name('staff.show')->middleware('can:staff.view');

    $r->get('/staff/{id}/edit', [StaffController::class, 'edit'])
        ->name('staff.edit')->middleware('can:staff.update');

    $r->post('/staff/{id}', [StaffController::class, 'update'])
        ->name('staff.update')->middleware('can:staff.update');

    $r->post('/staff/{id}/archive', [StaffController::class, 'archive'])
        ->name('staff.archive')->middleware('can:staff.update');

    $r->post('/staff/{id}/restore', [StaffController::class, 'restore'])
        ->name('staff.restore')->middleware('can:staff.update');

    $r->post('/staff/{id}/shifts', [StaffController::class, 'storeShift'])
        ->name('staff.shifts.store')->middleware('can:staff.update');

    $r->post('/staff/{id}/shifts/{shiftId}/delete', [StaffController::class, 'deleteShift'])
        ->name('staff.shifts.delete')->middleware('can:staff.update');

    $r->post('/staff/{id}/attendance', [StaffController::class, 'storeAttendance'])
        ->name('staff.attendance.store')->middleware('can:staff.update');

    $r->post('/staff/{id}/leaves', [StaffController::class, 'storeLeave'])
        ->name('staff.leaves.store')->middleware('can:staff.update');

    $r->post('/staff/{id}/leaves/{leaveId}', [StaffController::class, 'decideLeave'])
        ->name('staff.leaves.decide')->middleware('can:staff.update');

    // --- Appointments ----------------------------------------------------
    $r->get('/appointments', [AppointmentController::class, 'index'])
        ->name('appointments.index')->middleware('can:appointments.view');

    $r->get('/appointments/calendar', [AppointmentController::class, 'calendar'])
        ->name('appointments.calendar')->middleware('can:appointments.view');

    $r->get('/appointments/queue', [AppointmentController::class, 'queue'])
        ->name('appointments.queue')->middleware('can:appointments.view');

    $r->get('/appointments/reports', [AppointmentController::class, 'reports'])
        ->name('appointments.reports')->middleware('can:appointments.view');

    $r->get('/appointments/export', [AppointmentController::class, 'export'])
        ->name('appointments.export')->middleware('can:appointments.view');

    $r->get('/appointments/create', [AppointmentController::class, 'create'])
        ->name('appointments.create')->middleware('can:appointments.create');

    $r->post('/appointments', [AppointmentController::class, 'store'])
        ->name('appointments.store')->middleware('can:appointments.create');

    $r->get('/appointments/{id}', [AppointmentController::class, 'show'])
        ->name('appointments.show')->middleware('can:appointments.view');

    $r->get('/appointments/{id}/edit', [AppointmentController::class, 'edit'])
        ->name('appointments.edit')->middleware('can:appointments.update');

    $r->post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])
        ->name('appointments.reschedule')->middleware('can:appointments.update');

    $r->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])
        ->name('appointments.cancel')->middleware('can:appointments.update');

    $r->post('/appointments/{id}/notes', [AppointmentController::class, 'updateNotes'])
        ->name('appointments.notes')->middleware('can:appointments.update');

    $r->post('/appointments/{id}/confirm', [AppointmentController::class, 'confirm'])
        ->name('appointments.confirm')->middleware('can:appointments.approve');

    $r->post('/appointments/{id}/check-in', [AppointmentController::class, 'checkIn'])
        ->name('appointments.check_in')->middleware('can:appointments.update');

    $r->post('/appointments/{id}/start', [AppointmentController::class, 'startConsultation'])
        ->name('appointments.start')->middleware('can:appointments.update');

    $r->post('/appointments/{id}/complete', [AppointmentController::class, 'complete'])
        ->name('appointments.complete')->middleware('can:appointments.update');

    $r->post('/appointments/{id}/no-show', [AppointmentController::class, 'markNoShow'])
        ->name('appointments.no_show')->middleware('can:appointments.update');

    // --- Roles & permissions ---------------------------------------------
    $r->get('/roles', [RoleController::class, 'index'])
        ->name('roles.index')->middleware('can:roles.view');

    $r->get('/roles/create', [RoleController::class, 'create'])
        ->name('roles.create')->middleware('can:roles.create');

    $r->post('/roles', [RoleController::class, 'store'])
        ->name('roles.store')->middleware('can:roles.create');

    $r->get('/roles/{id}/edit', [RoleController::class, 'edit'])
        ->name('roles.edit')->middleware('can:roles.update');

    $r->post('/roles/{id}', [RoleController::class, 'update'])
        ->name('roles.update')->middleware('can:roles.update');

    $r->post('/roles/{id}/delete', [RoleController::class, 'destroy'])
        ->name('roles.delete')->middleware('can:roles.delete');

    $r->get('/permissions', [PermissionController::class, 'index'])
        ->name('permissions.index')->middleware('can:roles.view');

    // --- Settings, audit, profile ------------------------------------------
    $r->get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit.index')->middleware('can:audit.view');

    $r->get('/settings', [SettingController::class, 'index'])
        ->name('settings.index')->middleware('can:settings.view');

    $r->post('/settings', [SettingController::class, 'update'])
        ->name('settings.update')->middleware('can:settings.update');

    $r->get('/profile', [ProfileController::class, 'index'])
        ->name('profile.index');

    $r->post('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    $r->post('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password');

    $r->get('/profile/activity', [ProfileController::class, 'activity'])
        ->name('profile.activity');
});

// ---------------------------------------------------------------------
// API (JSON) for the admin chrome
// ---------------------------------------------------------------------
$router->get('/api/search', [ApiController::class, 'search'])
    ->name('api.search')->middleware('auth');

$router->get('/api/notifications', [ApiController::class, 'notifications'])
    ->name('api.notifications')->middleware('auth');

$router->post('/api/patients/duplicates', [ApiController::class, 'patientDuplicates'])
    ->name('api.patients.duplicates')->middleware('auth');
