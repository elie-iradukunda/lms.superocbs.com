<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The one place the books ask the ledger its two questions:
 *
 *   what is each account's balance, and what moved in this period?
 *
 * Every book reads these, so the Trial Balance and the Balance Sheet cannot
 * disagree: they are looking at the same numbers, not at two similar queries
 * written months apart.
 *
 * Balances are always derived - SUM(debit) - SUM(credit) - and never stored, so
 * an edited, reversed or deleted entry corrects its own balance with no rebuild.
 */
final class Ledger
{
    /** Types whose balance belongs on the Balance Sheet rather than the P&L. */
    public const BALANCE_SHEET_TYPES = ['asset', 'liability', 'equity'];
    public const INCOME_STATEMENT_TYPES = ['income', 'cost_of_sales', 'expense'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    // ---------------------------------------------------------------- chart

    /** @return list<array<string, mixed>> */
    public static function accounts(bool $includeInactive = false): array
    {
        $sql = 'SELECT * FROM gl_accounts WHERE deleted_at IS NULL';
        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY section_order, account_code';

        return self::db()->query($sql)->fetchAll();
    }

    public static function account(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM gl_accounts WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function accountByCode(string $code): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM gl_accounts WHERE account_code = ? AND deleted_at IS NULL LIMIT 1');
        $statement->execute([$code]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** The id of an account by its code, for the posting rules. */
    public static function idOf(string $code): ?int
    {
        static $cache = [];

        if (array_key_exists($code, $cache)) {
            return $cache[$code];
        }

        $account = self::accountByCode($code);

        return $cache[$code] = $account === null ? null : (int) $account['id'];
    }

    /** Options for a select, as "1020 - Bank - main account". */
    public static function accountOptions(bool $postableOnly = true): array
    {
        $options = [];
        foreach (self::accounts() as $account) {
            if ($postableOnly && (int) $account['is_header'] === 1) {
                continue;
            }
            $options[(int) $account['id']] = $account['account_code'] . ' - ' . $account['account_name'];
        }

        return $options;
    }

    // ------------------------------------------------------------- balances

    /**
     * Every account with its opening balance, what moved in the period, and its
     * closing balance. One query, so every book agrees with every other.
     *
     * A debit balance is positive and a credit balance negative, whatever the
     * account's normal side; the books flip the sign where a statement expects
     * it, which keeps the arithmetic here in one direction.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Which currency's books are being read.
     *
     * A company working in three countries keeps three sets of books, one per
     * currency, because nothing is converted: a shilling receipt is counted in
     * shillings and balances against shillings. Every question asked of the
     * ledger is therefore asked of one currency at a time, and this is what
     * says which. Null means all of them, which only makes sense for counting
     * entries, never for a balance.
     */
    private static ?string $scope = null;

    /** Reads the books in one currency until it is set again. */
    public static function inCurrency(?string $code): void
    {
        $code = strtoupper(trim((string) $code));
        self::$scope = $code === '' || $code === 'ALL' ? null : $code;
    }

    public static function currentCurrency(): ?string
    {
        return self::$scope;
    }

    /**
     * The condition that narrows a query to the currency in scope.
     *
     * Returned as literal SQL rather than a placeholder because these queries
     * are assembled in a dozen places, several of which already bind their
     * parameters positionally. The value never comes from a user: it is matched
     * against the configured currencies before it reaches here.
     */
    /** The same condition, for the books that write their own SQL. */
    public static function scopeFor(string $alias = 'e'): string
    {
        return self::scopeSql($alias);
    }

    private static function scopeSql(string $alias = 'e'): string
    {
        if (self::$scope === null) {
            return '';
        }

        return sprintf(" AND %s.currency = '%s'", $alias, self::$scope);
    }

    public static function balances(?string $from = null, ?string $to = null, bool $includeZero = false): array
    {
        $to ??= date('Y-m-d');
        $from ??= '1900-01-01';

        $sql = "SELECT a.id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                       a.report_section, a.section_order, a.depth, a.is_header, a.is_contra,
                       a.cash_flow_class, a.is_bank,
                       COALESCE(SUM(CASE WHEN e.entry_date < :f1 THEN l.debit - l.credit END), 0) AS opening,
                       COALESCE(SUM(CASE WHEN e.entry_date >= :f2 AND e.entry_date <= :t1 THEN l.debit  END), 0) AS period_debit,
                       COALESCE(SUM(CASE WHEN e.entry_date >= :f3 AND e.entry_date <= :t2 THEN l.credit END), 0) AS period_credit,
                       COALESCE(SUM(CASE WHEN e.entry_date <= :t3 THEN l.debit - l.credit END), 0) AS closing,
                       COUNT(l.id) AS line_count
                  FROM gl_accounts a
                  LEFT JOIN gl_journal_lines l ON l.account_id = a.id
                  LEFT JOIN gl_journal_entries e
                         ON e.id = l.entry_id
                        AND e.status = 'posted'
                        AND e.deleted_at IS NULL
                        AND e.entry_date <= :t4" . self::scopeSql() . "
                 WHERE a.deleted_at IS NULL AND a.is_active = 1
                 GROUP BY a.id
                 ORDER BY a.section_order, a.account_code";

        $statement = self::db()->prepare($sql);
        $statement->execute([
            ':f1' => $from, ':f2' => $from, ':f3' => $from,
            ':t1' => $to, ':t2' => $to, ':t3' => $to, ':t4' => $to,
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($includeZero) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $r): bool =>
            abs((float) $r['closing']) > 0.004
            || abs((float) $r['period_debit']) > 0.004
            || abs((float) $r['period_credit']) > 0.004));
    }

    /**
     * The profit made between two dates, from the income and expense accounts.
     * The Balance Sheet needs it to show this year's earnings inside equity.
     */
    public static function profitBetween(string $from, string $to): float
    {
        $statement = self::db()->prepare(
            "SELECT COALESCE(SUM(l.credit - l.debit), 0)
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
               INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE e.status = 'posted' AND e.deleted_at IS NULL
                AND a.deleted_at IS NULL
                AND a.account_type IN ('income', 'cost_of_sales', 'expense')
                AND e.entry_date BETWEEN ? AND ?" . self::scopeSql()
        );
        $statement->execute([$from, $to]);

        return round((float) $statement->fetchColumn(), 2);
    }

    // -------------------------------------------------------------- entries

    /**
     * Journal entries with their lines, newest first.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int}
     */
    public static function entries(array $query = [], int $perPage = 50): array
    {
        $conditions = ["e.status <> 'draft'", 'e.deleted_at IS NULL'];
        $parameters = [];

        if (!empty($query['from']) && strtotime((string) $query['from']) !== false) {
            $conditions[] = 'e.entry_date >= ?';
            $parameters[] = date('Y-m-d', (int) strtotime((string) $query['from']));
        }
        if (!empty($query['to']) && strtotime((string) $query['to']) !== false) {
            $conditions[] = 'e.entry_date <= ?';
            $parameters[] = date('Y-m-d', (int) strtotime((string) $query['to']));
        }
        if (!empty($query['source'])) {
            $conditions[] = 'e.source_type = ?';
            $parameters[] = (string) $query['source'];
        }
        if (!empty($query['q'])) {
            $conditions[] = '(e.entry_no LIKE ? OR e.memo LIKE ? OR e.source_code LIKE ? OR e.reference LIKE ?)';
            $like = '%' . $query['q'] . '%';
            array_push($parameters, $like, $like, $like, $like);
        }

        $where = implode(' AND ', $conditions);

        $count = self::db()->prepare("SELECT COUNT(*) FROM gl_journal_entries e WHERE {$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($query['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $statement = self::db()->prepare(
            "SELECT e.*, COALESCE(u.full_name, 'System') AS posted_by_name,
                    (SELECT COALESCE(SUM(debit), 0) FROM gl_journal_lines WHERE entry_id = e.id) AS total_debit
               FROM gl_journal_entries e
               LEFT JOIN users u ON u.id = e.posted_by
              WHERE {$where}
              ORDER BY e.entry_date DESC, e.id DESC
              LIMIT {$perPage} OFFSET {$offset}"
        );
        $statement->execute($parameters);

        return ['rows' => $statement->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public static function entry(int $id): ?array
    {
        $statement = self::db()->prepare(
            "SELECT e.*, COALESCE(u.full_name, 'System') AS posted_by_name
               FROM gl_journal_entries e
               LEFT JOIN users u ON u.id = e.posted_by
              WHERE e.id = ? AND e.deleted_at IS NULL LIMIT 1"
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function lines(int $entryId): array
    {
        $statement = self::db()->prepare(
            'SELECT l.*, a.account_code, a.account_name
               FROM gl_journal_lines l
               INNER JOIN gl_accounts a ON a.id = l.account_id
              WHERE l.entry_id = ?
              ORDER BY l.line_no, l.id'
        );
        $statement->execute([$entryId]);

        return $statement->fetchAll();
    }

    /**
     * Every line that touched one account in a period, in date order, so a
     * balance on a statement can be traced to the entries that produced it.
     *
     * @return list<array<string, mixed>>
     */
    public static function movements(int $accountId, string $from, string $to): array
    {
        $statement = self::db()->prepare(
            "SELECT e.id AS entry_id, e.entry_no, e.entry_date, e.memo, e.source_type, e.source_code,
                    l.description, l.debit, l.credit
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
              WHERE l.account_id = ?
                AND e.status = 'posted' AND e.deleted_at IS NULL
                AND e.entry_date BETWEEN ? AND ?" . self::scopeSql() . "
              ORDER BY e.entry_date, e.id, l.line_no"
        );
        $statement->execute([$accountId, $from, $to]);

        return $statement->fetchAll();
    }

    public static function openingBalance(int $accountId, string $before): float
    {
        $statement = self::db()->prepare(
            "SELECT COALESCE(SUM(l.debit - l.credit), 0)
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
              WHERE l.account_id = ?
                AND e.status = 'posted' AND e.deleted_at IS NULL
                AND e.entry_date < ?" . self::scopeSql() . ""
        );
        $statement->execute([$accountId, $before]);

        return round((float) $statement->fetchColumn(), 2);
    }

    // --------------------------------------------------------------- writing

    public static function nextEntryNo(): string
    {
        $year = date('Y');
        $statement = self::db()->prepare(
            "SELECT entry_no FROM gl_journal_entries
              WHERE entry_no LIKE ?
              ORDER BY LENGTH(entry_no) DESC, entry_no DESC LIMIT 1"
        );
        $statement->execute(["JRN-{$year}-%"]);
        $last = (string) ($statement->fetchColumn() ?: '');

        $sequence = 1;
        if ($last !== '' && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('JRN-%s-%04d', $year, $sequence);
    }

    /** A period an accountant has closed will not accept a new posting. */
    public static function isPeriodClosed(string $date): bool
    {
        try {
            $statement = self::db()->prepare(
                "SELECT COUNT(*) FROM gl_fiscal_periods
                  WHERE status = 'closed' AND ? BETWEEN starts_on AND ends_on"
            );
            $statement->execute([$date]);

            return (int) $statement->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Writes one balanced entry and returns its id.
     *
     * A `source` pair makes the entry belong to the document that caused it, so
     * posting the same invoice twice replaces its entry instead of adding a
     * second one.
     *
     * @param list<array{account: int|string, debit?: float, credit?: float, description?: string}> $lines
     */
    public static function post(
        string $date,
        string $memo,
        array $lines,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $sourceCode = null,
        ?string $reference = null,
        ?string $currency = null
    ): int {
        // Each currency is its own set of books. Shillings balance against
        // shillings, francs against francs, and nothing is ever restated into
        // something the receipt did not say.
        $currency = strtoupper(trim((string) $currency)) ?: Currency::base();

        $date = date('Y-m-d', (int) strtotime($date));

        if (self::isPeriodClosed($date)) {
            throw new \RuntimeException(sprintf('The period containing %s is closed, so nothing can be posted into it.', $date));
        }

        $prepared = [];
        $debits = 0.0;
        $credits = 0.0;

        foreach ($lines as $line) {
            $accountId = is_int($line['account']) ? $line['account'] : self::idOf((string) $line['account']);
            if ($accountId === null) {
                throw new \RuntimeException(sprintf('Account "%s" is not in the chart of accounts.', (string) $line['account']));
            }

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit < 0 || $credit < 0) {
                throw new \RuntimeException('A journal line cannot carry a negative amount; reverse the sides instead.');
            }
            if ($debit > 0 && $credit > 0) {
                throw new \RuntimeException('A journal line is either a debit or a credit, never both.');
            }
            if ($debit === 0.0 && $credit === 0.0) {
                continue;
            }

            $debits += $debit;
            $credits += $credit;
            $prepared[] = [$accountId, $line['description'] ?? null, $debit, $credit];
        }

        if ($prepared === []) {
            throw new \RuntimeException('A journal entry needs at least one line with an amount.');
        }

        // The rule the whole system rests on: an entry that does not balance is
        // not an entry.
        if (abs($debits - $credits) > 0.004) {
            throw new \RuntimeException(sprintf(
                'The entry does not balance: debits %s against credits %s.',
                number_format($debits, 2),
                number_format($credits, 2)
            ));
        }

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }

        try {
            $existingId = null;
            if ($sourceType !== null && $sourceId !== null) {
                $find = $db->prepare('SELECT id FROM gl_journal_entries WHERE source_type = ? AND source_id = ? LIMIT 1');
                $find->execute([$sourceType, $sourceId]);
                $found = $find->fetchColumn();
                $existingId = $found === false ? null : (int) $found;
            }

            if ($existingId !== null) {
                $update = $db->prepare(
                    "UPDATE gl_journal_entries
                        SET entry_date = ?, currency = ?, memo = ?, reference = ?, source_code = ?,
                            status = 'posted', deleted_at = NULL, posted_by = ?
                      WHERE id = ?"
                );
                $update->execute([$date, $currency, mb_substr($memo, 0, 255), $reference, $sourceCode, \current_user_id(), $existingId]);
                $db->prepare('DELETE FROM gl_journal_lines WHERE entry_id = ?')->execute([$existingId]);
                $entryId = $existingId;
            } else {
                $insert = $db->prepare(
                    "INSERT INTO gl_journal_entries
                        (entry_no, entry_date, currency, memo, reference, source_type, source_id, source_code, status, posted_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?)"
                );
                $insert->execute([
                    self::nextEntryNo(), $date, $currency, mb_substr($memo, 0, 255), $reference,
                    $sourceType, $sourceId, $sourceCode, \current_user_id(),
                ]);
                $entryId = (int) $db->lastInsertId();
            }

            $line = $db->prepare(
                'INSERT INTO gl_journal_lines (entry_id, line_no, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($prepared as $index => [$accountId, $description, $debit, $credit]) {
                $line->execute([$entryId, $index + 1, $accountId, $description, $debit, $credit]);
            }

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return $entryId;
    }

    /**
     * Reverses an entry by writing its mirror image rather than deleting it.
     * The original stays in the journal, which is what makes the book auditable.
     */
    public static function reverse(int $entryId, string $reason): int
    {
        $entry = self::entry($entryId);
        if ($entry === null) {
            throw new \RuntimeException('That entry no longer exists.');
        }
        if ($entry['status'] === 'reversed') {
            throw new \RuntimeException('That entry has already been reversed.');
        }

        $lines = [];
        foreach (self::lines($entryId) as $line) {
            $lines[] = [
                'account' => (int) $line['account_id'],
                'debit' => (float) $line['credit'],
                'credit' => (float) $line['debit'],
                'description' => 'Reversal: ' . (string) ($line['description'] ?? ''),
            ];
        }

        $db = self::db();
        $db->beginTransaction();
        try {
            $reversalId = self::post(
                date('Y-m-d'),
                'Reversal of ' . $entry['entry_no'] . ' - ' . $reason,
                $lines,
                'reversal',
                $entryId,
                (string) $entry['entry_no']
            );

            $db->prepare("UPDATE gl_journal_entries SET status = 'reversed', reversed_by = ? WHERE id = ?")
               ->execute([$reversalId, $entryId]);

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('gl.reversed', 'journal', (string) $entry['entry_no'], $reason);

        return $reversalId;
    }

    /**
     * Whether the ledger as a whole balances. If this is ever false the books
     * are not to be relied on, so the Trial Balance says so at its foot.
     */
    public static function isBalanced(): bool
    {
        // Each currency balances on its own, so "are the books balanced" is
        // asked of one at a time; asked of all of them it would add shillings
        // to francs and always look wrong.
        $row = self::db()->query(
            "SELECT COALESCE(SUM(l.debit), 0) d, COALESCE(SUM(l.credit), 0) c
               FROM gl_journal_lines l
               INNER JOIN gl_journal_entries e ON e.id = l.entry_id
              WHERE e.status = 'posted' AND e.deleted_at IS NULL" . self::scopeSql()
        )->fetch();

        return abs((float) $row['d'] - (float) $row['c']) < 0.004;
    }
}
