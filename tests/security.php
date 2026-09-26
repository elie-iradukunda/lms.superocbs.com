<?php

declare(strict_types=1);

/**
 * Security behaviour: CSRF tokens, login lockout, password rules, row-level
 * scoping for drivers, and the allowlists that keep table and column names out
 * of interpolated SQL.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_security');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Security tests');
    $repository = new Models\UserRepository();
    $accounts = $repository->activeLoginAccounts();

    // ------------------------------------------------------------------ CSRF
    $token = Core\Csrf::token();
    $test->assert(strlen($token) === 64, 'the CSRF token is 32 random bytes');
    $test->assert(Core\Csrf::check($token), 'the matching token is accepted');
    $test->assert(!Core\Csrf::check('not-the-token'), 'a wrong token is rejected');
    $test->assert(!Core\Csrf::check(''), 'an empty token is rejected');

    Core\Csrf::rotate();
    $test->assert(!Core\Csrf::check($token), 'a token issued before the rotation is rejected');
    $test->assert(str_contains(Core\Csrf::field(), 'name="_token"'), 'the hidden form field carries the token');

    // -------------------------------------------------------- login lockout
    $maximum = Models\Settings::int('max_login_attempts', 5);
    $driverUser = $repository->findActiveByEmail('samuel@itec.rw');
    $test->assert($driverUser !== null, 'the driver account can be loaded');

    $test->assert(!$repository->lockState($driverUser)['locked'], 'a fresh account is not locked');

    for ($attempt = 0; $attempt < $maximum; $attempt++) {
        $current = $repository->findActiveByEmail('samuel@itec.rw');
        $repository->registerFailure('samuel@itec.rw', $current);
    }

    $locked = $repository->findActiveByEmail('samuel@itec.rw');
    $test->assert($repository->lockState($locked)['locked'], "the account locks after {$maximum} failed sign-ins");
    $test->same('locked', (string) $locked['status'], 'the account status becomes locked');
    $test->assert($repository->lockState($locked)['minutes'] > 0, 'the lockout reports how long it lasts');

    $test->assert(
        (int) $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE email = 'samuel@itec.rw' AND succeeded = 0")->fetchColumn() === $maximum,
        'every failed attempt is logged'
    );

    // A lockout whose window has passed clears itself on the next attempt.
    $pdo->prepare("UPDATE users SET locked_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE email = 'samuel@itec.rw'")->execute();
    $cleared = $repository->clearExpiredLock($repository->findActiveByEmail('samuel@itec.rw'));
    $test->assert(!$repository->lockState($cleared)['locked'], 'an expired lockout releases the account');
    $test->same('active', (string) $cleared['status'], 'the account status returns to active');

    $repository->touchLastLogin((int) $cleared['id']);
    $test->same(0, (int) $pdo->query("SELECT failed_login_count FROM users WHERE email = 'samuel@itec.rw'")->fetchColumn(), 'a successful sign-in clears the failure count');

    // ------------------------------------------------------ password rules
    $rejected = [
        'short' => 'Ab1defg',
        'no capital' => 'abcdefghij1',
        'no lower case' => 'ABCDEFGHIJ1',
        'no digit' => 'Abcdefghijk',
        'an obvious word' => 'password',
    ];
    foreach ($rejected as $why => $candidate) {
        $test->assert(
            Controllers\HomeController::passwordProblem($candidate, $candidate) !== null,
            "a password with {$why} is rejected"
        );
    }
    $test->same(null, Controllers\HomeController::passwordProblem('Kigali2026Fresh', 'Kigali2026Fresh'), 'a strong password is accepted');
    $test->assert(Controllers\HomeController::passwordProblem('Kigali2026Fresh', 'Kigali2026Fresk') !== null, 'mismatched confirmation is rejected');

    // Changing a password rehashes it and clears the one-time flag.
    $userId = (int) $accounts['finance']['id'];
    $repository->setPassword($userId, 'Kigali2026Fresh', false);
    $test->assert($repository->verifyPassword($userId, 'Kigali2026Fresh'), 'the new password verifies');
    $test->assert(!$repository->verifyPassword($userId, 'password'), 'the old password no longer works');
    $test->same(0, (int) $pdo->query("SELECT must_change_password FROM users WHERE id = {$userId}")->fetchColumn(), 'changing a password clears the forced-change flag');

    // ------------------------------------------------------- reset tokens
    $plain = $repository->createResetToken($userId, 60);
    $test->same($userId, $repository->consumeResetToken($plain), 'a valid reset token identifies its user');
    $test->same(null, $repository->consumeResetToken($plain), 'a reset token cannot be used twice');
    $test->same(null, $repository->consumeResetToken('nonsense'), 'an unknown reset token is refused');

    $expired = $repository->createResetToken($userId, 60);
    $pdo->prepare('UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE token_hash = ?')->execute([hash('sha256', $expired)]);
    $test->same(null, $repository->consumeResetToken($expired), 'an expired reset token is refused');

    // ------------------------------------------------- driver row scoping
    $samuelDriverId = (int) $pdo->query("SELECT d.id FROM drivers d INNER JOIN users u ON u.id = d.user_id WHERE u.email = 'samuel@itec.rw'")->fetchColumn();
    $test->assert($samuelDriverId > 0, 'the driver login is linked to a driver profile');

    test_sign_in($accounts['super_admin'], 'super_admin', 1);
    $allTrips = Models\LogisticsData::listing('trips', ['per_page' => 100], current_context())['total'];

    test_sign_in($accounts['driver'], 'driver');
    $ownTrips = Models\LogisticsData::listing('trips', ['per_page' => 100], current_context());
    $test->assert($ownTrips['total'] < $allTrips, 'a driver sees fewer trips than an administrator');
    $test->assert($ownTrips['total'] > 0, 'a driver does see their own trips');

    $expectedOwn = (int) $pdo->query("SELECT COUNT(*) FROM trips WHERE driver_id = {$samuelDriverId} AND deleted_at IS NULL")->fetchColumn();
    $test->same($expectedOwn, $ownTrips['total'], 'a driver sees exactly the trips assigned to them');

    foreach ($ownTrips['rows'] as $trip) {
        $ownerId = (int) $pdo->query("SELECT driver_id FROM trips WHERE id = {$trip['id']}")->fetchColumn();
        $test->same($samuelDriverId, $ownerId, 'every trip on the driver list belongs to that driver');
    }

    // Another driver's trip must not be reachable by guessing its id.
    $otherTripId = (int) $pdo->query("SELECT id FROM trips WHERE driver_id <> {$samuelDriverId} AND driver_id IS NOT NULL AND deleted_at IS NULL LIMIT 1")->fetchColumn();
    $test->same(null, Models\LogisticsData::find('trips', $otherTripId, current_context()), 'a driver cannot open another driver trip by its id');
    $test->assert(Models\LogisticsData::find('trips', $otherTripId, ['role' => 'super_admin']) !== null, 'an administrator can open that same trip');

    // A login with no driver profile must see nothing rather than everything.
    $_SESSION['logistics_driver_id'] = null;
    $_SESSION['logistics_user_id'] = (int) $accounts['management']['id'];
    $_SESSION['logistics_role'] = 'driver';
    $test->same(0, Models\LogisticsData::listing('trips', [], ['role' => 'driver', 'driver_id' => null])['total'], 'a driver login with no profile sees no trips');

    // ------------------------------------------------------- permissions
    test_sign_in($accounts['driver'], 'driver');
    $test->assert(!role_can('users'), 'a driver has no access to users');
    $test->assert(!role_can('settings'), 'a driver has no access to company settings');
    $test->assert(!role_can('invoices'), 'a driver has no access to invoices');
    $test->assert(role_can('deliveries', 'edit'), 'a driver may update a delivery');
    $test->assert(!role_can('deliveries', 'delete'), 'a driver may not delete a delivery');

    test_sign_in($accounts['finance'], 'finance');
    $test->assert(role_can('expenses', 'approve'), 'finance may approve expenses');
    $test->assert(!role_can('vehicles'), 'finance has no access to the vehicle registry');

    // Permissions really are editable, which is the point of moving them into the database.
    $financeRoleId = (int) $pdo->query("SELECT id FROM roles WHERE role_key = 'finance'")->fetchColumn();
    Models\Permission::sync($financeRoleId, ['dashboard' => ['view'], 'vehicles' => ['view', 'edit']]);
    test_sign_in($accounts['finance'], 'finance');
    $test->assert(role_can('vehicles', 'edit'), 'a granted permission takes effect');
    $test->assert(!role_can('expenses'), 'a revoked permission takes effect');

    // ------------------------------------------ SQL identifier allowlists
    $test->throws(
        static fn () => Models\Reference::next('X', 'users; DROP TABLE users', 'id'),
        'an unknown table is refused by the reference generator'
    );
    $test->throws(
        static fn () => Models\Reference::next('X', 'users', 'password_hash'),
        'a column outside the allowlist is refused by the reference generator'
    );
    $test->same([], Models\LogisticsData::relationOptions(['table' => 'users', 'label' => 'password_hash']), 'a relation cannot read an arbitrary column');
    $test->same([], Models\LogisticsData::relationOptions(['table' => 'audit_logs', 'label' => 'metadata']), 'a relation cannot read an arbitrary table');

    // -------------------------------------------------- upload validation
    $oversize = ['name' => 'proof.pdf', 'error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024, 'tmp_name' => ''];
    $test->assert(
        Models\LogisticsData::validate('deliveries', ['delivery_code' => 'DEL-SEC-1', 'recipient_name' => 'X', 'destination' => 'Y', 'status' => 'loading'], ['file_proof_file' => $oversize]) !== [],
        'an upload over 5 MB is rejected'
    );

    $executable = ['name' => 'payload.php', 'error' => UPLOAD_ERR_OK, 'size' => 1024, 'tmp_name' => ''];
    $test->assert(
        Models\LogisticsData::validate('deliveries', ['delivery_code' => 'DEL-SEC-2', 'recipient_name' => 'X', 'destination' => 'Y', 'status' => 'loading'], ['file_proof_file' => $executable]) !== [],
        'an upload with an executable extension is rejected'
    );

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
