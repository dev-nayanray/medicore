# PROJECT_CONTEXT — MediCore HMS

> **Purpose:** the durable architecture contract for every future phase.
> Read this before adding any module. Last updated: Phase 7 (billing & financial management).

---

## 1. Implementation status

### ✅ Phase 1 — Foundation (COMPLETE)

MVC architecture, PDO layer, migrations/seeders, RBAC schema, audit trail,
settings, admin chrome (sidebar/topbar/search/notifications/themes), dashboard
with real stats + honest empty states, 32-assertion test suite.

### ✅ Phase 2 — Authentication & Access Control (COMPLETE, verified 2026-10-03)

| Area | State |
|---|---|
| Login/logout via AuthService (timing-uniform credential check) | ✅ Live |
| DB-backed rate limiting: 5 fails/email/10 min (+4x threshold per IP) | ✅ Live |
| Forgot/reset password: hashed single-use 60-min tokens, no enumeration | ✅ Live |
| Dev mail transport (storage/logs/mail.log; dev link surfaced in local) | ✅ Live |
| Forced password rotation (`must_change_password` + `password_current` middleware) | ✅ Live |
| Session: idle expiry ("session expired" notice), 30-min ID regeneration, fingerprint | ✅ Live |
| User CRUD: create/edit/deactivate/archive/restore/delete + confirm dialogs | ✅ Live |
| Role CRUD: configurable permission matrix (select-all per module), system-role protection | ✅ Live |
| Permission catalogue browser (50 abilities incl. approve + archive actions) | ✅ Live |
| Per-user permission overrides (grant/deny; deny > allow > role) | ✅ Live |
| Login attempt trail (`login_attempts`) + profile Activity page | ✅ Live |
| Privilege-escalation guards (super-admin assignable only by super-admin; self-protection) | ✅ Live |
| Searchable/filterable user directory (role + status filters, archived hidden) | ✅ Live |
| Test suite: 44 assertions (`php console test`) | ✅ 44/44 |

**Key mechanics to preserve:**

- **Auth::can() resolution order:** super-admin → direct deny → direct allow → role grant.
  Session snapshot re-syncs from the DB at most every 300 s (`Auth::PERMISSION_TTL`);
  call `Auth::expireSnapshot()` after permission edits for immediate effect.
- **Every state-changing route is POST + CSRF-verified + `can:` gated.** Destructive
  forms render `data-confirm="Title|Message[|danger]"` for the modal interceptor.
- **UserService self-protection:** no self-deactivate/archive/delete; super-admin
  accounts manageable only by super-admins; `mayAssignRoles()` blocks escalation.
- **Password policy:** `strong` validator rule (8+, upper, lower, digit) enforced on
  every password path (create, edit, reset, forced change, profile change).
- **Deactivated vs archived:** both block sign-in at the door; archived additionally
  hides the account from default lists and is reversible via restore.

### ✅ Phase 3 — Patient Management (COMPLETE, verified 2026-10-03)

| Area | State |
|---|---|
| Patient registration with auto-generated unique IDs (MCP-YYYY-NNNNN) | ✅ Live |
| Full CRUD: create / edit / view / archive / restore + confirm dialogs | ✅ Live |
| Multi-section form: personal, contact, emergency, clinical, optional first document | ✅ Live |
| Server-side validation (required, unique NID, email, enum, length rules) | ✅ Live |
| Patient directory: search, gender/blood/status filters, sortable columns, pagination | ✅ Live |
| CSV export (respects active filters) | ✅ Live |
| Duplicate detection: live AJAX check + server-side gate (phone / NID / name+DOB) | ✅ Live |
| Patient documents: secure upload (extension + finfo MIME sniff), private storage, gated download | ✅ Live |
| Encounter timeline: record visits (type, doctor, complaint, diagnosis, notes, status) | ✅ Live |
| Tabbed profile: overview, visits (timeline), documents, prescriptions/lab-reports/invoices (empty states) | ✅ Live |
| Printable patient summary (standalone print layout, hospital header, allergy banner) | ✅ Live |
| Archive preserves visits, documents and audit history (soft `archived_at` timestamp) | ✅ Live |
| Audit coverage: created / updated / archived / restored / visit recorded / document uploaded / downloaded / deleted / exported / summary printed | ✅ Live |
| Role-based access: `patients.view / create / update` gates on every route; medical info visible to clinical roles only | ✅ Live |
| Dashboard integration: patients stat tile live, sidebar link, quick-action buttons, global search returns patient results | ✅ Live |
| Responsive tables + mobile-friendly forms + empty states + toast notifications | ✅ Live |
| Test suite: 50+ assertions covering code generation, CRUD lifecycle, duplicate detection, directory filters/sort/pagination, CSV export, document upload validation, access control | ✅ Live |

**Key mechanics to preserve:**

- **Patient ID generation (`Patient::nextCode`):** reads `MAX(sequence)` for the
  current year, increments, collision-checks, retries up to 5 times. Final
  fallback is a time-based suffix. Never trust client input for the code.
- **Duplicate detection (`Patient::findPossibleDuplicates`):** matches on
  exact national_id, normalized phone (spaces/hyphens stripped), or
  case-insensitive first+last name + date_of_birth. Archived patients are
  excluded from duplicate candidates. The AJAX endpoint (`POST /api/patients/duplicates`)
  requires `patients.create` OR `patients.update`; the server-side gate in
  `PatientController::store/update` blocks the save unless `confirm_dupes=1`.
- **Document pipeline (`PatientService::storeDocument`):** files live OUTSIDE
  the docroot at `storage/uploads/patients/` with random 40-hex names — the
  original filename is stored only for display. Validation layers: PHP upload
  error code, size cap (5 MB), extension whitelist (pdf/jpg/jpeg/png/webp),
  finfo MIME sniffing (extension and content must agree). Downloads stream
  through `PatientController::downloadDocument` with `X-Content-Type-Options:
  nosniff` and `Content-Disposition: inline`. The `move_uploaded_file` call
  falls back to `copy()` for CLI/test contexts where `is_uploaded_file()` is
  false.
- **Archive is soft:** `archived_at` timestamp only. Visits, documents and
  audit history are never deleted on archive; the profile stays viewable
  (read-only) and the patient can be restored. The directory hides archived
  patients by default (`status=not_archived`) but the filter exposes them.
- **Doctor references on visits:** `PatientService::addVisit` validates the
  `doctor_id` against active users with a doctor/administrator/super-admin
  role; invalid references are silently dropped (not stored) to prevent
  dangling FKs when staff are later archived.

### ✅ Phase 4 — Doctor, Staff & Department Management (COMPLETE, verified 2026-10-04)

| Area | State |
|---|---|
| Department CRUD: create / edit / view / archive / restore + member roster | ✅ Live |
| Department overview dashboard: doctor/staff counts, contact, head doctor | ✅ Live |
| Doctor profiles: specialization, qualifications, registration no., bio, fees, room, dept, status | ✅ Live |
| Auto-generated doctor codes (MCD-YYYY-NNNNN, collision-safe) | ✅ Live |
| Doctor profile image upload (JPG/PNG/WebP, MIME-sniffed, private storage) | ✅ Live |
| Weekly recurring schedule editor (day-of-week + time range + max patients + room) | ✅ Live |
| Schedule overlap detection — same doctor + same day, server-side rejection | ✅ Live |
| Doctor leave records with approval workflow (pending → approved/rejected) | ✅ Live |
| Approved leave transitions doctor status → on_leave automatically | ✅ Live |
| Doctor profile tabs: overview, schedule, leaves, recent consultations | ✅ Live |
| Doctor consultation statistics (visit count from patient_visits) | ✅ Live |
| Staff profiles: employee ID, job title, department, employment type, hire date, status | ✅ Live |
| Auto-generated staff codes (MCS-YYYY-NNNNN, collision-safe) | ✅ Live |
| Staff shift scheduling with overlap detection (same staff + same date) | ✅ Live |
| Staff attendance: daily check-in/check-out with upsert on same date | ✅ Live |
| Staff leave management: 6 leave types, approval workflow, status transition | ✅ Live |
| Staff profile tabs: overview, shifts (upcoming 14), attendance (30-day summary), leaves | ✅ Live |
| Department-assignment for both doctors and staff (FK with SET NULL on dept archive) | ✅ Live |
| Audit coverage on every write: created/updated/archived/restored/schedule/shift/attendance/leave | ✅ Live |
| Role-based access: doctors/departments/staff view granted to clinical + admin roles; CRUD to admins | ✅ Live |
| Sidebar links for Doctors, Departments, Staff (replaced Soon placeholders) | ✅ Live |
| Dashboard quick-action buttons for Doctors and Staff | ✅ Live |
| Global search returns doctor / staff / department results (permission-gated) | ✅ Live |
| Dashboard module registry marks Doctors, Departments, Staff as installed | ✅ Live |
| Test suite: 13 new assertions covering dept CRUD/slug, doctor CRUD/schedule overlap/leave, staff CRUD/shift overlap/leave/attendance upsert, access control, search, module registry | ✅ Live |

**Key mechanics to preserve:**

- **Doctor / Staff profile extension pattern:** both `doctors` and `staff_profiles`
  have a 1:1 FK to `users.id` (the auth account). Auth stays in users; clinical
  and HR data live in the profile tables. A user can have at most one of each
  profile type (enforced by a UNIQUE constraint on user_id).
- **Code generation:** `Doctor::nextCode()` → `MCD-YYYY-NNNNN`, `StaffProfile::nextCode()`
  → `MCS-YYYY-NNNNN`. Both read MAX(sequence) + 1, collision-check, retry up
  to 5 times, fall back to a time-based suffix. Never trust client input for
  the code.
- **Department head_doctor_id references users (not doctors)** to avoid a
  circular FK with doctors.department_id. This keeps the schema acyclic while
  still validating that the head is a real user account.
- **Schedule overlap detection (`DoctorSchedule::overlaps`):** checks if
  `start_time < existing_end AND end_time > existing_start` for the same
  doctor + day_of_week. Adjacent slots (touching at a boundary, e.g. 09–13
  and 13–17) are allowed. Overlapping slots are rejected server-side.
- **Shift overlap detection (`StaffShift::overlaps`):** same interval logic
  for the same staff_id + shift_date. Prevents double-booking a staff member.
- **Leave status transitions:** when a doctor/staff leave is set to `approved`,
  the service automatically transitions the profile status to `on_leave`.
  Rejecting a leave does NOT revert the status (the most recent approved
  leave wins). This is intentional — rejecting one leave request should not
  silently reactivate someone who has another approved leave active.
- **Attendance upsert:** `staff_attendance` has a UNIQUE(staff_id, date)
  constraint. `StaffService::recordAttendance` checks for an existing row
  and updates it if present (no duplicate). This lets supervisors correct a
  record later in the day without creating a second entry.
- **Image uploads** (doctor/staff profile images) follow the same secure
  pipeline as patient documents: extension whitelist + `finfo` MIME sniffing
  + size cap (2 MB) + random stored name in `storage/uploads/{doctors,staff}/`.
  Downloads stream through a permission-gated controller (to be wired if
  profile-image serving is needed — currently images are optional and not
  rendered inline in the directory cards).

### ⏳ Planned modules (each = one future phase)

Modules light up on the dashboard/sidebar **automatically** when their
backing table exists (`DashboardService::moduleStatus()` + stat guards):

| Module | Backing table(s) | Permissions already seeded | State |
|---|---|---|---|
| ~~Patients~~ | `patients`, `patient_visits`, `patient_documents` | patients.view/create/update/delete | ✅ Phase 3 |
| ~~Doctors~~ | `doctors`, `doctor_schedules`, `doctor_leaves` | doctors.view/create/update/delete | ✅ Phase 4 |
| ~~Departments~~ | `departments` | departments.view/create/update/delete | ✅ Phase 4 |
| ~~Staff~~ | `staff_profiles`, `staff_shifts`, `staff_attendance`, `staff_leaves` | staff.view/create/update/delete | ✅ Phase 4 |
| ~~Appointments~~ | `appointments`, `appointment_reminders` | appointments.view/create/update/delete/approve | ✅ Phase 5 |
| ~~Consultations~~ | `consultations`, `prescriptions`, `prescription_items`, `consultation_attachments`, `consultation_amendments` | consultations.view/create/update/finalize/amend, prescriptions.view/create/update/finalize | ✅ Phase 6 |
| ~~Billing~~ | `invoices`, `invoice_items`, `payments`, `services`, `expenses` | billing.view/create/update/delete/approve, payments.view/create/approve, expenses.view/create/update/delete | ✅ Phase 7 |
| Bed management | `beds` | beds.view/update | ⏳ |
| Laboratory | `lab_tests` | laboratory.* (incl. approve) | ⏳ |
| Pharmacy | `medicines` | pharmacy.* | ⏳ |
| Reports | — | reports.view/export | ⏳ |

---

## 2. Request lifecycle

```
public/index.php (sole web entry point)
 ├─ Session::start()                       (hardened cookies, fingerprint)
 ├─ new Router + routes/web.php            (route table)
 ├─ global middleware pipeline: secure → csrf → route middleware (auth, can:) → controller
 └─ controller returns View/JSON/redirect
     └─ View::make()->render()             (layouts + sections, escaped output)
```

- **Routing:** `routes/web.php`. Routes carry names (`->name('users.index')`)
  and middleware. Resolve URLs only via `route('name')` / `url('path')` — the
  `url()` helper derives the host from the request so assets stay same-origin
  (CSP-safe) under any host the app is reached from.
- **Middleware:** `app/Middleware/*`, aliased in `Alias::MAP`
  (`auth`, `guest`, `csrf`, `can:<permission>`, `secure`). Add new aliases there.
- **Errors:** `ErrorHandler` renders `resources/views/errors/{404,500}.php`,
  JSON for ajax, plain text for CLI, and logs to `storage/logs/app-*.log`.
  Debug details only when `APP_DEBUG=true`.

## 3. Database conventions

- **Connection:** `App\Core\Database` — lazy PDO singleton, native prepared
  statements only (`ATTR_EMULATE_PREPARES=false`). Use `Database::query /
  queryOne / scalar / execute / transaction`.
- **Migrations:** `database/migrations/NNN_name.php` returning
  `['up' => fn(PDO), 'down' => fn(PDO)]`. Tracked in the `migrations` table by
  batch. **MySQL auto-commits DDL — never wrap CREATE/DROP in transactions**
  (the Migrator already accounts for this).
- **Conventions:** utf8mb4/unicode_ci, InnoDB, `id INT UNSIGNED AUTO_INCREMENT`,
  `created_at`/`updated_at` timestamps, FKs with explicit `ON DELETE`
  behaviour, snake_case columns, indexes on every FK and frequent filter.
- **Seeders:** `database/seeders/*.php` extending `Seeders\Seeder` with
  `run(PDO)`, `order()`, `label()`. Register new seeders in the `seeders()`
  list inside `console`. All seeders are idempotent (truncate-then-insert).

## 4. Security contract (do not weaken)

1. Every query uses prepared statements — no string interpolation of user
   data into SQL (table names must come from a whitelist, never from input).
2. Every echoed value goes through `e()` / component partials that escape.
3. Every POST route sits behind the global CSRF middleware; forms include
   `csrf_field()`, fetches send `X-CSRF-Token`.
4. Permission-gated routes use `->middleware('can:module.action')`;
   menu items and buttons render only when `can(...)` allows.
5. Security-relevant actions call `AuditService::log(...)` — login success/
   failure (with reason), logout, create/update/delete on any module,
   settings changes, password events, role changes.
6. New admin routes go under the `/admin` prefix group (auth +
   password_current middleware).
7. File uploads target `storage/uploads/<module>/` (OUTSIDE the docroot),
   validated by extension whitelist + `finfo` MIME sniffing + size cap,
   stored under random server-generated names — the original filename is
   kept only for display. Downloads stream through a permission-gated
   controller with `nosniff` + `Content-Disposition: inline`, never as
   public URLs. See `PatientService::storeDocument` for the reference
   implementation.
8. **Authentication flows go through `AuthService` / `PasswordResetService`**
   — never verify passwords ad-hoc in controllers. New failure reasons go
   into `login_attempts.failure_reason` for the history UI.
9. **Password changes must use the `strong` validator rule** and
   `User::updatePassword()` (which stamps `password_changed_at`).
10. **Reset tokens are hashed before storage** (`PasswordReset::issue`),
    single-use and 60-min TTL; the plaintext token exists only in the mail
    payload (and the local-dev link bridge).

## 5. Front-end conventions

- **Stack:** Tailwind (Play CDN vendored at `public/assets/vendor/tailwind/`
  with the design tokens configured in both layouts), Alpine.js (deferred),
  Lucide icons, Chart.js — all vendored; **no external CDN calls at runtime**.
- **Theme:** `darkMode:'class'`; palette = deep navy `navy-950` (#0b1f3a),
  teal accent (#0d9488→#2dd4bf), slate neutrals. Custom colors are defined in
  the `tailwind.config` script block of `layouts/admin.php` — keep the login
  layout's copy in sync when changing tokens.
- **Component classes** live in `public/assets/css/app.css`
  (`.btn`, `.input`, `.card`, `.badge-*`, `.nav-link`, `.table`, `.page-btn`,
  toggle switches). **Structural components** are PHP partials in
  `resources/views/components/` (`stat-card`, `empty-state`, `pagination`) —
  render with `$this->insert('components/x', [...])`.
- **Alpine components** are registered in `public/assets/js/app.js` under
  `alpine:init` (`layout`, `globalSearch`, `notifications`, `toasts`). Icons
  refresh automatically via MutationObserver — dynamically injected
  `[data-lucide]` elements just work.
- **Golden rule — no fake data:** dashboards and charts render only real
  query results; missing modules show empty states (see stat-card /
  empty-state components).

## 6. Adding a module (checklist)

1. Migration `database/migrations/NNN_create_<module>_table.php` (+ FKs to
   users/patients as appropriate) → `php console migrate`.
2. Model in `app/Models/` extending `App\Models\Model`.
3. Service in `app/Services/` for business logic + dashboard stat method in
   `DashboardService` (guard with `Database::tableExists('<table>')`).
4. Controller in `app/Controllers/Admin/`, routes in `routes/web.php` with
   `can:<module>.<action>` middleware.
5. Views in `resources/views/admin/<module>/` extending `layouts/admin`.
6. Permissions for the module are already seeded; if new ones are needed,
   extend `PermissionSeeder` and the role matrix, then reseed.
7. Replace the sidebar "Soon" item in `layouts/admin.php` with a real link
   (keep the `can:` filter pattern).
8. Add audit logging to every write action.
9. Extend `tests/run.php` with module tests; run `php console test`.

## 7. Known limitations (accepted through phase 7)

- Deactivating/archiving a user does not kill their live session instantly;
  it lands on the next 5-minute snapshot re-sync or their next request after
  TTL (sign-in is blocked immediately at the door either way).
- `MailService` is a dev transport (writes storage/logs/mail.log). Swap
  `send()` for a real mailer in production; call sites stay unchanged.
- Permission catalogue is code-defined (ships with migrations + seeders);
  there is intentionally no runtime permission-create UI.
- Tailwind runs via the (locally vendored) Play CDN — fine for an admin
  tool; swap to a compiled build for public-facing traffic.
- Remember-me tokens, password-reset emails with real SMTP, and 2FA are not
  implemented. `php console auth:purge` must be scheduled (cron/Task
  Scheduler) to prune old login attempts and consumed reset tokens.
- CSP allows `'unsafe-eval'` (Alpine standard build). Switching to Alpine's
  CSP build would tighten this when required.
- Patient documents are stored on the local filesystem under
  `storage/uploads/patients/`. For multi-server deployments, swap the
  `PatientService` file operations for an S3-compatible adapter — the DB
  schema and download controller stay unchanged.
- Patient prescriptions, lab reports and invoices tabs render honest empty
  states today; they fill automatically once the corresponding modules ship
  (laboratory, pharmacy, billing).
- Duplicate detection is intentional/assisted, not blocking — staff can
  override with `confirm_dupes=1`. A future hard-block policy could be added
  per role if a stricter workflow is required.
- The encounter timeline records visits created through the profile form.
  Integration with an appointments module (auto-creating a visit when an
  appointment is completed) is deferred to the appointments phase.
- Doctor availability (`doctor_schedules`) and leave (`doctor_leaves`) are
  stored and queryable, but the actual integration with an appointments
  booking flow (slot generation, leave blocking) is deferred to the
  appointments phase. The `DoctorSchedule::isOnLeaveOn()` and
  `DoctorSchedule::slotsForDate()` helpers are ready for that integration.
- Doctor and staff profile images are uploaded and stored securely but are
  not yet rendered inline on directory cards/profile pages. A future
  image-serving route (permission-gated, like patient document downloads)
  would enable inline display without exposing files as public URLs.

### ✅ Phase 5 — Appointment & Queue Management (COMPLETE, verified 2026-10-04)

| Area | State |
|---|---|
| Book / edit / reschedule / cancel / complete appointments | ✅ Live |
| Patient + doctor + department + date + time slot selection | ✅ Live |
| Walk-in appointments (auto checked-in + immediate queue token) | ✅ Live |
| Auto-generated unique codes (APT-YYYY-NNNNN, collision-safe) | ✅ Live |
| Per-day sequential queue tokens (Q-NNN, per-department) | ✅ Live |
| Status lifecycle: pending → confirmed → checked_in → in_consultation → completed (or cancelled / no_show) | ✅ Live |
| Overlap prevention via DB transaction + SELECT ... FOR UPDATE + range-overlap check | ✅ Live |
| Adjacent slots allowed (end == start boundary touch) | ✅ Live |
| Doctor schedule validation (booking must fall within a working slot, skip for walk-in/telemedicine) | ✅ Live |
| Doctor leave blocking (skip schedule validation when doctor is on leave that day) | ✅ Live |
| Queue dashboard: swim-lanes by status, quick-action buttons, today's stats tiles | ✅ Live |
| Calendar: day / week / month views with color-coded status indicators | ✅ Live |
| Appointment directory: search + status/type/doctor/department/date filters + CSV export | ✅ Live |
| Reports: status breakdown, type breakdown, doctor leaderboard, 14-day volume chart, no-show rate | ✅ Live |
| Configurable appointment reminders (appointment_reminders table, 24h-before schedule, process() gateway adapter) | ✅ Live |
| Status transition validation (enforced lifecycle map — terminal states blocked) | ✅ Live |
| Audit coverage: created / confirmed / checked_in / in_consultation / completed / cancelled / no_show / rescheduled / notes_updated / exported | ✅ Live |
| Role-based access: appointments.view / create / update / delete / approve on all routes | ✅ Live |
| Dashboard integration: appointments stat tile live, daily-volume chart, quick-action buttons, sidebar link | ✅ Live |
| Global search returns appointment results (permission-gated) | ✅ Live |
| Test suite: 11 new assertions covering code gen, overlap prevention, walk-in lifecycle, status transitions, cancel, queue tokens, directory filters, no-show rate, reminders, dashboard stat, search | ✅ Live |

**Key mechanics to preserve:**

- **Overlap detection (`Appointment::overlaps`):** range overlap formula
  `start1 < end2 AND start2 < end1`. Touching boundaries (end == start)
  are allowed — adjacent slots are not overlapping. Cancelled and no-show
  appointments are excluded from the overlap check.
- **Concurrent booking prevention (`AppointmentService::book`):** the
  overlap check + INSERT run inside a `beginTransaction()` /
  `commit()` block. Before the check, `SELECT id FROM appointments WHERE
  doctor_id = ? AND appointment_date = ? FOR UPDATE` locks all existing
  rows for that doctor + date. Any concurrent transaction trying the
  same lock blocks until the first commits, then sees the new row and
  correctly detects the overlap. The unique constraint
  `(doctor_id, appointment_date, start_time)` is a last-resort guard at
  the DB level for exact-time duplicates.
- **Walk-ins vs scheduled:** walk-ins (`appointment_type = 'walk_in'`)
  auto-start in `checked_in` status and get a queue token immediately.
  Scheduled appointments start in `pending` and get a queue token only
  when checked in. Walk-ins require a doctor only if the front desk
  assigns one at booking time; unassigned walk-ins are allowed.
- **Queue token generation (`Appointment::nextQueueToken`):** reads
  `MAX(sequence)` for the given date (+ optional department), increments,
  formats as `Q-NNN`. Walk-ins get it at booking; scheduled appointments
  get it at the `checked_in` transition.
- **Status transition map:** enforced server-side in
  `AppointmentService::TRANSITIONS`. Terminal states (completed,
  cancelled, no_show) have empty allowed-next arrays. Attempting an
  illegal transition returns `['ok' => false, 'error' => …]`.
  Side effects per transition: `checked_in` stamps `checked_in_at` +
  assigns `queue_token`; `in_consultation` stamps
  `consultation_started_at`; `completed` stamps `completed_at`.
- **Doctor schedule integration:** `AppointmentService::book` calls
  `DoctorSchedule::slotsForDate()` to find the doctor's working slots
  for that day-of-week. The booking's (start, end) must fall within a
  slot's (start, end). Walk-ins and telemedicine appointments skip this
  check (they may be outside regular hours). If the doctor is on leave
  (`DoctorSchedule::isOnLeaveOn`), the schedule check is skipped (the
  leave record is the authoritative block).
- **Reminders:** `AppointmentService::scheduleReminder()` creates a
  `appointment_reminders` row scheduled 24h before the appointment. If
  the appointment is within 24h, the reminder is due immediately.
  `AppointmentService::processReminders()` is the integration-ready
  gateway: it queries due pending reminders, "sends" them (dev transport
  writes to `storage/logs/mail.log`), marks them `sent`. Swap the
  `file_put_contents` call for a real SMS/email API in production — the
  DB schema and service contract stay unchanged. Reschedule cancels
  pending reminders and schedules a new one for the new date. Cancel
  cancels pending reminders too.

### ✅ Phase 6 — Consultation, Clinical Records & Prescriptions (COMPLETE, verified 2026-10-04)

| Area | State |
|---|---|
| Create consultations from appointments or walk-in visits | ✅ Live |
| Record symptoms, observations, vitals, clinical notes, diagnoses | ✅ Live |
| Vitals: temp, BP (systolic/diastolic), pulse, resp rate, SpO₂, weight, height, auto-BMI | ✅ Live |
| Patient consultation history (timeline) | ✅ Live |
| Digital prescriptions with medicines, dosage, frequency, duration, quantity, instructions | ✅ Live |
| Follow-up dates and referrals (to specialist/department + reason) | ✅ Live |
| Printable prescription with hospital branding, patient details, allergies banner | ✅ Live |
| Draft → finalized lifecycle for consultations + prescriptions | ✅ Live |
| Finalized records are immutable — corrections require formal amendments | ✅ Live |
| Clinical record amendment history (field, old value, new value, reason, authorizer) | ✅ Live |
| Secure clinical attachments (PDF/JPG/PNG, MIME-sniffed, private storage, gated downloads) | ✅ Live |
| Doctor workspace (today's consultations, summary tiles, quick actions) | ✅ Live |
| Patient summary sidebar (demographics, allergies, vitals, recent history) | ✅ Live |
| Dynamic prescription builder (Alpine.js — add/remove medicine rows inline) | ✅ Live |
| Audit coverage: created / updated / finalized / amended / attachment uploaded/downloaded/deleted / prescription created/updated/finalized/printed | ✅ Live |
| Role-based clinical access: consultations.view/create/update/finalize/amend, prescriptions.view/create/update/finalize | ✅ Live |
| Sidebar Clinical section (Workspace + Consultations) | ✅ Live |
| Dashboard: clinical workspace quick-action button, module registry marks Consultations + Prescriptions installed | ✅ Live |
| Global search returns consultation results (permission-gated) | ✅ Live |
| Test suite: 9 new assertions covering code gen, create/update/finalize, amendment, cannot amend draft, prescription create/finalize, no-empty-prescription, access control, patient history, dashboard registry, search | ✅ Live |

**Key mechanics to preserve:**

- **Draft → Finalized lifecycle:** consultations start as `draft` (editable).
  `ConsultationService::finalize()` stamps `finalized_at` + `finalized_by`
  and flips `status` to `finalized`. Once finalized, `update()` returns an
  error — corrections must go through `amend()`. If the consultation is
  linked to an appointment, finalizing also marks the appointment as
  `completed`.
- **Amendment trail:** `ConsultationService::amend()` records the old value,
  new value, reason, and authorizing user in `consultation_amendments`
  BEFORE applying the change. The consultation status becomes `amended`.
  Only finalized/amended consultations can be amended. A reason is always
  required (minimum 5 chars). The amendable fields are whitelisted (clinical
  text fields + vitals).
- **Prescription 1:1 with consultation:** the `prescriptions` table has a
  unique constraint on `consultation_id` — only one prescription per
  consultation. `PrescriptionService::create()` checks for an existing
  prescription first.
- **Prescription finalization gate:** `PrescriptionService::finalize()`
  requires the parent consultation to be finalized first (prevents
  finalizing a prescription against an unfinalized diagnosis). It also
  requires at least one medicine item. Finalized prescriptions are
  immutable — `update()` returns an error.
- **Prescription items replacement:** `PrescriptionService::replaceItems()`
  deletes all existing items then inserts the new set. This makes the
  prescription builder on the UI a simple "replace all" pattern — the
  Alpine.js form submits all rows every time.
- **Auto-BMI calculation:** `ConsultationService::normalizeVitals()`
  auto-calculates BMI from weight + height when both are present
  (weight[kg] / (height[m])²). The BMI column is read-only on the form.
- **Clinical attachments pipeline:** same secure pattern as patient
  documents — extension whitelist (PDF/JPG/PNG/WebP) + `finfo` MIME sniff
  + size cap (10 MB) + random stored name in
  `storage/uploads/consultations/`. Downloads stream through a
  permission-gated controller with `nosniff` + `Content-Disposition:
  inline`. Attachments can only be uploaded/deleted while the consultation
  is a draft (finalized records lock attachments too).
- **Clinical permissions:** strict role-based access —
  `consultations.create/finalize/amend` and `prescriptions.create/finalize`
  are granted to doctors and administrators only. Nurses get view-only
  access. Pharmacists get `prescriptions.view` + `prescriptions.finalize`
  (for dispensing). Receptionists get `consultations.view` (read-only).
  Accountants have no clinical access at all.
- Staff shift overlap detection prevents same-day double-booking for one
  staff member, but does not enforce minimum rest between an evening shift
  end and a next-day morning shift start. That gap rule can be added to
  `StaffShift::overlaps` when the scheduling policy is finalised.
- Attendance is manually recorded by a supervisor. Auto check-in via
  hardware (RFID / biometric / QR) is not implemented; the schema supports
  the data but the capture is manual for now.
- Leave balance / accrual tracking (annual leave entitlement + consumed
  count) is not modelled. The current design records each leave request
  individually; a future balance ledger could aggregate them per year.
- Appointment reminders use a dev transport (`storage/logs/mail.log`).
  `AppointmentService::processReminders()` is the integration-ready hook
  — swap the `file_put_contents` call for a real SMS/email API in
  production. `php console` does not yet have an `appointments:remind`
  command; a cron job should call `AppointmentService::processReminders()`
  every 5 minutes in production.
- The calendar views are server-rendered (full page reload on view/date
  change). A future Alpine-powered AJAX calendar would avoid the reload
  for smoother navigation; the current design is fast enough for an
  admin tool.
- Appointment reschedule does not notify the patient automatically. The
  reminder is re-scheduled for the new date, but no "your appointment
  has been rescheduled" notification is sent. Add a second reminder row
  with `channel = 'in_app'` or a direct notification if needed.
- No-show detection is manual (a supervisor clicks "No-show"). Auto
  no-show detection (mark as no_show if `checked_in_at` is still NULL
  30 minutes past `start_time`) is not implemented; a scheduled job
  could handle this.
- Prescription medicine names are free-text (no FK to a medicines
  table). When the pharmacy module ships, the `medicine_name` column
  can be cross-referenced or migrated to a FK — the current design
  keeps the prescription self-contained and does not block on pharmacy.
- Clinical attachments are stored on the local filesystem under
  `storage/uploads/consultations/`. For multi-server deployments, swap
  the `ConsultationService` file operations for an S3-compatible
  adapter — the DB schema and download controller stay unchanged.
- The amendment workflow requires a reason but does not require a
  second-doctor co-sign for high-risk fields (diagnoses, clinical
  notes). A future policy could add a `co_signed_by` column and gate
  specific fields behind dual authorization.
- Consultations link to appointments via an optional FK, but the
  reverse lookup (appointment → consultation) is not surfaced on the
  appointment detail page yet. The `appointment_id` on consultations
  is the authoritative link; the appointment show view can be extended
  to display the linked consultation when that integration is needed.

### ✅ Phase 7 — Billing & Financial Management (COMPLETE, verified 2026-10-04)

| Area | State |
|---|---|
| Configurable hospital services catalogue (6 categories, 40 seeded services with pricing) | ✅ Live |
| Generate invoices with dynamic line items linked to patients + services | ✅ Live |
| Automatic subtotal, discount (%), tax (%), and total calculations — all DECIMAL | ✅ Live |
| Full and partial payment handling with outstanding balance tracking | ✅ Live |
| 6 payment methods (cash, card, mobile_banking, bank_transfer, insurance, other) | ✅ Live |
| Duplicate payment prevention (transaction + row lock + 5-min duplicate detection) | ✅ Live |
| Refunds with authorization (payments.approve gate) — negative payments, status transition | ✅ Live |
| Invoice cancellation with reason (draft/sent/partial only — paid requires refund first) | ✅ Live |
| Unique invoice (INV-YYYY-NNNNN), payment (PAY-YYYY-NNNNN), expense (EXP-YYYY-NNNNN) codes | ✅ Live |
| Printable invoice (standalone HTML, hospital branding, auto-print dialog → Save as PDF) | ✅ Live |
| Expense management (7 categories, CRUD, code generation) | ✅ Live |
| Revenue & payment dashboard (KPI tiles, 12-month revenue chart, payment method breakdown, daily collection chart) | ✅ Live |
| Financial reports (daily/weekly/monthly/custom-range — P&L, expense breakdown, method breakdown) | ✅ Live |
| Invoice directory with advanced filters (search/status/date range) + CSV export | ✅ Live |
| Audit coverage: invoice created/cancelled/printed/exported, payment recorded/refunded, expense created/updated/deleted | ✅ Live |
| Role-based access: billing.view/create/update/delete, payments.view/create/approve, expenses.view/create/update/delete | ✅ Live |
| Dashboard integration: revenue stat tile + pending bills tile live, monthly revenue chart, quick-action buttons | ✅ Live |
| Sidebar Finance section (Billing & Invoices, Services & Pricing, Expenses) | ✅ Live |
| Test suite: 10 new assertions covering invoice calc, full/partial payment, duplicate detection, refund, cancel, expense, stats, access control, dashboard registry | ✅ Live |

**Key mechanics to preserve:**

- **Monetary precision:** all monetary columns use `DECIMAL(12,2)` for amounts
  and `DECIMAL(5,2)` for percentages. PHP `round()` with 2 decimal places is
  used for all calculations. Never use `FLOAT` for money — it introduces
  rounding errors.
- **Invoice calculation formula:** `line_total = (quantity * unit_price) -
  item_discount`; `subtotal = SUM(line_total)`; `discount_amount = subtotal *
  (discount_percentage / 100)`; `after_discount = subtotal - discount_amount`;
  `tax_amount = after_discount * (tax_percentage / 100)`; `total =
  after_discount + tax_amount`. All rounded to 2 decimal places.
- **Payment recording (`BillingService::recordPayment`):** runs inside a
  `beginTransaction` / `commit` block. Before inserting the payment,
  `SELECT * FROM invoices WHERE id = ? FOR UPDATE` locks the invoice row.
  Then a duplicate check: same invoice + method + amount + reference within
  5 minutes. If the payment exceeds the balance, it's rejected. After
  insert, `Invoice::recalculate()` updates `paid_amount`, `balance_due`,
  and `status` (sent → partially_paid → paid).
- **Refund recording (`BillingService::recordRefund`):** same transaction +
  lock pattern. Creates a payment with negative `amount` and `is_refund = 1`.
  Requires a reason (min 5 chars). Cannot refund more than `paid_amount`.
  After insert, `Invoice::recalculate()` is called, and if `paid_amount`
  drops to 0 (and there was ever a payment), status becomes `refunded`.
  Requires `payments.approve` permission — only accountants and admins.
- **Invoice cancellation:** only `draft`/`sent`/`partially_paid` invoices
  can be cancelled (paid invoices must be refunded first). A reason is
  required. Pending payments are voided. The `cancelled_at`/`cancelled_by`
  /`cancellation_reason` fields are stamped.
- **`Invoice::recalculate()`:** recomputes `paid_amount` from the payments
  table (`SUM(amount) WHERE status = 'completed'`), then derives
  `balance_due = total - paid_amount`, and sets `status` based on the
  ratio: `paid <= 0 → sent`, `paid >= total → paid`, `0 < paid < total →
  partially_paid`. Called after every payment/refund inside the
  transaction.
- **Services catalogue:** the `services` table has a `category` enum
  (consultation, laboratory, procedure, medicine, admission, other) and
  `is_active` flag. Services are soft-deactivated (not hard-deleted) to
  preserve invoice item references. The `medicine_name` on prescriptions
  is free-text — when the pharmacy module ships, services can be
  cross-referenced.
