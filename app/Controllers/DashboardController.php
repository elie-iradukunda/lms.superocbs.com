<?php

namespace Controllers;

use Models\ChartData;
use Models\Clearance;
use Models\Currency;
use Models\Workboard;

class DashboardController
{
    private const HEADINGS = [
        'super_admin' => ['Administration', 'Companies, users, permissions, settings and the audit log. Admin keeps the system safe and takes no part in requests or payments.'],
        'management' => ['Managing Director', 'Your company: what waits for your authorization, money, and the reports of everything running.'],
        'logistics_manager' => ['Operations', 'Your fleet: trucks by status, trips and their money, drivers, documents, service and sister-company requests.'],
        'finance' => ['Finance', 'Requests to verify, the books, what is owed and owing, payroll and the month-end.'],
        'cashier' => ['Cash office', 'Authorized requests to pay, MTN batches waiting for proof, the cash box, and the books.'],
        'warehouse_manager' => ['Store & garage', 'Issue notes to issue, stock below minimum, deliveries to receive, and the job cards.'],
        'driver' => ['My trip', 'Your trip, your money and your documents.'],
        'clearance_officer' => ['Clearance desk', 'Every clearance file from papers to release, and the trucks waiting on them.'],
    ];

    public function index(): void
    {
        $role = \current_role();
        // The driver's home is the app with its five buttons (section 16).
        if ($role === 'driver' && \current_driver_id() !== null && ($_GET['denied'] ?? '') === '') {
            header('Location: ' . \url('my-trips'));
            exit;
        }
        // Customers and suppliers work in their portal (sections 13 and 18).
        if (in_array($role, ['customer', 'supplier'], true) && ($_GET['denied'] ?? '') === '') {
            header('Location: ' . \url('portal'));
            exit;
        }
        $userId = \current_user_id();
        [$title, $subtitle] = self::HEADINGS[$role] ?? self::HEADINGS['logistics_manager'];
        $currency = ChartData::currencyCode((string) ($_GET['currency'] ?? ''));

        \view('dashboard/index', [
            'title' => $title,
            'roleSubtitle' => $subtitle,
            'home' => \Models\RoleHome::cards($role),
            'metrics' => array_map(static fn (array $metric): array => $metric + ['tone' => 'blue'], ChartData::metrics($role, $userId)),
            'charts' => ChartData::forRole($role, $userId, $currency),
            'chartCurrency' => $currency,
            'currencies' => Currency::options(),
            'attention' => ChartData::attention($role, $userId),
            'workboard' => Workboard::forRole($role, $userId),
            'clearanceBoard' => in_array($role, ['clearance_officer', 'super_admin', 'logistics_manager'], true) ? Clearance::board() : [],
            'role' => $role,
            'denied' => preg_match('/^[a-z_]{0,40}$/', (string) ($_GET['denied'] ?? '')) === 1 ? (string) ($_GET['denied'] ?? '') : '',
            'recentTrips' => ChartData::recentTrips($userId, $role),
            'pageScripts' => ['/assets/js/vendor/apexcharts-3.54.1.min.js', '/assets/js/lms-charts.js'],
        ]);
    }
}
