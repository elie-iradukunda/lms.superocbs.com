<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * One customer movement that takes more than one truck.
 *
 * Dar es Salaam to Kigali often runs as two legs: a Tanzanian truck to Rusumo,
 * a Rwandan one from Rusumo. Each leg is an ordinary trip with its own truck,
 * driver, fuel and border paperwork. The journey ties them together, so the
 * cargo is handed from one to the next instead of being re-booked, the driver
 * of the second leg sees the papers, and the customer is billed once for the
 * whole move while the profit is read across every leg.
 */
final class Journey
{
    public const STATUS = ['planned' => 'Planned', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /** @return list<array<string, mixed>> the legs in order */
    public static function legs(int $journeyId): array
    {
        $statement = self::db()->prepare(
            "SELECT * FROM trips WHERE journey_id = ? AND deleted_at IS NULL ORDER BY COALESCE(leg_no, 99), id"
        );
        $statement->execute([$journeyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The leg after this one that can still take cargo, or null. */
    public static function nextLeg(array $trip): ?array
    {
        if (empty($trip['journey_id'])) {
            return null;
        }

        foreach (self::legs((int) $trip['journey_id']) as $leg) {
            if ((int) $leg['id'] === (int) $trip['id'] || in_array($leg['status'], ['cancelled', 'delivered'], true)) {
                continue;
            }
            if ((int) ($leg['leg_no'] ?? 0) > (int) ($trip['leg_no'] ?? 0)) {
                return $leg;
            }
        }

        return null;
    }

    /**
     * Completing a leg marks whatever is still aboard as delivered. On a leg
     * that hands over to another truck, that would deliver the cargo at the
     * border, so it is refused until the cargo has been handed on.
     */
    public static function guardComplete(array $trip): ?string
    {
        try {
            $next = self::nextLeg($trip);
        } catch (\Throwable) {
            return null;
        }
        if ($next === null) {
            return null;
        }

        $aboard = (int) self::scalar(
            "SELECT COUNT(*) FROM shipments WHERE trip_id = ? AND deleted_at IS NULL AND status NOT IN ('cancelled','delivered','returned')",
            [(int) $trip['id']]
        );

        return $aboard > 0
            ? sprintf('This leg still carries %d load%s for leg %d (%s). Press "Hand cargo to next leg" first, then complete it.',
                $aboard, $aboard === 1 ? '' : 's', (int) $next['leg_no'], $next['reference_code'])
            : null;
    }

    /**
     * Moves the cargo of one leg onto the next truck, keeping where it has been.
     *
     * @return array{ok: bool, message: string}
     */
    public static function handOver(int $tripId): array
    {
        $trip = self::row('SELECT * FROM trips WHERE id = ? AND deleted_at IS NULL', [$tripId]);
        if ($trip === null || empty($trip['journey_id'])) {
            return ['ok' => false, 'message' => 'This trip is not a leg of a journey.'];
        }

        $next = self::nextLeg($trip);
        if ($next === null) {
            return ['ok' => false, 'message' => 'There is no later leg to hand the cargo to. Add the next leg to the journey first.'];
        }

        $loads = self::rows(
            "SELECT id, shipment_code FROM shipments WHERE trip_id = ? AND deleted_at IS NULL AND status NOT IN ('cancelled','delivered','returned')",
            [$tripId]
        );
        if ($loads === []) {
            return ['ok' => false, 'message' => 'Nothing is aboard this leg to hand over.'];
        }

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }

        try {
            $point = (string) $trip['destination'];
            $leg = $db->prepare(
                'INSERT INTO shipment_legs (shipment_id, trip_id, leg_no, handed_over_at, handover_point) VALUES (?, ?, ?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE handed_over_at = NOW(), handover_point = VALUES(handover_point)'
            );
            $joins = $db->prepare('INSERT IGNORE INTO shipment_legs (shipment_id, trip_id, leg_no) VALUES (?, ?, ?)');
            $status = in_array($next['status'], ['in_transit'], true) ? 'in_transit' : 'loaded';
            $move = $db->prepare('UPDATE shipments SET trip_id = ?, status = ? WHERE id = ?');
            // The delivery belongs to whoever makes the last drop.
            $deliveries = $db->prepare("UPDATE deliveries SET trip_id = ? WHERE shipment_id = ? AND trip_id = ? AND status NOT IN ('delivered','failed')");

            foreach ($loads as $load) {
                $leg->execute([(int) $load['id'], $tripId, $trip['leg_no'], $point]);
                $joins->execute([(int) $load['id'], (int) $next['id'], $next['leg_no']]);
                $move->execute([(int) $next['id'], $status, (int) $load['id']]);
                $deliveries->execute([(int) $next['id'], (int) $load['id'], $tripId]);
            }

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('journey.handed_over', 'trips', (string) $trip['reference_code'], null, [
            'to' => $next['reference_code'], 'loads' => array_column($loads, 'shipment_code'),
        ]);
        if (!empty($next['driver_id'])) {
            $driverUser = (int) self::scalar('SELECT user_id FROM drivers WHERE id = ?', [(int) $next['driver_id']]);
            if ($driverUser > 0) {
                Notifier::toUser($driverUser, 'Cargo handed to you',
                    sprintf('%d load(s) from %s are now on your trip %s at %s. Check the papers on your dashboard.', count($loads), $trip['reference_code'], $next['reference_code'], $point),
                    'trips', 'info', 'trips', (string) $next['id']);
            }
        }

        return ['ok' => true, 'message' => sprintf('%d load%s handed from %s to %s (leg %d) at %s. You can now complete this leg.',
            count($loads), count($loads) === 1 ? '' : 's', $trip['reference_code'], $next['reference_code'], (int) $next['leg_no'], $point)];
    }

    /** The journey's state follows its legs. */
    public static function sync(int $journeyId): void
    {
        $journey = self::row('SELECT status FROM journeys WHERE id = ?', [$journeyId]);
        if ($journey === null || $journey['status'] === 'cancelled') {
            return;
        }

        $legs = array_filter(self::legs($journeyId), static fn (array $leg): bool => $leg['status'] !== 'cancelled');
        $states = array_column($legs, 'status');
        $status = match (true) {
            $legs !== [] && count(array_filter($states, static fn (string $s): bool => $s === 'delivered')) === count($states) => 'completed',
            array_intersect($states, ['loading', 'in_transit', 'delivered']) !== [] => 'in_progress',
            default => 'planned',
        };

        if ($status !== $journey['status']) {
            self::db()->prepare('UPDATE journeys SET status = ? WHERE id = ?')->execute([$status, $journeyId]);
        }
    }

    /** Syncs the journey a trip belongs to, if any. Never lets a journey problem stop a trip. */
    public static function syncForTrip(int $tripId): void
    {
        try {
            $journeyId = (int) self::scalar('SELECT journey_id FROM trips WHERE id = ?', [$tripId]);
            if ($journeyId > 0) {
                self::sync($journeyId);
            }
        } catch (\Throwable) {
            // Before journeys exist there is nothing to keep in step.
        }
    }

    /** The defaults for the next leg's trip form. */
    public static function nextLegDefaults(int $journeyId): ?array
    {
        $journey = self::row('SELECT * FROM journeys WHERE id = ? AND deleted_at IS NULL', [$journeyId]);
        if ($journey === null) {
            return null;
        }

        $legs = self::legs($journeyId);
        $last = $legs === [] ? null : $legs[count($legs) - 1];

        return [
            'journey_id' => $journey['id'],
            'leg_no' => count($legs) + 1,
            'customer_id' => $journey['customer_id'],
            'request_id' => $journey['request_id'],
            'trip_type' => 'delivery',
            'status' => 'requested',
            'pickup_location' => $last !== null ? $last['destination'] : $journey['origin'],
            'destination' => $journey['final_destination'],
        ];
    }

    private static function row(string $sql, array $parameters): ?array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function rows(string $sql, array $parameters): array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function scalar(string $sql, array $parameters): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
