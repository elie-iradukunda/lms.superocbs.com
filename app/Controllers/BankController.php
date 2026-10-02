<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\AuditLog;
use Models\BankReconciliation;
use Support\XlsxReader;

/** The bank reconciliation screen for one statement, and the buttons on it. */
final class BankController
{
    public function reconcile(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $statement = BankReconciliation::statement($id);
        if ($statement === null) {
            Flash::error('That statement does not exist.');
            $this->redirect(\url('bank_statements'));
        }

        $bankLines = BankReconciliation::bankLines($id);
        $bookEntries = BankReconciliation::bookEntries($statement);

        $accounts = Database::connection()->query(
            "SELECT id, CONCAT(account_code, ' ', account_name) AS label FROM gl_accounts
              WHERE deleted_at IS NULL AND is_header = 0 AND is_active = 1 ORDER BY account_code"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        \view('bank/reconcile', [
            'title' => 'Reconcile ' . $statement['statement_code'],
            'statement' => $statement,
            'bankLines' => $bankLines,
            'bookEntries' => $bookEntries,
            'summary' => BankReconciliation::summary($statement, $bankLines, $bookEntries),
            'accounts' => $accounts,
            'importErrors' => $_SESSION['bank_import_errors'] ?? [],
        ]);
        unset($_SESSION['bank_import_errors']);
    }

    public function act(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $op = (string) ($params['op'] ?? '');

        try {
            match ($op) {
                'auto' => $this->auto($id),
                'match' => $this->match($id),
                'unmatch' => $this->unmatch($id),
                'post' => $this->post($id),
                'import' => $this->import($id),
                'complete' => $this->complete($id),
                'reopen' => $this->reopen($id),
                default => Flash::error('Unknown action.'),
            };
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }

        $this->redirect(\url(['bank_statements', $id, 'reconcile']));
    }

    private function auto(int $id): void
    {
        $matched = BankReconciliation::autoMatch($id);
        AuditLog::record('bank.auto_matched', 'bank_statements', (string) $id, null, ['matched' => $matched]);
        $matched > 0
            ? Flash::success(sprintf('%d line%s matched by amount and date.', $matched, $matched === 1 ? '' : 's'))
            : Flash::error('Nothing more could be matched automatically. Match the rest by hand, or post them to the books.');
    }

    private function match(int $id): void
    {
        BankReconciliation::match($id, (int) ($_POST['line_id'] ?? 0), (int) ($_POST['entry_id'] ?? 0));
        AuditLog::record('bank.matched', 'bank_statements', (string) $id, null, ['line' => (int) ($_POST['line_id'] ?? 0), 'entry' => (int) ($_POST['entry_id'] ?? 0)]);
        Flash::success('Matched.');
    }

    private function unmatch(int $id): void
    {
        BankReconciliation::unmatch($id, (int) ($_POST['line_id'] ?? 0));
        AuditLog::record('bank.unmatched', 'bank_statements', (string) $id, null, ['line' => (int) ($_POST['line_id'] ?? 0)]);
        Flash::success('Match removed.');
    }

    private function post(int $id): void
    {
        $entryId = BankReconciliation::postLine($id, (int) ($_POST['line_id'] ?? 0), (int) ($_POST['account_id'] ?? 0));
        AuditLog::record('bank.line_posted', 'bank_statements', (string) $id, null, ['entry' => $entryId]);
        Flash::success('Posted to the books and matched.');
    }

    private function import(int $id): void
    {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::error('Choose the statement file exported from the bank.');
            return;
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            Flash::error('The file must be Excel (.xlsx) or CSV (.csv).');
            return;
        }

        $result = BankReconciliation::import($id, XlsxReader::rows((string) $file['tmp_name'], (string) $file['name']));
        if ($result['errors'] !== []) {
            $_SESSION['bank_import_errors'] = $result['errors'];
            Flash::error('Nothing was imported. Fix the rows listed and upload again.');
            return;
        }

        AuditLog::record('bank.imported', 'bank_statements', (string) $id, null, ['file' => (string) $file['name'], 'lines' => $result['added']]);
        Flash::success(sprintf('%d statement line%s imported.', $result['added'], $result['added'] === 1 ? '' : 's'));
    }

    private function complete(int $id): void
    {
        BankReconciliation::complete($id, \current_user_id());
        AuditLog::record('bank.reconciled', 'bank_statements', (string) $id);
        Flash::success('Statement reconciled. The bank and the books agree.');
    }

    private function reopen(int $id): void
    {
        BankReconciliation::reopen($id);
        AuditLog::record('bank.reopened', 'bank_statements', (string) $id);
        Flash::success('Statement reopened.');
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
