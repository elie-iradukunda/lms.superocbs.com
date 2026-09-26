<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/** The readiness and driver-visible document pack for one shipment. */
final class DocumentPack
{
    /** @return list<string> */
    public static function requiredFor(array $shipment): array
    {
        $required = ['Commercial invoice', 'Packing list', 'Transport document', 'Insurance'];
        if (!empty($shipment['trip_id'])) {
            $required[] = 'Customs declaration';
            $required[] = 'Certificate of origin';
        }
        if (in_array((string) ($shipment['cargo_type'] ?? ''), ['hazardous', 'perishable'], true) || !empty($shipment['is_hazardous'])) {
            $required[] = 'Import/Export permit';
        }

        return $required;
    }

    /** @return list<string> */
    public static function missingForShipment(array $shipment): array
    {
        $statement = Database::connection()->prepare("SELECT document_type FROM shipment_documents WHERE shipment_id = ? AND status = 'valid'");
        $statement->execute([(int) $shipment['id']]);
        $available = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_diff(self::requiredFor($shipment), $available));
    }

    public static function dispatchGuard(int $tripId): ?string
    {
        $db = Database::connection();
        $shipments = $db->prepare('SELECT * FROM shipments WHERE trip_id = ? AND deleted_at IS NULL');
        $shipments->execute([$tripId]);
        $rows = $shipments->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $shipment) {
            $started = $db->prepare('SELECT (SELECT COUNT(*) FROM shipment_documents WHERE shipment_id = ?) + (SELECT COUNT(*) FROM shipment_pre_dispatch_checks WHERE shipment_id = ?)');
            $started->execute([(int) $shipment['id'], (int) $shipment['id']]);
            // Existing domestic work predates the document-pack process. Once
            // operations starts a pack or a final check, it becomes mandatory.
            if ((int) $started->fetchColumn() === 0) {
                continue;
            }
            $missing = self::missingForShipment($shipment);
            if ($missing !== []) {
                return sprintf('%s is missing valid documents: %s.', $shipment['shipment_code'], implode(', ', $missing));
            }
            $check = $db->prepare("SELECT status FROM shipment_pre_dispatch_checks WHERE shipment_id = ?");
            $check->execute([(int) $shipment['id']]);
            if ($check->fetchColumn() !== 'ready') {
                return sprintf('%s has not passed the final pre-dispatch check.', $shipment['shipment_code']);
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function forDriver(int $shipmentId, int $driverId): ?array
    {
        $db = Database::connection();
        $shipment = $db->prepare(
            'SELECT s.id, s.trip_id, s.cargo_type, s.is_hazardous, s.shipment_code, s.origin, s.destination, s.cargo_description, t.reference_code AS trip, v.plate_number
               FROM shipments s INNER JOIN trips t ON t.id = s.trip_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
              WHERE s.id = ? AND t.driver_id = ? AND s.deleted_at IS NULL'
        );
        $shipment->execute([$shipmentId, $driverId]);
        $header = $shipment->fetch(PDO::FETCH_ASSOC);
        if ($header === false) {
            return null;
        }

        $docs = $db->prepare('SELECT document_type, document_number, status, document_file FROM shipment_documents WHERE shipment_id = ? ORDER BY document_type');
        $docs->execute([$shipmentId]);
        $vehicleDocs = $db->prepare("SELECT document_type, document_number, status, document_file FROM vehicle_documents WHERE vehicle_id = (SELECT vehicle_id FROM trips WHERE id = (SELECT trip_id FROM shipments WHERE id = ?)) AND deleted_at IS NULL ORDER BY document_type");
        $vehicleDocs->execute([$shipmentId]);
        $driverDocs = $db->prepare('SELECT document_type, document_number, status, document_file FROM driver_documents WHERE driver_id = ? ORDER BY document_type');
        $driverDocs->execute([$driverId]);

        return [
            'shipment' => $header,
            'required' => self::requiredFor($header),
            'documents' => self::withOpenUrls($docs->fetchAll(), 'shipment_documents'),
            'vehicle_documents' => self::withOpenUrls($vehicleDocs->fetchAll(), 'vehicle_documents'),
            'driver_documents' => self::withOpenUrls($driverDocs->fetchAll(), 'driver_documents'),
        ];
    }

    /** @param list<array<string, mixed>> $documents */
    private static function withOpenUrls(array $documents, string $module): array
    {
        foreach ($documents as &$document) {
            $file = basename((string) ($document['document_file'] ?? ''));
            $document['open_url'] = $file === '' ? null : \url(['files', $module, $file]);
        }
        unset($document);

        return $documents;
    }
}
