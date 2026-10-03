<?php

declare(strict_types=1);

/**
 * MediCore test suite — dependency-free assertion runner.
 *
 *   php console test        (or)      php tests/run.php
 *
 * Covers: helpers/escaping, Env parsing, Validator rules, Router matching,
 * View rendering, DB connectivity, migrations, RBAC resolution, CSRF,
 * settings service and the dashboard service contract.
 */

require dirname(__DIR__) . '/bootstrap/app.php';

// Sessions must start before any test output ("headers already sent").
App\Core\Session::start();

use App\Core\Auth;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\PasswordResetService;
use App\Services\PatientService;
use App\Services\SearchService;
use App\Services\SettingService;
use App\Services\UserService;

// ---------------------------------------------------------------------------
// Tiny runner
// ---------------------------------------------------------------------------
$passes = 0;
$failures = [];

function test(string $name, callable $case): void
{
    global $passes, $failures;
    try {
        $case();
        $passes++;
        echo "  ✓ {$name}" . PHP_EOL;
    } catch (Throwable $e) {
        $failures[] = ['name' => $name, 'error' => $e->getMessage()];
        echo "  ✗ {$name}" . PHP_EOL . '      ' . $e->getMessage() . PHP_EOL;
    }
}

function expect(mixed $actual, mixed $expected, string $message = ''): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '') .
            'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function expectTrue(bool $condition, string $message = 'Condition was false'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

echo PHP_EOL . 'MediCore test suite' . PHP_EOL . str_repeat('─', 50) . PHP_EOL;

// ---------------------------------------------------------------------------
// Helpers & escaping
// ---------------------------------------------------------------------------
test('e() escapes HTML specials', static function () {
    expect(e('<script>alert("x")</script>'), '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;');
});

test('e() handles null gracefully', static function () {
    expect(e(null), '');
});

test('initials() builds two-letter monograms', static function () {
    expect(initials('Sarah Chen'), 'SC');
    expect(initials('plato'), 'P');
});

test('time_ago() formats relative times', static function () {
    expect(time_ago(date('Y-m-d H:i:s', time() - 30)), 'just now');
    expect(time_ago(date('Y-m-d H:i:s', time() - 120)), '2 min ago');
});

test('str_limit() truncates long strings', static function () {
    expectTrue(mb_strlen(str_limit(str_repeat('a', 200), 50)) <= 50);
});

// ---------------------------------------------------------------------------
// Env
// ---------------------------------------------------------------------------
test('Env::get() reads loaded .env values', static function () {
    expectTrue(App\Core\Env::get('APP_NAME') !== '', 'APP_NAME missing');
    expect(App\Core\Env::get('MISSING_KEY_XYZ', 'fallback'), 'fallback');
});

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
test('config() resolves dot notation with defaults', static function () {
    expectTrue(is_string(config('app.name')));
    expect(config('app.nonexistent.key', 'def'), 'def');
});

// ---------------------------------------------------------------------------
// Validator
// ---------------------------------------------------------------------------
test('Validator: required + email rules', static function () {
    $v = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
    expectTrue($v->fails());
    expectTrue(isset($v->firstErrors()['email']));

    $v = Validator::make(['email' => 'nurse@medicore.test'], ['email' => 'required|email']);
    expectTrue($v->passes());
});

test('Validator: min/max/confirmed', static function () {
    $v = Validator::make(
        ['password' => 'short', 'password_confirmation' => 'short'],
        ['password' => 'required|min:8|confirmed']
    );
    expectTrue($v->fails());

    $v = Validator::make(
        ['password' => 'longenough1', 'password_confirmation' => 'longenough1'],
        ['password' => 'required|min:8|confirmed']
    );
    expectTrue($v->passes());
    expect($v->validated()['password'], 'longenough1');
});

test('Validator: unique against users table', static function () {
    $v = Validator::make(['email' => 'admin@medicore.test'], ['email' => 'unique:users,email']);
    expectTrue($v->fails(), 'seeded admin email should collide');

    $v = Validator::make(['email' => 'nobody@nowhere.test'], ['email' => 'unique:users,email']);
    expectTrue($v->passes());
});

test('Validator: nullable skips empty optional fields', static function () {
    $v = Validator::make(['phone' => ''], ['phone' => 'nullable|max:30']);
    expectTrue($v->passes());
    expect($v->validated()['phone'], null);
});

// ---------------------------------------------------------------------------
// Router matching
// ---------------------------------------------------------------------------
test('Router: static + parameterized routes resolve', static function () {
    $router = new Router(new Request());
    $router->get('/admin/users', fn () => 'users-page');
    $router->get('/admin/users/{id}', fn (Request $r, $id) => "user-{$id}");

    $route = null;
    foreach ($router->routes() as $r) {
        $m = $r->match('GET', '/admin/users');
        if (is_array($m)) { $route = $r; break; }
    }
    expectTrue($route !== null, 'static route not matched');

    $params = null;
    foreach ($router->routes() as $r) {
        $m = $r->match('GET', '/admin/users/42');
        if (is_array($m)) { $params = $m; break; }
    }
    expect($params['id'] ?? null, '42');

    // Method mismatch flagged
    $r = $router->routes()[0];
    expect($r->match('POST', '/admin/users'), false);
    expect($r->match('GET', '/other/path'), null);
});

// ---------------------------------------------------------------------------
// View engine
// ---------------------------------------------------------------------------
test('View renders templates with extracted data', static function () {
    $out = View::make('errors/404', ['message' => 'custom missing thing'])->render();
    expectTrue(str_contains($out, 'custom missing thing'), '404 view should render message');
});

// ---------------------------------------------------------------------------
// Database + schema
// ---------------------------------------------------------------------------
test('Database: connection is live', static function () {
    expect(Database::scalar('SELECT 1'), 1);
});

test('Database: migrations fully applied', static function () {
    $migrator = Migrator::make();
    expect($migrator->pending(), [], 'pending migrations exist');
});

foreach (['roles', 'permissions', 'permission_role', 'users', 'role_user', 'hospital_settings', 'audit_logs'] as $table) {
    test("Database: table `{$table}` exists", static function () use ($table) {
        expectTrue(Database::tableExists($table), "table {$table} missing");
    });
}

test('Database: transactions roll back on failure', static function () {
    $before = (int) Database::scalar('SELECT COUNT(*) FROM roles');
    try {
        Database::transaction(static function (PDO $pdo): void {
            $pdo->exec("INSERT INTO roles (name, slug) VALUES ('Tmp Role', 'tmp-role-" . uniqid() . "')");
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        // expected
    }
    $after = (int) Database::scalar('SELECT COUNT(*) FROM roles');
    expect($after, $before, 'rollback did not restore row count');
});

// ---------------------------------------------------------------------------
// Seeded data integrity
// ---------------------------------------------------------------------------
test('Seed: demo admin account exists with super-admin role', static function () {
    $admin = User::findByEmail('admin@medicore.test');
    expectTrue($admin !== null, 'admin user missing');
    expectTrue(in_array('super-admin', User::roleSlugs((int) $admin['id']), true));
    expectTrue(password_verify('Admin@12345', (string) $admin['password_hash']), 'admin password hash mismatch');
});

test('Seed: roles and permission matrix populated', static function () {
    expect((int) Database::scalar('SELECT COUNT(*) FROM roles') >= 8, true);
    expect((int) Database::scalar('SELECT COUNT(*) FROM permissions') >= 30, true);
    expect((int) Database::scalar('SELECT COUNT(*) FROM permission_role') > 0, true);

    // Doctor role should be able to view patients but not manage users.
    $doctorPerms = Database::query(
        "SELECT p.name FROM permissions p
         INNER JOIN permission_role pr ON pr.permission_id = p.id
         INNER JOIN roles r ON r.id = pr.role_id WHERE r.slug = 'doctor'"
    );
    $names = array_map(static fn ($r) => $r['name'], $doctorPerms);
    expectTrue(in_array('patients.view', $names, true), 'doctor missing patients.view');
    expectTrue(!in_array('users.delete', $names, true), 'doctor unexpectedly holds users.delete');
});

test('Seed: hospital settings catalogue seeded', static function () {
    expect(SettingService::get('hospital_name'), 'MediCore General Hospital');
    expectTrue(is_string(SettingService::get('currency')));
});

// ---------------------------------------------------------------------------
// Session / CSRF
// ---------------------------------------------------------------------------
test('Session: CSRF token stable + verification timing-safe', static function () {
    Session::start();
    $a = Session::csrfToken();
    $b = Session::csrfToken();
    expect($a, $b, 'token should be stable within a session');
    expectTrue(Session::verifyCsrf($a));
    expectTrue(!Session::verifyCsrf('forged-token'));
    expectTrue(!Session::verifyCsrf(null));
});

// ---------------------------------------------------------------------------
// RBAC (Auth::can against a real seeded user)
// ---------------------------------------------------------------------------
test('RBAC: admin session bypasses permission checks', static function () {
    Session::start();
    $admin = User::findByEmail('admin@medicore.test');
    Auth::login($admin);
    expectTrue(Auth::check());
    expectTrue(Auth::can('users.delete'));
    expectTrue(Auth::hasRole('super-admin'));
    Auth::logout();
});

test('RBAC: nurse session is limited to nursing abilities', static function () {
    Session::start();
    $nurse = User::findByEmail('farhana@medicore.test');
    Auth::login($nurse);
    expectTrue(Auth::can('patients.view'));
    expectTrue(!Auth::can('users.delete'));
    expectTrue(!Auth::can('settings.update'));
    Auth::logout();
});

// ---------------------------------------------------------------------------
// Services
// ---------------------------------------------------------------------------
test('DashboardService: returns contract + real staff stat', static function () {
    $data = DashboardService::build();
    foreach (['stats', 'charts', 'recentActivity', 'modules', 'system'] as $key) {
        expectTrue(array_key_exists($key, $data), "missing key {$key}");
    }
    expectTrue($data['stats']['staff']['available'], 'staff stat should be available in phase 1');
    expectTrue($data['stats']['staff']['value'] >= 1, 'staff count should be >= 1');
    // Patients module activates from phase 3 onward (dedicated test below).
    expectTrue(!$data['charts']['appointments']['available'], 'appointments chart should be pending');
    expectTrue($data['system']['db_ok']);
});

test('SearchService: finds seeded staff, flags future modules', static function () {
    $result = SearchService::search('sarah');
    expectTrue($result['total'] >= 1, 'should find Dr. Sarah Chen');
    expectTrue($result['modules'] !== [], 'future modules should be listed');
});

test('SettingService: set + get round-trip', static function () {
    Session::start();
    SettingService::set('hospital_tagline', 'Test tagline value', null);
    expect(SettingService::get('hospital_tagline'), 'Test tagline value');
    SettingService::set('hospital_tagline', 'Care that never sleeps', null);
    expect(SettingService::get('hospital_tagline'), 'Care that never sleeps');
});

// ---------------------------------------------------------------------------
// Phase 2 — access control
// ---------------------------------------------------------------------------
Database::execute('DELETE FROM login_attempts'); // deterministic rate-limit state

test('Validator: strong password policy', static function () {
    $weak = Validator::make(['password' => 'abc'], ['password' => 'strong']);
    expectTrue($weak->fails(), 'weak password should fail');

    foreach (['Abcdefg1', 'Passw0rdX', 'Zx9aaaaa'] as $good) {
        $v = Validator::make(['password' => $good], ['password' => 'strong']);
        expectTrue($v->passes(), "{$good} should pass");
    }
    foreach (['password1', 'PASSWORD1', 'Password', 'P4ss'] as $bad) {
        $v = Validator::make(['password' => $bad], ['password' => 'strong']);
        expectTrue($v->fails(), "{$bad} should fail");
    }
});

test('AuthService: records attempts + locks out after threshold', static function () {
    Session::start();
    $email = 'locktest_' . uniqid() . '@medicore.test';
    $request = new Request();

    // Simulate a seeded account to lock (use the demo admin's real password hash target).
    $maxAttempts = (int) config('auth.throttle.max_attempts', 5);
    for ($i = 0; $i < $maxAttempts; $i++) {
        $r = AuthService::attempt($email, 'WrongPass1', $request);
        expectTrue(!$r['ok']);
        expect($r['status'], 'invalid_password');
    }
    // Next attempt for the SAME identifier is locked — even with correct creds.
    $locked = AuthService::attempt($email, 'Admin@12345', $request);
    expectTrue(!$locked['ok']);
    expect($locked['status'], 'locked');
    expectTrue($locked['retrySeconds'] > 0);
});

test('AuthService: valid credentials sign in and record success', static function () {
    Session::start();
    $request = new Request();
    $r = AuthService::attempt('admin@medicore.test', 'Admin@12345', $request);
    expectTrue($r['ok'], 'login failed: ' . json_encode($r));
    expectTrue(Auth::check());
    Auth::logout();

    $bad = AuthService::attempt('admin@medicore.test', 'WrongPass1', $request);
    expectTrue(!$bad['ok']);
    expect($bad['status'], 'invalid_password');
});

test('AuthService: archived and deactivated accounts are blocked', static function () {
    Session::start();
    $request = new Request();

    // Omar Farooq is seeded deactivated.
    $r = AuthService::attempt('omar@medicore.test', 'Staff@12345', $request);
    expectTrue(!$r['ok']);
    expect($r['status'], 'inactive');

    // Archive Dr. Sarah temporarily, then restore.
    $sarah = User::findByEmail('sarah.chen@medicore.test');
    Database::execute('UPDATE users SET archived_at = NOW() WHERE id = ?', [(int) $sarah['id']]);
    $r = AuthService::attempt('sarah.chen@medicore.test', 'Staff@12345', $request);
    expect($r['status'], 'archived');
    Database::execute('UPDATE users SET archived_at = NULL WHERE id = ?', [(int) $sarah['id']]);
});

test('Password reset: issue → validate → consume → reuse rejected', static function () {
    $token = \App\Models\PasswordReset::issue('rahim@medicore.test', '127.0.0.1');

    // Token must be hashed at rest.
    $stored = (int) Database::scalar('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]);
    expect($stored, 1);
    $plaintextStored = (int) Database::scalar('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$token]);
    expect($plaintextStored, 0, 'plaintext token must never be stored');

    $request = new Request();
    $r = PasswordResetService::reset($token, 'NewPass123', $request);
    expectTrue($r['ok'], $r['message']);

    // Password actually changed.
    $user = User::findByEmail('rahim@medicore.test');
    expectTrue(password_verify('NewPass123', (string) $user['password_hash']));

    // Single-use: second attempt fails.
    $again = PasswordResetService::reset($token, 'Other1234', $request);
    expectTrue(!$again['ok']);

    // Restore demo password.
    User::updatePassword((int) $user['id'], password_hash('Staff@12345', PASSWORD_BCRYPT, ['cost' => 12]));
});

test('Password reset: unknown email answers generically (no enumeration)', static function () {
    $request = new Request();
    $r = PasswordResetService::request('nonexistent@medicore.test', $request);
    expectTrue($r['ok']);
    expect($r['message'], 'If that email exists, a reset link has been sent.');
    expectTrue(!isset($r['dev_link']), 'unknown email must not leak a link');
});

test('User-specific overrides: deny beats role grant, allow grants without role', static function () {
    Session::start();
    $nurse = User::findByEmail('farhana@medicore.test');
    $nurseId = (int) $nurse['id'];
    $permId = (int) Database::scalar("SELECT id FROM permissions WHERE name = 'patients.view'");

    try {
        Database::execute('DELETE FROM permission_user WHERE user_id = ?', [$nurseId]);

        // 1) Deny override beats the role grant.
        Database::execute('INSERT INTO permission_user (user_id, permission_id, type) VALUES (?, ?, ?)', [$nurseId, $permId, 'deny']);
        Auth::login(User::findByEmail('farhana@medicore.test'));
        expectTrue(!Auth::can('patients.view'), 'deny override should beat the role grant');
        Auth::logout();

        // 2) Allow override grants even without the role.
        Database::execute('DELETE FROM permission_user WHERE user_id = ?', [$nurseId]);
        Database::execute('DELETE ru FROM role_user ru INNER JOIN roles r ON r.id = ru.role_id WHERE ru.user_id = ? AND r.slug = "nurse"', [$nurseId]);
        Database::execute('INSERT INTO permission_user (user_id, permission_id, type) VALUES (?, ?, ?)', [$nurseId, $permId, 'allow']);
        Auth::login(User::findByEmail('farhana@medicore.test'));
        expectTrue(Auth::can('patients.view'), 'allow override should grant without role');
        Auth::logout();
    } finally {
        // Restore: nurse role back, overrides gone.
        Database::execute(
            'INSERT IGNORE INTO role_user (user_id, role_id) SELECT ?, id FROM roles WHERE slug = "nurse"',
            [$nurseId]
        );
        Database::execute('DELETE FROM permission_user WHERE user_id = ?', [$nurseId]);
    }
});

test('must_change_password: flag forces change and clears after rotation', static function () {
    Session::start();
    Database::execute('UPDATE users SET must_change_password = 1 WHERE email = ?', ['nusrat@medicore.test']);
    $pharmacist = User::findByEmail('nusrat@medicore.test');
    Auth::login($pharmacist);
    expectTrue(Auth::mustChangePassword());

    Auth::clearMustChangePassword();
    expectTrue(!Auth::mustChangePassword());
    $fresh = User::findByEmail('nusrat@medicore.test');
    expect((int) $fresh['must_change_password'], 0);
    Auth::logout();
});

test('Role model: create, set permissions, unique slug, safe delete', static function () {
    $roleId = Role::create('QA Tester Role', 'Temporary test role');
    $role = Role::find($roleId);
    expect($role['slug'], 'qa-tester-role');
    expect((int) $role['is_system'], 0);

    $permIds = array_map(
        static fn (array $r): int => (int) $r['id'],
        Database::query("SELECT id FROM permissions WHERE module IN ('dashboard','reports')")
    );
    Role::setPermissions($roleId, $permIds);
    expect(count(Role::permissionIds($roleId)), count($permIds));

    // Non-system role still assigned -> cannot delete.
    Database::execute('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)', ['Tmp', 'tmp-role-test@x.test', 'x']);
    $tmpUserId = Database::lastInsertId();
    Database::execute('INSERT INTO role_user (user_id, role_id) VALUES (?, ?)', [$tmpUserId, $roleId]);
    expectTrue(!Role::safeDelete($roleId), 'assigned role must not delete');
    Database::execute('DELETE FROM users WHERE id = ?', [$tmpUserId]);

    expectTrue(Role::safeDelete($roleId));
    expectTrue(Role::find($roleId) === null);

    // System role can never be deleted.
    $super = Role::findBySlug('super-admin');
    expectTrue(!Role::safeDelete((int) $super['id']));
});

test('UserService: self-protection blocks self-deactivate/self-archive', static function () {
    Session::start();
    $request = new Request();
    $admin = User::findByEmail('admin@medicore.test');
    Auth::login($admin);

    $r = UserService::setActive((int) $admin['id'], false, $request);
    expectTrue(!$r['ok']);
    $r = UserService::archive((int) $admin['id'], $request);
    expectTrue(!$r['ok']);
    $r = UserService::delete((int) $admin['id'], $request);
    expectTrue(!$r['ok']);
    Auth::logout();
});

test('Authorization: nurse cannot act on admin routes (CanMiddleware logic)', static function () {
    Session::start();
    $nurse = User::findByEmail('farhana@medicore.test');
    Auth::login($nurse);
    expectTrue(!Auth::can('users.delete'));
    expectTrue(!Auth::can('roles.create'));
    expectTrue(!Auth::can('settings.update'));
    expectTrue(Auth::can('patients.view'), 'nurse keeps her own abilities');
    Auth::logout();

    $admin = User::findByEmail('admin@medicore.test');
    Auth::login($admin);
    expectTrue(Auth::can('users.archive'));
    expectTrue(Auth::can('appointments.approve'), 'new approve permission live');
    Auth::logout();
});

test('User listing: filters by role and archived status', static function () {
    Database::execute('UPDATE users SET archived_at = NOW() WHERE email = ?', ['omar@medicore.test']);
    $archived = User::paginateWithRoles('', '', 'archived', 1, 50);
    expect((int) $archived['total'], 1);
    $default = User::paginateWithRoles('', '', 'not_archived', 1, 50);
    expectTrue((int) $default['total'] === 8, 'archived user must vanish from default list');

    $nurses = User::paginateWithRoles('', 'nurse', 'all', 1, 50);
    expectTrue((int) $nurses['total'] >= 1);
    Database::execute('UPDATE users SET archived_at = NULL WHERE email = ?', ['omar@medicore.test']);
});

// ---------------------------------------------------------------------------
// Phase 3 — patient management
// ---------------------------------------------------------------------------
// Restore the seeded demo patients at the end of the patient block.
require_once BASE_PATH . '/database/seeders/Seeder.php';
require_once BASE_PATH . '/database/seeders/PatientSeeder.php';

// Deterministic slate: wipe and reseed the patient pool.
Database::execute('DELETE FROM patient_documents');
Database::execute('DELETE FROM patient_visits');
Database::execute('DELETE FROM patients');
\Seeders\PatientSeeder::run(Database::pdo());

test('Patient: unique sequential codes are generated', static function () {
    $codes = [];
    for ($i = 0; $i < 3; $i++) {
        $codes[] = Patient::nextCode();
        $request = new Request();
        PatientService::create([
            'first_name' => 'Code', 'last_name' => 'Test' . $i, 'gender' => 'male',
            'date_of_birth' => '1990-01-01', 'phone' => '+88017000' . str_pad((string) $i, 4, '0'),
        ], $request);
    }
    expect(count(array_unique($codes)), 3, 'codes must be unique');
    foreach ($codes as $code) {
        expectTrue(preg_match('/^MCP-\d{4}-\d{5}$/', $code) === 1, "bad code format: {$code}");
    }
    Database::execute('DELETE FROM patients WHERE first_name = ?', ['Code']);
});

test('Patient: full CRUD lifecycle with archive preservation', static function () {
    Session::start();
    $admin = User::findByEmail('admin@medicore.test');
    Auth::login($admin);
    $request = new Request();

    $created = PatientService::create([
        'first_name' => 'Test', 'last_name' => 'Lifecycle', 'gender' => 'female',
        'date_of_birth' => '1985-05-05', 'phone' => '+8801999555111',
        'blood_group' => 'O+', 'city' => 'Dhaka', 'allergies' => 'Penicillin',
    ], $request);
    expectTrue($created['ok']);
    $id = (int) $created['id'];

    // Update.
    $r = PatientService::update($id, [
        'first_name' => 'Test', 'last_name' => 'Lifecycle', 'gender' => 'female',
        'date_of_birth' => '1985-05-05', 'phone' => '+8801999555222', 'city' => 'Gazipur',
    ], $request);
    expectTrue($r['ok']);
    $fresh = Patient::find($id);
    expect($fresh['phone'], '+8801999555222');
    expect($fresh['city'], 'Gazipur');

    // Visit recorded.
    $r = PatientService::addVisit($id, [
        'visit_type' => 'outpatient', 'chief_complaint' => 'Cough for 3 days',
        'diagnosis' => 'URTI', 'status' => 'completed',
    ], $request);
    expectTrue($r['ok']);
    expect(count(Patient::visits($id)), 1);

    // Archive preserves the timeline.
    $r = PatientService::archive($id, $request);
    expectTrue($r['ok']);
    $archived = Patient::find($id);
    expectTrue($archived['archived_at'] !== null);
    expect(count(Patient::visits($id)), 1, 'visits must survive archiving');

    // Archived patient hidden from default directory, visible in archived filter.
    expectTrue(Patient::find($id) !== null);
    $default = Patient::directory(['status' => 'not_archived'], 1, 1000);
    $archivedList = Patient::directory(['status' => 'archived'], 1, 1000);
    $inDefault = in_array($id, array_map(static fn ($r) => (int) $r['id'], $default['rows']), true);
    $inArchived = in_array($id, array_map(static fn ($r) => (int) $r['id'], $archivedList['rows']), true);
    expectTrue(!$inDefault && $inArchived);

    // Restore.
    expectTrue(PatientService::restore($id, $request)['ok']);
    expectTrue(Patient::find($id)['archived_at'] === null);
    Auth::logout();
});

test('Patient: duplicate detection by phone, NID and name+dob', static function () {
    // Seeded Kamal Hossain has NID-1987-4410 and phone +880 1712 445510.
    $byNid = Patient::findPossibleDuplicates(['national_id' => 'NID-1987-4410']);
    expectTrue($byNid !== [], 'NID match should be detected');

    $byPhone = Patient::findPossibleDuplicates(['phone' => '+880-1712-445510']);
    expectTrue($byPhone !== [], 'normalized phone match should be detected');

    $byIdentity = Patient::findPossibleDuplicates([
        'first_name' => 'kamal', 'last_name' => 'HOSSAIN', 'date_of_birth' => '1978-03-12',
    ]);
    expectTrue($byIdentity !== [], 'name+dob (case-insensitive) should be detected');

    $none = Patient::findPossibleDuplicates(['first_name' => 'ZZZ', 'last_name' => 'Nope', 'phone' => '+880300000000']);
    expect($none, [], 'non-matching data must return no duplicates');
});

test('Patient: directory filters, sorting and pagination', static function () {
    $females = Patient::directory(['gender' => 'female', 'status' => 'not_archived'], 1, 1000);
    foreach ($females['rows'] as $row) {
        expect($row['gender'], 'female');
    }

    $blood = Patient::directory(['blood_group' => 'O+', 'status' => 'all'], 1, 1000);
    foreach ($blood['rows'] as $row) {
        expect($row['blood_group'], 'O+');
    }

    $search = Patient::directory(['search' => 'MCP-', 'status' => 'all'], 1, 1000);
    expectTrue($search['total'] > 0, 'code search should match');

    $sorted = Patient::directory(['sort' => 'name', 'dir' => 'asc', 'status' => 'all'], 1, 1000);
    $names = array_map(static fn ($r) => $r['last_name'], $sorted['rows']);
    $sortedNames = $names;
    sort($sortedNames);
    expect($names, $sortedNames, 'name sort must be alphabetical');

    $page1 = Patient::directory(['status' => 'all'], 1, 5);
    expect(count($page1['rows']), 5);
    expectTrue($page1['pages'] >= 2, 'pagination must page the full set');
    $page2 = Patient::directory(['status' => 'all'], 2, 5);
    $ids1 = array_map(static fn ($r) => (int) $r['id'], $page1['rows']);
    $ids2 = array_map(static fn ($r) => (int) $r['id'], $page2['rows']);
    expect(array_intersect($ids1, $ids2), [], 'pages must not overlap');
});

test('Patient: CSV export respects filters', static function () {
    $csv = Patient::directoryCsv(['gender' => 'male', 'status' => 'not_archived']);
    $lines = array_filter(explode("\n", trim($csv)));
    expectTrue(str_starts_with($lines[0] ?? '', 'Patient ID'), 'header row present');
    expectTrue(count($lines) >= 2, 'data rows present');
    foreach (array_slice($lines, 1) as $line) {
        if (trim($line) === '') { continue; }
        expectTrue(str_contains($line, ',male,'), 'export must contain only males: ' . $line);
    }
});

test('Patient documents: upload validation (extension + MIME sniffing)', static function () {
    Session::start();
    Auth::login(User::findByEmail('admin@medicore.test'));
    $request = new Request();
    $created = PatientService::create([
        'first_name' => 'Doc', 'last_name' => 'Upload', 'gender' => 'male',
        'date_of_birth' => '2000-01-01', 'phone' => '+8801888777666',
    ], $request);
    $id = (int) $created['id'];

    // A real PNG payload.
    $pngFile = tempnam(sys_get_temp_dir(), 'mc');
    file_put_contents($pngFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $good = PatientService::storeDocument($id, [
        'name' => 'xray.png', 'type' => 'image/png', 'size' => (int) filesize($pngFile),
        'tmp_name' => $pngFile, 'error' => 0,
    ], 'scan', 'Chest X-ray', $request);
    expectTrue($good['ok'], 'valid PNG must be accepted');
    unlink($pngFile);

    // Spoofed: .png extension but text content — must be rejected.
    $spoof = tempnam(sys_get_temp_dir(), 'mc');
    file_put_contents($spoof, '<?php echo "evil"; ?>');
    $bad = PatientService::storeDocument($id, [
        'name' => 'malware.png', 'type' => 'image/png', 'size' => (int) filesize($spoof),
        'tmp_name' => $spoof, 'error' => 0,
    ], 'other', 'Evil', $request);
    expectTrue(!$bad['ok'], 'MIME-mismatched file must be rejected');
    unlink($spoof);

    // Disallowed extension entirely.
    $disallowed = PatientService::storeDocument($id, [
        'name' => 'notes.docx', 'type' => 'application/zip', 'size' => 100, 'tmp_name' => '', 'error' => 0,
    ], 'other', 'Docx', $request);
    expectTrue(!$disallowed['ok'], 'non-whitelisted extension must be rejected');

    // The accepted document downloads through the validated path.
    $docs = Patient::documents($id);
    expect(count($docs), 1);
    $path = PatientService::documentDownloadPath((int) $docs[0]['id']);
    expectTrue(isset($path['file']) && is_file($path['file']), 'stored file must exist in private storage');
    expectTrue(str_starts_with($path['file'], BASE_PATH . DIRECTORY_SEPARATOR . 'storage'), 'file must live outside the docroot');

    // Delete removes DB row + file.
    $r = PatientService::deleteDocument((int) $docs[0]['id'], $request);
    expectTrue($r['ok']);
    expectTrue(!is_file($path['file']), 'file must be removed from disk');
    expect(count(Patient::documents($id)), 0);

    Database::execute('DELETE FROM patients WHERE id = ?', [$id]);
    Auth::logout();
});

test('Patient access control: receptionist can manage, nurse cannot create', static function () {
    Session::start();
    $request = new Request();

    $receptionist = User::findByEmail('rahim@medicore.test');
    Auth::login($receptionist);
    expectTrue(Auth::can('patients.create'));
    expectTrue(Auth::can('patients.update'));
    expectTrue(Auth::can('patients.view'));
    Auth::logout();

    $nurse = User::findByEmail('farhana@medicore.test');
    Auth::login($nurse);
    expectTrue(Auth::can('patients.view'), 'nurse may view');
    expectTrue(Auth::can('patients.update'), 'nurse may update');
    expectTrue(!Auth::can('patients.create'), 'nurse cannot register (not granted)');
    expectTrue(!Auth::can('patients.delete'));
    Auth::logout();

    $accountant = User::findByEmail('mahin@medicore.test');
    Auth::login($accountant);
    expectTrue(!Auth::can('patients.view'), 'accountant has no patient access');
    Auth::logout();
});

test('Dashboard: patients stat is now live', static function () {
    $data = DashboardService::build();
    expectTrue($data['stats']['patients']['available'], 'patients stat should activate with the table present');
    expectTrue($data['stats']['patients']['value'] >= 1);
    $modules = array_column($data['modules'], 'ready', 'name');
    expect($modules['Patients'] ?? null, true, 'module registry should mark Patients installed');
});

test('Environment: restore seeded patients', static function () {
    Database::execute('DELETE FROM patient_visits');
    Database::execute('DELETE FROM patients');
    \Seeders\PatientSeeder::run(Database::pdo());
    expectTrue(\App\Models\Patient::counts()['total'] >= 12);
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo str_repeat('─', 50) . PHP_EOL;
$total = $passes + count($failures);
echo "{$passes}/{$total} passed";

if ($failures !== []) {
    echo ' — FAILED:' . PHP_EOL;
    foreach ($failures as $f) {
        echo '  ✗ ' . $f['name'] . ' → ' . $f['error'] . PHP_EOL;
    }
    exit(1);
}
echo ' ✅' . PHP_EOL . PHP_EOL;
exit(0);
