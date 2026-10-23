<?php

declare(strict_types=1);

/**
 * The go-live controls, end to end:
 *
 *   approvals by department (order, thresholds per currency, separation of
 *   duties, rejection, restart when the amount changes, the inbox, escalation),
 *   papers checked before they count, clearance lodged before a cross-border
 *   truck may leave, the trip released by every department, GPS positions and
 *   the border recognised from them, and updates read per person.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_golive');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Go-live tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $one = static function (string $sql, array $params = []) use ($pdo): mixed {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    };

    // ------------------------------------------------------------ the set-up
    $test->assert(isset($accounts['clearance_officer']), 'there is a Clearance Officer login');
    $test->assert(Models\Permission::allows('clearance_officer', 'crossings', 'approve'), 'the clearance officer moves clearance files');
    $test->assert(Models\Permission::allows('clearance_officer', 'clearance_documents', 'approve'), 'the clearance officer verifies customs papers');
    $test->assert(Models\Permission::allows('management', 'expenses', 'approve'), 'a department on an approval step can press the button');
    $test->assert((int) $one('SELECT COUNT(*) FROM approval_policies WHERE is_active = 1') >= 20, 'the approval rules are in place');

    // ------------------------------------------- an expense, signed in order
    $tripId = (int) $one("SELECT id FROM trips WHERE deleted_at IS NULL ORDER BY id LIMIT 1");
    test_sign_in($accounts['driver'], 'driver', 2);
    $bigUsd = Models\LogisticsData::save('expenses', null, [
        'reference_code' => 'EXP-GL-001', 'category' => 'Repair', 'amount' => '2500', 'currency' => 'USD',
        'expense_date' => date('Y-m-d'), 'trip_id' => (string) $tripId, 'status' => 'pending', 'payment_method' => 'cash',
    ]);
    $smallRwf = Models\LogisticsData::save('expenses', null, [
        'reference_code' => 'EXP-GL-002', 'category' => 'Allowance', 'amount' => '2500', 'currency' => 'RWF',
        'expense_date' => date('Y-m-d'), 'trip_id' => (string) $tripId, 'status' => 'pending', 'payment_method' => 'cash',
    ]);
    $steps = static fn (int $id): array => $pdo->query("SELECT step_label, approver_role, status FROM approval_steps WHERE module = 'expenses' AND record_id = {$id} ORDER BY round_no DESC, step_no")->fetchAll();
    $test->same(3, count($steps($bigUsd)), 'USD 2,500 needs operations, finance and management');
    $test->same(2, count($steps($smallRwf)), 'RWF 2,500 is not compared with a dollar threshold: operations and finance only');
    $test->same('pending', $steps($bigUsd)[0]['status'], 'the first step is in front of operations');
    $test->assert(
        (int) $one("SELECT COUNT(*) FROM notifications WHERE role_key = 'logistics_manager' AND title LIKE 'Approval needed:%' AND entity_type = 'expenses'") >= 2,
        'operations is told an approval is waiting'
    );
    $test->same(2, count(array_filter(Models\Approvals::inbox('logistics_manager'), static fn (array $s): bool => $s['module'] === 'expenses' && in_array((int) $s['record_id'], [$bigUsd, $smallRwf], true))), 'both expenses are in the operations inbox');

    // The wrong department cannot sign the step in front of another.
    test_sign_in($accounts['finance'], 'finance', 2);
    $early = Models\Workflow::apply('expenses', $bigUsd, 'approve');
    $test->assert(!$early['ok'] && str_contains($early['message'], 'Logistics Manager'), 'finance cannot sign before operations: ' . $early['message']);

    test_sign_in($accounts['logistics_manager'], 'logistics_manager', 2);
    $first = Models\Workflow::apply('expenses', $bigUsd, 'approve', 'Repair on the trip');
    $test->assert($first['ok'] && str_contains($first['message'], 'Step 1 of 3'), 'operations signs step 1 of 3: ' . $first['message']);
    $test->same('pending', (string) $one('SELECT status FROM expenses WHERE id = ?', [$bigUsd]), 'one signature does not approve the expense');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE role_key = 'finance' AND title LIKE 'Approval needed:%' AND entity_id = ?", [(string) $bigUsd]) === 1, 'finance is told it is their turn');

    test_sign_in($accounts['finance'], 'finance', 2);
    $test->assert(Models\Workflow::apply('expenses', $bigUsd, 'approve')['ok'], 'finance signs step 2');
    test_sign_in($accounts['management'], 'management', 2);
    $last = Models\Workflow::apply('expenses', $bigUsd, 'approve');
    $test->assert($last['ok'], 'management signs the last step: ' . $last['message']);
    $test->same('approved', (string) $one('SELECT status FROM expenses WHERE id = ?', [$bigUsd]), 'the last signature approves the expense');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'Approved: EXP-GL-001'", [$accounts['driver']['id']]) === 1, 'the person who raised it is told it is fully approved');

    // Nobody signs what they raised.
    test_sign_in($accounts['finance'], 'finance', 2);
    $ownId = Models\LogisticsData::save('expenses', null, [
        'reference_code' => 'EXP-GL-003', 'category' => 'Parking', 'amount' => '10', 'currency' => 'USD',
        'expense_date' => date('Y-m-d'), 'trip_id' => (string) $tripId, 'status' => 'pending', 'payment_method' => 'cash',
    ]);
    test_sign_in($accounts['logistics_manager'], 'logistics_manager', 2);
    Models\Workflow::apply('expenses', $ownId, 'approve');
    test_sign_in($accounts['finance'], 'finance', 2);
    $own = Models\Workflow::apply('expenses', $ownId, 'approve');
    $test->assert(!$own['ok'] && str_contains($own['message'], 'You raised this record'), 'the person who raised a record cannot approve it');

    // Sent back: the round ends and the person who raised it hears why.
    test_sign_in($accounts['logistics_manager'], 'logistics_manager', 2);
    $rejected = Models\Workflow::apply('expenses', $smallRwf, 'reject', 'No receipt attached');
    $test->assert($rejected['ok'], 'a step can send the record back with a reason');
    $test->same('rejected', $steps($smallRwf)[0]['status'], 'the step records the rejection');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'Sent back: EXP-GL-002'", [$accounts['driver']['id']]) === 1, 'the person who raised it is told it was sent back');
    $test->same(0, count(array_filter(Models\Approvals::inbox('logistics_manager'), static fn (array $s): bool => (int) $s['record_id'] === $smallRwf && $s['module'] === 'expenses')), 'a sent-back record leaves the inbox');

    // A changed amount is a different record: the signatures start again.
    test_sign_in($accounts['driver'], 'driver', 2);
    $changing = Models\LogisticsData::save('expenses', null, [
        'reference_code' => 'EXP-GL-004', 'category' => 'Toll', 'amount' => '100', 'currency' => 'USD',
        'expense_date' => date('Y-m-d'), 'trip_id' => (string) $tripId, 'status' => 'pending', 'payment_method' => 'cash',
    ]);
    test_sign_in($accounts['logistics_manager'], 'logistics_manager', 2);
    Models\Workflow::apply('expenses', $changing, 'approve');
    $pdo->prepare('UPDATE expenses SET amount = 3000 WHERE id = ?')->execute([$changing]);
    Models\Approvals::open('expenses', $changing);
    $round = (int) $one("SELECT MAX(round_no) FROM approval_steps WHERE module = 'expenses' AND record_id = ?", [$changing]);
    $test->same(2, $round, 'raising the amount after a signature starts a new round');
    $test->same(3, (int) $one("SELECT COUNT(*) FROM approval_steps WHERE module = 'expenses' AND record_id = ? AND round_no = 2", [$changing]), 'the new amount brings in management');

    // The form cannot give a status only the signatures give.
    $formErrors = Models\LogisticsData::validate('expenses', ['status' => 'approved', 'category' => 'Toll', 'amount' => '3000', 'currency' => 'USD', 'expense_date' => date('Y-m-d'), 'payment_method' => 'cash'], [], $changing);
    $test->assert(array_filter($formErrors, static fn (string $e): bool => str_contains($e, 'given by the approvals')) !== [], 'the form refuses to type in "Approved"');

    // Out of time: management is told, once.
    $pdo->prepare("UPDATE approval_steps SET activated_at = DATE_SUB(NOW(), INTERVAL 3 DAY), due_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE module = 'expenses' AND record_id = ? AND status = 'pending'")->execute([$changing]);
    $sweep = Models\Approvals::sweep();
    $test->assert($sweep['escalated'] >= 1, 'an overdue step is escalated');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE role_key = 'management' AND title = 'Approval overdue'") >= 1, 'management hears about the overdue approval');
    $again = Models\Approvals::sweep();
    $test->same(0, $again['escalated'], 'an escalation is raised only once');

    // ------------------------------------------------- updates read per person
    $secondFinance = Models\LogisticsData::save('users', null, [
        'full_name' => 'Second Accountant', 'email' => 'second.finance@test.rw', 'role_id' => (string) $one("SELECT id FROM roles WHERE role_key = 'finance'"),
        'status' => 'active', 'prvg' => '2', 'must_change_password' => '0',
    ]);
    Models\LogisticsData::takeOneTimePassword();
    Models\Notifier::bellToRole('finance', 'Shared update', 'For every accountant.');
    $key = (string) $one("SELECT notification_key FROM notifications WHERE title = 'Shared update' ORDER BY id DESC LIMIT 1");
    $notes = new Models\Notification();
    $beforeFirst = $notes->unreadCount((int) $accounts['finance']['id'], 'finance');
    $beforeSecond = $notes->unreadCount($secondFinance, 'finance');
    Models\Notifier::markRead((int) $accounts['finance']['id'], 'finance', $key);
    $test->same($beforeFirst - 1, $notes->unreadCount((int) $accounts['finance']['id'], 'finance'), 'reading an update clears it for the reader');
    $test->same($beforeSecond, $notes->unreadCount($secondFinance, 'finance'), 'a colleague still has it unread');

    // ---------------------------------------- a cross-border run, done right
    test_sign_in($accounts['super_admin'], 'super_admin', 1);
    $postId = (int) Models\LogisticsData::save('border_posts', null, [
        'post_name' => 'Testgate / Testpost', 'country_a' => 'Rwanda', 'country_b' => 'Tanzania', 'typical_hours' => '6', 'is_active' => '1',
        'latitude' => '-2.3828', 'longitude' => '30.7837', 'geofence_m' => '2000',
    ]);
    $customerId = (int) $one("SELECT id FROM customers WHERE deleted_at IS NULL ORDER BY id LIMIT 1");
    $vehicleId = Models\LogisticsData::save('vehicles', null, [
        'plate_number' => 'RAG 001 T', 'vehicle_type' => 'Truck', 'capacity_kg' => '30000', 'status' => 'available', 'fuel_type' => 'diesel', 'ownership' => 'owned', 'mileage' => '100000',
        'gps_device_id' => 'IMEI-TEST-0001',
    ]);
    $driverId = Models\LogisticsData::save('drivers', null, [
        'full_name' => 'Test Corridor Driver', 'license_number' => 'RWA-DL-GL01', 'license_expiry' => date('Y-m-d', strtotime('+2 years')), 'status' => 'available', 'phone' => '+250 788 000 900',
    ]);
    $year = date('Y-m-d', strtotime('+1 year'));
    foreach (['insurance', 'inspection', 'permit'] as $n => $type) {
        $pdo->prepare("INSERT INTO vehicle_documents (document_code, vehicle_id, document_type, document_number, issued_on, expires_on, document_file, status) VALUES (?, ?, ?, ?, CURDATE(), ?, 'vehicle_documents/t.pdf', 'valid')")
            ->execute(['DOC-GL-' . $n, $vehicleId, $type, 'N-' . $n, $year]);
    }
    foreach (['driving_licence', 'passport'] as $type) {
        $pdo->prepare("INSERT INTO driver_documents (driver_id, document_type, document_number, issue_date, expiry_date, status, document_file) VALUES (?, ?, ?, CURDATE(), ?, 'valid', 'driver_documents/t.pdf')")
            ->execute([$driverId, $type, 'P-' . $type, $year]);
    }

    $tripId = Models\LogisticsData::save('trips', null, [
        'reference_code' => 'TRP-GL-001', 'customer_id' => (string) $customerId, 'trip_type' => 'delivery',
        'pickup_location' => 'Kigali', 'destination' => 'Dar es Salaam',
        'planned_departure_at' => date('Y-m-d H:i:s', strtotime('+1 hour')), 'planned_arrival_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'vehicle_id' => (string) $vehicleId, 'driver_id' => (string) $driverId, 'status' => 'requested',
    ]);
    Models\LogisticsData::saveLines('trips', $tripId, [
        ['stop_sequence' => '1', 'stop_type' => 'pickup', 'location_name' => 'Kigali', 'status' => 'pending'],
        ['stop_sequence' => '2', 'stop_type' => 'checkpoint', 'location_name' => 'Testgate border', 'status' => 'pending'],
        ['stop_sequence' => '3', 'stop_type' => 'dropoff', 'location_name' => 'Dar es Salaam', 'status' => 'pending'],
    ]);
    $shipmentId = Models\LogisticsData::save('shipments', null, [
        'shipment_code' => 'SHP-GL-001', 'customer_id' => (string) $customerId, 'trip_id' => (string) $tripId,
        'consignee_name' => 'Dar Buyer', 'consignee_phone' => '+255 700 000 001', 'origin' => 'Kigali', 'destination' => 'Dar es Salaam',
        'cargo_type' => 'general', 'cargo_description' => 'Coffee, 300 bags', 'packages_count' => '300', 'weight_kg' => '18000',
        'currency' => 'USD', 'status' => 'booked', 'container_size' => 'loose',
    ]);

    // The border is on the route and nobody has opened its file.
    $noFile = Models\Clearance::dispatchGuard($tripId);
    $test->assert($noFile !== null && str_contains($noFile, 'no clearance file'), 'a border on the route with no clearance file holds the truck: ' . (string) $noFile);
    $earlyRelease = Models\Workflow::apply('trips', $tripId, 'approve');
    $test->assert(!$earlyRelease['ok'] && str_contains($earlyRelease['message'], 'Not ready for release'), 'the trip cannot be released before its clearance is lodged');

    // The clearance file: checklist, papers checked, declaration lodged, bond.
    $crossingId = Models\LogisticsData::save('crossings', null, [
        'reference' => 'BDR-GL-001', 'shipment_id' => (string) $shipmentId, 'trip_id' => (string) $tripId, 'border_post_id' => (string) $postId,
        'direction' => 'export', 'currency' => 'RWF', 'vehicle_id' => (string) $vehicleId, 'driver_id' => (string) $driverId,
    ]);
    $checklist = $pdo->query("SELECT document_type FROM border_documents WHERE crossing_id = {$crossingId} ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $test->same(['Export declaration', 'Commercial invoice', 'Packing list', 'Certificate of origin'], $checklist, 'an export file opens with the export checklist');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE role_key = 'clearance_officer' AND title = 'New clearance file: BDR-GL-001'") === 1, 'the clearance department is told a new file is open');
    $pending = Models\Clearance::dispatchGuard($tripId);
    $test->assert($pending !== null && str_contains($pending, 'Papers not yet checked'), 'unchecked papers hold the truck');

    $pdo->exec("UPDATE border_documents SET document_file = 'clearance_documents/t.pdf', document_no = CONCAT('NO-', id) WHERE crossing_id = {$crossingId}");
    test_sign_in($accounts['clearance_officer'], 'clearance_officer', 2);
    foreach ($pdo->query("SELECT id FROM border_documents WHERE crossing_id = {$crossingId}")->fetchAll(PDO::FETCH_COLUMN) as $paperId) {
        $verified = Models\Workflow::apply('clearance_documents', (int) $paperId, 'verify');
        $test->assert($verified['ok'], 'the clearance officer verifies a paper: ' . $verified['message']);
    }
    $notLodged = Models\Clearance::dispatchGuard($tripId);
    $test->assert($notLodged !== null && str_contains($notLodged, 'not lodged'), 'checked papers are not enough: the declaration must be lodged');
    $pdo->prepare("UPDATE border_crossings SET declaration_no = 'RRA-EX-2026-001' WHERE id = ?")->execute([$crossingId]);
    $test->assert(Models\Workflow::apply('crossings', $crossingId, 'submit_declaration')['ok'], 'the clearance officer lodges the declaration');
    $test->same(null, Models\Clearance::dispatchGuard($tripId), 'with the declaration lodged the clearance no longer holds the truck');

    // The trip released by the departments on the rules, in order.
    $trip = $pdo->query("SELECT * FROM trips WHERE id = {$tripId}")->fetch();
    $releaseSteps = Models\Approvals::requiredSteps('trips', 'approve', $trip);
    $test->same(['clearance_officer', 'logistics_manager'], array_column($releaseSteps, 'approver_role'), 'a cross-border trip is released by clearance, then operations');
    $test->assert(str_contains((string) Models\Workflow::awaitingRelease($trip), 'Clearance ready'), 'until then it waits for "Clearance ready"');
    $byClearance = Models\Workflow::apply('trips', $tripId, 'approve');
    $test->assert($byClearance['ok'] && str_contains($byClearance['message'], 'Step 1 of 2'), 'clearance signs: ' . $byClearance['message']);
    test_sign_in($accounts['logistics_manager'], 'logistics_manager', 2);
    $byOperations = Models\Workflow::apply('trips', $tripId, 'approve');
    $test->assert($byOperations['ok'], 'operations releases the truck: ' . $byOperations['message']);
    $test->same('approved', (string) $one('SELECT status FROM trips WHERE id = ?', [$tripId]), 'the trip reads Approved');

    // The document pack: every paper checked by operations (and clearance, cross-border).
    $papersNeeded = Models\DocumentPack::requiredFor($pdo->query("SELECT * FROM shipments WHERE id = {$shipmentId}")->fetch());
    $packBlocked = Models\Workflow::apply('trips', $tripId, 'dispatch');
    $test->assert(!$packBlocked['ok'], 'the truck does not leave without its document pack: ' . $packBlocked['message']);
    foreach ($papersNeeded as $type) {
        $paperId = Models\LogisticsData::save('shipment_documents', null, [
            'shipment_id' => (string) $shipmentId, 'document_type' => $type, 'document_number' => 'SD-' . substr(md5($type), 0, 6), 'status' => 'valid',
        ]);
        $test->same('pending', (string) $one('SELECT status FROM shipment_documents WHERE id = ?', [$paperId]), 'a new paper starts pending whatever the form says: ' . $type);
        $pdo->prepare("UPDATE shipment_documents SET document_file = 'shipment_documents/t.pdf' WHERE id = ?")->execute([$paperId]);
        test_sign_in($accounts['logistics_manager'], 'logistics_manager', 2);
        $ops = Models\Workflow::apply('shipment_documents', $paperId, 'verify');
        test_sign_in($accounts['clearance_officer'], 'clearance_officer', 2);
        $customs = Models\Workflow::apply('shipment_documents', $paperId, 'verify');
        $test->assert($ops['ok'] && $customs['ok'] && (string) $one('SELECT status FROM shipment_documents WHERE id = ?', [$paperId]) === 'valid', 'a cross-border paper is checked by operations, then clearance: ' . $type);
    }
    test_sign_in($accounts['logistics_manager'], 'logistics_manager', 2);
    Models\LogisticsData::save('pre_dispatch_checks', null, [
        'shipment_id' => (string) $shipmentId, 'cargo_loaded' => '1', 'quantity_verified' => '1', 'packaging_verified' => '1',
        'status' => 'ready', 'checked_by' => (string) $accounts['logistics_manager']['id'], 'checked_at' => date('Y-m-d H:i:s'),
    ]);
    $analysis = Models\DispatchReadiness::forTrip($tripId);
    $test->assert($analysis !== null && $analysis['ready'], 'the trip is ready to leave: ' . implode('; ', $analysis['failures'] ?? []));
    $dispatched = Models\Workflow::apply('trips', $tripId, 'dispatch');
    $test->assert($dispatched['ok'], 'with clearance lodged, the release signed and the papers checked, the truck leaves: ' . $dispatched['message']);

    // ------------------------------------------------------- GPS on the road
    $far = Models\Tracking::record($vehicleId, -1.9441, 30.0619, null, 'device', 60.0);
    $test->assert($far['ok'] && $far['trip_id'] === $tripId, 'a position is stored against the trip on the road');
    $border = Models\Tracking::record($vehicleId, -2.3830, 30.7840, null, 'device', 5.0);
    $test->assert(str_contains($border['message'], 'Arrived at Testgate border'), 'a position inside the border circle records the arrival: ' . $border['message']);
    $stop = $pdo->query("SELECT actual_arrival_at, arrival_source FROM trip_stops WHERE trip_id = {$tripId} AND stop_type = 'checkpoint'")->fetch();
    $test->assert($stop['actual_arrival_at'] !== null && $stop['arrival_source'] === 'gps', 'the stop says it was reached by GPS');
    $gone = Models\Tracking::record($vehicleId, -2.5000, 31.0000, null, 'device', 50.0);
    $test->assert(str_contains($gone['message'], 'Left Testgate border'), 'leaving the circle records the departure: ' . $gone['message']);
    $test->same(3, count(Models\Tracking::trail($tripId)), 'the trail has every position');
    $test->assert(Models\Tracking::record($vehicleId, 0.0, 0.0)['ok'] === false, 'a position at 0,0 is refused');

    // ------------------------------------------------------------ dashboards
    foreach (['super_admin', 'logistics_manager', 'clearance_officer', 'finance', 'management', 'fleet_manager'] as $role) {
        $panels = Models\Workboard::forRole($role, null);
        $test->assert($panels !== [], 'the ' . $role . ' dashboard has its work panels');
    }
    $money = Models\ChartData::moneyByCurrency("SELECT 'USD' AS currency, 10 AS total UNION ALL SELECT 'RWF', 5000");
    $test->assert(str_starts_with($money, 'RWF') && str_contains($money, 'USD'), 'money on a card is shown per currency, base first: ' . $money);

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
