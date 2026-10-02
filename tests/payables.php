<?php

declare(strict_types=1);

/**
 * The money around a trip that is not freight:
 *
 *   duty paid for the customer -> recharged at cost on the invoice, no VAT ->
 *   recovered when the invoice is paid;
 *   a subcontractor's bill -> approved -> paid in two parts -> aging;
 *   the bank statement for the month -> matched -> a bank charge posted -> reconciled.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_payables');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Payables tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $one = static function (string $sql, array $params = []) use ($pdo): mixed {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    };
    $balance = static function (string $code) use ($one): float {
        return round((float) $one(
            "SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id AND e.deleted_at IS NULL AND e.currency = 'RWF'
               INNER JOIN gl_accounts a ON a.id = l.account_id WHERE a.account_code = ?",
            [$code]
        ), 2);
    };
    $sourceLines = static function (string $type, int $id) use ($pdo): array {
        $statement = $pdo->prepare(
            'SELECT a.account_code, l.debit, l.credit FROM gl_journal_entries e
               INNER JOIN gl_journal_lines l ON l.entry_id = e.id INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE e.source_type = ? AND e.source_id = ? AND e.deleted_at IS NULL ORDER BY l.line_no'
        );
        $statement->execute([$type, $id]);
        $lines = [];
        foreach ($statement->fetchAll() as $line) {
            $lines[$line['account_code']] = round((float) $line['debit'] - (float) $line['credit'], 2);
        }
        return $lines;
    };

    // What the form does: validate, then save. Refusals are validation errors.
    $checked = static function (string $key, array $input): int {
        $errors = Models\LogisticsData::validate($key, $input);
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }
        return Models\LogisticsData::save($key, null, $input);
    };

    // A bank account of its own, so the reconciliation sees only this test's money.
    $pdo->exec("INSERT INTO gl_accounts (account_code, account_name, account_type, normal_balance, report_section, section_order, cash_flow_class, is_bank, is_active)
                VALUES ('1031', 'Test bank RWF', 'asset', 'debit', 'Cash and bank', 10, 'cash', 1, 1)");
    $bankId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO payment_methods (method_key, method_name, gl_account_id, direction, show_on_invoice, sort_order, is_active)
                VALUES ('test_bank', 'Test bank', {$bankId}, 'both', 0, 99, 1)");
    Models\PaymentMethod::flush();
    $test->same('1150', (string) $one("SELECT account_code FROM gl_accounts WHERE account_code = '1150'"), 'the disbursements account exists');

    $customerId = Models\LogisticsData::save('customers', null, [
        'customer_code' => '', 'customer_name' => 'Payables Test Hardware Ltd', 'customer_type' => 'corporate',
        'phone' => '+250 788 100 200', 'email' => 'ap@test.rw', 'payment_terms_days' => '30', 'currency' => 'RWF', 'status' => 'active',
    ]);
    $supplierId = Models\LogisticsData::save('suppliers', null, [
        'supplier_name' => 'Subcontract Trucks Ltd', 'payment_terms_days' => '15', 'status' => 'active',
    ]);
    $tripId = Models\LogisticsData::save('trips', null, [
        'reference_code' => '', 'customer_id' => (string) $customerId, 'trip_type' => 'delivery',
        'pickup_location' => 'Dar es Salaam', 'destination' => 'Kigali', 'status' => 'requested',
    ]);

    // ------------------------------------------------------- disbursement
    $test->throws(static fn () => $checked('disbursements', [
        'disbursement_code' => '', 'customer_id' => (string) $customerId, 'category' => 'Customs duty', 'description' => 'Nothing',
        'amount' => '0', 'currency' => 'RWF', 'paid_at' => date('Y-m-d'), 'method' => 'test_bank',
    ]), 'a disbursement of nothing is refused');

    $disbursementId = Models\LogisticsData::save('disbursements', null, [
        'disbursement_code' => '', 'customer_id' => (string) $customerId, 'trip_id' => (string) $tripId,
        'category' => 'Customs duty', 'description' => 'Import duty, entry C 4471', 'amount' => '300000',
        'currency' => 'RWF', 'paid_at' => date('Y-m-d'), 'method' => 'test_bank', 'reference' => 'RRA-4471',
    ]);
    $test->same('pending', (string) $one('SELECT status FROM disbursements WHERE id = ?', [$disbursementId]), 'a new disbursement waits to be invoiced');
    $test->same(['1150' => 300000.0, '1031' => -300000.0], $sourceLines('disbursement', $disbursementId), 'paying duty for a customer is an asset to recover, not a cost');

    // ------------------------------------------------------------ invoice
    $invoiceId = Models\LogisticsData::save('invoices', null, [
        'invoice_number' => '', 'customer_id' => (string) $customerId, 'trip_id' => (string) $tripId, 'status' => 'draft',
        'currency' => 'RWF', 'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')), 'tax_rate' => '18',
    ]);
    Models\LogisticsData::saveLines('invoices', $invoiceId, [['description' => 'Freight Dar es Salaam to Kigali', 'quantity' => '1', 'unit_price' => '1000000']]);
    $test->same(1, Models\Payables::addToInvoice($invoiceId), 'the waiting disbursement is added to the draft invoice');
    $test->same(0, Models\Payables::addToInvoice($invoiceId), 'adding again adds nothing twice');

    $invoice = $pdo->query("SELECT * FROM invoices WHERE id = {$invoiceId}")->fetch();
    $test->same(1300000.0, (float) $invoice['subtotal'], 'the subtotal holds the freight and the disbursement');
    $test->same(180000.0, (float) $invoice['tax_amount'], 'VAT is charged on the freight only');
    $test->same(1480000.0, (float) $invoice['total_amount'], 'the total is freight + VAT + disbursement at cost');
    $test->same('invoiced', (string) $one('SELECT status FROM disbursements WHERE id = ?', [$disbursementId]), 'the disbursement is now on an invoice');

    // Editing the lines keeps the disbursement line and its kind.
    $lines = $pdo->query("SELECT description, quantity, unit_price, line_kind, disbursement_id FROM invoice_lines WHERE invoice_id = {$invoiceId} ORDER BY id")->fetchAll();
    Models\LogisticsData::saveLines('invoices', $invoiceId, $lines);
    $test->same(180000.0, (float) $one('SELECT tax_amount FROM invoices WHERE id = ?', [$invoiceId]), 'saving the lines again keeps VAT off the disbursement');

    $test->assert(Models\Workflow::apply('invoices', $invoiceId, 'approve')['ok'], 'the invoice is issued');
    $test->same(['1100' => 1480000.0, '4000' => -1000000.0, '1150' => -300000.0, '2100' => -180000.0], $sourceLines('invoice', $invoiceId),
        'the invoice credits revenue with the freight only, and clears the disbursement');
    $test->same(0.0, $balance('1150'), 'nothing is left to recover once it is invoiced');

    Models\LogisticsData::save('payments', null, [
        'payment_code' => '', 'invoice_id' => (string) $invoiceId, 'amount' => '1480000', 'method' => 'test_bank',
        'reference' => 'BK-1', 'paid_at' => date('Y-m-d H:i:s'),
    ]);
    $test->same('paid', (string) $one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]), 'the invoice is paid');
    $test->same('recovered', (string) $one('SELECT status FROM disbursements WHERE id = ?', [$disbursementId]), 'a paid invoice marks the disbursement recovered');

    // ------------------------------------------------------- supplier bill
    $otherExpense = (int) $one("SELECT id FROM gl_accounts WHERE account_code = '6900'");
    $billId = Models\LogisticsData::save('supplier_bills', null, [
        'bill_no' => '', 'supplier_id' => (string) $supplierId, 'supplier_invoice_no' => 'ST-883', 'bill_date' => date('Y-m-d'),
        'due_date' => '', 'currency' => 'RWF', 'tax_rate' => '18', 'trip_id' => (string) $tripId,
    ]);
    $bill = $pdo->query("SELECT * FROM supplier_bills WHERE id = {$billId}")->fetch();
    $test->same('draft', (string) $bill['status'], 'a new bill is a draft');
    $test->same(date('Y-m-d', strtotime('+15 days')), (string) $bill['due_date'], 'a blank due date follows the supplier terms');
    $test->assert(!Models\Workflow::apply('supplier_bills', $billId, 'approve')['ok'], 'a bill without lines cannot be approved');
    $test->throws(static fn () => $checked('supplier_payments', [
        'payment_code' => '', 'bill_id' => (string) $billId, 'amount' => '1000', 'method' => 'test_bank', 'paid_at' => date('Y-m-d H:i:s'),
    ]), 'a draft bill cannot be paid');

    Models\LogisticsData::saveLines('supplier_bills', $billId, [['account_id' => (string) $otherExpense, 'description' => 'Subcontracted truck, Dar to Kigali', 'quantity' => '1', 'unit_price' => '500000']]);
    $test->same(590000.0, (float) $one('SELECT total_amount FROM supplier_bills WHERE id = ?', [$billId]), 'the bill totals its lines plus VAT');
    $test->same([], $sourceLines('supplier_bill', $billId), 'a draft bill is not in the books');
    $test->assert(Models\Workflow::apply('supplier_bills', $billId, 'approve')['ok'], 'the bill is approved');
    $test->same(['6900' => 500000.0, '2100' => 90000.0, '2000' => -590000.0], $sourceLines('supplier_bill', $billId), 'an approved bill is a cost, VAT to reclaim, and money owed');

    $test->throws(static fn () => $checked('supplier_payments', [
        'payment_code' => '', 'bill_id' => (string) $billId, 'amount' => '600000', 'method' => 'test_bank', 'paid_at' => date('Y-m-d H:i:s'),
    ]), 'paying more than the bill is refused');
    $firstPayment = Models\LogisticsData::save('supplier_payments', null, [
        'payment_code' => '', 'bill_id' => (string) $billId, 'amount' => '290000', 'method' => 'test_bank', 'reference' => 'TRF-1', 'paid_at' => date('Y-m-d H:i:s'),
    ]);
    $test->same('partially_paid', (string) $one('SELECT status FROM supplier_bills WHERE id = ?', [$billId]), 'part payment leaves the bill partly paid');
    $test->same(['2000' => 290000.0, '1031' => -290000.0], $sourceLines('supplier_payment', $firstPayment), 'paying a supplier clears the payable from the bank');
    $test->assert(!Models\Workflow::apply('supplier_bills', $billId, 'reject', 'mistake')['ok'], 'a bill with payments cannot be cancelled');

    $aging = Models\Payables::aging('payable');
    $row = array_values(array_filter($aging, static fn (array $r): bool => $r['party'] === 'Subcontract Trucks Ltd'))[0] ?? null;
    $test->assert($row !== null && abs($row['current'] - 300000.0) < 0.01, 'AP aging shows what is still owed as not yet due');
    $report = Models\ReportData::run('ap_aging');
    $test->assert(count($report['rows']) >= 1, 'the AP aging report runs');
    $test->assert(count(Models\ReportData::run('ar_aging')['columns']) === 8, 'the AR aging report runs');

    Models\LogisticsData::save('supplier_payments', null, [
        'payment_code' => '', 'bill_id' => (string) $billId, 'amount' => '300000', 'method' => 'test_bank', 'reference' => 'TRF-2', 'paid_at' => date('Y-m-d H:i:s'),
    ]);
    $test->same('paid', (string) $one('SELECT status FROM supplier_bills WHERE id = ?', [$billId]), 'the second payment settles the bill');
    $test->same(0.0, round(array_sum(array_column(array_filter(Models\Payables::aging('payable'), static fn (array $r): bool => $r['party'] === 'Subcontract Trucks Ltd'), 'total')), 2), 'a paid bill leaves the aging');

    $profit = array_values(array_filter(Models\ReportData::run('trip_profitability')['rows'], static fn (array $r): bool => (int) $one('SELECT id FROM trips WHERE reference_code = ?', [$r['reference_code']]) === $tripId))[0] ?? [];
    $test->same(1000000.0, (float) ($profit['revenue'] ?? 0), 'trip revenue leaves the recharged duty out');
    $test->same(500000.0, (float) ($profit['expense_cost'] ?? 0), 'the subcontractor bill counts as trip cost');

    // ------------------------------------------------- bank reconciliation
    $test->same(590000.0, $balance('1031'), 'the books show 590,000 in the test bank');
    // The statement is for the bank account as set up under Payment methods;
    // its ledger account and currency come from there.
    $pdo->exec("UPDATE payment_methods SET provider_name = 'Test Bank Rwanda', account_number = '00099-000111-22', account_name = 'ITEC Test', currency = 'RWF' WHERE method_key = 'test_bank'");
    Models\PaymentMethod::flush();
    $methodId = (int) $one("SELECT id FROM payment_methods WHERE method_key = 'test_bank'");
    $statementId = $checked('bank_statements', [
        'statement_code' => '', 'payment_method_id' => (string) $methodId, 'currency' => 'USD',
        'period_from' => date('Y-m-01'), 'period_to' => date('Y-m-t'), 'opening_balance' => '0', 'closing_balance' => '585000',
    ]);
    $test->same($bankId, (int) $one('SELECT account_id FROM bank_statements WHERE id = ?', [$statementId]), 'the statement takes its ledger account from the bank account chosen');
    $test->same('RWF', (string) $one('SELECT currency FROM bank_statements WHERE id = ?', [$statementId]), 'and its currency, whatever was typed');
    $test->assert(str_contains((string) (Models\PaymentMethod::options('both')['test_bank'] ?? ''), 'No. 00099-000111-22'), 'payment forms offer the account with its number');
    $csv = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'statement_' . uniqid() . '.csv';
    file_put_contents($csv, "Date,Description,Reference,Credit,Debit\n"
        . date('d/m/Y') . ",Receipt Payables Test Hardware,BK-1,\"1,480,000.00\",\n"
        . date('d/m/Y') . ",RRA duty,RRA-4471,,300000\n"
        . date('d/m/Y') . ",Transfer Subcontract Trucks,TRF-1,,290000\n"
        . date('d/m/Y') . ",Transfer Subcontract Trucks,TRF-2,,300000\n"
        . date('d/m/Y') . ",Monthly charges,,,5000\n");
    $imported = Models\BankReconciliation::import($statementId, Support\XlsxReader::rows($csv, 'statement.csv'));
    @unlink($csv);
    $test->same(['added' => 5, 'errors' => []], $imported, 'the bank export is imported, day-first dates and both columns read');

    $statement = Models\BankReconciliation::statement($statementId);
    $test->same(4, Models\BankReconciliation::autoMatch($statementId), 'four lines match the books by amount, date and reference');
    $summary = Models\BankReconciliation::summary($statement, Models\BankReconciliation::bankLines($statementId), Models\BankReconciliation::bookEntries($statement));
    $test->same(0.0, $summary['opening_check'], 'opening plus the lines gives the closing balance');
    $test->same(1, $summary['unmatched_bank'], 'the bank charge is the one line the books do not have');
    $test->throws(static fn () => Models\BankReconciliation::complete($statementId, null), 'a statement with an unexplained line cannot be closed');

    $chargeLine = (int) $one('SELECT id FROM bank_statement_lines WHERE statement_id = ? AND matched_entry_id IS NULL', [$statementId]);
    Models\BankReconciliation::postLine($statementId, $chargeLine, $otherExpense);
    $test->same(585000.0, $balance('1031'), 'posting the charge brings the books to the bank figure');
    $statement = Models\BankReconciliation::statement($statementId);
    $summary = Models\BankReconciliation::summary($statement, Models\BankReconciliation::bankLines($statementId), Models\BankReconciliation::bookEntries($statement));
    $test->same(0.0, $summary['difference'], 'nothing is left unexplained');
    Models\BankReconciliation::complete($statementId, null);
    $test->same('reconciled', (string) $one('SELECT status FROM bank_statements WHERE id = ?', [$statementId]), 'the statement is reconciled');
    $test->throws(static fn () => Models\LogisticsData::saveLines('bank_statements', $statementId, []), 'a reconciled statement cannot be edited');

    // ------------------------------------------------------ cancellation
    $invoice2 = Models\LogisticsData::save('invoices', null, [
        'invoice_number' => '', 'customer_id' => (string) $customerId, 'status' => 'draft', 'currency' => 'RWF',
        'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')), 'tax_rate' => '18',
    ]);
    $second = Models\LogisticsData::save('disbursements', null, [
        'disbursement_code' => '', 'customer_id' => (string) $customerId, 'category' => 'Port charges', 'description' => 'Wharfage',
        'amount' => '50000', 'currency' => 'RWF', 'paid_at' => date('Y-m-d'), 'method' => 'test_bank',
    ]);
    Models\Payables::addToInvoice($invoice2);
    $test->same(0.0, (float) $one('SELECT tax_amount FROM invoices WHERE id = ?', [$invoice2]), 'an invoice of disbursements only carries no VAT');
    $test->assert(Models\Workflow::apply('invoices', $invoice2, 'reject', 'Wrong customer')['ok'], 'that invoice can be cancelled');
    $test->same('pending', (string) $one('SELECT status FROM disbursements WHERE id = ?', [$second]), 'cancelling the invoice puts the disbursement back on the list');
    $test->assert(Models\Workflow::apply('disbursements', $second, 'write_off', 'Customer will not pay')['ok'], 'an unrecoverable disbursement can be written off');
    $test->same(0.0, $balance('1150'), 'the write-off clears it from the recoverable account');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
