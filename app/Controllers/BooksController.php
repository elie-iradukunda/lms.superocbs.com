<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\Books;
use Models\Ledger;
use Models\Posting;
use Support\Report;

/**
 * The accounting books: the gallery, one book on screen, and the same book as a
 * PDF, an Excel workbook or a CSV.
 */
final class BooksController
{
    private const FORMATS = ['pdf', 'xlsx', 'csv'];

    public function index(): void
    {
        \view('books/index', [
            'title' => 'Accounting books',
            'catalogue' => Books::CATALOGUE,
            'balanced' => Ledger::isBalanced(),
            'accounts' => count(Ledger::accounts()),
            'canSync' => \can_edit('journal'),
        ]);
    }

    public function show(array $params): void
    {
        $key = (string) ($params['key'] ?? '');
        if (!Books::exists($key)) {
            Flash::error('That book does not exist.');
            $this->redirect(\url('books'));
        }

        try {
            $book = Books::build($key, $_GET);
        } catch (\Throwable $exception) {
            Flash::error('That book could not be prepared: ' . $exception->getMessage());
            $this->redirect(\url('books'));
        }

        \view('books/show', [
            'title' => $book['label'],
            'book' => $book,
            'catalogue' => Books::CATALOGUE,
            'accountOptions' => Ledger::accountOptions(),
            'balanced' => Ledger::isBalanced(),
        ]);
    }

    public function export(array $params): void
    {
        $key = (string) ($params['key'] ?? '');
        $format = strtolower((string) ($params['format'] ?? 'pdf'));

        if (!Books::exists($key)) {
            Flash::error('That book does not exist.');
            $this->redirect(\url('books'));
        }

        if (!in_array($format, self::FORMATS, true)) {
            Flash::error('That download format is not supported.');
            $this->redirect(\url(['books', 'view', $key]));
        }

        try {
            $book = Books::build($key, $_GET);
        } catch (\Throwable $exception) {
            Flash::error('That book could not be prepared: ' . $exception->getMessage());
            $this->redirect(\url('books'));
        }

        AuditLog::record('books.exported', 'books', $key, null, [
            'format' => $format,
            'from' => $book['from'],
            'to' => $book['to'],
        ]);

        // Nothing may be echoed before this: one stray byte corrupts the file.
        Report::download($book['doc'], $format);
    }

    /**
     * Posts every operational document that belongs in the ledger and is not
     * there yet, and removes entries whose documents no longer qualify.
     */
    public function sync(): void
    {
        try {
            $counts = Posting::syncAll();
            $total = array_sum($counts);
            $detail = [];
            foreach ($counts as $kind => $count) {
                if ($count > 0) {
                    $detail[] = $count . ' ' . $kind . ($count === 1 ? '' : 's');
                }
            }

            Flash::success($total === 0
                ? 'The ledger was already up to date; nothing needed posting.'
                : sprintf('Posted %d document(s) to the ledger: %s.', $total, implode(', ', $detail)));

            if (!Ledger::isBalanced()) {
                Flash::error('The ledger does not balance after that run. Open the trial balance before relying on the books.');
            }
        } catch (\Throwable $exception) {
            Flash::error('The ledger could not be brought up to date: ' . $exception->getMessage());
        }

        $this->redirect(\url('books'));
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
