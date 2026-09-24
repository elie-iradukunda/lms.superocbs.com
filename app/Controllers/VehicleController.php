<?php

namespace Controllers;

class VehicleController
{
    public function index(): void
    {
        \view('vehicles/index', [
            'title' => 'Vehicle fleet',
            'vehicles' => [
                ['plate' => 'RAC 482D', 'type' => 'Delivery truck', 'driver' => 'Samuel N.', 'status' => 'On trip', 'service' => '12 Oct 2026'],
                ['plate' => 'RAB 118K', 'type' => 'Pickup', 'driver' => 'Marie U.', 'status' => 'Available', 'service' => '18 Oct 2026'],
                ['plate' => 'RAC 901P', 'type' => 'Box truck', 'driver' => 'Eric M.', 'status' => 'Maintenance', 'service' => 'Today'],
            ],
        ]);
    }
}
