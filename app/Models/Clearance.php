<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Customs clearance as a process the company runs before the truck leaves,
 * not something that happens to it at the border.
 *
 * Under the EAC Single Customs Territory the entry for Kigali is lodged and
 * assessed while the container is still at Mombasa or Dar es Salaam, and a
 * transit through Uganda or Tanzania is declared and bonded before departure.
 * So the rule here is simple: every border on the route has a clearance file,
 * every file has its checklist of papers, each paper is checked by the
 * clearance department, and the declaration is lodged, before the truck may
 * be released. At the border the file then only has to be cleared and
 * released, and the clock says how long that took.
 *
 * BorderCrossing keeps the file's own steps and its money; this class is what
 * ties the file to the trip.
 */
final class Clearance
{
    /** The statuses at which the declaration is in the customs system. */
    public const LODGED = ['declaration_submitted', 'under_review', 'inspection', 'duties_pending', 'payment_pending', 'cleared', 'released'];

    /** The stages the clearance board shows, in order. */
    public const STAGES = [
        'papers' => ['label' => 'Collecting papers', 'statuses' => ['draft', 'documents_pending']],
        'lodged' => ['label' => 'Lodged with customs', 'statuses' => ['declaration_submitted', 'under_review']],
        'inspection' => ['label' => 'Inspection', 'statuses' => ['inspection']],
        'duties' => ['label' => 'Duties and payment', 'statuses' => ['duties_pending', 'payment_pending']],
        'cleared' => ['label' => 'Cleared, waiting release', 'statuses' => ['cleared']],
    ];

    /** Papers that only exist once the truck is at the border, so they cannot hold it in the yard. */
    private const AT_THE_BORDER = ['Tax payment receipt', 'Weighbridge ticket', 'Customs release order'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    // ------------------------------------------------------------ checklist

    /**
     * The papers a clearance file needs, by direction and by what is in it.
     *
     * @return list<string>
     */
    public static function checklist(string $direction, ?array $shipment, string $country = ''): array
    {
        $kenya = stripos($country, 'kenya') !== false;
        $boxed = $shipment !== null && (trim((string) ($shipment['container_no'] ?? '')) !== '' || trim((string) ($shipment['bill_of_lading'] ?? '')) !== '');
        $controlled = $shipment !== null && (!empty($shipment['is_hazardous']) || in_array((string) ($shipment['cargo_type'] ?? ''), ['hazardous', 'perishable', 'cold_chain'], true));

        $papers = match ($direction) {
            'export' => ['Export declaration', 'Commercial invoice', 'Packing list', 'Certificate of origin'],
            'transit' => ['Customs declaration', 'T1 transit bond', 'Commercial invoice', 'Packing list'],
            default => ['Import declaration', 'Commercial invoice', 'Packing list', 'Certificate of origin', 'Tax payment receipt'],
        };
        if ($boxed) {
            $papers[] = 'Bill of lading';
            if ($direction !== 'export') {
                $papers[] = 'Delivery order';
            }
            if ($direction === 'transit') {
                $papers[] = 'Electronic cargo tracking seal';
            }
        }
        if ($controlled) {
            $papers[] = $direction === 'export' ? 'Phytosanitary certificate' : 'Import permit';
        }
        // Entering Kenya: the Import Declaration Form is lodged before the entry.
        if ($kenya && $direction === 'import') {
            array_splice($papers, 1, 0, ['Import Declaration Form (IDF)']);
        }

        return array_values(array_unique($papers));
    }

    /**
     * Opens a clearance file: the checklist rows it is missing are added as
     * pending, so the department sees at once what to collect. Run on every
     * save; it only ever adds.
     */
    public static function openFile(int $crossingId, bool $isNew = false): int
    {
        $crossing = BorderCrossing::load($crossingId);
        if ($crossing === null) {
            return 0;
        }
        $shipment = null;
        if (!empty($crossing['shipment_id'])) {
            $statement = self::db()->prepare('SELECT * FROM shipments WHERE id = ?');
            $statement->execute([(int) $crossing['shipment_id']]);
            $shipment = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $have = self::db()->prepare('SELECT document_type FROM border_documents WHERE crossing_id = ?');
        $have->execute([$crossingId]);
        $existing = array_map('strtolower', $have->fetchAll(PDO::FETCH_COLUMN));

        $insert = self::db()->prepare("INSERT INTO border_documents (crossing_id, document_type, status, is_received, notes) VALUES (?, ?, 'pending', 0, ?)");
        $added = 0;
        foreach (self::checklist((string) $crossing['direction'], $shipment, (string) ($crossing['customs_country'] ?? '')) as $paper) {
            if (in_array(strtolower($paper), $existing, true)) {
                continue;
            }
            $insert->execute([$crossingId, $paper, in_array($paper, self::AT_THE_BORDER, true) ? 'Obtained at the border.' : 'Needed before the truck leaves.']);
            $added++;
        }

        if ($isNew) {
            $post = self::postName((int) $crossing['border_post_id']);
            Notifier::toRole('clearance_officer', 'New clearance file: ' . $crossing['reference'], sprintf(
                '%s at %s (%s). %d paper%s to collect, check and lodge before the truck leaves.',
                $crossing['reference'], $post, ucfirst((string) $crossing['direction']), $added, $added === 1 ? '' : 's'
            ), 'crossings', 'info', 'crossings', (string) $crossingId);
        }

        return $added;
    }

    // ---------------------------------------------------- the trip and the border

    public static function tripCrossesBorder(int $tripId): bool
    {
        if ($tripId <= 0) {
            return false;
        }
        $stops = (int) self::scalar("SELECT COUNT(*) FROM trip_stops WHERE trip_id = ? AND stop_type = 'checkpoint'", [$tripId]);
        $files = (int) self::scalar("SELECT COUNT(*) FROM border_crossings WHERE trip_id = ? AND deleted_at IS NULL AND status <> 'rejected'", [$tripId]);

        return $stops + $files > 0;
    }

    public static function shipmentCrossesBorder(int $shipmentId): bool
    {
        if ($shipmentId <= 0) {
            return false;
        }
        if ((int) self::scalar("SELECT COUNT(*) FROM border_crossings WHERE shipment_id = ? AND deleted_at IS NULL AND status <> 'rejected'", [$shipmentId]) > 0) {
            return true;
        }

        return self::tripCrossesBorder((int) self::scalar('SELECT COALESCE(trip_id, 0) FROM shipments WHERE id = ?', [$shipmentId]));
    }

    /**
     * Every check on the trip's clearance, as readiness items.
     *
     * @return list<array{label: string, state: string, detail: string, more: array}>
     */
    public static function readiness(int $tripId): array
    {
        $items = [];
        $strict = Settings::int('clearance_before_dispatch', 1) === 1;
        $fail = $strict ? 'fail' : 'warn';

        $stops = self::db()->prepare("SELECT location_name FROM trip_stops WHERE trip_id = ? AND stop_type = 'checkpoint' ORDER BY stop_sequence, id");
        $stops->execute([$tripId]);
        $borders = $stops->fetchAll(PDO::FETCH_COLUMN);

        $files = self::db()->prepare(
            "SELECT bc.*, bp.post_name FROM border_crossings bc
               INNER JOIN border_posts bp ON bp.id = bc.border_post_id
              WHERE bc.trip_id = ? AND bc.deleted_at IS NULL AND bc.status <> 'rejected'
              ORDER BY bc.id"
        );
        $files->execute([$tripId]);
        $crossings = $files->fetchAll(PDO::FETCH_ASSOC);

        if ($borders === [] && $crossings === []) {
            return [];
        }

        // A border on the route with no file is the classic surprise.
        foreach ($borders as $border) {
            $covered = false;
            foreach ($crossings as $crossing) {
                if (BorderCrossing::sameBorder((string) $crossing['post_name'], (string) $border)) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $first = self::scalar('SELECT id FROM shipments WHERE trip_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$tripId]);
                $post = self::postIdFor((string) $border);
                $items[] = ['label' => 'Clearance at ' . $border, 'state' => $fail, 'detail' => sprintf('%s is on the route but has no clearance file. Open one and lodge it before the truck leaves.', $border),
                    'more' => ['open' => \url(['crossings', 'create'], array_filter(['trip_id' => $tripId, 'shipment_id' => $first, 'border_post_id' => $post])), 'open_label' => 'Open the clearance file']];
            }
        }

        foreach ($crossings as $crossing) {
            $label = sprintf('Clearance %s, %s', $crossing['reference'], $crossing['post_name']);
            $link = ['open' => \url(['crossings', $crossing['id']]), 'open_label' => 'Open ' . $crossing['reference']];
            $missing = array_values(array_diff(self::papersOutstanding((int) $crossing['id']), self::AT_THE_BORDER));
            $facts = [
                'Direction' => ucfirst((string) $crossing['direction']),
                'Status' => Schema::label((string) $crossing['status']),
                'Declaration' => (string) ($crossing['declaration_no'] ?: 'not lodged'),
                'T1 / bond' => (string) ($crossing['transit_bond_no'] ?: '—'),
                'Seal' => (string) ($crossing['seal_no'] ?: '—'),
            ];

            if ($missing !== []) {
                $items[] = ['label' => $label, 'state' => $fail, 'detail' => 'Papers not yet checked: ' . implode(', ', $missing) . '.', 'more' => $link + ['facts' => $facts]];
                continue;
            }
            if (!in_array((string) $crossing['status'], self::LODGED, true)) {
                $items[] = ['label' => $label, 'state' => $fail, 'detail' => sprintf('The papers are in, but the declaration is not lodged yet (%s). Submit it before the truck leaves.', Schema::label((string) $crossing['status'])), 'more' => $link + ['facts' => $facts]];
                continue;
            }
            if ((string) $crossing['direction'] === 'transit' && trim((string) $crossing['transit_bond_no']) === '') {
                $items[] = ['label' => $label, 'state' => $fail, 'detail' => 'A transit needs its T1 / regional guarantee (RCTG) number before the truck leaves.', 'more' => $link + ['facts' => $facts]];
                continue;
            }
            $boxed = trim((string) self::scalar('SELECT container_no FROM shipments WHERE id = ?', [(int) ($crossing['shipment_id'] ?? 0)])) !== '';
            if ($boxed && trim((string) $crossing['seal_no']) === '') {
                $items[] = ['label' => $label, 'state' => $fail, 'detail' => 'The container seal number is not on the clearance file.', 'more' => $link + ['facts' => $facts]];
                continue;
            }
            $items[] = ['label' => $label, 'state' => 'ok', 'detail' => sprintf('Lodged, declaration %s · %s', $crossing['declaration_no'], Schema::label((string) $crossing['status'])), 'more' => $link + ['facts' => $facts]];
        }

        return $items;
    }

    /** Why the truck may not leave because of its clearance, or null when it may. */
    public static function dispatchGuard(int $tripId): ?string
    {
        if (Settings::int('clearance_before_dispatch', 1) !== 1) {
            return null;
        }
        foreach (self::readiness($tripId) as $item) {
            if ($item['state'] === 'fail') {
                return $item['label'] . ': ' . $item['detail'];
            }
        }

        return null;
    }

    /** @return list<string> papers on the file that are not valid yet */
    public static function papersOutstanding(int $crossingId): array
    {
        return BorderCrossing::missingDocuments($crossingId);
    }

    // -------------------------------------------------------- what people hear

    /**
     * After a clearance file moves: the right people are told, and the driver
     * hears what concerns him at the border.
     */
    public static function afterStep(string $action, array $crossing, string $message): string
    {
        try {
            $code = (string) $crossing['reference'];
            $id = (string) $crossing['id'];
            $post = self::postName((int) $crossing['border_post_id']);
            $driverUser = !empty($crossing['driver_id']) ? (int) self::scalar('SELECT COALESCE(user_id, 0) FROM drivers WHERE id = ?', [(int) $crossing['driver_id']]) : 0;
            $driver = static function (string $title, string $text, string $severity) use ($driverUser, $code): void {
                if ($driverUser > 0) {
                    Notifier::toUser($driverUser, $title, $text, 'my-trips', $severity, 'crossings', $code);
                }
            };

            switch ($action) {
                case 'submit_declaration':
                    Notifier::bellToRole('logistics_manager', 'Declaration lodged', sprintf('%s at %s is lodged with customs (declaration %s).', $code, $post, $crossing['declaration_no']), 'crossings', 'success', 'crossings', $id);
                    break;
                case 'request_inspection':
                    Notifier::toRole('logistics_manager', 'Customs inspection', sprintf('Customs asked to inspect %s at %s.', $code, $post), 'crossings', 'warning', 'crossings', $id);
                    $driver('Customs inspection at ' . $post, 'Customs will inspect the load. Stay with the truck and keep the seal intact until they open it.', 'warning');
                    break;
                case 'request_payment':
                    $owed = BorderCrossing::outstandingByCurrency((int) $crossing['id']);
                    Notifier::toRole('finance', 'Duties to pay: ' . $code, sprintf('%s at %s is waiting for payment%s. Pay, then record the receipt on the clearance.', $code, $post, $owed !== '' ? ' of ' . $owed : ''), 'crossings', 'warning', 'crossings', $id);
                    break;
                case 'clear':
                    Notifier::bellToRole('logistics_manager', 'Cleared by customs', sprintf('%s at %s is cleared.', $code, $post), 'crossings', 'success', 'crossings', $id);
                    $driver('Cleared at ' . $post, 'Customs has cleared the load. Wait for the release, then press Leaving at the border.', 'success');
                    break;
                case 'release':
                    Notifier::bellToRole('logistics_manager', 'Released at the border', sprintf('%s is released at %s.', $code, $post), 'crossings', 'success', 'crossings', $id);
                    $driver('Released: you may go on', sprintf('The load is released at %s. Press Leaving on My trips as you drive off.', $post), 'success');
                    break;
                case 'reject':
                    Notifier::toRole('clearance_officer', 'Clearance held: ' . $code, sprintf('%s at %s is held.', $code, $post), 'crossings', 'danger', 'crossings', $id);
                    $driver('Held at ' . $post, 'The load is held by customs. Stay with the truck; the office is dealing with it.', 'danger');
                    break;
            }
        } catch (\Throwable) {
            // Telling people never undoes the step.
        }

        return $message;
    }

    /** A paper was checked, sent back or raised again. */
    public static function documentMoved(string $module, string $action, array $document, string $reason): string
    {
        $type = (string) $document['document_type'];
        if ($module === 'clearance_documents') {
            $crossing = BorderCrossing::load((int) $document['crossing_id']);
            $code = (string) ($crossing['reference'] ?? '');
            if ($action === 'verify' && $crossing !== null) {
                $db = self::db();
                $db->prepare('UPDATE border_documents SET is_received = 1 WHERE id = ?')->execute([(int) $document['id']]);
                $left = array_values(array_diff(self::papersOutstanding((int) $crossing['id']), self::AT_THE_BORDER));
                if ($left === [] && !in_array((string) $crossing['status'], self::LODGED, true)) {
                    Notifier::toRole('clearance_officer', 'Ready to lodge: ' . $code, sprintf('Every paper for %s is checked. Submit the declaration so the truck can be released.', $code), 'crossings', 'success', 'crossings', (string) $crossing['id']);
                }

                return sprintf('%s checked for %s.%s', $type, $code, $left === [] ? ' Every paper needed before departure is now in.' : ' Still to check: ' . implode(', ', $left) . '.');
            }
            if ($action === 'reject') {
                Notifier::toRoles(['clearance_officer', 'logistics_manager'], 'Paper sent back: ' . $type, sprintf('%s for %s was sent back: %s', $type, $code, $reason), 'clearance_documents', 'danger', 'clearance_documents', (string) $document['id']);

                return sprintf('%s sent back. Upload a corrected copy, then press Send again.', $type);
            }

            return sprintf('%s is waiting to be checked again.', $type);
        }

        $shipment = (string) self::scalar('SELECT shipment_code FROM shipments WHERE id = ?', [(int) $document['shipment_id']]);
        if ($action === 'verify') {
            $missing = DocumentPack::missingForShipment(['id' => (int) $document['shipment_id']] + (self::shipmentRow((int) $document['shipment_id']) ?? []));

            return sprintf('%s checked for %s.%s', $type, $shipment, $missing === [] ? ' The document pack is complete.' : ' Still missing: ' . implode(', ', $missing) . '.');
        }
        if ($action === 'reject') {
            if (!empty($document['uploaded_by'])) {
                Notifier::toUser((int) $document['uploaded_by'], 'Paper sent back: ' . $type, sprintf('%s for %s was sent back: %s', $type, $shipment, $reason), 'shipment_documents', 'danger', 'shipment_documents', (string) $document['id']);
            }
            Notifier::bellToRole('logistics_manager', 'Paper sent back: ' . $type, sprintf('%s for %s: %s', $type, $shipment, $reason), 'shipment_documents', 'danger', 'shipment_documents', (string) $document['id']);

            return sprintf('%s sent back. Upload a corrected copy, then press Send again.', $type);
        }

        return sprintf('%s is waiting to be checked again.', $type);
    }

    /**
     * Files that have not moved for too long: the department and operations
     * are told once a day per file.
     */
    public static function stuckAlerts(callable $raise): void
    {
        $hours = max(1, Settings::int('clearance_alert_hours', 24));
        $rows = self::db()->query(
            "SELECT bc.id, bc.reference, bc.status, bp.post_name,
                    GREATEST(COALESCE(bc.released_at, '2000-01-01'), COALESCE(bc.cleared_at, '2000-01-01'), COALESCE(bc.approved_at, '2000-01-01'),
                             COALESCE(bc.submitted_at, '2000-01-01'), COALESCE(bc.lodged_at, '2000-01-01'), COALESCE(bc.arrived_at, '2000-01-01'), bc.created_at) AS moved
               FROM border_crossings bc INNER JOIN border_posts bp ON bp.id = bc.border_post_id
              WHERE bc.deleted_at IS NULL AND bc.status NOT IN ('released','rejected')"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $idle = (time() - (int) strtotime((string) $row['moved'])) / 3600;
            if ($idle < $hours) {
                continue;
            }
            $raise(
                'clearance-idle-' . $row['id'] . '-' . date('Ymd'),
                ['clearance_officer', 'logistics_manager'],
                'Clearance not moving: ' . $row['reference'],
                sprintf('%s at %s has been %s for %d hours.', $row['reference'], $row['post_name'], Schema::label((string) $row['status']), (int) $idle),
                'crossings',
                'warning'
            );
        }
    }

    /**
     * Every open file by stage, for the clearance board.
     *
     * @return array<string, array{label: string, files: list<array<string, mixed>>}>
     */
    public static function board(): array
    {
        $board = [];
        foreach (self::STAGES as $key => $stage) {
            $board[$key] = ['label' => $stage['label'], 'files' => []];
        }
        try {
            $rows = self::db()->query(
                "SELECT bc.id, bc.reference, bc.status, bc.direction, bc.hold_reason, bc.declaration_no, bc.created_at, bc.arrived_at,
                        bp.post_name, v.plate_number, t.reference_code AS trip, t.status AS trip_status, s.shipment_code,
                        (SELECT COUNT(*) FROM border_documents d WHERE d.crossing_id = bc.id AND d.status <> 'valid') AS papers_open
                   FROM border_crossings bc
                   INNER JOIN border_posts bp ON bp.id = bc.border_post_id
                   LEFT JOIN vehicles v ON v.id = bc.vehicle_id
                   LEFT JOIN trips t ON t.id = bc.trip_id
                   LEFT JOIN shipments s ON s.id = bc.shipment_id
                  WHERE bc.deleted_at IS NULL AND bc.status NOT IN ('released','rejected')
                  ORDER BY bc.id"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return $board;
        }
        foreach ($rows as $row) {
            foreach (self::STAGES as $key => $stage) {
                if (in_array($row['status'], $stage['statuses'], true)) {
                    $board[$key]['files'][] = $row;
                    break;
                }
            }
        }

        return $board;
    }

    /** @return list<array<string, mixed>> trips that cannot leave because of their clearance */
    public static function blockedTrips(int $limit = 10): array
    {
        $out = [];
        try {
            $trips = self::db()->query("SELECT id, reference_code, pickup_location, destination, planned_departure_at FROM trips WHERE deleted_at IS NULL AND status IN ('requested','approved','loading') ORDER BY planned_departure_at IS NULL, planned_departure_at, id LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($trips as $trip) {
                foreach (self::readiness((int) $trip['id']) as $item) {
                    if ($item['state'] !== 'ok') {
                        $out[] = $trip + ['problem' => $item['label'] . ': ' . $item['detail']];
                        break;
                    }
                }
                if (count($out) >= $limit) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    // ------------------------------------------------------------- helpers

    private static function postName(int $postId): string
    {
        return (string) (self::scalar('SELECT post_name FROM border_posts WHERE id = ?', [$postId]) ?: 'the border');
    }

    private static function postIdFor(string $place): ?int
    {
        foreach (self::db()->query('SELECT id, post_name FROM border_posts WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $post) {
            if (BorderCrossing::sameBorder((string) $post['post_name'], $place)) {
                return (int) $post['id'];
            }
        }

        return null;
    }

    private static function shipmentRow(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM shipments WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }
}
