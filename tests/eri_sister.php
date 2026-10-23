<?php

declare(strict_types=1);

/**
 * ERI specification, section 17: ERI Tanzania (company 3) needs 4 tyres on a
 * truck in Kigali. The supervisor raises F11; ERI Rwanda (company 1) confirms
 * it, which opens a job card and a stores issue note there; the storekeeper
 * issues the tyres; the garage closes the job; and an inter-company invoice is
 * made — a receivable and income in ERI Rwanda, an expense on the truck and a
 * payable in ERI Tanzania. Each company sees only its own books.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_sister', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\Company;
use Models\Garage;
use Models\Ledger;
use Models\LogisticsData;
use Models\RequestForms;

$test = new TestRun('ERI sister-company requests');
$test->stopOn();
$accounts = (new Models\UserRepository())->activeLoginAccounts();
Models\Settings::put('signature_required_to_approve', '0');
$as = static function (string $role) use ($accounts): void {
    test_sign_in($accounts[$role], $role);
};
$bal = static function (int $company, string $code) use ($pdo): float {
    return round((float) $pdo->query("SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM gl_journal_lines l INNER JOIN gl_accounts a ON a.id = l.account_id INNER JOIN gl_journal_entries e ON e.id = l.entry_id WHERE a.account_code = '{$code}' AND e.company_id = {$company}")->fetchColumn(), 2);
};

// Rates between the base currencies: 1 TZS = 0.5 RWF (so 1 RWF = 2 TZS).
$pdo->exec("INSERT INTO currency_rates (currency_code, effective_from, rate, source) VALUES ('TZS', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 0.5, 'test')");
Models\Currency::flush();

// ERI Rwanda's store and tyre stock (company 1).
$pdo->exec("INSERT INTO warehouses (company_id, warehouse_code, warehouse_name, status) VALUES (1, 'KGL', 'Kigali store', 'active')");
$store = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO inventory_items (company_id, warehouse_id, sku, item_name, category, unit_of_measure, quantity, minimum_level, unit_cost, status) VALUES (1, ?, 'TY', 'Tyre 315/80 R22.5', 'Tyres', 'pcs', 0, 2, 380000, 'in_stock')")->execute([$store]);
$tyre = (int) $pdo->lastInsertId();
Models\StockLedger::openingBalance($tyre, 10, 380000);

// A Tanzania truck currently in Kigali (company 3).
$pdo->exec("INSERT INTO vehicles (company_id, plate_number, fleet, vehicle_type, status, mileage, created_at) VALUES (3, 'T 123 ABC', 'container', 'Truck', 'available', 200000, NOW())");
$truck = (int) $pdo->lastInsertId();

// Shomari, the Operational Manager of ERI Tanzania (company 3), raises F11 to ERI Rwanda (company 1).
$shomari = $pdo->query("SELECT id, full_name AS name, email FROM users WHERE email = 'shomari@itec.rw'")->fetch(PDO::FETCH_ASSOC);
test_sign_in($shomari, 'logistics_manager');
$test->same(3, (int) \current_company_id(), 'the ERI Tanzania OM works in his own company');
$f11 = RequestForms::create([
    'form_code' => 'F11', 'to_company_id' => 1, 'vehicle_id' => $truck,
    'title' => '4 tyres for T 123 ABC', 'notes' => 'Two front tyres worn, two burst',
], [
    ['item_code' => 'tyre', 'vehicle_id' => $truck, 'description' => 'Tyre 315/80 R22.5', 'quantity' => 4, 'unit' => 'tyre'],
]);
RequestForms::send($f11);
$test->same('sent', RequestForms::find($f11)['status'], 'it is sent to ERI Rwanda');

// The ERI Rwanda OM confirms it.
$as('logistics_manager');
$test->same(1, (int) \current_company_id(), 'the receiving OM works in ERI Rwanda');
$seen = RequestForms::find($f11);
$test->assert($seen !== null, 'the receiving OM can see the sister request');
$test->same('confirm', RequestForms::pendingStep($seen), 'it waits for the OM to confirm');
$test->same(null, RequestForms::refusal($seen, 'confirm'), 'and the receiving OM may confirm it');
RequestForms::confirm($f11, 'Approved, our store has the tyres');

$job = (int) $pdo->query("SELECT id FROM maintenance_orders WHERE request_id = {$f11}")->fetchColumn();
$test->assert($job > 0, 'a job card opens in ERI Rwanda');
$jobRow = Garage::job($job);
$test->same(1, (int) $jobRow['company_id'], 'the job card is ERI Rwanda\'s');
$test->same(3, (int) $jobRow['owner_company_id'], 'for ERI Tanzania\'s truck');
$f4 = (int) $pdo->query("SELECT id FROM form_requests WHERE form_code = 'F4' AND job_card_id = {$job}")->fetchColumn();
$test->assert($f4 > 0, 'with a stores issue note (the tyre matched the store by name)');

// The ERI Rwanda storekeeper issues the 4 tyres.
$as('warehouse_manager');
$line = (int) RequestForms::lines($f4)[0]['id'];
Garage::issue($f4, [$line => 4]);
$test->same(6.0, (float) $pdo->query("SELECT quantity FROM inventory_items WHERE id = {$tyre}")->fetchColumn(), '4 of 10 tyres issued, 6 left');

// The garage closes the job.
$as('warehouse_manager');
Garage::close($job, ['work_done' => 'Fitted 4 tyres', 'odometer' => 200000]);
$test->same('completed', $pdo->query("SELECT status FROM maintenance_orders WHERE id = {$job}")->fetchColumn(), 'the job is closed');

// The inter-company invoice posts on both companies' books.
$ici = $pdo->query("SELECT * FROM intercompany_invoices WHERE job_card_id = {$job}")->fetch();
$test->assert($ici !== false, 'an inter-company invoice is made');
$test->same(1520000.0, (float) $ici['total_amount'], '4 tyres at 380,000 = 1,520,000 RWF');
$test->same(1520000.0, $bal(1, '1170'), 'ERI Rwanda is owed 1,520,000 (receivable from sister)');
$test->same(-1520000.0, $bal(1, '4300'), 'ERI Rwanda earns service income 1,520,000');
$test->same(-1520000.0, $bal(1, '1200'), 'the tyres left ERI Rwanda\'s stock');
$test->same(3040000.0, $bal(3, '6000'), 'ERI Tanzania carries the repair on the truck: 1,520,000 RWF = 3,040,000 TZS');
$test->same(-3040000.0, $bal(3, '2310'), 'and owes its sister 3,040,000 TZS');

// The receivable is ERI Rwanda's alone: Tanzania booked a payable, not a receivable.
$test->same(0.0, $bal(3, '1170'), 'ERI Tanzania has no receivable from the work: it owes, it is not owed');
$test->same(0.0, $bal(1, '2310'), 'and ERI Rwanda has no payable: it is owed, it does not owe');

$test->assert(Ledger::isBalanced(), 'both sets of books balance');
$test->finish();
