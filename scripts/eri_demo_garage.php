<?php

declare(strict_types=1);

/**
 * Demo data for the garage and store (section 12), on top of scripts/eri_demo.php:
 * a store with parts, a truck at its service with the service request
 * authorized (job card open, issue note waiting in the store), and tyre and
 * crane log lines waiting to be paid.
 *
 *   php scripts/eri_demo_garage.php
 *
 * Run by scripts/eri_demo.php; safe to run on its own once.
 */

if (!defined('ERI_DEMO_RUNNING')) {
    require __DIR__ . '/../bootstrap.php';
}

use Models\Garage;
use Models\LogisticsData;
use Models\RequestForms;
use Models\StockLedger;

$db = Core\Database::connection();
if ((int) $db->query("SELECT COUNT(*) FROM warehouses WHERE warehouse_code = 'KGL-STORE'")->fetchColumn() > 0) {
    echo "The garage demo data is already loaded.\n";
    return;
}

$accounts = (new Models\UserRepository())->activeLoginAccounts();
$as = static function (string $role) use ($accounts): void {
    $account = $accounts[$role];
    $_SESSION = [
        'logistics_authenticated' => true, 'logistics_role' => $role, 'logistics_user_id' => (int) $account['id'],
        'logistics_user_name' => $account['name'], 'logistics_user_email' => $account['email'], 'logistics_prvg' => 2,
        'logistics_home_company_id' => 1, 'logistics_company_id' => 1,
    ];
    Models\Permission::flush();
};
$previous = Models\Settings::get('signature_required_to_approve', '1');
Models\Settings::put('signature_required_to_approve', '0');

$as('super_admin');
$db->exec("INSERT INTO warehouses (company_id, warehouse_code, warehouse_name, location, status) VALUES (1, 'KGL-STORE', 'Kigali main store', 'Kigali yard', 'active')");
$store = (int) $db->lastInsertId();
$items = [];
foreach ([
    ['DF-01', 'Diesel filter', 'Spare parts', 'pcs', 6, 2, 12000],
    ['OF-01', 'Oil filter', 'Spare parts', 'pcs', 3, 4, 8000],
    ['EO-15W40', 'Engine oil 15W40', 'Lubricants', 'L', 120, 50, 5000],
    ['WS-01', 'Water separator', 'Spare parts', 'pcs', 0, 2, 45000],
    ['TY-31580', 'Tyre 315/80 R22.5', 'Tyres', 'pcs', 10, 4, 380000],
] as [$sku, $name, $category, $unit, $qty, $minimum, $cost]) {
    $db->prepare("INSERT INTO inventory_items (company_id, warehouse_id, sku, item_name, category, unit_of_measure, quantity, minimum_level, unit_cost, status) VALUES (1, ?, ?, ?, ?, ?, 0, ?, ?, 'in_stock')")
        ->execute([$store, $sku, $name, $category, $unit, $minimum, $cost]);
    $items[$sku] = (int) $db->lastInsertId();
    StockLedger::openingBalance($items[$sku], $qty, $cost);
}
$parts = (int) $db->query("SELECT id FROM suppliers WHERE supplier_name LIKE 'ERI Rwanda LTD%' LIMIT 1")->fetchColumn();
if ($parts === 0) {
    $parts = LogisticsData::save('suppliers', null, ['supplier_name' => 'ERI Rwanda LTD (garage and spare parts)', 'category' => 'Garage', 'status' => 'active', 'payment_terms_days' => '30', 'payment_method' => 'bank', 'bank_name' => 'Bank of Kigali', 'bank_account' => '00040-0012345'], []);
}
$db->exec("UPDATE suppliers SET payment_method = 'momo', momo_number = '0788555111', momo_name = 'KIGALI TYRE FITTERS' WHERE supplier_name = 'Kigali Tyre Fitters'");
$db->exec("UPDATE suppliers SET payment_method = 'momo', momo_number = '0788555222', momo_name = 'MOMBASA CRANE SERVICES' WHERE supplier_name = 'Mombasa Crane Services'");

// A truck at its service: the clock raises the F3, the OM completes it, the chain authorizes it.
$as('logistics_manager');
Garage::raiseDueServices();
$f3 = (int) $db->query("SELECT r.id FROM form_requests r INNER JOIN vehicles v ON v.id = r.vehicle_id WHERE r.form_code = 'F3' AND r.status = 'draft' ORDER BY r.id LIMIT 1")->fetchColumn();
if ($f3 > 0) {
    $request = RequestForms::find($f3);
    RequestForms::update($f3, ['title' => $request['title'], 'vehicle_id' => $request['vehicle_id'], 'payload' => is_array($request['payload']) ? $request['payload'] : (json_decode((string) $request['payload'], true) ?: [])], [
        ['item_code' => 'service', 'description' => 'Service', 'quantity' => 1, 'amount' => 0],
        ['item_code' => 'part', 'item_id' => $items['DF-01'], 'description' => 'Diesel filter', 'quantity' => 1, 'unit_price' => 12000],
        ['item_code' => 'part', 'item_id' => $items['OF-01'], 'description' => 'Oil filter', 'quantity' => 2, 'unit_price' => 8000],
        ['item_code' => 'part', 'item_id' => $items['EO-15W40'], 'description' => 'Engine oil 15W40', 'quantity' => 25, 'unit' => 'L', 'unit_price' => 5000],
        ['item_code' => 'part', 'item_id' => $items['WS-01'], 'description' => 'Water separator', 'quantity' => 1, 'unit_price' => 45000],
    ]);
    RequestForms::send($f3);
    $as('finance');
    RequestForms::verify($f3, 'Standard service kit');
    $as('management');
    RequestForms::authorize($f3, 'OK');
}

// Tyre changes and crane lifts this month, not paid yet.
$as('logistics_manager');
$truck = (int) $db->query("SELECT id FROM vehicles WHERE plate_number = 'RAD 310C'")->fetchColumn();
$fitter = (int) $db->query("SELECT id FROM suppliers WHERE supplier_name = 'Kigali Tyre Fitters'")->fetchColumn();
$crane = (int) $db->query("SELECT id FROM suppliers WHERE supplier_name = 'Mombasa Crane Services'")->fetchColumn();
if ($truck > 0 && $fitter > 0) {
    foreach ([['front_left', 'worn'], ['front_right', 'worn'], ['drive_left_outer', 'puncture']] as $i => [$position, $reason]) {
        LogisticsData::save('tyre_logs', null, ['changed_on' => date('Y-m-d', strtotime('-' . (6 - $i) . ' days')), 'vehicle_id' => (string) $truck, 'position' => $position, 'reason' => $reason, 'quantity' => '1', 'brand' => '315/80 R22.5', 'supplier_id' => (string) $fitter, 'currency' => 'RWF'], []);
    }
}
if ($truck > 0 && $crane > 0) {
    foreach ([['MSCU1234567', 'loading', '20'], ['TGHU7654321', 'loading', '40']] as $i => [$container, $operation, $size]) {
        LogisticsData::save('crane_logs', null, ['lift_date' => date('Y-m-d', strtotime('-' . (4 - $i) . ' days')), 'vehicle_id' => (string) $truck, 'container_no' => $container, 'operation' => $operation, 'container_size' => $size, 'supplier_id' => (string) $crane, 'currency' => 'RWF'], []);
    }
}

Models\Settings::put('signature_required_to_approve', $previous);
echo "Garage demo loaded: a store with 5 items, a truck at its service with its job card open and parts waiting in the store, 3 tyre changes and 2 crane lifts to pay.\n";
