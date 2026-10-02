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
}
