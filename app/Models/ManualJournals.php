<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * A manual journal is a request like any other (section 14): an accountant
 * prepares it, the Finance Manager verifies it, the MD authorizes it, and only
 * then does it reach the books. Who signs each step comes from the approver
 * settings (verify and authorize), so delegations and backups apply here too.
 *
 * Nobody signs their own: the verifier is not the preparer, and the authorizer
 * is neither.
 */
final class ManualJournals
{
    public const STATUS = [
        'sent' => ['To verify (Finance)', 'warning'],
        'verified' => ['To authorize (MD)', 'info'],
        'posted' => ['Posted', 'success'],
        'refused' => ['Refused', 'danger'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Checks a journal before it is saved; the same rules the books apply,
     * caught early so the preparer can fix them.
     *
     * @param list<array{account:int, debit:float, credit:float, description:?string}> $lines
     * @return list<string>
     */
    public static function problems(string $date, string $memo, string $currency, array $lines): array
    {
        $problems = [];
        if ($date === '' || strtotime($date) === false) {
            $problems[] = 'The entry needs a valid date.';
        } elseif (Ledger::isPeriodClosed(date('Y-m-d', (int) strtotime($date)))) {
            $problems[] = 'That date falls in a closed period, so nothing can be posted into it.';
        }
        if (trim($memo) === '') {
            $problems[] = 'The entry needs a memo saying what it is for.';
        }
        if (!array_key_exists($currency, Currency::options())) {
            $problems[] = 'Choose one of the active currencies.';
        }
        if (count($lines) < 2) {
            $problems[] = 'An entry needs at least two lines: something given and something received.';
        }
        $debits = array_sum(array_column($lines, 'debit'));
        $credits = array_sum(array_column($lines, 'credit'));
        if ($lines !== [] && abs($debits - $credits) > 0.004) {
            $problems[] = sprintf(
                'The entry is out of balance by %s. Debits total %s and credits total %s.',
                number_format(abs($debits - $credits), 2),
                number_format($debits, 2),
                number_format($credits, 2)
            );
        }

        return $problems;
    }

    /**
     * Saves the journal and sends it on. When the preparer is the verifier,
     * preparing counts as verifying and it goes straight to the MD.
     *
     * @param list<array{account:int, debit:float, credit:float, description:?string}> $lines
     */
    public static function prepare(string $date, string $memo, ?string $reference, string $currency, array $lines): int
    {
        $problems = self::problems($date, $memo, $currency, $lines);
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $companyId = \current_company_id() ?: Company::defaultId();
        $userId = (int) \current_user_id();
        $date = date('Y-m-d', (int) strtotime($date));
        $number = Numbering::next($companyId, 'journal', Settings::get('journal_number_format', 'JV-{YEAR}-{SEQ4}'), 'year', $date);
        $selfVerified = Approver::actingFor($companyId, 'verify', $userId, \current_role()) !== false;

        self::db()->prepare(
            'INSERT INTO journal_drafts (company_id, draft_no, entry_date, memo, reference, currency, lines_json, total, status, prepared_by, verified_by, verified_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $companyId, $number, $date, trim($memo), $reference ?: null, $currency,
            json_encode(array_values($lines)), round(array_sum(array_column($lines, 'debit')), 2),
            $selfVerified ? 'verified' : 'sent', $userId,
            $selfVerified ? $userId : null, $selfVerified ? date('Y-m-d H:i:s') : null,
        ]);
        $id = (int) self::db()->lastInsertId();

        AuditLog::record('journal.prepared', 'journal_drafts', $number, trim($memo), ['currency' => $currency, 'total' => array_sum(array_column($lines, 'debit'))]);
        self::tellNext($id);

        return $id;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $condition = Company::condition('journal_drafts', 'j.company_id');
        $statement = self::db()->prepare(
            'SELECT j.*, p.full_name AS prepared_name, v.full_name AS verified_name, a.full_name AS authorized_name, r.full_name AS refused_name
               FROM journal_drafts j
               LEFT JOIN users p ON p.id = j.prepared_by
               LEFT JOIN users v ON v.id = j.verified_by
               LEFT JOIN users a ON a.id = j.authorized_by
               LEFT JOIN users r ON r.id = j.refused_by
              WHERE j.id = ?' . ($condition !== null ? ' AND ' . $condition : '')
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['lines'] = json_decode((string) $row['lines_json'], true) ?: [];

        return $row;
    }

    /**
     * Journals not yet posted, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function waiting(): array
    {
        $condition = Company::condition('journal_drafts', 'j.company_id');

        return self::db()->query(
            "SELECT j.*, p.full_name AS prepared_name FROM journal_drafts j LEFT JOIN users p ON p.id = j.prepared_by
              WHERE j.status IN ('sent','verified')" . ($condition !== null ? ' AND ' . $condition : '') . '
              ORDER BY j.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The step this person can sign on the journal now, if any. */
    public static function stepFor(array $journal): ?string
    {
        $userId = (int) \current_user_id();
        $companyId = (int) $journal['company_id'];
        if ($journal['status'] === 'sent' && $userId !== (int) $journal['prepared_by']
            && Approver::actingFor($companyId, 'verify', $userId, \current_role(), (string) $journal['prepared_at']) !== false) {
            return 'verify';
        }
        if ($journal['status'] === 'verified' && !in_array($userId, [(int) $journal['prepared_by'], (int) $journal['verified_by']], true)
            && Approver::actingFor($companyId, 'authorize', $userId, \current_role(), (string) ($journal['verified_at'] ?? $journal['prepared_at'])) !== false) {
            return 'authorize';
        }

        return null;
    }

    public static function verify(int $id): void
    {
        $journal = self::mustBe($id, 'verify');
        self::db()->prepare("UPDATE journal_drafts SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ? AND status = 'sent'")
            ->execute([\current_user_id(), $id]);
        AuditLog::record('journal.verified', 'journal_drafts', (string) $journal['draft_no'], (string) $journal['memo']);
        self::tellNext($id);
    }

    /** The MD authorizes and the entry posts, in one transaction. */
    public static function authorize(int $id): int
    {
        $journal = self::mustBe($id, 'authorize');
        $lines = array_map(static fn (array $line): array => [
            'account' => (int) $line['account'],
            'debit' => (float) $line['debit'],
            'credit' => (float) $line['credit'],
            'description' => $line['description'] ?? null,
        ], $journal['lines']);

        $pdo = self::db();
        $outer = $pdo->inTransaction();
        if (!$outer) {
            $pdo->beginTransaction();
        }
        try {
            $entryId = Ledger::post(
                (string) $journal['entry_date'],
                (string) $journal['memo'],
                $lines,
                'manual',
                $id,
                (string) $journal['draft_no'],
                $journal['reference'] ?: null,
                (string) $journal['currency'],
                (int) $journal['company_id']
            );
            $pdo->prepare("UPDATE journal_drafts SET status = 'posted', authorized_by = ?, authorized_at = NOW(), entry_id = ? WHERE id = ?")
                ->execute([\current_user_id(), $entryId, $id]);
            if (!$outer) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if (!$outer && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('gl.posted', 'journal', (string) $journal['draft_no'], (string) $journal['memo'], ['entry_id' => $entryId, 'total' => (float) $journal['total']]);
        Notifier::forCompany((int) $journal['company_id'], static fn () => Notifier::toUser(
            (int) $journal['prepared_by'],
            'Journal posted',
            sprintf('%s "%s" was authorized by %s and posted.', $journal['draft_no'], $journal['memo'], \current_user_name()),
            'journal',
            'success',
            'journal',
            (string) $entryId
        ));

        return $entryId;
    }

    /** A verifier or the MD refuses, with a reason the preparer reads. */
    public static function refuse(int $id, string $reason): void
    {
        $journal = self::find($id);
        if ($journal === null || self::stepFor($journal) === null) {
            throw new \RuntimeException('This journal is not waiting for your signature.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Say why it is refused; the preparer reads it.');
        }
        self::db()->prepare("UPDATE journal_drafts SET status = 'refused', refused_by = ?, refused_at = NOW(), refused_reason = ? WHERE id = ?")
            ->execute([\current_user_id(), mb_substr(trim($reason), 0, 255), $id]);
        AuditLog::record('journal.refused', 'journal_drafts', (string) $journal['draft_no'], trim($reason));
        Notifier::forCompany((int) $journal['company_id'], static fn () => Notifier::toUser(
            (int) $journal['prepared_by'],
            'Journal refused',
            sprintf('%s "%s" was refused by %s: %s', $journal['draft_no'], $journal['memo'], \current_user_name(), trim($reason)),
            'journal',
            'danger',
            'journal_drafts',
            (string) $id
        ));
    }

    /** The preparer withdraws a journal nobody has posted yet. */
    public static function cancel(int $id): void
    {
        $journal = self::find($id);
        if ($journal === null || !in_array($journal['status'], ['sent', 'verified'], true) || (int) $journal['prepared_by'] !== (int) \current_user_id()) {
            throw new \RuntimeException('Only the preparer can withdraw a journal that is not posted yet.');
        }
        self::db()->prepare("UPDATE journal_drafts SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        AuditLog::record('journal.cancelled', 'journal_drafts', (string) $journal['draft_no'], (string) $journal['memo']);
    }

    /** How many journals wait for this person's signature. */
    public static function inboxCount(): int
    {
        try {
            return count(array_filter(self::waiting(), static fn (array $journal): bool => self::stepFor($journal) !== null));
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string, mixed> */
    private static function mustBe(int $id, string $step): array
    {
        $journal = self::find($id);
        if ($journal === null || self::stepFor($journal) !== $step) {
            throw new \RuntimeException('This journal is not waiting for your signature.');
        }

        return $journal;
    }

    private static function tellNext(int $id): void
    {
        $journal = self::find($id);
        if ($journal === null) {
            return;
        }
        $step = $journal['status'] === 'sent' ? 'verify' : 'authorize';
        $setting = Approver::setting((int) $journal['company_id'], $step);
        if ($setting === null) {
            return;
        }
        $title = $step === 'verify' ? 'Journal to verify' : 'Journal to authorize';
        $message = sprintf('%s "%s" for %s, prepared by %s.', $journal['draft_no'], $journal['memo'], Currency::format((float) $journal['total'], (string) $journal['currency']), $journal['prepared_name'] ?? '');

        Notifier::forCompany((int) $journal['company_id'], static function () use ($setting, $title, $message, $id): void {
            $main = (int) ($setting['main_user_id'] ?? 0);
            if ($main > 0) {
                Notifier::toUser($main, $title, $message, 'journal', 'warning', 'journal_drafts', (string) $id);
            } else {
                Notifier::toRole((string) $setting['role_key'], $title, $message, 'journal', 'warning', 'journal_drafts', (string) $id);
            }
        });
    }
}
