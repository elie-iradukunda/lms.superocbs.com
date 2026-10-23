<?php

declare(strict_types=1);

/**
 * Demo data for invoicing, shortages, payroll and sister-company requests
 * (sections 6, 13 and 17), on top of scripts/eri_demo.php and
 * scripts/eri_demo_garage.php:
 *
 *   - a customer rate card and shortage rule for Jambo oil factory;
 *   - driver salaries, so the payroll list shows figures;
 *   - a sister-company request (ERI Tanzania truck, 4 tyres in Kigali),
 *     confirmed so a job card and issue note are open.
 *
 *   php scripts/eri_demo_commerce.php
 *
 * Run by scripts/eri_demo.php; safe to run on its own once.
 */

if (!defined('ERI_DEMO_RUNNING')) {
    require __DIR__ . '/../bootstrap.php';
}

use Models\Garage;
use Models\LogisticsData;
use Models\RequestForms;

$db = Core\Database::connection();
if ((int) $db->query("SELECT COUNT(*) FROM rate_cards WHERE rate_code = 'JAMBO-L'")->fetchColumn() > 0) {
    echo "The commerce demo data is already loaded.\n";
    return;
}

$accounts = (new Models\UserRepository())->activeLoginAccounts();
$as = static function (string $role) use ($accounts, $db): void {
    $account = $accounts[$role];
    $_SESSION = [
        'logistics_authenticated' => true, 'logistics_role' => $role, 'logistics_user_id' => (int) $account['id'],
        'logistics_user_name' => $account['name'], 'logistics_user_email' => $account['email'], 'logistics_prvg' => 2,
        'logistics_home_company_id' => (int) ($db->query('SELECT company_id FROM users WHERE id = ' . (int) $account['id'])->fetchColumn() ?: 1),
    ];
    $_SESSION['logistics_company_id'] = $_SESSION['logistics_home_company_id'];
    Models\Permission::flush();
};
$save = static function (string $module, array $input) use ($db): int {
    $errors = LogisticsData::validate($module, $input, [], null);
    if ($errors !== []) {
        throw new RuntimeException($module . ': ' . implode(' ', $errors));
    }
    $id = LogisticsData::save($module, null, $input, []);
    LogisticsData::takeOneTimePassword();

    return $id;
};
$previous = Models\Settings::get('signature_required_to_approve', '1');
Models\Settings::put('signature_required_to_approve', '0');
$start = date('Y-m-d', strtotime('-30 days'));

$jambo = (int) $db->query("SELECT id FROM customers WHERE customer_name = 'Jambo oil factory' LIMIT 1")->fetchColumn();
$route = (int) $db->query("SELECT id FROM routes WHERE route_code = 'KGL-MBA-KGL' LIMIT 1")->fetchColumn();

// Finance sets the customer's price and shortage tolerance.
$as('finance');
if ($jambo > 0) {
    $save('rates', ['rate_code' => 'JAMBO-L', 'service_type' => 'full_truck', 'customer_id' => (string) $jambo, 'route_id' => (string) $route, 'cargo_type' => 'litres', 'status' => 'active', 'rate_type' => 'per_litre', 'rate_amount' => '25', 'currency' => 'RWF', 'effective_from' => $start]);
    $save('shortage_rules', ['customer_id' => (string) $jambo, 'cargo_type' => 'litres', 'tolerance_type' => 'percent', 'tolerance_value' => '0.5', 'charge' => 'excess', 'unit_value' => '1500', 'currency' => 'RWF', 'valid_from' => $start, 'is_active' => '1']);
}

// Driver salaries (company 1), so the payroll list has figures.
$db->exec("UPDATE drivers SET salary = 300000, salary_currency = 'RWF' WHERE company_id = 1 AND (salary IS NULL OR salary = 0)");

// A sister-company request: an ERI Tanzania truck needs 4 tyres in Kigali.
$tzTruck = (int) $db->query("SELECT id FROM vehicles WHERE company_id = 3 AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
if ($tzTruck === 0) {
    $db->exec("INSERT INTO vehicles (company_id, plate_number, fleet, vehicle_type, status, mileage, created_at) VALUES (3, 'T 456 DEF', 'container', 'Truck', 'available', 210000, NOW())");
    $tzTruck = (int) $db->lastInsertId();
}
$tyreItem = (int) $db->query("SELECT id FROM inventory_items WHERE sku = 'TY-31580' LIMIT 1")->fetchColumn();
// Shomari is the Operational Manager of ERI Tanzania (company 3); he raises the
// request from there to ERI Rwanda.
$shomari = $db->query("SELECT u.id, u.full_name AS name, u.email FROM users u WHERE u.email = 'shomari@itec.rw' AND u.deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$signAs = static function (array $account, string $role, int $company): void {
    $_SESSION = [
        'logistics_authenticated' => true, 'logistics_role' => $role, 'logistics_user_id' => (int) $account['id'],
        'logistics_user_name' => $account['name'], 'logistics_user_email' => $account['email'], 'logistics_prvg' => 2,
        'logistics_home_company_id' => $company, 'logistics_company_id' => $company,
    ];
    Models\Permission::flush();
};
if ($shomari && $tzTruck > 0) {
    $signAs($shomari, 'logistics_manager', 3);
    $f11 = RequestForms::create([
        'form_code' => 'F11', 'to_company_id' => 1, 'vehicle_id' => $tzTruck,
        'title' => '4 tyres for a truck in Kigali', 'notes' => 'Two front tyres worn, two damaged at the border',
    ], [[
        'item_code' => 'tyre', 'vehicle_id' => $tzTruck, 'description' => $tyreItem > 0 ? (string) $db->query("SELECT item_name FROM inventory_items WHERE id = {$tyreItem}")->fetchColumn() : 'Tyre 315/80 R22.5', 'quantity' => 4, 'unit' => 'tyre',
    ]]);
    RequestForms::send($f11);
    // The ERI Rwanda OM (company 1) confirms it: a job card and issue note open.
    $rwOm = $db->query("SELECT id, full_name AS name, email FROM users WHERE email = 'aline@itec.rw' AND deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $signAs($rwOm, 'logistics_manager', 1);
    try {
        RequestForms::confirm($f11, 'Approved; our store has the tyres');
    } catch (\Throwable $exception) {
        echo 'Sister request not confirmed in demo: ' . $exception->getMessage() . "\n";
    }
}

Models\Settings::put('signature_required_to_approve', $previous);
echo "Commerce demo loaded: Jambo rate card and shortage rule, driver salaries, and a confirmed sister-company tyre request with its job card.\n";
