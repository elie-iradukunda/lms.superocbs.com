<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Vehicle handover, F14 (section 6). A driver ends a trip only by handing the
 * truck over in the app: where it is parked, the odometer, the fuel level,
 * photos, and who took the key. The OM confirms it; the trip closes and the
 * truck is free for another driver.
 */
final class Handover
{
    public const FUEL_LEVELS = ['empty' => 'Empty', 'quarter' => '1/4', 'half' => '1/2', 'three_quarters' => '3/4', 'full' => 'Full'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function find(int $id): ?array
    {
        $statement = self::db()->prepare(
            'SELECT h.*, v.plate_number, d.full_name AS driver_name, t.reference_code AS trip_code
               FROM vehicle_handovers h
               INNER JOIN vehicles v ON v.id = h.vehicle_id
               LEFT JOIN drivers d ON d.id = h.driver_id
               LEFT JOIN trips t ON t.id = h.trip_id
              WHERE h.id = ? AND h.deleted_at IS NULL'
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * The driver hands the truck over.
     *
     * @param array<string, mixed> $data parked_at, odometer, fuel_level, key_received_by, notes, lat, lng
     * @param array<string, ?string> $photos side => stored file
     */
    public static function submit(int $vehicleId, ?int $tripId, ?int $driverId, array $data, array $photos): int
    {
        $vehicle = Fleet::vehicle($vehicleId) ?? throw new \RuntimeException('That truck does not exist.');
        $parked = trim((string) ($data['parked_at'] ?? ''));
        $odometer = (int) preg_replace('/\D/', '', (string) ($data['odometer'] ?? ''));
        $fuel = (string) ($data['fuel_level'] ?? '');
        $key = trim((string) ($data['key_received_by'] ?? ''));

        $problems = [];
        if ($parked === '') {
            $problems[] = 'Say where the truck is parked.';
        }
        if ($odometer <= 0) {
            $problems[] = 'Enter the odometer reading.';
        } elseif ($odometer < (int) $vehicle['mileage']) {
            $problems[] = sprintf('The odometer cannot be lower than the last reading (%s km).', number_format((int) $vehicle['mileage']));
        }
        if (!array_key_exists($fuel, self::FUEL_LEVELS)) {
            $problems[] = 'Choose the fuel level.';
        }
        if ($key === '') {
            $problems[] = 'Say who took the key.';
        }
        if (count(array_filter($photos)) < 2) {
            $problems[] = 'Take at least two photos of the truck.';
        }
        if ($tripId !== null) {
            $trip = TripLife::find($tripId);
            if ($trip === null || !in_array($trip['status'], ['returning', 'arrived', 'on_road', 'offloading'], true)) {
                $problems[] = 'This trip cannot be handed over now.';
            }
            $open = self::db()->prepare("SELECT COUNT(*) FROM vehicle_handovers WHERE trip_id = ? AND status = 'submitted' AND deleted_at IS NULL");
            $open->execute([$tripId]);
            if ((int) $open->fetchColumn() > 0) {
                $problems[] = 'A handover for this trip is already waiting for the OM.';
            }
        }
        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $companyId = (int) ($vehicle['company_id'] ?: \current_company_id());
        $code = Numbering::next($companyId, 'handover', Settings::get('handover_number_format', 'HO-{YEAR}-{SEQ4}'), 'year');
        self::db()->prepare(
            'INSERT INTO vehicle_handovers (company_id, handover_code, trip_id, vehicle_id, driver_id, parked_at, odometer, fuel_level, key_received_by,
                photo_front, photo_back, photo_left, photo_right, notes, latitude, longitude, submitted_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $companyId, $code, $tripId, $vehicleId, $driverId, $parked, $odometer, $fuel, $key,
            $photos['front'] ?? null, $photos['back'] ?? null, $photos['left'] ?? null, $photos['right'] ?? null,
            trim((string) ($data['notes'] ?? '')) ?: null,
            ($data['lat'] ?? '') !== '' ? (float) $data['lat'] : null, ($data['lng'] ?? '') !== '' ? (float) $data['lng'] : null,
            \current_user_id(),
        ]);
        $id = (int) self::db()->lastInsertId();

        // F14 in the chain: the OM confirms.
        $requestId = RequestForms::create([
            'form_code' => 'F14',
            'company_id' => $companyId,
            'title' => sprintf('Handover of %s at %s', $vehicle['plate_number'], $parked),
            'trip_id' => $tripId,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'payload' => ['handover_id' => $id],
        ], [[
            'item_code' => 'other', 'description' => sprintf('Parked at %s; odometer %s km; fuel %s; key with %s', $parked, number_format($odometer), self::FUEL_LEVELS[$fuel], $key),
            'vehicle_id' => $vehicleId, 'driver_id' => $driverId, 'trip_id' => $tripId, 'quantity' => 1,
        ]]);
        RequestForms::send($requestId);
        if ($tripId !== null) {
            TripLife::event($tripId, 'handover_submitted', 'driver', ['odometer' => $odometer, 'place' => $parked], 'Truck handed over: waiting for the OM');
        }
        AuditLog::record('handover.submitted', 'vehicle_handovers', $code);

        return $id;
    }

    /** The OM confirms: the trip closes from the odometer, and the truck is free. */
    public static function confirm(int $id): void
    {
        $handover = self::find($id) ?? throw new \RuntimeException('That handover does not exist.');
        if ($handover['status'] !== 'submitted') {
            return;
        }
        self::db()->prepare("UPDATE vehicle_handovers SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?")->execute([\current_user_id(), $id]);
        if (!empty($handover['trip_id'])) {
            TripLife::close((int) $handover['trip_id'], (int) $handover['odometer']);
        } else {
            self::db()->prepare('UPDATE vehicles SET mileage = GREATEST(mileage, ?), status = IF(status IN (\'assigned\', \'returning\'), \'available\', status) WHERE id = ?')
                ->execute([(int) $handover['odometer'], (int) $handover['vehicle_id']]);
        }
        AuditLog::record('handover.confirmed', 'vehicle_handovers', (string) $handover['handover_code']);
    }

    /** Rejected by the OM: the driver hands the truck over again, correctly. */
    public static function returned(array $request): void
    {
        $id = (int) ($request['payload']['handover_id'] ?? 0);
        if ($id > 0) {
            self::db()->prepare("UPDATE vehicle_handovers SET status = 'rejected', rejection_reason = ? WHERE id = ? AND status = 'submitted'")
                ->execute([$request['returned_reason'] ?? null, $id]);
        }
        // A rejected handover is done again from the app, not edited.
        RequestForms::cancel((int) $request['id'], 'Handover rejected: ' . ($request['returned_reason'] ?? ''));
    }
}
