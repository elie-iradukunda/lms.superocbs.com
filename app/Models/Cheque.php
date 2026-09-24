<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Paying by cheque.
 *
 * A cheque is not simply a payment with a different label on it. It is a
 * numbered leaf out of a particular book, drawn on one bank account, that the
 * bank may not take for days, and that can be cancelled without ever being
 * cashed. Each of those is a state the books have to tell apart:
 *
 *   draft      being prepared. Nothing is posted and no money has moved.
 *   issued     posted. The bank is down and the costs are up, which is right:
 *              the company is committed the moment it hands the leaf over.
 *   presented  the bank has actually taken it. Nothing is posted again — this
 *              records that the ledger and the statement now agree, and it is
 *              the difference between the two that a reconciliation explains.
 *   void       cancelled. The entry is reversed and the amount reads zero, but
 *              the row stays, because the number must never be written again.
 *
 * `Workflow` drives the moves; this class says what must be true first and what
 * happens after.
 */
final class Cheque
{
    /**
     * What must be true before a cheque may move.
     *
     * Workflow asks before it changes anything, so a refusal leaves the cheque
     * exactly as it was.
     */
    public static function guard(string $action, array $cheque): ?string
    {
        if ($action === 'issue') {
            $lines = self::lines((int) $cheque['id']);
            if ($lines === []) {
                return 'Say what the money is for before issuing this cheque: add at least one line under "What the money is for".';
            }

            $total = self::sum($lines);
            if ($total <= 0) {
                return 'A cheque cannot be issued for nothing.';
            }
            if (abs($total - (float) $cheque['amount']) > 0.005) {
                return sprintf(
                    'The lines add up to %s but the cheque reads %s. Save the lines again so the two agree.',
                    Settings::money($total),
                    Settings::money((float) $cheque['amount'])
                );
            }
            if (trim((string) $cheque['cheque_no']) === '' && self::nextNumber((int) $cheque['bank_account_id']) === null) {
                return 'Enter the number printed on the leaf, or record the cheque book it came from so the next number can be used.';
            }
            if (self::accountCode((int) $cheque['bank_account_id']) === null) {
                return 'The bank account this cheque is drawn on is no longer in the chart of accounts.';
            }

            foreach ($lines as $line) {
                if (self::accountCode((int) $line['account_id']) === null) {
                    return 'One of the lines points at an account that no longer exists.';
                }
            }
        }

        if ($action === 'void' && (string) $cheque['status'] === 'presented') {
            return 'The bank has already taken this cheque. Record the money coming back instead of voiding it.';
        }

        return null;
    }

    /**
     * What happens once the status has moved.
     *
     * Workflow has already written the new status inside a transaction, so
     * anything thrown here takes that change with it.
     */
    public static function applied(string $action, array $cheque, string $reason, ?int $actor): string
    {
        return match ($action) {
            'issue' => self::postIssue($cheque, $actor),
            'present' => self::recordPresented($cheque),
            'void' => self::reverse($cheque, $reason, $actor),
            default => sprintf('Cheque %s updated.', $cheque['reference']),
        };
    }

    /**
     * Dr what it was spent on, Cr the bank it was drawn on.
     *
     * The posting follows the shape of the paper: one credit to the bank for the
     * whole amount, because that is the single line a bank statement shows, and
     * one debit for each thing the money was for.
     */
    private static function postIssue(array $cheque, ?int $actor): string
    {
        $db = Database::connection();
        $id = (int) $cheque['id'];
        $lines = self::lines($id);
        $total = self::sum($lines);

        // A leaf with no number written on it takes the next one from the book.
        $number = trim((string) $cheque['cheque_no']);
        $bookId = $cheque['cheque_book_id'] !== null ? (int) $cheque['cheque_book_id'] : null;
        if ($number === '') {
            $next = self::nextNumber((int) $cheque['bank_account_id']);
            $number = $next['cheque_no'];
            $bookId = $next['book_id'];
        }

        $postings = [[
            'account' => self::accountCode((int) $cheque['bank_account_id']),
            'credit' => $total,
            'description' => sprintf('Cheque %s to %s', $number, $cheque['payee_name']),
        ]];

        foreach ($lines as $line) {
            $postings[] = [
                'account' => self::accountCode((int) $line['account_id']),
                'debit' => round((float) $line['amount'], 2),
                'description' => (string) ($line['description'] ?: $cheque['payee_name']),
            ];
        }

        $entryId = Ledger::post(
            (string) $cheque['cheque_date'],
            sprintf('Cheque %s to %s', $number, $cheque['payee_name']),
            $postings,
            'cheque',
            $id,
            (string) $cheque['reference'],
            $number
        );

        $db->prepare('UPDATE gl_cheques SET cheque_no = ?, cheque_book_id = ?, entry_id = ?, issued_by = ?, issued_at = NOW() WHERE id = ?')
           ->execute([$number, $bookId, $entryId, $actor, $id]);

        self::advanceBook($bookId);

        Notifier::toRole(
            'finance',
            'Cheque issued',
            sprintf('%s, number %s, for %s to %s.', $cheque['reference'], $number, Settings::money($total), $cheque['payee_name']),
            'cheques',
            'info',
            'cheques',
            (string) $cheque['reference']
        );

        return sprintf('Cheque %s issued for %s. The bank is down by that amount until the cheque is presented.', $number, Settings::money($total));
    }

    /**
     * The bank has taken it.
     *
     * Nothing is posted: the money left the books when the cheque was issued.
     * This only records that the ledger and the statement now agree.
     */
    private static function recordPresented(array $cheque): string
    {
        $date = date('Y-m-d');
        Database::connection()
            ->prepare('UPDATE gl_cheques SET presented_on = COALESCE(presented_on, ?) WHERE id = ?')
            ->execute([$date, (int) $cheque['id']]);

        return sprintf('%s is presented. It comes off the outstanding list from %s.', $cheque['reference'], $date);
    }

    /**
     * Cancel a cheque without cashing it.
     *
     * The ledger entry goes and the amount reads zero, but the row and its
     * number stay: a spoiled leaf is spent, and writing the number again would
     * mean two cheques the bank cannot tell apart.
     */
    private static function reverse(array $cheque, string $reason, ?int $actor): string
    {
        Posting::unpost('cheque', (int) $cheque['id']);

        Database::connection()
            ->prepare('UPDATE gl_cheques SET amount = 0, entry_id = NULL, void_by = ?, void_at = NOW(), void_reason = ? WHERE id = ?')
            ->execute([$actor, mb_substr(trim($reason), 0, 255), (int) $cheque['id']]);

        return sprintf(
            '%s is void and its ledger entry is gone. Number %s is spent and will not be offered again.',
            $cheque['reference'],
            $cheque['cheque_no'] !== null && $cheque['cheque_no'] !== '' ? $cheque['cheque_no'] : '(none written)'
        );
    }

    /**
     * The next unused leaf on a bank account, from its open cheque book.
     *
     * @return array{book_id: int, cheque_no: string}|null
     */
    public static function nextNumber(int $bankAccountId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, prefix, next_no FROM gl_cheque_books
              WHERE bank_account_id = ? AND status = 'active' AND deleted_at IS NULL AND next_no <= last_no
              ORDER BY next_no LIMIT 1"
        );
        $statement->execute([$bankAccountId]);
        $book = $statement->fetch(PDO::FETCH_ASSOC);

        if ($book === false) {
            return null;
        }

        return [
            'book_id' => (int) $book['id'],
            'cheque_no' => (string) ($book['prefix'] ?? '') . (string) $book['next_no'],
        ];
    }

    /** Move the book past the leaf just used, and close it when the last one goes. */
    private static function advanceBook(?int $bookId): void
    {
        if ($bookId === null) {
            return;
        }

        $db = Database::connection();
        $db->prepare('UPDATE gl_cheque_books SET next_no = next_no + 1 WHERE id = ? AND next_no <= last_no')->execute([$bookId]);
        $db->prepare("UPDATE gl_cheque_books SET status = 'finished' WHERE id = ? AND next_no > last_no")->execute([$bookId]);
    }

    /**
     * Cheques written but not yet taken by the bank.
     *
     * This is exactly the difference between what the ledger says the bank holds
     * and what the statement says, and it is the first thing anyone reconciling
     * an account needs in front of them.
     *
     * @return array{rows: list<array>, total: float}
     */
    public static function outstanding(?int $bankAccountId = null, string $asOf = ''): array
    {
        $asOf = trim($asOf) !== '' ? date('Y-m-d', (int) strtotime($asOf)) : date('Y-m-d');

        $sql = "SELECT c.reference, c.cheque_no, c.cheque_date, c.payee_name, c.amount,
                       a.account_code, a.account_name AS bank
                  FROM gl_cheques c
                  INNER JOIN gl_accounts a ON a.id = c.bank_account_id
                 WHERE c.deleted_at IS NULL AND c.status = 'issued' AND c.cheque_date <= ?";
        $parameters = [$asOf];

        if ($bankAccountId !== null) {
            $sql .= ' AND c.bank_account_id = ?';
            $parameters[] = $bankAccountId;
        }

        $sql .= ' ORDER BY a.account_code, c.cheque_date, c.cheque_no';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => self::sum($rows)];
    }

    public static function load(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM gl_cheques WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function lines(int $chequeId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM gl_cheque_lines WHERE cheque_id = ? ORDER BY id');
        $statement->execute([$chequeId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Keep the written amount equal to what the lines add up to.
     *
     * A void cheque is worth nothing whatever its lines once said, so it is left
     * alone.
     */
    public static function recalculate(int $chequeId): float
    {
        $db = Database::connection();
        $total = self::sum(self::lines($chequeId));

        $db->prepare("UPDATE gl_cheques SET amount = ? WHERE id = ? AND status <> 'void'")->execute([$total, $chequeId]);

        return $total;
    }

    private static function sum(array $rows): float
    {
        return round(array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $rows)), 2);
    }

    private static function accountCode(int $accountId): ?string
    {
        $statement = Database::connection()->prepare('SELECT account_code FROM gl_accounts WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$accountId]);
        $code = $statement->fetchColumn();

        return $code === false ? null : (string) $code;
    }
}
