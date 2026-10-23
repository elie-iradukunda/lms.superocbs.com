<?php

declare(strict_types=1);

/**
 * ERI specification, sections 7 to 11 end to end: a route with its rate
 * card, two trucks planned on one F1, the chain Prepared -> Verified ->
 * Authorized -> Paid, the MoMo batch, the driver confirming the money,
 * departure, the Mombasa top-up, loading, delivery, handover and close.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_trip_money', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\Fleet;
use Models\LogisticsData;
use Models\Payments;
use Models\RequestForms;
use Models\TripLife;

$test = new TestRun('ERI trip money');
$test->stopOn();
$accounts = (new Models\UserRepository())->activeLoginAccounts();
$pdo->exec("UPDATE company_settings SET setting_value = '0' WHERE setting_key = 'signature_required_to_approve'");
Models\Settings::put('signature_required_to_approve', '0');

$as = static function (string $role) use ($accounts): void {
    test_sign_in($accounts[$role], $role);
};
$save = static function (string $module, array $input, ?int $id = null) use ($test): ?int {
    $errors = LogisticsData::validate($module, $input, [], $id);
    $test->same([], $errors, "{$module} saves");
    if ($errors !== []) {
        return null;
    }
    $saved = LogisticsData::save($module, $id, $input, []);
    LogisticsData::takeOneTimePassword();

    return $saved;
};

// ------------------------------------------------------------- set-up (Admin)
$as('super_admin');
$kenya = $save('suppliers', ['supplier_name' => 'ERI Kenya (Mombasa fuel)', 'status' => 'active', 'payment_terms_days' => '30']);
$station = $save('suppliers', ['supplier_name' => 'Kigali Fuel Station', 'status' => 'active', 'payment_terms_days' => '30']);
$routeId = $save('routes', ['route_code' => 'KGL-MBA-KGL', 'route_name' => 'Kigali – Mombasa – Kigali', 'origin' => 'Kigali', 'turnaround_point' => 'Mombasa', 'destination' => 'Kigali', 'round_trip' => '1', 'crosses_border' => '1', 'is_active' => '1', 'expected_days' => '10', 'max_return_days' => '3', 'total_km' => '3000']);
$today = date('Y-m-d', strtotime('-1 day'));
$save('route_rates', ['route_id' => (string) $routeId, 'rate_item' => 'fuel', 'fleet' => 'all', 'fuel_point' => 'Kigali', 'fuel_on' => 'departure', 'supplier_id' => (string) $station, 'quantity' => '1200', 'currency' => 'RWF', 'valid_from' => $today, 'is_active' => '1']);
$save('route_rates', ['route_id' => (string) $routeId, 'rate_item' => 'fuel', 'fleet' => 'all', 'fuel_point' => 'Mombasa', 'fuel_on' => 'turnaround', 'supplier_id' => (string) $kenya, 'quantity' => '150', 'currency' => 'RWF', 'valid_from' => $today, 'is_active' => '1']);
$save('route_rates', ['route_id' => (string) $routeId, 'rate_item' => 'road_toll', 'fleet' => 'all', 'amount' => '510000', 'currency' => 'RWF', 'valid_from' => $today, 'is_active' => '1']);
$save('route_rates', ['route_id' => (string) $routeId, 'rate_item' => 'mileage', 'fleet' => 'all', 'amount' => '500000', 'currency' => 'RWF', 'valid_from' => $today, 'is_active' => '1']);

// ----------------------------------------------- trucks and drivers (the OM)
$as('logistics_manager');
$inAYear = date('Y-m-d', strtotime('+1 year'));
$trucks = [];
$drivers = [];
foreach ([['RAK 205D', 'BYIRINGIRO EMMANUEL', '0788100001'], ['RAK 206D', 'NSENGIYUMVA JEAN', '0788100002']] as $i => [$plate, $name, $momo]) {
    $truckId = $save('vehicles', ['plate_number' => $plate, 'fleet' => 'tanker', 'vehicle_type' => 'Tanker', 'max_payload_t' => '30', 'cargo_tank_l' => '36000', 'normal_l_per_100km' => '40', 'status' => 'available', 'mileage' => '100000']);
    $driverId = $save('drivers', ['full_name' => $name, 'nationality' => 'Rwandan', 'phone' => '+250 788 20000' . $i, 'payment_method' => 'momo', 'momo_number' => $momo, 'momo_name' => $name, 'status' => 'active', 'fleet' => 'tanker']);
    foreach (array_keys(Fleet::documentTypeOptions('vehicle')) as $type) {
        $pdo->prepare("INSERT INTO vehicle_documents (company_id, document_code, owner_type, vehicle_id, document_type, document_number, expires_on, document_file, status) VALUES (1, ?, 'vehicle', ?, ?, 'N', ?, 'x.pdf', 'valid')")
            ->execute(["D-{$truckId}-{$type}", $truckId, $type, $inAYear]);
    }
    foreach (array_keys(Fleet::documentTypeOptions('driver')) as $type) {
        $pdo->prepare("INSERT INTO driver_documents (company_id, driver_id, document_type, document_number, expiry_date, document_file, status) VALUES (1, ?, ?, 'N', ?, 'x.pdf', 'valid')")
            ->execute([$driverId, $type, $inAYear]);
    }
    $trucks[] = $truckId;
    $drivers[] = $driverId;
}

// --------------------------------------------------------------- planning
$plan = [
    'route_id' => $routeId, 'cargo_type' => 'litres', 'cargo_quantity' => '35000', 'departure_at' => date('Y-m-d H:i:s'),
    'rows' => [
        ['vehicle_id' => $trucks[0], 'driver_id' => $drivers[0]],
        ['vehicle_id' => $trucks[1], 'driver_id' => $drivers[1]],
    ],
];
$bad = $plan;
$bad['cargo_quantity'] = '40000';
$test->assert(str_contains(implode(' ', TripLife::problems($bad)), 'more than the tank holds'), 'a load above the tank is refused (control 3)');
$bad = $plan;
$bad['rows'][1]['driver_id'] = $drivers[0];
$test->assert(str_contains(implode(' ', TripLife::problems($bad)), 'twice'), 'one driver cannot drive two trucks');
$pdo->exec("UPDATE vehicles SET mileage = 108000 WHERE id = {$trucks[1]}");
$test->assert(str_contains(implode(' ', TripLife::problems($plan)), 'due for service'), 'a truck due for service needs a reason');
$plan['rows'][1]['service_due_reason'] = 'Booked for service on return';
$test->same([], TripLife::problems($plan), 'the plan passes every check');

$result = TripLife::plan($plan);
$test->same(2, count($result['trip_ids']), 'two trips are created');
$test->same(1, count($result['request_ids']), 'both trucks go on one F1');
$f1 = RequestForms::find($result['request_ids'][0]);
$test->same('sent', $f1['status'], 'the F1 is sent into the chain, prepared by the OM');
$test->same('2020000.00', $f1['total_amount'], 'F1 = 2 × (510,000 road toll + 500,000 mileage)');
$test->same('2400.00', $f1['total_quantity'], 'F1 carries 2 × 1,200 L of fuel');
$test->assert(str_starts_with((string) $f1['request_no'], '001/'), 'the F1 is numbered: ' . $f1['request_no']);
$trip = TripLife::find($result['trip_ids'][0]);
$test->same('planned', $trip['status'], 'the trip waits for its money');
$test->same('assigned', Fleet::vehicle($trucks[0])['status'], 'the truck is Assigned');

// A second F1 for the same trip would pay road toll twice (control 5).
$test->throws(static function () use ($trip): void {
    $id = RequestForms::create(['form_code' => 'F1', 'title' => 'Again'], [['item_code' => 'road_toll', 'trip_id' => $trip['id'], 'vehicle_id' => $trip['vehicle_id'], 'amount' => 510000, 'payee_type' => 'driver', 'payee_name' => 'X', 'payment_method' => 'cash']]);
    RequestForms::send($id);
}, 'road toll is paid once per trip');

// The OM cannot verify their own request, and cannot depart before the money.
$test->throws(static fn () => RequestForms::verify($f1['id']), 'the OM cannot verify (control 10)');
$test->throws(static fn () => TripLife::act($trip['id'], 'depart'), 'no departure before the money (control 1)');

// ---------------------------------------------------------- the chain
$as('finance');
$inbox = RequestForms::inbox();
$test->same(1, count(array_filter($inbox, static fn (array $r): bool => (int) $r['id'] === (int) $f1['id'])), 'the F1 waits in the Finance Manager\'s inbox');
RequestForms::sendBack($f1['id'], 'Mileage for RAK 206D: check the rate');
$test->same('returned', RequestForms::find($f1['id'])['status'], 'rejected with a reason, it returns to the OM');

$as('logistics_manager');
RequestForms::send($f1['id']);
$f1 = RequestForms::find($f1['id']);
$test->same(2, (int) $f1['version'], 'resent as version 2; every version is kept');

$as('finance');
RequestForms::verify($f1['id'], 'Checked against the rate card');
$entry = $pdo->query("SELECT * FROM gl_journal_entries WHERE source_type = 'request_v2' AND source_id = {$f1['id']}")->fetch();
$test->assert($entry !== false, 'verification posts the books');
$sums = $pdo->query("SELECT SUM(debit) d, SUM(credit) c FROM gl_journal_lines WHERE entry_id = {$entry['id']}")->fetch();
$test->same($sums['d'], $sums['c'], 'the entry balances');
$test->same('2020000.00', $sums['d'], 'road toll and mileage expensed against drivers payable');
$toll = (float) $pdo->query("SELECT SUM(l.debit) FROM gl_journal_lines l INNER JOIN gl_accounts a ON a.id = l.account_id WHERE l.entry_id = {$entry['id']} AND a.account_code = '5210'")->fetchColumn();
$test->same(1020000.0, $toll, 'road toll is on its own account (5210)');
$test->same(1, (int) $entry['company_id'], 'the entry belongs to ERI Rwanda');
$test->same('RWF', $entry['base_currency'], 'kept in the company\'s base currency');

$as('management');
RequestForms::sendBack($f1['id'], 'Hold until Monday');
$reversed = $pdo->query("SELECT status FROM gl_journal_entries WHERE id = {$entry['id']}")->fetchColumn();
$test->same('reversed', $reversed, 'sent back by the MD, the entry is reversed automatically');

$as('logistics_manager');
RequestForms::send($f1['id']);
$as('finance');
RequestForms::verify($f1['id']);
$as('management');
RequestForms::authorize($f1['id'], 'OK');
$f1 = RequestForms::find($f1['id']);
$test->same('authorized', $f1['status'], 'authorized by the MD');
$fuelOrders = (int) $pdo->query("SELECT COUNT(*) FROM fuel_orders WHERE request_id = {$f1['id']} AND status = 'ordered'")->fetchColumn();
$test->same(2, $fuelOrders, 'authorizing F1 sends the Kigali fuel orders to the station');

// ---------------------------------------------------------- payment
$as('cashier');
$toPay = Payments::toPay();
$test->same(2, count($toPay), 'the Cashier sees one line per driver on To pay');
$test->same(1010000.0, (float) $toPay[0]['amount'], 'each driver is owed road toll + mileage');
$batchId = Payments::build(array_column($toPay, 'key'));
$batch = Payments::batch($batchId);
$test->assert(str_starts_with((string) $batch['batch_no'], 'BATCH-' . date('Y-m')), 'the batch is numbered ' . $batch['batch_no']);
$csv = Payments::mtnFile($batchId);
$test->assert(str_contains($csv, '250788100001') && str_contains($csv, '1010000'), 'the MTN file lists each MoMo number and amount');
$lines = Payments::batchLines($batchId);
$test->throws(static fn () => Payments::record($batchId, '', 'proof.pdf'), 'the MTN batch number is required');
Payments::record($batchId, 'MTN-778899', 'payment_batches/proof.pdf', [(int) $lines[1]['id'] => 'Number not registered']);
$test->same('partly_paid', RequestForms::find($f1['id'])['status'], 'one driver paid, one failed: partly paid');
$test->same(1, count(Payments::toPay()), 'the failed line stays open on To pay');
$paymentEntry = $pdo->query("SELECT COUNT(*) FROM gl_journal_entries WHERE source_type = 'request_payment'")->fetchColumn();
$test->same(1, (int) $paymentEntry, 'the payment posts for the line paid');

$retry = Payments::build(array_column(Payments::toPay(), 'key'));
Payments::record($retry, 'MTN-778900', 'payment_batches/proof2.pdf');
$test->same('paid', RequestForms::find($f1['id'])['status'], 'paid in full after the retry');

// ---------------------------------------------------------- the driver
$tripOne = $result['trip_ids'][0];
$test->same('planned', TripLife::find($tripOne)['status'], 'paid but not confirmed: not ready yet');
$driverAccount = Fleet::driver($drivers[0]);
test_sign_in(['id' => $driverAccount['user_id'], 'name' => $driverAccount['full_name'], 'email' => 'd@x'], 'driver');
$line = array_values(array_filter(Payments::forDriver($drivers[0]), static fn (array $l): bool => $l['status'] === 'paid'))[0];
Payments::confirmReceipt((int) $line['id'], $drivers[0]);
$test->same('ready', TripLife::find($tripOne)['status'], 'the driver confirmed the money: ready to depart');

TripLife::act($tripOne, 'depart', ['odometer' => 100100]);
$test->same('on_road', Fleet::vehicle($trucks[0])['status'], 'departed: the truck is on the road');
$test->same('on_trip', Fleet::driver($drivers[0])['status'], 'the driver is on trip');

TripLife::act($tripOne, 'arrive_turnaround');
$trip = TripLife::find($tripOne);
$test->assert($trip['return_due_at'] !== null, 'the 3-day return clock starts at Mombasa');
$topUp = $pdo->query("SELECT r.* FROM form_requests r INNER JOIN form_request_lines l ON l.request_id = r.id WHERE r.form_code = 'F2' AND l.trip_id = {$tripOne}")->fetch();
$test->assert($topUp !== false && $topUp['status'] === 'draft', 'the 150 L top-up order is raised for the OM to send');
$test->same((string) $kenya, (string) $topUp['supplier_id'], 'the top-up goes to ERI Kenya');

TripLife::act($tripOne, 'start_loading');
$test->same('loading', Fleet::vehicle($trucks[0])['status'], 'the truck is Loading');
$test->throws(static fn () => TripLife::act($tripOne, 'loaded'), 'loading done needs the quantity loaded');
TripLife::act($tripOne, 'loaded', ['quantity' => 35000]);
TripLife::act($tripOne, 'arrive_customer');
$test->same('offloading', Fleet::vehicle($trucks[0])['status'], 'at the customer: Offloading');
TripLife::deliveryDone($tripOne, 34950);
$test->same('returning', Fleet::vehicle($trucks[0])['status'], 'delivery note done: Empty, returning');
TripLife::act($tripOne, 'arrive_base', ['odometer' => 103050]);

// The station confirms what it gave, then the handover closes the trip.
$as('logistics_manager');
$order = $pdo->query("SELECT id FROM fuel_orders WHERE trip_id = {$tripOne}")->fetchColumn();
Models\FuelOrders::confirmDelivered((int) $order, 1200);
$handoverId = Models\Handover::submit($trucks[0], $tripOne, $drivers[0], ['parked_at' => 'Kigali yard', 'odometer' => 103100, 'fuel_level' => 'quarter', 'key_received_by' => 'Yard keeper'], ['front' => 'h/f.jpg', 'back' => 'h/b.jpg']);
$f14 = $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F14'")->fetchColumn();
RequestForms::confirm((int) $f14);
$trip = TripLife::find($tripOne);
$test->same('closed', $trip['status'], 'the OM confirmed the handover: the trip is closed');
$test->same(3000, (int) $trip['km_driven'], 'kilometres from the odometer: 3,000 km');
$test->same('1200.00', $trip['fuel_given_l'], 'litres given come from the fuel orders');
$test->same(null, $trip['fuel_flag'], '1,200 L for 3,000 km at 40 L/100 km is normal: no flag');
$test->same('1200.00', $trip['fuel_expected_l'], 'expected litres come from the GPS/odometer km and the truck normal consumption');
$test->same('available', Fleet::vehicle($trucks[0])['status'], 'the truck is Available again');
$test->same('active', Fleet::driver($drivers[0])['status'], 'the driver is free for another trip');
$test->same(103100, (int) Fleet::vehicle($trucks[0])['mileage'], 'the odometer moves on');

// The second truck used more than normal: Finance and the OM are told.
$tripTwo = $result['trip_ids'][1];
$pdo->exec("UPDATE trips SET status = 'arrived', start_mileage = 108000 WHERE id = {$tripTwo}");
$pdo->exec("UPDATE fuel_orders SET litres_delivered = 1350, status = 'delivered' WHERE trip_id = {$tripTwo}");
TripLife::close($tripTwo, 111000);
$flag = (string) TripLife::find($tripTwo)['fuel_flag'];
$test->assert(str_contains($flag, 'more than normal'), 'over-consumption is flagged: ' . $flag);
$test->assert((int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE title LIKE 'Fuel check%'")->fetchColumn() >= 1, 'Finance and the OM are alerted');

// The books balance.
$test->assert(Models\Ledger::isBalanced(), 'the ledger balances');

$test->finish();
