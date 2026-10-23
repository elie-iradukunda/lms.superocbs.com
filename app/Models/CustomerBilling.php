<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * Customer invoicing (section 13, F13):
 *
 *   1. Finance picks the customer and the period; the delivered trips are
 *      listed with their delivery notes.
 *   2. Each trip is priced from the customer's rate card: per trip, or per
 *      ton / litre / container delivered, per customer and currency.
 *   3. Shortages the customer deducts are shown on the invoice.
 *   4. Finance -> MD; authorized, it posts (receivable and income), becomes
 *      the customer's invoice, is emailed and appears in their portal.
 *   5. Payments are recorded against it; unpaid invoices show in ageing.
 *
 * A trip is invoiced once (control 7): a trip already on a live F13 or an
 * invoice is not offered again.
 */
final class CustomerBilling
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Trips delivered to a customer in a period that are not invoiced yet,
     * each with its price.
     *
     * @return list<array<string, mixed>>
     */
    public static function billable(int $customerId, string $from, string $to): array
    {
        $condition = Company::condition('trips', 't.company_id');
        $statement = self::db()->prepare(
            "SELECT t.*, v.plate_number, d.full_name AS driver_name, ro.route_code, ro.route_name,
                    n.id AS note_id, n.request_no AS note_no, n.payload AS note_payload, n.created_at AS delivered_at
               FROM trips t
               INNER JOIN form_requests n ON n.trip_id = t.id AND n.form_code = 'F8' AND n.status = 'closed' AND n.deleted_at IS NULL
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN drivers d ON d.id = t.driver_id
               LEFT JOIN routes ro ON ro.id = t.route_id
              WHERE t.deleted_at IS NULL AND t.customer_id = ? AND DATE(n.created_at) BETWEEN ? AND ?
                AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.trip_id = t.id AND i.deleted_at IS NULL AND i.status <> 'cancelled')
                AND NOT EXISTS (SELECT 1 FROM invoice_lines il INNER JOIN invoices i ON i.id = il.invoice_id WHERE il.trip_id = t.id AND i.deleted_at IS NULL AND i.status <> 'cancelled')
                AND NOT EXISTS (SELECT 1 FROM form_request_lines rl INNER JOIN form_requests r ON r.id = rl.request_id
                                 WHERE r.form_code = 'F13' AND rl.trip_id = t.id AND rl.item_code = 'transport' AND r.deleted_at IS NULL AND r.status <> 'cancelled')"
            . ($condition !== null ? ' AND ' . $condition : '') . '
              ORDER BY n.created_at, t.id'
        );
        $statement->execute([$customerId, $from, $to]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['note'] = json_decode((string) ($row['note_payload'] ?? ''), true) ?: [];
            $row['price'] = self::price($row);
        }

        return $rows;
    }

    /**
     * The price of one delivered trip from the customer's rate card.
     *
     * @return array{card: ?array, amount: float, currency: string, basis: string, quantity: float, problem: ?string}
     */
    public static function price(array $trip): array
    {
        $card = self::card((int) $trip['company_id'], (int) $trip['customer_id'], $trip['route_id'] ? (int) $trip['route_id'] : null, (string) ($trip['cargo_type'] ?? ''), (string) ($trip['delivered_at'] ?? date('Y-m-d')));
        if ($card === null) {
            return ['card' => null, 'amount' => 0.0, 'currency' => '', 'basis' => '', 'quantity' => 0.0, 'problem' => 'No rate card for this customer and route. Finance adds one under Rate cards.'];
        }
        $delivered = (float) ($trip['note']['delivered'] ?? $trip['delivered_quantity'] ?? 0);
        $containers = $trip['container_numbers'] ? count(array_filter(preg_split('/[\s,;]+/', (string) $trip['container_numbers']))) : 0;
        [$quantity, $basis] = match ((string) $card['rate_type']) {
            'per_ton' => [(string) $trip['cargo_type'] === 'tonnes' ? $delivered : 0.0, 'per ton delivered'],
            'per_kg' => [(string) $trip['cargo_type'] === 'tonnes' ? $delivered * 1000 : 0.0, 'per kg delivered'],
            'per_litre' => [(string) $trip['cargo_type'] === 'litres' ? $delivered : 0.0, 'per litre delivered'],
            'per_container' => [max(1, $containers), 'per container'],
            default => [1.0, 'per trip'],
        };
        if ($quantity <= 0) {
            return ['card' => $card, 'amount' => 0.0, 'currency' => (string) $card['currency'], 'basis' => $basis, 'quantity' => 0.0, 'problem' => 'The rate card is ' . $basis . ' but the delivery note has no such quantity.'];
        }
        $amount = round($quantity * (float) $card['rate_amount'], 2);
        if ($card['minimum_charge'] !== null && $amount < (float) $card['minimum_charge']) {
            $amount = (float) $card['minimum_charge'];
        }

        return ['card' => $card, 'amount' => $amount, 'currency' => (string) $card['currency'], 'basis' => $basis, 'quantity' => $quantity, 'problem' => null];
    }

    /** The customer's card for the route and cargo, else the route's card for everyone. */
    public static function card(int $companyId, int $customerId, ?int $routeId, string $cargoType, string $date): ?array
    {
        $statement = self::db()->prepare(
            "SELECT * FROM rate_cards WHERE deleted_at IS NULL AND status = 'active'
                AND (customer_id = ? OR customer_id IS NULL) AND (route_id = ? OR route_id IS NULL)
                AND (cargo_type = ? OR cargo_type IS NULL OR cargo_type = '')
                AND (company_id = ? OR company_id IS NULL)
                AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
                AND (route_id IS NOT NULL OR customer_id IS NOT NULL)
              ORDER BY customer_id IS NULL, route_id IS NULL, cargo_type IS NULL, effective_from DESC LIMIT 1"
        );
        $date = date('Y-m-d', (int) strtotime($date));
        $statement->execute([$customerId, $routeId ?? 0, $cargoType, $companyId, $date, $date]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    // ------------------------------------------------- rate card form (Hooks)

    /** A card on a route takes the route's places when none are typed. */
    public static function derive(string $key, array $columns, ?int $id): array
    {
        if ($key === 'rates' && !empty($columns['route_id'])) {
            $route = self::db()->prepare('SELECT origin, destination, turnaround_point FROM routes WHERE id = ?');
            $route->execute([(int) $columns['route_id']]);
            if (($row = $route->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (trim((string) ($columns['origin'] ?? '')) === '') {
                    $columns['origin'] = $row['origin'];
                }
                if (trim((string) ($columns['destination'] ?? '')) === '') {
                    $columns['destination'] = $row['turnaround_point'] ?: $row['destination'];
                }
            }
        }

        return $columns;
    }

    /** @return list<string> */
    public static function rules(string $key, array $input, ?int $id): array
    {
        $problems = [];
        if ($key === 'rates') {
            if (empty($input['route_id']) && (trim((string) ($input['origin'] ?? '')) === '' || trim((string) ($input['destination'] ?? '')) === '')) {
                $problems[] = 'Choose the route, or type where the card goes from and to.';
            }
            $needs = ['per_ton' => 'tonnes', 'per_kg' => 'tonnes', 'per_litre' => 'litres', 'per_container' => 'containers'][(string) ($input['rate_type'] ?? '')] ?? null;
            if ($needs !== null && !empty($input['cargo_type']) && $input['cargo_type'] !== $needs) {
                $problems[] = 'A price ' . str_replace('_', ' ', (string) $input['rate_type']) . ' needs the cargo to be ' . $needs . '.';
            }
        }

        return $problems;
    }

    /**
     * Builds the F13 for the chosen trips: one request per currency. Each trip
     * is a transport line; a shortage the customer deducts is a negative line;
     * VAT is added when the setting asks for it. Returns the request ids.
     *
     * @param list<int> $tripIds
     * @return list<int>
     */
    public static function build(int $customerId, string $from, string $to, array $tripIds, string $lpo = ''): array
    {
        $customer = self::db()->prepare('SELECT * FROM customers WHERE id = ?');
        $customer->execute([$customerId]);
        $customer = $customer->fetch(PDO::FETCH_ASSOC);
        if ($customer === false) {
            throw new \RuntimeException('Choose the customer.');
        }
        $chosen = array_flip(array_map('intval', $tripIds));
        $trips = array_values(array_filter(self::billable($customerId, $from, $to), static fn (array $t): bool => isset($chosen[(int) $t['id']])));
        if ($trips === []) {
            throw new \RuntimeException('Tick at least one delivered trip to invoice.');
        }
        $problems = [];
        foreach ($trips as $trip) {
            if ($trip['price']['problem'] !== null) {
                $problems[] = $trip['reference_code'] . ': ' . $trip['price']['problem'];
            }
        }
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $byCurrency = [];
        foreach ($trips as $trip) {
            $byCurrency[$trip['price']['currency']][] = $trip;
        }
        $vat = max(0.0, Settings::float('invoice_vat_percent', 0));
        $ids = [];
        foreach ($byCurrency as $currency => $group) {
            $lines = [];
            $subtotal = 0.0;
            foreach ($group as $trip) {
                $price = $trip['price'];
                $note = $trip['note'];
                $unit = (string) ($note['unit'] ?? '');
                $lines[] = [
                    'item_code' => 'transport', 'trip_id' => (int) $trip['id'], 'vehicle_id' => (int) $trip['vehicle_id'], 'driver_id' => (int) $trip['driver_id'],
                    'description' => sprintf('%s · %s · %s · %s · delivered %s %s (%s)', $trip['reference_code'], $trip['plate_number'], $trip['route_code'] ?: trim($trip['pickup_location'] . '–' . $trip['destination'], '–'), \human_date((string) $trip['delivered_at']), $note['delivered'] ?? '', $unit, $price['basis']),
                    'quantity' => $price['quantity'], 'unit' => $price['basis'] === 'per trip' ? 'trip' : $unit, 'unit_price' => (float) $price['card']['rate_amount'], 'amount' => $price['amount'],
                    'extra' => ['delivery_note' => $trip['note_no'], 'rate_card' => $price['card']['rate_code']],
                ];
                $subtotal += $price['amount'];
                $assessment = $note['assessment'] ?? null;
                if (is_array($assessment) && (float) ($assessment['value'] ?? 0) > 0 && ($assessment['currency'] ?? '') === $currency) {
                    $lines[] = [
                        'item_code' => 'deduction', 'trip_id' => (int) $trip['id'], 'vehicle_id' => (int) $trip['vehicle_id'], 'driver_id' => (int) $trip['driver_id'],
                        'description' => sprintf('Shortage deducted: %s %s above tolerance on %s', $assessment['chargeable'], $unit, $trip['reference_code']),
                        'quantity' => (float) $assessment['chargeable'], 'unit' => $unit, 'unit_price' => (float) $assessment['unit_value'], 'amount' => -1 * (float) $assessment['value'],
                    ];
                }
            }
            if ($vat > 0) {
                $lines[] = ['item_code' => 'vat', 'description' => sprintf('VAT %s%%', rtrim(rtrim(number_format($vat, 2), '0'), '.')), 'quantity' => 1, 'amount' => round($subtotal * $vat / 100, 2)];
            }
            $ids[] = RequestForms::create([
                'form_code' => 'F13',
                'title' => sprintf('Invoice %s — %s to %s', $customer['customer_name'], \human_date($from), \human_date($to)),
                'currency' => $currency,
                'customer_id' => $customerId,
                'period_from' => $from,
                'period_to' => $to,
                'payload' => ['lpo' => trim($lpo)],
                'notes' => trim($lpo) !== '' ? 'Customer order (LPO): ' . trim($lpo) : '',
            ], $lines);
        }
        AuditLog::record('billing.invoice_built', 'form_requests', implode(',', $ids), $customer['customer_name']);

        return $ids;
    }

    /**
     * Authorized by the MD (the entry posted with the authorization): the
     * customer's invoice is made, emailed with its PDF, and shown in their
     * portal.
     */
    public static function invoiceAuthorized(array $request): void
    {
        $id = (int) $request['id'];
        $db = self::db();
        $exists = $db->prepare('SELECT id FROM invoices WHERE request_id = ?');
        $exists->execute([$id]);
        if ($exists->fetchColumn()) {
            return;
        }
        $customer = $db->prepare('SELECT * FROM customers WHERE id = ?');
        $customer->execute([(int) $request['customer_id']]);
        $customer = $customer->fetch(PDO::FETCH_ASSOC) ?: [];
        $lines = RequestForms::lines($id);
        $subtotal = 0.0;
        $tax = 0.0;
        $trips = [];
        foreach ($lines as $line) {
            if ($line['item_code'] === 'vat') {
                $tax += (float) $line['amount'];
            } else {
                $subtotal += (float) $line['amount'];
            }
            if ($line['trip_id']) {
                $trips[(int) $line['trip_id']] = true;
            }
        }
        $total = round($subtotal + $tax, 2);
        $days = (int) ($customer['payment_terms_days'] ?? 0) ?: Settings::int('invoice_due_days', 30);
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : (json_decode((string) ($request['payload'] ?? ''), true) ?: []);
        $base = Company::baseCurrency((int) $request['company_id']);
        $rate = Currency::crossRate((string) $request['currency'], $base, date('Y-m-d')) ?? 1.0;

        $db->prepare(
            "INSERT INTO invoices (company_id, invoice_number, customer_id, trip_id, request_id, lpo_number, issue_date, due_date, subtotal, tax_rate, tax_amount, total_amount, currency, exchange_rate, base_amount, amount_paid, status, issued_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, ?, ?, ?, ?, ?, ?, 0, 'issued', ?, ?)"
        )->execute([
            (int) $request['company_id'], (string) $request['request_no'], (int) $request['customer_id'], count($trips) === 1 ? array_key_first($trips) : null, $id,
            ($payload['lpo'] ?? '') ?: null, $days, round($subtotal, 2), $subtotal > 0 ? round($tax / $subtotal * 100, 2) : 0, round($tax, 2), $total,
            (string) $request['currency'], $rate, round($total * $rate, 2), $request['prepared_by'], $request['title'],
        ]);
        $invoiceId = (int) $db->lastInsertId();
        $insertLine = $db->prepare('INSERT INTO invoice_lines (invoice_id, trip_id, request_line_id, description, quantity, unit_price, line_kind) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($lines as $line) {
            if ($line['item_code'] === 'vat') {
                continue;
            }
            $quantity = (float) $line['quantity'] ?: 1.0;
            $unitPrice = round((float) $line['amount'] / $quantity, 2);
            if (abs($unitPrice * $quantity - (float) $line['amount']) > 0.004) {
                // The invoice line multiplies out to the amount on the request.
                [$quantity, $unitPrice] = [1.0, (float) $line['amount']];
            }
            $insertLine->execute([$invoiceId, $line['trip_id'] ?: null, (int) $line['id'], mb_substr((string) $line['description'], 0, 255), $quantity, $unitPrice, 'service']);
        }
        AuditLog::record('billing.invoice_issued', 'invoices', (string) $request['request_no'], (string) ($customer['customer_name'] ?? ''));

        // The customer's portal users, and their email.
        $users = $db->prepare("SELECT id FROM users WHERE customer_id = ? AND status = 'active' AND deleted_at IS NULL");
        $users->execute([(int) $request['customer_id']]);
        foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            Notifier::toUser((int) $userId, 'New invoice', sprintf('%s for %s from %s.', $request['request_no'], Currency::format($total, (string) $request['currency']), $request['company_name']), 'portal', 'info', 'invoices', (string) $invoiceId);
        }
        $email = trim((string) ($customer['email'] ?? ''));
        if ($email !== '') {
            $pdf = RequestPdf::store($id);
            Mailer::send([
                'key' => 'customer-invoice-' . $id,
                'category' => 'customer_invoice',
                'to' => $email,
                'to_name' => (string) $customer['customer_name'],
                'subject' => sprintf('Invoice %s from %s', $request['request_no'], $request['company_name']),
                'heading' => 'Invoice ' . $request['request_no'],
                'lines' => [
                    sprintf('Dear %s,', $customer['customer_name']),
                    sprintf('Please find attached invoice %s for %s, due on %s.', $request['request_no'], Currency::format($total, (string) $request['currency']), \human_date(date('Y-m-d', strtotime('+' . $days . ' days')))),
                    'The delivery notes of each trip are listed on it and can be seen in your portal.',
                ],
                'attachments' => $pdf === null ? [] : [$pdf],
                'entity_type' => 'invoices',
                'entity_id' => (string) $invoiceId,
            ]);
        }
    }
}
