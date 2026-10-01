<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Everything a driver needs on the road, on one screen: the trips he has, the
 * route, who signs for each load, and every paper he must be able to show at a
 * port, a weighbridge or a border — shipment documents, customs documents, his
 * truck's insurance and inspection, and his own licence and passport.
 *
 * Only his own trips, so a driver never sees another driver's work.
 */
final class DriverWorkspace
{
    /** @return list<array<string, mixed>> */
    public static function trips(int $driverId): array
    {
        $db = Database::connection();

        $trips = $db->prepare(
            "SELECT t.id, t.reference_code, t.status, t.pickup_location, t.destination, t.planned_departure_at, t.planned_arrival_at,
                    t.departure_at, t.cargo_summary, t.vehicle_id, v.plate_number, v.make, v.model, c.customer_name
               FROM trips t
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN customers c ON c.id = t.customer_id
              WHERE t.driver_id = ? AND t.deleted_at IS NULL
                AND (t.status IN ('requested','approved','loading','in_transit') OR t.completed_at >= DATE_SUB(NOW(), INTERVAL 3 DAY))
              ORDER BY FIELD(t.status, 'in_transit', 'loading', 'approved', 'requested', 'delivered'), t.planned_departure_at, t.id
              LIMIT 6"
        );
        $trips->execute([$driverId]);
        $out = $trips->fetchAll(PDO::FETCH_ASSOC);

        foreach ($out as &$trip) {
            $id = (int) $trip['id'];
            $trip['stops'] = self::all('SELECT stop_sequence, stop_type, location_name, contact_name, contact_phone, planned_arrival_at, status FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence', [$id]);
            $trip['shipments'] = self::all(
                "SELECT id, shipment_code, cargo_description, packages_count, weight_kg, container_no, container_size, seal_no, trailer_plate,
                        consignee_name, consignee_phone, destination, special_instructions, status
                   FROM shipments WHERE trip_id = ? AND deleted_at IS NULL ORDER BY id",
                [$id]
            );
            foreach ($trip['shipments'] as &$shipment) {
                $shipment['documents'] = self::withUrls(self::all(
                    'SELECT document_type, document_number, expiry_date, status, document_file FROM shipment_documents WHERE shipment_id = ? ORDER BY document_type',
                    [(int) $shipment['id']]
                ), 'shipment_documents');
                $shipment['missing'] = DocumentPack::missingForShipment($shipment + ['trip_id' => $id, 'cargo_type' => '', 'is_hazardous' => 0]);
            }
            unset($shipment);

            $trip['crossings'] = self::all(
                "SELECT c.id, c.reference, c.status, c.declaration_no, c.seal_no, bp.post_name
                   FROM border_crossings c
                   INNER JOIN border_posts bp ON bp.id = c.border_post_id
                   LEFT JOIN shipments s ON s.id = c.shipment_id
                  WHERE (c.trip_id = ? OR s.trip_id = ?) AND c.deleted_at IS NULL
                  ORDER BY c.id",
                [$id, $id]
            );
            foreach ($trip['crossings'] as &$crossing) {
                $crossing['documents'] = self::withUrls(self::all(
                    'SELECT document_type, document_no AS document_number, expires_on AS expiry_date, status, document_file FROM border_documents WHERE crossing_id = ? ORDER BY id',
                    [(int) $crossing['id']]
                ), 'clearance_documents');
            }
            unset($crossing);

            $trip['vehicle_documents'] = $trip['vehicle_id'] === null ? [] : self::withUrls(self::all(
                "SELECT document_type, document_number, expires_on AS expiry_date, status, document_file
                   FROM vehicle_documents WHERE vehicle_id = ? AND deleted_at IS NULL AND status <> 'cancelled' ORDER BY document_type",
                [(int) $trip['vehicle_id']]
            ), 'vehicle_documents');

            $trip['deliveries'] = self::all(
                'SELECT id, delivery_code, recipient_name, recipient_phone, destination, status FROM deliveries WHERE trip_id = ? AND deleted_at IS NULL ORDER BY id',
                [$id]
            );
        }
        unset($trip);

        return $out;
    }

    /** The driver's own papers: licence, passport, permits. */
    public static function ownDocuments(int $driverId): array
    {
        $driver = self::all('SELECT license_number, license_class, license_expiry FROM drivers WHERE id = ?', [$driverId])[0] ?? null;
        $documents = self::withUrls(self::all(
            'SELECT document_type, document_number, expiry_date, status, document_file FROM driver_documents WHERE driver_id = ? ORDER BY document_type',
            [$driverId]
        ), 'driver_documents');

        return ['licence' => $driver, 'documents' => $documents];
    }

    private static function all(string $sql, array $params): array
    {
        try {
            $statement = Database::connection()->prepare($sql);
            $statement->execute($params);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            // A table from a later update that is not installed yet must not
            // take the driver's whole dashboard down with it.
            return [];
        }
    }

    private static function withUrls(array $documents, string $module): array
    {
        foreach ($documents as &$document) {
            $file = basename((string) ($document['document_file'] ?? ''));
            $document['open_url'] = $file === '' ? null : \url(['files', $module, $file]);
        }
        unset($document);

        return $documents;
    }
}
