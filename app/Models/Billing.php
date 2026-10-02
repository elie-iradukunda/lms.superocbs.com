<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Turns a trip into its invoices, so finance never retypes what operations
 * already recorded.
 *
 * A trip can carry loads for several customers, each billed in their own
 * currency, so it becomes one draft invoice per customer and currency. Each
 * load is a line priced at what the customer was quoted, and the customer's
 * LPO number travels onto the invoice. The invoice is left as a draft: finance
 * checks it and presses Issue, which is what posts it to the books.
 */
final class Billing
{
    /**
     * @return array{ok: bool, message: string, invoices: list<array{id: int, number: string, created: bool}>}
     */
    public static function invoiceTrip(int $tripId): array
    {
        $db = Database::connection();

        $trip = self::row('SELECT * FROM trips WHERE id = ? AND deleted_at IS NULL', [$tripId]);
        if ($trip === null) {
            return ['ok' => false, 'message' => 'That trip no longer exists.', 'invoices' => []];
        }
        if ($trip['status'] === 'cancelled') {
            return ['ok' => false, 'message' => sprintf('%s was cancelled, so there is nothing to bill.', $trip['reference_code']), 'invoices' => []];
        }

        // A leg of a journey is billed as the whole journey: the customer pays
        // once for Dar es Salaam to Kigali, not once per truck. The invoice sits
        // on the first leg and carries the journey.
        $journeyId = (int) ($trip['journey_id'] ?? 0);
        $legIds = [$tripId];
        if ($journeyId > 0) {
            $legs = array_values(array_filter(Journey::legs($journeyId), static fn (array $leg): bool => $leg['status'] !== 'cancelled'));
            if ($legs !== []) {
                $legIds = array_map(static fn (array $leg): int => (int) $leg['id'], $legs);
                $trip = $legs[0] + ['journey_reference' => (string) self::scalar('SELECT journey_code FROM journeys WHERE id = ?', [$journeyId])];
                $tripId = (int) $trip['id'];
            }
        }
        $legList = implode(',', array_map('intval', $legIds));

        $statement = $db->prepare(
            "SELECT s.*, c.currency AS customer_currency, c.payment_terms_days, r.lpo_number AS request_lpo,
                    r.quoted_amount AS request_quote, r.currency AS request_currency,
                    (SELECT COUNT(*) FROM shipments x WHERE x.request_id = s.request_id AND x.deleted_at IS NULL AND x.status <> 'cancelled') AS loads_on_request
               FROM shipments s
               INNER JOIN customers c ON c.id = s.customer_id
               LEFT JOIN transport_requests r ON r.id = s.request_id
              WHERE s.trip_id IN ({$legList}) AND s.deleted_at IS NULL AND s.status <> 'cancelled'
              ORDER BY s.id"
        );
        $statement->execute();
        $loads = $statement->fetchAll(PDO::FETCH_ASSOC);

        // A truck hired whole by one customer may carry no booked shipment; the
        // request it fulfils still says who pays and what was quoted.
        if ($loads === [] && !empty($trip['request_id'])) {
            $request = self::row(
                "SELECT r.*, c.currency AS customer_currency, c.payment_terms_days
                   FROM transport_requests r INNER JOIN customers c ON c.id = r.customer_id
                  WHERE r.id = ? AND r.deleted_at IS NULL",
                [(int) $trip['request_id']]
            );
            if ($request !== null) {
                $loads[] = [
                    'shipment_code' => $request['reference_code'], 'id' => null,
                    'customer_id' => $request['customer_id'], 'currency' => $request['currency'],
                    'customer_currency' => $request['customer_currency'], 'payment_terms_days' => $request['payment_terms_days'],
                    'origin' => $request['pickup_location'], 'destination' => $request['destination'],
                    'cargo_description' => $request['cargo_description'], 'quoted_amount' => $request['quoted_amount'],
                    'customer_reference' => $request['lpo_number'], 'request_lpo' => $request['lpo_number'],
                    'container_no' => null,
                ];
            }
        }

        if ($loads === []) {
            return ['ok' => false, 'message' => sprintf('%s carries no customer shipment yet. Load a shipment on it first.', $trip['reference_code']), 'invoices' => []];
        }

        $groups = [];
        foreach ($loads as $load) {
            $currency = strtoupper((string) ($load['currency'] ?: ($load['customer_currency'] ?: Currency::base())));
            $groups[(int) $load['customer_id'] . '|' . $currency][] = $load + ['bill_currency' => $currency];
        }

        $results = [];
        $unpriced = 0;

        foreach ($groups as $groupKey => $group) {
            [$customerId, $currency] = explode('|', $groupKey);
            $customerId = (int) $customerId;

            $existing = self::row(
                "SELECT id, invoice_number FROM invoices
                  WHERE trip_id IN ({$legList}) AND customer_id = ? AND currency = ? AND deleted_at IS NULL AND status <> 'cancelled'
                  ORDER BY id LIMIT 1",
                [$customerId, $currency]
            );
            if ($existing !== null) {
                $results[] = ['id' => (int) $existing['id'], 'number' => (string) $existing['invoice_number'], 'created' => false];
                continue;
            }

            $first = $group[0];
            $lpos = array_values(array_unique(array_filter(array_map(
                static fn (array $l): string => trim((string) (($l['customer_reference'] ?? '') ?: ($l['request_lpo'] ?? ''))),
                $group
            ))));
            $terms = (int) ($first['payment_terms_days'] ?? 0) ?: Settings::int('payment_terms_days', 30);

            $invoiceId = LogisticsData::save('invoices', null, [
                'invoice_number' => '',
                'customer_id' => (string) $customerId,
                'trip_id' => (string) $tripId,
                'status' => 'draft',
                'currency' => $currency,
                'lpo_number' => implode(', ', $lpos),
                'issue_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+' . $terms . ' days')),
                'tax_rate' => (string) Settings::float('tax_rate', 18.0),
                'notes' => $journeyId > 0
                    ? sprintf('Raised from journey %s (%d legs).', $trip['journey_reference'] ?? '', count($legIds))
                    : sprintf('Raised from trip %s (%s to %s).', $trip['reference_code'], $trip['pickup_location'], $trip['destination']),
            ]);
            if ($journeyId > 0) {
                $db->prepare('UPDATE invoices SET journey_id = ? WHERE id = ?')->execute([$journeyId, $invoiceId]);
            }

            $lines = [];
            foreach ($group as $load) {
                $price = $load['quoted_amount'] !== null ? (float) $load['quoted_amount'] : 0.0;
                // The customer accepted the request's quote. When the load is the
                // only one booked against that request and no rate card priced the
                // shipment itself, that accepted price is what is owed.
                if ($price <= 0 && (float) ($load['request_quote'] ?? 0) > 0 && (int) ($load['loads_on_request'] ?? 0) === 1
                    && strtoupper((string) ($load['request_currency'] ?? '')) === $load['bill_currency']) {
                    $price = (float) $load['request_quote'];
                }
                if ($price <= 0) {
                    $unpriced++;
                }
                $description = sprintf('Freight %s to %s: %s', $load['origin'], $load['destination'], $load['cargo_description']);
                if (!empty($load['container_no'])) {
                    $description .= ', container ' . $load['container_no'];
                }
                $description .= ' (' . $load['shipment_code'] . ')';
                $lines[] = ['description' => mb_substr($description, 0, 255), 'quantity' => '1', 'unit_price' => (string) $price];
            }
            LogisticsData::saveLines('invoices', $invoiceId, $lines);

            // Duty and port charges paid for this customer go on the same
            // invoice, at cost and without VAT, so none is left unclaimed.
            try {
                $disbursed = Payables::addToInvoice($invoiceId);
            } catch (\Throwable) {
                $disbursed = 0;
            }

            $number = (string) self::scalar('SELECT invoice_number FROM invoices WHERE id = ?', [$invoiceId]);
            AuditLog::record('invoice.from_trip', 'invoices', $number, null, ['trip' => $trip['reference_code']]);
            Notifier::toRole('finance', 'Draft invoice ready to issue',
                sprintf('%s was raised from trip %s. Check it and press Issue invoice.', $number, $trip['reference_code']),
                'invoices', 'info', 'invoices', (string) $invoiceId);

            $results[] = ['id' => $invoiceId, 'number' => $number, 'created' => true, 'disbursements' => $disbursed];
        }

        $created = array_values(array_filter($results, static fn (array $r): bool => $r['created']));
        $kept = array_values(array_filter($results, static fn (array $r): bool => !$r['created']));

        $parts = [];
        if ($created !== []) {
            $parts[] = sprintf('Draft invoice%s %s raised from %s. Check %s and press Issue invoice.',
                count($created) === 1 ? '' : 's', implode(', ', array_column($created, 'number')), $trip['reference_code'],
                count($created) === 1 ? 'it' : 'each one');
        }
        $disbursedLines = array_sum(array_column($created, 'disbursements'));
        if ($disbursedLines > 0) {
            $parts[] = sprintf('%d disbursement%s recharged at cost.', $disbursedLines, $disbursedLines === 1 ? '' : 's');
        }
        if ($kept !== []) {
            $parts[] = sprintf('Already invoiced: %s.', implode(', ', array_column($kept, 'number')));
        }
        if ($unpriced > 0) {
            $parts[] = sprintf('%d line%s had no quoted price (no rate card matched); enter the price on the invoice lines.', $unpriced, $unpriced === 1 ? '' : 's');
        }

        return ['ok' => true, 'message' => implode(' ', $parts), 'invoices' => $results];
    }

    private static function row(string $sql, array $params): ?array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function scalar(string $sql, array $params): mixed
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }
}
