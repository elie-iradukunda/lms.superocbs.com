<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\AuditLog;
use Models\Currency;
use Models\Ledger;
use Models\Schema;
use Support\Report;
use Support\XlsxReader;
use Support\XlsxWriter;

/**
 * Bringing an existing chart of accounts (and its opening balances) in from
 * Excel, so a company moving onto the system does not type hundreds of
 * accounts by hand.
 *
 * The file is checked as a whole first. If any row is wrong nothing is saved
 * and every problem is listed with its row number, so the user fixes the file
 * once and uploads it again.
 */
final class ImportController
{
    /** Header in the file => internal key. Matching ignores case and spacing. */
    private const COLUMNS = [
        'account code' => 'code',
        'account name' => 'name',
        'type' => 'type',
        'normal balance' => 'normal',
        'statement section' => 'section',
        'section order' => 'order',
        'parent code' => 'parent',
        'heading (yes/no)' => 'header',
        'bank or cash (yes/no)' => 'bank',
        'cash flow' => 'cashflow',
        'description' => 'description',
        'opening debit' => 'opening_debit',
        'opening credit' => 'opening_credit',
    ];

    private const SECTION_ORDER = [
        'Cash and bank' => 10, 'Accounts receivable' => 20, 'Other current assets' => 30, 'Fixed assets' => 40,
        'Accounts payable' => 50, 'Other current liabilities' => 60, 'Long term liabilities' => 70, 'Equity' => 80,
        'Income' => 100, 'Cost of sales' => 110, 'Operating expenses' => 120, 'Other income' => 140, 'Other expenses' => 150,
    ];

    private const DEFAULT_SECTION = [
        'asset' => 'Other current assets', 'liability' => 'Other current liabilities', 'equity' => 'Equity',
        'income' => 'Income', 'cost_of_sales' => 'Cost of sales', 'expense' => 'Operating expenses',
    ];

    public function accountsForm(): void
    {
        $this->render([], null);
    }

    /** The current chart, in the exact layout the import reads: edit it and upload it back. */
    public function accountsTemplate(): void
    {
        $doc = Report::make('Chart of accounts import', 'Fill one account per row. Keep the heading row as it is.', array_map(
            static fn (string $label): array => ['label' => strtoupper($label), 'align' => 'left', 'width' => in_array($label, ['account name', 'description'], true) ? 34 : 16],
            array_keys(self::COLUMNS)
        ));

        $accounts = Database::connection()->query(
            'SELECT a.*, p.account_code AS parent_code FROM gl_accounts a
               LEFT JOIN gl_accounts p ON p.id = a.parent_id
              WHERE a.deleted_at IS NULL ORDER BY a.section_order, a.account_code'
        )->fetchAll();

        foreach ($accounts as $account) {
            Report::row($doc, [
                (string) $account['account_code'], (string) $account['account_name'], (string) $account['account_type'],
                (string) $account['normal_balance'], (string) $account['report_section'], (string) $account['section_order'],
                (string) ($account['parent_code'] ?? ''), $account['is_header'] ? 'yes' : 'no', $account['is_bank'] ? 'yes' : 'no',
                (string) $account['cash_flow_class'], (string) ($account['description'] ?? ''), null, null,
            ]);
        }

        AuditLog::record('accounts.template_downloaded', 'accounts');
        XlsxWriter::send($doc);
    }

    public function accountsImport(): void
    {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->render(['Choose the Excel or CSV file to import.'], null);
            return;
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            $this->render(['The file must be Excel (.xlsx) or CSV (.csv). Old .xls files: open them in Excel and save as .xlsx.'], null);
            return;
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            $this->render(['The file is larger than 5 MB.'], null);
            return;
        }

        try {
            $rows = XlsxReader::rows((string) $file['tmp_name'], (string) $file['name']);
        } catch (\Throwable $exception) {
            $this->render([$exception->getMessage()], null);
            return;
        }

        $openingDate = trim((string) ($_POST['opening_date'] ?? '')) ?: date('Y-01-01');
        $updateExisting = isset($_POST['update_existing']);

        [$accounts, $errors] = $this->parse($rows);
        if ($errors !== []) {
            $this->render($errors, null);
            return;
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $summary = $this->store($accounts, $updateExisting);
            $summary['opening'] = $this->postOpeningBalances($accounts, $openingDate);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->render(['Nothing was imported: ' . $exception->getMessage()], null);
            return;
        }

        AuditLog::record('accounts.imported', 'accounts', null, null, [
            'file' => (string) $file['name'], 'added' => $summary['added'], 'updated' => $summary['updated'], 'skipped' => $summary['skipped'],
        ]);
        Flash::success(sprintf('%d account(s) added, %d updated, %d left unchanged.', $summary['added'], $summary['updated'], $summary['skipped']));
        $this->render([], $summary);
    }

    /**
     * @param list<list<string>> $rows
     * @return array{0: list<array>, 1: list<string>}
     */
    private function parse(array $rows): array
    {
        // The heading row may sit under a title block (the template has one),
        // so look for it instead of assuming it is row 1.
        $map = null;
        $headerIndex = 0;
        foreach ($rows as $index => $row) {
            $found = [];
            foreach ($row as $column => $cell) {
                $label = strtolower(preg_replace('/\s+/', ' ', trim($cell)) ?? '');
                if (isset(self::COLUMNS[$label])) {
                    $found[self::COLUMNS[$label]] = $column;
                }
            }
            if (isset($found['code'], $found['name'])) {
                $map = $found;
                $headerIndex = $index;
                break;
            }
        }

        if ($map === null) {
            return [[], ['The file has no heading row with "Account code" and "Account name". Download the template and keep its heading row.']];
        }

        $types = Schema::ACCOUNT_TYPES;
        $typeByLabel = array_change_key_case(array_flip($types));
        $sections = array_keys(self::SECTION_ORDER);
        $sectionByLower = array_combine(array_map('strtolower', $sections), $sections);

        $accounts = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $cell = static fn (string $key): string => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
            $line = $index + 1;
            $code = $cell('code');
            if ($code === '' && $cell('name') === '') {
                continue;
            }

            if ($code === '') {
                $errors[] = "Row {$line}: the account code is empty.";
                continue;
            }
            if (isset($seen[$code])) {
                $errors[] = "Row {$line}: account code {$code} appears twice in the file (also row {$seen[$code]}).";
                continue;
            }
            $seen[$code] = $line;

            $name = $cell('name');
            if ($name === '') {
                $errors[] = "Row {$line}: account {$code} has no name.";
            }

            $rawType = strtolower(str_replace([' ', '-'], '_', $cell('type')));
            $type = isset($types[$rawType]) ? $rawType : ($typeByLabel[strtolower($cell('type'))] ?? null);
            if ($type === null) {
                $errors[] = "Row {$line}: account {$code} has type \"{$cell('type')}\"; use asset, liability, equity, income, cost_of_sales or expense.";
                continue;
            }

            $normal = strtolower($cell('normal'));
            if ($normal === '') {
                $normal = in_array($type, ['asset', 'expense', 'cost_of_sales'], true) ? 'debit' : 'credit';
            } elseif (!in_array($normal, ['debit', 'credit'], true)) {
                $errors[] = "Row {$line}: normal balance must be debit or credit.";
            }

            $section = $sectionByLower[strtolower($cell('section'))] ?? null;
            if ($cell('section') !== '' && $section === null) {
                $errors[] = "Row {$line}: statement section \"{$cell('section')}\" is not one of: " . implode(', ', $sections) . '.';
            }
            $section ??= self::DEFAULT_SECTION[$type];

            $cashflow = strtolower($cell('cashflow'));
            if (!in_array($cashflow, ['operating', 'investing', 'financing', 'cash', 'none'], true)) {
                $cashflow = match ($section) {
                    'Cash and bank' => 'cash',
                    'Fixed assets' => 'investing',
                    'Long term liabilities', 'Equity' => 'financing',
                    default => 'operating',
                };
            }

            $debit = $this->amount($cell('opening_debit'));
            $credit = $this->amount($cell('opening_credit'));
            if ($debit === null || $credit === null) {
                $errors[] = "Row {$line}: opening balances must be numbers.";
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                $errors[] = "Row {$line}: give account {$code} an opening debit or an opening credit, not both.";
            }
            $isHeader = $this->yes($cell('header'));
            if ($isHeader && ($debit > 0 || $credit > 0)) {
                $errors[] = "Row {$line}: {$code} is a heading, so it cannot carry an opening balance.";
            }

            $accounts[] = [
                'line' => $line, 'code' => $code, 'name' => mb_substr($name, 0, 150), 'type' => $type, 'normal' => $normal,
                'section' => $section, 'order' => $cell('order') !== '' ? (int) $cell('order') : self::SECTION_ORDER[$section],
                'parent' => $cell('parent'), 'header' => $isHeader, 'bank' => $this->yes($cell('bank')),
                'cashflow' => $cashflow, 'description' => mb_substr($cell('description'), 0, 255),
                'opening_debit' => $debit, 'opening_credit' => $credit,
            ];
        }

        if ($accounts === [] && $errors === []) {
            $errors[] = 'The file has a heading row but no accounts under it.';
        }

        return [$accounts, $errors];
    }

    /** @return array{added: int, updated: int, skipped: int, lines: list<array>} */
    private function store(array $accounts, bool $updateExisting): array
    {
        $db = Database::connection();
        $find = $db->prepare('SELECT id FROM gl_accounts WHERE account_code = ?');
        $insert = $db->prepare(
            'INSERT INTO gl_accounts (account_code, account_name, account_type, normal_balance, is_header, is_contra, depth,
                                      report_section, section_order, cash_flow_class, is_bank, description, is_active)
             VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, 1)'
        );
        $update = $db->prepare(
            'UPDATE gl_accounts SET account_name = ?, account_type = ?, normal_balance = ?, is_header = ?, report_section = ?,
                    section_order = ?, cash_flow_class = ?, is_bank = ?, description = ?, deleted_at = NULL, is_active = 1
              WHERE id = ?'
        );

        $summary = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'lines' => []];
        foreach ($accounts as $account) {
            $find->execute([$account['code']]);
            $id = $find->fetchColumn();
            $values = [$account['name'], $account['type'], $account['normal'], (int) $account['header'], $account['section'],
                       $account['order'], $account['cashflow'], (int) $account['bank'], $account['description'] ?: null];

            if ($id === false) {
                $insert->execute([$account['code'], ...$values]);
                $summary['added']++;
                $summary['lines'][] = ['code' => $account['code'], 'name' => $account['name'], 'result' => 'Added'];
            } elseif ($updateExisting) {
                $update->execute([...$values, (int) $id]);
                $summary['updated']++;
                $summary['lines'][] = ['code' => $account['code'], 'name' => $account['name'], 'result' => 'Updated'];
            } else {
                $summary['skipped']++;
                $summary['lines'][] = ['code' => $account['code'], 'name' => $account['name'], 'result' => 'Already there, left as it was'];
            }
        }

        // Parents last: a parent may appear further down the file than its children.
        $setParent = $db->prepare(
            'UPDATE gl_accounts c INNER JOIN gl_accounts p ON p.account_code = ? SET c.parent_id = p.id, c.depth = LEAST(p.depth + 1, 3) WHERE c.account_code = ?'
        );
        foreach ($accounts as $account) {
            if ($account['parent'] !== '' && $account['parent'] !== $account['code']) {
                $find->execute([$account['parent']]);
                if ($find->fetchColumn() === false) {
                    throw new \RuntimeException(sprintf('row %d: parent code %s does not exist.', $account['line'], $account['parent']));
                }
                $setParent->execute([$account['parent'], $account['code']]);
            }
        }

        return $summary;
    }

    /**
     * One balanced journal entry carrying every opening balance in the file. If
     * the file's debits and credits differ, the difference goes to Retained
     * earnings and the summary says so, rather than refusing a whole chart over
     * a balance the accountant may still be finalising.
     */
    private function postOpeningBalances(array $accounts, string $date): ?array
    {
        $lines = [];
        $debits = 0.0;
        $credits = 0.0;
        foreach ($accounts as $account) {
            if ($account['opening_debit'] > 0) {
                $lines[] = ['account' => $account['code'], 'debit' => $account['opening_debit'], 'description' => 'Opening balance'];
                $debits += $account['opening_debit'];
            } elseif ($account['opening_credit'] > 0) {
                $lines[] = ['account' => $account['code'], 'credit' => $account['opening_credit'], 'description' => 'Opening balance'];
                $credits += $account['opening_credit'];
            }
        }

        if ($lines === []) {
            return null;
        }

        $difference = round($debits - $credits, 2);
        if ($difference !== 0.0) {
            $lines[] = $difference > 0
                ? ['account' => '3100', 'credit' => $difference, 'description' => 'Opening balance difference']
                : ['account' => '3100', 'debit' => -$difference, 'description' => 'Opening balance difference'];
        }

        // Keyed on the date, so importing the balances again for the same date
        // replaces that entry instead of doubling every balance.
        $entryId = Ledger::post(
            $date,
            'Opening balances as at ' . date('d M Y', (int) strtotime($date)),
            $lines,
            'opening_balance',
            (int) date('Ymd', (int) strtotime($date)),
            'OPENING-' . date('Y-m-d', (int) strtotime($date)),
            'Imported chart of accounts',
            Currency::base()
        );

        return ['entry_id' => $entryId, 'debits' => $debits, 'credits' => $credits, 'difference' => $difference, 'date' => $date];
    }

    private function amount(string $value): ?float
    {
        $clean = str_replace([',', ' '], '', $value);
        if ($clean === '' || $clean === '-') {
            return 0.0;
        }

        return is_numeric($clean) ? abs((float) $clean) : null;
    }

    private function yes(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['yes', 'y', '1', 'true', 'x'], true);
    }

    private function render(array $errors, ?array $summary): void
    {
        \view('accounts/import', [
            'title' => 'Import chart of accounts',
            'errors' => $errors,
            'summary' => $summary,
        ]);
    }
}
