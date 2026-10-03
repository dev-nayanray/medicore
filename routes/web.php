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

use App\Controllers\Admin\AdmissionController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\AppointmentController;
use App\Controllers\Admin\BillingController;
use App\Controllers\Admin\ConsultationController;
use App\Controllers\Admin\DepartmentController;
use App\Controllers\Admin\DoctorController;
use App\Controllers\Admin\ExpenseController;
use App\Controllers\Admin\InventoryController;
use App\Controllers\Admin\LaboratoryController;
use App\Controllers\Admin\NotificationController;
use App\Controllers\Admin\PharmacyController;
use App\Controllers\Admin\PrescriptionController;
use App\Controllers\Admin\ReportsController;
use App\Controllers\Admin\ServiceController;
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
use App\Controllers\MarketingController;
use App\Core\Router;

$router->get('/', [DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware('auth', 'password_current');

// ---------------------------------------------------------------------
// Marketing website (public, no auth)
// ---------------------------------------------------------------------
$router->get('/home', [MarketingController::class, 'home'])->name('marketing.home');
$router->get('/features', [MarketingController::class, 'features'])->name('marketing.features');
$router->get('/pricing', [MarketingController::class, 'pricing'])->name('marketing.pricing');
$router->get('/contact', [MarketingController::class, 'contact'])->name('marketing.contact');
$router->post('/demo-request', [MarketingController::class, 'submitDemo'])->name('marketing.demo');

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

    // --- Consultations & Prescriptions -----------------------------------
    $r->get('/consultations', [ConsultationController::class, 'index'])
        ->name('consultations.index')->middleware('can:consultations.view');

    $r->get('/consultations/workspace', [ConsultationController::class, 'workspace'])
        ->name('consultations.workspace')->middleware('can:consultations.view');

    $r->get('/consultations/create', [ConsultationController::class, 'create'])
        ->name('consultations.create')->middleware('can:consultations.create');

    $r->post('/consultations', [ConsultationController::class, 'store'])
        ->name('consultations.store')->middleware('can:consultations.create');

    $r->get('/consultations/{id}', [ConsultationController::class, 'show'])
        ->name('consultations.show')->middleware('can:consultations.view');

    $r->get('/consultations/{id}/edit', [ConsultationController::class, 'edit'])
        ->name('consultations.edit')->middleware('can:consultations.update');

    $r->post('/consultations/{id}', [ConsultationController::class, 'update'])
        ->name('consultations.update')->middleware('can:consultations.update');

    $r->post('/consultations/{id}/finalize', [ConsultationController::class, 'finalize'])
        ->name('consultations.finalize')->middleware('can:consultations.finalize');

    $r->post('/consultations/{id}/amend', [ConsultationController::class, 'amend'])
        ->name('consultations.amend')->middleware('can:consultations.amend');

    $r->post('/consultations/{id}/attachments', [ConsultationController::class, 'uploadAttachment'])
        ->name('consultations.attachments.store')->middleware('can:consultations.update');

    $r->get('/consultations/{id}/attachments/{attId}', [ConsultationController::class, 'downloadAttachment'])
        ->name('consultations.attachments.download')->middleware('can:consultations.view');

    $r->post('/consultations/{id}/attachments/{attId}/delete', [ConsultationController::class, 'deleteAttachment'])
        ->name('consultations.attachments.delete')->middleware('can:consultations.update');

    $r->post('/consultations/{id}/prescriptions', [ConsultationController::class, 'storePrescription'])
        ->name('consultations.prescriptions.store')->middleware('can:prescriptions.create');

    $r->post('/consultations/{id}/prescriptions/{rxId}', [ConsultationController::class, 'updatePrescription'])
        ->name('consultations.prescriptions.update')->middleware('can:prescriptions.update');

    $r->post('/consultations/{id}/prescriptions/{rxId}/finalize', [ConsultationController::class, 'finalizePrescription'])
        ->name('consultations.prescriptions.finalize')->middleware('can:prescriptions.finalize');

    // Standalone prescription views (detail + print)
    $r->get('/prescriptions/{id}', [PrescriptionController::class, 'show'])
        ->name('prescriptions.show')->middleware('can:prescriptions.view');

    $r->get('/prescriptions/{id}/print', [PrescriptionController::class, 'print'])
        ->name('prescriptions.print')->middleware('can:prescriptions.view');

    // --- Billing & Payments ----------------------------------------------
    $r->get('/billing', [BillingController::class, 'index'])
        ->name('billing.index')->middleware('can:billing.view');

    $r->get('/billing/dashboard', [BillingController::class, 'dashboard'])
        ->name('billing.dashboard')->middleware('can:billing.view');

    $r->get('/billing/reports', [BillingController::class, 'reports'])
        ->name('billing.reports')->middleware('can:billing.view');

    $r->get('/billing/export', [BillingController::class, 'export'])
        ->name('billing.export')->middleware('can:billing.view');

    $r->get('/billing/create', [BillingController::class, 'create'])
        ->name('billing.create')->middleware('can:billing.create');

    $r->post('/billing', [BillingController::class, 'store'])
        ->name('billing.store')->middleware('can:billing.create');

    $r->get('/billing/{id}', [BillingController::class, 'show'])
        ->name('billing.show')->middleware('can:billing.view');

    $r->get('/billing/{id}/print', [BillingController::class, 'print'])
        ->name('billing.print')->middleware('can:billing.view');

    $r->post('/billing/{id}/payment', [BillingController::class, 'recordPayment'])
        ->name('billing.payment')->middleware('can:payments.create');

    $r->post('/billing/{id}/refund', [BillingController::class, 'recordRefund'])
        ->name('billing.refund')->middleware('can:payments.approve');

    $r->post('/billing/{id}/cancel', [BillingController::class, 'cancel'])
        ->name('billing.cancel')->middleware('can:billing.update');

    $r->post('/billing/{id}/notes', [BillingController::class, 'updateNotes'])
        ->name('billing.notes')->middleware('can:billing.update');

    // --- Hospital Services -----------------------------------------------
    $r->get('/services', [ServiceController::class, 'index'])
        ->name('services.index')->middleware('can:billing.view');

    $r->post('/services', [ServiceController::class, 'store'])
        ->name('services.store')->middleware('can:billing.update');

    $r->post('/services/{id}', [ServiceController::class, 'update'])
        ->name('services.update')->middleware('can:billing.update');

    $r->post('/services/{id}/delete', [ServiceController::class, 'destroy'])
        ->name('services.delete')->middleware('can:billing.delete');

    // --- Expenses ---------------------------------------------------------
    $r->get('/expenses', [ExpenseController::class, 'index'])
        ->name('expenses.index')->middleware('can:expenses.view');

    $r->post('/expenses', [ExpenseController::class, 'store'])
        ->name('expenses.store')->middleware('can:expenses.create');

    $r->post('/expenses/{id}', [ExpenseController::class, 'update'])
        ->name('expenses.update')->middleware('can:expenses.update');

    $r->post('/expenses/{id}/delete', [ExpenseController::class, 'destroy'])
        ->name('expenses.delete')->middleware('can:expenses.delete');

    // --- Pharmacy ---------------------------------------------------------
    $r->get('/pharmacy', [PharmacyController::class, 'index'])
        ->name('pharmacy.index')->middleware('can:pharmacy.view');
    $r->get('/pharmacy/create', [PharmacyController::class, 'index'])
        ->name('pharmacy.create')->middleware('can:pharmacy.create');
    $r->post('/pharmacy', [PharmacyController::class, 'store'])
        ->name('pharmacy.store')->middleware('can:pharmacy.create');
    // Literal paths MUST come before /pharmacy/{id} to avoid the {id} catch-all.
    $r->get('/pharmacy/dispense', [PharmacyController::class, 'dispense'])
        ->name('pharmacy.dispense')->middleware('can:pharmacy.create');
    $r->post('/pharmacy/dispense', [PharmacyController::class, 'storeDispensing'])
        ->name('pharmacy.dispense.store')->middleware('can:pharmacy.create');
    $r->get('/pharmacy/dispensing/{id}', [PharmacyController::class, 'showDispensing'])
        ->name('pharmacy.dispensing.show')->middleware('can:pharmacy.view');
    $r->post('/pharmacy/dispensing/{id}/return', [PharmacyController::class, 'returnMedicine'])
        ->name('pharmacy.return')->middleware('can:pharmacy.update');
    $r->get('/pharmacy/purchases', [PharmacyController::class, 'purchases'])
        ->name('pharmacy.purchases')->middleware('can:pharmacy.view');
    $r->post('/pharmacy/purchases', [PharmacyController::class, 'storePurchase'])
        ->name('pharmacy.purchases.store')->middleware('can:pharmacy.create');
    $r->get('/pharmacy/purchases/{id}', [PharmacyController::class, 'showPurchase'])
        ->name('pharmacy.purchases.show')->middleware('can:pharmacy.view');
    $r->post('/pharmacy/purchases/{id}/receive', [PharmacyController::class, 'receivePurchase'])
        ->name('pharmacy.purchases.receive')->middleware('can:pharmacy.update');
    // {id} catch-all MUST come last.
    $r->get('/pharmacy/{id}', [PharmacyController::class, 'show'])
        ->name('pharmacy.show')->middleware('can:pharmacy.view');

    // --- Inventory --------------------------------------------------------
    $r->get('/inventory', [InventoryController::class, 'index'])
        ->name('inventory.index')->middleware('can:inventory.view');
    $r->post('/inventory/items', [InventoryController::class, 'storeItem'])
        ->name('inventory.items.store')->middleware('can:inventory.create');
    // Literal paths MUST come before /inventory/{id} to avoid the {id} catch-all.
    $r->post('/inventory/purchases', [InventoryController::class, 'storePurchase'])
        ->name('inventory.purchases.store')->middleware('can:inventory.create');
    $r->get('/inventory/purchases/{id}', [InventoryController::class, 'showPurchase'])
        ->name('inventory.purchases.show')->middleware('can:inventory.view');
    $r->post('/inventory/purchases/{id}/receive', [InventoryController::class, 'receivePurchase'])
        ->name('inventory.purchases.receive')->middleware('can:inventory.update');
    $r->post('/inventory/adjustments', [InventoryController::class, 'requestAdjustment'])
        ->name('inventory.adjustments.store')->middleware('can:inventory.create');
    $r->post('/inventory/adjustments/{id}/approve', [InventoryController::class, 'approveAdjustment'])
        ->name('inventory.adjustments.approve')->middleware('can:inventory.update');
    $r->post('/inventory/adjustments/{id}/reject', [InventoryController::class, 'rejectAdjustment'])
        ->name('inventory.adjustments.reject')->middleware('can:inventory.update');
    // {id} catch-all MUST come last.
    $r->get('/inventory/{id}', [InventoryController::class, 'showItem'])
        ->name('inventory.show')->middleware('can:inventory.view');

    // --- Laboratory -------------------------------------------------------
    $r->get('/laboratory', [LaboratoryController::class, 'index'])
        ->name('laboratory.index')->middleware('can:laboratory.view');
    $r->get('/laboratory/create', [LaboratoryController::class, 'create'])
        ->name('laboratory.create')->middleware('can:laboratory.create');
    $r->post('/laboratory', [LaboratoryController::class, 'store'])
        ->name('laboratory.store')->middleware('can:laboratory.create');
    $r->get('/laboratory/{id}', [LaboratoryController::class, 'show'])
        ->name('laboratory.show')->middleware('can:laboratory.view');
    $r->post('/laboratory/{id}/items/{itemId}/collect', [LaboratoryController::class, 'collectSample'])
        ->name('laboratory.collect')->middleware('can:laboratory.update');
    $r->post('/laboratory/{id}/items/{itemId}/result', [LaboratoryController::class, 'enterResult'])
        ->name('laboratory.result')->middleware('can:laboratory.update');
    $r->post('/laboratory/{id}/items/{itemId}/verify', [LaboratoryController::class, 'verifyResult'])
        ->name('laboratory.verify')->middleware('can:laboratory.approve');
    $r->post('/laboratory/{id}/items/{itemId}/release', [LaboratoryController::class, 'releaseResult'])
        ->name('laboratory.release')->middleware('can:laboratory.approve');
    $r->post('/laboratory/{id}/alerts/{alertId}/acknowledge', [LaboratoryController::class, 'acknowledgeAlert'])
        ->name('laboratory.acknowledge')->middleware('can:laboratory.update');
    $r->get('/laboratory/{id}/print', [LaboratoryController::class, 'printReport'])
        ->name('laboratory.print')->middleware('can:laboratory.view');

    // --- Bed Management & Admissions ------------------------------------
    $r->get('/beds', [AdmissionController::class, 'bedsIndex'])
        ->name('beds.index')->middleware('can:beds.view');
    $r->get('/beds/wards', [AdmissionController::class, 'wardsIndex'])
        ->name('beds.wards')->middleware('can:beds.view');
    $r->post('/beds/wards', [AdmissionController::class, 'storeWard'])
        ->name('beds.wards.store')->middleware('can:beds.update');
    $r->post('/beds/rooms', [AdmissionController::class, 'storeRoom'])
        ->name('beds.rooms.store')->middleware('can:beds.update');
    $r->post('/beds/{id}/status', [AdmissionController::class, 'setBedStatus'])
        ->name('beds.status')->middleware('can:beds.update');

    $r->get('/admissions', [AdmissionController::class, 'index'])
        ->name('admissions.index')->middleware('can:admissions.view');
    $r->get('/admissions/create', [AdmissionController::class, 'create'])
        ->name('admissions.create')->middleware('can:admissions.create');
    $r->post('/admissions', [AdmissionController::class, 'store'])
        ->name('admissions.store')->middleware('can:admissions.create');
    $r->get('/admissions/{id}', [AdmissionController::class, 'show'])
        ->name('admissions.show')->middleware('can:admissions.view');
    $r->post('/admissions/{id}/transfer', [AdmissionController::class, 'transfer'])
        ->name('admissions.transfer')->middleware('can:admissions.update');
    $r->post('/admissions/{id}/discharge', [AdmissionController::class, 'discharge'])
        ->name('admissions.discharge')->middleware('can:admissions.update');

    // --- Reports ---------------------------------------------------------
    $r->get('/reports', [ReportsController::class, 'index'])
        ->name('reports.index')->middleware('can:reports.view');
    $r->get('/reports/export', [ReportsController::class, 'export'])
        ->name('reports.export')->middleware('can:reports.export');

    // --- Notifications ---------------------------------------------------
    $r->get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index')->middleware('auth');
    $r->post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read')->middleware('auth');
    $r->post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read_all')->middleware('auth');

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
