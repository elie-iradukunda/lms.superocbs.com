<?php

declare(strict_types=1);

/**
 * ERI specification, sections 13 and 16: the driver asks for support (money
 * becomes an F10 in the chain), fills the delivery note with the shortage
 * worked out, and hands the truck over; the OM confirms.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_driver_app', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\DeliveryNotes;
use Models\DriverSupport;
use Models\Fleet;
use Models\LogisticsData;
use Models\RequestForms;
use Models\TripLife;

$test = new TestRun('ERI driver app');
$test->stopOn();
$accounts = (new Models\UserRepository())->activeLoginAccounts();
Models\Settings::put('signature_required_to_approve', '0');
test_sign_in($accounts['logistics_manager'], 'logistics_manager');

// A truck, a driver and a trip already on the road.
$truck = LogisticsData::save('vehicles', null, ['plate_number' => 'RAK 300A', 'fleet' => 'tanker', 'vehicle_type' => 'Tanker', 'max_payload_t' => '30', 'cargo_tank_l' => '36000', 'status' => 'available', 'mileage' => '50000'], []);
$driverId = LogisticsData::save('drivers', null, ['full_name' => 'MUGISHA PAUL', 'nationality' => 'Rwandan', 'phone' => '+250 788 300 300', 'payment_method' => 'momo', 'momo_number' => '0788300300', 'momo_name' => 'MUGISHA PAUL', 'status' => 'active', 'default_vehicle_id' => (string) $truck], []);
LogisticsData::takeOneTimePassword();
$customer = LogisticsData::save('customers', null, ['customer_name' => 'Jambo oil factory', 'customer_type' => 'corporate', 'status' => 'active', 'payment_terms_days' => '30', 'currency' => 'RWF'], []);
$pdo->exec("INSERT INTO trips (company_id, reference_code, customer_id, vehicle_id, driver_id, pickup_location, destination, status, cargo_type, cargo_quantity, loaded_quantity, start_mileage)
            VALUES (1, 'TRP-TEST-1', {$customer}, {$truck}, {$driverId}, 'Mombasa', 'Kigali', 'offloading', 'litres', 36000, 36000, 50000)");
$tripId = (int) $pdo->lastInsertId();
$pdo->exec("UPDATE vehicles SET status = 'offloading' WHERE id = {$truck}");
$pdo->exec("UPDATE drivers SET status = 'on_trip' WHERE id = {$driverId}");

$driver = Fleet::driver($driverId);
$asDriver = static fn () => test_sign_in(['id' => $driver['user_id'], 'name' => $driver['full_name'], 'email' => 'x'], 'driver');

// ---------------------------------------------------------------- support
$asDriver();
$test->throws(static fn () => DriverSupport::submit($driverId, $tripId, ['type' => 'money', 'details' => 'Tyre burst']), 'money support needs an amount');
$supportId = DriverSupport::submit($driverId, $tripId, ['type' => 'money', 'details' => 'Tyre burst near Malaba', 'amount' => '80000', 'currency' => 'RWF', 'location' => 'Malaba'], ['trip_events/p.jpg']);
$test->assert((int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE role_key = 'logistics_manager' AND link_route = 'support_requests'")->fetchColumn() >= 1, 'the OM is told at once');

test_sign_in($accounts['logistics_manager'], 'logistics_manager');
DriverSupport::decide($supportId, true, 'Approved, buy at Malaba', 'finance');
$support = DriverSupport::find($supportId);
$test->same('in_progress', $support['status'], 'approved and forwarded to Finance');
$f10 = RequestForms::find((int) $support['request_id']);
$test->assert($f10 !== null && $f10['form_code'] === 'F10' && $f10['status'] === 'sent', 'the money became an F10 sent into the chain');
$test->same('80000.00', $f10['total_amount'], 'the F10 carries the amount asked for');
$lines = RequestForms::lines((int) $f10['id']);
$test->same($tripId, (int) $lines[0]['trip_id'], 'the F10 lands on the trip\'s cost');
$test->same('driver', $lines[0]['payee_type'], 'the driver is paid');

test_sign_in($accounts['finance'], 'finance');
DriverSupport::resolve($supportId, 'Paid by MoMo once authorized');
$test->same('resolved', DriverSupport::find($supportId)['status'], 'the person it went to resolves it');

test_sign_in($accounts['logistics_manager'], 'logistics_manager');
$otherId = DriverSupport::submit($driverId, $tripId, ['type' => 'breakdown', 'details' => 'Engine warning', 'urgency' => 'urgent'], []);
$test->throws(static fn () => DriverSupport::decide($otherId, false, ''), 'a rejection needs a reason');
DriverSupport::decide($otherId, false, 'Drive to the next garage, it is minor');
$test->same('rejected', DriverSupport::find($otherId)['status'], 'the OM can refuse with a reason');

// ---------------------------------------------------------- delivery note
$asDriver();
$test->throws(static fn () => DeliveryNotes::submit($tripId, ['loaded' => 36000, 'delivered' => 35900, 'received_by' => 'Store keeper'], null), 'the stamped copy photo is required');
$f8 = DeliveryNotes::submit($tripId, ['loaded' => 36000, 'delivered' => 35920, 'received_by' => 'Jean, store keeper'], 'delivery_notes/copy.jpg');
$note = RequestForms::find($f8);
$test->same('F8', $note['form_code'], 'an F8 delivery note is created');
$test->same(80.0, (float) $note['payload']['shortage'], 'shortage = loaded − delivered (36,000 − 35,920 = 80)');
$test->same('sent', $note['status'], 'sent to the OM to check');
$trip = TripLife::find($tripId);
$test->same('returning', $trip['status'], 'delivery note done: empty, returning');
$test->same('returning', Fleet::vehicle($truck)['status'], 'the truck shows Empty, returning');
$test->assert((int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE title = 'Delivery note with shortage'")->fetchColumn() >= 1, 'a shortage is reported to the OM and Finance');

test_sign_in($accounts['logistics_manager'], 'logistics_manager');
RequestForms::confirm($f8);
$test->same('closed', RequestForms::find($f8)['status'], 'the OM checks the delivery note');

// --------------------------------------------------------------- handover
$asDriver();
TripLife::act($tripId, 'arrive_base', ['odometer' => 50900]);
$test->throws(static fn () => Models\Handover::submit($truck, $tripId, $driverId, ['parked_at' => 'Yard', 'odometer' => 50950, 'fuel_level' => 'half', 'key_received_by' => 'Guard'], ['front' => 'a.jpg']), 'a handover needs at least two photos');
Models\Handover::submit($truck, $tripId, $driverId, ['parked_at' => 'Kigali yard', 'odometer' => 50950, 'fuel_level' => 'half', 'key_received_by' => 'Guard Eric'], ['front' => 'a.jpg', 'back' => 'b.jpg']);
$f14 = (int) $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F14' ORDER BY id DESC LIMIT 1")->fetchColumn();

test_sign_in($accounts['logistics_manager'], 'logistics_manager');
RequestForms::sendBack($f14, 'Photo of the left side is missing');
$test->same('rejected', $pdo->query('SELECT status FROM vehicle_handovers ORDER BY id DESC LIMIT 1')->fetchColumn(), 'a rejected handover goes back to the driver');
$test->same('arrived', TripLife::find($tripId)['status'], 'the trip stays open');

$asDriver();
Models\Handover::submit($truck, $tripId, $driverId, ['parked_at' => 'Kigali yard', 'odometer' => 50950, 'fuel_level' => 'half', 'key_received_by' => 'Guard Eric'], ['front' => 'a.jpg', 'back' => 'b.jpg', 'left' => 'c.jpg']);
$f14 = (int) $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F14' AND status = 'sent' ORDER BY id DESC LIMIT 1")->fetchColumn();
test_sign_in($accounts['logistics_manager'], 'logistics_manager');
RequestForms::confirm($f14);
$trip = TripLife::find($tripId);
$test->same('closed', $trip['status'], 'confirmed: the trip is closed');
$test->same(950, (int) $trip['km_driven'], 'kilometres from the handover odometer');
$test->same('available', Fleet::vehicle($truck)['status'], 'the truck is Available');
$test->same('active', Fleet::driver($driverId)['status'], 'the driver is free');

$test->finish();
