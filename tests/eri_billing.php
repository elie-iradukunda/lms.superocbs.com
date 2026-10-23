<?php

declare(strict_types=1);

/**
 * ERI specification, sections 6, 13 and 18 end to end: a delivery note short
 * above the customer's tolerance raises a deduction (F9) the driver can
 * explain; authorized, it goes on the payroll list. The customer invoice (F13)
 * is priced from the rate card per litre, shows the customer's deduction,
 * posts when the MD authorizes it and becomes the customer's invoice. A
 * supplier answers an order and invoices it from the portal, and the invoice
 * is matched against the order and what was received.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_billing', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\CustomerBilling;
use Models\Ledger;
use Models\LogisticsData;
use Models\Payroll;
use Models\RequestForms;
use Models\Shortages;
use Models\SupplierPortal;

$test = new TestRun('ERI billing, shortages and suppliers');
$test->stopOn();
$accounts = (new Models\UserRepository())->activeLoginAccounts();
Models\Settings::put('signature_required_to_approve', '0');
$as = static function (string $role) use ($accounts): void {
    test_sign_in($accounts[$role], $role);
};
$save = static function (string $module, array $input, ?int $id = null) use ($test): ?int {
    $errors = LogisticsData::validate($module, $input, [], $id);
    $test->same([], $errors, "{$module} saves");
    $saved = LogisticsData::save($module, $id, $input, []);
    LogisticsData::takeOneTimePassword();

    return $saved;
};
$balance = static function (string $code) use ($pdo): float {
    return round((float) $pdo->query("SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM gl_journal_lines l INNER JOIN gl_accounts a ON a.id = l.account_id INNER JOIN gl_journal_entries e ON e.id = l.entry_id WHERE a.account_code = '{$code}' AND e.company_id = 1")->fetchColumn(), 2);
};

// ------------------------------------------------------------------ set-up
$as('super_admin');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$routeId = $save('routes', ['route_code' => 'KGL-MBA-KGL', 'route_name' => 'Kigali – Mombasa – Kigali', 'origin' => 'Kigali', 'turnaround_point' => 'Mombasa', 'destination' => 'Kigali', 'round_trip' => '1', 'crosses_border' => '1', 'is_active' => '1', 'expected_days' => '10', 'max_return_days' => '3', 'total_km' => '3000']);
$jambo = $save('customers', ['customer_name' => 'Jambo oil factory', 'customer_type' => 'corporate', 'status' => 'active', 'payment_terms_days' => '30', 'currency' => 'RWF']);
$as('finance');
$save('rates', ['rate_code' => 'JAMBO-L', 'service_type' => 'full_truck', 'customer_id' => (string) $jambo, 'route_id' => (string) $routeId, 'cargo_type' => 'litres', 'status' => 'active', 'rate_type' => 'per_litre', 'rate_amount' => '25', 'currency' => 'RWF', 'effective_from' => $yesterday]);
$test->same('Kigali', $pdo->query("SELECT origin FROM rate_cards WHERE rate_code = 'JAMBO-L'")->fetchColumn(), 'a card on a route takes its places from the route');
$save('shortage_rules', ['customer_id' => (string) $jambo, 'cargo_type' => 'litres', 'tolerance_type' => 'percent', 'tolerance_value' => '0.5', 'charge' => 'excess', 'unit_value' => '1500', 'currency' => 'RWF', 'valid_from' => $yesterday, 'is_active' => '1']);

$as('logistics_manager');
$truck = $save('vehicles', ['plate_number' => 'RAK 205D', 'fleet' => 'tanker', 'vehicle_type' => 'Tanker', 'max_payload_t' => '30', 'cargo_tank_l' => '36000', 'normal_l_per_100km' => '40', 'status' => 'available', 'mileage' => '100000']);
$driver = $save('drivers', ['full_name' => 'BYIRINGIRO EMMANUEL', 'nationality' => 'Rwandan', 'phone' => '+250 788 200000', 'payment_method' => 'momo', 'momo_number' => '0788100001', 'momo_name' => 'BYIRINGIRO EMMANUEL', 'status' => 'active', 'fleet' => 'tanker', 'salary' => '300000', 'salary_currency' => 'RWF']);
$pdo->exec("UPDATE drivers SET salary = 300000, salary_currency = 'RWF' WHERE id = {$driver}");
$pdo->prepare("INSERT INTO trips (company_id, reference_code, customer_id, route_id, vehicle_id, driver_id, pickup_location, destination, status, cargo_type, cargo_quantity, cargo_summary, loaded_quantity, departure_at, created_at)
               VALUES (1, 'TRP-TEST-0001', ?, ?, ?, ?, 'Kigali', 'Mombasa', 'offloading', 'litres', 36000, 'Diesel', 36000, NOW(), NOW())")
    ->execute([$jambo, $routeId, $truck, $driver]);
$tripId = (int) $pdo->lastInsertId();

// ---------------------------------------------------- delivery note (F8)
$noteId = Models\DeliveryNotes::submit($tripId, ['loaded' => '36000', 'delivered' => '35700', 'received_by' => 'Jambo store keeper'], 'requests/note.jpg');
$test->same(300.0, (float) RequestForms::find($noteId)['payload']['shortage'], 'loaded 36,000, delivered 35,700: 300 L short');
RequestForms::confirm($noteId, 'Checked');
$f9 = (int) $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F9' AND trip_id = {$tripId}")->fetchColumn();
$test->assert($f9 > 0, 'above the 0.5 % tolerance (180 L), the confirmed note raises a deduction (F9)');
$f9Row = RequestForms::find($f9);
$test->same(180000.0, (float) $f9Row['total_amount'], 'the 120 L above tolerance at the customer\'s 1,500 = 180,000');
$test->same('sent', $f9Row['status'], 'and it waits for Finance');

// The driver explains.
$test->throws(static fn () => Shortages::explain($f9, $driver + 1, 'Not mine'), 'only the driver of the trip can explain it');
Shortages::explain($f9, $driver, 'The customer meter was not calibrated');
$test->same('The customer meter was not calibrated', RequestForms::find($f9)['payload']['explanation'], 'the explanation is on the request for Finance and the MD');

$as('finance');
RequestForms::verify($f9);
$test->same(180000.0, $balance('1160'), 'verified: the driver owes the shortage');
$as('management');
RequestForms::authorize($f9);
$test->same(1, (int) $pdo->query("SELECT COUNT(*) FROM driver_deductions WHERE request_id = {$f9} AND status = 'pending'")->fetchColumn(), 'authorized: the deduction waits for the next payroll');

// ------------------------------------------------------- payroll list
$as('finance');
$sheet = Payroll::sheet(1, date('Y-m'));
$row = array_values(array_filter($sheet, static fn (array $r): bool => (int) $r['id'] === $driver))[0];
$test->same(120000.0, (float) $row['net'], 'the payroll list shows 300,000 less 180,000');
Payroll::close(1, date('Y-m'));
$test->same('deducted', $pdo->query("SELECT status FROM driver_deductions WHERE request_id = {$f9}")->fetchColumn(), 'closing the month marks the deduction taken');
$test->same(0.0, $balance('1160'), 'and clears the driver receivable');
$test->throws(static fn () => Payroll::close(1, date('Y-m')), 'a month closes once');

// ------------------------------------------------- customer invoice (F13)
$as('finance');
$billable = CustomerBilling::billable($jambo, date('Y-m-01'), date('Y-m-d'));
$test->same(1, count($billable), 'the delivered trip is ready to invoice');
$test->same(892500.0, $billable[0]['price']['amount'], 'priced from the rate card: 35,700 L delivered × 25');
[$f13] = CustomerBilling::build($jambo, date('Y-m-01'), date('Y-m-d'), [$tripId], 'LPO-77');
$test->same(712500.0, (float) RequestForms::find($f13)['total_amount'], 'the customer deducts 180,000: 712,500 to pay');
$test->same([], CustomerBilling::billable($jambo, date('Y-m-01'), date('Y-m-d')), 'a trip on an invoice is not offered again (control 7)');
RequestForms::send($f13);
$test->same(0.0, $balance('1100'), 'nothing posts before the MD authorizes');
$as('management');
RequestForms::authorize($f13);
$test->same(712500.0, $balance('1100'), 'authorized: the customer owes 712,500');
$test->same(-892500.0, $balance('4000'), 'freight revenue 892,500');
$test->same(0.0, $balance('5900'), 'the shortage the customer took is recovered from the driver: no loss left');
$invoice = $pdo->query("SELECT * FROM invoices WHERE request_id = {$f13}")->fetch();
$test->assert($invoice !== false && (float) $invoice['total_amount'] === 712500.0 && $invoice['status'] === 'issued', 'the customer\'s invoice is issued');
$test->same('LPO-77', $invoice['lpo_number'], 'with their order number');
$entriesBefore = (int) $pdo->query('SELECT COUNT(*) FROM gl_journal_entries')->fetchColumn();
Models\Posting::tryPost('invoice', (int) $invoice['id']);
$test->same($entriesBefore, (int) $pdo->query('SELECT COUNT(*) FROM gl_journal_entries')->fetchColumn(), 'the invoice does not post a second time');

// The customer pays.
$as('finance');
$save('payments', ['payment_code' => 'RCPT-1', 'invoice_id' => (string) $invoice['id'], 'amount' => '712500', 'currency' => 'RWF', 'method' => 'bank_transfer', 'reference' => 'BK-9981', 'paid_at' => date('Y-m-d H:i:s')]);
$test->same('paid', $pdo->query("SELECT status FROM invoices WHERE id = {$invoice['id']}")->fetchColumn(), 'paid in full, the invoice is paid');
$test->same(0.0, $balance('1100'), 'and nothing is owed');

// -------------------------------------------------- supplier portal (18)
$as('super_admin');
$supplier = $save('suppliers', ['supplier_name' => 'ERI Rwanda Spare Parts', 'status' => 'active', 'payment_terms_days' => '30', 'payment_method' => 'bank', 'bank_name' => 'BK', 'bank_account' => '1']);
$pdo->exec("INSERT INTO warehouses (company_id, warehouse_code, warehouse_name, status) VALUES (1, 'S1', 'Store', 'active')");
$pdo->exec("INSERT INTO inventory_items (company_id, warehouse_id, sku, item_name, unit_of_measure, quantity, minimum_level, unit_cost, status) VALUES (1, LAST_INSERT_ID(), 'WS', 'Water separator', 'pcs', 0, 1, 40000, 'out_of_stock')");
$item = (int) $pdo->lastInsertId();
$role = (int) $pdo->query("SELECT id FROM roles WHERE role_key = 'supplier'")->fetchColumn();
$pdo->prepare("INSERT INTO users (role_id, company_id, supplier_id, full_name, email, password_hash, status) VALUES (?, 1, ?, 'Spare parts desk', 'parts@supplier.test', ?, 'active')")->execute([$role, $supplier, password_hash('password', PASSWORD_DEFAULT)]);
$supplierUser = ['id' => (int) $pdo->lastInsertId(), 'name' => 'Spare parts desk', 'email' => 'parts@supplier.test'];

$as('warehouse_manager');
$po = RequestForms::create(['form_code' => 'F5', 'title' => 'Water separators', 'supplier_id' => $supplier, 'vehicle_id' => $truck], [
    ['item_code' => 'part', 'item_id' => $item, 'description' => 'Water separator', 'quantity' => 2, 'unit_price' => 45000],
    ['item_code' => 'part', 'description' => 'Diesel pipe', 'quantity' => 1, 'unit_price' => 15000],
]);
RequestForms::send($po);
$as('finance');
RequestForms::verify($po);
$as('management');
RequestForms::authorize($po);

test_sign_in($supplierUser, 'supplier');
$test->same(1, count(SupplierPortal::orders($supplier)), 'the supplier sees its order');
$test->throws(static fn () => SupplierPortal::answer($po, $supplier, 'decline', ''), 'declining needs a reason');
SupplierPortal::answer($po, $supplier, 'accept', '');
$test->throws(static fn () => SupplierPortal::answer($po, $supplier, 'accept', ''), 'an order is answered once');

$as('warehouse_manager');
$poLines = RequestForms::lines($po);
Models\Store::receive($po, [(int) $poLines[0]['id'] => 2], 'DN-1');

test_sign_in($supplierUser, 'supplier');
$test->throws(static fn () => SupplierPortal::submitInvoice($po, $supplier, 'SP-100', date('Y-m-d'), [(int) $poLines[0]['id'] => '2'], [], null), 'the scan of the invoice is required');
$f12 = SupplierPortal::submitInvoice($po, $supplier, 'SP-100', date('Y-m-d'), [(int) $poLines[0]['id'] => '2', (int) $poLines[1]['id'] => '1'], [(int) $poLines[0]['id'] => '47000'], 'requests/sp100.pdf');
$test->same('sent', RequestForms::find($f12)['status'], 'the invoice goes to Finance');
$test->throws(static fn () => SupplierPortal::submitInvoice($po, $supplier, 'SP-100', date('Y-m-d'), [(int) $poLines[0]['id'] => '1'], [], 'requests/x.pdf'), 'the same invoice number cannot be sent twice');
$match = SupplierPortal::match($f12);
$test->assert(str_contains(implode(' ', $match[0]['problems']), 'price 47,000.00 instead of 45,000.00'), 'a price above the order is highlighted');
$test->assert(str_contains(implode(' ', $match[1]['problems']), 'only 0 received'), 'so is a line invoiced but not received');

$as('finance');
RequestForms::verify($f12);
$test->same(0.0, $balance('2050') + 90000.0 - 94000.0 + 4000.0, 'the store lines clear goods received (90,000) and the price difference stays visible');
$test->same(-109000.0, $balance('2000'), 'the supplier is owed what was invoiced: 94,000 + 15,000');

$test->assert(Ledger::isBalanced(), 'the books balance');
$test->finish();
