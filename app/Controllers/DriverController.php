<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\DriverWorkspace;

/**
 * The driver's own page: the trips he is on now, the ones just finished, and
 * every paper he may be asked for at a port, a weighbridge or a border.
 */
final class DriverController
{
    public function myTrips(): void
    {
        $driverId = \current_role() === 'driver' ? \current_driver_id() : null;
        if ($driverId === null) {
            Flash::info('"My trips" is the page for drivers. The full list of trips is under Transport.');
            header('Location: ' . \url('trips'));
            exit;
        }

        $trips = DriverWorkspace::trips($driverId);
        $active = array_values(array_filter($trips, static fn (array $t): bool => in_array($t['status'], ['requested', 'approved', 'loading', 'in_transit'], true)));
        $done = array_values(array_filter($trips, static fn (array $t): bool => !in_array($t['status'], ['requested', 'approved', 'loading', 'in_transit'], true)));

        // A truck's papers belong to the truck, not to each trip it makes, so
        // they are listed once per truck.
        $trucks = [];
        foreach ($trips as $trip) {
            if (!empty($trip['vehicle_id']) && !isset($trucks[$trip['vehicle_id']])) {
                $trucks[$trip['vehicle_id']] = ['plate' => (string) ($trip['plate_number'] ?? ''), 'make' => trim(($trip['make'] ?? '') . ' ' . ($trip['model'] ?? '')), 'documents' => $trip['vehicle_documents']];
            }
        }

        \view('driver/my_trips', [
            'title' => 'My trips & papers',
            'active' => $active,
            'done' => $done,
            'trucks' => array_values($trucks),
            'own' => DriverWorkspace::ownDocuments($driverId),
        ]);
    }

    /**
     * "Start trip": the driver says he is driving out. It is the same dispatch
     * the office can do, with every check it makes, only pressed by the person
     * who actually knows the truck has left.
     */
    public function start(array $params): void
    {
        $trip = $this->ownTrip((int) ($params['id'] ?? 0));
        if (!in_array((string) $trip['status'], ['requested', 'approved', 'loading'], true)) {
            Flash::error(sprintf('%s has already started. Mark each stop as you reach it.', $trip['reference_code']));
            $this->back();
        }
        $ready = \Models\DispatchReadiness::forTrip((int) $trip['id']);
        if ($ready === null || !$ready['ready']) {
            Flash::error('This trip is not ready to start yet: ' . implode('; ', array_slice($ready['failures'] ?? ['the office has not finished the checks'], 0, 2)) . '. Call the office.');
            $this->back();
        }
        // The kilometres on the clock as he leaves: every distance on the trip is counted from here.
        $db = \Core\Database::connection();
        $typed = str_replace([',', ' '], '', trim((string) ($_POST['mileage'] ?? '')));
        if ($typed === '' || !ctype_digit($typed)) {
            Flash::error('Write the kilometres on the clock as a number, like 186450, then press Start trip.');
            $this->back();
        }
        $km = (int) $typed;
        $last = (int) $db->query('SELECT mileage FROM vehicles WHERE id = ' . (int) $trip['vehicle_id'])->fetchColumn();
        if ($last > 0 && $km < $last) {
            Flash::error(sprintf('%s km is less than the truck\'s last reading of %s km. Check the clock.', number_format($km), number_format($last)));
            $this->back();
        }
        if ($last > 0 && $km - $last > 2000) {
            Flash::error(sprintf('%s km is %s km more than the truck\'s last reading (%s km). Check the clock and type it again.', number_format($km), number_format($km - $last), number_format($last)));
            $this->back();
        }
        $db->prepare('UPDATE trips SET start_mileage = ? WHERE id = ?')->execute([$km, (int) $trip['id']]);

        $result = \Models\Workflow::apply('trips', (int) $trip['id'], 'dispatch');
        if ($result['ok']) {
            $db->prepare('UPDATE vehicles SET mileage = GREATEST(COALESCE(mileage, 0), ?) WHERE id = ?')->execute([$km, (int) $trip['vehicle_id']]);
            Flash::success($result['message']);
        } else {
            $db->prepare('UPDATE trips SET start_mileage = NULL WHERE id = ?')->execute([(int) $trip['id']]);
            Flash::error($result['message']);
        }
        $this->back();
    }

    /** "I have arrived" at a stop: the time is recorded and the office is told. */
    public function arrive(array $params): void
    {
        $this->stop($params, 'arrive');
    }

    /** "Leaving" a stop: the time he went on, so waiting at a border shows. */
    public function leave(array $params): void
    {
        $this->stop($params, 'leave');
    }

    private function stop(array $params, string $what): void
    {
        $trip = $this->ownTrip((int) ($params['id'] ?? 0));
        if ($trip['status'] !== 'in_transit') {
            Flash::error('Start the trip first: stops are recorded while the truck is on the road.');
            $this->back();
        }
        $result = \Models\TripProgress::stop((int) $trip['id'], (int) ($params['stop'] ?? 0), $what, [
            'note' => (string) ($_POST['note'] ?? ''),
            'mileage' => (string) ($_POST['mileage'] ?? ''),
            'condition' => (string) ($_POST['condition'] ?? ''),
        ]);
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);
        $this->back();
    }

    /**
     * The phone's position while My trips is open on the road: the truck on
     * the live map, and arrivals at a border recorded on their own.
     */
    public function position(array $params): void
    {
        $driverId = \current_role() === 'driver' ? \current_driver_id() : null;
        $statement = \Core\Database::connection()->prepare('SELECT * FROM trips WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([(int) ($params['id'] ?? 0)]);
        $trip = $statement->fetch(\PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        if ($driverId === null || $trip === false || (int) $trip['driver_id'] !== (int) $driverId || empty($trip['vehicle_id'])) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'That trip is not one of yours.']);
            exit;
        }
        if (!in_array((string) $trip['status'], ['approved', 'loading', 'in_transit'], true)) {
            echo json_encode(['ok' => false, 'message' => 'The trip is not on the road.']);
            exit;
        }
        $lat = filter_var($_POST['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'No position was sent.']);
            exit;
        }
        // One position a minute is plenty for a lorry.
        $recent = \Core\Database::connection()->prepare('SELECT COUNT(*) FROM vehicle_positions WHERE trip_id = ? AND received_at >= DATE_SUB(NOW(), INTERVAL 50 SECOND)');
        $recent->execute([(int) $trip['id']]);
        if ((int) $recent->fetchColumn() > 0) {
            echo json_encode(['ok' => true, 'message' => 'Already up to date.']);
            exit;
        }
        $speed = filter_var($_POST['speed'] ?? null, FILTER_VALIDATE_FLOAT);
        $heading = filter_var($_POST['heading'] ?? null, FILTER_VALIDATE_FLOAT);
        $accuracy = filter_var($_POST['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
        $result = \Models\Tracking::record((int) $trip['vehicle_id'], (float) $lat, (float) $lng, null, 'phone',
            $speed !== false && $speed !== null ? (float) $speed * 3.6 : null, $heading !== false && $heading !== null ? (int) $heading : null,
            $accuracy !== false && $accuracy !== null ? (float) $accuracy : null, (int) $driverId);
        echo json_encode($result);
        exit;
    }

    /**
     * The goods handed over at the door: the receiver's name, a photo of the
     * goods or the signed note, the receiver's signature on the screen, and
     * where it happened. The delivery is closed in the same press.
     */
    public function handOver(array $params): void
    {
        $trip = $this->ownTrip((int) ($params['id'] ?? 0));
        $db = \Core\Database::connection();
        $statement = $db->prepare('SELECT * FROM deliveries WHERE id = ? AND trip_id = ? AND deleted_at IS NULL');
        $statement->execute([(int) ($params['delivery'] ?? 0), (int) $trip['id']]);
        $delivery = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($delivery === false) {
            Flash::error('That delivery is not on your trip.');
            $this->back();
        }
        if (!in_array((string) $delivery['status'], ['loading', 'in_transit', 'at_destination'], true)) {
            Flash::error(sprintf('%s is already %s.', $delivery['delivery_code'], \Models\Schema::label((string) $delivery['status'])));
            $this->back();
        }
        $receiver = mb_substr(trim((string) ($_POST['received_by'] ?? '')), 0, 150);
        if ($receiver === '') {
            Flash::error('Write the name of the person who received the goods.');
            $this->back();
        }

        $folder = rtrim((string) \config('uploads.path', dirname(__DIR__, 2) . '/storage/uploads'), "/\\") . '/deliveries';
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            Flash::error('The proof could not be saved: the upload folder is missing.');
            $this->back();
        }

        $photo = $_FILES['photo'] ?? null;
        $photoPath = null;
        if (is_array($photo) && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo((string) $photo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true) || (int) $photo['size'] > (int) \config('uploads.max_bytes', 5242880)) {
                Flash::error('The photo must be a picture or a PDF, no bigger than the upload limit.');
                $this->back();
            }
            $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
            if (move_uploaded_file((string) $photo['tmp_name'], $folder . '/' . $name)) {
                $photoPath = 'deliveries/' . $name;
            }
        }

        $signaturePath = null;
        $signature = (string) ($_POST['signature'] ?? '');
        if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signature, $match) === 1) {
            $png = base64_decode($match[1], true);
            if ($png !== false && strlen($png) > 200 && strlen($png) < 1_500_000 && str_starts_with($png, "\x89PNG")) {
                $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '-signature.png';
                if (file_put_contents($folder . '/' . $name, $png) !== false) {
                    $signaturePath = 'deliveries/' . $name;
                }
            }
        }
        if ($photoPath === null && $signaturePath === null) {
            Flash::error('Take a photo of the signed delivery note, or let the receiver sign on the screen, before closing the delivery.');
            $this->back();
        }

        $lat = filter_var($_POST['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $db->prepare('UPDATE deliveries SET proof_file = COALESCE(?, proof_file), recipient_signature = COALESCE(?, recipient_signature), collected_by = ?,
                             pod_latitude = ?, pod_longitude = ? WHERE id = ?')
           ->execute([$photoPath, $signaturePath, $receiver, $lat !== false && $lat !== null ? $lat : null, $lng !== false && $lng !== null ? $lng : null, (int) $delivery['id']]);
        \Models\AuditLog::record('delivery.proof_captured', 'deliveries', (string) $delivery['delivery_code'], null, ['photo' => $photoPath !== null, 'signature' => $signaturePath !== null, 'received_by' => $receiver]);

        $result = \Models\Workflow::apply('deliveries', (int) $delivery['id'], 'complete');
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);
        $this->back();
    }

    /** The trip, only when it is this driver's. */
    private function ownTrip(int $id): array
    {
        $driverId = \current_role() === 'driver' ? \current_driver_id() : null;
        $statement = \Core\Database::connection()->prepare('SELECT * FROM trips WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        $trip = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($driverId === null || $trip === false || (int) $trip['driver_id'] !== (int) $driverId) {
            Flash::error('That trip is not one of yours.');
            $this->back();
        }

        return $trip;
    }

    private function back(): never
    {
        header('Location: ' . \url('my-trips'));
        exit;
    }
}
