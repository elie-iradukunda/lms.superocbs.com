<?php

namespace Controllers;

use Models\ChartData;

class DashboardController
{
    private const HEADINGS = [
        'super_admin' => ['Executive control center', 'Full system visibility across fleet, transport, warehouse and finance.'],
        'logistics_manager' => ['Operations dashboard', 'Coordinate transport requests, trips, deliveries and daily logistics performance.'],
        'fleet_manager' => ['Fleet performance', 'Monitor vehicle utilization, driver assignments, maintenance and fuel consumption.'],
        'warehouse_manager' => ['Warehouse control', 'Keep stock levels healthy and procurement moving for every depot.'],
        'driver' => ['Driver workspace', 'See your assigned trips, delivery instructions and proof of delivery tasks.'],
        'finance' => ['Finance dashboard', 'Control logistics spending, fuel costs, approvals and monthly financial reports.'],
        'management' => ['Management overview', 'Track the operational and financial indicators that need leadership attention.'],
    ];

    public function index(): void
    {
        $role = \current_role();
        $userId = \current_user_id();
        [$title, $subtitle] = self::HEADINGS[$role] ?? self::HEADINGS['logistics_manager'];

        \view('dashboard/index', [
            'title' => $title,
            'roleSubtitle' => $subtitle,
            'metrics' => array_map(static fn (array $metric): array => $metric + ['tone' => 'blue'], ChartData::metrics($role, $userId)),
            'charts' => ChartData::forRole($role, $userId),
            'attention' => ChartData::attention($role, $userId),
            'role' => $role,
            'denied' => preg_match('/^[a-z_]{0,40}$/', (string) ($_GET['denied'] ?? '')) === 1 ? (string) ($_GET['denied'] ?? '') : '',
            'recentTrips' => ChartData::recentTrips($userId, $role),
            'pageScripts' => ['/assets/js/vendor/apexcharts-3.54.1.min.js', '/assets/js/lms-charts.js'],
        ]);
    }
}
