<?php

declare(strict_types=1);

/**
 * Seed integrity: the seed files can be run again without duplicating anything,
 * every role has a usable account, and every module a role may open actually has
 * data behind it.
 */

require __DIR__ . '/support.php';

/**
 * The modules the seed fills.
 *
 * Everything else ships empty on purpose: a new company starts with its own
 * vehicles and its own customers, not with somebody else's. These are the ones
 * that must arrive with something in them, because they are what a first-time
 * user needs in order to see how the system fits together.
 */
const SEEDED_MODULES = [
    'vehicles', 'drivers', 'requests', 'trips', 'shipments', 'deliveries',
    'customers', 'rates', 'fuel', 'warehouses', 'warehouse', 'movements',
    'procurement', 'suppliers', 'users', 'accounts', 'payment_methods',
    'currencies', 'lookups', 'border_posts',
];

[$pdo, $root, $dbName] = test_database('logistics_mvc_seed');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Seed integrity tests');

    $tables = [
        'roles', 'users', 'vehicles', 'drivers', 'warehouses', 'suppliers', 'trips',
        'transport_requests', 'deliveries', 'inventory_items', 'maintenance_orders',
        'purchase_requests', 'fuel_records', 'expenses', 'reports', 'notifications',
        'customers', 'shipments', 'trip_stops', 'vehicle_documents', 'stock_movements',
        'rate_cards', 'invoices', 'invoice_lines', 'payments', 'maintenance_parts',
        'purchase_request_lines', 'permissions', 'role_permissions', 'company_settings',
    ];

    $countAll = static function () use ($pdo, $tables): array {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        }
        return $counts;
    };

    $before = $countAll();

    // ------------------------------------------------------------ idempotency
    $config = require __DIR__ . '/../config/config.php';
    $rewrite = static fn (string $path): string => str_replace('logistics_mvc', $dbName, (string) file_get_contents($path));
    foreach (['seed.sql', 'seed_extended.sql'] as $seed) {
        $root->exec($rewrite(__DIR__ . '/../database/' . $seed));
    }

    $after = $countAll();
    foreach ($tables as $table) {
        $test->same($before[$table], $after[$table], "{$table} is unchanged when the seeds are run a second time");
        $test->assert($before[$table] > 0, "{$table} has seeded rows");
    }

    // ------------------------------------------------------------- accounts
    $repository = new Models\UserRepository();
    $accounts = $repository->activeLoginAccounts();
    $test->same(7, count($accounts), 'all seven roles have an active login account');

    $privileges = $pdo->query('SELECT email, prvg FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);
    $test->same(1, (int) ($privileges['admin@itec.rw'] ?? 0), 'the seeded admin may switch roles');
    $test->same(1, count(array_filter($privileges, static fn ($p): bool => (int) $p !== 2)), 'no other seeded account is privileged');

    // ------------------------------------------------------ permission matrix
    $test->assert(count(Models\Permission::catalogue()) >= 20, 'the permission catalogue is seeded');

    foreach (array_keys(role_definitions()) as $roleKey) {
        $matrix = Models\Permission::forRole($roleKey);
        $test->assert($matrix !== [], "{$roleKey} has permissions in the database");
        $test->assert(!empty($matrix['dashboard']['view']), "{$roleKey} may open the dashboard");
    }

    $superAdmin = Models\Permission::forRole('super_admin');
    $test->same(count(Models\Permission::catalogue()), count($superAdmin), 'super admin holds every permission');

    $driverMatrix = Models\Permission::forRole('driver');
    $test->assert(!isset($driverMatrix['users']), 'a driver holds no permission on users');
    $test->assert(!isset($driverMatrix['invoices']), 'a driver holds no permission on invoices');

    // ------------------------------- every role can open what it is allowed to
    foreach (role_definitions() as $roleKey => $definition) {
        $account = $accounts[$roleKey] ?? null;
        $test->assert($account !== null, "{$roleKey} has an account");
        if ($account === null) {
            continue;
        }

        $user = $repository->findActiveByEmail((string) $account['email']);
        $test->assert($user !== null && password_verify('password', $user['password_hash']), "{$roleKey} can sign in with the seeded password");

        test_sign_in($account, $roleKey, $roleKey === 'super_admin' ? 1 : 2);

        $notifications = current_notifications(20);
        $test->assert($notifications !== [], "{$roleKey} receives at least one notification");
        foreach ($notifications as $notification) {
            $route = (string) ($notification['link_route'] ?? '');
            $test->assert($route === '' || role_can($route), "{$roleKey} notification links only to a route it may open");
        }

        foreach (array_keys(Models\Schema::all()) as $moduleKey) {
            if (!role_can($moduleKey)) {
                continue;
            }

            $listing = Models\LogisticsData::listing($moduleKey, [], current_context());
            $test->assert(isset($listing['rows']), "{$roleKey} can load the {$moduleKey} list");

            // A driver legitimately sees only their own rows, so only require
            // data for the roles that see the whole company — and only for the
            // modules the seed actually fills. The demo data was deliberately
            // cut back to what a new company needs to find its way around, so a
            // module standing empty is a decision, not a fault. What matters is
            // that its page opens, which is asserted above.
            if ($roleKey !== 'driver' && in_array($moduleKey, SEEDED_MODULES, true)) {
                $test->assert($listing['total'] >= 1, "{$moduleKey} has data for {$roleKey}");
            }
        }
    }

    // ----------------------------------------------- derived values are right
    $invoices = $pdo->query("SELECT invoice_number, subtotal, tax_rate, tax_amount, total_amount, amount_paid FROM invoices WHERE invoice_number LIKE 'INV-2026-%'")->fetchAll();
    $test->assert($invoices !== [], 'invoices are seeded');
    foreach ($invoices as $invoice) {
        $expectedTax = round((float) $invoice['subtotal'] * ((float) $invoice['tax_rate'] / 100), 2);
        $test->same($expectedTax, (float) $invoice['tax_amount'], "{$invoice['invoice_number']} VAT matches its subtotal");
        $test->same(round((float) $invoice['subtotal'] + $expectedTax, 2), (float) $invoice['total_amount'], "{$invoice['invoice_number']} total matches subtotal plus VAT");

        $lineTotal = (float) $pdo->query("SELECT COALESCE(SUM(line_total), 0) FROM invoice_lines il INNER JOIN invoices i ON i.id = il.invoice_id WHERE i.invoice_number = '{$invoice['invoice_number']}'")->fetchColumn();
        $test->same($lineTotal, (float) $invoice['subtotal'], "{$invoice['invoice_number']} subtotal matches its lines");
    }

    // The stock ledger and the item balance must agree.
    $drifted = $pdo->query(
        "SELECT i.sku
           FROM inventory_items i
           INNER JOIN (
               SELECT item_id, balance_after, id,
                      ROW_NUMBER() OVER (PARTITION BY item_id ORDER BY moved_at DESC, id DESC) rn
                 FROM stock_movements
           ) m ON m.item_id = i.id AND m.rn = 1
          WHERE ABS(i.quantity - m.balance_after) > 0.001"
    )->fetchAll(PDO::FETCH_COLUMN);
    $test->same([], $drifted, 'every item balance matches the last ledger entry');

    // Every seeded reference is unique, which the id-based routing relies on.
    foreach ([
        'vehicles' => 'plate_number', 'trips' => 'reference_code', 'shipments' => 'shipment_code',
        'deliveries' => 'delivery_code', 'invoices' => 'invoice_number', 'customers' => 'customer_code',
        'inventory_items' => 'sku', 'stock_movements' => 'movement_code',
    ] as $table => $column) {
        $duplicates = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT {$column} FROM {$table} GROUP BY {$column} HAVING COUNT(*) > 1) d")->fetchColumn();
        $test->same(0, $duplicates, "{$table}.{$column} has no duplicates");
    }

    // Report catalogue rows point at a query that really exists.
    $reportKeys = $pdo->query('SELECT report_key FROM reports WHERE report_key IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($reportKeys as $reportKey) {
        $test->assert(Models\ReportData::exists((string) $reportKey), "saved report {$reportKey} maps to a live query");
    }

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
