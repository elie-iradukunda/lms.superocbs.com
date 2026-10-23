<?php

declare(strict_types=1);

/**
 * The walkthrough in the user guide, done by the system itself: one 40ft
 * container from Mombasa to Kigali, each step signed in as the person whose
 * job it is, in the order the guide gives. If the guide says a button works,
 * this proves it does.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_walkthrough');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Walkthrough: Mombasa to Kigali');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();

    $one = static function (string $sql, array $params = []) use ($pdo): mixed {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    };
    $ok = static function (array $result, string $what) use ($test): void {
        $test->assert($result['ok'], $what . ': ' . $result['message']);
    };
    $roleId = static fn (string $key): string => (string) $one('SELECT id FROM roles WHERE role_key = ?', [$key]);
    $as = static function (array $person): void {
        test_sign_in(['id' => $person['id'], 'name' => $person['name'], 'email' => $person['email']], $person['role'], 2);
    };
    $scan = static fn (string $table, int $id) => $pdo->prepare("UPDATE {$table} SET document_file = '{$table}/scan.pdf' WHERE id = ?")->execute([$id]);
    $clearancePapers = static fn (int $crossingId): array => $pdo->query("SELECT id, document_type FROM border_documents WHERE crossing_id = {$crossingId} ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);

    // ---------------------------------------------------- 0. Super Admin: the cast
    test_sign_in($accounts['super_admin'], 'super_admin', 1);
    $people = [];
    foreach ([
        'grace' => ['Grace Wanjiru', 'grace@eri-kenya.test', 'logistics_manager', 'Operations officer, Mombasa'],
        'peter' => ['Peter Otieno', 'peter@eri-kenya.test', 'logistics_manager', 'Operations manager'],
        'amina' => ['Amina Hassan', 'amina@eri-kenya.test', 'clearance_officer', 'Clearance officer, Mombasa'],
        'jean' => ['Jean Bosco Habimana', 'jean@eri-kenya.test', 'clearance_officer', 'Clearance officer, Kigali'],
        'david' => ['David Mwangi', 'david@eri-kenya.test', 'fleet_manager', 'Fleet manager'],
        'samuel' => ['Samuel Kiprono', 'samuel@eri-kenya.test', 'driver', 'Driver'],
        'ruth' => ['Ruth Achieng', 'ruth@eri-kenya.test', 'finance', 'Accountant'],
        'joseph' => ['Joseph Kamau', 'joseph@eri-kenya.test', 'finance', 'Finance manager'],
        'md' => ['Managing Director', 'md@eri-kenya.test', 'management', 'Managing Director'],
    ] as $key => [$name, $email, $role, $title]) {
        $id = Models\LogisticsData::save('users', null, ['full_name' => $name, 'email' => $email, 'role_id' => $roleId($role), 'job_title' => $title, 'status' => 'active', 'prvg' => '2', 'must_change_password' => '0']);
        Models\LogisticsData::takeOneTimePassword();
        $people[$key] = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role];
    }
    $test->same(9, count($people), 'the nine people of the walkthrough have accounts');

    $customerId = Models\LogisticsData::save('customers', null, [
        'customer_code' => '', 'customer_name' => 'Kigali Hardware Ltd', 'customer_type' => 'corporate', 'contact_name' => 'Eric Mugisha',
        'phone' => '+250 788 123 456', 'email' => 'orders@kigalihardware.test', 'payment_terms_days' => '30', 'currency' => 'USD', 'status' => 'active',
    ]);
    $lineId = Models\LogisticsData::save('suppliers', null, ['supplier_code' => '', 'supplier_name' => 'Maersk Kenya', 'category' => 'Shipping line', 'status' => 'active', 'payment_terms_days' => '30']);
    Models\LogisticsData::save('rates', null, [
        'rate_code' => '', 'service_type' => 'full_truck', 'status' => 'active', 'origin' => 'Mombasa', 'destination' => 'Kigali',
        'rate_type' => 'per_trip', 'rate_amount' => '3800', 'currency' => 'USD', 'effective_from' => date('Y-m-d', strtotime('-1 day')),
    ]);

    // ------------------------------------------- 0. Fleet manager: truck and driver
    $as($people['david']);
    $truckId = Models\LogisticsData::save('vehicles', null, [
        'plate_number' => 'KDA 512X', 'vehicle_type' => 'Prime mover', 'make' => 'Mercedes', 'model' => 'Actros', 'capacity_kg' => '30000',
        'status' => 'available', 'fuel_type' => 'diesel', 'ownership' => 'owned', 'mileage' => '412000', 'gps_device_id' => '356307042441013',
    ]);
    $driverId = Models\LogisticsData::save('drivers', null, [
        'full_name' => 'Samuel Kiprono', 'phone' => '+254 711 222 333', 'license_number' => 'KE-DL-778812', 'license_class' => 'E',
        'license_expiry' => date('Y-m-d', strtotime('+2 years')), 'user_id' => (string) $people['samuel']['id'], 'status' => 'available',
    ]);
    $inAYear = date('Y-m-d', strtotime('+1 year'));
    foreach (['insurance' => 'Jubilee Insurance', 'inspection' => 'NTSA', 'permit' => 'COMESA / RURA'] as $type => $issuer) {
        $docId = Models\LogisticsData::save('vehicle_documents', null, ['document_code' => '', 'vehicle_id' => (string) $truckId, 'document_type' => $type, 'document_number' => strtoupper($type) . '-2026-77', 'provider_name' => $issuer, 'issued_on' => date('Y-m-d'), 'expires_on' => $inAYear]);
        $scan('vehicle_documents', $docId);
    }
    foreach (['driving_licence', 'passport'] as $type) {
        $docId = Models\LogisticsData::save('driver_documents', null, ['driver_id' => (string) $driverId, 'document_type' => $type, 'document_number' => strtoupper($type) . '-1', 'issue_date' => date('Y-m-d', strtotime('-1 year')), 'expiry_date' => $inAYear, 'status' => 'valid']);
        $scan('driver_documents', $docId);
    }
    $test->same('valid', (string) $one("SELECT status FROM vehicle_documents WHERE vehicle_id = ? AND document_type = 'insurance'", [$truckId]), 'the truck insurance is valid');

    // ------------------------------------------------- 1. The customer asks (website)
    $pdo->prepare("INSERT INTO transport_requests (reference_code, source, service_type, load_type, customer_id, requested_by_contact, contact_email, contact_phone, company_name,
                   pickup_location, destination, cargo_description, weight_kg, packages_count, required_date, priority, status, currency)
                   VALUES ('REQ-WALK-001', 'website', 'sea', 'fcl', ?, 'Eric Mugisha', 'orders@kigalihardware.test', '+250 788 123 456', 'Kigali Hardware Ltd',
                   'Mombasa', 'Kigali', '1 x 40ft container of building materials', 24000, 1, ?, 'normal', 'pending', 'USD')")
        ->execute([$customerId, date('Y-m-d', strtotime('+30 days'))]);
    $requestId = (int) $pdo->lastInsertId();
    Models\Approvals::open('requests', $requestId);

    // ----------------------------------------- 2. Grace (Operations): price and accept
    $as($people['grace']);
    $quoted = Models\Workflow::apply('requests', $requestId, 'quote');
    $ok($quoted, 'Grace quotes the customer from the rate card');
    $test->same(3800.0, (float) $one('SELECT quoted_amount FROM transport_requests WHERE id = ?', [$requestId]), 'the rate card gives USD 3,800');
    $ok(Models\Workflow::apply('requests', $requestId, 'approve'), 'Grace records that the customer accepted');
    $test->same('approved', (string) $one('SELECT status FROM transport_requests WHERE id = ?', [$requestId]), 'under USD 10,000, Operations alone approves the request');

    // ----------------------------------------------- 3. Grace: the freight job
    $jobId = Models\LogisticsData::save('freight_jobs', null, [
        'job_code' => '', 'customer_id' => (string) $customerId, 'request_id' => (string) $requestId, 'direction' => 'import', 'mode' => 'sea',
        'scope' => 'door_to_door', 'incoterm' => 'CIF', 'load_type' => 'fcl', 'commodity' => 'Building materials', 'hs_code' => '6907.21',
        'packages' => '1', 'weight_kg' => '24000', 'containers_count' => '1', 'container_numbers' => 'MSKU7788123', 'origin' => 'Guangzhou, China',
        'port_of_loading' => 'Nansha', 'port_of_discharge' => 'Mombasa', 'final_destination' => 'Kigali', 'carrier_id' => (string) $lineId,
        'vessel_flight' => 'Maersk Kampala', 'voyage_no' => '242W', 'booking_no' => 'MAEU-BK-5521', 'eta' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'insured' => '1', 'insurer' => 'Jubilee Insurance', 'insurance_cert_no' => 'MC-2026-11872', 'insured_value' => '48000', 'premium' => '240', 'insurance_currency' => 'USD',
        'quoted_amount' => '3800', 'currency' => 'USD', 'notify_customer' => '1',
    ]);
    $job = $pdo->query("SELECT * FROM freight_jobs WHERE id = {$jobId}")->fetch();
    $shipmentId = (int) $job['shipment_id'];
    $test->assert($shipmentId > 0, 'the job opened its shipment');
    $test->same(3800.0, (float) $one('SELECT quoted_amount FROM shipments WHERE id = ?', [$shipmentId]), 'the agreed price is on the shipment');

    // The shipment: who receives it, the box and its seal.
    $shipment = $pdo->query("SELECT * FROM shipments WHERE id = {$shipmentId}")->fetch();
    Models\LogisticsData::save('shipments', $shipmentId, array_merge(array_map('strval', array_filter($shipment, static fn ($v) => $v !== null)), [
        'consignee_name' => 'Eric Mugisha (Kigali Hardware Ltd)', 'consignee_phone' => '+250 788 123 456', 'container_size' => '40ft',
        'seal_no' => 'ML-4471209', 'trailer_plate' => 'ZF 8812', 'declared_value' => '48000', 'status' => 'booked',
    ]));
    $test->same('40ft', (string) $one('SELECT container_size FROM shipments WHERE id = ?', [$shipmentId]), 'the shipment records the 40ft box');

    $noBl = Models\Workflow::apply('freight_jobs', $jobId, 'depart');
    $test->assert(!$noBl['ok'], 'the job cannot depart without the bill of lading');
    $pdo->prepare("UPDATE freight_jobs SET master_bl = 'MAEU242W77812' WHERE id = ?")->execute([$jobId]);
    $ok(Models\Workflow::apply('freight_jobs', $jobId, 'depart'), 'Grace: Departed origin');
    $ok(Models\Workflow::apply('freight_jobs', $jobId, 'arrive'), 'Grace: Arrived at port');

    // ----------------------------------- 4. Amina (Clearance, Mombasa): Kenyan transit
    $as($people['amina']);
    $malaba = (int) $one("SELECT id FROM border_posts WHERE post_name = 'Malaba'");
    $gatuna = (int) $one("SELECT id FROM border_posts WHERE post_name LIKE 'Gatuna%'");
    $kenyaFile = Models\LogisticsData::save('crossings', null, [
        'reference' => '', 'shipment_id' => (string) $shipmentId, 'border_post_id' => (string) $malaba, 'direction' => 'transit', 'customs_country' => 'Kenya', 'currency' => 'KES',
    ]);
    $kenyaPapers = $clearancePapers($kenyaFile);
    $test->assert(in_array('T1 transit bond', $kenyaPapers, true) && in_array('Electronic cargo tracking seal', $kenyaPapers, true) && in_array('Delivery order', $kenyaPapers, true), 'the Kenyan transit file asks for T1, ECTS seal and delivery order: ' . implode(', ', $kenyaPapers));
    $numbers = ['Customs declaration' => 'KE-TR-2026-118822', 'T1 transit bond' => 'RCTG-KE-55120', 'Electronic cargo tracking seal' => 'ECTS-889210', 'Commercial invoice' => 'CI-88123', 'Packing list' => 'PL-88123', 'Bill of lading' => 'MAEU242W77812', 'Delivery order' => 'DO-MSK-7781'];
    foreach (['Commercial invoice', 'Packing list', 'Bill of lading', 'Delivery order'] as $type) {
        $paper = array_search($type, $kenyaPapers, true);
        Models\LogisticsData::save('clearance_documents', (int) $paper, ['crossing_id' => (string) $kenyaFile, 'document_type' => $type, 'document_no' => $numbers[$type]]);
        $scan('border_documents', (int) $paper);
        $ok(Models\Workflow::apply('clearance_documents', (int) $paper, 'verify'), 'Amina verifies ' . $type);
    }
    $pdo->prepare('UPDATE border_crossings SET declaration_no = ? WHERE id = ?')->execute(['KE-TR-2026-118822', $kenyaFile]);
    $ok(Models\Workflow::apply('crossings', $kenyaFile, 'submit_declaration'), 'Amina lodges the Kenyan transit entry');
    foreach (['Customs declaration', 'T1 transit bond', 'Electronic cargo tracking seal'] as $type) {
        $paper = array_search($type, $kenyaPapers, true);
        Models\LogisticsData::save('clearance_documents', (int) $paper, ['crossing_id' => (string) $kenyaFile, 'document_type' => $type, 'document_no' => $numbers[$type]]);
        $scan('border_documents', (int) $paper);
        $ok(Models\Workflow::apply('clearance_documents', (int) $paper, 'verify'), 'Amina verifies ' . $type);
    }
    $kenya = $pdo->query("SELECT * FROM border_crossings WHERE id = {$kenyaFile}")->fetch();
    $test->same('RCTG-KE-55120', (string) $kenya['transit_bond_no'], 'the RCTG number fills the file');
    $test->same('ECTS-889210', (string) $kenya['seal_no'], 'the ECTS number fills the seal');

    // ------------------------------------- 5. Jean (Clearance, Kigali): Rwanda import
    $as($people['jean']);
    $rwandaFile = Models\LogisticsData::save('crossings', null, [
        'reference' => '', 'shipment_id' => (string) $shipmentId, 'border_post_id' => (string) $gatuna, 'direction' => 'import', 'customs_country' => 'Rwanda', 'currency' => 'RWF',
    ]);
    $rwandaPapers = $clearancePapers($rwandaFile);
    foreach ($rwandaPapers as $paper => $type) {
        if (in_array($type, ['Import declaration', 'Tax payment receipt'], true)) {
            continue;
        }
        Models\LogisticsData::save('clearance_documents', (int) $paper, ['crossing_id' => (string) $rwandaFile, 'document_type' => $type, 'document_no' => 'RW-' . substr(md5($type), 0, 6)]);
        $scan('border_documents', (int) $paper);
        $ok(Models\Workflow::apply('clearance_documents', (int) $paper, 'verify'), 'Jean verifies ' . $type);
    }
    $pdo->prepare('UPDATE border_crossings SET declaration_no = ? WHERE id = ?')->execute(['C 2026/RRA/554120', $rwandaFile]);
    $ok(Models\Workflow::apply('crossings', $rwandaFile, 'submit_declaration'), 'Jean lodges the Rwanda import declaration (SCT, before the truck leaves)');
    $declaration = (int) array_search('Import declaration', $rwandaPapers, true);
    Models\LogisticsData::save('clearance_documents', $declaration, ['crossing_id' => (string) $rwandaFile, 'document_type' => 'Import declaration', 'document_no' => 'C 2026/RRA/554120']);
    $scan('border_documents', $declaration);
    $ok(Models\Workflow::apply('clearance_documents', $declaration, 'verify'), 'Jean verifies the import declaration');

    // ------------------------------------------------- 6. Grace: customs at the port
    $as($people['grace']);
    $ok(Models\Workflow::apply('freight_jobs', $jobId, 'start_customs'), 'Grace: Entry lodged');
    $pdo->prepare("UPDATE freight_jobs SET entry_no = 'KE-TR-2026-118822' WHERE id = ?")->execute([$jobId]);
    $ok(Models\Workflow::apply('freight_jobs', $jobId, 'clear'), 'Grace: Cleared by customs (every file lodged)');

    // --------------------------------------------------- 7. Grace: the trip
    $tripId = Models\LogisticsData::save('trips', null, [
        'reference_code' => '', 'customer_id' => (string) $customerId, 'trip_type' => 'delivery', 'pickup_location' => 'Mombasa', 'destination' => 'Kigali',
        'cargo_summary' => '1 x 40ft building materials', 'vehicle_id' => (string) $truckId, 'driver_id' => (string) $driverId,
        'planned_departure_at' => date('Y-m-d H:i:s', strtotime('+1 hour')), 'planned_arrival_at' => date('Y-m-d H:i:s', strtotime('+5 days')), 'status' => 'requested',
    ]);
    Models\LogisticsData::saveLines('trips', $tripId, [
        ['stop_sequence' => '1', 'stop_type' => 'pickup', 'location_name' => 'Mombasa CFS', 'status' => 'pending'],
        ['stop_sequence' => '2', 'stop_type' => 'checkpoint', 'location_name' => 'Malaba border', 'status' => 'pending'],
        ['stop_sequence' => '3', 'stop_type' => 'waypoint', 'location_name' => 'Kampala (overnight)', 'status' => 'pending'],
        ['stop_sequence' => '4', 'stop_type' => 'checkpoint', 'location_name' => 'Gatuna border', 'status' => 'pending'],
        ['stop_sequence' => '5', 'stop_type' => 'dropoff', 'location_name' => 'Kigali Hardware Ltd, Gikondo', 'contact_name' => 'Eric Mugisha', 'contact_phone' => '+250 788 123 456', 'status' => 'pending'],
    ]);
    $pdo->prepare('UPDATE shipments SET trip_id = ? WHERE id = ?')->execute([$tripId, $shipmentId]);
    $pdo->prepare('UPDATE border_crossings SET trip_id = ?, vehicle_id = ?, driver_id = ? WHERE id IN (?, ?)')->execute([$tripId, $truckId, $driverId, $kenyaFile, $rwandaFile]);
    $deliveryId = Models\LogisticsData::save('deliveries', null, [
        'delivery_code' => '', 'trip_id' => (string) $tripId, 'shipment_id' => (string) $shipmentId, 'recipient_name' => 'Eric Mugisha',
        'recipient_phone' => '+250 788 123 456', 'destination' => 'Kigali Hardware Ltd, Gikondo', 'status' => 'loading', 'attempt_number' => '1', 'failure_reason' => 'none',
    ]);
    $test->same(null, Models\Clearance::dispatchGuard($tripId), 'both borders are lodged, so clearance does not hold the truck');

    // The document pack: Grace checks each paper, then Amina.
    foreach (Models\DocumentPack::requiredFor($pdo->query("SELECT * FROM shipments WHERE id = {$shipmentId}")->fetch()) as $type) {
        $paperId = Models\LogisticsData::save('shipment_documents', null, ['shipment_id' => (string) $shipmentId, 'document_type' => $type, 'document_number' => 'SP-' . substr(md5($type), 0, 6)]);
        $scan('shipment_documents', $paperId);
        $as($people['grace']);
        $ok(Models\Workflow::apply('shipment_documents', $paperId, 'verify'), 'Grace: Operations check on ' . $type);
        $as($people['amina']);
        $ok(Models\Workflow::apply('shipment_documents', $paperId, 'verify'), 'Amina: Customs compliance on ' . $type);
    }

    // ------------------------------------------ 8. Release: Clearance, then Operations
    $as($people['grace']);
    $own = Models\Workflow::apply('trips', $tripId, 'approve');
    $test->assert(!$own['ok'], 'Grace raised the trip, so she cannot sign its release: ' . $own['message']);
    $as($people['amina']);
    $ok(Models\Workflow::apply('trips', $tripId, 'approve'), 'Amina: Clearance ready');
    $as($people['peter']);
    $ok(Models\Workflow::apply('trips', $tripId, 'approve'), 'Peter: Release the truck');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'Your trip is approved'", [$people['samuel']['id']]) === 1, 'Samuel is told the trip is approved');

    // ------------------------------------------- 9. Grace: the pre-dispatch check
    $as($people['grace']);
    $checkInput = ['shipment_id' => (string) $shipmentId, 'cargo_loaded' => '1', 'quantity_verified' => '1', 'packaging_verified' => '1', 'status' => 'ready',
                   'checked_by' => (string) $people['grace']['id'], 'checked_at' => date('Y-m-d H:i:s')];
    $errors = Models\LogisticsData::validate('pre_dispatch_checks', $checkInput);
    $test->same([], $errors, 'the dispatch check can be marked ready');
    Models\LogisticsData::save('pre_dispatch_checks', null, $checkInput);

    // ------------------------------------------------- 10. Samuel: start the trip
    $as($people['samuel']);
    $readiness = Models\DispatchReadiness::forTrip($tripId);
    $test->assert($readiness['ready'], 'My trips shows Start trip: ' . implode('; ', $readiness['failures']));
    $pdo->prepare('UPDATE trips SET start_mileage = 412150 WHERE id = ?')->execute([$tripId]);
    $ok(Models\Workflow::apply('trips', $tripId, 'dispatch'), 'Samuel: Start trip');
    $test->same('inland', (string) $one('SELECT status FROM freight_jobs WHERE id = ?', [$jobId]), 'the job is On the truck by itself');

    // ------------------------------------------------- 11. On the road: Malaba
    $at = static fn (float $lat, float $lng) => Models\Tracking::record($truckId, $lat, $lng, null, 'phone', 55.0);
    $test->assert(str_contains($at(0.6365, 34.2805)['message'], 'Arrived at Malaba'), 'GPS records the arrival at Malaba');
    $fuelId = Models\LogisticsData::save('fuel', null, ['reference_code' => '', 'vehicle_id' => (string) $truckId, 'trip_id' => (string) $tripId, 'litres' => '300', 'unit_price' => '5200', 'currency' => 'UGX', 'odometer' => '413020', 'purchased_at' => date('Y-m-d H:i:s'), 'fuel_type' => 'diesel', 'is_full_tank' => '1']);
    $test->assert($fuelId > 0, 'Samuel records fuel in UGX');
    $expenseId = Models\LogisticsData::save('expenses', null, ['reference_code' => '', 'category' => 'Allowance', 'amount' => '150', 'currency' => 'USD', 'expense_date' => date('Y-m-d'), 'trip_id' => (string) $tripId, 'status' => 'pending', 'payment_method' => 'cash']);

    $as($people['amina']);
    $ok(Models\Workflow::apply('crossings', $kenyaFile, 'start_review'), 'Amina: Start review at Malaba');
    $ok(Models\Workflow::apply('crossings', $kenyaFile, 'assess_duties'), 'Amina: Assess duties');
    Models\LogisticsData::saveLines('crossings', $kenyaFile, [['charge_type' => 'Transit bond', 'description' => 'RCTG cancellation', 'receipt_no' => 'KRA-R-7712', 'amount' => '5000', 'paid_for' => 'us', 'status' => 'paid']]);
    $ok(Models\Workflow::apply('crossings', $kenyaFile, 'request_payment'), 'Amina: Request payment');
    $ok(Models\Workflow::apply('crossings', $kenyaFile, 'clear'), 'Amina: Mark cleared at Malaba');
    Models\LogisticsData::save('clearance_releases', null, ['crossing_id' => (string) $kenyaFile, 'release_number' => 'KE-EXIT-55120', 'release_date' => date('Y-m-d'), 'released_by' => (string) $people['amina']['id']]);
    $ok(Models\Workflow::apply('crossings', $kenyaFile, 'release'), 'Amina: Mark released');
    $test->assert(str_contains($at(0.70, 34.10)['message'], 'Left Malaba'), 'GPS records leaving Malaba');

    // The allowance: Operations, then Finance.
    $as($people['peter']);
    $ok(Models\Workflow::apply('expenses', $expenseId, 'approve'), 'Peter: Operations check on the allowance');
    $as($people['ruth']);
    $ok(Models\Workflow::apply('expenses', $expenseId, 'approve'), 'Ruth: Finance approval');
    $test->same('approved', (string) $one('SELECT status FROM expenses WHERE id = ?', [$expenseId]), 'the USD 150 allowance is approved');

    // ------------------------------------------------- 12. Gatuna: duties and release
    $as($people['samuel']);
    $test->assert(str_contains($at(-1.4302, 30.0112)['message'], 'Arrived at Gatuna'), 'GPS records the arrival at Gatuna');
    $as($people['jean']);
    $ok(Models\Workflow::apply('crossings', $rwandaFile, 'start_review'), 'Jean: Start review');
    $ok(Models\Workflow::apply('crossings', $rwandaFile, 'assess_duties'), 'Jean: Assess duties');
    Models\LogisticsData::saveLines('crossings', $rwandaFile, [
        ['charge_type' => 'Customs duty', 'description' => 'Import duty 25%', 'receipt_no' => '', 'amount' => '4500000', 'paid_for' => 'customer', 'status' => 'pending'],
        ['charge_type' => 'Import VAT', 'description' => 'VAT 18%', 'receipt_no' => '', 'amount' => '3240000', 'paid_for' => 'customer', 'status' => 'pending'],
        ['charge_type' => 'Clearing agent fee', 'description' => 'Gatuna processing', 'receipt_no' => '', 'amount' => '60000', 'paid_for' => 'us', 'status' => 'pending'],
    ]);
    $ok(Models\Workflow::apply('crossings', $rwandaFile, 'request_payment'), 'Jean: Request payment');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE role_key = 'finance' AND title LIKE 'Duties to pay:%'") >= 1, 'Finance is told the duties are due');
    $held = Models\Workflow::apply('crossings', $rwandaFile, 'clear');
    $test->assert(!$held['ok'], 'customs does not clear before the duties are paid and the receipt is checked');

    $as($people['ruth']);
    $pdo->prepare("UPDATE border_charges SET status = 'paid', receipt_no = 'RRA-PAY-889120' WHERE crossing_id = ?")->execute([$rwandaFile]);
    $as($people['jean']);
    $receipt = (int) array_search('Tax payment receipt', $rwandaPapers, true);
    Models\LogisticsData::save('clearance_documents', $receipt, ['crossing_id' => (string) $rwandaFile, 'document_type' => 'Tax payment receipt', 'document_no' => 'RRA-PAY-889120']);
    $scan('border_documents', $receipt);
    $ok(Models\Workflow::apply('clearance_documents', $receipt, 'verify'), 'Jean verifies the tax receipt');
    $ok(Models\Workflow::apply('crossings', $rwandaFile, 'clear'), 'Jean: Mark cleared at Gatuna');
    $test->assert((int) $one("SELECT COUNT(*) FROM disbursements WHERE crossing_id = ? AND currency = 'RWF' AND amount = 7740000", [$rwandaFile]) === 1, 'duty and VAT become an RWF 7,740,000 disbursement to recharge');
    Models\LogisticsData::save('clearance_releases', null, ['crossing_id' => (string) $rwandaFile, 'release_number' => 'RRA-REL-554120', 'release_date' => date('Y-m-d'), 'released_by' => (string) $people['jean']['id']]);
    $ok(Models\Workflow::apply('crossings', $rwandaFile, 'release'), 'Jean: Mark released');
    $as($people['samuel']);
    $at(-1.60, 30.05);

    // ------------------------------------------------- 13. Kigali: hand over
    $test->assert(str_contains(Models\TripProgress::stop($tripId, (int) $one("SELECT id FROM trip_stops WHERE trip_id = ? AND stop_type = 'dropoff'", [$tripId]), 'arrive', ['condition' => 'fine'])['message'], 'Kigali'), 'Samuel: I have arrived in Kigali');
    $pdo->prepare("UPDATE deliveries SET proof_file = 'deliveries/pod.jpg', recipient_signature = 'deliveries/sign.png', collected_by = 'Eric Mugisha' WHERE id = ?")->execute([$deliveryId]);
    $ok(Models\Workflow::apply('deliveries', $deliveryId, 'complete'), 'Samuel: Hand over → Delivered');
    $test->same('delivered', (string) $one('SELECT status FROM freight_jobs WHERE id = ?', [$jobId]), 'the job is Delivered by itself');
    $test->same('delivered', (string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]), 'the shipment is Delivered');
    $as($people['grace']);
    $ok(Models\Workflow::apply('trips', $tripId, 'complete'), 'Grace: Mark delivered on the trip');
    $test->same('available', (string) $one('SELECT status FROM vehicles WHERE id = ?', [$truckId]), 'the truck is available again');

    // ------------------------------------------------- 14. Ruth and Joseph: money
    $as($people['ruth']);
    $billed = Models\Billing::invoiceTrip($tripId);
    $ok($billed, 'Ruth: Create invoice');
    $usdInvoice = (int) $one("SELECT id FROM invoices WHERE trip_id = ? AND currency = 'USD'", [$tripId]);
    $rwfInvoice = (int) $one("SELECT id FROM invoices WHERE trip_id = ? AND currency = 'RWF'", [$tripId]);
    $test->same(3800.0, (float) $one('SELECT subtotal FROM invoices WHERE id = ?', [$usdInvoice]), 'the USD invoice carries the USD 3,800 freight');
    $test->assert($rwfInvoice > 0, 'the RWF duties are claimed on an RWF invoice of their own');
    $own = Models\Workflow::apply('invoices', $usdInvoice, 'approve');
    $test->assert(!$own['ok'], 'Ruth raised the invoice, so she cannot issue it');
    $as($people['joseph']);
    $ok(Models\Workflow::apply('invoices', $usdInvoice, 'approve'), 'Joseph: Issue the USD invoice');
    $ok(Models\Workflow::apply('invoices', $rwfInvoice, 'approve'), 'Joseph: Issue the RWF invoice');

    $as($people['ruth']);
    $total = (float) $one('SELECT total_amount FROM invoices WHERE id = ?', [$usdInvoice]);
    Models\LogisticsData::save('payments', null, ['payment_code' => '', 'invoice_id' => (string) $usdInvoice, 'amount' => (string) $total, 'currency' => 'USD', 'method' => 'bank_transfer', 'reference' => 'TT-EQB-552190', 'paid_at' => date('Y-m-d')]);
    $test->same('paid', (string) $one('SELECT status FROM invoices WHERE id = ?', [$usdInvoice]), 'the USD invoice is paid');
    $test->assert(Models\Ledger::isBalanced(), 'the books balance');

    $as($people['grace']);
    $ok(Models\Workflow::apply('freight_jobs', $jobId, 'close'), 'Grace: Close the job');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
