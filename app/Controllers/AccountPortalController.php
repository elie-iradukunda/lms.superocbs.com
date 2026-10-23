<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\RequestForms;
use Models\RequestPdf;
use Models\SupplierPortal;
use Models\Uploads;
use PDO;

/**
 * The signed-in portal of a customer (section 13) or a supplier (section 18).
 * Each sees only their own records: a customer their trips, delivery notes and
 * invoices; a supplier their orders, deliveries, invoices and payments.
 */
final class AccountPortalController
{
    public function index(): void
    {
        [$kind, $id, $name] = $this->who();
        if ($kind === 'customer') {
            $db = Database::connection();
            $trips = $db->prepare(
                "SELECT t.id, t.reference_code, t.status, t.cargo_summary, t.cargo_type, t.loaded_quantity, t.delivered_quantity, t.departure_at, t.container_numbers,
                        v.plate_number, co.short_name AS company
                   FROM trips t LEFT JOIN vehicles v ON v.id = t.vehicle_id LEFT JOIN companies co ON co.id = t.company_id
                  WHERE t.customer_id = ? AND t.deleted_at IS NULL AND t.status <> 'cancelled' ORDER BY t.id DESC LIMIT 50"
            );
            $trips->execute([$id]);
            $notes = $db->prepare("SELECT r.id, r.request_no, r.payload, r.created_at, t.reference_code FROM form_requests r LEFT JOIN trips t ON t.id = r.trip_id WHERE r.form_code = 'F8' AND r.customer_id = ? AND r.status = 'closed' AND r.deleted_at IS NULL ORDER BY r.id DESC LIMIT 50");
            $notes->execute([$id]);
            $invoices = $db->prepare("SELECT i.*, co.company_name FROM invoices i LEFT JOIN companies co ON co.id = i.company_id WHERE i.customer_id = ? AND i.deleted_at IS NULL AND i.status NOT IN ('draft','cancelled') ORDER BY i.issue_date DESC, i.id DESC LIMIT 100");
            $invoices->execute([$id]);
            $invoiceRows = $invoices->fetchAll(PDO::FETCH_ASSOC);
            $balance = [];
            foreach ($invoiceRows as $invoice) {
                $balance[$invoice['currency']] = ($balance[$invoice['currency']] ?? 0) + (float) $invoice['total_amount'] - (float) $invoice['amount_paid'];
            }

            \view('portal_account/customer', [
                'title' => 'My account',
                'name' => $name,
                'trips' => $trips->fetchAll(PDO::FETCH_ASSOC),
                'notes' => array_map(static fn (array $n): array => $n + ['data' => json_decode((string) $n['payload'], true) ?: []], $notes->fetchAll(PDO::FETCH_ASSOC)),
                'invoices' => $invoiceRows,
                'balance' => array_filter($balance, static fn (float $v): bool => abs($v) > 0.004),
            ]);

            return;
        }

        \view('portal_account/supplier', [
            'title' => 'My orders',
            'name' => $name,
            'orders' => SupplierPortal::orders($id),
            'invoices' => SupplierPortal::invoices($id),
            'payments' => SupplierPortal::payments($id),
        ]);
    }

    /** A document of this customer or supplier, as PDF. */
    public function pdf(array $params): void
    {
        $request = $this->owned((int) ($params['id'] ?? 0));
        $bytes = RequestPdf::render((int) $request['id']);
        if ($bytes === null) {
            Flash::error('The PDF could not be made.');
            $this->redirect(\url('portal'));
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9\-]+/', '-', RequestForms::label($request)) . '.pdf"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    /** The photo of the signed delivery note. */
    public function photo(array $params): void
    {
        $request = $this->owned((int) ($params['id'] ?? 0));
        $payload = is_array($request['payload']) ? $request['payload'] : (json_decode((string) $request['payload'], true) ?: []);
        $path = (string) ($payload['photo'] ?? '');
        $file = $path !== '' && !str_contains($path, '..') ? Uploads::root() . '/' . ltrim($path, '/') : '';
        if ($file === '' || !is_file($file)) {
            http_response_code(404);
            exit('Not found');
        }
        header('Content-Type: ' . (mime_content_type($file) ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /** A supplier accepts or declines an order (section 18, step 3). */
    public function answer(array $params): void
    {
        [$kind, $id] = $this->who();
        if ($kind !== 'supplier') {
            $this->redirect(\url('portal'));
        }
        try {
            SupplierPortal::answer((int) ($params['id'] ?? 0), $id, (string) ($params['answer'] ?? ''), (string) ($_POST['reason'] ?? ''));
            Flash::success(($params['answer'] ?? '') === 'accept' ? 'Order accepted.' : 'Order declined. The OM has been told why.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url('portal'));
    }

    /** A supplier sends their invoice (F12) against an order. */
    public function invoice(array $params): void
    {
        [$kind, $id] = $this->who();
        if ($kind !== 'supplier') {
            $this->redirect(\url('portal'));
        }
        try {
            $file = Uploads::store($_FILES['scan'] ?? null, 'requests');
            SupplierPortal::submitInvoice((int) ($params['id'] ?? 0), $id, (string) ($_POST['invoice_no'] ?? ''), (string) ($_POST['invoice_date'] ?? ''), (array) ($_POST['qty'] ?? []), (array) ($_POST['price'] ?? []), $file);
            Flash::success('Invoice sent. Finance checks it against the order and what was received.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url('portal'));
    }

    /** @return array{0: string, 1: int, 2: string} 'customer'|'supplier', id, name */
    private function who(): array
    {
        $statement = Database::connection()->prepare(
            'SELECT u.customer_id, u.supplier_id, c.customer_name, s.supplier_name FROM users u
               LEFT JOIN customers c ON c.id = u.customer_id LEFT JOIN suppliers s ON s.id = u.supplier_id WHERE u.id = ?'
        );
        $statement->execute([\current_user_id()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!empty($row['customer_id'])) {
            return ['customer', (int) $row['customer_id'], (string) $row['customer_name']];
        }
        if (!empty($row['supplier_id'])) {
            return ['supplier', (int) $row['supplier_id'], (string) $row['supplier_name']];
        }
        Flash::error('This login is not linked to a customer or a supplier. Ask the administrator.');
        $this->redirect(\url(['dashboard'], ['denied' => 'portal']));
    }

    /** A request this customer or supplier may see. */
    private function owned(int $requestId): array
    {
        [$kind, $id] = $this->who();
        $request = RequestForms::find($requestId, false);
        $mine = $request !== null && (
            ($kind === 'customer' && (int) $request['customer_id'] === $id && in_array($request['form_code'], ['F8', 'F13'], true) && in_array($request['status'], ['closed', 'authorized', 'paid'], true))
            || ($kind === 'supplier' && (int) $request['supplier_id'] === $id && in_array($request['form_code'], ['F2', 'F5', 'F12'], true) && !in_array($request['status'], ['draft', 'cancelled'], true))
        );
        if (!$mine) {
            http_response_code(404);
            exit('Not found');
        }

        return $request;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
