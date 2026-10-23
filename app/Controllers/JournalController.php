<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\Company;
use Models\Currency;
use Models\Ledger;
use Models\ManualJournals;

/**
 * The journal: every entry the ledger holds, and the form for the ones an
 * accountant writes by hand.
 *
 * A manual entry is prepared here and waits for Finance to verify and the MD
 * to authorize (Models\ManualJournals); only then does Models\Ledger::post
 * write it, which refuses anything that does not balance.
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
            'currency' => strtoupper((string) ($_GET['currency'] ?? '')),
            'currencies' => Ledger::currenciesUsed(),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
            'balanced' => Ledger::isBalanced(),
            'waiting' => ManualJournals::waiting(),
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
            'currency' => Company::baseCurrency(\current_company_id() ?: Company::defaultId()),
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

        $currency = strtoupper(trim((string) ($_POST['currency'] ?? ''))) ?: Company::baseCurrency(\current_company_id() ?: Company::defaultId());
        $errors = array_values(array_unique(array_merge($errors, ManualJournals::problems($date, $memo, $currency, $lines))));

        $entry = ['entry_date' => $date, 'memo' => $memo, 'reference' => $reference, 'currency' => $currency];
        if ($errors !== []) {
            $this->renderForm($entry, $submitted, $errors);
            return;
        }

        try {
            $id = ManualJournals::prepare($date, $memo, $reference ?: null, $currency, $lines);
        } catch (\Throwable $exception) {
            $this->renderForm($entry, $submitted, [$exception->getMessage()]);
            return;
        }

        $journal = ManualJournals::find($id);
        Flash::success(sprintf(
            'Journal %s sent %s. It posts once the MD authorizes it.',
            (string) ($journal['draft_no'] ?? ''),
            ($journal['status'] ?? '') === 'verified' ? 'to the MD' : 'to Finance to verify'
        ));
        $this->redirect(\url(['journal', 'drafts', $id]));
    }

    /** A journal on its way to the books: who prepared, verified and authorized. */
    public function draft(array $params): void
    {
        $journal = ManualJournals::find((int) ($params['id'] ?? 0));
        if ($journal === null) {
            Flash::error('That journal does not exist.');
            $this->redirect(\url('journal'));
        }

        \view('journal/draft', [
            'title' => (string) $journal['draft_no'],
            'journal' => $journal,
            'step' => ManualJournals::stepFor($journal),
            'accountOptions' => Ledger::accountOptions(),
        ]);
    }

    public function act(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');

        try {
            match ($action) {
                'verify' => ManualJournals::verify($id),
                'authorize' => ManualJournals::authorize($id),
                'refuse' => ManualJournals::refuse($id, (string) ($_POST['reason'] ?? '')),
                'cancel' => ManualJournals::cancel($id),
                default => throw new \RuntimeException('Unknown action.'),
            };
            Flash::success([
                'verify' => 'Verified. The MD has been asked to authorize it.',
                'authorize' => 'Authorized and posted to the books.',
                'refuse' => 'Refused. The preparer has been told why.',
                'cancel' => 'Withdrawn.',
            ][$action]);
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }

        $journal = ManualJournals::find($id);
        if ($action === 'authorize' && !empty($journal['entry_id'])) {
            $this->redirect(\url(['journal', (int) $journal['entry_id']]));
        }
        $this->redirect(\url(['journal', 'drafts', $id]));
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
            'currencyOptions' => Currency::options(),
        ]);
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
