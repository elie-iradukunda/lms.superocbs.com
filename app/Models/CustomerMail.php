<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * What the customer hears, and when.
 *
 * Every message the system sent went to somebody inside the company. The person
 * who is actually waiting for the goods heard nothing at all, so they rang the
 * office instead — which is the cost this removes.
 *
 * Four moments, which are the four a customer asks about:
 *
 *   quoted        here is the price, and how it was worked out
 *   on the way    the truck has left, with the driver and the plate
 *   arrived       it is at the depot and can be collected
 *   handed over   somebody signed for it, and this is the receipt
 *
 * Nothing is sent to a customer with no email address on file, and nothing is
 * sent twice: each message carries a key made from the record it is about.
 */
final class CustomerMail
{
    /** The price, and the working behind it. */
    public static function quote(int $requestId): bool
    {
        $request = self::row(
            'SELECT r.*, c.customer_name, c.contact_name, c.email,
                    wo.warehouse_name AS from_depot, wd.warehouse_name AS to_depot
               FROM transport_requests r
               LEFT JOIN customers c ON c.id = r.customer_id
               LEFT JOIN warehouses wo ON wo.id = r.origin_warehouse_id
               LEFT JOIN warehouses wd ON wd.id = r.destination_warehouse_id
              WHERE r.id = ?',
            $requestId
        );

        if ($request === null || trim((string) $request['email']) === '' || $request['quoted_amount'] === null) {
            return false;
        }

        $from = self::place($request, 'from_depot', 'pickup_location');
        $to = self::place($request, 'to_depot', 'destination');
        $currency = (string) ($request['currency'] ?: Currency::base());
        $holdsUntil = date('j F Y', strtotime('+30 days'));

        return self::send($request, [
            'key' => 'quote-' . $requestId,
            'subject' => sprintf('Quotation for transport, %s to %s', $from, $to),
            'heading' => sprintf('Transport quotation — %s to %s', $from, $to),
            'opening' => sprintf(
                'Thank you for your enquiry. We are pleased to offer the following terms for carrying your goods from %s to %s.',
                $from,
                $to
            ),
            'items' => [
                'title' => 'Our offer',
                'columns' => ['detail' => 'Detail', 'value' => 'Value'],
                'numeric' => [],
                'rows' => [
                    ['detail' => 'Goods to be carried', 'value' => (string) ($request['cargo_description'] ?: 'As described in your enquiry')],
                    ['detail' => 'Weight', 'value' => self::kg((float) $request['weight_kg'])],
                    ['detail' => 'Collection from', 'value' => $from],
                    ['detail' => 'Delivery to', 'value' => $to],
                    ['detail' => 'Required by', 'value' => date('j F Y', (int) strtotime((string) $request['required_date']))],
                ],
                'totals' => ['Total price' => Currency::format((float) $request['quoted_amount'], $currency)],
            ],
            // No internal data block: a customer wants the offer and the terms,
            // not the reference the office files it under. The reference goes in
            // the closing line, where it is useful to them rather than to us.
            'facts' => [],
            'closing' => [
                sprintf(
                    'The price above is for the weight shown and includes carriage from %s to %s. A heavier load is quoted again, since it takes more of the vehicle.',
                    $from,
                    $to
                ),
                sprintf('This quotation is open for acceptance until %s.', $holdsUntil),
                sprintf('To accept, simply reply to this message. Please quote reference %s in any correspondence.', $request['reference_code']),
            ],
            'what' => 'quotation',
            'entity' => 'requests',
        ]);
    }

    /** The truck has left, with the details a customer wants to be able to repeat. */
    public static function dispatched(int $tripId): int
    {
        $sent = 0;

        foreach (self::shipmentsOn($tripId) as $shipment) {
            $collect = self::place($shipment, 'to_depot', 'destination');
            $arrival = trim((string) $shipment['planned_arrival_at']) !== ''
                ? date('l j F Y, H:i', (int) strtotime((string) $shipment['planned_arrival_at']))
                : 'to be confirmed';

            $ok = self::send($shipment, [
                'key' => 'ontheway-' . $shipment['id'],
                'subject' => sprintf('Your consignment %s has left for %s', $shipment['shipment_code'], $collect),
                'heading' => sprintf('Your goods are on the way to %s', $collect),
                'opening' => sprintf(
                    'We are writing to confirm that your consignment left %s today and is now in transit to %s. The details are set out below for your records.',
                    self::place($shipment, 'from_depot', 'origin'),
                    $collect
                ),
                'items' => [
                    [
                        'title' => 'Your consignment',
                        'columns' => ['detail' => 'Detail', 'value' => 'Value'],
                        'numeric' => [],
                        'rows' => array_values(array_filter([
                            ['detail' => 'Consignment number', 'value' => (string) $shipment['shipment_code']],
                            ['detail' => 'Goods', 'value' => (string) $shipment['cargo_description']],
                            ['detail' => 'Weight', 'value' => self::kg((float) $shipment['weight_kg'])],
                            (int) $shipment['packages_count'] > 0
                                ? ['detail' => 'Packages', 'value' => number_format((int) $shipment['packages_count'])]
                                : null,
                            ['detail' => 'Collected from', 'value' => self::place($shipment, 'from_depot', 'origin')],
                            ['detail' => 'Delivering to', 'value' => $collect],
                        ])),
                    ],
                    [
                        'title' => 'Carriage',
                        'columns' => ['detail' => 'Detail', 'value' => 'Value'],
                        'numeric' => [],
                        'rows' => [
                            ['detail' => 'Vehicle', 'value' => (string) ($shipment['plate_number'] ?: 'To be confirmed')],
                            ['detail' => 'Driver', 'value' => (string) ($shipment['driver_name'] ?: 'To be confirmed')],
                            ['detail' => 'Expected to arrive', 'value' => $arrival],
                        ],
                    ],
                ],
                'facts' => [],
                'closing' => [
                    sprintf(
                        'We will write to you again the moment your goods reach %s, so that you can arrange collection. Please do not travel to the depot before that message.',
                        $collect
                    ),
                    sprintf('Please quote consignment number %s in any correspondence about this load.', $shipment['shipment_code']),
                    'Thank you for your business.',
                ],
                'what' => 'notice',
                'entity' => 'shipments',
            ]);

            $sent += $ok ? 1 : 0;
        }

        return $sent;
    }

    /** It is at the depot. This is the one that stops the phone ringing. */
    public static function arrived(int $deliveryId): bool
    {
        $delivery = self::deliveryRow($deliveryId);
        if ($delivery === null) {
            return false;
        }

        $where = self::place($delivery, 'to_depot', 'destination');

        return self::send($delivery, [
            'key' => 'arrived-' . $deliveryId,
            'subject' => sprintf('Ready for collection at %s — %s', $where, $delivery['delivery_code']),
            'heading' => 'Ready for collection',
            'opening' => sprintf(
                'Your goods have arrived at %s and are ready for you to collect. Please bring identification, and quote the reference below.',
                $where
            ),
            'items' => [
                'title' => 'Waiting for you',
                'columns' => ['detail' => 'Detail', 'value' => 'Value'],
                'numeric' => [],
                'rows' => [
                    ['detail' => 'Reference', 'value' => (string) $delivery['delivery_code']],
                    ['detail' => 'Goods', 'value' => (string) ($delivery['cargo_description'] ?: 'As consigned')],
                    ['detail' => 'Collect from', 'value' => $where],
                    ['detail' => 'Arrived', 'value' => date('Y-m-d H:i')],
                ],
            ],
            'facts' => array_filter([
                'Depot address' => (string) ($delivery['depot_location'] ?? ''),
                'Depot phone' => (string) ($delivery['depot_phone'] ?? ''),
            ]),
            'closing' => ['If somebody else will collect on your behalf, tell us their name beforehand so we can release the goods to them.'],
            'what' => 'collection notice',
            'entity' => 'deliveries',
        ]);
    }

    /** Somebody signed for it. The customer's copy of that. */
    public static function handedOver(int $deliveryId): bool
    {
        $delivery = self::deliveryRow($deliveryId);
        if ($delivery === null) {
            return false;
        }

        return self::send($delivery, [
            'key' => 'handedover-' . $deliveryId,
            'subject' => sprintf('Collected — %s', $delivery['delivery_code']),
            'heading' => 'Goods handed over',
            'opening' => 'This confirms that the goods below have been handed over. Thank you for your business.',
            'items' => [
                'title' => 'Handed over',
                'columns' => ['detail' => 'Detail', 'value' => 'Value'],
                'numeric' => [],
                'rows' => [
                    ['detail' => 'Reference', 'value' => (string) $delivery['delivery_code']],
                    ['detail' => 'Goods', 'value' => (string) ($delivery['cargo_description'] ?: 'As consigned')],
                    ['detail' => 'Received by', 'value' => (string) ($delivery['collected_by'] ?: $delivery['recipient_name'])],
                    ['detail' => 'On', 'value' => (string) ($delivery['delivered_at'] ?: date('Y-m-d H:i'))],
                ],
            ],
            'facts' => ['Proof of delivery' => $delivery['proof_file'] !== null && $delivery['proof_file'] !== '' ? 'Held on file' : 'Being filed'],
            'closing' => ['Our invoice for this consignment follows separately.'],
            'what' => 'confirmation',
            'entity' => 'deliveries',
        ]);
    }

    // ------------------------------------------------------------- plumbing

    /**
     * Returns the sentence to show whoever pressed the button.
     *
     * "Sent", "in the outbox" and "no address on file" are three different
     * outcomes, and telling somebody the wrong one is worse than saying nothing:
     * they will assume the customer knows when the customer does not.
     */
    private static function say(bool $queued, bool $sent, string $address, string $what): string
    {
        if ($address === '') {
            return sprintf(' No email address is on file for that customer, so no %s was sent.', $what);
        }
        if ($sent) {
            return sprintf(' The %s was emailed to %s.', $what, $address);
        }
        if ($queued) {
            return sprintf(' The %s for %s is in the outbox and will go out on the next send.', $what, $address);
        }

        return sprintf(' The %s could not be queued; check Administration, Email outbox.', $what);
    }

    private static function send(array $who, array $message): bool
    {
        $address = trim((string) ($who['email'] ?? ''));
        if ($address === '') {
            return false;
        }

        $name = trim((string) ($who['contact_name'] ?: $who['customer_name']));

        $result = Mailer::send([
            'key' => $message['key'],
            'category' => 'notification',
            'to' => $address,
            'to_name' => $name,
            'subject' => $message['subject'],
            'heading' => $message['heading'],
            'lines' => [sprintf('Dear %s,', $name !== '' ? $name : 'Customer'), $message['opening']],
            'items' => $message['items'] ?? null,
            'facts' => $message['facts'] ?? [],
            'closing' => $message['closing'] ?? [],
            // Customer mail is a letter from the company, and letters may be
            // answered. The internal signature tells staff not to reply.
            'signature' => sprintf('Sent by %s. You may reply to this message and it will reach us.', \company_name()),
            'entity_type' => $message['entity'],
            'entity_id' => (string) ($who['reference_code'] ?? $who['shipment_code'] ?? $who['delivery_code'] ?? ''),
        ]);

        self::$lastOutcome = self::say((bool) $result['queued'], (bool) $result['sent'], $address, $message['what'] ?? 'message');

        return (bool) $result['queued'];
    }

    /** How the last message went, in a sentence, for the flash the user sees. */
    private static string $lastOutcome = '';

    public static function outcome(): string
    {
        $outcome = self::$lastOutcome;
        self::$lastOutcome = '';

        return $outcome;
    }

    private static function deliveryRow(int $id): ?array
    {
        return self::row(
            'SELECT d.*, c.customer_name, c.contact_name, c.email,
                    s.cargo_description,
                    w.warehouse_name AS to_depot, w.location AS depot_location, w.phone AS depot_phone
               FROM deliveries d
               LEFT JOIN shipments s ON s.id = d.shipment_id
               LEFT JOIN customers c ON c.id = s.customer_id
               LEFT JOIN warehouses w ON w.id = d.destination_warehouse_id
              WHERE d.id = ? AND d.deleted_at IS NULL',
            $id
        );
    }

    private static function shipmentsOn(int $tripId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, c.customer_name, c.contact_name, c.email,
                    t.reference_code AS trip_code, t.planned_arrival_at,
                    v.plate_number, dr.full_name AS driver_name,
                    wo.warehouse_name AS from_depot, wd.warehouse_name AS to_depot
               FROM shipments s
               INNER JOIN trips t ON t.id = s.trip_id
               LEFT JOIN customers c ON c.id = s.customer_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN drivers dr ON dr.id = t.driver_id
               LEFT JOIN warehouses wo ON wo.id = s.origin_warehouse_id
               LEFT JOIN warehouses wd ON wd.id = s.destination_warehouse_id
              WHERE s.trip_id = ? AND s.deleted_at IS NULL AND s.status <> "cancelled"'
        );
        $statement->execute([$tripId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function row(string $sql, int $id): ?array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** The depot's name when there is one, otherwise whatever was typed. */
    private static function place(array $row, string $depotColumn, string $textColumn): string
    {
        $depot = trim((string) ($row[$depotColumn] ?? ''));

        return $depot !== '' ? $depot : (string) ($row[$textColumn] ?? '');
    }

    private static function kg(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, '.', ','), '0'), '.') . ' kg';
    }
}
