<?php

declare(strict_types=1);

/**
 * ERI specification, sections 3, 5 and 6: companies, trucks, trailers,
 * drivers and their documents, through the same forms people use.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_fleet', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\Fleet;
use Models\LogisticsData;

$test = new TestRun('ERI fleet and drivers');
$test->stopOn();
$accounts = (new Models\UserRepository())->activeLoginAccounts();
test_sign_in($accounts['logistics_manager'], 'logistics_manager');

$save = static function (string $module, array $input, ?int $id = null): array {
    $errors = LogisticsData::validate($module, $input, [], $id);
    if ($errors !== []) {
        return ['id' => null, 'errors' => $errors];
    }

    return ['id' => LogisticsData::save($module, $id, $input, []), 'errors' => []];
};

// ------------------------------------------------------------- the truck
$tanker = ['plate_number' => 'rak 205d', 'fleet' => 'tanker', 'vehicle_type' => 'Tanker', 'max_payload_t' => '30', 'status' => 'available', 'mileage' => '120000'];
$result = $save('vehicles', $tanker);
$test->assert($result['id'] === null && str_contains(implode(' ', $result['errors']), 'cargo tank'), 'a tanker without its tank capacity is refused');

$result = $save('vehicles', $tanker + ['cargo_tank_l' => '36000', 'normal_l_per_100km' => '38']);
$test->assert($result['id'] !== null, 'a complete tanker is registered: ' . implode(' ', $result['errors']));
$truckId = (int) $result['id'];
$truck = Fleet::vehicle($truckId);
$test->same('RAK 205D', $truck['plate_number'], 'the plate is written in one standard format');
$test->same(1, (int) $truck['company_id'], 'the truck belongs to the OM\'s company');
$test->same(127000, (int) $truck['next_service_km'], 'the next service is one interval after the odometer it arrived with');

$result = $save('vehicles', ['plate_number' => 'RAD 111A', 'fleet' => 'container', 'vehicle_type' => 'Truck', 'max_payload_t' => '28', 'status' => 'available']);
$test->assert($result['id'] === null, 'a container truck must say which container sizes it may carry');

$result = $save('vehicles', ['plate_number' => 'RAD 111A', 'fleet' => 'container', 'vehicle_type' => 'Truck', 'max_payload_t' => '28', 'container_sizes' => '1x20,1x40,2x20', 'status' => 'off_road']);
$test->assert($result['id'] === null && str_contains(implode(' ', $result['errors']), 'off road'), 'off road needs a reason');

// --------------------------------------------------------------- service
$service = Fleet::serviceState($truck);
$test->same('ok', $service['state'], 'a truck just registered is not due');
$pdo->exec("UPDATE vehicles SET mileage = 126600 WHERE id = {$truckId}");
$test->same('warning', Fleet::serviceState(Fleet::vehicle($truckId))['state'], 'within 500 km of the service it warns (6,500 of 7,000)');
$pdo->exec("UPDATE vehicles SET mileage = 127050 WHERE id = {$truckId}");
$test->same('due', Fleet::serviceState(Fleet::vehicle($truckId))['state'], 'past 7,000 km the service is due');
Fleet::recordService($truckId, 127050);
$test->same(134050, (int) Fleet::vehicle($truckId)['next_service_km'], 'after a service the next is 7,000 km later');

// --------------------------------------------------------------- trailer
$result = $save('trailers', ['trailer_number' => 'rl 9465 /bx', 'trailer_type' => 'tanker', 'status' => 'available', 'vehicle_id' => (string) $truckId]);
$test->assert($result['id'] === null, 'a tanker trailer needs its tank capacity');
$result = $save('trailers', ['trailer_number' => 'rl 9465 /bx', 'trailer_type' => 'tanker', 'tank_capacity_l' => '36000', 'status' => 'available', 'vehicle_id' => (string) $truckId]);
$trailerId = (int) $result['id'];
$test->same($trailerId, (int) Fleet::vehicle($truckId)['trailer_id'], 'coupling the trailer to the truck links them both ways');

// ------------------------------------------------------------- documents
$problems = Fleet::documentProblems('vehicle', $truckId, 'RAK 205D', 'tanker', date('Y-m-d', strtotime('+10 days')), true);
$test->assert(count($problems) >= 6, 'a truck with no documents has every required one missing');

$inAYear = date('Y-m-d', strtotime('+1 year'));
foreach (array_keys(Fleet::documentTypeOptions('vehicle')) as $type) {
    $pdo->prepare("INSERT INTO vehicle_documents (document_code, owner_type, vehicle_id, document_type, document_number, expires_on, document_file, status) VALUES (?, 'vehicle', ?, ?, 'X1', ?, 'vehicle_documents/x.pdf', 'valid')")
        ->execute(['DOC-' . $type, $truckId, $type, $inAYear]);
}
$test->same([], Fleet::documentProblems('vehicle', $truckId, 'RAK 205D', 'tanker', date('Y-m-d', strtotime('+10 days')), true), 'with every document in date, nothing stops the truck');

$pdo->exec("UPDATE vehicle_documents SET expires_on = DATE_ADD(CURDATE(), INTERVAL 5 DAY) WHERE vehicle_id = {$truckId} AND document_type = 'insurance'");
$problems = Fleet::documentProblems('vehicle', $truckId, 'RAK 205D', 'tanker', date('Y-m-d', strtotime('+10 days')), true);
$test->assert(count($problems) === 1 && str_contains($problems[0], 'before the trip is expected back'), 'insurance that ends during the trip stops it');

$result = $save('vehicle_documents', ['owner_type' => 'trailer', 'document_type' => 'tank_calibration', 'document_number' => 'TC-9', 'expires_on' => date('Y-m-d', strtotime('+20 days'))]);
$test->assert($result['id'] === null, 'a trailer document must name its trailer');
$result = $save('vehicle_documents', ['owner_type' => 'trailer', 'trailer_id' => (string) $trailerId, 'document_type' => 'tank_calibration', 'document_number' => 'TC-9', 'expires_on' => date('Y-m-d', strtotime('+20 days')), 'existing_document_file' => 'vehicle_documents/tc9.pdf']);
$test->same([], $result['errors'], 'a trailer document with its scan is accepted');
$doc = $pdo->query('SELECT * FROM vehicle_documents WHERE id = ' . (int) $result['id'])->fetch();
$test->same('expiring', $doc['status'], 'a document 20 days from expiry is marked expiring');
$test->assert($doc['vehicle_id'] === null && (int) $doc['trailer_id'] === $trailerId, 'a trailer document belongs to the trailer only');

// ---------------------------------------------------------------- driver
$driver = ['full_name' => 'BYIRINGIRO EMMANUEL', 'nationality' => 'Rwandan', 'national_id' => '1199080012345678', 'phone' => '+250 788 123 456', 'payment_method' => 'momo', 'status' => 'active', 'fleet' => 'tanker'];
$result = $save('drivers', $driver);
$test->assert($result['id'] === null && str_contains(implode(' ', $result['errors']), 'MoMo'), 'a MoMo driver needs the number and the registered name');

$result = $save('drivers', $driver + ['momo_number' => '0788123456', 'momo_name' => 'BYIRINGIRO EMMANUEL', 'default_vehicle_id' => (string) $truckId]);
$test->assert($result['id'] !== null, 'a complete driver is registered: ' . implode(' ', $result['errors']));
$driverId = (int) $result['id'];
$password = LogisticsData::takeOneTimePassword();
$test->assert($password !== null, 'saving a new driver creates a one-time password');
$row = Fleet::driver($driverId);
$test->assert(!empty($row['user_id']), 'the driver has a login');
$login = (new Models\UserRepository())->findActiveByEmail('0788 123 456');
$test->assert($login !== null && (int) $login['id'] === (int) $row['user_id'] && password_verify((string) $password, (string) $login['password_hash']), 'the driver signs in with the phone number and the one-time password');
$test->same('driver', $login['role_key'] ?? '', 'the login holds the Driver role');
$test->same($driverId, (int) Fleet::vehicle($truckId)['assigned_driver_id'], 'the default truck shows the driver');

$result = $save('drivers', ['full_name' => 'Juma Hamisi', 'nationality' => 'Tanzanian', 'phone' => '+255 754 999 111', 'payment_method' => 'cash', 'status' => 'active']);
$test->assert($result['id'] === null && str_contains(implode(' ', $result['errors']), 'passport'), 'a foreign driver needs a passport number');
$result = $save('drivers', ['full_name' => 'Juma Hamisi', 'nationality' => 'Tanzanian', 'passport_number' => 'TZ123', 'phone' => '+250 788 123 456', 'payment_method' => 'cash', 'status' => 'active']);
$test->assert($result['id'] === null && str_contains(implode(' ', $result['errors']), 'phone'), 'two drivers cannot share a phone number');
LogisticsData::takeOneTimePassword();

$problems = Fleet::documentProblems('driver', $driverId, 'Emmanuel', 'tanker', date('Y-m-d', strtotime('+10 days')), true);
$test->assert(count($problems) === 3, 'a tanker driver crossing borders needs a licence, passport and hazardous-goods training');

// -------------------------------------------------------- MoMo change
$result = $save('driver_momo_changes', ['driver_id' => (string) $driverId, 'new_number' => '0788123456', 'new_name' => 'X']);
$test->assert($result['id'] === null, 'a change to the same number is refused');
$result = $save('driver_momo_changes', ['driver_id' => (string) $driverId, 'new_number' => '0788654321', 'new_name' => 'BYIRINGIRO E.']);
$changeId = (int) $result['id'];
$test->same('0788123456', Fleet::driver($driverId)['momo_number'], 'the new number is not used before the OM approves');
$outcome = Models\Workflow::apply('driver_momo_changes', $changeId, 'approve');
$test->assert($outcome['ok'], 'the OM approves the change: ' . $outcome['message']);
$test->same('0788654321', Fleet::driver($driverId)['momo_number'], 'once approved the driver is paid on the new number');

// -------------------------------------------------------- companies
$tanzania = $pdo->query("SELECT id, full_name AS name, email FROM users WHERE email = 'shomari@itec.rw'")->fetch(\PDO::FETCH_ASSOC);
test_sign_in($tanzania, 'logistics_manager');
$listing = LogisticsData::listing('vehicles', [], current_context());
$test->same(0, $listing['total'], 'ERI Tanzania does not see ERI Rwanda\'s trucks');
test_sign_in($accounts['logistics_manager'], 'logistics_manager');
$listing = LogisticsData::listing('vehicles', [], current_context());
$test->same(1, $listing['total'], 'ERI Rwanda sees its own truck');

// ---------------------------------------------------------- deleting
$pdo->exec("INSERT INTO trips (company_id, reference_code, pickup_location, destination, vehicle_id, driver_id, status) VALUES (1, 'TRP-T1', 'Kigali', 'Mombasa', {$truckId}, {$driverId}, 'in_transit')");
$test->throws(static fn () => LogisticsData::remove('vehicles', $truckId, 'test'), 'a truck on a trip cannot be removed');
$result = $save('drivers', array_merge($driver, ['momo_number' => '0788654321', 'momo_name' => 'X', 'status' => 'left', 'left_reason' => 'Resigned']), $driverId);
$test->assert($result["id"] === null && str_contains(implode(" ", $result["errors"]), "on trip"), "a driver on a trip cannot be set to Left: " . implode(" | ", $result["errors"]));

$test->finish();
