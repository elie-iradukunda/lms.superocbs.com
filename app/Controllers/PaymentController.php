<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\Company;
use Models\Currency;
use Models\Payments;
use Models\Uploads;

/**
 * The Cashier's pages (section 10): To pay, building a MoMo or cash batch,
 * the file for MTN's bulk upload, recording the MTN batch number and proof,
 * and paying cash at the counter with the payee's PIN or signature.
 */
final class PaymentController
{
    public function index(): void
    {
        $currency = (string) ($_GET['currency'] ?? '');
        $method = (string) ($_GET['method'] ?? '');
        $company = Company::condition('payment_batches', 'b.company_id');
        $batches = Database::connection()->query(
            'SELECT b.*, u.full_name AS built_name,
                    (SELECT COUNT(*) FROM payment_batch_lines bl WHERE bl.batch_id = b.id AND bl.status = \'failed\') AS failed_lines,
                    (SELECT COUNT(*) FROM payment_batch_lines bl WHERE bl.batch_id = b.id AND bl.status = \'paid\' AND bl.confirmed_at IS NULL AND bl.payee_type = \'driver\') AS unconfirmed
               FROM payment_batches b LEFT JOIN users u ON u.id = b.built_by'
            . ($company !== null ? ' WHERE ' . $company : '') . ' ORDER BY b.id DESC LIMIT 60'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $cash = [];
        foreach (array_keys(Currency::options()) as $code) {
            $balance = Payments::cashBox(\current_company_id(), $code);
            if (abs($balance) > 0.004) {
                $cash[$code] = $balance;
            }
        }

        \view('payments/index', [
            'title' => 'Payments',
            'toPay' => Payments::toPay($currency ?: null, $method ?: null),
            'batches' => $batches,
            'cash' => $cash,
            'methods' => $this->moneyAccounts(),
        ]);
    }

    public function build(): void
    {
        try {
            $id = Payments::build((array) ($_POST['keys'] ?? []), ($_POST['payment_method_id'] ?? '') !== '' ? (int) $_POST['payment_method_id'] : null, (string) ($_POST['notes'] ?? ''));
            $batch = Payments::batch($id);
            Flash::success(sprintf('Batch %s built. %s', $batch['batch_no'], $batch['method'] === 'momo' ? 'Download the MTN file and upload it in MTN; then record the MTN batch number and the proof here.' : 'Pay each line at the counter.'));
            $this->redirect(\url(['payment_batches', $id]));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url('payment_batches'));
        }
    }

    public function show(array $params): void
    {
        $batch = $this->batch($params);
        \view('payments/batch', [
            'title' => $batch['batch_no'],
            'batch' => $batch,
            'lines' => Payments::batchLines((int) $batch['id']),
        ]);
    }

    public function mtnFile(array $params): void
    {
        $batch = $this->batch($params);
        $csv = Payments::mtnFile((int) $batch['id']);
        \Models\AuditLog::record('payment.mtn_file', 'payment_batches', (string) $batch['batch_no']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $batch['batch_no'] . '-MTN.csv"');
        echo $csv;
        exit;
    }

    public function record(array $params): void
    {
        $batch = $this->batch($params);
        try {
            $proof = Uploads::store($_FILES['proof'] ?? null, 'payment_batches');
            $failed = [];
            foreach ((array) ($_POST['failed'] ?? []) as $lineId => $flag) {
                if ($flag === '1') {
                    $failed[(int) $lineId] = (string) ($_POST['failure_reason'][$lineId] ?? '');
                }
            }
            Payments::record((int) $batch['id'], (string) ($_POST['mtn_batch_no'] ?? ''), $proof, $failed);
            Flash::success($failed === [] ? 'Payment recorded. Each payee was told and asked to confirm the money arrived.' : sprintf('Recorded. %d failed line%s stay%s on To pay with the reason.', count($failed), count($failed) === 1 ? '' : 's', count($failed) === 1 ? 's' : ''));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['payment_batches', $batch['id']]));
    }

    public function payCash(array $params): void
    {
        $batch = $this->batch($params);
        try {
            $receipt = Payments::payCash((int) ($params['line'] ?? 0), (string) ($_POST['pin'] ?? ''), (string) ($_POST['signature'] ?? ''));
            Flash::success('Paid in cash. Receipt ' . $receipt . '.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['payment_batches', $batch['id']]));
    }

    /** The cash receipt, printed for the person paid. */
    public function receipt(array $params): void
    {
        $batch = $this->batch($params);
        $line = null;
        foreach (Payments::batchLines((int) $batch['id']) as $candidate) {
            if ((int) $candidate['id'] === (int) ($params['line'] ?? 0)) {
                $line = $candidate;
            }
        }
        if ($line === null || $line['status'] !== 'paid') {
            Flash::error('That line has not been paid.');
            $this->redirect(\url(['payment_batches', $batch['id']]));
        }
        $company = Company::header((int) $batch['company_id']);
        $pdf = new \Support\FormPdf('Receipt ' . $line['receipt_no']);
        $pdf->text(40, 40, strtoupper($company['name']), 13, true);
        $pdf->text(40, 58, trim($company['address'] . '   ' . $company['phone']), 8, false, [0.42, 0.46, 0.52]);
        $pdf->line(40, 74, 555, 74, 1.2, [0.12, 0.31, 0.55]);
        $pdf->text(40, 88, 'CASH PAYMENT RECEIPT ' . ($line['receipt_no'] ?: ''), 12, true);
        $top = 116;
        foreach ([
            'Paid to' => $line['payee_name'],
            'Amount' => Currency::format((float) $line['amount'], (string) $line['currency']),
            'In words' => \Support\Words::amount((float) $line['amount'], (string) $line['currency']) . ' only',
            'For' => $line['form_code'] . ' ' . $line['request_no'] . ' — ' . $line['title'],
            'Batch' => $batch['batch_no'],
            'Paid on' => human_date((string) $line['paid_at'], true),
            'Paid by' => (string) ($batch['recorded_name'] ?? \current_user_name()),
            'Confirmed by' => $line['confirmed_via'] === 'pin' ? 'The payee\'s PIN' : 'The payee\'s signature',
        ] as $label => $value) {
            $pdf->text(40, $top, $label, 9, true);
            $top = max($top + 16, $pdf->paragraph(140, $top, 400, (string) $value, 9) + 4);
        }
        $signature = \Models\Signature::uploadedImage($line['confirmation_signature'] ?? null);
        if ($signature !== null) {
            $pdf->text(40, $top + 10, 'Signature of the payee', 8, true);
            $pdf->image($signature, 40, $top + 24, 200, 60);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . ($line['receipt_no'] ?: 'receipt') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    /** @return array<int, string> the MoMo, cash and bank accounts money can leave from */
    private function moneyAccounts(): array
    {
        $options = [];
        foreach (\Models\PaymentMethod::all() as $method) {
            if ((int) ($method['is_active'] ?? 1) !== 1 || ($method['direction'] ?? 'both') === 'in') {
                continue;
            }
            if (!empty($method['company_id']) && (int) $method['company_id'] !== \current_company_id()) {
                continue;
            }
            $options[(int) $method['id']] = \Models\PaymentMethod::label($method);
        }

        return $options;
    }

    private function batch(array $params): array
    {
        $batch = Payments::batch((int) ($params['id'] ?? 0));
        if ($batch === null) {
            Flash::error('That batch does not exist, or belongs to another company.');
            $this->redirect(\url('payment_batches'));
        }

        return $batch;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
