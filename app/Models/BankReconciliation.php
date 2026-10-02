<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Agreeing the bank's statement with the books.
 *
 * Each line of the statement is matched to the journal entry that recorded
 * the same money: the customer receipt, the supplier payment, the cheque. What
 * is left over on either side explains the difference:
 *
 *   - in the books, not yet at the bank: a cheque the supplier has not cashed,
 *     a deposit made on the last day. These clear on next month's statement.
 *   - at the bank, not in the books: charges, interest, a transfer nobody
 *     recorded. These are posted from here, and then match themselves.
 *
 * Matching is to an entry, not to one of its lines, because reposting a
 * document (an edited payment) rewrites its lines but keeps its entry.
 */
final class BankReconciliation
{
    /** Days either side of the bank date that automatic matching will accept. */
    private const MATCH_WINDOW_DAYS = 7;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function statement(int $id): ?array
    {
        return self::row(
            'SELECT s.*, a.account_code, a.account_name, u.full_name AS reconciled_by_name,
                    pm.method_name, pm.provider_name, pm.branch_name, pm.account_name AS holder, pm.account_number, pm.swift_code
               FROM bank_statements s
               INNER JOIN gl_accounts a ON a.id = s.account_id
               LEFT JOIN payment_methods pm ON pm.id = s.payment_method_id
               LEFT JOIN users u ON u.id = s.reconciled_by
              WHERE s.id = ? AND s.deleted_at IS NULL',
            [$id]
        );
    }

    /** The statement's own lines, with the entry each one matched. */
    public static function bankLines(int $statementId): array
    {
        return self::rows(
            'SELECT l.*, e.entry_no, e.entry_date, e.memo
               FROM bank_statement_lines l
               LEFT JOIN gl_journal_entries e ON e.id = l.matched_entry_id
              WHERE l.statement_id = ?
              ORDER BY l.line_date, l.id',
            [$statementId]
        );
    }

    /**
     * Every entry that moved this bank account, in this currency, up to the end
     * of the statement, that no other statement has already claimed.
     *
     * `amount` is what it did to the bank: positive money in, negative money out.
     */
    public static function bookEntries(array $statement): array
    {
        return self::rows(
            "SELECT e.id, e.entry_no, e.entry_date, e.memo, e.reference, e.source_type, e.source_code,
                    SUM(l.debit - l.credit) AS amount,
                    (SELECT bl.statement_id FROM bank_statement_lines bl
                       INNER JOIN bank_statements bs ON bs.id = bl.statement_id AND bs.deleted_at IS NULL AND bs.account_id = :account
                      WHERE bl.matched_entry_id = e.id LIMIT 1) AS matched_statement_id
               FROM gl_journal_entries e
               INNER JOIN gl_journal_lines l ON l.entry_id = e.id AND l.account_id = :account2
              WHERE e.deleted_at IS NULL AND e.currency = :currency AND e.entry_date <= :to
                -- Before the first statement ever reconciled for this account,
                -- older entries are already inside its opening balance.
                AND (e.entry_date >= :from
                     OR EXISTS (SELECT 1 FROM bank_statements p
                                 WHERE p.account_id = :account3 AND p.deleted_at IS NULL AND p.id <> :statement2 AND p.period_to < :from2))
              GROUP BY e.id
             HAVING ROUND(amount, 2) <> 0 AND (matched_statement_id IS NULL OR matched_statement_id = :statement)
              ORDER BY e.entry_date, e.id",
            [
                'account' => (int) $statement['account_id'],
                'account2' => (int) $statement['account_id'],
                'currency' => (string) $statement['currency'],
                'to' => (string) $statement['period_to'],
                'statement' => (int) $statement['id'],
                'from' => (string) $statement['period_from'],
                'account3' => (int) $statement['account_id'],
                'statement2' => (int) $statement['id'],
                'from2' => (string) $statement['period_from'],
            ]
        );
    }

    /** What the books say the account holds at the statement date. */
    public static function ledgerBalance(array $statement): float
    {
        return round((float) self::scalar(
            'SELECT COALESCE(SUM(l.debit - l.credit), 0)
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
              WHERE l.account_id = ? AND e.deleted_at IS NULL AND e.currency = ? AND e.entry_date <= ?',
            [(int) $statement['account_id'], (string) $statement['currency'], (string) $statement['period_to']]
        ), 2);
    }

    /**
     * The reconciliation in figures.
     *
     * @return array{closing: float, ledger: float, in_books_only: float, at_bank_only: float, expected: float, difference: float, statement_total: float, opening_check: float, unmatched_bank: int, unmatched_books: int}
     */
    public static function summary(array $statement, array $bankLines, array $bookEntries): array
    {
        $ledger = self::ledgerBalance($statement);

        $inBooksOnly = 0.0;
        $unmatchedBooks = 0;
        foreach ($bookEntries as $entry) {
            if ($entry['matched_statement_id'] === null) {
                $inBooksOnly += (float) $entry['amount'];
                $unmatchedBooks++;
            }
        }

        $atBankOnly = 0.0;
        $unmatchedBank = 0;
        $statementTotal = 0.0;
        foreach ($bankLines as $line) {
            $statementTotal += (float) $line['amount'];
            if ($line['matched_entry_id'] === null) {
                $atBankOnly += (float) $line['amount'];
                $unmatchedBank++;
            }
        }

        $closing = round((float) $statement['closing_balance'], 2);
        // The bank should show the books, less what has not reached it yet,
        // plus what reached it that the books have not caught up with.
        $expected = round($ledger - $inBooksOnly + $atBankOnly, 2);

        return [
            'closing' => $closing,
            'ledger' => $ledger,
            'in_books_only' => round($inBooksOnly, 2),
            'at_bank_only' => round($atBankOnly, 2),
            'expected' => $expected,
            'difference' => round($closing - $expected, 2),
            'statement_total' => round($statementTotal, 2),
            // Opening + the lines should give the closing figure; if not, a
            // line was typed wrong or is missing from the import.
            'opening_check' => round((float) $statement['opening_balance'] + $statementTotal - $closing, 2),
            'unmatched_bank' => $unmatchedBank,
            'unmatched_books' => $unmatchedBooks,
        ];
    }

    // ------------------------------------------------------------- matching

    /** Pairs every unmatched bank line with an unmatched entry of the same amount near the same date. */
    public static function autoMatch(int $statementId): int
    {
        $statement = self::statement($statementId);
        if ($statement === null) {
            return 0;
        }
        self::assertOpen($statement);

        $entries = array_values(array_filter(self::bookEntries($statement), static fn (array $e): bool => $e['matched_statement_id'] === null));
        $matched = 0;

        foreach (self::bankLines($statementId) as $line) {
            if ($line['matched_entry_id'] !== null) {
                continue;
            }

            $best = null;
            $bestScore = PHP_INT_MAX;
            foreach ($entries as $index => $entry) {
                if (abs((float) $entry['amount'] - (float) $line['amount']) > 0.004) {
                    continue;
                }
                $days = (int) abs((strtotime((string) $entry['entry_date']) - strtotime((string) $line['line_date'])) / 86400);
                if ($days > self::MATCH_WINDOW_DAYS) {
                    continue;
                }
                // A shared reference beats a closer date.
                $reference = trim((string) $line['reference']);
                $sameReference = $reference !== '' && (
                    stripos((string) $entry['reference'], $reference) !== false
                    || stripos((string) $entry['source_code'], $reference) !== false
                    || stripos((string) $entry['memo'], $reference) !== false
                );
                $score = $days - ($sameReference ? 100 : 0);
                if ($score < $bestScore) {
                    $best = $index;
                    $bestScore = $score;
                }
            }

            if ($best !== null) {
                self::setMatch((int) $line['id'], (int) $entries[$best]['id']);
                unset($entries[$best]);
                $matched++;
            }
        }

        return $matched;
    }

    public static function match(int $statementId, int $lineId, int $entryId): void
    {
        $statement = self::statement($statementId);
        if ($statement === null) {
            throw new \RuntimeException('That statement no longer exists.');
        }
        self::assertOpen($statement);

        $line = self::row('SELECT * FROM bank_statement_lines WHERE id = ? AND statement_id = ?', [$lineId, $statementId]);
        if ($line === null) {
            throw new \RuntimeException('That bank line is not on this statement.');
        }

        $entry = null;
        foreach (self::bookEntries($statement) as $candidate) {
            if ((int) $candidate['id'] === $entryId) {
                $entry = $candidate;
            }
        }
        if ($entry === null || $entry['matched_statement_id'] !== null) {
            throw new \RuntimeException('That entry does not touch this bank account, or it is already matched.');
        }
        if (abs((float) $entry['amount'] - (float) $line['amount']) > 0.004) {
            throw new \RuntimeException(sprintf(
                'The bank line is %s but entry %s moved %s. Only the same amount can be matched; post the difference as its own line.',
                number_format((float) $line['amount'], 2), $entry['entry_no'], number_format((float) $entry['amount'], 2)
            ));
        }

        self::setMatch($lineId, $entryId);
    }

    public static function unmatch(int $statementId, int $lineId): void
    {
        $statement = self::statement($statementId);
        if ($statement === null) {
            throw new \RuntimeException('That statement no longer exists.');
        }
        self::assertOpen($statement);

        self::db()->prepare('UPDATE bank_statement_lines SET matched_entry_id = NULL WHERE id = ? AND statement_id = ?')
            ->execute([$lineId, $statementId]);
    }

    /**
     * Records in the books something only the bank knew about — a charge, interest
     * — and matches it in the same step.
     */
    public static function postLine(int $statementId, int $lineId, int $accountId): int
    {
        $statement = self::statement($statementId);
        if ($statement === null) {
            throw new \RuntimeException('That statement no longer exists.');
        }
        self::assertOpen($statement);

        $line = self::row('SELECT * FROM bank_statement_lines WHERE id = ? AND statement_id = ?', [$lineId, $statementId]);
        if ($line === null || $line['matched_entry_id'] !== null) {
            throw new \RuntimeException('That bank line is already matched.');
        }
        if ($accountId === (int) $statement['account_id']) {
            throw new \RuntimeException('Choose the account the money came from or went to, not the bank account itself.');
        }

        $amount = round(abs((float) $line['amount']), 2);
        $description = trim((string) $line['description']) ?: 'Bank statement line';
        $bank = (int) $statement['account_id'];
        $lines = (float) $line['amount'] > 0
            ? [['account' => $bank, 'debit' => $amount, 'description' => $description], ['account' => $accountId, 'credit' => $amount, 'description' => $description]]
            : [['account' => $accountId, 'debit' => $amount, 'description' => $description], ['account' => $bank, 'credit' => $amount, 'description' => $description]];

        $entryId = Ledger::post(
            (string) $line['line_date'],
            mb_substr('Bank: ' . $description, 0, 255),
            $lines,
            'bank_line',
            $lineId,
            (string) $statement['statement_code'],
            $line['reference'] ?: null,
            (string) $statement['currency']
        );

        self::setMatch($lineId, $entryId);

        return $entryId;
    }

    /**
     * Statement lines from the bank's export: a date, a description, a
     * reference and either one signed amount or separate money-in and money-out
     * columns.
     *
     * @param list<list<string>> $rows
     * @return array{added: int, errors: list<string>}
     */
    public static function import(int $statementId, array $rows): array
    {
        $statement = self::statement($statementId);
        if ($statement === null) {
            throw new \RuntimeException('That statement no longer exists.');
        }
        self::assertOpen($statement);

        $aliases = [
            'date' => ['date', 'transaction date', 'value date', 'posting date', 'booking date'],
            'description' => ['description', 'details', 'narration', 'particulars', 'transaction details', 'memo'],
            'reference' => ['reference', 'ref', 'cheque no', 'cheque number', 'transaction id', 'ref no'],
            'amount' => ['amount', 'net amount'],
            'in' => ['credit', 'money in', 'deposit', 'deposits', 'paid in', 'credit amount'],
            'out' => ['debit', 'money out', 'withdrawal', 'withdrawals', 'paid out', 'debit amount'],
        ];

        $map = null;
        $headerIndex = 0;
        foreach ($rows as $index => $row) {
            $found = [];
            foreach ($row as $column => $cell) {
                $label = strtolower(preg_replace('/\s+/', ' ', trim((string) $cell)) ?? '');
                foreach ($aliases as $key => $names) {
                    if (!isset($found[$key]) && in_array($label, $names, true)) {
                        $found[$key] = $column;
                    }
                }
            }
            if (isset($found['date']) && (isset($found['amount']) || isset($found['in']) || isset($found['out']))) {
                $map = $found;
                $headerIndex = $index;
                break;
            }
        }

        if ($map === null) {
            return ['added' => 0, 'errors' => ['No heading row found. The file needs a "Date" column and either an "Amount" column or "Credit" and "Debit" columns.']];
        }

        $parsed = [];
        $errors = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $cell = static fn (string $key): string => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
            $rawDate = $cell('date');
            if ($rawDate === '' && $cell('description') === '') {
                continue;
            }

            $date = self::parseDate($rawDate);
            if ($date === null) {
                $errors[] = sprintf('Row %d: "%s" is not a date.', $index + 1, $rawDate);
                continue;
            }

            if (isset($map['amount'])) {
                $amount = self::number($cell('amount'));
            } else {
                $in = self::number($cell('in'));
                $out = self::number($cell('out'));
                $amount = $in === null || $out === null ? null : abs($in) - abs($out);
            }
            if ($amount === null) {
                $errors[] = sprintf('Row %d: the amount is not a number.', $index + 1);
                continue;
            }
            if (round($amount, 2) == 0.0) {
                continue;
            }

            $parsed[] = [$date, mb_substr($cell('description'), 0, 255), mb_substr($cell('reference'), 0, 80), round($amount, 2)];
        }

        if ($errors !== []) {
            return ['added' => 0, 'errors' => $errors];
        }

        $insert = self::db()->prepare('INSERT INTO bank_statement_lines (statement_id, line_date, description, reference, amount) VALUES (?, ?, ?, ?, ?)');
        foreach ($parsed as [$date, $description, $reference, $amount]) {
            $insert->execute([$statementId, $date, $description ?: null, $reference ?: null, $amount]);
        }

        return ['added' => count($parsed), 'errors' => []];
    }

    /** Closes the statement once nothing is left unexplained. */
    public static function complete(int $statementId, ?int $actor): void
    {
        $statement = self::statement($statementId);
        if ($statement === null) {
            throw new \RuntimeException('That statement no longer exists.');
        }
        self::assertOpen($statement);

        $summary = self::summary($statement, self::bankLines($statementId), self::bookEntries($statement));
        if ($summary['unmatched_bank'] > 0) {
            throw new \RuntimeException(sprintf('%d bank line%s still unmatched. Match each one, or post it to the books.', $summary['unmatched_bank'], $summary['unmatched_bank'] === 1 ? ' is' : 's are'));
        }
        if (abs($summary['difference']) > 0.004) {
            throw new \RuntimeException(sprintf('There is still a difference of %s between the statement and the books.', number_format($summary['difference'], 2)));
        }

        self::db()->prepare("UPDATE bank_statements SET status = 'reconciled', reconciled_by = ?, reconciled_at = NOW() WHERE id = ?")
            ->execute([$actor, $statementId]);
    }

    public static function reopen(int $statementId): void
    {
        self::db()->prepare("UPDATE bank_statements SET status = 'open', reconciled_by = NULL, reconciled_at = NULL WHERE id = ?")
            ->execute([$statementId]);
    }

    // ---------------------------------------------------------------- helpers

    private static function setMatch(int $lineId, int $entryId): void
    {
        self::db()->prepare('UPDATE bank_statement_lines SET matched_entry_id = ? WHERE id = ?')->execute([$entryId, $lineId]);
    }

    private static function assertOpen(array $statement): void
    {
        if ($statement['status'] === 'reconciled') {
            throw new \RuntimeException('This statement is already reconciled. Reopen it first to change anything.');
        }
    }

    private static function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        // Excel keeps dates as a day count from 1899-12-30.
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return date('Y-m-d', (int) round(((float) $value - 25569) * 86400));
        }
        // Day first, the way East African banks print it: 05/10/2026.
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
        }
        $time = strtotime($value);

        return $time === false ? null : date('Y-m-d', $time);
    }

    private static function number(string $value): ?float
    {
        $clean = str_replace([',', ' ', "\u{00A0}"], '', $value);
        if ($clean === '' || $clean === '-') {
            return 0.0;
        }
        // (1,200.00) is how some banks print money out.
        if (preg_match('/^\((.+)\)$/', $clean, $m)) {
            $clean = '-' . $m[1];
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private static function row(string $sql, array $parameters): ?array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function rows(string $sql, array $parameters): array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function scalar(string $sql, array $parameters): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
