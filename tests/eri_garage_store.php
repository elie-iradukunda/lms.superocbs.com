<?php

declare(strict_types=1);

/**
 * ERI specification, section 12 end to end: a truck reaches 7,000 km and a
 * service request (F3) is raised; once authorized a job card opens and the
 * parts go to the store on an issue note (F4); the storekeeper issues (stock
 * down, cost on the truck); what is missing is ordered (F5), received and
 * invoiced (F12); the mechanic closes the job; a stock count is approved; the
 * tyre and crane logs build F6 and F7.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_garage_store', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\Fleet;
use Models\Garage;
use Models\Ledger;
use Models\LogisticsData;
use Models\RequestForms;
use Models\ServiceLogs;
use Models\Store;

$test = new TestRun('ERI garage and store');
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
    $saved = LogisticsData::save($module, $id, $input, []);
    LogisticsData::takeOneTimePassword();

    return $saved;
};
$balance = static function (string $code) use ($pdo): float {
    return round((float) $pdo->query("SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM gl_journal_lines l INNER JOIN gl_accounts a ON a.id = l.account_id INNER JOIN gl_journal_entries e ON e.id = l.entry_id WHERE a.account_code = '{$code}' AND e.company_id = 1")->fetchColumn(), 2);
};
$onHand = static fn (int $item): float => (float) $pdo->query("SELECT quantity FROM inventory_items WHERE id = {$item}")->fetchColumn();

// ------------------------------------------------------------------ set-up
$as('super_admin');
$pdo->exec("INSERT INTO warehouses (company_id, warehouse_code, warehouse_name, status) VALUES (1, 'KGL-STORE', 'Kigali main store', 'active')");
$store = (int) $pdo->lastInsertId();
$item = static function (string $sku, string $name, float $qty, float $cost, string $unit = 'pcs', float $minimum = 1) use ($pdo, $store): int {
    $pdo->prepare("INSERT INTO inventory_items (company_id, warehouse_id, sku, item_name, category, unit_of_measure, quantity, minimum_level, unit_cost, status) VALUES (1, ?, ?, ?, 'Spare parts', ?, 0, ?, ?, 'in_stock')")
        ->execute([$store, $sku, $name, $unit, $minimum, $cost]);
    $id = (int) $pdo->lastInsertId();
    Models\StockLedger::openingBalance($id, $qty, $cost);

    return $id;
};
$dieselFilter = $item('DF-01', 'Diesel filter', 5, 12000);
$oilFilter = $item('OF-01', 'Oil filter', 1, 8000);
$engineOil = $item('EO-15W40', 'Engine oil 15W40', 40, 5000, 'L', 20);
$partsSupplier = $save('suppliers', ['supplier_name' => 'ERI Rwanda Spare Parts', 'status' => 'active', 'payment_terms_days' => '30', 'payment_method' => 'bank', 'bank_name' => 'BK', 'bank_account' => '000123']);
$fitter = $save('suppliers', ['supplier_name' => 'Tyre fitter Gikondo', 'status' => 'active', 'payment_terms_days' => '7', 'payment_method' => 'momo', 'momo_number' => '0788555111', 'momo_name' => 'HABIMANA JEAN']);
$crane = $save('suppliers', ['supplier_name' => 'Crane operator Masaka', 'status' => 'active', 'payment_terms_days' => '7', 'payment_method' => 'momo', 'momo_number' => '0788555222', 'momo_name' => 'UWASE ALICE']);
$yesterday = date('Y-m-d', strtotime('-1 day'));
$save('service_rates', ['rate_type' => 'tyre', 'description' => 'Tyre fitting', 'variant' => 'per_tyre', 'unit' => 'tyre', 'amount' => '5000', 'currency' => 'RWF', 'supplier_id' => (string) $fitter, 'valid_from' => $yesterday, 'is_active' => '1']);
$save('service_rates', ['rate_type' => 'crane', 'description' => 'Crane lift 20 ft loading', 'variant' => '20ft_loading', 'unit' => 'lift', 'amount' => '30000', 'currency' => 'RWF', 'supplier_id' => (string) $crane, 'valid_from' => $yesterday, 'is_active' => '1']);

$as('logistics_manager');
$truck = $save('vehicles', ['plate_number' => 'RAK 300A', 'fleet' => 'tanker', 'vehicle_type' => 'Tanker', 'max_payload_t' => '30', 'cargo_tank_l' => '36000', 'normal_l_per_100km' => '40', 'status' => 'available', 'mileage' => '7100', 'last_service_km' => '0']);
$test->same('due', Fleet::serviceState(Fleet::vehicle($truck))['state'], 'at 7,100 km with no service yet, the truck is due');

// --------------------------------------------- the system raises the F3
$test->same(1, Garage::raiseDueServices(), 'the clock raises a draft service request');
$test->same(0, Garage::raiseDueServices(), 'once: not again while it is open');
$f3 = (int) $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F3' AND vehicle_id = {$truck}")->fetchColumn();
$test->same('draft', RequestForms::find($f3)['status'], 'it waits for the OM as a draft');

// The OM confirms what is needed and sends it.
RequestForms::update($f3, ['title' => 'Service 7,000 km — RAK 300A', 'vehicle_id' => $truck, 'payload' => ['odometer' => '7100', 'problem' => 'Service due']], [
    ['item_code' => 'service', 'description' => 'Service at 7,100 km', 'quantity' => 1, 'amount' => 0],
    ['item_code' => 'part', 'item_id' => $dieselFilter, 'description' => 'Diesel filter', 'quantity' => 1, 'unit_price' => 12000],
    ['item_code' => 'part', 'item_id' => $oilFilter, 'description' => 'Oil filter', 'quantity' => 2, 'unit_price' => 8000],
    ['item_code' => 'part', 'item_id' => $engineOil, 'description' => 'Engine oil', 'quantity' => 25, 'unit' => 'L', 'unit_price' => 5000],
    ['item_code' => 'part', 'description' => 'Water separator', 'quantity' => 1, 'unit_price' => 45000],
    ['item_code' => 'labour', 'description' => 'Labour', 'quantity' => 1, 'unit_price' => 30000],
]);
RequestForms::send($f3);
$as('finance');
RequestForms::verify($f3);
$test->same(0, (int) $pdo->query("SELECT COUNT(*) FROM gl_journal_entries WHERE source_type LIKE 'request%' AND source_id = {$f3}")->fetchColumn(), 'a service request posts nothing: the cost posts as parts are issued');
$as('management');
RequestForms::authorize($f3);

$job = (int) $pdo->query("SELECT id FROM maintenance_orders WHERE request_id = {$f3}")->fetchColumn();
$test->assert($job > 0, 'authorized, a job card opens');
$test->same('in_garage', Fleet::vehicle($truck)['status'], 'and the truck is In garage');
$f4 = (int) $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F4' AND job_card_id = {$job}")->fetchColumn();
$test->same('sent', RequestForms::find($f4)['status'], 'the issue note (F4) waits in the store');
$test->same(3, count(RequestForms::lines($f4)), 'with the three store items');
$f5 = (int) $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F5' AND job_card_id = {$job}")->fetchColumn();
$test->assert($f5 > 0 && RequestForms::find($f5)['status'] === 'draft', 'the part the store does not carry is on a draft purchase order (F5)');

// ------------------------------------------------------- the store issues
$as('warehouse_manager');
$lines = [];
foreach (Garage::noteLines($f4) as $line) {
    $lines[(int) $line['item_id']] = (int) $line['id'];
}
$test->throws(static fn () => Garage::issue($f4, [$lines[$oilFilter] => 2]), 'cannot issue more than the store holds');
Garage::issue($f4, [$lines[$dieselFilter] => 1, $lines[$oilFilter] => 1, $lines[$engineOil] => 25]);
$test->same(4.0, $onHand($dieselFilter), 'stock goes down: 5 diesel filters, 4 left');
$test->same(15.0, $onHand($engineOil), '40 L oil, 15 L left');
$test->same(145000.0, $balance('6000'), 'the value issued (12,000 + 8,000 + 125,000) is charged to repairs');
$test->same(-145000.0, $balance('1200'), 'and taken out of stock');
$onTruck = (float) $pdo->query("SELECT SUM(l.debit) FROM gl_journal_lines l INNER JOIN gl_accounts a ON a.id = l.account_id WHERE a.account_code = '6000' AND l.vehicle_id = {$truck}")->fetchColumn();
$test->same(145000.0, $onTruck, 'on the truck');
$test->same(3, count(Garage::parts($job)), 'the parts are on the job card');
$test->same('sent', RequestForms::find($f4)['status'], 'one oil filter is still owed, so the note stays open');

// Closing with parts still waiting is refused.
$as('warehouse_manager');
$test->throws(static fn () => Garage::close($job, ['work_done' => 'Service', 'odometer' => 7150]), 'the job cannot close while the store owes parts');

// The missing oil filter is ordered, authorized, received and issued.
$as('warehouse_manager');
$shortfall = Garage::orderShortfall($f4);
$test->same(1.0, (float) RequestForms::lines($shortfall)[0]['quantity'], 'the shortfall order asks for the 1 missing oil filter');
RequestForms::update($shortfall, ['title' => 'Oil filter', 'supplier_id' => $partsSupplier, 'vehicle_id' => $truck, 'parent_request_id' => $f4, 'job_card_id' => $job], [
    ['item_code' => 'part', 'item_id' => $oilFilter, 'description' => 'Oil filter', 'quantity' => 1, 'unit_price' => 9000],
]);
RequestForms::send($shortfall);
$as('finance');
RequestForms::verify($shortfall);
$test->same(0.0, $balance('2050'), 'an order is a commitment: nothing posts yet');
$as('management');
RequestForms::authorize($shortfall);
$as('warehouse_manager');
$orderLine = (int) RequestForms::lines($shortfall)[0]['id'];
$test->throws(static fn () => Store::receive($shortfall, [$orderLine => 2]), 'cannot receive more than was ordered');
Store::receive($shortfall, [$orderLine => 1], 'DN-5521');
$test->same(1.0, $onHand($oilFilter), 'the oil filter is in the store');
$test->same(9000.0, (float) $pdo->query("SELECT unit_cost FROM inventory_items WHERE id = {$oilFilter}")->fetchColumn(), 'at the price paid');
$test->same(-9000.0, $balance('2050'), 'goods received, not yet invoiced');
$test->same('closed', RequestForms::find($shortfall)['status'], 'fully received, the order closes');
Garage::issue($f4, [$lines[$oilFilter] => 1]);
$test->same('closed', RequestForms::find($f4)['status'], 'everything issued, the note closes');

// The supplier invoice clears goods received instead of booking the cost again.
$as('warehouse_manager');
$f12 = RequestForms::create(['form_code' => 'F12', 'title' => 'Invoice SP-881', 'supplier_id' => $partsSupplier, 'parent_request_id' => $shortfall], [
    ['item_code' => 'part', 'description' => 'Oil filter', 'quantity' => 1, 'unit_price' => 9000, 'payee_type' => 'supplier', 'payee_id' => $partsSupplier, 'payee_name' => 'ERI Rwanda Spare Parts', 'payment_method' => 'bank'],
]);
RequestForms::send($f12);
$as('finance');
RequestForms::verify($f12);
$test->same(0.0, $balance('2050'), 'the invoice clears goods received not invoiced');
$test->same(-9000.0, $balance('2000'), 'and the supplier is owed');

// ------------------------------------------------- the mechanic closes it
$as('warehouse_manager');
Garage::start($job);
$test->throws(static fn () => Garage::close($job, ['work_done' => '', 'odometer' => 7150]), 'the work done is required');
$test->throws(static fn () => Garage::close($job, ['work_done' => 'Service', 'odometer' => 6000]), 'an odometer below the truck\'s km is refused');
Garage::close($job, ['work_done' => 'Changed diesel filter, 2 oil filters and 25 L engine oil', 'odometer' => 7150]);
$vehicle = Fleet::vehicle($truck);
$test->same('available', $vehicle['status'], 'the truck is Available again');
$test->same(14150, Fleet::nextServiceKm($vehicle), 'and the next service is 7,000 km later');
$test->same('completed', $pdo->query("SELECT status FROM maintenance_orders WHERE id = {$job}")->fetchColumn(), 'the job card is closed');
$test->same('closed', RequestForms::find($f3)['status'], 'and so is its service request');
$test->same(0, (int) $pdo->query("SELECT COUNT(*) FROM gl_journal_entries WHERE source_type = 'maintenance' AND source_id = {$job}")->fetchColumn(), 'closing does not post the cost a second time');

// --------------------------------------------------------- a stock count
$as('warehouse_manager');
$count = Store::startCount($store, 'Month-end');
$test->throws(static fn () => Store::startCount($store), 'one open count per store');
$countLines = [];
foreach (Store::count($count)['lines'] as $line) {
    $countLines[(int) $line['item_id']] = (int) $line['id'];
}
$counted = [$countLines[$dieselFilter] => '3', $countLines[$oilFilter] => '0', $countLines[$engineOil] => '15'];
$test->throws(static fn () => Store::saveCount($count, $counted, [], true), 'a difference must be explained');
Store::saveCount($count, $counted, [$countLines[$dieselFilter] => 'One damaged by water'], true);
$test->throws(static fn () => Store::approveCount($count), 'the counter cannot approve');
$as('finance');
Store::approveCount($count);
$test->same(3.0, $onHand($dieselFilter), 'approved, the stock is adjusted');
$test->same(12000.0, $balance('6960'), 'and the loss posts to stock count differences');

// ------------------------------------------------------- tyre and crane
$as('logistics_manager');
$tyre = $save('tyre_logs', ['changed_on' => date('Y-m-d'), 'vehicle_id' => (string) $truck, 'position' => 'front_left', 'reason' => 'worn', 'quantity' => '2', 'supplier_id' => (string) $fitter, 'currency' => 'RWF']);
$test->same(5000.0, (float) $pdo->query("SELECT unit_price FROM tyre_logs WHERE id = {$tyre}")->fetchColumn(), 'the fitting fee comes from the fitter\'s rate');
$test->same(7150, (int) $pdo->query("SELECT odometer_km FROM tyre_logs WHERE id = {$tyre}")->fetchColumn(), 'and the km from the truck');
$liftInput = ['lift_date' => date('Y-m-d'), 'vehicle_id' => (string) $truck, 'container_no' => 'mscu 1234567', 'operation' => 'loading', 'container_size' => '20', 'supplier_id' => (string) $crane, 'currency' => 'RWF'];
$lift = $save('crane_logs', $liftInput);
$test->same('MSCU1234567', $pdo->query("SELECT container_no FROM crane_logs WHERE id = {$lift}")->fetchColumn(), 'the container number is cleaned');
$test->same(30000.0, (float) $pdo->query("SELECT fee FROM crane_logs WHERE id = {$lift}")->fetchColumn(), 'the fee comes from the operator\'s rate');
$test->assert(LogisticsData::validate('crane_logs', $liftInput, [], null) !== [], 'the same container cannot be logged twice for the same lift (control 7)');

$f6 = ServiceLogs::buildRequest('tyre', $fitter, date('Y-m-01'), date('Y-m-d'));
$test->same(10000.0, (float) RequestForms::find($f6)['total_amount'], 'F6 is built from the log: 2 tyres × 5,000');
$test->same('HABIMANA JEAN', RequestForms::lines($f6)[0]['momo_name'], 'paid to the fitter\'s MoMo');
$test->throws(static fn () => ServiceLogs::buildRequest('tyre', $fitter, date('Y-m-01'), date('Y-m-d')), 'a line already on a request is not paid twice');
$test->throws(static fn () => LogisticsData::delete('tyre_logs', $tyre), 'nor deleted');
RequestForms::cancel($f6, 'Wrong period');
$test->same(null, $pdo->query("SELECT request_id FROM tyre_logs WHERE id = {$tyre}")->fetchColumn() ?: null, 'a cancelled request gives the lines back');
$f7 = ServiceLogs::buildRequest('crane', $crane, date('Y-m-01'), date('Y-m-d'));
$test->same('F7', RequestForms::find($f7)['form_code'], 'F7 is built from the crane log');
$test->assert(count(ServiceLogs::tyreLife($truck)) === 1, 'tyre life is read from the same log');

$test->assert(Ledger::isBalanced(), 'the books balance');
$test->finish();
