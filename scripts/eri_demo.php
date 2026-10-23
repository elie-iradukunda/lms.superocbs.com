<?php

declare(strict_types=1);

/**
 * Demo data for trying the ERI system: a route with its rate card, trucks,
 * trailers, drivers with documents, a customer, suppliers, and trips at
 * different stages (money in approval, ready, on the road, at Mombasa).
 *
 * It goes through the same code the screens use, so everything it creates is
 * what a person would have created. Run it on a test database only:
 *
 *     php scripts/eri_demo.php
 *
 * Every demo login keeps the password "password".
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

require __DIR__ . '/../bootstrap.php';

use Models\Fleet;
use Models\LogisticsData;
use Models\Payments;
use Models\RequestForms;
use Models\TripLife;

$db = Core\Database::connection();
if ((int) $db->query("SELECT COUNT(*) FROM routes WHERE route_code = 'KGL-MBA-KGL'")->fetchColumn() > 0) {
    exit("The demo data is already loaded.\n");
}

$accounts = (new Models\UserRepository())->activeLoginAccounts();
$signIn = static function (string $role) use ($accounts): void {
    $account = $accounts[$role];
    $_SESSION = [
        'logistics_authenticated' => true, 'logistics_role' => $role, 'logistics_user_id' => (int) $account['id'],
        'logistics_user_name' => $account['name'], 'logistics_user_email' => $account['email'], 'logistics_prvg' => 2,
        'logistics_home_company_id' => 1, 'logistics_company_id' => 1,
    ];
    Models\Permission::flush();
};
$save = static function (string $module, array $input): int {
    $errors = LogisticsData::validate($module, $input, [], null);
    if ($errors !== []) {
        throw new RuntimeException($module . ': ' . implode(' ', $errors));
    }
    $id = LogisticsData::save($module, null, $input, []);
    LogisticsData::takeOneTimePassword();

    return $id;
};

$previous = Models\Settings::get('signature_required_to_approve', '1');
Models\Settings::put('signature_required_to_approve', '0');

// ------------------------------------------------------------ Admin set-up
$signIn('super_admin');
$start = date('Y-m-d', strtotime('-30 days'));
$kenya = $save('suppliers', ['supplier_name' => 'ERI Kenya (Mombasa fuel)', 'category' => 'Fuel', 'status' => 'active', 'payment_terms_days' => '30', 'email' => '']);
$station = $save('suppliers', ['supplier_name' => 'Kigali Fuel Station', 'category' => 'Fuel', 'status' => 'active', 'payment_terms_days' => '30']);
$crane = $save('suppliers', ['supplier_name' => 'Mombasa Crane Services', 'category' => 'Crane', 'status' => 'active', 'payment_terms_days' => '15']);
$fitter = $save('suppliers', ['supplier_name' => 'Kigali Tyre Fitters', 'category' => 'Tyres', 'status' => 'active', 'payment_terms_days' => '15']);
$route = $save('routes', ['route_code' => 'KGL-MBA-KGL', 'route_name' => 'Kigali – Mombasa – Kigali', 'origin' => 'Kigali', 'turnaround_point' => 'Mombasa', 'destination' => 'Kigali', 'round_trip' => '1', 'crosses_border' => '1', 'is_active' => '1', 'total_km' => '3060', 'expected_days' => '10', 'max_return_days' => '3']);
LogisticsData::saveLines('routes', $route, [
    ['from_place' => 'Kigali', 'to_place' => 'Rusumo', 'distance_km' => '160', 'expected_hours' => '4'],
    ['from_place' => 'Rusumo', 'to_place' => 'Isaka', 'distance_km' => '400', 'expected_hours' => '10'],
    ['from_place' => 'Isaka', 'to_place' => 'Mombasa', 'distance_km' => '970', 'expected_hours' => '30', 'is_turnaround' => '1'],
    ['from_place' => 'Mombasa', 'to_place' => 'Malaba', 'distance_km' => '930', 'expected_hours' => '28'],
    ['from_place' => 'Malaba', 'to_place' => 'Kigali', 'distance_km' => '600', 'expected_hours' => '20'],
]);
foreach ([
    ['rate_item' => 'fuel', 'fuel_point' => 'Kigali', 'fuel_on' => 'departure', 'supplier_id' => (string) $station, 'quantity' => '1200'],
    ['rate_item' => 'fuel', 'fuel_point' => 'Mombasa', 'fuel_on' => 'turnaround', 'supplier_id' => (string) $kenya, 'quantity' => '150'],
    ['rate_item' => 'road_toll', 'amount' => '510000'],
    ['rate_item' => 'mileage', 'amount' => '500000'],
] as $rate) {
    $save('route_rates', $rate + ['route_id' => (string) $route, 'fleet' => 'all', 'currency' => 'RWF', 'valid_from' => $start, 'is_active' => '1']);
}
$save('service_rates', ['rate_type' => 'crane', 'description' => 'Crane lift, 20 ft', 'variant' => '20ft_loading', 'unit' => 'lift', 'amount' => '40000', 'currency' => 'RWF', 'supplier_id' => (string) $crane, 'valid_from' => $start, 'is_active' => '1']);
$save('service_rates', ['rate_type' => 'crane', 'description' => 'Crane lift, 40 ft', 'variant' => '40ft_loading', 'unit' => 'lift', 'amount' => '50000', 'currency' => 'RWF', 'supplier_id' => (string) $crane, 'valid_from' => $start, 'is_active' => '1']);
$save('service_rates', ['rate_type' => 'tyre', 'description' => 'Tyre change', 'variant' => 'per_tyre', 'unit' => 'tyre', 'amount' => '5000', 'currency' => 'RWF', 'supplier_id' => (string) $fitter, 'valid_from' => $start, 'is_active' => '1']);
$jambo = $save('customers', ['customer_name' => 'Jambo oil factory', 'customer_type' => 'corporate', 'contact_name' => 'Operations manager', 'status' => 'active', 'payment_terms_days' => '30', 'currency' => 'RWF']);

// ---------------------------------------------------- trucks and drivers (OM)
$signIn('logistics_manager');
$inAYear = date('Y-m-d', strtotime('+1 year'));
$fleet = [
    ['RAK 205D', 'RL 9465 /BX', 'BYIRINGIRO EMMANUEL', '0788100001', 'tanker'],
    ['RAK 206D', 'RL 9466 /BX', 'NSENGIYUMVA JEAN', '0788100002', 'tanker'],
    ['RAK 207D', 'RL 9467 /BX', 'HABIMANA ERIC', '0788100003', 'tanker'],
    ['RAD 310C', 'RL 7001 /CT', 'MUGISHA PAUL', '0788100004', 'container'],
    ['RAD 311C', 'RL 7002 /CT', 'JUMA HAMISI', null, 'container'],
];
$trucks = [];
$drivers = [];
foreach ($fleet as $i => [$plate, $trailerNo, $name, $momo, $type]) {
    $trailer = $save('trailers', ['trailer_number' => $trailerNo, 'trailer_type' => $type === 'tanker' ? 'tanker' : 'skeleton', 'tank_capacity_l' => $type === 'tanker' ? '36000' : '', 'container_sizes' => $type === 'container' ? '1x20,1x40,2x20' : '', 'max_load_t' => '32', 'status' => 'available']);
    $truck = $save('vehicles', ['plate_number' => $plate, 'fleet' => $type, 'vehicle_type' => $type === 'tanker' ? 'Tanker' : 'Truck', 'make' => 'Mercedes-Benz', 'model' => 'Actros', 'manufacture_year' => '2019',
        'max_payload_t' => '30', 'cargo_tank_l' => $type === 'tanker' ? '36000' : '', 'container_sizes' => $type === 'container' ? '1x20,1x40,2x20' : '', 'fuel_tank_l' => '1200', 'normal_l_per_100km' => '40',
        'trailer_id' => (string) $trailer, 'status' => 'available', 'mileage' => (string) (150000 + $i * 7300), 'last_service_km' => (string) (149000 + $i * 7300 - ($i === 2 ? 6800 : 0))]);
    $driverInput = ['full_name' => $name, 'nationality' => $momo === null ? 'Tanzanian' : 'Rwandan', 'passport_number' => $momo === null ? 'TZ8804411' : 'PC' . (500000 + $i), 'phone' => '+250 788 40000' . $i,
        'payment_method' => $momo === null ? 'cash' : 'momo', 'momo_number' => (string) $momo, 'momo_name' => $momo === null ? '' : $name, 'status' => 'active', 'fleet' => $type, 'default_vehicle_id' => (string) $truck, 'hired_on' => '2024-01-15', 'contract_type' => 'permanent'];
    $driver = $save('drivers', $driverInput);
    foreach (['vehicle' => $truck, 'trailer' => $trailer] as $owner => $ownerId) {
        foreach (array_keys(Fleet::documentTypeOptions($owner)) as $docType) {
            $expires = ($plate === 'RAK 207D' && $docType === 'insurance' && $owner === 'vehicle') ? date('Y-m-d', strtotime('+12 days')) : $inAYear;
            $db->prepare("INSERT INTO vehicle_documents (company_id, document_code, owner_type, vehicle_id, trailer_id, document_type, document_number, issued_on, expires_on, document_file, status)
                          VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, 'vehicle_documents/demo.pdf', ?)")
                ->execute([strtoupper(substr($owner, 0, 1)) . "-{$ownerId}-{$docType}", $owner, $owner === 'vehicle' ? $ownerId : null, $owner === 'trailer' ? $ownerId : null, $docType, 'N-' . $ownerId . '-' . $docType, $start, $expires, Fleet::paperStatus($expires)]);
        }
    }
    foreach (array_keys(Fleet::documentTypeOptions('driver')) as $docType) {
        $db->prepare("INSERT INTO driver_documents (company_id, driver_id, document_type, document_number, issue_date, expiry_date, document_file, status) VALUES (1, ?, ?, ?, ?, ?, 'driver_documents/demo.pdf', 'valid')")
            ->execute([$driver, $docType, 'D-' . $driver . '-' . $docType, $start, $inAYear]);
    }
    $trucks[] = $truck;
    $drivers[] = $driver;
}

// ------------------------------------------------------------------ trips
$plan = static fn (array $rows, string $cargo, string $quantity, string $summary): array => [
    'route_id' => $route, 'customer_id' => $jambo, 'cargo_type' => $cargo, 'cargo_quantity' => $quantity, 'cargo_summary' => $summary,
    'departure_at' => date('Y-m-d H:i:s'), 'rows' => $rows,
];
// Two tankers on one F1, money still in approval.
TripLife::plan($plan([
    ['vehicle_id' => $trucks[0], 'driver_id' => $drivers[0]],
    ['vehicle_id' => $trucks[1], 'driver_id' => $drivers[1]],
], 'litres', '36000', 'Diesel for Jambo oil factory'));

// A container truck taken all the way: authorized, paid, confirmed, departed, at Mombasa.
$third = TripLife::plan($plan([['vehicle_id' => $trucks[3], 'driver_id' => $drivers[3], 'container_numbers' => 'MSKU7712345', 'container_size' => '1x40']], 'containers', '1', 'Container for Jambo oil factory'));
$f1 = $third['request_ids'][0];
$signIn('finance');
RequestForms::verify($f1, 'Matches the rate card');
$signIn('management');
RequestForms::authorize($f1, 'OK');
$signIn('cashier');
$batch = Payments::build(array_column(Payments::toPay(), 'key'));
Payments::record($batch, 'MTN-DEMO-0001', 'payment_batches/demo-proof.pdf');
$line = Payments::batchLines($batch)[0];
$driverRow = Fleet::driver($drivers[3]);
$_SESSION['logistics_role'] = 'driver';
$_SESSION['logistics_user_id'] = (int) $driverRow['user_id'];
Payments::confirmReceipt((int) $line['id'], $drivers[3]);
$tripId = $third['trip_ids'][0];
TripLife::act($tripId, 'depart', ['odometer' => 172000]);
TripLife::act($tripId, 'arrive_turnaround');

// A support request waiting for the OM.
Models\DriverSupport::submit($drivers[3], $tripId, ['type' => 'police_border', 'details' => 'Waiting at Malaba: customs system down', 'urgency' => 'urgent', 'location' => 'Malaba border'], []);

Models\Settings::put('signature_required_to_approve', $previous);

// The garage and store (section 12).
define('ERI_DEMO_RUNNING', true);
require __DIR__ . '/eri_demo_garage.php';
require __DIR__ . '/eri_demo_commerce.php';

echo "Demo data loaded: 1 route with its rate card, 5 trucks with trailers, 5 drivers, 3 trips.\n";
echo "Sign in with any demo account (password: password). Drivers sign in with their phone number.\n";
