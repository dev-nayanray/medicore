<?php
$this->extend('layouts/marketing');
$title = 'MediCore — Smarter Hospital Management. Better Patient Care.';
$features = [
    ['icon' => 'users', 'title' => 'Patient Management', 'desc' => 'Full patient registration, medical history, allergies, documents, visit timeline, and printable summaries.'],
    ['icon' => 'stethoscope', 'title' => 'Doctor & Staff Management', 'desc' => 'Doctor profiles with schedules, staff employment records, shifts, attendance, and leave management.'],
    ['icon' => 'calendar-days', 'title' => 'Appointment Scheduling', 'desc' => 'Interactive calendar with day/week/month views, walk-in support, queue tokens, and overlap prevention.'],
    ['icon' => 'clipboard-list', 'title' => 'Electronic Medical Records', 'desc' => 'Clinical consultations with vitals, diagnoses, prescriptions, amendments, and attachment management.'],
    ['icon' => 'prescription', 'title' => 'Prescription Management', 'desc' => 'Digital prescriptions with dynamic medicine builder, dosage tracking, and printable branded prescriptions.'],
    ['icon' => 'receipt-text', 'title' => 'Billing & Invoicing', 'desc' => 'Dynamic invoices with automatic calculations, partial payments, refunds, expense tracking, and financial reports.'],
    ['icon' => 'pill', 'title' => 'Pharmacy & Inventory', 'desc' => 'Medicine catalogue with batch tracking, FEFO dispensing, purchase orders, stock movements, and low-stock alerts.'],
    ['icon' => 'flask-conical', 'title' => 'Laboratory Management', 'desc' => 'Test orders with sample collection, result entry, verification workflow, critical alerts, and printable reports.'],
    ['icon' => 'bed-double', 'title' => 'Inpatient & Bed Management', 'desc' => 'Ward/room/bed configuration, patient admission, bed transfers, discharge summaries, and occupancy dashboard.'],
    ['icon' => 'chart-pie', 'title' => 'Reports & Analytics', 'desc' => 'Cross-module reporting with date-range filters, CSV export, and real MySQL analytics across all departments.'],
    ['icon' => 'shield-check', 'title' => 'Role-Based Access Control', 'desc' => 'Granular permissions per role, per-user overrides, 7 roles with tailored access across every module.'],
    ['icon' => 'bell', 'title' => 'Notifications & Alerts', 'desc' => 'In-app notification center with low-stock, expiry, lab-pending, and appointment reminder alerts.'],
];
$benefits = [
    ['icon' => 'settings', 'title' => 'Simplified Operations', 'desc' => 'Centralize every hospital workflow — from patient registration to discharge — in one unified system.'],
    ['icon' => 'zap', 'title' => 'Faster Appointments', 'desc' => 'Reduce patient wait times with intelligent scheduling, queue tokens, and real-time availability checks.'],
    ['icon' => 'database', 'title' => 'Centralized Records', 'desc' => 'Every patient interaction — visits, prescriptions, lab results, billing — in one connected record.'],
    ['icon' => 'eye', 'title' => 'Administrative Visibility', 'desc' => 'Real-time dashboards show revenue, appointments, bed occupancy, and staff workload at a glance.'],
    ['icon' => 'file-text', 'title' => 'Reduced Paperwork', 'desc' => 'Digital consultations, e-prescriptions, and automated invoicing eliminate manual paperwork.'],
    ['icon' => 'git-branch', 'title' => 'Department Coordination', 'desc' => 'Doctors, nurses, pharmacists, lab technicians, and accountants all work from the same platform.'],
];
$solutions = [
    ['icon' => 'building-2', 'title' => 'Hospital Administration', 'desc' => 'Full-system oversight with dashboards, audit trails, and role management for the entire hospital.'],
    ['icon' => 'user-cog', 'title' => 'Reception & Front Desk', 'desc' => 'Register patients, book appointments, collect payments, and manage walk-in queues from one desk.'],
    ['icon' => 'stethoscope', 'title' => 'Doctors & Clinical Staff', 'desc' => 'Access patient history, record consultations, prescribe medicines, and order lab tests from a clinical workspace.'],
    ['icon' => 'receipt-text', 'title' => 'Billing & Accounts', 'desc' => 'Generate invoices, record payments, manage refunds, track expenses, and export financial reports.'],
    ['icon' => 'pill', 'title' => 'Pharmacy', 'desc' => 'Dispense from finalized prescriptions, track batches, manage stock, and receive purchase orders with FEFO deduction.'],
    ['icon' => 'flask-conical', 'title' => 'Laboratory', 'desc' => 'Order tests, collect samples, enter results, verify with separation of duties, and release printable reports.'],
    ['icon' => 'boxes', 'title' => 'Inventory Management', 'desc' => 'Track supplies, receive purchases, request adjustments with approval, and monitor stock valuation.'],
];
$faqs = [
    ['q' => 'What is MediCore?', 'a' => 'MediCore is a complete hospital management system built on PHP, MySQL, Tailwind CSS, and Alpine.js. It covers patient management, appointments, consultations, prescriptions, billing, pharmacy, laboratory, inpatient bed management, reporting, and notifications — all in one platform.'],
    ['q' => 'Which hospital departments can use it?', 'a' => 'MediCore supports hospital administration, reception/front desk, doctors and clinical staff, billing and accounts, pharmacy, laboratory, and inventory management. Each role gets tailored access with permission-based controls.'],
    ['q' => 'Can multiple staff members access the system?', 'a' => 'Yes. MediCore supports 7 predefined roles (Super Admin, Administrator, Doctor, Nurse, Receptionist, Pharmacist, Lab Technician, Accountant) with granular per-module, per-action permissions. Multiple staff can work simultaneously with full audit logging.'],
    ['q' => 'Is MediCore mobile-friendly?', 'a' => 'Yes. The entire interface is built with Tailwind CSS and is fully responsive across desktop, tablet, and mobile devices. The sidebar collapses on mobile, tables scroll horizontally, and forms adapt to small screens.'],
    ['q' => 'Can the system be customized?', 'a' => 'MediCore is built on raw PHP with a clean MVC architecture. New modules follow a documented 9-step checklist. The permission catalogue, role matrix, service catalogue, ward/room/bed config, and pricing are all configurable from the admin panel.'],
    ['q' => 'How can I request a demo?', 'a' => 'Use the contact form on our Contact page. Fill in your name, hospital name, work email, and a brief message — we will get back to you to schedule a personalized demo.'],
    ['q' => 'How is hospital data protected?', 'a' => 'MediCore enforces: PDO prepared statements (no SQL injection), CSRF tokens on every form, bcrypt password hashing, session fingerprinting, role-based access control on every route, audit logging on every write action, and MIME-sniffed file upload validation with private storage outside the docroot.'],
];
?>
<?php $this->section('content'); ?>

<!-- ===== HERO ===== -->
<section class="relative overflow-hidden pt-20">
    <!-- Animated background -->
    <div class="absolute inset-0 -z-10">
        <div class="absolute left-1/4 top-0 h-96 w-96 rounded-full bg-teal-500/10 blur-3xl animate-blob"></div>
        <div class="absolute right-1/4 top-20 h-96 w-96 rounded-full bg-navy-500/10 blur-3xl animate-blob" style="animation-delay:2s"></div>
        <div class="absolute left-1/2 top-40 h-72 w-72 rounded-full bg-teal-400/5 blur-3xl animate-blob" style="animation-delay:4s"></div>
    </div>
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <!-- Left: copy -->
            <div class="reveal">
                <span class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-teal-500"></span>
                    Modern Hospital Management Platform
                </span>
                <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-navy-950 sm:text-5xl lg:text-6xl">
                    Smarter Hospital Management.
                    <span class="bg-gradient-to-r from-teal-500 to-teal-700 bg-clip-text text-transparent">Better Patient Care.</span>
                </h1>
                <p class="mt-5 max-w-lg text-lg leading-relaxed text-slate-600">
                    MediCore streamlines hospital operations with a unified platform for patient management, appointments, consultations, billing, pharmacy, laboratory, and bed management — all secured with role-based access control.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="<?= url('/contact') ?>" class="rounded-xl bg-teal-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-teal-600/30 transition-all hover:bg-teal-700 hover:shadow-xl hover:shadow-teal-600/40">
                        Get Started
                    </a>
                    <a href="<?= url('/#features') ?>" class="rounded-xl border border-slate-300 px-6 py-3 text-base font-semibold text-slate-700 transition-all hover:border-teal-400 hover:text-teal-600">
                        Explore Features
                    </a>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-500">
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-teal-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1l2.5 5.5L18 7l-4 4 1 6-5-3-5 3 1-6-4-4 5.5-.5z"/></svg> Secure & Auditable</span>
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-teal-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3 9H7v-2h6v2z"/></svg> Efficient Workflows</span>
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-teal-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4M10 2a8 8 0 100 16 8 8 0 000-16z" stroke="currentColor" stroke-width="2" fill="none"/></svg> Role-Based Access</span>
                </div>
            </div>
            <!-- Right: dashboard preview -->
            <div class="relative reveal" style="animation-delay:0.2s">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-navy-950/10">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                        <div class="flex gap-1.5"><span class="h-3 w-3 rounded-full bg-rose-400"></span><span class="h-3 w-3 rounded-full bg-amber-400"></span><span class="h-3 w-3 rounded-full bg-emerald-400"></span></div>
                        <span class="ml-2 text-xs font-medium text-slate-400">MediCore Dashboard</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 pt-3">
                        <div class="rounded-lg bg-teal-50 p-3"><p class="text-2xl font-bold text-teal-700">12</p><p class="text-[10px] text-slate-400">Patients Today</p></div>
                        <div class="rounded-lg bg-navy-50 p-3"><p class="text-2xl font-bold text-navy-700">8</p><p class="text-[10px] text-slate-400">Appointments</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><p class="text-2xl font-bold text-emerald-700">৳45k</p><p class="text-[10px] text-slate-400">Revenue</p></div>
                    </div>
                    <div class="mt-3 rounded-lg bg-slate-50 p-3">
                        <div class="flex items-end gap-1.5 h-20">
                            <div class="w-1/7 rounded-t bg-teal-400" style="height:40%"></div>
                            <div class="w-1/7 rounded-t bg-teal-500" style="height:65%"></div>
                            <div class="w-1/7 rounded-t bg-teal-400" style="height:30%"></div>
                            <div class="w-1/7 rounded-t bg-teal-600" style="height:80%"></div>
                            <div class="w-1/7 rounded-t bg-teal-500" style="height:55%"></div>
                            <div class="w-1/7 rounded-t bg-teal-700" style="height:90%"></div>
                            <div class="w-1/7 rounded-t bg-teal-500" style="height:45%"></div>
                        </div>
                        <p class="mt-2 text-[10px] text-slate-400">Weekly Appointments</p>
                    </div>
                    <div class="mt-3 space-y-1.5">
                        <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-2 py-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span><span class="text-[11px] font-medium text-slate-600">Dr. Sarah Chen</span><span class="ml-auto text-[10px] text-slate-400">Cardiology · Room 3</span></div>
                        <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-2 py-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span><span class="text-[11px] font-medium text-slate-600">Dr. Imran Hossain</span><span class="ml-auto text-[10px] text-slate-400">Neurology · OPD-2</span></div>
                    </div>
                </div>
                <!-- Floating cards -->
                <div class="absolute -left-6 top-1/3 hidden rounded-xl border border-slate-200 bg-white p-3 shadow-xl lg:block animate-float">
                    <div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-500/10 text-emerald-600"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4M10 2a8 8 0 100 16 8 8 0 000-16z" stroke="currentColor" stroke-width="2" fill="none"/></svg></span><div><p class="text-xs font-semibold text-slate-800">Payment Received</p><p class="text-[10px] text-slate-400">৳1,500 · Cash</p></div></div>
                </div>
                <div class="absolute -right-4 bottom-1/4 hidden rounded-xl border border-slate-200 bg-white p-3 shadow-xl lg:block animate-float" style="animation-delay:1.5s">
                    <div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-rose-500/10 text-rose-600"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2L2 18h16L10 2z"/></svg></span><div><p class="text-xs font-semibold text-slate-800">Low Stock Alert</p><p class="text-[10px] text-slate-400">Metformin 500mg</p></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== TRUST & STATS ===== -->
<section class="border-y border-slate-100 bg-slate-50 py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-6 text-center md:grid-cols-4">
            <div class="reveal"><p class="text-3xl font-extrabold text-teal-600" data-counter="12">12</p><p class="mt-1 text-sm text-slate-500">Integrated Modules</p></div>
            <div class="reveal" style="animation-delay:0.1s"><p class="text-3xl font-extrabold text-teal-600" data-counter="7">7</p><p class="mt-1 text-sm text-slate-500">Role Types</p></div>
            <div class="reveal" style="animation-delay:0.2s"><p class="text-3xl font-extrabold text-teal-600" data-counter="51">51</p><p class="mt-1 text-sm text-slate-500">Database Tables</p></div>
            <div class="reveal" style="animation-delay:0.3s"><p class="text-3xl font-extrabold text-teal-600" data-counter="265">265</p><p class="mt-1 text-sm text-slate-500">PHP Files</p></div>
        </div>
    </div>
</section>

<!-- ===== FEATURES ===== -->
<section id="features" class="py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Everything your hospital needs</h2>
            <p class="mt-3 text-lg text-slate-600">Twelve integrated modules covering the full patient journey — from registration to discharge.</p>
        </div>
        <div class="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($features as $f): ?>
            <div class="group rounded-2xl border border-slate-200 bg-white p-6 transition-all hover:-translate-y-1 hover:border-teal-300 hover:shadow-xl reveal">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-teal-500/10 text-teal-600 transition-transform group-hover:scale-110">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </span>
                <h3 class="mt-4 text-lg font-semibold text-navy-950"><?= e($f['title']) ?></h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($f['desc']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== SHOWCASE ===== -->
<section id="showcase" class="bg-navy-950 py-20 text-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">See MediCore in action</h2>
            <p class="mt-3 text-lg text-slate-400">Switch between key modules to preview the interface.</p>
        </div>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-2" id="showcase-tabs">
            <button onclick="switchTab('dashboard')" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:border-teal-400 hover:text-teal-400 showcase-tab-active">Admin Dashboard</button>
            <button onclick="switchTab('patients')" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:border-teal-400 hover:text-teal-400">Patient Management</button>
            <button onclick="switchTab('calendar')" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:border-teal-400 hover:text-teal-400">Appointment Calendar</button>
            <button onclick="switchTab('billing')" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:border-teal-400 hover:text-teal-400">Billing & Payments</button>
        </div>
        <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-900 p-6 reveal" id="showcase-content">
            <!-- Content replaced by JS -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-800 p-4"><p class="text-2xl font-bold text-teal-400">12</p><p class="text-xs text-slate-400">Patients Today</p></div>
                <div class="rounded-xl bg-slate-800 p-4"><p class="text-2xl font-bold text-teal-400">8</p><p class="text-xs text-slate-400">Appointments</p></div>
                <div class="rounded-xl bg-slate-800 p-4"><p class="text-2xl font-bold text-teal-400">3</p><p class="text-xs text-slate-400">In Queue</p></div>
            </div>
            <div class="mt-4 space-y-2">
                <div class="flex items-center justify-between rounded-lg bg-slate-800 px-4 py-2"><span class="text-sm font-medium">Sarah Chen · Cardiology</span><span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400">Completed</span></div>
                <div class="flex items-center justify-between rounded-lg bg-slate-800 px-4 py-2"><span class="text-sm font-medium">Rahim Uddin · General</span><span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-400">In Consultation</span></div>
                <div class="flex items-center justify-between rounded-lg bg-slate-800 px-4 py-2"><span class="text-sm font-medium">Nasrin Akter · Neurology</span><span class="rounded-full bg-navy-500/20 px-2 py-0.5 text-xs text-navy-300">Checked In</span></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== BENEFITS ===== -->
<section id="benefits" class="py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Why hospitals choose MediCore</h2>
            <p class="mt-3 text-lg text-slate-600">Practical benefits that reduce administrative overhead and improve patient care.</p>
        </div>
        <div class="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($benefits as $b): ?>
            <div class="rounded-2xl bg-gradient-to-br from-teal-50 to-white border border-teal-100 p-6 reveal">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-teal-500/10 text-teal-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></span>
                <h3 class="mt-3 text-base font-semibold text-navy-950"><?= e($b['title']) ?></h3>
                <p class="mt-1.5 text-sm text-slate-600"><?= e($b['desc']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section id="how-it-works" class="bg-slate-50 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Get started in 4 steps</h2>
        </div>
        <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-4">
            <?php foreach ([['1','Set up your hospital','Configure hospital name, currency, and basic settings.'],['2','Add departments & staff','Create departments, assign doctors with schedules, and register staff profiles.'],['3','Manage daily operations','Register patients, book appointments, record consultations, and dispense medicines.'],['4','Monitor & report','Track revenue, bed occupancy, lab results, and generate cross-module reports.']] as $step): ?>
            <div class="relative rounded-2xl border border-slate-200 bg-white p-6 text-center reveal">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-teal-600 text-lg font-bold text-white"><?= e($step[0]) ?></span>
                <h3 class="mt-4 text-base font-semibold text-navy-950"><?= e($step[1]) ?></h3>
                <p class="mt-1.5 text-sm text-slate-600"><?= e($step[2]) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== SOLUTIONS BY DEPARTMENT ===== -->
<section id="solutions" class="py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Built for every department</h2>
            <p class="mt-3 text-lg text-slate-600">Role-based access ensures each team sees what they need.</p>
        </div>
        <div class="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($solutions as $s): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:shadow-lg reveal">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-navy-500/10 text-navy-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg></span>
                <h3 class="mt-3 text-sm font-semibold text-navy-950"><?= e($s['title']) ?></h3>
                <p class="mt-1.5 text-xs leading-relaxed text-slate-600"><?= e($s['desc']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== SECURITY ===== -->
<section id="security" class="bg-navy-950 py-20 text-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div class="reveal">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Security by design</h2>
                <p class="mt-4 text-lg text-slate-400">MediCore enforces defense-in-depth security at every layer — from database queries to session cookies.</p>
                <div class="mt-8 space-y-4">
                    <?php foreach ([
                        ['shield-check','Role-Based Access Control','Granular per-module, per-action permissions with 7 roles and per-user overrides.'],
                        ['lock','Secure Authentication','Bcrypt password hashing, login rate limiting, forced password rotation, and session fingerprinting.'],
                        ['database','Protected Database Access','PDO prepared statements everywhere — zero SQL injection vectors. MIME-sniffed file uploads stored outside the docroot.'],
                        ['file-text','Audit-Friendly Activity Records','Every state-changing action across all 12 modules is logged to the audit trail with user, IP, and timestamp.'],
                    ] as $sec): ?>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-500/10 text-teal-400"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/></svg></span>
                        <div><p class="text-sm font-semibold text-white"><?= e($sec[1]) ?></p><p class="mt-0.5 text-sm text-slate-400"><?= e($sec[2]) ?></p></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="reveal">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                    <div class="rounded-lg bg-slate-800 p-4 font-mono text-xs">
                        <p class="text-emerald-400">// Security headers</p>
                        <p class="text-slate-400">X-Content-Type-Options: nosniff</p>
                        <p class="text-slate-400">X-Frame-Options: DENY</p>
                        <p class="text-slate-400">Referrer-Policy: strict-origin</p>
                        <p class="text-slate-400">Content-Security-Policy: ...</p>
                        <p class="mt-2 text-emerald-400">// Session</p>
                        <p class="text-slate-400">httponly = true</p>
                        <p class="text-slate-400">samesite = lax</p>
                        <p class="text-slate-400">secure = auto (HTTPS)</p>
                        <p class="mt-2 text-emerald-400">// Passwords</p>
                        <p class="text-slate-400">algorithm = bcrypt</p>
                        <p class="text-slate-400">cost = 12</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== TESTIMONIALS ===== -->
<section class="py-20 bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">What healthcare teams say</h2>
            <p class="mt-1 text-sm text-slate-400 italic">Sample testimonials — replace with real feedback after deployment.</p>
        </div>
        <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
            <?php foreach ([
                ['"The appointment queue dashboard alone saved our front desk 2 hours a day. Patients see their token number and wait calmly."','Front Desk Supervisor','Sample Hospital'],
                ['"Having consultations, prescriptions, and lab results in one patient record means I never miss an allergy or a critical result."','Cardiologist','Sample Medical Center'],
                ['"The billing module with automatic invoice generation for pharmacy and lab orders eliminated our manual invoice gap entirely."','Hospital Administrator','Sample General Hospital'],
            ] as $t): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 reveal">
                <svg class="h-8 w-8 text-teal-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 4c2-1 4-1 6 0v6c-2 1-4 1-6 0V4zm10 0c2-1 4-1 6 0v6c-2 1-4 1-6 0V4z"/></svg>
                <p class="mt-4 text-sm leading-relaxed text-slate-700"><?= e($t[0]) ?></p>
                <div class="mt-4 flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-navy-100 text-sm font-semibold text-navy-600"><?= e(strtoupper(substr($t[1], 0, 1))) ?></span>
                    <div><p class="text-sm font-semibold text-navy-950"><?= e($t[1]) ?></p><p class="text-xs text-slate-400"><?= e($t[2]) ?></p></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== PRICING ===== -->
<section id="pricing" class="py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Simple, transparent pricing</h2>
            <p class="mt-3 text-lg text-slate-600">Choose the plan that fits your hospital. <span class="text-xs text-slate-400">Example pricing — contact us for a custom quote.</span></p>
        </div>
        <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
            <?php foreach ([
                ['Starter', '৳5,000/mo', ['Up to 50 staff users','Patient & appointment management','Basic billing & invoicing','Pharmacy essentials','Email support'], false],
                ['Professional', '৳15,000/mo', ['Up to 200 staff users','All 12 modules','Lab result verification workflow','Bed management & admissions','Priority support'], true],
                ['Enterprise', 'Custom', ['Unlimited users','All Professional features','Custom module development','On-premise deployment','Dedicated account manager','24/7 support'], false],
            ] as $plan): ?>
            <div class="rounded-2xl border <?= $plan[3] ? 'border-teal-400 ring-2 ring-teal-400/20' : 'border-slate-200' ?> bg-white p-6 reveal <?= $plan[3] ? 'lg:-translate-y-4' : '' ?>">
                <?php if ($plan[3]): ?><span class="float-right rounded-full bg-teal-500 px-2 py-0.5 text-[10px] font-bold text-white">POPULAR</span><?php endif; ?>
                <h3 class="text-lg font-bold text-navy-950"><?= e($plan[0]) ?></h3>
                <p class="mt-2 text-3xl font-extrabold text-navy-950"><?= e($plan[1]) ?></p>
                <ul class="mt-4 space-y-2">
                    <?php foreach ($plan[2] as $feat): ?><li class="flex items-center gap-2 text-sm text-slate-600"><svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg><?= e($feat) ?></li><?php endforeach; ?>
                </ul>
                <a href="<?= url('/contact') ?>" class="mt-6 block rounded-xl <?= $plan[3] ? 'bg-teal-600 text-white' : 'border border-slate-300 text-slate-700' ?> px-4 py-2.5 text-center text-sm font-semibold transition-colors <?= $plan[3] ? 'hover:bg-teal-700' : 'hover:border-teal-400 hover:text-teal-600' ?>">Request a Quote</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== FAQ ===== -->
<section id="faq" class="bg-slate-50 py-20">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="text-center reveal">
            <h2 class="text-3xl font-bold tracking-tight text-navy-950 sm:text-4xl">Frequently asked questions</h2>
        </div>
        <div class="mt-10 space-y-3" id="faq-list">
            <?php foreach ($faqs as $i => $faq): ?>
            <div class="rounded-xl border border-slate-200 bg-white overflow-hidden reveal">
                <button onclick="toggleFAQ(<?= $i ?>)" class="flex w-full items-center justify-between px-5 py-4 text-left text-sm font-semibold text-navy-950">
                    <?= e($faq['q']) ?>
                    <svg class="h-5 w-5 text-slate-400 transition-transform faq-icon-<?= $i ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="faq-answer-<?= $i ?> max-h-0 overflow-hidden transition-all duration-300">
                    <p class="px-5 pb-4 text-sm leading-relaxed text-slate-600"><?= e($faq['a']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== FINAL CTA ===== -->
<section class="relative overflow-hidden py-20">
    <div class="absolute inset-0 -z-10 bg-gradient-to-br from-navy-950 via-navy-900 to-teal-950"></div>
    <div class="absolute inset-0 -z-10 opacity-20" style="background-image:radial-gradient(circle at 20% 50%, rgba(45,212,191,0.3) 0, transparent 50%), radial-gradient(circle at 80% 30%, rgba(13,148,136,0.2) 0, transparent 50%)"></div>
    <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8 reveal">
        <h2 class="text-3xl font-bold tracking-tight text-white sm:text-5xl">Ready to modernize your hospital?</h2>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-300">Join the hospitals that have streamlined their operations with MediCore. Request a personalized demo today.</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="<?= url('/contact') ?>" class="rounded-xl bg-teal-500 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-teal-500/30 transition-all hover:bg-teal-400">Request a Demo</a>
            <a href="<?= url('/login') ?>" class="rounded-xl border border-slate-600 px-6 py-3 text-base font-semibold text-white transition-all hover:border-teal-400 hover:text-teal-300">Login to Dashboard</a>
        </div>
    </div>
</section>

<?php $this->end(); ?>
