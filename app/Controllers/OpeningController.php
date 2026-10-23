<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\OpeningBalances;

/** Opening balances from Tally (section 14): load, check against Tally, sign off. */
final class OpeningController
{
    public function index(): void
    {
        \view('opening/index', [
            'title' => 'Opening balances',
            'openings' => OpeningBalances::forCompany(\current_company_id()),
        ]);
    }

    public function template(array $params): void
    {
        $kind = (string) ($params['kind'] ?? 'ledger');
        $columns = OpeningBalances::COLUMNS[$kind] ?? OpeningBalances::COLUMNS['ledger'];
        $examples = [
            'ledger' => [['1020', 'RWF', '15000000', '0', 'Bank - main account'], ['2000', 'RWF', '0', '4200000', 'Suppliers']],
            'customers' => [['Jambo oil factory', 'RWF', '3500000', 'INV 0441', date('Y-m-d', strtotime('+15 days'))]],
            'suppliers' => [['Kigali Fuel Station', 'RWF', '1800000', 'FS-2210', date('Y-m-d', strtotime('+10 days'))]],
        ][$kind] ?? [];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="opening-' . $kind . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $columns);
        foreach ($examples as $example) {
            fputcsv($out, $example);
        }
        fclose($out);
        exit;
    }

    public function store(): void
    {
        $kind = (string) ($_POST['kind'] ?? '');
        $csv = '';
        if (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $csv = (string) file_get_contents((string) $_FILES['file']['tmp_name']);
        }
        if (trim($csv) === '') {
            $csv = (string) ($_POST['pasted'] ?? '');
        }
        try {
            $parsed = OpeningBalances::parse($kind, $csv);
            if ($parsed['problems'] !== []) {
                throw new \RuntimeException(implode(' ', $parsed['problems']));
            }
            $id = OpeningBalances::save(\current_company_id(), $kind, (string) ($_POST['as_of'] ?? ''), $parsed['rows'], (string) ($_POST['notes'] ?? ''));
            Flash::success('Loaded. Compare the totals with Tally, then mark them checked.');
            $this->redirect(\url('opening_balances') . '#ob' . $id);
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url('opening_balances'));
        }
    }

    public function act(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            if (($params['action'] ?? '') === 'check') {
                OpeningBalances::check($id);
                Flash::success('Marked as checked against Tally. The MD can sign them off.');
            } elseif (($params['action'] ?? '') === 'sign') {
                if (!\role_can('opening_balances', 'approve')) {
                    throw new \RuntimeException('The MD signs the opening balances off.');
                }
                OpeningBalances::signOff($id);
                Flash::success('Signed off and posted. From now on everything is entered in the LMS only.');
            }
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url('opening_balances'));
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
