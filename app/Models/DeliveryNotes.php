<?php

declare(strict_types=1);

namespace Models;

/**
 * The delivery note, F8 (section 13), filled in the driver's app at the
 * customer: the trip, truck, cargo and quantity loaded come already filled;
 * the driver enters the quantity delivered and photographs the customer's
 * signed or stamped copy. The shortage is loaded minus delivered. The OM
 * checks it; Finance can invoice the same day.
 */
final class DeliveryNotes
{
    /**
     * @param array<string, mixed> $data loaded, delivered, received_by, note
     */
    public static function submit(int $tripId, array $data, ?string $photo): int
    {
        $trip = TripLife::find($tripId) ?? throw new \RuntimeException('That trip does not exist.');
        if (!in_array($trip['status'], ['offloading', 'on_road', 'at_turnaround'], true)) {
            throw new \RuntimeException('The delivery note is filled at the customer, while offloading.');
        }
        $loaded = (float) str_replace(',', '', (string) ($data['loaded'] ?? $trip['loaded_quantity'] ?? 0));
        $delivered = (float) str_replace(',', '', (string) ($data['delivered'] ?? ''));
        $problems = [];
        if ($loaded <= 0) {
            $problems[] = 'Enter the quantity loaded.';
        }
        if (($data['delivered'] ?? '') === '' || $delivered < 0) {
            $problems[] = 'Enter the quantity delivered.';
        }
        if ($delivered > $loaded && $loaded > 0) {
            $problems[] = 'More cannot be delivered than was loaded.';
        }
        if ($photo === null) {
            $problems[] = 'Take a photo of the customer\'s signed or stamped copy.';
        }
        if (trim((string) ($data['received_by'] ?? '')) === '') {
            $problems[] = 'Write who received the cargo.';
        }
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $shortage = round(max(0.0, $loaded - $delivered), 3);
        $unit = match ((string) $trip['cargo_type']) {
            'litres' => 'L',
            'tonnes' => 't',
            'containers' => 'container',
            default => 'unit',
        };
        $requestId = RequestForms::create([
            'form_code' => 'F8',
            'company_id' => (int) $trip['company_id'],
            'title' => sprintf('Delivery note %s — %s', $trip['reference_code'], $trip['customer_name'] ?: 'customer'),
            'trip_id' => $tripId,
            'vehicle_id' => (int) $trip['vehicle_id'],
            'driver_id' => (int) $trip['driver_id'],
            'customer_id' => $trip['customer_id'],
            'payload' => [
                'loaded' => $loaded, 'delivered' => $delivered, 'shortage' => $shortage, 'unit' => $unit,
                'received_by' => trim((string) $data['received_by']), 'photo' => $photo, 'attachments' => [$photo],
                'lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null,
            ],
            'notes' => trim((string) ($data['note'] ?? '')),
        ], [[
            'item_code' => 'other', 'description' => trim(($trip['cargo_summary'] ?: 'Cargo') . ($trip['container_numbers'] ? ' ' . $trip['container_numbers'] : '')),
            'quantity' => $delivered, 'unit' => $unit, 'trip_id' => $tripId, 'vehicle_id' => (int) $trip['vehicle_id'], 'driver_id' => (int) $trip['driver_id'],
            'extra' => ['loaded' => $loaded, 'shortage' => $shortage],
        ]]);
        RequestForms::send($requestId);
        TripLife::deliveryDone($tripId, $delivered);
        \Core\Database::connection()->prepare('UPDATE trips SET loaded_quantity = COALESCE(loaded_quantity, ?) WHERE id = ?')->execute([$loaded, $tripId]);

        if ($shortage > 0) {
            Notifier::forCompany((int) $trip['company_id'], static fn () => Notifier::toRoles(['logistics_manager', 'finance'], 'Delivery note with shortage', sprintf('%s (%s, %s): loaded %s, delivered %s, short %s %s.', $trip['reference_code'], $trip['plate_number'], $trip['driver_name'], self::n($loaded), self::n($delivered), self::n($shortage), $unit), 'form_requests', 'warning', 'form_requests', (string) $requestId));
        }

        return $requestId;
    }

    private static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3), '0'), '.');
    }
}
