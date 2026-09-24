<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * What to charge for carrying something.
 *
 * The business quotes the way a haulier thinks: "Kigali to Rusumo, a full truck
 * is 500 kg and costs 100,000." A customer with 100 kg for the same destination
 * is taking a fifth of the truck and pays a fifth of the price.
 *
 * That is all this does, but doing it in one place means the office stops
 * working it out on the back of an envelope, two people stop quoting different
 * numbers for the same run, and every quote can be explained afterwards:
 *
 *     100 kg of a 500 kg load to Rusumo at RWF 100,000 = RWF 20,000
 *
 * A quoted figure is written onto the shipment and kept there. Renegotiating a
 * rate card next month must not silently change what a customer was told last
 * month.
 */
final class Rate
{
    /**
     * The price for one load.
     *
     * Returns null when no card covers the route, which is a real answer: it
     * means somebody has to agree a price, and inventing one here would hide
     * that.
     *
     * @return array{card: array, amount: float, basis: string, minimum_applied: bool}|null
     */
    public static function quote(
        string $destination,
        float $weightKg = 0,
        ?int $customerId = null,
        string $origin = '',
        string $vehicleType = '',
        string $onDate = '',
        ?int $originWarehouseId = null,
        ?int $destinationWarehouseId = null
    ): ?array {
        $card = self::cardFor($destination, $customerId, $origin, $vehicleType, $onDate, $originWarehouseId, $destinationWarehouseId);
        if ($card === null) {
            return null;
        }

        return self::price($card, $weightKg);
    }

    /**
     * Apply a card to a weight.
     *
     * @return array{card: array, amount: float, basis: string, minimum_applied: bool}
     */
    public static function price(array $card, float $weightKg): array
    {
        $unit = self::unitRate($card);
        $minimum = (float) ($card['minimum_charge'] ?? 0);

        // Per trip and per day do not care what the load weighs: the truck goes
        // whether it is full or not.
        if (in_array((string) $card['rate_type'], ['per_trip', 'per_day'], true) || $weightKg <= 0) {
            $amount = (float) $card['rate_amount'];
            $basis = sprintf('%s to %s, %s', $card['origin'], $card['destination'], self::rateTypeWords((string) $card['rate_type']));
        } else {
            $amount = round($unit * $weightKg, 2);
            $basis = self::explain($card, $weightKg, $amount);
        }

        $belowMinimum = $minimum > 0 && $amount < $minimum;
        if ($belowMinimum) {
            $basis .= sprintf(', brought up to the minimum charge of %s', self::inCardMoney($minimum, $card));
            $amount = $minimum;
        }

        return ['card' => $card, 'amount' => round($amount, 2), 'basis' => $basis, 'minimum_applied' => $belowMinimum];
    }

    /**
     * The rate for one unit, worked out from the full-load pair when there is one.
     *
     * A card written as "500 kg for 100,000" is the same card as one written as
     * "200 a kilo", but the pair can be checked by the person who agreed it.
     */
    public static function unitRate(array $card): float
    {
        $fullKg = (float) ($card['full_load_kg'] ?? 0);
        $fullPrice = (float) ($card['full_load_price'] ?? 0);

        if ($fullKg > 0 && $fullPrice > 0) {
            return round($fullPrice / $fullKg, 6);
        }

        return (float) ($card['rate_amount'] ?? 0);
    }

    /**
     * The quote in the words somebody would use to justify it.
     *
     * The customer asking "why 20,000?" gets the working, not a number.
     */
    private static function explain(array $card, float $weightKg, float $amount): string
    {
        $fullKg = (float) ($card['full_load_kg'] ?? 0);
        $fullPrice = (float) ($card['full_load_price'] ?? 0);
        $weight = self::number($weightKg);

        if ($fullKg > 0 && $fullPrice > 0) {
            return sprintf(
                '%s kg of a %s kg load to %s at %s a full truck',
                $weight,
                self::number($fullKg),
                $card['destination'],
                self::inCardMoney($fullPrice, $card)
            );
        }

        return sprintf('%s kg to %s at %s a kilogram', $weight, $card['destination'], self::inCardMoney(self::unitRate($card), $card));
    }

    /**
     * The card that applies, most specific first.
     *
     * A price agreed with one customer beats the list price; a price for one
     * kind of vehicle beats a price for any vehicle. Where two cards are equally
     * specific, the one that came into force most recently wins, because that is
     * the one that was agreed last.
     */
    public static function cardFor(
        string $destination,
        ?int $customerId = null,
        string $origin = '',
        string $vehicleType = '',
        string $onDate = '',
        ?int $originWarehouseId = null,
        ?int $destinationWarehouseId = null
    ): ?array {
        $destination = trim($destination);
        if ($destination === '' && $destinationWarehouseId === null) {
            return null;
        }

        $onDate = trim($onDate) !== '' ? date('Y-m-d', (int) strtotime($onDate)) : date('Y-m-d');

        $sql = "SELECT * FROM rate_cards
                 WHERE deleted_at IS NULL
                   AND status = 'active'
                   AND effective_from <= ?
                   AND (effective_to IS NULL OR effective_to >= ?)";
        $parameters = [$onDate, $onDate];

        // A warehouse is an exact thing; a typed place name is a hope. When the
        // shipment names a warehouse, the card must name the same one — that is
        // what stops "Kigali", "kigali " and "Kigali Central" being three routes.
        if ($destinationWarehouseId !== null) {
            $sql .= ' AND (destination_warehouse_id = ? OR (destination_warehouse_id IS NULL AND destination = ?))';
            $parameters[] = $destinationWarehouseId;
            $parameters[] = $destination;
        } else {
            $sql .= ' AND destination = ?';
            $parameters[] = $destination;
        }

        if ($originWarehouseId !== null) {
            $sql .= ' AND (origin_warehouse_id = ? OR origin_warehouse_id IS NULL)';
            $parameters[] = $originWarehouseId;
        } elseif ($origin !== '') {
            $sql .= ' AND (origin = ? OR origin = "")';
            $parameters[] = trim($origin);
        }

        // A card naming this customer, or the list price. Never another
        // customer's negotiated rate.
        if ($customerId !== null) {
            $sql .= ' AND (customer_id = ? OR customer_id IS NULL)';
            $parameters[] = $customerId;
        } else {
            $sql .= ' AND customer_id IS NULL';
        }

        if ($vehicleType !== '') {
            $sql .= ' AND (vehicle_type = ? OR vehicle_type IS NULL OR vehicle_type = "")';
            $parameters[] = trim($vehicleType);
        }

        // Most specific first: a price agreed with this customer, on this pair of
        // warehouses, for this kind of vehicle. Where two are equally specific,
        // the one agreed most recently wins.
        $sql .= ' ORDER BY (customer_id IS NULL),
                           (destination_warehouse_id IS NULL),
                           (origin_warehouse_id IS NULL),
                           (vehicle_type IS NULL OR vehicle_type = ""),
                           effective_from DESC
                  LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        $card = $statement->fetch(PDO::FETCH_ASSOC);

        return $card === false ? null : $card;
    }

    /**
     * Price a shipment and remember the answer on it.
     *
     * Called when a shipment is saved. A figure already quoted is left alone
     * unless the weight or the destination changed, so that re-saving a shipment
     * to correct a phone number does not requote it at today's rate.
     */
    public static function quoteShipment(int $shipmentId, array $before = []): void
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, t.reference_code AS trip_code, v.vehicle_type
               FROM shipments s
               LEFT JOIN trips t ON t.id = s.trip_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
              WHERE s.id = ? AND s.deleted_at IS NULL'
        );
        $statement->execute([$shipmentId]);
        $shipment = $statement->fetch(PDO::FETCH_ASSOC);

        if ($shipment === false) {
            return;
        }

        $changed = $before === []
            || (string) ($before['destination'] ?? '') !== (string) $shipment['destination']
            || (int) ($before['destination_warehouse_id'] ?? 0) !== (int) $shipment['destination_warehouse_id']
            || (int) ($before['origin_warehouse_id'] ?? 0) !== (int) $shipment['origin_warehouse_id']
            || (float) ($before['weight_kg'] ?? 0) !== (float) $shipment['weight_kg']
            || (int) ($before['customer_id'] ?? 0) !== (int) $shipment['customer_id'];

        if (!$changed && $shipment['quoted_amount'] !== null) {
            return;
        }

        $quote = self::quote(
            (string) $shipment['destination'],
            (float) $shipment['weight_kg'],
            $shipment['customer_id'] !== null ? (int) $shipment['customer_id'] : null,
            (string) $shipment['origin'],
            (string) ($shipment['vehicle_type'] ?? ''),
            '',
            $shipment['origin_warehouse_id'] !== null ? (int) $shipment['origin_warehouse_id'] : null,
            $shipment['destination_warehouse_id'] !== null ? (int) $shipment['destination_warehouse_id'] : null
        );

        $update = Database::connection()->prepare(
            'UPDATE shipments SET rate_card_id = ?, quoted_amount = ?, quote_basis = ? WHERE id = ?'
        );

        if ($quote === null) {
            // Said plainly rather than left blank: a blank price looks like a
            // free load, and somebody has to agree a figure.
            $update->execute([null, null, self::noCardReason(
                (string) $shipment['destination'],
                $shipment['destination_warehouse_id'] !== null ? (int) $shipment['destination_warehouse_id'] : null,
                $shipment['customer_id'] !== null ? (int) $shipment['customer_id'] : null
            ), $shipmentId]);
            return;
        }

        $update->execute([(int) $quote['card']['id'], $quote['amount'], mb_substr($quote['basis'], 0, 160), $shipmentId]);
    }

    /**
     * Price a transport request and remember the answer on it.
     *
     * The customer asked to have something carried; this works out what it costs
     * before anyone commits a truck to it. The figure is kept, so accepting it
     * later is accepting this number and not whatever the rate card says by then.
     *
     * @return array{amount: float, basis: string}|null null when nothing covers the route
     */
    /**
     * Why nothing could be priced, in words the person can act on.
     *
     * "No rate card covers Nairobi yet" sends someone to write a card that is
     * already there. The three answers are different jobs: write a card, put a
     * date on it, or fix the depot on the record.
     */
    public static function noCardReason(string $destination, ?int $destinationWarehouseId, ?int $customerId = null): string
    {
        $place = trim($destination) !== '' ? trim($destination) : 'that destination';

        if ($destinationWarehouseId === null) {
            return 'No depot is named on this record, so no rate card could be matched.';
        }

        $statement = Database::connection()->prepare(
            "SELECT status, effective_from, effective_to, customer_id
               FROM rate_cards
              WHERE deleted_at IS NULL AND destination_warehouse_id = ?
              ORDER BY effective_from DESC"
        );
        $statement->execute([$destinationWarehouseId]);
        $cards = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($cards === []) {
            return 'No rate card covers ' . $place . ' yet.';
        }

        $today = date('Y-m-d');
        $reasons = [];

        foreach ($cards as $card) {
            if ($card['status'] !== 'active') {
                $reasons['inactive'] = 'The rate card for ' . $place . ' is not active.';
                continue;
            }
            if ((string) $card['effective_from'] > $today) {
                $reasons['future'] = 'The rate card for ' . $place . ' only starts on ' . $card['effective_from'] . '.';
                continue;
            }
            if ($card['effective_to'] !== null && (string) $card['effective_to'] < $today) {
                $reasons['ended'] = 'The rate card for ' . $place . ' ended on ' . $card['effective_to'] . '.';
                continue;
            }
            if ($card['customer_id'] !== null && (int) $card['customer_id'] !== (int) $customerId) {
                $reasons['other'] = 'The only rate card for ' . $place . ' belongs to another customer.';
                continue;
            }

            // In force, open to this customer, and still nothing matched: the
            // destination is not what disagrees.
            return 'The rate card for ' . $place . ' starts from a different depot than the one on this record.';
        }

        foreach (['future', 'ended', 'other', 'inactive'] as $reason) {
            if (isset($reasons[$reason])) {
                return $reasons[$reason];
            }
        }

        return 'No rate card covers ' . $place . ' yet.';
    }
    public static function quoteRequest(int $requestId, array $before = []): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM transport_requests WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$requestId]);
        $request = $statement->fetch(PDO::FETCH_ASSOC);

        if ($request === false) {
            return null;
        }

        // Once a customer has accepted a price, it is theirs. Editing the notes
        // on the request must not requote it. A request that never got a price
        // is not protected by this — there is nothing there to protect, and the
        // reason it failed is usually fixed minutes later.
        if ($request['accepted_at'] !== null && $request['quoted_amount'] !== null) {
            return ['amount' => (float) $request['quoted_amount'], 'basis' => (string) $request['quote_basis']];
        }

        $changed = $before === []
            || (float) ($before['weight_kg'] ?? 0) !== (float) $request['weight_kg']
            || (int) ($before['destination_warehouse_id'] ?? 0) !== (int) $request['destination_warehouse_id']
            || (int) ($before['origin_warehouse_id'] ?? 0) !== (int) $request['origin_warehouse_id']
            || (int) ($before['customer_id'] ?? 0) !== (int) $request['customer_id'];

        if (!$changed && $request['quoted_amount'] !== null) {
            return ['amount' => (float) $request['quoted_amount'], 'basis' => (string) $request['quote_basis']];
        }

        $quote = self::quote(
            (string) $request['destination'],
            (float) $request['weight_kg'],
            $request['customer_id'] !== null ? (int) $request['customer_id'] : null,
            (string) $request['pickup_location'],
            '',
            '',
            $request['origin_warehouse_id'] !== null ? (int) $request['origin_warehouse_id'] : null,
            $request['destination_warehouse_id'] !== null ? (int) $request['destination_warehouse_id'] : null
        );

        $update = Database::connection()->prepare(
            'UPDATE transport_requests SET rate_card_id = ?, quoted_amount = ?, quote_basis = ? WHERE id = ?'
        );

        if ($quote === null) {
            $update->execute([null, null, self::noCardReason(
                (string) $request['destination'],
                $request['destination_warehouse_id'] !== null ? (int) $request['destination_warehouse_id'] : null,
                $request['customer_id'] !== null ? (int) $request['customer_id'] : null
            ), $requestId]);
            return null;
        }

        $update->execute([(int) $quote['card']['id'], $quote['amount'], mb_substr($quote['basis'], 0, 160), $requestId]);

        return ['amount' => $quote['amount'], 'basis' => $quote['basis']];
    }

    /**
     * What a trip is worth: every shipment on it at its own quoted price.
     *
     * This is what an invoice for the trip should come to, and Finance can price
     * the invoice from it rather than working the loads out again.
     *
     * @return array{rows: list<array>, total: float}
     */
    public static function tripValue(int $tripId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT shipment_code, destination, weight_kg, quoted_amount, quote_basis
               FROM shipments
              WHERE trip_id = ? AND deleted_at IS NULL AND status <> "cancelled"
              ORDER BY id'
        );
        $statement->execute([$tripId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'total' => round(array_sum(array_map(static fn (array $r): float => (float) $r['quoted_amount'], $rows)), 2),
        ];
    }

    private static function rateTypeWords(string $type): string
    {
        return match ($type) {
            'per_trip' => 'a flat price for the trip',
            'per_day' => 'a daily rate',
            'per_kg' => 'by weight',
            'per_m3' => 'by volume',
            'per_package' => 'by the package',
            default => $type,
        };
    }

    /**
     * An amount in the money the card is written in.
     *
     * Settings::money() formats in the company base currency, which would have
     * a Nairobi card quoted in shillings explaining itself in francs.
     */
    private static function inCardMoney(float $amount, array $card): string
    {
        $code = strtoupper(trim((string) ($card['currency'] ?? '')));

        return $code === '' || $code === Currency::base()
            ? Settings::money($amount)
            : Currency::format($amount, $code);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }
}
