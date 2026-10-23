<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\Company;
use Models\CustomerBilling;
use Models\Payroll;

/**
 * Customer invoicing from delivered trips (F13, section 13) and the drivers'
 * payroll list with deductions (section 6).
 */
final class BillingController
{
    public function index(): void
    {
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $from = (string) ($_GET['from'] ?? date('Y-m-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));
        $condition = Company::condition('customers', 'company_id');

        \view('billing/index', [
            'title' => 'Invoice delivered trips',
            'customers' => Database::connection()->query("SELECT id, customer_name FROM customers WHERE deleted_at IS NULL AND status <> 'inactive'" . ($condition !== null ? ' AND ' . $condition : '') . ' ORDER BY customer_name')->fetchAll(\PDO::FETCH_KEY_PAIR),
            'customerId' => $customerId,
            'from' => $from,
            'to' => $to,
            'trips' => $customerId > 0 ? CustomerBilling::billable($customerId, $from, $to) : [],
        ]);
    }

    public function build(): void
    {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $from = (string) ($_POST['from'] ?? '');
        $to = (string) ($_POST['to'] ?? '');
        try {
            $ids = CustomerBilling::build($customerId, $from, $to, array_map('intval', (array) ($_POST['trip_ids'] ?? [])), (string) ($_POST['lpo'] ?? ''));
            Flash::success(count($ids) === 1
                ? 'The invoice is ready as a draft. Check it and send it to the MD.'
                : sprintf('%d invoices are ready as drafts, one per currency. Check them and send them to the MD.', count($ids)));
            $this->redirect(\url(['form_requests', $ids[0]]));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url('billing', ['customer_id' => $customerId, 'from' => $from, 'to' => $to]));
        }
    }

    // ------------------------------------------------------------ payroll

    public function payroll(): void
    {
        $period = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['period'] ?? '')) === 1 ? (string) $_GET['period'] : date('Y-m');
        $companyId = \current_company_id() ?: Company::defaultId();

        \view('billing/payroll', [
            'title' => 'Payroll list',
            'period' => $period,
            'rows' => Payroll::sheet($companyId, $period),
            'run' => Payroll::run($companyId, $period),
        ]);
    }

    public function payrollExport(): void
    {
        $period = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['period'] ?? '')) === 1 ? (string) $_GET['period'] : date('Y-m');
        $companyId = \current_company_id() ?: Company::defaultId();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="payroll-' . $period . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Driver', 'ID / passport', 'Phone', 'Paid by', 'MoMo number', 'MoMo name', 'Salary', 'Currency', 'Deductions', 'Net', 'Deduction details']);
        foreach (Payroll::sheet($companyId, $period) as $row) {
            fputcsv($out, [
                $row['full_name'], $row['national_id'] ?: $row['passport_number'], $row['phone'], $row['payment_method'], $row['momo_number'], $row['momo_name'],
                number_format((float) $row['salary'], 2, '.', ''), $row['currency'],
                number_format((float) ($row['deducted'][$row['currency']] ?? 0), 2, '.', ''), number_format((float) $row['net'], 2, '.', ''),
                implode('; ', array_map(static fn (array $d): string => $d['description'] . ' ' . $d['currency'] . ' ' . number_format((float) $d['amount'], 2, '.', ''), $row['deductions'])),
            ]);
        }
        fclose($out);
        exit;
    }

    public function payrollClose(): void
    {
        $period = (string) ($_POST['period'] ?? '');
        try {
            Payroll::close(\current_company_id() ?: Company::defaultId(), $period);
            Flash::success('The month is closed and the deductions are marked as taken.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url('payroll', ['period' => $period]));
    }

    public function intercompany(): void
    {
        \view('billing/intercompany', [
            'title' => 'Inter-company invoices',
            'invoices' => \Models\SisterRequests::invoices(),
            'companyId' => \current_company_id(),
        ]);
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
