<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Suppliers and purchase orders (section 18): the supplier sees its own
 * orders, accepts or declines them, and sends its invoice (F12) against the
 * order. The system matches order, goods received and invoice line by line;
 * a difference is highlighted for Finance before verifying.
 */
final class SupplierPortal
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** @return list<array<string, mixed>> orders (F5) and fuel orders (F2) sent to the supplier */
    public static function orders(int $supplierId): array
    {
        $statement = self::db()->prepare(
            "SELECT r.*, co.company_name, v.plate_number,
                    (SELECT COUNT(*) FROM form_requests i WHERE i.parent_request_id = r.id AND i.form_code = 'F12' AND i.deleted_at IS NULL AND i.status <> 'cancelled') AS invoiced
               FROM form_requests r LEFT JOIN companies co ON co.id = r.company_id LEFT JOIN vehicles v ON v.id = r.vehicle_id
              WHERE r.supplier_id = ? AND r.form_code IN ('F5','F2') AND r.deleted_at IS NULL AND r.status IN ('authorized','closed','paid','cancelled')
              ORDER BY r.id DESC LIMIT 100"
        );
        $statement->execute([$supplierId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['payload'] = json_decode((string) ($row['payload'] ?? ''), true) ?: [];
            $row['lines'] = RequestForms::lines((int) $row['id']);
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public static function invoices(int $supplierId): array
    {
        $statement = self::db()->prepare(
            "SELECT r.*, p.request_no AS order_no FROM form_requests r LEFT JOIN form_requests p ON p.id = r.parent_request_id
              WHERE r.supplier_id = ? AND r.form_code = 'F12' AND r.deleted_at IS NULL AND r.status <> 'draft' ORDER BY r.id DESC LIMIT 100"
        );
        $statement->execute([$supplierId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public static function payments(int $supplierId): array
    {
        $statement = self::db()->prepare(
            "SELECT l.*, b.batch_no, b.mtn_batch_no, b.method, r.request_no, r.form_code FROM payment_batch_lines l
               INNER JOIN payment_batches b ON b.id = l.batch_id INNER JOIN form_requests r ON r.id = l.request_id
              WHERE l.payee_type = 'supplier' AND l.payee_id = ? AND l.status = 'paid' ORDER BY l.paid_at DESC LIMIT 100"
        );
        $statement->execute([$supplierId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Accept, or decline with a reason (section 18, step 3). */
    public static function answer(int $orderId, int $supplierId, string $answer, string $reason): void
    {
        $order = RequestForms::find($orderId, false);
        if ($order === null || (int) $order['supplier_id'] !== $supplierId || !in_array($order['form_code'], ['F5', 'F2'], true)) {
            throw new \RuntimeException('That order is not yours.');
        }
        if ($order['status'] !== 'authorized') {
            throw new \RuntimeException('This order can no longer be answered.');
        }
        $payload = is_array($order['payload']) ? $order['payload'] : (json_decode((string) $order['payload'], true) ?: []);
        if (!empty($payload['supplier_answer'])) {
            throw new \RuntimeException('This order was already answered.');
        }
        if (!in_array($answer, ['accept', 'decline'], true)) {
            throw new \RuntimeException('Accept or decline.');
        }
        if ($answer === 'decline' && trim($reason) === '') {
            throw new \RuntimeException('Say why you decline: the OM reads it.');
        }
        $payload['supplier_answer'] = ['answer' => $answer, 'reason' => trim($reason) ?: null, 'at' => date('Y-m-d H:i:s'), 'by' => \current_user_id()];
        $db = self::db();
        $db->prepare('UPDATE form_requests SET payload = ? WHERE id = ?')->execute([json_encode($payload), $orderId]);
        if ($answer === 'decline') {
            // A declined order commits nothing: it is cancelled, to be placed elsewhere.
            $db->prepare("UPDATE form_requests SET status = 'cancelled', returned_reason = ? WHERE id = ?")->execute(['Declined by the supplier: ' . trim($reason), $orderId]);
        }
        RequestForms::event($orderId, (int) $order['version'], $answer === 'accept' ? 'accepted' : 'declined', trim($reason) ?: null, true);
        AuditLog::record('supplier.' . $answer, 'form_requests', RequestForms::label($order), trim($reason) ?: null);
        Notifier::forCompany((int) $order['company_id'], static fn () => Notifier::toRoles(
            ['logistics_manager', 'warehouse_manager'],
            $answer === 'accept' ? 'Order accepted' : 'Order declined',
            sprintf('%s %s %s%s', $order['supplier_name'], $answer === 'accept' ? 'accepted' : 'declined', RequestForms::label($order), $answer === 'decline' ? ': ' . trim($reason) . '. Place it with another supplier.' : '.'),
            'form_requests',
            $answer === 'accept' ? 'success' : 'danger',
            'form_requests',
            (string) $orderId
        ));
    }

    /**
     * The supplier's invoice (F12) against the order: quantity and price per
     * order line, with the scan. It goes to Finance like any request.
     *
     * @param array<int, string> $quantities order line id => quantity invoiced
     * @param array<int, string> $prices order line id => unit price invoiced
     */
    public static function submitInvoice(int $orderId, int $supplierId, string $invoiceNo, string $invoiceDate, array $quantities, array $prices, ?string $scan): int
    {
        $order = RequestForms::find($orderId, false);
        if ($order === null || (int) $order['supplier_id'] !== $supplierId || !in_array($order['form_code'], ['F5', 'F2'], true) || !in_array($order['status'], ['authorized', 'closed'], true)) {
            throw new \RuntimeException('Invoices are sent against an order that was authorized for you.');
        }
        if (trim($invoiceNo) === '' || strtotime($invoiceDate) === false) {
            throw new \RuntimeException('Give your invoice number and date.');
        }
        if ($scan === null) {
            throw new \RuntimeException('Attach the scan of your invoice.');
        }
        $duplicate = self::db()->prepare("SELECT COUNT(*) FROM form_requests WHERE form_code = 'F12' AND supplier_id = ? AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.invoice_no')) = ? AND status <> 'cancelled' AND deleted_at IS NULL");
        $duplicate->execute([$supplierId, trim($invoiceNo)]);
        if ((int) $duplicate->fetchColumn() > 0) {
            throw new \RuntimeException('Invoice ' . trim($invoiceNo) . ' was already sent.');
        }

        $supplier = self::db()->prepare('SELECT * FROM suppliers WHERE id = ?');
        $supplier->execute([$supplierId]);
        $supplier = $supplier->fetch(PDO::FETCH_ASSOC) ?: [];
        $lines = [];
        foreach (RequestForms::lines($orderId) as $line) {
            $qty = trim((string) ($quantities[(int) $line['id']] ?? ''));
            if ($qty === '' || (float) $qty <= 0) {
                continue;
            }
            $price = trim((string) ($prices[(int) $line['id']] ?? '')) !== '' ? (float) str_replace(',', '', (string) $prices[(int) $line['id']]) : (float) $line['unit_price'];
            $lines[] = [
                'item_code' => $line['item_code'], 'item_id' => $line['item_id'], 'description' => $line['description'],
                'quantity' => (float) str_replace(',', '', $qty), 'unit' => $line['unit'], 'unit_price' => $price,
                'vehicle_id' => $line['vehicle_id'], 'trailer_id' => $line['trailer_id'], 'trip_id' => $line['trip_id'],
                'payee_type' => 'supplier', 'payee_id' => $supplierId, 'payee_name' => $supplier['supplier_name'] ?? '',
                'payment_method' => (string) ($supplier['payment_method'] ?? 'bank'), 'momo_number' => $supplier['momo_number'] ?? null, 'momo_name' => $supplier['momo_name'] ?? null,
                'extra' => ['order_line' => (int) $line['id']],
            ];
        }
        if ($lines === []) {
            throw new \RuntimeException('Enter the quantity invoiced on at least one line.');
        }

        $id = RequestForms::create([
            'form_code' => 'F12',
            'company_id' => (int) $order['company_id'],
            'title' => sprintf('Invoice %s — %s', trim($invoiceNo), $supplier['supplier_name'] ?? ''),
            'currency' => (string) $order['currency'],
            'supplier_id' => $supplierId,
            'vehicle_id' => $order['vehicle_id'],
            'parent_request_id' => $orderId,
            'payload' => ['invoice_no' => trim($invoiceNo), 'invoice_date' => date('Y-m-d', (int) strtotime($invoiceDate)), 'attachments' => [$scan], 'from_portal' => true],
        ], $lines);
        RequestForms::send($id);

        return $id;
    }

    /**
     * Order -> received -> invoice, line by line (section 18, step 6).
     *
     * @return list<array{description:string, ordered:float, ordered_price:float, received:float, invoiced:float, invoiced_price:float, problems:list<string>}>
     */
    public static function match(int $invoiceId): array
    {
        $invoice = RequestForms::find($invoiceId, false);
        if ($invoice === null || empty($invoice['parent_request_id'])) {
            return [];
        }
        $orderLines = RequestForms::lines((int) $invoice['parent_request_id']);
        $invoiced = [];
        foreach (RequestForms::lines($invoiceId) as $line) {
            $key = (int) ($line['extra']['order_line'] ?? 0);
            if ($key === 0) {
                foreach ($orderLines as $orderLine) {
                    if (($line['item_id'] && (int) $line['item_id'] === (int) $orderLine['item_id']) || strcasecmp(trim((string) $line['description']), trim((string) $orderLine['description'])) === 0) {
                        $key = (int) $orderLine['id'];
                        break;
                    }
                }
            }
            $invoiced[$key][] = $line;
        }

        $rows = [];
        foreach ($orderLines as $orderLine) {
            $id = (int) $orderLine['id'];
            $qty = array_sum(array_map(static fn (array $l): float => (float) $l['quantity'], $invoiced[$id] ?? []));
            $amount = array_sum(array_map(static fn (array $l): float => (float) $l['amount'], $invoiced[$id] ?? []));
            $price = $qty > 0 ? round($amount / $qty, 2) : 0.0;
            $received = (float) $orderLine['done_quantity'];
            if ($invoice['form_code'] === 'F12' && ($orderForm ??= (string) (RequestForms::find((int) $invoice['parent_request_id'], false)['form_code'] ?? '')) === 'F2') {
                // Fuel: what the station confirmed it delivered to that truck.
                $fuel = self::db()->prepare('SELECT COALESCE(SUM(litres_delivered), 0) FROM fuel_orders WHERE request_id = ? AND vehicle_id <=> ?');
                $fuel->execute([(int) $invoice['parent_request_id'], $orderLine['vehicle_id']]);
                $received = (float) $fuel->fetchColumn();
            }
            $problems = [];
            if ($qty <= 0) {
                $problems[] = 'ordered but not invoiced';
            } else {
                if ($qty > $received + 0.004) {
                    $problems[] = sprintf('invoiced %s but only %s received', self::n($qty), self::n($received));
                }
                if ($qty > (float) $orderLine['quantity'] + 0.004) {
                    $problems[] = 'more than ordered';
                }
                if (abs($price - (float) $orderLine['unit_price']) > 0.004) {
                    $problems[] = sprintf('price %s instead of %s', number_format($price, 2), number_format((float) $orderLine['unit_price'], 2));
                }
            }
            $rows[] = ['description' => (string) $orderLine['description'], 'ordered' => (float) $orderLine['quantity'], 'ordered_price' => (float) $orderLine['unit_price'], 'received' => $received, 'invoiced' => $qty, 'invoiced_price' => $price, 'problems' => $problems];
        }
        foreach ($invoiced[0] ?? [] as $line) {
            $rows[] = ['description' => (string) $line['description'], 'ordered' => 0.0, 'ordered_price' => 0.0, 'received' => 0.0, 'invoiced' => (float) $line['quantity'], 'invoiced_price' => (float) $line['unit_price'], 'problems' => ['not on the order']];
        }

        return $rows;
    }

    private static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
