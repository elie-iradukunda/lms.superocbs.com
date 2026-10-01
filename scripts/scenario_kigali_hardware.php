<?php

declare(strict_types=1);

/**
 * The full Kigali Hardware job, played through the system by the real staff:
 * a 40ft container of building materials collected at Dar es Salaam port,
 * driven to Kigali through Rusumo, stored at the ITEC Kigali warehouse and
 * collected by the customer, invoiced in USD and paid in two instalments.
 *
 * Nothing is inserted behind the system's back. Each step signs in as the
 * person whose job it is and goes through the same functions as the buttons on
 * the screen, so every status, warehouse movement, notification, email, approval
 * and ledger entry is produced exactly as it would be by hand:
 *
 *   Emmanuel Safari  (Finance)            rate card, invoice, payments, approves expenses
 *   Aline Mukamana   (Logistics Manager)  customer, request, quote, trip, shipment, dispatch, customs
 *   Nadine Tuyisenge (Warehouse Manager)  document pack, pre-dispatch check, hand-over at the depot
 *   Eric Murenzi     (Fleet Manager)      truck permit, driver papers
 *   Samuel Niyonzima (Driver)             fuel, road expenses, route progress, drop at the warehouse
 *   Jean Pierre      (Management)         receives the profitability report by email
 *
 * Run once:   php scripts/scenario_kigali_hardware.php
 * It refuses to run twice (it checks for the customer first).
 */

require __DIR__ . '/../bootstrap.php';

use Core\Database;
use Models\Billing;
use Models\LogisticsData;
use Models\ReportSchedule;
use Models\Workflow;

$db = Database::connection();
$failures = [];
$step = 0;

$say = static function (string $text) use (&$step): void {
    printf("\n%2d. %s\n", ++$step, $text);
};
$ok = static function (bool $condition, string $what) use (&$failures): void {
    echo ($condition ? '    ✓ ' : '    ✗ ') . $what . "\n";
    if (!$condition) {
        $failures[] = $what;
    }
};
$one = static function (string $sql, array $params = []) use ($db): mixed {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
};
$row = static function (string $sql, array $params = []) use ($db): array {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
};

/** Signs in as an existing person, exactly as the login page would. */
$signIn = static function (string $email) use ($row): array {
    $user = $row('SELECT u.id, u.full_name, u.email, u.prvg, r.role_key FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND u.deleted_at IS NULL', [$email]);
    if ($user === []) {
        fwrite(STDERR, "The user {$email} does not exist. Nothing more was done.\n");
        exit(1);
    }
    $_SESSION['logistics_authenticated'] = true;
    $_SESSION['logistics_role'] = $user['role_key'];
    $_SESSION['logistics_user_id'] = (int) $user['id'];
    $_SESSION['logistics_user_name'] = $user['full_name'];
    $_SESSION['logistics_user_email'] = $user['email'];
    $_SESSION['logistics_prvg'] = (int) $user['prvg'];
    unset($_SESSION['logistics_driver_id']);
    Models\Permission::flush();
    echo "    signed in as {$user['full_name']} ({$user['role_key']})\n";
    return $user;
};

/** Runs a workflow button and reports its message. */
$press = static function (string $module, int $id, string $action, string $reason = '') use ($ok): void {
    $result = Workflow::apply($module, $id, $action, $reason);
    $ok($result['ok'], sprintf('%s → %s: %s', $module, $action, $result['message']));
};

/** Saves a record the way a form does: validated first, refused with the form's own messages. */
$save = static function (string $module, ?int $id, array $input) use ($ok): int {
    $errors = LogisticsData::validate($module, $input, [], $id);
    if ($errors !== []) {
        $ok(false, sprintf('%s form refused: %s', $module, implode(' ', $errors)));
        return 0;
    }
    return LogisticsData::save($module, $id, $input);
};

/** A small real PDF standing in for a scanned paper, so "Open" shows something. */
$scan = static function (string $module, string $title, array $lines): string {
    $text = 'BT /F1 16 Tf 50 780 Td (' . addcslashes($title, '()\\') . ') Tj ET';
    $y = 750;
    foreach ($lines as $line) {
        $text .= ' BT /F1 11 Tf 50 ' . $y . ' Td (' . addcslashes($line, '()\\') . ') Tj ET';
        $y -= 18;
    }
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length ' . strlen($text) . " >>\nstream\n" . $text . "\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    $directory = rtrim((string) config('uploads.path', __DIR__ . '/../storage/uploads'), '/\\') . '/' . $module;
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($directory . '/' . $name, $pdf);

    return $module . '/' . $name;
};

// ---------------------------------------------------------------- preconditions
if ($one("SELECT id FROM customers WHERE customer_name = 'Kigali Hardware Ltd' AND deleted_at IS NULL") !== false) {
    echo "The Kigali Hardware scenario is already in this database. Nothing was done.\n";
    exit(0);
}

$warehouseId = (int) $one("SELECT id FROM warehouses WHERE warehouse_name = 'ITEC Kigali Main Warehouse' AND deleted_at IS NULL");
$vehicleId = (int) $one("SELECT id FROM vehicles WHERE plate_number = 'RAD 482 K' AND deleted_at IS NULL");
$driver = $row("SELECT d.id, d.full_name FROM drivers d INNER JOIN users u ON u.id = d.user_id WHERE u.email = 'samuel@itec.rw' AND d.deleted_at IS NULL");
$postId = (int) $one("SELECT id FROM border_posts WHERE post_name = 'Rusumo'");
$agentId = (int) ($one("SELECT id FROM suppliers WHERE supplier_name = 'Great Lakes Clearing & Forwarding Ltd' AND deleted_at IS NULL") ?: 0);
if ($warehouseId === 0 || $vehicleId === 0 || $driver === [] || $postId === 0) {
    fwrite(STDERR, "Needs the ITEC Kigali Main Warehouse, the truck RAD 482 K, Samuel's driver profile and the Rusumo border post (load sample_data_inserts.sql first).\n");
    exit(1);
}
$driverId = (int) $driver['id'];
if ($one("SELECT COUNT(*) FROM trips WHERE (vehicle_id = ? OR driver_id = ?) AND status = 'in_transit' AND deleted_at IS NULL", [$vehicleId, $driverId]) > 0) {
    fwrite(STDERR, "RAD 482 K or Samuel is already out on another trip. Finish that trip first.\n");
    exit(1);
}

echo "KIGALI HARDWARE LTD — Dar es Salaam port to ITEC Kigali warehouse\n";
echo str_repeat('=', 66) . "\n";

// ------------------------------------------------------------ 1. fleet papers
$say('Fleet: the truck\'s Tanzania transit permit and the driver\'s papers are on file');
$signIn('eric@itec.rw');
$permitId = $save('vehicle_documents', null, [
    'document_code' => '', 'vehicle_id' => (string) $vehicleId, 'document_type' => 'permit',
    'document_number' => 'TZ-RTP-2026-77310', 'provider_name' => 'TANROADS (Tanzania road transit permit)',
    'issued_on' => date('Y-m-d', strtotime('-10 days')), 'expires_on' => date('Y-m-d', strtotime('+170 days')),
    'cost' => '120000', 'status' => 'valid', 'notes' => 'Required for foreign trucks on the Central Corridor.',
]);
$db->prepare('UPDATE vehicle_documents SET document_file = ? WHERE id = ?')->execute([$scan('vehicle_documents', 'TANZANIA ROAD TRANSIT PERMIT', ['Permit TZ-RTP-2026-77310', 'Vehicle RAD 482 K (Scania R450)', 'Valid 170 days']), $permitId]);
$db->prepare("UPDATE vehicle_documents SET document_file = ? WHERE vehicle_id = ? AND document_type = 'insurance' AND document_file IS NULL")
   ->execute([$scan('vehicle_documents', 'SONARWA MOTOR INSURANCE + COMESA YELLOW CARD', ['Policy SNR-MV-2026-118420', 'Vehicle RAD 482 K', 'Valid in Rwanda, Tanzania, Uganda, Kenya']), $vehicleId]);
foreach ([['passport', 'PC1234567', '+5 years', 'PASSPORT'], ['driving_licence', 'RW-DL-0456789', '+18 months', 'DRIVING LICENCE C/D']] as [$type, $number, $expiry, $title]) {
    $db->prepare(
        "INSERT INTO driver_documents (driver_id, document_type, document_number, issue_date, expiry_date, status, document_file)
         VALUES (?, ?, ?, ?, ?, 'valid', ?)
         ON DUPLICATE KEY UPDATE document_number = VALUES(document_number), expiry_date = VALUES(expiry_date), status = 'valid', document_file = VALUES(document_file)"
    )->execute([$driverId, $type, $number, date('Y-m-d', strtotime('-1 year')), date('Y-m-d', strtotime($expiry)), $scan('driver_documents', $title, ['Samuel Niyonzima', 'No. ' . $number])]);
}
$ok((int) $one("SELECT COUNT(*) FROM vehicle_documents WHERE vehicle_id = ? AND status IN ('valid','expiring') AND deleted_at IS NULL", [$vehicleId]) >= 2, 'RAD 482 K has insurance and a Tanzania transit permit');
$ok((int) $one('SELECT COUNT(*) FROM driver_documents WHERE driver_id = ?', [$driverId]) >= 2, 'Samuel\'s passport and licence are on file');

// -------------------------------------------------------------- 2. rate card
$say('Finance agrees the price: USD 3,200 a container, Dar es Salaam to the Kigali warehouse');
$signIn('emmanuel@itec.rw');
// The customer must exist for a customer-specific card, so finance sets up a
// general route card here and the request below picks it.
$rateId = $save('rates', null, [
    'rate_code' => '', 'customer_id' => '', 'status' => 'active',
    'origin' => 'Dar es Salaam', 'destination' => 'Kigali', 'destination_warehouse_id' => (string) $warehouseId,
    'vehicle_type' => '', 'rate_type' => 'per_trip', 'rate_amount' => '3200', 'currency' => 'USD',
    'minimum_charge' => '3200', 'effective_from' => date('Y-m-01', strtotime('-1 month')), 'effective_to' => date('Y-12-31', strtotime('+1 year')),
    'notes' => '40ft container, Dar es Salaam port to ITEC Kigali warehouse via Rusumo. Storage, offloading and customs duty billed separately.',
]);
$ok($rateId > 0, 'rate card ' . $one('SELECT rate_code FROM rate_cards WHERE id = ?', [$rateId]) . ' is active');

// ---------------------------------------------------------- 3. the customer
$say('Logistics registers the customer');
$signIn('aline@itec.rw');
$customerId = $save('customers', null, [
    'customer_code' => '', 'customer_name' => 'Kigali Hardware Ltd', 'customer_type' => 'corporate',
    'contact_name' => 'Jean Claude Mugabo', 'phone' => '+250 788 455 120', 'email' => 'imports@kigalihardware.rw',
    'address' => 'KN 5 Rd, Nyarugenge', 'district' => 'Nyarugenge', 'tin_number' => '109887766',
    'payment_terms_days' => '30', 'credit_limit' => '50000', 'currency' => 'USD', 'status' => 'active',
    'notes' => 'Importer of building materials from China, through Dar es Salaam.',
]);
$db->prepare('UPDATE rate_cards SET customer_id = ? WHERE id = ?')->execute([$customerId, $rateId]);
$ok($customerId > 0, 'customer ' . $one('SELECT customer_code FROM customers WHERE id = ?', [$customerId]) . ' Kigali Hardware Ltd, billed in USD');

// --------------------------------------------------- 4. request, quote, accept
$say('The customer sends its LPO; Logistics records the request, quotes it and the customer accepts');
$requestId = $save('requests', null, [
    'reference_code' => '', 'customer_id' => (string) $customerId, 'requested_by_contact' => 'Jean Claude Mugabo',
    'lpo_number' => 'LPO-KH-0912', 'priority' => 'high',
    'pickup_location' => 'Dar es Salaam', 'destination' => 'Kigali', 'destination_warehouse_id' => (string) $warehouseId,
    'required_date' => date('Y-m-d', strtotime('+6 days')),
    'cargo_description' => 'Building materials: floor tiles, taps, PVC pipes — 1 x 40ft container (MSKU 7788123)',
    'weight_kg' => '22000', 'packages_count' => '640', 'currency' => 'USD', 'status' => 'pending',
    'notes' => 'Container discharged at Dar es Salaam port. Free time at the port ends in 7 days.',
]);
$db->prepare('UPDATE transport_requests SET lpo_file = ? WHERE id = ?')->execute([$scan('requests', 'LOCAL PURCHASE ORDER LPO-KH-0912', ['Kigali Hardware Ltd', 'Transport 1 x 40ft container Dar es Salaam - Kigali', 'Agreed price USD 3,200 + VAT']), $requestId]);
$press('requests', $requestId, 'quote');
$ok((float) $one('SELECT quoted_amount FROM transport_requests WHERE id = ?', [$requestId]) === 3200.0, 'quoted USD 3,200 from the rate card, and the quote was emailed to the customer');
$db->prepare("UPDATE transport_requests SET accepted_by = 'Jean Claude Mugabo', accepted_at = NOW() WHERE id = ?")->execute([$requestId]);
$press('requests', $requestId, 'approve');
$requestCode = (string) $one('SELECT reference_code FROM transport_requests WHERE id = ?', [$requestId]);

// --------------------------------------------------------- 5. trip and route
$say('Logistics plans the trip: RAD 482 K with Samuel Niyonzima, five stops');
$tripId = $save('trips', null, [
    'reference_code' => '', 'request_id' => (string) $requestId, 'customer_id' => (string) $customerId, 'trip_type' => 'delivery',
    'pickup_location' => 'Dar es Salaam Port (Kurasini)', 'destination' => 'ITEC Kigali Main Warehouse, Masaka',
    'cargo_summary' => '1 x 40ft container, building materials, 22,000 kg',
    'planned_departure_at' => date('Y-m-d 06:00'), 'planned_arrival_at' => date('Y-m-d 16:00', strtotime('+5 days')),
    'vehicle_id' => (string) $vehicleId, 'driver_id' => (string) $driverId, 'status' => 'requested',
    'notes' => 'Central Corridor: Dar - Morogoro - Dodoma - Nzega - Nyakanazi - Rusumo - Kayonza - Kigali.',
]);
$route = [
    ['stop_type' => 'pickup', 'location_name' => 'Dar es Salaam Port, Kurasini container terminal', 'contact_name' => 'Port release desk', 'planned_arrival_at' => date('Y-m-d 06:00')],
    ['stop_type' => 'waypoint', 'location_name' => 'Morogoro', 'planned_arrival_at' => date('Y-m-d 13:00')],
    ['stop_type' => 'waypoint', 'location_name' => 'Nyakanazi (fuel and night stop)', 'planned_arrival_at' => date('Y-m-d 18:00', strtotime('+3 days'))],
    ['stop_type' => 'checkpoint', 'location_name' => 'Rusumo One Stop Border Post', 'contact_name' => 'Joseph Kamanzi (clearing agent)', 'contact_phone' => '+250 785 118 204', 'planned_arrival_at' => date('Y-m-d 10:00', strtotime('+4 days'))],
    ['stop_type' => 'dropoff', 'location_name' => 'ITEC Kigali Main Warehouse, Masaka', 'contact_name' => 'Nadine Tuyisenge', 'contact_phone' => '+250 782 110 554', 'planned_arrival_at' => date('Y-m-d 16:00', strtotime('+5 days'))],
];
LogisticsData::saveLines('trips', $tripId, array_map(static fn (array $s): array => $s + ['status' => 'pending', 'contact_name' => $s['contact_name'] ?? '', 'contact_phone' => $s['contact_phone'] ?? ''], $route));
$tripCode = (string) $one('SELECT reference_code FROM trips WHERE id = ?', [$tripId]);
$ok((string) $one('SELECT status FROM transport_requests WHERE id = ?', [$requestId]) === 'assigned', "trip {$tripCode} planned, request {$requestCode} is now Assigned");

// ------------------------------------------------------------- 6. the cargo
$say('Logistics books the cargo: container, seal, trailer, bill of lading and LPO');
$shipmentId = $save('shipments', null, [
    'shipment_code' => '', 'customer_id' => (string) $customerId, 'request_id' => (string) $requestId, 'trip_id' => (string) $tripId,
    'consignee_name' => 'Kigali Hardware Ltd (Jean Claude Mugabo)', 'consignee_phone' => '+250 788 455 120',
    'origin' => 'Dar es Salaam Port', 'destination' => 'Kigali', 'destination_warehouse_id' => (string) $warehouseId,
    'cargo_type' => 'general', 'cargo_description' => 'Floor tiles, taps, PVC pipes (building materials)',
    'packages_count' => '640', 'weight_kg' => '22000', 'volume_m3' => '67', 'declared_value' => '85000', 'currency' => 'USD',
    'container_no' => 'MSKU 7788123', 'container_size' => '40ft', 'seal_no' => 'TZ-SEAL-448812', 'trailer_plate' => 'RL 2231',
    'bill_of_lading' => 'MEDUQ1234567', 'customer_reference' => 'LPO-KH-0912', 'status' => 'booked',
    'special_instructions' => 'Tiles are fragile: no sudden braking, check the seal at every stop. Store under cover in Kigali.',
]);
$shipment = $row('SELECT shipment_code, quoted_amount, customer_reference FROM shipments WHERE id = ?', [$shipmentId]);
$ok((float) $shipment['quoted_amount'] === 3200.0, "shipment {$shipment['shipment_code']} priced USD 3,200 from the rate card, LPO {$shipment['customer_reference']}");

// ------------------------------------------------ 7. document pack + check
$say('Warehouse checks the document pack and signs the pre-dispatch check');
$signIn('nadine@itec.rw');
$pack = [
    'Commercial invoice' => ['CI-SZ-2026-3381', 'COMMERCIAL INVOICE', 'Foshan Ceramics Co. to Kigali Hardware Ltd, USD 85,000'],
    'Packing list' => ['PL-SZ-2026-3381', 'PACKING LIST', '640 packages: tiles 520 cartons, taps 60 boxes, PVC 60 bundles'],
    'Transport document' => ['MEDUQ1234567', 'BILL OF LADING', 'Shenzhen to Dar es Salaam, container MSKU 7788123'],
    'Certificate of origin' => ['COO-CN-2026-118204', 'CERTIFICATE OF ORIGIN', 'Made in China'],
    'Customs declaration' => ['C 2026 RW 188431', 'SINGLE CUSTOMS TERRITORY DECLARATION', 'Lodged with RRA Kigali, transit through Tanzania'],
    'Insurance' => ['GIT-2026-5521', 'GOODS IN TRANSIT INSURANCE', 'Cover USD 85,000, Dar es Salaam to Kigali'],
];
foreach ($pack as $type => [$number, $title, $line]) {
    $documentId = $save('shipment_documents', null, [
        'shipment_id' => (string) $shipmentId, 'document_type' => $type, 'document_number' => $number,
        'issue_date' => date('Y-m-d', strtotime('-12 days')), 'status' => 'valid',
    ]);
    $db->prepare('UPDATE shipment_documents SET document_file = ? WHERE id = ?')->execute([$scan('shipment_documents', $title, [$number, $line, 'Shipment ' . $shipment['shipment_code']]), $documentId]);
}
$ok(Models\DocumentPack::missingForShipment($row('SELECT * FROM shipments WHERE id = ?', [$shipmentId])) === [], 'all six documents are in the pack and valid');
$save('pre_dispatch_checks', null, [
    'shipment_id' => (string) $shipmentId, 'cargo_loaded' => '1', 'quantity_verified' => '1', 'packaging_verified' => '1',
    'documents_verified' => '1', 'vehicle_verified' => '1', 'driver_verified' => '1', 'route_verified' => '1',
    'status' => 'ready', 'notes' => 'Seal TZ-SEAL-448812 intact at loading. RECTS seal fitted by TRA.',
]);
$ok((string) $one('SELECT status FROM shipment_pre_dispatch_checks WHERE shipment_id = ?', [$shipmentId]) === 'ready', 'pre-dispatch check: Ready');

// ------------------------------------------------------------ 8. dispatch
$say('Logistics dispatches the truck from Dar es Salaam port');
$signIn('aline@itec.rw');
$press('trips', $tripId, 'dispatch');
$ok((string) $one('SELECT status FROM vehicles WHERE id = ?', [$vehicleId]) === 'on_trip', 'RAD 482 K is On trip');
$ok((string) $one('SELECT status FROM drivers WHERE id = ?', [$driverId]) === 'on_trip', 'Samuel is On trip and was notified');
$ok((string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]) === 'in_transit', 'the container is In transit; the customer was emailed');
$deliveryId = $save('deliveries', null, [
    'delivery_code' => '', 'trip_id' => (string) $tripId, 'shipment_id' => (string) $shipmentId, 'attempt_number' => '1',
    'recipient_name' => 'Kigali Hardware Ltd (Jean Claude Mugabo)', 'recipient_phone' => '+250 788 455 120',
    'destination' => 'ITEC Kigali Main Warehouse, Masaka', 'destination_warehouse_id' => (string) $warehouseId,
    'planned_at' => date('Y-m-d 16:00', strtotime('+5 days')), 'status' => 'in_transit', 'failure_reason' => 'none',
]);
$deliveryCode = (string) $one('SELECT delivery_code FROM deliveries WHERE id = ?', [$deliveryId]);
$ok($deliveryId > 0, "delivery {$deliveryCode} opened for the drop at the Kigali warehouse");

// ----------------------------------------------------- 9. invoice + advance
$say('Finance raises the invoice from the trip, issues it, and banks the 50% advance');
$signIn('emmanuel@itec.rw');
$billing = Billing::invoiceTrip($tripId);
$ok($billing['ok'] && ($billing['invoices'][0]['created'] ?? false), $billing['message']);
$invoiceId = (int) ($billing['invoices'][0]['id'] ?? 0);
$press('invoices', $invoiceId, 'approve');
$invoice = $row('SELECT invoice_number, currency, total_amount, lpo_number FROM invoices WHERE id = ?', [$invoiceId]);
$ok($invoice['currency'] === 'USD' && (float) $invoice['total_amount'] === 3776.0 && $invoice['lpo_number'] === 'LPO-KH-0912', "{$invoice['invoice_number']}: USD 3,776 (3,200 + 18% VAT), LPO-KH-0912");
$save('payments', null, [
    'payment_code' => '', 'invoice_id' => (string) $invoiceId, 'amount' => '1888', 'method' => 'bank_transfer',
    'reference' => 'BK-KH-ADV-20261001', 'paid_at' => date('Y-m-d H:i'), 'notes' => '50% advance before departure from Dar es Salaam.',
]);
$ok((string) $one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]) === 'partially_paid', 'advance of USD 1,888 recorded; invoice Partially paid; the customer got a receipt');

// -------------------------------------------------------- 10. on the road
$say('The driver on the road: fuel in Tanzanian shillings, road costs, progress along the route');
$signIn('samuel@itec.rw');
$_SESSION['logistics_driver_id'] = $driverId;
$mileage = (int) $one('SELECT mileage FROM vehicles WHERE id = ?', [$vehicleId]);
foreach ([
    ['Puma Energy Kurasini, Dar es Salaam', 450, 3150, $mileage + 15, date('Y-m-d 05:40')],
    ['Lake Oil Nyakanazi', 320, 3100, $mileage + 1180, date('Y-m-d H:i')],
] as [$station, $litres, $price, $odometer, $when]) {
    $save('fuel', null, [
        'reference_code' => '', 'vehicle_id' => (string) $vehicleId, 'station_name' => $station, 'fuel_type' => 'diesel',
        'purchased_at' => $when, 'litres' => (string) $litres, 'unit_price' => (string) $price, 'currency' => 'TZS',
        'is_full_tank' => '1', 'mileage' => (string) $odometer, 'trip_id' => (string) $tripId,
    ]);
}
$ok((int) $one("SELECT COUNT(*) FROM fuel_records WHERE trip_id = ? AND currency = 'TZS'", [$tripId]) === 2, 'two fuel stops recorded in TZS; the odometer moved on its own');
foreach ([
    ['Allowance', '150', 'USD', 'Night-out allowance, 5 nights x USD 30 (Dar - Kigali).'],
    ['Toll', '85000', 'TZS', 'Tanzania road charges and weighbridges (Vigwaza, Mikese, Nyakanazi).'],
    ['Parking', '10000', 'RWF', 'Secure parking at Rusumo while the declaration was reviewed.'],
] as [$category, $amount, $currency, $note]) {
    $save('expenses', null, [
        'reference_code' => '', 'category' => $category, 'amount' => $amount, 'currency' => $currency,
        'expense_date' => date('Y-m-d'), 'vehicle_id' => (string) $vehicleId, 'trip_id' => (string) $tripId,
        'payment_method' => 'cash', 'notes' => $note,
    ]);
}
$ok((int) $one("SELECT COUNT(*) FROM expenses WHERE trip_id = ? AND status = 'pending'", [$tripId]) === 3, 'three road expenses filed by the driver, waiting for Finance');
$progress = $route;
foreach ($progress as $index => &$stop) {
    $stop += ['contact_name' => '', 'contact_phone' => ''];
    $stop['status'] = $index < 3 ? 'completed' : ($index === 3 ? 'arrived' : 'pending');
}
unset($stop);
LogisticsData::saveLines('trips', $tripId, $progress);
$ok((string) $one("SELECT status FROM trip_stops WHERE trip_id = ? AND stop_type = 'checkpoint'", [$tripId]) === 'arrived', 'driver marked Dar, Morogoro and Nyakanazi done and arrived at Rusumo');

// --------------------------------------------------------- 11. customs
$say('Customs clearance at Rusumo: documents, inspection, charges, clearance and release');
$signIn('aline@itec.rw');
$crossingId = $save('crossings', null, [
    'reference' => '', 'trip_id' => (string) $tripId, 'shipment_id' => (string) $shipmentId,
    'vehicle_id' => (string) $vehicleId, 'driver_id' => (string) $driverId, 'border_post_id' => (string) $postId,
    'direction' => 'import', 'clearing_agent_id' => $agentId > 0 ? (string) $agentId : '',
    'declaration_no' => 'C 2026 RW 188431', 'transit_bond_no' => 'SCT-TZ-2026-55120', 'seal_no' => 'TZ-SEAL-448812',
    'weighbridge_kg' => '37800', 'arrived_at' => date('Y-m-d H:i'), 'currency' => 'RWF',
    'notes' => 'Single Customs Territory: duties assessed by RRA, paid by Kigali Hardware Ltd directly.',
]);
$crossingCode = (string) $one('SELECT reference FROM border_crossings WHERE id = ?', [$crossingId]);
$press('crossings', $crossingId, 'prepare_documents');
foreach ([
    ['Customs declaration', 'C 2026 RW 188431', 'SCT DECLARATION (RRA)'],
    ['Commercial invoice', 'CI-SZ-2026-3381', 'COMMERCIAL INVOICE'],
    ['Packing list', 'PL-SZ-2026-3381', 'PACKING LIST'],
    ['Certificate of origin', 'COO-CN-2026-118204', 'CERTIFICATE OF ORIGIN'],
] as [$type, $number, $title]) {
    $documentId = $save('clearance_documents', null, [
        'crossing_id' => (string) $crossingId, 'document_type' => $type, 'document_no' => $number,
        'issued_on' => date('Y-m-d', strtotime('-12 days')), 'status' => 'valid',
    ]);
    $db->prepare('UPDATE border_documents SET document_file = ? WHERE id = ?')->execute([$scan('clearance_documents', $title, [$number, 'Clearance ' . $crossingCode, 'Rusumo One Stop Border Post']), $documentId]);
}
$press('crossings', $crossingId, 'submit_declaration');
$press('crossings', $crossingId, 'start_review');
$press('crossings', $crossingId, 'request_inspection');
$save('clearance_inspections', null, [
    'crossing_id' => (string) $crossingId, 'inspection_type' => 'Seal check and physical verification',
    'scheduled_at' => date('Y-m-d H:i'), 'location' => 'Rusumo OSBP inspection bay', 'inspector' => 'RRA officer on duty',
    'result' => 'passed', 'remarks' => 'Seal intact, contents match the packing list.',
]);
$press('crossings', $crossingId, 'pass_inspection');
$press('crossings', $crossingId, 'request_payment');
LogisticsData::saveLines('crossings', $crossingId, [
    ['charge_type' => 'Clearing agent fee', 'description' => 'Lodging and following the SCT declaration', 'receipt_no' => 'GLCF-RSM-2026-0518', 'amount' => '180000', 'status' => 'paid'],
    ['charge_type' => 'Weighbridge', 'description' => 'Axle load check, Rusumo', 'receipt_no' => 'RTDA-WB-88120', 'amount' => '25000', 'status' => 'paid'],
]);
$signIn('emmanuel@itec.rw');
$save('clearance_payments', null, [
    'crossing_id' => (string) $crossingId, 'amount' => '205000', 'currency' => 'RWF', 'payment_method' => 'Mobile money',
    'reference' => 'MOMO-RSM-20261001-118', 'payment_date' => date('Y-m-d'), 'status' => 'paid',
    'notes' => 'Clearing agent fee and weighbridge at Rusumo.',
]);
$signIn('aline@itec.rw');
$press('crossings', $crossingId, 'clear');
$save('clearance_releases', null, [
    'crossing_id' => (string) $crossingId, 'release_number' => 'RRA-REL-2026-40912', 'release_date' => date('Y-m-d'),
    'released_by' => (string) current_user_id(),
]);
$press('crossings', $crossingId, 'release');
$ok((string) $one('SELECT status FROM border_crossings WHERE id = ?', [$crossingId]) === 'released', "{$crossingCode} released at Rusumo");
$ok((string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]) === 'in_transit', 'the container is back on the road to Kigali (In transit)');

// ------------------------------------------------- 12. arrival at Kigali
$say('The truck reaches the ITEC Kigali warehouse; the driver drops the container');
$signIn('samuel@itec.rw');
$_SESSION['logistics_driver_id'] = $driverId;
foreach ($progress as &$stop) {
    $stop['status'] = 'completed';
}
unset($stop);
LogisticsData::saveLines('trips', $tripId, $progress);
$press('deliveries', $deliveryId, 'drop');
$ok((int) $one("SELECT COUNT(*) FROM warehouse_cargo WHERE shipment_id = ? AND warehouse_id = ? AND reason = 'arrived'", [$shipmentId, $warehouseId]) === 1, 'the Kigali warehouse now holds the container (custody: arrived); the customer was told it is ready for collection');

$say('Logistics closes the trip');
$signIn('aline@itec.rw');
$press('trips', $tripId, 'complete');
$ok((string) $one('SELECT status FROM vehicles WHERE id = ?', [$vehicleId]) === 'available', 'RAD 482 K is Available again');
$ok((string) $one('SELECT status FROM drivers WHERE id = ?', [$driverId]) === 'available', 'Samuel is Available again');

// ------------------------------------------------- 13. finance approvals
$say('Finance approves the driver\'s road expenses');
$signIn('emmanuel@itec.rw');
foreach ($db->query("SELECT id FROM expenses WHERE trip_id = {$tripId} AND status = 'pending'")->fetchAll(PDO::FETCH_COLUMN) as $expenseId) {
    $press('expenses', (int) $expenseId, 'approve');
}
$ok((int) $one("SELECT COUNT(*) FROM expenses WHERE trip_id = ? AND status = 'approved' AND approved_by IS NOT NULL", [$tripId]) === 3, 'all three expenses approved, with Emmanuel named as approver');

// --------------------------------------------- 14. customer collects
$say('The customer collects at the warehouse; the warehouse hands over against the delivery note');
$signIn('nadine@itec.rw');
$db->prepare("UPDATE deliveries SET collected_by = 'Jean Claude Mugabo', collected_id_no = '1198580034567812', proof_file = ? WHERE id = ?")
   ->execute([$scan('deliveries', 'DELIVERY NOTE ' . $deliveryCode . ' - SIGNED', ['Received in good order: 640 packages', 'Jean Claude Mugabo, ID 1198580034567812', 'Kigali Hardware Ltd stamp']), $deliveryId]);
$press('deliveries', $deliveryId, 'complete');
$ok((int) $one("SELECT COUNT(*) FROM warehouse_cargo WHERE shipment_id = ? AND warehouse_id = ? AND reason = 'collected'", [$shipmentId, $warehouseId]) === 1, 'the container left the warehouse (custody: collected); the customer got the hand-over email');
$ok((string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]) === 'delivered', 'the shipment is Delivered');

// ------------------------------------------------------- 15. balance
$say('The customer pays the balance');
$signIn('emmanuel@itec.rw');
$save('payments', null, [
    'payment_code' => '', 'invoice_id' => (string) $invoiceId, 'amount' => '1888', 'method' => 'bank_transfer',
    'reference' => 'BK-KH-BAL-20261006', 'paid_at' => date('Y-m-d H:i'), 'notes' => 'Balance after collection at the Kigali warehouse.',
]);
$ok((string) $one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]) === 'paid', "{$invoice['invoice_number']} is Paid in full");
$ok(Models\Ledger::isBalanced(), 'the books balance');

// ------------------------------------------------------ 16. reporting
$say('Management receives the trip profitability report by email');
$scheduleId = (int) ($one("SELECT id FROM report_subscriptions WHERE report_key = 'trip_profitability' AND deleted_at IS NULL ORDER BY id LIMIT 1") ?: 0);
if ($scheduleId > 0) {
    $sent = ReportSchedule::send($scheduleId);
    $ok($sent['sent'] > 0, 'trip profitability report: ' . $sent['status']);
} else {
    $ok(false, 'no trip profitability schedule (import migration 000032 first)');
}

// ------------------------------------------------------------ summary
echo "\n" . str_repeat('=', 66) . "\nWHAT THE SYSTEM NOW HOLDS\n";
printf("  Request   %s  %s\n", $requestCode, $one('SELECT status FROM transport_requests WHERE id = ?', [$requestId]));
printf("  Trip      %s  %s\n", $tripCode, $one('SELECT status FROM trips WHERE id = ?', [$tripId]));
printf("  Shipment  %s  %s\n", $shipment['shipment_code'], $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]));
printf("  Customs   %s  %s\n", $crossingCode, $one('SELECT status FROM border_crossings WHERE id = ?', [$crossingId]));
printf("  Delivery  %s  %s\n", $deliveryCode, $one('SELECT status FROM deliveries WHERE id = ?', [$deliveryId]));
printf("  Invoice   %s  %s (paid USD %s)\n", $invoice['invoice_number'], $one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]), number_format((float) $one('SELECT amount_paid FROM invoices WHERE id = ?', [$invoiceId]), 2));
echo "  Warehouse trail:\n";
foreach ($db->query("SELECT wc.movement_code, w.warehouse_name, wc.direction, wc.reason, wc.moved_at FROM warehouse_cargo wc JOIN warehouses w ON w.id = wc.warehouse_id WHERE wc.shipment_id = {$shipmentId} ORDER BY wc.id") as $move) {
    printf("    %s  %-28s %-3s %-9s %s\n", $move['movement_code'], $move['warehouse_name'], $move['direction'], $move['reason'], $move['moved_at']);
}
printf("  Journal entries for this job: %d\n", (int) $one(
    "SELECT COUNT(*) FROM gl_journal_entries WHERE deleted_at IS NULL AND (reference = ? OR source_code = ? OR source_code IN (SELECT payment_code FROM payments WHERE invoice_id = ?) OR source_code IN (SELECT reference_code FROM expenses WHERE trip_id = ?) OR source_code IN (SELECT reference_code FROM fuel_records WHERE trip_id = ?) OR source_code = ?)",
    [$tripCode, $invoice['invoice_number'], $invoiceId, $tripId, $tripId, $crossingCode]
));
printf("  Emails recorded: %d to the customer, %d to staff\n",
    (int) $one("SELECT COUNT(*) FROM email_outbox WHERE to_email = 'imports@kigalihardware.rw'"),
    (int) $one("SELECT COUNT(*) FROM email_outbox WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND to_email <> 'imports@kigalihardware.rw'"));
printf("  Notifications raised: %d\n", (int) $one('SELECT COUNT(*) FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)'));

if ($failures !== []) {
    echo "\n" . count($failures) . " check(s) FAILED:\n  - " . implode("\n  - ", $failures) . "\n";
    exit(1);
}
echo "\nEvery step passed.\n";
exit(0);
