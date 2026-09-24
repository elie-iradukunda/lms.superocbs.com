<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\Ledger;

/**
 * The journal: every entry the ledger holds, and the form for the ones an
 * accountant writes by hand.
 *
 * A manual entry goes through Models\Ledger::post, which refuses anything that
 * does not balance, so an unbalanced entry cannot reach the books at all.
 */
final class JournalController
{
    public function index(): void
    {
        $entries = Ledger::entries($_GET);

        \view('journal/index', [
            'title' => 'Journal',
            'entries' => $entries,
            'search' => (string) ($_GET['q'] ?? ''),
            'source' => (string) ($_GET['source'] ?? ''),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
            'balanced' => Ledger::isBalanced(),
        ]);
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $entry = Ledger::entry($id);

        if ($entry === null) {
            Flash::error('That journal entry does not exist.');
            $this->redirect(\url('journal'));
        }

        \view('journal/show', [
            'title' => (string) $entry['entry_no'],
            'entry' => $entry,
            'lines' => Ledger::lines($id),
            'reversal' => $entry['reversed_by'] !== null ? Ledger::entry((int) $entry['reversed_by']) : null,
        ]);
    }

    public function create(): void
    {
        $this->renderForm([
            'entry_date' => date('Y-m-d'),
            'memo' => '',
            'reference' => '',
        ], [], []);
    }

    public function store(): void
    {
        $date = trim((string) ($_POST['entry_date'] ?? ''));
        $memo = trim((string) ($_POST['memo'] ?? ''));
        $reference = trim((string) ($_POST['reference'] ?? ''));
        $submitted = is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [];

        $errors = [];
        if ($date === '' || strtotime($date) === false) {
            $errors[] = 'The entry needs a valid date.';
        }
        if ($memo === '') {
            $errors[] = 'The entry needs a memo saying what it is for.';
        }

        $lines = [];
        $debits = 0.0;
        $credits = 0.0;

        foreach ($submitted as $row) {
            $accountId = (int) ($row['account_id'] ?? 0);
            $debit = (float) str_replace(',', '', (string) ($row['debit'] ?? ''));
            $credit = (float) str_replace(',', '', (string) ($row['credit'] ?? ''));

            if ($accountId === 0 && $debit === 0.0 && $credit === 0.0) {
                continue;   // a blank row the user left alone
            }

            if ($accountId === 0) {
                $errors[] = 'Every line with an amount needs an account.';
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                $errors[] = 'A line is either a debit or a credit, never both.';
                continue;
            }
            if ($debit <= 0 && $credit <= 0) {
                $errors[] = 'Every line needs an amount on one side.';
                continue;
            }
            if ($debit < 0 || $credit < 0) {
                $errors[] = 'Amounts cannot be negative; put the figure on the other side instead.';
                continue;
            }

            $debits += $debit;
            $credits += $credit;
            $lines[] = [
                'account' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
            ];
        }

        if (count($lines) < 2) {
            $errors[] = 'An entry needs at least two lines: something given and something received.';
        }
        if ($lines !== [] && abs($debits - $credits) > 0.004) {
            $errors[] = sprintf(
                'The entry is out of balance by %s. Debits total %s and credits total %s.',
                number_format(abs($debits - $credits), 2),
                number_format($debits, 2),
                number_format($credits, 2)
            );
        }
        if ($date !== '' && strtotime($date) !== false && Ledger::isPeriodClosed(date('Y-m-d', (int) strtotime($date)))) {
            $errors[] = 'That date falls in a closed period, so nothing can be posted into it.';
        }

        if ($errors !== []) {
            $this->renderForm(['entry_date' => $date, 'memo' => $memo, 'reference' => $reference], $submitted, $errors);
            return;
        }

        try {
            $id = Ledger::post($date, $memo, $lines, 'manual', null, null, $reference ?: null);
        } catch (\Throwable $exception) {
            $this->renderForm(['entry_date' => $date, 'memo' => $memo, 'reference' => $reference], $submitted, [$exception->getMessage()]);
            return;
        }

        $entry = Ledger::entry($id);
        AuditLog::record('gl.posted', 'journal', (string) ($entry['entry_no'] ?? $id), $memo, ['lines' => count($lines), 'total' => $debits]);

        Flash::success(sprintf('Entry %s posted for %s.', (string) ($entry['entry_no'] ?? ''), \money($debits, true)));
        $this->redirect(\url(['journal', $id]));
    }

    /**
     * Reverses an entry rather than deleting it: the original stays in the
     * journal, which is what makes the book auditable.
     */
    public function reverse(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            Flash::error('A reversal needs a reason; it stays on both entries.');
            $this->redirect(\url(['journal', $id]));
        }

        try {
            $reversalId = Ledger::reverse($id, $reason);
            Flash::success('The entry was reversed. Its mirror image has been posted.');
            $this->redirect(\url(['journal', $reversalId]));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['journal', $id]));
        }
    }

    private function renderForm(array $entry, array $lines, array $errors): void
    {
        // Always leave a few empty rows so a longer entry does not need a
        // round trip before it can be typed.
        while (count($lines) < 6) {
            $lines[] = ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''];
        }

        \view('journal/form', [
            'title' => 'New journal entry',
            'entry' => $entry,
            'lines' => $lines,
            'errors' => $errors,
            'accountOptions' => Ledger::accountOptions(),
        ]);
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
