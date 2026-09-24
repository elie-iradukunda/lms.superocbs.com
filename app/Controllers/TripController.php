<?php

namespace Controllers;

class TripController
{
    public function index(): void
    {
        \view('trips/index', [
            'title' => 'Trip planning',
            'trips' => [
                ['reference' => 'TRP-0248', 'route' => 'Kigali - Huye', 'pickup' => '18 Sep, 08:30', 'status' => 'In transit'],
                ['reference' => 'TRP-0247', 'route' => 'Kigali - Musanze', 'pickup' => '18 Sep, 07:00', 'status' => 'Delivered'],
                ['reference' => 'TRP-0246', 'route' => 'Kigali - Rubavu', 'pickup' => '18 Sep, 10:15', 'status' => 'Loading'],
            ],
        ]);
    }
}
