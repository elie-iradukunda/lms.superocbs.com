<?php

declare(strict_types=1);

/**
 * ERI specification, section 14: each company's books in its own base
 * currency, every entry carrying its rate, exchange gains and losses on
 * payment, and one company never seeing another's books.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_accounting', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\Currency;
use Models\Ledger;
use Models\Payments;
use Models\RequestForms;

$test = new TestRun('ERI accounting');
$test->stopOn();
$accounts = (new Models\UserRepository())->activeLoginAccounts();
Models\Settings::put('signature_required_to_approve', '0');

// Rates against the system base (RWF).
$pdo->exec("INSERT INTO currency_rates (currency_code, effective_from, rate, source) VALUES ('USD', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 1300, 'BNR'), ('KES', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 10, 'BNR')");
Currency::flush();
$test->same(130.0, Currency::crossRate('USD', 'KES', date('Y-m-d')), 'a cross rate goes through the base: 1 USD = 1300 / 10 = 130 KES');

// ERI Rwanda pays a supplier 100 USD.
test_sign_in($accounts['logistics_manager'], 'logistics_manager');
$requestId = RequestForms::create(['form_code' => 'F15', 'title' => 'Port charges in USD', 'currency' => 'USD'], [[
    'item_code' => 'shipping', 'description' => 'Port handling', 'quantity' => 1, 'amount' => 100,
    'payee_type' => 'other', 'payee_name' => 'Mombasa Port Agent', 'payment_method' => 'bank',
]]);
RequestForms::send($requestId);
test_sign_in($accounts['finance'], 'finance');
RequestForms::verify($requestId);
$entry = $pdo->query("SELECT * FROM gl_journal_entries WHERE source_type = 'request_v1' AND source_id = {$requestId}")->fetch();
$test->same('USD', $entry['currency'], 'the entry keeps the original currency');
$test->same('RWF', $entry['base_currency'], 'ERI Rwanda keeps its books in RWF');
$test->same(1300.0, (float) $entry['exchange_rate'], 'the rate used is stored on the entry');
$base = $pdo->query("SELECT SUM(base_debit) FROM gl_journal_lines WHERE entry_id = {$entry['id']}")->fetchColumn();
$test->same(130000.0, (float) $base, 'and the base-currency amount: 100 USD = 130,000 RWF');

test_sign_in($accounts['management'], 'management');
RequestForms::authorize($requestId);

// The rate moves before the Cashier pays: 1 USD = 1350 RWF.
$pdo->exec("INSERT INTO currency_rates (currency_code, effective_from, rate, source) VALUES ('USD', CURDATE(), 1350, 'BNR')");
Currency::flush();
test_sign_in($accounts['cashier'], 'cashier');
$batch = Payments::build(array_column(Payments::toPay(), 'key'));
Payments::record($batch, 'BANK-REF-1', 'payment_batches/proof.pdf');
$fx = $pdo->query("SELECT * FROM gl_journal_entries WHERE source_type = 'fx_difference'")->fetch();
$test->assert($fx !== false, 'paying at a different rate posts an exchange difference');
$loss = $pdo->query("SELECT l.debit FROM gl_journal_lines l INNER JOIN gl_accounts a ON a.id = l.account_id WHERE l.entry_id = {$fx['id']} AND a.account_code = '6950'")->fetchColumn();
$test->same(5000.0, (float) $loss, 'paid 135,000 RWF for what was booked at 130,000: an exchange loss of 5,000 RWF');
$test->same('RWF', $fx['currency'], 'the exchange difference is in the base currency');

// In base currency, the payable is cleared to zero.
test_sign_in($accounts['finance'], 'finance');
Ledger::inCurrency(Ledger::BASE);
$payable = 0.0;
foreach (Ledger::balances() as $row) {
    if ($row['account_code'] === '2000') {
        $payable = (float) $row['closing'];
    }
}
$test->same(0.0, round($payable, 2), 'converted, the supplier payable is fully settled');
$test->assert(Ledger::isBalanced(), 'the books balance');

// Opening balances from Tally: loaded, checked by Finance, signed off by the MD.
test_sign_in($accounts['finance'], 'finance');
$parsed = Models\OpeningBalances::parse('ledger', "account_code,currency,debit,credit,description\n1020,RWF,\"1,000,000\",0,Bank\n2000,RWF,0,300000,Suppliers\n3100,RWF,0,650000,Retained earnings\n9999,RWF,1,0,Unknown\n");
$test->assert(str_contains(implode(' ', $parsed['problems']), '9999'), 'an account not in the chart is refused');
$parsed = Models\OpeningBalances::parse('ledger', "account_code,currency,debit,credit,description\n1020,RWF,\"1,000,000\",0,Bank\n2000,RWF,0,300000,Suppliers\n3100,RWF,0,650000,Retained earnings\n");
$test->same([], $parsed['problems'], 'a clean Tally trial balance is accepted');
$openingId = Models\OpeningBalances::save(1, 'ledger', date('Y-m-01'), $parsed['rows']);
$test->throws(static fn () => Models\OpeningBalances::signOff($openingId), 'nothing posts before Finance checks it');
Models\OpeningBalances::check($openingId);
$test->throws(static fn () => Models\OpeningBalances::signOff($openingId), 'the person who checked cannot sign off');
test_sign_in($accounts['management'], 'management');
Models\OpeningBalances::signOff($openingId);
$opening = $pdo->query("SELECT * FROM gl_journal_entries WHERE source_type = 'opening'")->fetch();
$test->assert($opening !== false && $opening['entry_date'] === date('Y-m-d', strtotime(date('Y-m-01') . ' -1 day')), 'the opening entry is dated the day before go-live');
$equity = (float) $pdo->query("SELECT SUM(l.credit) FROM gl_journal_lines l INNER JOIN gl_accounts a ON a.id = l.account_id WHERE l.entry_id = {$opening['id']} AND a.account_code = '3900'")->fetchColumn();
$test->same(50000.0, $equity, 'the 50,000 difference goes to Opening balance equity');

$pdo->exec("INSERT INTO customers (company_id, customer_code, customer_name, status, currency) VALUES (1, 'C-JAMBO', 'Jambo oil factory', 'active', 'RWF')");
test_sign_in($accounts['finance'], 'finance');
$parsed = Models\OpeningBalances::parse('customers', "customer,currency,amount,reference,due_date\nC-JAMBO,RWF,3500000,INV 0441," . date('Y-m-d', strtotime('+15 days')) . "\n");
$customersId = Models\OpeningBalances::save(1, 'customers', date('Y-m-01'), $parsed['rows']);
Models\OpeningBalances::check($customersId);
test_sign_in($accounts['management'], 'management');
$entriesBefore = (int) $pdo->query('SELECT COUNT(*) FROM gl_journal_entries')->fetchColumn();
Models\OpeningBalances::signOff($customersId);
$invoice = $pdo->query('SELECT * FROM invoices WHERE is_opening = 1')->fetch();
$test->assert($invoice !== false && (float) $invoice['total_amount'] === 3500000.0, 'an open customer balance becomes an open invoice, to be paid and aged');
$test->same($entriesBefore, (int) $pdo->query('SELECT COUNT(*) FROM gl_journal_entries')->fetchColumn(), 'it does not post twice: it is already in the trial balance');

// ERI Tanzania does not see ERI Rwanda's books.
$tanzania = $pdo->query("SELECT id, full_name AS name, email FROM users WHERE email = 'shomari@itec.rw'")->fetch(\PDO::FETCH_ASSOC);
test_sign_in($tanzania, 'logistics_manager');
Ledger::inCurrency(null);
$test->same([], Ledger::balances(), 'another company sees none of these entries');

$test->finish();
