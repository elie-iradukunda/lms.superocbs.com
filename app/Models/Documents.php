<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The PDFs attached to business emails: invoice, receipt, quotation, purchase
 * order and delivery note. Each returns what Mailer needs to attach it, or null
 * when the record cannot be found, in which case the email goes without it.
 */
final class Documents
{
    private const FOLDER = 'documents';

    public static function invoice(int $invoiceId): ?array
    {
        $invoice = self::row(
            'SELECT i.*, c.customer_name, c.contact_name, c.address, c.district, c.tin_number, c.email, c.phone,
                    u.full_name AS signer, u.job_title AS signer_title, t.reference_code AS trip_code
               FROM invoices i
               LEFT JOIN customers c ON c.id = i.customer_id
               LEFT JOIN users u ON u.id = i.issued_by
               LEFT JOIN trips t ON t.id = i.trip_id
              WHERE i.id = ?',
            $invoiceId
        );
        if ($invoice === null) {
            return null;
        }
        $currency = (string) ($invoice['currency'] ?: Currency::base());
        $money = static fn (float $amount): string => Currency::format($amount, $currency);

        $rows = [];
        foreach (self::all('SELECT description, quantity, unit_price, line_total FROM invoice_lines WHERE invoice_id = ? ORDER BY id', [$invoiceId]) as $n => $line) {
            $rows[] = [(string) ($n + 1), (string) $line['description'], self::qty((float) $line['quantity']), $money((float) $line['unit_price']), $money((float) $line['line_total'])];
        }

        $paid = (float) $invoice['amount_paid'];
        $totals = [
            'Subtotal' => $money((float) $invoice['subtotal']),
            sprintf('VAT at %s%%', self::qty((float) $invoice['tax_rate'])) => $money((float) $invoice['tax_amount']),
        ];
        if ($paid > 0) {
            $totals['Total'] = $money((float) $invoice['total_amount']);
            $totals['Paid'] = $money($paid);
        }
        $totals[$paid > 0 ? 'Balance due' : 'Total due'] = $money(max(0.0, (float) $invoice['total_amount'] - $paid));

        $pay = [];
        foreach (PaymentMethod::invoiceInstructions($currency) as $method) {
            $pay[] = $method['details'] !== '' ? $method['name'] . ': ' . $method['details'] : $method['name'];
        }

        return BusinessPdf::store(self::FOLDER . '/invoices', [
            'file' => 'Invoice-' . $invoice['invoice_number'] . '.pdf',
            'title' => 'Invoice',
            'number' => (string) $invoice['invoice_number'],
            'meta' => [
                'Invoice date' => \human_date($invoice['issue_date']),
                'Due date' => \human_date($invoice['due_date']),
                'Your order (LPO)' => (string) ($invoice['lpo_number'] ?? ''),
                'Trip' => (string) ($invoice['trip_code'] ?? ''),
                'Currency' => $currency,
            ],
            'to_label' => 'Bill to',
            'to' => self::party($invoice),
            'columns' => [['#', 4, 'left'], ['Description', 52, 'left'], ['Qty', 7, 'right'], ['Unit price', 17, 'right'], ['Amount', 18, 'right']],
            'rows' => $rows,
            'totals' => $totals,
            'blocks' => [
                ['How to pay', $pay !== [] ? $pay : ['Ask us for our bank details.']],
                ['Please note', ['Quote the invoice number ' . $invoice['invoice_number'] . ' as the payment reference.', trim((string) ($invoice['notes'] ?? ''))]],
            ],
            'sign' => ['name' => (string) ($invoice['signer'] ?? ''), 'role' => (string) ($invoice['signer_title'] ?? ''), 'signature' => Signature::path(isset($invoice['issued_by']) ? (int) $invoice['issued_by'] : null)],
            'seal' => true,
        ]);
    }

    public static function receipt(int $paymentId): ?array
    {
        $payment = self::row(
            'SELECT p.*, i.invoice_number, i.total_amount, i.amount_paid, i.currency AS invoice_currency, i.customer_id,
                    c.customer_name, c.contact_name, c.address, c.district, c.tin_number, c.email, c.phone,
                    u.full_name AS signer, u.job_title AS signer_title
               FROM payments p
               INNER JOIN invoices i ON i.id = p.invoice_id
               LEFT JOIN customers c ON c.id = i.customer_id
               LEFT JOIN users u ON u.id = p.recorded_by
              WHERE p.id = ?',
            $paymentId
        );
        if ($payment === null) {
            return null;
        }
        $currency = (string) ($payment['invoice_currency'] ?: $payment['currency'] ?: Currency::base());
        $money = static fn (float $amount): string => Currency::format($amount, $currency);
        $before = max(0.0, (float) $payment['amount_paid'] - (float) $payment['amount']);
        $outstanding = max(0.0, (float) $payment['total_amount'] - (float) $payment['amount_paid']);

        return BusinessPdf::store(self::FOLDER . '/receipts', [
            'file' => 'Receipt-' . $payment['payment_code'] . '.pdf',
            'title' => 'Receipt',
            'number' => (string) $payment['payment_code'],
            'meta' => [
                'Date received' => \human_date($payment['paid_at']),
                'Invoice' => (string) $payment['invoice_number'],
                'Paid by' => PaymentMethod::name((string) $payment['method']),
                'Your reference' => (string) ($payment['reference'] ?? ''),
            ],
            'to_label' => 'Received from',
            'to' => self::party($payment),
            'columns' => [['Detail', 70, 'left'], ['Amount', 30, 'right']],
            'rows' => [
                ['Invoice ' . $payment['invoice_number'] . ' total', $money((float) $payment['total_amount'])],
                ['Paid before this receipt', $money($before)],
                ['This payment', $money((float) $payment['amount'])],
            ],
            'totals' => ['Amount received' => $money((float) $payment['amount']), $outstanding > 0 ? 'Still outstanding' : 'Balance' => $money($outstanding)],
            'blocks' => [['Thank you', [$outstanding > 0
                ? sprintf('%s remains outstanding on invoice %s.', $money($outstanding), $payment['invoice_number'])
                : sprintf('Invoice %s is now settled in full.', $payment['invoice_number'])]]],
            'sign' => ['name' => (string) ($payment['signer'] ?? ''), 'role' => (string) ($payment['signer_title'] ?? ''), 'signature' => Signature::path(isset($payment['recorded_by']) ? (int) $payment['recorded_by'] : null)],
            'seal' => true,
        ]);
    }

    public static function quotation(int $requestId): ?array
    {
        $request = self::row(
            'SELECT r.*, c.customer_name, c.contact_name, c.address, c.district, c.tin_number, c.email, c.phone, c.payment_terms_days,
                    wo.warehouse_name AS from_depot, wd.warehouse_name AS to_depot,
                    u.full_name AS signer, u.job_title AS signer_title, u.id AS signer_id
               FROM transport_requests r
               LEFT JOIN customers c ON c.id = r.customer_id
               LEFT JOIN warehouses wo ON wo.id = r.origin_warehouse_id
               LEFT JOIN warehouses wd ON wd.id = r.destination_warehouse_id
               LEFT JOIN users u ON u.id = COALESCE(r.approved_by, r.requester_id)
              WHERE r.id = ?',
            $requestId
        );
        if ($request === null || $request['quoted_amount'] === null) {
            return null;
        }
        $currency = (string) ($request['currency'] ?: Currency::base());
        $from = (string) ($request['from_depot'] ?: $request['pickup_location']);
        $to = (string) ($request['to_depot'] ?: $request['destination']);
        $vat = self::qty(Settings::float('tax_rate', 18));

        return BusinessPdf::store(self::FOLDER . '/quotations', [
            'file' => 'Quotation-' . $request['reference_code'] . '.pdf',
            'title' => 'Quotation',
            'number' => (string) $request['reference_code'],
            'meta' => [
                'Date' => \human_date($request['quoted_at'] ?: date('Y-m-d')),
                'Valid until' => \human_date(date('Y-m-d', strtotime('+30 days', (int) strtotime((string) ($request['quoted_at'] ?: 'now'))))),
                'Required by' => \human_date($request['required_date']),
                'Currency' => $currency,
            ],
            'to_label' => 'Prepared for',
            'to' => self::party($request),
            'columns' => [['Service', 62, 'left'], ['Weight', 16, 'right'], ['Price', 22, 'right']],
            'rows' => [[
                sprintf('Transport from %s to %s: %s', $from, $to, (string) ($request['cargo_description'] ?: 'goods as described in your enquiry')),
                number_format((float) $request['weight_kg']) . ' kg',
                Currency::format((float) $request['quoted_amount'], $currency),
            ]],
            'totals' => ['Total price, excluding VAT' => Currency::format((float) $request['quoted_amount'], $currency)],
            'blocks' => [
                ['Terms', [
                    sprintf('Prices exclude VAT (%s%%), which is added on the invoice.', $vat),
                    'Included: carriage, the truck and its driver, fuel, road tolls and tracking. Not included: customs duty and taxes, port and storage charges; when we pay these for you they are recharged at cost with the receipts.',
                    sprintf('Payment %d days from the date of invoice.', (int) ($request['payment_terms_days'] ?: Settings::int('payment_terms_days', 30))),
                    'To accept, reply to the email this came with, quoting ' . $request['reference_code'] . '.',
                ]],
            ],
            'sign' => ['name' => (string) ($request['signer'] ?? ''), 'role' => (string) ($request['signer_title'] ?? ''), 'signature' => Signature::path(isset($request['signer_id']) ? (int) $request['signer_id'] : null)],
            'seal' => false,
        ]);
    }

    public static function purchaseOrder(int $requestId): ?array
    {
        $order = self::row(
            'SELECT pr.*, s.supplier_name AS customer_name, s.contact_name, s.address, NULL AS district, s.tin_number, s.email, s.phone,
                    s.payment_terms_days, w.warehouse_name, w.location AS warehouse_location,
                    u.full_name AS signer, u.job_title AS signer_title
               FROM purchase_requests pr
               LEFT JOIN suppliers s ON s.id = pr.supplier_id
               LEFT JOIN warehouses w ON w.id = pr.warehouse_id
               LEFT JOIN users u ON u.id = pr.approved_by
              WHERE pr.id = ?',
            $requestId
        );
        if ($order === null) {
            return null;
        }
        $currency = (string) ($order['currency'] ?: Currency::base());
        $money = static fn (float $amount): string => Currency::format($amount, $currency);
        $rows = [];
        $total = 0.0;
        foreach (self::all('SELECT item_name, quantity, unit_of_measure, unit_price, line_total FROM purchase_request_lines WHERE purchase_request_id = ? ORDER BY id', [$requestId]) as $n => $line) {
            $rows[] = [(string) ($n + 1), (string) $line['item_name'], self::units((float) $line['quantity'], (string) $line['unit_of_measure']), $money((float) $line['unit_price']), $money((float) $line['line_total'])];
            $total += (float) $line['line_total'];
        }
        if ($rows === []) {
            $rows[] = ['1', (string) $order['description'], '', '', $money((float) $order['amount'])];
            $total = (float) $order['amount'];
        }
        $number = self::orderNumber((string) $order['request_code']);

        return BusinessPdf::store(self::FOLDER . '/purchase-orders', [
            'file' => 'Purchase-order-' . $number . '.pdf',
            'title' => 'Purchase order',
            'number' => $number,
            'meta' => [
                'Order date' => \human_date($order['approved_at'] ?: date('Y-m-d')),
                'Deliver by' => \human_date($order['expected_date']),
                'Our request' => (string) $order['request_code'],
                'Currency' => $currency,
            ],
            'to_label' => 'Supplier',
            'to' => self::party($order),
            'right_label' => 'Deliver to',
            'right' => array_filter([(string) ($order['warehouse_name'] ?? ''), (string) ($order['warehouse_location'] ?? '')]),
            'columns' => [['#', 4, 'left'], ['Item', 50, 'left'], ['Quantity', 14, 'right'], ['Unit price', 15, 'right'], ['Amount', 17, 'right']],
            'rows' => $rows,
            'totals' => ['Order value' => $money($total)],
            'blocks' => [
                ['Invoice us at', [\company_name() . '  ·  ' . \Support\Report::companyDetails(), 'Quote ' . $number . ' on your delivery note and invoice.']],
                ['Terms', [sprintf('Payment %d days from delivery.', (int) ($order['payment_terms_days'] ?: 30)), 'Deliver during working hours with a delivery note quoting this order.']],
            ],
            'sign' => ['name' => (string) ($order['signer'] ?? ''), 'role' => (string) ($order['signer_title'] ?? ''), 'signature' => Signature::path(isset($order['approved_by']) ? (int) $order['approved_by'] : null)],
            'seal' => true,
        ]);
    }

    public static function deliveryNote(int $deliveryId): ?array
    {
        $delivery = self::row(
            'SELECT d.*, s.shipment_code, s.cargo_description, s.packages_count, s.weight_kg, s.container_no, s.seal_no,
                    s.customer_reference, c.customer_name, c.contact_name, c.address, c.district, c.tin_number, c.email, c.phone,
                    t.reference_code AS trip_code, v.plate_number, dr.full_name AS driver_name,
                    w.warehouse_name, u.full_name AS signer, u.job_title AS signer_title
               FROM deliveries d
               LEFT JOIN shipments s ON s.id = d.shipment_id
               LEFT JOIN customers c ON c.id = s.customer_id
               LEFT JOIN trips t ON t.id = d.trip_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN drivers dr ON dr.id = t.driver_id
               LEFT JOIN warehouses w ON w.id = d.destination_warehouse_id
               LEFT JOIN users u ON u.id = d.delivered_by
              WHERE d.id = ?',
            $deliveryId
        );
        if ($delivery === null) {
            return null;
        }

        return BusinessPdf::store(self::FOLDER . '/delivery-notes', [
            'file' => 'Delivery-note-' . $delivery['delivery_code'] . '.pdf',
            'title' => 'Delivery note',
            'number' => (string) $delivery['delivery_code'],
            'meta' => [
                'Delivered' => \human_date($delivery['delivered_at'], true),
                'Consignment' => (string) ($delivery['shipment_code'] ?? ''),
                'Trip' => (string) ($delivery['trip_code'] ?? ''),
                'Your order' => (string) ($delivery['customer_reference'] ?? ''),
            ],
            'to_label' => 'Delivered to',
            'to' => self::party($delivery),
            'right_label' => 'Carried by',
            'right' => array_filter([
                trim(($delivery['plate_number'] ?? '') . ($delivery['driver_name'] ? ', ' . $delivery['driver_name'] : ''), ', '),
                $delivery['container_no'] ? 'Container ' . $delivery['container_no'] . ($delivery['seal_no'] ? ', seal ' . $delivery['seal_no'] : '') : '',
            ]),
            'columns' => [['Goods', 60, 'left'], ['Packages', 18, 'right'], ['Weight', 22, 'right']],
            'rows' => [[(string) ($delivery['cargo_description'] ?? ''), number_format((float) $delivery['packages_count']), number_format((float) $delivery['weight_kg']) . ' kg']],
            'blocks' => [['Received by', array_filter([
                (string) ($delivery['collected_by'] ?: $delivery['recipient_name']),
                $delivery['collected_id_no'] ? 'ID ' . self::maskId((string) $delivery['collected_id_no']) : '',
                $delivery['warehouse_name'] ? 'Collected from ' . $delivery['warehouse_name'] : 'At ' . $delivery['destination'],
                'Goods received complete and in good order unless noted on the signed copy held on file.',
            ])]],
            'sign' => ['name' => (string) ($delivery['signer'] ?? ''), 'role' => (string) ($delivery['signer_title'] ?? ''), 'signature' => Signature::path(isset($delivery['delivered_by']) ? (int) $delivery['delivered_by'] : null)],
            'seal' => true,
        ]);
    }

    /** A purchase request becomes an order once approved: PR-2026-0002 is order PO-2026-0002. */
    public static function orderNumber(string $requestCode): string
    {
        return preg_replace('/^PR-/', 'PO-', $requestCode) ?? $requestCode;
    }

    /** "10 units", "1 drum", "16 boxes": a quantity with its unit, as people write it. */
    public static function units(float $quantity, string $unit): string
    {
        $amount = self::qty($quantity);
        if (trim($unit) === '' || in_array(strtolower(trim($unit)), ['kg', 'l', 'm', 'm3', 'm²', 'm³', 'g', 'km'], true)) {
            return trim($amount . ' ' . trim($unit));
        }
        $unit = strtolower(trim($unit));
        if (abs($quantity - 1.0) > 0.0001) {
            $unit = preg_match('/(s|x|ch|sh)$/', $unit) ? $unit . 'es' : $unit . 's';
        }

        return $amount . ' ' . $unit;
    }

    private static function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }

    private static function maskId(string $id): string
    {
        return strlen($id) > 6 ? '••••' . substr($id, -4) : $id;
    }

    /** @return list<string> name, address, contact, TIN */
    private static function party(array $row): array
    {
        return array_values(array_filter([
            (string) ($row['customer_name'] ?? ''),
            trim(implode(', ', array_filter([(string) ($row['address'] ?? ''), (string) ($row['district'] ?? '')]))),
            trim(implode('  ·  ', array_filter([(string) ($row['contact_name'] ?? ''), (string) ($row['phone'] ?? ''), (string) ($row['email'] ?? '')]))),
            ($row['tin_number'] ?? '') !== '' && $row['tin_number'] !== null ? 'TIN ' . $row['tin_number'] : '',
        ]));
    }

    private static function row(string $sql, int $id): ?array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function all(string $sql, array $parameters): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
