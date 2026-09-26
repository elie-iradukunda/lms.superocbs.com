<?php

declare(strict_types=1);

/**
 * Core smoke test: the database builds, people can sign in, permissions come
 * from the database, and a record can be created, read, updated and removed
 * through the schema-driven model.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_smoke');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Smoke tests');

    // ------------------------------------------------------------- accounts
    $users = new Models\UserRepository();
    $admin = $users->findActiveByEmail('admin@itec.rw');
    $test->assert($admin !== null, 'the seeded admin exists');
    $test->assert($admin !== null && password_verify('password', $admin['password_hash']), 'the seeded admin password verifies');

    $accounts = demo_accounts();
    $test->same(7, count($accounts), 'all seven seeded roles have an active login');
    $test->same('aline@itec.rw', $accounts['logistics_manager']['email'] ?? '', 'the login list reads its email from the database');

    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    // ---------------------------------------------------------- permissions
    $test->assert(role_can('dashboard'), 'super admin may open the dashboard');
    $test->assert(role_can('users', 'delete'), 'super admin may delete users');
    $test->assert(role_can('invoices', 'approve'), 'super admin may approve invoices');

    test_sign_in($accounts['driver'], 'driver');
    $test->assert(role_can('trips'), 'a driver may open trips');
    $test->assert(!role_can('users'), 'a driver may not open users');
    $test->assert(!role_can('trips', 'delete'), 'a driver may not delete a trip');

    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    // ------------------------------------------------------------ schema ---
    foreach (array_keys(Models\Schema::all()) as $moduleKey) {
        $module = Models\Schema::get($moduleKey);
        $test->assert(isset($module['table'], $module['code'], $module['fields'], $module['list']), "{$moduleKey} has a complete definition");

        // Every field named in a section must exist, or the form silently drops it.
        foreach ($module['sections'] as $section) {
            foreach ($section['fields'] as $fieldName) {
                $test->assert(isset($module['fields'][$fieldName]), "{$moduleKey} section field {$fieldName} is defined");
            }
        }

        // Every field must belong to a section, or it can never be filled in.
        $inSections = array_merge(...array_column($module['sections'], 'fields'));
        foreach (array_keys($module['fields']) as $fieldName) {
            $test->assert(in_array($fieldName, $inSections, true), "{$moduleKey} field {$fieldName} appears on the form");
        }

        $listing = Models\LogisticsData::listing($moduleKey, [], current_context());
        $test->assert(isset($listing['rows'], $listing['total'], $listing['pages']), "{$moduleKey} listing returns a page");
    }

    // ------------------------------------------------------------ validation
    $errors = Models\LogisticsData::validate('vehicles', []);
    $test->assert($errors !== [], 'a blank vehicle is rejected');

    $errors = Models\LogisticsData::validate('vehicles', ['plate_number' => 'RZZ 999Z', 'vehicle_type' => 'Flying saucer', 'status' => 'available']);
    $test->assert($errors !== [], 'a vehicle type outside the allowed list is rejected');

    $errors = Models\LogisticsData::validate('vehicles', ['plate_number' => 'RAC 482D', 'vehicle_type' => 'Pickup', 'status' => 'available']);
    $test->assert($errors !== [], 'a duplicate plate number is rejected');

    // -------------------------------------------------------------- writing
    $id = Models\LogisticsData::save('vehicles', null, [
        'plate_number' => 'RZZ 999Z',
        'vehicle_type' => 'Pickup',
        'make' => 'Toyota',
        'model' => 'Hilux',
        'status' => 'available',
        'mileage' => '1200',
        'capacity_kg' => '1100',
        'next_service_date' => '2026-12-31',
    ]);
    $test->assert($id > 0, 'creating a vehicle returns its primary key');

    $row = $pdo->query("SELECT * FROM vehicles WHERE plate_number = 'RZZ 999Z'")->fetch();
    $test->assert($row !== false, 'the new vehicle is in the database');
    $test->same(1100.0, (float) $row['capacity_kg'], 'the capacity was stored');

    Models\LogisticsData::save('vehicles', $id, [
        'plate_number' => 'RZZ 999Z',
        'vehicle_type' => 'Pickup',
        'status' => 'maintenance',
        'mileage' => '1500',
    ]);
    $test->same('maintenance', (string) $pdo->query("SELECT status FROM vehicles WHERE id = {$id}")->fetchColumn(), 'the update was saved');

    // Records are addressed by id, so two records may share a name safely.
    $found = Models\LogisticsData::find('vehicles', $id);
    $test->same('RZZ 999Z', $found['plate_number'] ?? '', 'a record is found by its primary key');

    // ---------------------------------------------------- auto references ---
    $tripId = Models\LogisticsData::save('trips', null, [
        'reference_code' => '',
        'pickup_location' => 'Kigali',
        'destination' => 'Huye',
        'status' => 'requested',
    ]);
    $generated = (string) $pdo->query("SELECT reference_code FROM trips WHERE id = {$tripId}")->fetchColumn();
    $test->assert(str_starts_with($generated, 'TRP-' . date('Y') . '-'), 'a blank trip reference is generated automatically');

    // ------------------------------------------------ new account creation --
    // `users.password_hash` is NOT NULL with no default, so a new account has to
    // be given a password by the system rather than left for someone to choose.
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE role_key = 'logistics_manager'")->fetchColumn();
    $newUserId = Models\LogisticsData::save('users', null, [
        'full_name' => 'Test Dispatcher',
        'email' => 'dispatcher@itec.rw',
        'role_id' => (string) $roleId,
        'department' => 'Operations',
        'status' => 'active',
        'prvg' => '2',
    ]);
    $oneTime = Models\LogisticsData::takeOneTimePassword();
    $newUser = $pdo->query("SELECT password_hash, must_change_password FROM users WHERE id = {$newUserId}")->fetch();

    $test->assert($oneTime !== null && strlen($oneTime) >= 12, 'a new account is given a one-time password');
    $test->assert(password_verify((string) $oneTime, (string) $newUser['password_hash']), 'the one-time password signs that account in');
    $test->same(1, (int) $newUser['must_change_password'], 'a new account must change its password at first sign-in');
    $test->same(null, Models\LogisticsData::takeOneTimePassword(), 'the one-time password is only readable once');
    $test->assert(
        Models\LogisticsData::validate('users', ['full_name' => 'Someone Else', 'email' => 'dispatcher@itec.rw', 'role_id' => (string) $roleId, 'status' => 'active']) !== [],
        'a duplicate email is rejected'
    );

    // ------------------------------------------------------- soft deletion --
    Models\LogisticsData::remove('vehicles', $id, 'Created by the smoke test.');
    $test->same(null, Models\LogisticsData::find('vehicles', $id), 'a removed vehicle disappears from the lists');
    $test->same(1, (int) $pdo->query("SELECT COUNT(*) FROM vehicles WHERE id = {$id} AND deleted_at IS NOT NULL")->fetchColumn(), 'a removed vehicle is kept with a deleted_at stamp');

    // ------------------------------------------------------------- reports --
    foreach (Models\ReportData::keys() as $reportKey) {
        $report = Models\ReportData::run($reportKey, '2026-01-01', '2026-12-31');
        $test->assert(isset($report['columns'], $report['rows']), "report {$reportKey} runs");
        $test->assert($report['columns'] !== [], "report {$reportKey} declares columns");
    }

    // --------------------------------------------------------------- audit --
    Models\AuditLog::record('tests.smoke', 'tests', 'smoke');
    $test->same(1, (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action_name = 'tests.smoke'")->fetchColumn(), 'audit entries are written');
    $test->assert(Models\LogisticsData::auditLog(5) !== [], 'the audit strip reads recent entries');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
