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
