<?php

declare(strict_types=1);

/**
 * The accounting books: that the ledger balances, that each operational
 * document posts the entry it should, that the statements agree with each
 * other, and that every book exports as a real PDF, xlsx and CSV.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_accounting');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Accounting tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['finance'], 'finance');

    $scalar = static function (string $sql, array $p = []) use ($pdo): mixed {
        $statement = $pdo->prepare($sql);
        $statement->execute($p);
        return $statement->fetchColumn();
    };

    // ------------------------------------------------------ the chart is sound
    $test->assert(count(Models\Ledger::accounts()) >= 30, 'the chart of accounts is seeded');

    foreach (['1010', '1020', '1100', '1200', '2000', '2100', '3000', '4000', '5000', '6000'] as $code) {
        $test->assert(Models\Ledger::idOf($code) !== null, "account {$code} exists in the chart");
    }

    $unbalanced = (int) $scalar(
        "SELECT COUNT(*) FROM (
            SELECT e.id FROM gl_journal_entries e
            INNER JOIN gl_journal_lines l ON l.entry_id = e.id
            WHERE e.deleted_at IS NULL
            GROUP BY e.id
            HAVING ABS(SUM(l.debit) - SUM(l.credit)) > 0.004
         ) x"
    );
    $test->same(0, $unbalanced, 'every seeded entry balances on its own');
    $test->assert(Models\Ledger::isBalanced(), 'the ledger as a whole balances after seeding');

    // ------------------------------------------------ an entry must balance
    $test->throws(
        static fn () => Models\Ledger::post(date('Y-m-d'), 'Deliberately lopsided', [
            ['account' => '1020', 'debit' => 1000],
            ['account' => '4000', 'credit' => 900],
        ]),
        'an entry whose sides differ is refused'
    );

    $test->throws(
        static fn () => Models\Ledger::post(date('Y-m-d'), 'Both sides on one line', [
            ['account' => '1020', 'debit' => 500, 'credit' => 500],
        ]),
        'a line carrying both a debit and a credit is refused'
    );

    $test->throws(
        static fn () => Models\Ledger::post(date('Y-m-d'), 'Unknown account', [
            ['account' => '9999', 'debit' => 100],
            ['account' => '1020', 'credit' => 100],
        ]),
        'an entry naming an account outside the chart is refused'
    );

    $before = (int) $scalar('SELECT COUNT(*) FROM gl_journal_entries');
    $entryId = Models\Ledger::post(date('Y-m-d'), 'Test accrual', [
        ['account' => '6300', 'debit' => 250000, 'description' => 'September rent'],
        ['account' => '2150', 'credit' => 250000, 'description' => 'Landlord'],
    ], 'manual');
    $test->same($before + 1, (int) $scalar('SELECT COUNT(*) FROM gl_journal_entries'), 'a balanced entry is written');
    $test->same(2, (int) $scalar('SELECT COUNT(*) FROM gl_journal_lines WHERE entry_id = ?', [$entryId]), 'both of its lines are written');

    // ---------------------------------------------------------- a closed period
    $pdo->prepare("UPDATE gl_fiscal_periods SET status = 'closed' WHERE ? BETWEEN starts_on AND ends_on")
        ->execute([date('Y-m-d')]);
    $test->assert(Models\Ledger::isPeriodClosed(date('Y-m-d')), 'the period reads as closed');
    $test->throws(
        static fn () => Models\Ledger::post(date('Y-m-d'), 'Into a closed period', [
            ['account' => '6300', 'debit' => 100],
            ['account' => '2150', 'credit' => 100],
        ]),
        'nothing can be posted into a closed period'
    );
    $pdo->prepare("UPDATE gl_fiscal_periods SET status = 'open'")->execute();

    // ------------------------------------------------------------- reversal
    $reversalId = Models\Ledger::reverse($entryId, 'Posted to the wrong month');
    $test->same('reversed', (string) $scalar('SELECT status FROM gl_journal_entries WHERE id = ?', [$entryId]), 'the original is marked reversed');
    $test->assert($reversalId > 0, 'a reversing entry is written');
    $test->same(
        250000.0,
        (float) $scalar('SELECT credit FROM gl_journal_lines WHERE entry_id = ? AND account_id = ?', [$reversalId, Models\Ledger::idOf('6300')]),
        'the reversal credits what the original debited'
    );
    $test->assert(Models\Ledger::isBalanced(), 'the ledger still balances after a reversal');
    $test->throws(static fn () => Models\Ledger::reverse($entryId, 'Again'), 'an entry cannot be reversed twice');

    // -------------------------------------------- the operational posting rules
    $counts = Models\Posting::syncAll();
    $test->assert(array_sum($counts) > 0, 'the seeded operations post to the ledger');
    $test->assert(Models\Ledger::isBalanced(), 'the ledger balances after posting the operations');

    // Running it again must not double anything: the source pair is unique.
    $entriesAfterFirst = (int) $scalar("SELECT COUNT(*) FROM gl_journal_entries WHERE deleted_at IS NULL");
    Models\Posting::syncAll();
    $test->same($entriesAfterFirst, (int) $scalar("SELECT COUNT(*) FROM gl_journal_entries WHERE deleted_at IS NULL"), 'posting twice does not duplicate an entry');

    // An issued invoice: receivable debited, revenue and VAT credited.
    $invoice = $pdo->query("SELECT id, invoice_number, subtotal, tax_amount, total_amount FROM invoices WHERE status = 'issued' AND deleted_at IS NULL LIMIT 1")->fetch();
    $test->assert($invoice !== false, 'an issued invoice exists to check');
    if ($invoice !== false) {
        $lines = $pdo->prepare(
            'SELECT a.account_code, l.debit, l.credit
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
               INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE e.source_type = ? AND e.source_id = ?'
        );
        $lines->execute(['invoice', (int) $invoice['id']]);
        $posted = [];
        foreach ($lines->fetchAll() as $line) {
            $posted[(string) $line['account_code']] = ['debit' => (float) $line['debit'], 'credit' => (float) $line['credit']];
        }

        $test->same(round((float) $invoice['total_amount'], 2), round($posted['1100']['debit'] ?? 0, 2), 'the invoice debits receivables with its total');
        $test->same(round((float) $invoice['subtotal'], 2), round($posted['4000']['credit'] ?? 0, 2), 'the invoice credits revenue with its subtotal');
        $test->same(round((float) $invoice['tax_amount'], 2), round($posted['2100']['credit'] ?? 0, 2), 'the invoice credits VAT payable with its tax');
    }

    // A payment: the bank debited, receivables credited.
    $payment = $pdo->query("SELECT id, amount, method FROM payments WHERE deleted_at IS NULL LIMIT 1")->fetch();
    if ($payment !== false) {
        $bankDebit = (float) $scalar(
            "SELECT COALESCE(SUM(l.debit), 0)
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
               INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE e.source_type = 'payment' AND e.source_id = ? AND a.is_bank = 1",
            [(int) $payment['id']]
        );
        $test->same(round((float) $payment['amount'], 2), round($bankDebit, 2), 'a payment debits a bank or cash account with its amount');
    }

    // Fuel: the fuel account carries the litres times the unit price.
    $fuel = $pdo->query('SELECT id, litres, unit_price FROM fuel_records WHERE deleted_at IS NULL LIMIT 1')->fetch();
    if ($fuel !== false) {
        $expected = round((float) $fuel['litres'] * (float) $fuel['unit_price'], 2);
        $fuelDebit = (float) $scalar(
            "SELECT COALESCE(SUM(l.debit), 0)
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
              WHERE e.source_type = 'fuel' AND e.source_id = ? AND l.account_id = ?",
            [(int) $fuel['id'], Models\Ledger::idOf('5000')]
        );
        $test->same($expected, round($fuelDebit, 2), 'a fuel record debits fuel with litres times price');
    }

    // A cancelled invoice must not leave revenue behind.
    if ($invoice !== false) {
        $pdo->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?")->execute([(int) $invoice['id']]);
        Models\Posting::invoice((int) $invoice['id']);
        $test->same(
            0,
            (int) $scalar("SELECT COUNT(*) FROM gl_journal_entries WHERE source_type = 'invoice' AND source_id = ? AND deleted_at IS NULL", [(int) $invoice['id']]),
            'cancelling an invoice takes its entry out of the ledger'
        );
        $pdo->prepare("UPDATE invoices SET status = 'issued' WHERE id = ?")->execute([(int) $invoice['id']]);
        Models\Posting::invoice((int) $invoice['id']);
        $test->assert(Models\Ledger::isBalanced(), 'the ledger balances again once the invoice is reinstated');
    }

    // ---------------------------------------------- the books agree with each other
    $from = date('Y-01-01');
    $to = date('Y-m-d');

    foreach (array_keys(Models\Books::CATALOGUE) as $key) {
        $book = Models\Books::build($key, ['from' => $from, 'to' => $to]);
        $test->assert($book['doc']['columns'] !== [], "the {$key} book declares columns");
        $test->assert(isset($book['doc']['rows']), "the {$key} book builds its rows");
    }

    $grandOf = static function (array $doc, string $label): ?float {
        foreach ($doc['rows'] as $row) {
            if ($row['type'] === 'grand' && isset($row['cells'][0]) && str_contains((string) $row['cells'][0], $label)) {
                foreach ($row['cells'] as $cell) {
                    if (is_float($cell) || is_int($cell)) {
                        return round((float) $cell, 2);
                    }
                }
            }
        }
        return null;
    };

    $trial = Models\Books::build('trial_balance', ['to' => $to]);
    $test->assert(str_contains($trial['note'], 'agree'), 'the trial balance reports that it agrees');

    $sheet = Models\Books::build('balance_sheet', ['to' => $to]);
    $assets = $grandOf($sheet['doc'], 'TOTAL ASSETS');
    $claims = $grandOf($sheet['doc'], 'TOTAL LIABILITIES AND EQUITY');
    $test->assert($assets !== null && $claims !== null, 'the balance sheet prints both totals');
    $test->same($assets, $claims, 'assets equal liabilities plus equity');

    $profit = Models\Books::build('profit_loss', ['from' => $from, 'to' => $to]);
    $netProfit = $grandOf($profit['doc'], 'NET PROFIT');
    $test->assert($netProfit !== null, 'the profit and loss prints a net result');
    $test->same(
        Models\Ledger::profitBetween($from, $to),
        $netProfit,
        'the profit and loss agrees with the figure the balance sheet carries into equity'
    );

    $cash = Models\Books::build('cash_flow', ['from' => $from, 'to' => $to]);
    $test->assert(str_contains($cash['note'], 'equals the change'), 'the cash flow statement reconciles to the bank accounts');

    // The general ledger's closing balance for an account must equal the
    // trial balance figure for that same account.
    $bankId = Models\Ledger::idOf('1020');
    $bankOpening = Models\Ledger::openingBalance($bankId, $from);
    $bankMovement = 0.0;
    foreach (Models\Ledger::movements($bankId, $from, $to) as $movement) {
        $bankMovement += (float) $movement['debit'] - (float) $movement['credit'];
    }
    $bankClosing = null;
    foreach (Models\Ledger::balances(null, $to, true) as $row) {
        if ((int) $row['id'] === $bankId) {
            $bankClosing = round((float) $row['closing'], 2);
        }
    }
    $test->same($bankClosing, round($bankOpening + $bankMovement, 2), 'the ledger movements add up to the balance the books report');

    // ------------------------------------------------------------- exports
    $writer = sys_get_temp_dir() . '/lms_book_writer.php';
    file_put_contents($writer, "<?php\n"
        . "\$_SERVER['REQUEST_URI'] = '/';\n"
        . "putenv('LOGISTICS_DB_NAME=' . \$argv[3]);\n"
        . "require '" . str_replace('\\', '/', dirname(__DIR__)) . "/bootstrap.php';\n"
        . "\\Support\\Report::download(unserialize(file_get_contents(\$argv[1])), \$argv[2]);\n");

    $docFile = sys_get_temp_dir() . '/lms_book_doc.bin';
    $outFile = sys_get_temp_dir() . '/lms_book_out.bin';

    foreach (['trial_balance', 'balance_sheet', 'journal'] as $key) {
        $book = Models\Books::build($key, ['from' => $from, 'to' => $to]);
        file_put_contents($docFile, serialize($book['doc']));

        foreach (['pdf', 'xlsx', 'csv'] as $format) {
            @unlink($outFile);
            // The writers clear every buffer and exit, so a child process with
            // its output redirected is the only honest way to see the bytes.
            exec(sprintf('"%s" "%s" "%s" "%s" "%s" > "%s" 2>&1', PHP_BINARY, $writer, $docFile, $format, $dbName, $outFile));
            $bytes = is_file($outFile) ? (string) file_get_contents($outFile) : '';

            $valid = match ($format) {
                'pdf' => str_starts_with($bytes, '%PDF-') && str_contains($bytes, '%%EOF'),
                'xlsx' => str_starts_with($bytes, "PK\x03\x04"),
                default => str_contains($bytes, ','),
            };

            $test->assert($valid, "the {$key} book exports a valid {$format} file");
            $test->assert(strlen($bytes) > 400, "the {$key} {$format} export has content");
        }
    }

    @unlink($writer);
    @unlink($docFile);
    @unlink($outFile);

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
