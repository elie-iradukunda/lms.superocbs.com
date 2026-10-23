<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Moving off Tally (section 14):
 *
 *   1. choose the go-live date (best at the start of a month);
 *   2. export from Tally the trial balance and the open customer and supplier
 *      balances at that date;
 *   3. load them here; Finance checks they match Tally and the MD signs them
 *      off, which posts them;
 *   4. from go-live everything is entered in the LMS only.
 *
 * The ledger balances post as one opening entry per currency (any difference
 * goes to Opening balance equity, 3900). Customer and supplier balances become
 * open invoices and bills, so they can be paid and aged, without posting again.
 */
final class OpeningBalances
{
    public const KINDS = [
        'ledger' => 'Trial balance (from Tally)',
        'customers' => 'Open customer balances',
        'suppliers' => 'Open supplier balances',
    ];

    /** The CSV columns each kind expects, in order. */
    public const COLUMNS = [
        'ledger' => ['account_code', 'currency', 'debit', 'credit', 'description'],
        'customers' => ['customer', 'currency', 'amount', 'reference', 'due_date'],
        'suppliers' => ['supplier', 'currency', 'amount', 'reference', 'due_date'],
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /** @return list<array<string, mixed>> */
    public static function forCompany(int $companyId): array
    {
        $statement = self::db()->prepare(
            'SELECT o.*, pu.full_name AS prepared_name, cu.full_name AS checked_name, su.full_name AS signed_name
               FROM opening_balances o
               LEFT JOIN users pu ON pu.id = o.prepared_by
               LEFT JOIN users cu ON cu.id = o.checked_by
               LEFT JOIN users su ON su.id = o.signed_by
              WHERE o.company_id = ? ORDER BY o.id DESC'
        );
        $statement->execute([$companyId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['rows'] = json_decode((string) $row['rows_json'], true) ?: [];
        }

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM opening_balances WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false || (\company_scope() !== null && !in_array((int) $row['company_id'], \company_scope(), true))) {
            return null;
        }
        $row['rows'] = json_decode((string) $row['rows_json'], true) ?: [];

        return $row;
    }

    /**
     * Reads a CSV exported from Tally (a header row, then one row per line)
     * into rows of the kind's columns.
     *
     * @return array{rows: list<array<string, string>>, problems: list<string>}
     */
    public static function parse(string $kind, string $csv): array
    {
        $columns = self::COLUMNS[$kind] ?? throw new \RuntimeException('Unknown kind of opening balance.');
        $rows = [];
        $problems = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = null;
        $number = 0;
        while (($fields = fgetcsv($handle)) !== false) {
            $number++;
            if ($fields === [null] || implode('', $fields) === '') {
                continue;
            }
            if ($header === null) {
                $header = array_map(static fn ($h): string => strtolower(trim((string) $h)), $fields);
                $missing = array_diff(array_slice($columns, 0, 3), $header);
                if ($missing !== []) {
                    $problems[] = 'The first row must name the columns: ' . implode(', ', $columns) . '. Missing: ' . implode(', ', $missing) . '.';
                    break;
                }
                continue;
            }
            $row = [];
            foreach ($columns as $column) {
                $index = array_search($column, $header, true);
                $row[$column] = $index === false ? '' : trim((string) ($fields[$index] ?? ''));
            }
            $row['currency'] = strtoupper($row['currency']);
            foreach (['debit', 'credit', 'amount'] as $money) {
                if (array_key_exists($money, $row)) {
                    $row[$money] = (string) (float) str_replace([',', ' '], '', $row[$money]);
                }
            }
            $rows[] = $row + ['_line' => (string) $number];
        }
        fclose($handle);

        return ['rows' => $rows, 'problems' => array_merge($problems, self::problems($kind, $rows))];
    }

    /** @return list<string> */
    public static function problems(string $kind, array $rows): array
    {
        $problems = [];
        if ($rows === []) {
            return ['There is nothing to load.'];
        }
        $currencies = array_keys(Currency::options());
        foreach ($rows as $row) {
            $where = 'Line ' . ($row['_line'] ?? '?');
            if (!in_array($row['currency'], $currencies, true)) {
                $problems[] = $where . ': currency "' . $row['currency'] . '" is not one in use.';
            }
            if ($kind === 'ledger') {
                if (Ledger::idOf((string) $row['account_code']) === null) {
                    $problems[] = $where . ': account ' . $row['account_code'] . ' is not in the chart of accounts.';
                }
                if ((float) $row['debit'] < 0 || (float) $row['credit'] < 0 || ((float) $row['debit'] > 0 && (float) $row['credit'] > 0)) {
                    $problems[] = $where . ': a line is either a debit or a credit, never negative.';
                }
            } else {
                $table = $kind === 'customers' ? 'customers' : 'suppliers';
                if (self::party($table, (string) $row[$kind === 'customers' ? 'customer' : 'supplier']) === null) {
                    $problems[] = sprintf('%s: %s "%s" is not registered. Add it first.', $where, $kind === 'customers' ? 'customer' : 'supplier', $row[$kind === 'customers' ? 'customer' : 'supplier']);
                }
                if ((float) $row['amount'] <= 0) {
                    $problems[] = $where . ': the open amount must be more than zero.';
                }
            }
        }

        return array_slice($problems, 0, 30);
    }

    /** Totals per currency, for the check against Tally. @return array<string, array{debit: float, credit: float}> */
    public static function totals(string $kind, array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $code = (string) $row['currency'];
            $totals[$code] ??= ['debit' => 0.0, 'credit' => 0.0];
            if ($kind === 'ledger') {
                $totals[$code]['debit'] += (float) $row['debit'];
                $totals[$code]['credit'] += (float) $row['credit'];
            } else {
                $totals[$code]['debit'] += (float) $row['amount'];
            }
        }

        return $totals;
    }

    public static function save(int $companyId, string $kind, string $asOf, array $rows, string $notes = ''): int
    {
        if (!isset(self::KINDS[$kind])) {
            throw new \RuntimeException('Choose what is being loaded.');
        }
        if (strtotime($asOf) === false) {
            throw new \RuntimeException('Give the go-live date.');
        }
        $problems = self::problems($kind, $rows);
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }
        $signed = self::db()->prepare("SELECT COUNT(*) FROM opening_balances WHERE company_id = ? AND kind = ? AND status = 'signed'");
        $signed->execute([$companyId, $kind]);
        if ((int) $signed->fetchColumn() > 0) {
            throw new \RuntimeException('These opening balances are already signed off. Any correction is a journal entry now.');
        }
        self::db()->prepare('INSERT INTO opening_balances (company_id, as_of, kind, rows_json, status, prepared_by, notes) VALUES (?, ?, ?, ?, \'draft\', ?, ?)')
            ->execute([$companyId, date('Y-m-d', (int) strtotime($asOf)), $kind, json_encode(array_values($rows)), \current_user_id(), trim($notes) ?: null]);
        $id = (int) self::db()->lastInsertId();
        AuditLog::record('opening.loaded', 'opening_balances', (string) $id, null, ['kind' => $kind, 'lines' => count($rows)]);

        return $id;
    }

    /** Finance checked the totals against Tally. */
    public static function check(int $id): void
    {
        $opening = self::find($id) ?? throw new \RuntimeException('Not found.');
        if ($opening['status'] !== 'draft') {
            throw new \RuntimeException('Already checked.');
        }
        self::db()->prepare("UPDATE opening_balances SET status = 'checked', checked_by = ?, checked_at = NOW() WHERE id = ?")->execute([\current_user_id(), $id]);
        AuditLog::record('opening.checked', 'opening_balances', (string) $id);
        Notifier::toRole('management', 'Opening balances to sign off', sprintf('%s as at %s were checked against Tally by %s.', self::KINDS[$opening['kind']], human_date((string) $opening['as_of']), \current_user_name()), 'opening_balances', 'warning', 'opening_balances', (string) $id);
    }

    /** The MD signs them off: they post, and they cannot be changed afterwards. */
    public static function signOff(int $id): void
    {
        $opening = self::find($id) ?? throw new \RuntimeException('Not found.');
        if ($opening['status'] !== 'checked') {
            throw new \RuntimeException('Finance must check them against Tally first.');
        }
        if ((int) $opening['checked_by'] === (int) \current_user_id()) {
            throw new \RuntimeException('The person who checked them cannot also sign them off.');
        }
        $companyId = (int) $opening['company_id'];
        $date = date('Y-m-d', (int) strtotime($opening['as_of'] . ' -1 day'));
        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        $entries = [];
        try {
            if ($opening['kind'] === 'ledger') {
                $byCurrency = [];
                foreach ($opening['rows'] as $row) {
                    $byCurrency[$row['currency']][] = $row;
                }
                foreach ($byCurrency as $currency => $rows) {
                    $lines = [];
                    $difference = 0.0;
                    foreach ($rows as $row) {
                        $lines[] = ['account' => (string) $row['account_code'], 'debit' => (float) $row['debit'], 'credit' => (float) $row['credit'], 'description' => $row['description'] ?: 'Opening balance'];
                        $difference += (float) $row['debit'] - (float) $row['credit'];
                    }
                    if (abs($difference) > 0.004) {
                        $lines[] = ['account' => '3900', 'debit' => $difference < 0 ? -$difference : 0, 'credit' => $difference > 0 ? $difference : 0, 'description' => 'Difference to opening balance equity'];
                    }
                    $entries[] = Ledger::post($date, 'Opening balances from Tally (' . $currency . ')', $lines, 'opening', $id * 100 + count($entries), 'OPENING', null, (string) $currency, $companyId);
                }
            } else {
                foreach ($opening['rows'] as $index => $row) {
                    $due = !empty($row['due_date']) && strtotime((string) $row['due_date']) !== false ? date('Y-m-d', (int) strtotime((string) $row['due_date'])) : $date;
                    if ($opening['kind'] === 'customers') {
                        $customer = self::party('customers', (string) $row['customer']);
                        $db->prepare(
                            "INSERT INTO invoices (company_id, invoice_number, customer_id, issue_date, due_date, subtotal, tax_rate, tax_amount, total_amount, currency, exchange_rate, amount_paid, status, issued_by, notes, is_opening)
                             VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, 1, 0, 'issued', ?, ?, 1)"
                        )->execute([$companyId, mb_substr('OB-' . ($row['reference'] ?: ($id . '-' . ($index + 1))), 0, 32), (int) $customer['id'], $date, $due, (float) $row['amount'], (float) $row['amount'], $row['currency'], \current_user_id(), 'Open balance from Tally']);
                    } else {
                        $supplier = self::party('suppliers', (string) $row['supplier']);
                        $db->prepare(
                            "INSERT INTO supplier_bills (company_id, bill_no, supplier_id, supplier_invoice_no, bill_date, due_date, currency, subtotal, tax_rate, tax_amount, total_amount, amount_paid, status, approved_by, approved_at, notes, is_opening)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, 'approved', ?, NOW(), ?, 1)"
                        )->execute([$companyId, mb_substr('OB-' . $id . '-' . ($index + 1), 0, 32), (int) $supplier['id'], $row['reference'] ?: null, $date, $due, $row['currency'], (float) $row['amount'], (float) $row['amount'], \current_user_id(), 'Open balance from Tally']);
                    }
                }
            }
            $db->prepare("UPDATE opening_balances SET status = 'signed', signed_by = ?, signed_at = NOW(), entry_ids = ? WHERE id = ?")
                ->execute([\current_user_id(), $entries === [] ? null : implode(',', $entries), $id]);
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        AuditLog::record('opening.signed', 'opening_balances', (string) $id, null, ['kind' => $opening['kind']]);
    }

    /** A customer or supplier by its code or its name. */
    private static function party(string $table, string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $code = $table === 'customers' ? 'customer_code' : 'supplier_code';
        $name = $table === 'customers' ? 'customer_name' : 'supplier_name';
        $company = Company::condition($table, 'company_id');
        $statement = self::db()->prepare("SELECT * FROM {$table} WHERE deleted_at IS NULL AND ({$code} = ? OR {$name} = ?)" . ($company !== null ? " AND {$company}" : '') . ' LIMIT 1');
        $statement->execute([$value, $value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
