<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\DeliveryNotes;
use Models\DriverSupport;
use Models\Fleet;
use Models\Handover;
use Models\LogisticsData;
use Models\Payments;
use Models\TripLife;
use Models\Uploads;

/**
 * The driver's app (section 16): five buttons, on the phone.
 *
 *   My trip            route, truck, cargo, days, OM contact; Money received,
 *                      Departed, Arrived, Loading done, Offloading done
 *   Delivery note      quantities delivered and the customer's stamped copy
 *   Report a problem   breakdown, accident, sickness, border delay, money
 *   Hand over truck    parking place, odometer, fuel, photos, key
 *   My profile         documents, payments received, deductions, MoMo number
 */
final class DriverAppController
{
    public function home(): void
    {
        $driver = $this->driver();
        \view('driver/home', [
            'title' => 'My trip',
            'driver' => $driver,
            'trip' => $this->currentTrip((int) $driver['id']),
            'toConfirm' => array_values(array_filter(Payments::forDriver((int) $driver['id']), static fn (array $line): bool => $line['status'] === 'paid' && $line['confirmed_at'] === null)),
            'support' => array_slice(DriverSupport::forDriver((int) $driver['id']), 0, 3),
            'shortages' => \Models\Shortages::forDriver((int) $driver['id']),
        ]);
    }

    /** The driver explains a shortage (F9) before Finance and the MD decide. */
    public function explainShortage(array $params): void
    {
        $driver = $this->driver();
        try {
            \Models\Shortages::explain((int) ($params['id'] ?? 0), (int) $driver['id'], (string) ($_POST['explanation'] ?? ''));
            Flash::success('Your explanation is with Finance and the OM.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url('my-trips'));
    }

    public function trip(): void
    {
        $driver = $this->driver();
        $trip = $this->currentTrip((int) $driver['id']);
        $legs = [];
        $om = null;
        if ($trip !== null) {
            $statement = Database::connection()->prepare('SELECT l.*, b.post_name FROM route_legs l LEFT JOIN border_posts b ON b.id = l.border_post_id WHERE l.route_id = ? ORDER BY l.leg_no');
            $statement->execute([(int) $trip['route_id']]);
            $legs = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $omRow = Database::connection()->prepare("SELECT u.full_name, u.phone, u.whatsapp_number FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.role_key = 'logistics_manager' AND u.status = 'active' AND (u.company_id = ? OR u.company_id IS NULL) ORDER BY u.id LIMIT 1");
            $omRow->execute([(int) $trip['company_id']]);
            $om = $omRow->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        \view('driver/trip', [
            'title' => 'My trip',
            'driver' => $driver,
            'trip' => $trip,
            'legs' => $legs,
            'om' => $om,
            'money' => $trip !== null ? TripLife::moneyState((int) $trip['id']) : null,
            'toConfirm' => array_values(array_filter(Payments::forDriver((int) $driver['id']), static fn (array $line): bool => $line['status'] === 'paid' && $line['confirmed_at'] === null)),
            'events' => $trip !== null ? array_reverse(TripLife::events((int) $trip['id'])) : [],
        ]);
    }

    public function act(array $params): void
    {
        $driver = $this->driver();
        $trip = $this->ownTrip((int) $driver['id'], (int) ($params['id'] ?? 0));
        try {
            $photo = Uploads::store($_FILES['photo'] ?? null, 'trip_events', Uploads::IMAGES);
            $message = TripLife::act((int) $trip['id'], (string) ($params['action'] ?? ''), [
                'odometer' => $_POST['odometer'] ?? '',
                'quantity' => $_POST['quantity'] ?? '',
                'lat' => $_POST['lat'] ?? '',
                'lng' => $_POST['lng'] ?? '',
                'note' => trim((string) ($_POST['note'] ?? '')) ?: null,
                'photo' => $photo,
                'source' => 'driver',
            ]);
            Flash::success($message);
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['my-trips', 'trip']));
    }

    /** "Money received". */
    public function confirmMoney(array $params): void
    {
        $driver = $this->driver();
        try {
            Payments::confirmReceipt((int) ($params['line'] ?? 0), (int) $driver['id']);
            Flash::success('Thank you. The office can see the money arrived.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url('my-trips'));
    }

    public function delivery(): void
    {
        $driver = $this->driver();
        \view('driver/delivery', ['title' => 'Delivery note', 'driver' => $driver, 'trip' => $this->currentTrip((int) $driver['id'])]);
    }

    public function storeDelivery(): void
    {
        $driver = $this->driver();
        $trip = $this->currentTrip((int) $driver['id']);
        try {
            if ($trip === null) {
                throw new \RuntimeException('You have no trip running.');
            }
            $photo = Uploads::store($_FILES['photo'] ?? null, 'delivery_notes', Uploads::IMAGES);
            DeliveryNotes::submit((int) $trip['id'], $_POST, $photo);
            Flash::success('Delivery note sent to the OM. Drive safely back.');
            $this->redirect(\url('my-trips'));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['my-trips', 'delivery']));
        }
    }

    public function support(): void
    {
        $driver = $this->driver();
        \view('driver/support', ['title' => 'Report a problem', 'driver' => $driver, 'trip' => $this->currentTrip((int) $driver['id']), 'requests' => DriverSupport::forDriver((int) $driver['id'])]);
    }

    public function storeSupport(): void
    {
        $driver = $this->driver();
        $trip = $this->currentTrip((int) $driver['id']);
        try {
            $photos = [];
            foreach ((array) ($_FILES['photos']['name'] ?? []) as $index => $name) {
                $stored = Uploads::store([
                    'name' => $name,
                    'tmp_name' => $_FILES['photos']['tmp_name'][$index] ?? '',
                    'size' => $_FILES['photos']['size'][$index] ?? 0,
                    'error' => $_FILES['photos']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                ], 'trip_events', Uploads::IMAGES);
                if ($stored !== null) {
                    $photos[] = $stored;
                }
            }
            DriverSupport::submit((int) $driver['id'], $trip !== null ? (int) $trip['id'] : null, $_POST, $photos);
            Flash::success('Sent. The OM has been told at once.');
            $this->redirect(\url('my-trips'));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['my-trips', 'support']));
        }
    }

    public function handover(): void
    {
        $driver = $this->driver();
        $trip = $this->currentTrip((int) $driver['id']);
        $vehicleId = $trip['vehicle_id'] ?? $driver['default_vehicle_id'] ?? null;
        \view('driver/handover', [
            'title' => 'Hand over truck',
            'driver' => $driver,
            'trip' => $trip,
            'vehicle' => $vehicleId !== null ? Fleet::vehicle((int) $vehicleId) : null,
        ]);
    }

    public function storeHandover(): void
    {
        $driver = $this->driver();
        $trip = $this->currentTrip((int) $driver['id']);
        $vehicleId = (int) ($trip['vehicle_id'] ?? $driver['default_vehicle_id'] ?? 0);
        try {
            if ($vehicleId === 0) {
                throw new \RuntimeException('You have no truck to hand over.');
            }
            $photos = [];
            foreach (['front', 'back', 'left', 'right'] as $side) {
                $photos[$side] = Uploads::store($_FILES['photo_' . $side] ?? null, 'vehicle_handovers', Uploads::IMAGES);
            }
            Handover::submit($vehicleId, $trip !== null ? (int) $trip['id'] : null, (int) $driver['id'], $_POST, $photos);
            Flash::success('Handover sent. The OM will confirm it.');
            $this->redirect(\url('my-trips'));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['my-trips', 'handover']));
        }
    }

    public function profile(): void
    {
        $driver = $this->driver();
        $db = Database::connection();
        $documents = $db->prepare(
            "SELECT dd.*, COALESCE(dt.type_name, dd.document_type) AS type_name FROM driver_documents dd
               LEFT JOIN document_types dt ON dt.applies_to = 'driver' AND dt.type_code = dd.document_type
              WHERE dd.driver_id = ? AND dd.deleted_at IS NULL ORDER BY dt.sort_order"
        );
        $documents->execute([(int) $driver['id']]);
        $changes = $db->prepare('SELECT * FROM driver_momo_changes WHERE driver_id = ? ORDER BY id DESC LIMIT 3');
        $changes->execute([(int) $driver['id']]);
        $deductions = [];
        try {
            $statement = $db->prepare(
                "SELECT r.request_no, r.title, r.total_amount, r.currency, r.status, r.created_at FROM form_requests r
                  WHERE r.form_code = 'F9' AND r.driver_id = ? AND r.status NOT IN ('draft', 'cancelled') ORDER BY r.id DESC LIMIT 20"
            );
            $statement->execute([(int) $driver['id']]);
            $deductions = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
        }

        \view('driver/profile', [
            'title' => 'My profile',
            'driver' => $driver,
            'documents' => $documents->fetchAll(\PDO::FETCH_ASSOC),
            'payments' => Payments::forDriver((int) $driver['id']),
            'changes' => $changes->fetchAll(\PDO::FETCH_ASSOC),
            'deductions' => $deductions,
        ]);
    }

    /** A new phone or MoMo number: used for payment only after the OM approves. */
    public function changeMomo(): void
    {
        $driver = $this->driver();
        $input = ['driver_id' => (string) $driver['id'], 'new_number' => trim((string) ($_POST['new_number'] ?? '')), 'new_name' => trim((string) ($_POST['new_name'] ?? '')), 'new_phone' => trim((string) ($_POST['new_phone'] ?? ''))];
        $errors = LogisticsData::validate('driver_momo_changes', $input, [], null);
        if ($errors !== []) {
            Flash::error(implode(' ', $errors));
        } else {
            LogisticsData::save('driver_momo_changes', null, $input, []);
            Flash::success('Sent to the OM. You are paid on the new number once it is approved.');
        }
        $this->redirect(\url(['my-trips', 'profile']));
    }

    /** The PIN the driver types at the cash office to confirm cash taken. */
    public function setPin(): void
    {
        $driver = $this->driver();
        $pin = (string) ($_POST['pin'] ?? '');
        if (preg_match('/^\d{4,6}$/', $pin) !== 1 || $pin !== (string) ($_POST['pin_confirm'] ?? '')) {
            Flash::error('The PIN is 4 to 6 digits, typed twice the same.');
        } else {
            Database::connection()->prepare('UPDATE drivers SET cash_pin_hash = ? WHERE id = ?')->execute([password_hash($pin, PASSWORD_DEFAULT), (int) $driver['id']]);
            \Models\AuditLog::record('driver.pin_set', 'drivers', (string) $driver['full_name']);
            Flash::success('Your cash PIN is set.');
        }
        $this->redirect(\url(['my-trips', 'profile']));
    }

    /** The phone reports it is alive (and where), so a silent phone can be noticed. */
    public function ping(): void
    {
        $driverId = \current_driver_id();
        if ($driverId !== null) {
            Database::connection()->prepare('UPDATE drivers SET last_seen_at = NOW() WHERE id = ?')->execute([$driverId]);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // --------------------------------------------------------------- helpers

    private function driver(): array
    {
        $driverId = \current_role() === 'driver' ? \current_driver_id() : null;
        $driver = $driverId !== null ? Fleet::driver($driverId) : null;
        if ($driver === null) {
            Flash::info('This is the drivers\' app. The trips of the company are under Trips.');
            $this->redirect(\url('trips'));
        }
        Database::connection()->prepare('UPDATE drivers SET last_seen_at = NOW() WHERE id = ?')->execute([(int) $driver['id']]);

        return $driver;
    }

    private function currentTrip(int $driverId): ?array
    {
        $statuses = "'" . implode("','", TripLife::ACTIVE) . "'";
        $id = Database::connection()->query("SELECT id FROM trips WHERE driver_id = {$driverId} AND deleted_at IS NULL AND status IN ({$statuses}) ORDER BY id DESC LIMIT 1")->fetchColumn();

        return $id === false ? null : TripLife::find((int) $id);
    }

    private function ownTrip(int $driverId, int $tripId): array
    {
        $trip = TripLife::find($tripId);
        if ($trip === null || (int) $trip['driver_id'] !== $driverId) {
            Flash::error('That is not your trip.');
            $this->redirect(\url('my-trips'));
        }

        return $trip;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
