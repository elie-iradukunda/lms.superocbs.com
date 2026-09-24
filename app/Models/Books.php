<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Report;

/**
 * The accounting books.
 *
 * Each one builds the same report document, so the page on screen, the PDF and
 * the Excel file are three renderings of one description and cannot disagree.
 * Every figure comes from Models\Ledger, which asks the journal once.
 */
final class Books
{
    public const CATALOGUE = [
        'chart_of_accounts' => [
            'label' => 'Chart of accounts',
            'icon' => 'list',
            'description' => 'Every account the business keeps, with its type and current balance.',
            'ranged' => false,
        ],
        'journal' => [
            'label' => 'Journal',
            'icon' => 'book-open',
            'description' => 'Every entry in date order, with both sides of each one.',
            'ranged' => true,
        ],
        'general_ledger' => [
            'label' => 'General ledger',
            'icon' => 'layers',
            'description' => 'Each account with its opening balance, every movement, and what it closed at.',
            'ranged' => true,
        ],
        'trial_balance' => [
            'label' => 'Trial balance',
            'icon' => 'check-square',
            'description' => 'Every account balance in a debit and a credit column, which must agree.',
            'ranged' => true,
        ],
        'profit_loss' => [
            'label' => 'Profit and loss',
            'icon' => 'trending-up',
            'description' => 'Revenue less cost of sales and operating expenses for the period.',
            'ranged' => true,
        ],
        'balance_sheet' => [
            'label' => 'Balance sheet',
            'icon' => 'pie-chart',
            'description' => 'What the business owns, what it owes and what is left for the owners.',
            'ranged' => false,
        ],
        'cash_flow' => [
            'label' => 'Statement of cash flows',
            'icon' => 'repeat',
            'description' => 'Where cash actually came from and went, by operating, investing and financing activity.',
            'ranged' => true,
        ],
        'account_statement' => [
            'label' => 'Account statement',
            'icon' => 'file-text',
            'description' => 'One account in detail, with a running balance down the page.',
            'ranged' => true,
        ],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::CATALOGUE[$key]);
    }

    public static function label(string $key): string
    {
        return self::CATALOGUE[$key]['label'] ?? $key;
    }

    /**
     * Builds one book.
     *
     * @return array{doc: array, key: string, label: string, from: string, to: string, account_id: ?int, note: string}
     */
    public static function build(string $key, array $query = []): array
    {
        if (!self::exists($key)) {
            throw new \InvalidArgumentException("Unknown book: {$key}");
        }

        [$from, $to] = self::period($query);
        $accountId = isset($query['account_id']) && $query['account_id'] !== '' ? (int) $query['account_id'] : null;

        // A book is read in one currency. Nothing is converted, so shillings and
        // francs are two sets of books that each balance on their own; asking
        // for both at once would add them together and answer nothing.
        $currency = self::currencyOf($query);
        Ledger::inCurrency($currency);

        $result = match ($key) {
            'chart_of_accounts' => self::chartOfAccounts($to),
            'journal' => self::journal($from, $to),
            'general_ledger' => self::generalLedger($from, $to, $accountId),
            'trial_balance' => self::trialBalance($from, $to),
            'profit_loss' => self::profitAndLoss($from, $to),
            'balance_sheet' => self::balanceSheet($to),
            'cash_flow' => self::cashFlow($from, $to),
            'account_statement' => self::accountStatement($accountId, $from, $to),
        };

        // A printed page has to say which money it counts, or a shilling trial
        // balance and a franc one are indistinguishable once they leave the
        // screen.
        $result['doc']['period'] = trim($result['doc']['period'] . ' · ' . $currency, ' ·');

        return [
            'doc' => $result['doc'],
            'note' => $result['note'] ?? '',
            'key' => $key,
            'label' => self::label($key),
            'from' => $from,
            'to' => $to,
            'account_id' => $accountId,
            'currency' => $currency,
            'currencies' => self::currenciesInUse(),
        ];
    }

    /**
     * Which currency this book is being read in.
     *
     * Only a currency the books actually contain is accepted: a code typed into
     * the address bar cannot steer the query, and a company that has never
     * recorded a shilling is never shown an empty shilling page.
     */
    private static function currencyOf(array $query): string
    {
        $wanted = strtoupper(trim((string) ($query['currency'] ?? '')));
        $available = self::currenciesInUse();

        if ($wanted !== '' && in_array($wanted, $available, true)) {
            return $wanted;
        }

        $base = Currency::base();

        return in_array($base, $available, true) ? $base : ($available[0] ?? $base);
    }

    /**
     * The currencies the ledger has entries in, base first.
     *
     * @return list<string>
     */
    public static function currenciesInUse(): array
    {
        $rows = Database::connection()
            ->query("SELECT DISTINCT currency FROM gl_journal_entries WHERE status = 'posted' AND deleted_at IS NULL ORDER BY currency")
            ->fetchAll(PDO::FETCH_COLUMN);

        $codes = array_values(array_filter(array_map('strval', $rows), static fn (string $c): bool => $c !== ''));
        $base = Currency::base();

        if ($codes === []) {
            return [$base];
        }

        // The base currency reads first because it is the one most pages want.
        usort($codes, static fn (string $a, string $b): int => ($a === $base ? -1 : 0) <=> ($b === $base ? -1 : 0) ?: strcmp($a, $b));

        return $codes;
    }

    /** @return array{0: string, 1: string} */
    private static function period(array $query): array
    {
        $to = self::date($query['to'] ?? null, date('Y-m-d'));
        $from = self::date($query['from'] ?? null, date('Y-01-01'));

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private static function date(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);
        $time = $value === '' ? false : strtotime($value);

        return $time === false ? $fallback : date('Y-m-d', $time);
    }

    private static function day(string $date): string
    {
        return date('j M Y', (int) strtotime($date));
    }

    // ----------------------------------------------------- chart of accounts

    private static function chartOfAccounts(string $asOf): array
    {
        $doc = Report::make('Chart of Accounts', 'As at ' . self::day($asOf), [
            ['label' => 'Code', 'align' => 'left', 'width' => 10],
            ['label' => 'Account', 'align' => 'left', 'width' => 40],
            ['label' => 'Type', 'align' => 'left', 'width' => 16],
            ['label' => 'Normal side', 'align' => 'left', 'width' => 12],
            ['label' => 'Balance', 'align' => 'right', 'width' => 18, 'currency' => true],
        ]);

        $balances = [];
        foreach (Ledger::balances(null, $asOf, true) as $row) {
            $balances[(int) $row['id']] = (float) $row['closing'];
        }

        $section = null;
        foreach (Ledger::accounts(true) as $account) {
            if ($account['report_section'] !== $section) {
                if ($section !== null) {
                    Report::blank($doc);
                }
                $section = (string) $account['report_section'];
                Report::section($doc, strtoupper($section));
            }

            $closing = $balances[(int) $account['id']] ?? 0.0;
            $display = self::displayBalance((string) $account['account_type'], $closing);

            Report::row($doc, [
                (string) $account['account_code'],
                (string) $account['account_name'],
                ucfirst(str_replace('_', ' ', (string) $account['account_type'])),
                ucfirst((string) $account['normal_balance']),
                abs($display) < 0.004 ? null : $display,
            ], (int) $account['is_header'] === 1 ? 0 : 1);
        }

        return ['doc' => $doc, 'note' => 'A balance is shown on the side the account normally carries, so a credit balance is positive on a liability.'];
    }

    // ---------------------------------------------------------------- journal

    private static function journal(string $from, string $to): array
    {
        $doc = Report::make('Journal', self::day($from) . ' to ' . self::day($to), [
            ['label' => 'Date', 'align' => 'left', 'width' => 12],
            ['label' => 'Entry', 'align' => 'left', 'width' => 14],
            ['label' => 'Source', 'align' => 'left', 'width' => 14],
            ['label' => 'Account', 'align' => 'left', 'width' => 30],
            ['label' => 'Description', 'align' => 'left', 'width' => 30],
            ['label' => 'Debit', 'align' => 'right', 'width' => 16, 'currency' => true],
            ['label' => 'Credit', 'align' => 'right', 'width' => 16, 'currency' => true],
        ]);

        $statement = Database::connection()->prepare(
            "SELECT e.entry_no, e.entry_date, e.memo, e.source_code, e.source_type, e.status,
                    a.account_code, a.account_name, l.description, l.debit, l.credit
               FROM gl_journal_entries e
               INNER JOIN gl_journal_lines l ON l.entry_id = e.id
               INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE e.status = 'posted' AND e.deleted_at IS NULL
                AND e.entry_date BETWEEN ? AND ?" . Ledger::scopeFor('e') . "
              ORDER BY e.entry_date, e.id, l.line_no"
        );
        $statement->execute([$from, $to]);

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $currentEntry = null;

        foreach ($statement->fetchAll() as $line) {
            if ($line['entry_no'] !== $currentEntry) {
                if ($currentEntry !== null) {
                    Report::blank($doc);
                }
                $currentEntry = (string) $line['entry_no'];
                Report::section($doc, sprintf('%s  %s', self::day((string) $line['entry_date']), (string) $line['memo']));
            }

            $debit = (float) $line['debit'];
            $credit = (float) $line['credit'];
            $totalDebit += $debit;
            $totalCredit += $credit;

            Report::row($doc, [
                self::day((string) $line['entry_date']),
                (string) $line['entry_no'],
                (string) ($line['source_code'] ?? ucfirst((string) ($line['source_type'] ?? 'Manual'))),
                $line['account_code'] . ' ' . $line['account_name'],
                (string) ($line['description'] ?? ''),
                $debit > 0 ? $debit : null,
                $credit > 0 ? $credit : null,
            ], 1);
        }

        Report::blank($doc);
        Report::grand($doc, ['TOTAL', '', '', '', '', $totalDebit, $totalCredit]);

        return ['doc' => $doc, 'note' => 'Both columns must agree. Every entry is listed with all of its lines, so nothing is hidden behind a summary.'];
    }

    // --------------------------------------------------------- general ledger

    private static function generalLedger(string $from, string $to, ?int $accountId): array
    {
        $doc = Report::make('General Ledger', self::day($from) . ' to ' . self::day($to), [
            ['label' => 'Date', 'align' => 'left', 'width' => 12],
            ['label' => 'Entry', 'align' => 'left', 'width' => 14],
            ['label' => 'Source', 'align' => 'left', 'width' => 14],
            ['label' => 'Description', 'align' => 'left', 'width' => 34],
            ['label' => 'Debit', 'align' => 'right', 'width' => 15, 'currency' => true],
            ['label' => 'Credit', 'align' => 'right', 'width' => 15, 'currency' => true],
            ['label' => 'Balance', 'align' => 'right', 'width' => 16, 'currency' => true],
        ]);

        $accounts = Ledger::balances($from, $to);
        if ($accountId !== null) {
            $accounts = array_values(array_filter($accounts, static fn (array $a): bool => (int) $a['id'] === $accountId));
        }

        foreach ($accounts as $account) {
            $id = (int) $account['id'];
            $movements = Ledger::movements($id, $from, $to);
            $opening = Ledger::openingBalance($id, $from);

            if ($movements === [] && abs($opening) < 0.004) {
                continue;
            }

            Report::section($doc, $account['account_code'] . '  ' . $account['account_name']);
            Report::row($doc, ['', '', '', 'Opening balance', null, null, $opening], 1);

            $running = $opening;
            $debits = 0.0;
            $credits = 0.0;

            foreach ($movements as $movement) {
                $debit = (float) $movement['debit'];
                $credit = (float) $movement['credit'];
                $running += $debit - $credit;
                $debits += $debit;
                $credits += $credit;

                Report::row($doc, [
                    self::day((string) $movement['entry_date']),
                    (string) $movement['entry_no'],
                    (string) ($movement['source_code'] ?? ucfirst((string) ($movement['source_type'] ?? 'Manual'))),
                    (string) ($movement['description'] ?: $movement['memo']),
                    $debit > 0 ? $debit : null,
                    $credit > 0 ? $credit : null,
                    $running,
                ], 1);
            }

            Report::total($doc, ['', '', '', 'Closing balance', $debits, $credits, $running], 1);
            Report::blank($doc);
        }

        return ['doc' => $doc, 'note' => 'The balance column runs down the page, so any figure can be traced to the entry that moved it.'];
    }

    // ---------------------------------------------------------- trial balance

    private static function trialBalance(string $from, string $to): array
    {
        $doc = Report::make('Trial Balance', 'As at ' . self::day($to), [
            ['label' => 'Code', 'align' => 'left', 'width' => 10],
            ['label' => 'Account', 'align' => 'left', 'width' => 44],
            ['label' => 'Debit', 'align' => 'right', 'width' => 20, 'currency' => true],
            ['label' => 'Credit', 'align' => 'right', 'width' => 20, 'currency' => true],
        ]);

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $section = null;

        foreach (Ledger::balances(null, $to) as $account) {
            if ((int) $account['is_header'] === 1) {
                continue;
            }

            if ($account['report_section'] !== $section) {
                if ($section !== null) {
                    Report::blank($doc);
                }
                $section = (string) $account['report_section'];
                Report::section($doc, strtoupper($section));
            }

            $closing = round((float) $account['closing'], 2);
            $debit = $closing > 0 ? $closing : 0.0;
            $credit = $closing < 0 ? -$closing : 0.0;
            $totalDebit += $debit;
            $totalCredit += $credit;

            Report::row($doc, [
                (string) $account['account_code'],
                (string) $account['account_name'],
                $debit > 0 ? $debit : null,
                $credit > 0 ? $credit : null,
            ], 1);
        }

        Report::blank($doc);
        Report::grand($doc, ['', 'TOTAL', $totalDebit, $totalCredit]);

        $difference = round($totalDebit - $totalCredit, 2);
        $note = abs($difference) < 0.004
            ? 'The two columns agree, so the ledger is in balance.'
            : sprintf('The columns differ by %s. Until that is resolved the other books cannot be relied on.', Report::money($difference, true));

        return ['doc' => $doc, 'note' => $note];
    }

    // ------------------------------------------------------- profit and loss

    private static function profitAndLoss(string $from, string $to): array
    {
        $doc = Report::make('Profit and Loss', self::day($from) . ' to ' . self::day($to), [
            ['label' => 'Account', 'align' => 'left', 'width' => 60],
            ['label' => 'Amount', 'align' => 'right', 'width' => 22, 'currency' => true],
        ]);

        $balances = Ledger::balances($from, $to, true);

        /** Movement in the period on the side the account normally carries. */
        $movement = static function (array $account): float {
            $change = (float) $account['period_debit'] - (float) $account['period_credit'];

            return in_array($account['account_type'], ['income'], true) ? -$change : $change;
        };

        $groups = [
            'Income' => ['income'],
            'Cost of sales' => ['cost_of_sales'],
            'Operating expenses' => ['expense'],
        ];

        $income = 0.0;
        $costOfSales = 0.0;
        $expenses = 0.0;

        foreach ($groups as $heading => $types) {
            $rows = array_values(array_filter(
                $balances,
                static fn (array $a): bool => in_array($a['account_type'], $types, true)
                    && (int) $a['is_header'] === 0
                    && ($a['report_section'] !== 'Other income' && $a['report_section'] !== 'Other expenses')
            ));

            Report::section($doc, strtoupper($heading));
            $subtotal = 0.0;
            foreach ($rows as $account) {
                $amount = round($movement($account), 2);
                if (abs($amount) < 0.004) {
                    continue;
                }
                $subtotal += $amount;
                Report::row($doc, [(string) $account['account_name'], $amount], 1);
            }
            Report::total($doc, ['Total ' . strtolower($heading), $subtotal], 1);
            Report::blank($doc);

            if ($heading === 'Income') {
                $income = $subtotal;
            } elseif ($heading === 'Cost of sales') {
                $costOfSales = $subtotal;
            } else {
                $expenses = $subtotal;
            }

            if ($heading === 'Cost of sales') {
                Report::grand($doc, ['GROSS PROFIT', round($income - $costOfSales, 2)]);
                Report::blank($doc);
            }
        }

        // Anything the business does outside its main trade is kept apart, so
        // the operating result is not flattered or spoiled by a one-off.
        $otherIncome = 0.0;
        $otherExpense = 0.0;
        foreach ($balances as $account) {
            if ($account['report_section'] === 'Other income') {
                $otherIncome += round($movement($account), 2);
            }
            if ($account['report_section'] === 'Other expenses') {
                $otherExpense += round($movement($account), 2);
            }
        }

        $operating = round($income - $costOfSales - $expenses, 2);
        Report::grand($doc, ['OPERATING PROFIT', $operating]);

        if (abs($otherIncome) > 0.004 || abs($otherExpense) > 0.004) {
            Report::blank($doc);
            Report::section($doc, 'OTHER INCOME AND EXPENSES');
            if (abs($otherIncome) > 0.004) {
                Report::row($doc, ['Other income', $otherIncome], 1);
            }
            if (abs($otherExpense) > 0.004) {
                Report::row($doc, ['Other expenses', -$otherExpense], 1);
            }
        }

        Report::blank($doc);
        Report::grand($doc, ['NET PROFIT FOR THE PERIOD', round($operating + $otherIncome - $otherExpense, 2)]);

        $margin = $income > 0 ? round(($operating / $income) * 100, 1) : null;
        $note = $margin === null
            ? 'No revenue was recorded in this period.'
            : sprintf('Operating margin %s%% of revenue for the period.', $margin);

        return ['doc' => $doc, 'note' => $note];
    }

    // --------------------------------------------------------- balance sheet

    private static function balanceSheet(string $asOf): array
    {
        $doc = Report::make('Balance Sheet', 'As at ' . self::day($asOf), [
            ['label' => 'Account', 'align' => 'left', 'width' => 60],
            ['label' => 'Amount', 'align' => 'right', 'width' => 22, 'currency' => true],
        ]);

        $balances = Ledger::balances(null, $asOf);
        $yearStart = date('Y-01-01', (int) strtotime($asOf));
        $currentEarnings = Ledger::profitBetween($yearStart, $asOf);

        $sectionsFor = static function (array $balances, array $sections) : array {
            return array_values(array_filter(
                $balances,
                static fn (array $a): bool => in_array($a['report_section'], $sections, true) && (int) $a['is_header'] === 0
            ));
        };

        $block = static function (array &$doc, string $heading, array $rows, bool $creditSide): float {
            Report::section($doc, strtoupper($heading));
            $subtotal = 0.0;
            foreach ($rows as $account) {
                $amount = round($creditSide ? -(float) $account['closing'] : (float) $account['closing'], 2);
                if (abs($amount) < 0.004) {
                    continue;
                }
                $subtotal += $amount;
                Report::row($doc, [(string) $account['account_name'], $amount], 1);
            }
            Report::total($doc, ['Total ' . strtolower($heading), round($subtotal, 2)], 1);
            Report::blank($doc);

            return round($subtotal, 2);
        };

        $currentAssets = $block($doc, 'Current assets', $sectionsFor($balances, ['Cash and bank', 'Accounts receivable', 'Other current assets']), false);
        $fixedAssets = $block($doc, 'Fixed assets', $sectionsFor($balances, ['Fixed assets']), false);
        $totalAssets = round($currentAssets + $fixedAssets, 2);
        Report::grand($doc, ['TOTAL ASSETS', $totalAssets]);
        Report::blank($doc);

        $currentLiabilities = $block($doc, 'Current liabilities', $sectionsFor($balances, ['Accounts payable', 'Other current liabilities']), true);
        $longTerm = $block($doc, 'Long term liabilities', $sectionsFor($balances, ['Long term liabilities']), true);
        $totalLiabilities = round($currentLiabilities + $longTerm, 2);
        Report::total($doc, ['Total liabilities', $totalLiabilities]);
        Report::blank($doc);

        // Equity carries this year's result, which is not an account anyone
        // posts to: it is the profit the income statement has just worked out.
        Report::section($doc, 'EQUITY');
        $equity = 0.0;
        foreach ($sectionsFor($balances, ['Equity']) as $account) {
            $amount = round(-(float) $account['closing'], 2);
            if (abs($amount) < 0.004) {
                continue;
            }
            $equity += $amount;
            Report::row($doc, [(string) $account['account_name'], $amount], 1);
        }
        Report::row($doc, ['Profit for the current year', $currentEarnings], 1);
        $equity = round($equity + $currentEarnings, 2);
        Report::total($doc, ['Total equity', $equity], 1);
        Report::blank($doc);

        Report::grand($doc, ['TOTAL LIABILITIES AND EQUITY', round($totalLiabilities + $equity, 2)]);

        $difference = round($totalAssets - ($totalLiabilities + $equity), 2);
        $note = abs($difference) < 0.004
            ? 'Assets equal liabilities plus equity, so the statement balances.'
            : sprintf('The statement is out by %s. Check the trial balance before relying on it.', Report::money($difference, true));

        return ['doc' => $doc, 'note' => $note];
    }

    // ------------------------------------------------------------- cash flow

    /**
     * A cash flow statement built from what actually moved through the bank and
     * cash accounts, rather than reconstructed from profit.
     *
     * For every entry that touches cash, the other side of that entry is what
     * the cash was for, so grouping those counterparts by their activity class
     * splits the movement exactly, with nothing left over.
     */
    private static function cashFlow(string $from, string $to): array
    {
        $doc = Report::make('Statement of Cash Flows', self::day($from) . ' to ' . self::day($to), [
            ['label' => 'Description', 'align' => 'left', 'width' => 60],
            ['label' => 'Amount', 'align' => 'right', 'width' => 22, 'currency' => true],
        ], 'Cash Basis');

        $statement = Database::connection()->prepare(
            "SELECT a.cash_flow_class, a.account_name,
                    COALESCE(SUM(l.credit - l.debit), 0) AS cash_effect
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
               INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE e.status = 'posted' AND e.deleted_at IS NULL
                AND e.entry_date BETWEEN ? AND ?" . Ledger::scopeFor('e') . "
                AND a.is_bank = 0
                AND e.id IN (
                    SELECT lx.entry_id FROM gl_journal_lines lx
                    INNER JOIN gl_accounts ax ON ax.id = lx.account_id
                    WHERE ax.is_bank = 1
                )
              GROUP BY a.id
              HAVING ABS(cash_effect) > 0.004
              ORDER BY a.cash_flow_class, a.account_code"
        );
        $statement->execute([$from, $to]);
        $rows = $statement->fetchAll();

        $activities = ['operating' => 'Operating activities', 'investing' => 'Investing activities', 'financing' => 'Financing activities'];
        $netMovement = 0.0;

        foreach ($activities as $class => $heading) {
            $inClass = array_values(array_filter($rows, static fn (array $r): bool => $r['cash_flow_class'] === $class));
            if ($inClass === [] && $class !== 'operating') {
                continue;
            }

            Report::section($doc, strtoupper($heading));
            $subtotal = 0.0;
            foreach ($inClass as $row) {
                $amount = round((float) $row['cash_effect'], 2);
                $subtotal += $amount;
                Report::row($doc, [(string) $row['account_name'], $amount], 1);
            }
            Report::total($doc, ['Net cash from ' . strtolower($heading), round($subtotal, 2)], 1);
            Report::blank($doc);
            $netMovement += $subtotal;
        }

        // Anything with no activity class still moved cash, so it is shown
        // rather than quietly dropped.
        $unclassified = array_values(array_filter($rows, static fn (array $r): bool => !isset($activities[$r['cash_flow_class']])));
        if ($unclassified !== []) {
            Report::section($doc, 'OTHER MOVEMENTS');
            foreach ($unclassified as $row) {
                $amount = round((float) $row['cash_effect'], 2);
                $netMovement += $amount;
                Report::row($doc, [(string) $row['account_name'], $amount], 1);
            }
            Report::blank($doc);
        }

        $openingCash = self::cashBalance($from, true);
        $closingCash = self::cashBalance($to, false);

        Report::grand($doc, ['NET MOVEMENT IN CASH', round($netMovement, 2)]);
        Report::blank($doc);
        Report::row($doc, ['Cash and bank at the start of the period', $openingCash]);
        Report::row($doc, ['Cash and bank at the end of the period', $closingCash]);
        Report::grand($doc, ['MOVEMENT PROVED', round($closingCash - $openingCash, 2)]);

        $difference = round($netMovement - ($closingCash - $openingCash), 2);
        $note = abs($difference) < 0.004
            ? 'The movement explained above equals the change in the bank and cash accounts.'
            : sprintf('The explanation is out by %s against the bank accounts.', Report::money($difference, true));

        return ['doc' => $doc, 'note' => $note];
    }

    private static function cashBalance(string $date, bool $before): float
    {
        $comparison = $before ? '<' : '<=';
        $statement = Database::connection()->prepare(
            "SELECT COALESCE(SUM(l.debit - l.credit), 0)
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
               INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE e.status = 'posted' AND e.deleted_at IS NULL
                AND a.is_bank = 1
                AND e.entry_date {$comparison} ?" . Ledger::scopeFor('e') . ""
        );
        $statement->execute([$date]);

        return round((float) $statement->fetchColumn(), 2);
    }

    // ------------------------------------------------------ account statement

    private static function accountStatement(?int $accountId, string $from, string $to): array
    {
        $account = $accountId === null ? null : Ledger::account($accountId);

        if ($account === null) {
            $doc = Report::make('Account Statement', self::day($from) . ' to ' . self::day($to), [
                ['label' => 'Account', 'align' => 'left', 'width' => 60],
                ['label' => 'Balance', 'align' => 'right', 'width' => 22, 'currency' => true],
            ]);
            Report::section($doc, 'CHOOSE AN ACCOUNT');
            foreach (Ledger::balances(null, $to) as $row) {
                Report::row($doc, [$row['account_code'] . ' - ' . $row['account_name'], (float) $row['closing']], 1);
            }

            return ['doc' => $doc, 'note' => 'Pick an account above to see every movement on it.'];
        }

        $doc = Report::make(
            'Account Statement - ' . $account['account_code'] . ' ' . $account['account_name'],
            self::day($from) . ' to ' . self::day($to),
            [
                ['label' => 'Date', 'align' => 'left', 'width' => 12],
                ['label' => 'Entry', 'align' => 'left', 'width' => 14],
                ['label' => 'Source', 'align' => 'left', 'width' => 16],
                ['label' => 'Description', 'align' => 'left', 'width' => 34],
                ['label' => 'Debit', 'align' => 'right', 'width' => 15, 'currency' => true],
                ['label' => 'Credit', 'align' => 'right', 'width' => 15, 'currency' => true],
                ['label' => 'Balance', 'align' => 'right', 'width' => 16, 'currency' => true],
            ]
        );

        $opening = Ledger::openingBalance((int) $account['id'], $from);
        Report::row($doc, ['', '', '', 'Opening balance', null, null, $opening], 0, 'total');

        $running = $opening;
        $debits = 0.0;
        $credits = 0.0;

        foreach (Ledger::movements((int) $account['id'], $from, $to) as $movement) {
            $debit = (float) $movement['debit'];
            $credit = (float) $movement['credit'];
            $running += $debit - $credit;
            $debits += $debit;
            $credits += $credit;

            Report::row($doc, [
                self::day((string) $movement['entry_date']),
                (string) $movement['entry_no'],
                (string) ($movement['source_code'] ?? ucfirst((string) ($movement['source_type'] ?? 'Manual'))),
                (string) ($movement['description'] ?: $movement['memo']),
                $debit > 0 ? $debit : null,
                $credit > 0 ? $credit : null,
                $running,
            ]);
        }

        Report::grand($doc, ['', '', '', 'Closing balance', $debits, $credits, round($running, 2)]);

        return ['doc' => $doc, 'note' => sprintf(
            '%s normally carries a %s balance. %s movements in this period.',
            $account['account_name'],
            $account['normal_balance'],
            number_format(count(Ledger::movements((int) $account['id'], $from, $to)))
        )];
    }

    // --------------------------------------------------------------- helpers

    /** A credit-normal account reads positive when it carries a credit balance. */
    private static function displayBalance(string $type, float $closing): float
    {
        return in_array($type, ['liability', 'equity', 'income'], true) ? -$closing : $closing;
    }
}
