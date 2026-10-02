<?php

declare(strict_types=1);

/**
 * A customer's goods kept in our warehouse, and a shipping line's container:
 *
 *   GRN for 1,000 bags -> 400 out on a gate pass -> storage billed per bag-day
 *   after the free days, never twice -> the rest out -> lot closed;
 *   container out of the port -> free days -> detention -> returned ->
 *   detention recharged to the customer -> warnings before free time ends.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_storage');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Storage and container tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $one = static function (string $sql, array $params = []) use ($pdo): mixed {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    };
    $checked = static function (string $key, array $input, ?int $id = null): int {
        $errors = Models\LogisticsData::validate($key, $input, [], $id);
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }
        return Models\LogisticsData::save($key, $id, $input);
    };
    $day = static fn (int $offset): string => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));

    $customerId = $checked('customers', [
        'customer_code' => '', 'customer_name' => 'Storage Test Hardware Ltd', 'customer_type' => 'corporate',
        'phone' => '+250 788 300 400', 'email' => 'store@test.rw', 'payment_terms_days' => '30', 'currency' => 'RWF', 'status' => 'active',
    ]);
    $warehouseId = (int) $one("SELECT id FROM warehouses WHERE deleted_at IS NULL AND status = 'active' ORDER BY id LIMIT 1");
    $test->assert($warehouseId > 0, 'a warehouse is on file');

    // ------------------------------------------------------------- the lot
    $lotId = $checked('storage_lots', [
        'lot_code' => '', 'customer_id' => (string) $customerId, 'warehouse_id' => (string) $warehouseId,
        'description' => 'Cement 50 kg bags', 'quantity_received' => '1000', 'unit' => 'Bags', 'bay_location' => 'C4',
        'received_at' => $day(-10) . ' 09:00:00', 'received_from' => 'Driver Jean', 'vehicle_plate' => 'RAD 123A',
        'free_days' => '7', 'charge_basis' => 'per_unit_day', 'daily_rate' => '10', 'currency' => 'RWF',
    ]);
    $lot = $pdo->query("SELECT * FROM storage_lots WHERE id = {$lotId}")->fetch();
    $test->same('in_store', (string) $lot['status'], 'a new lot is in store');
    $test->assert(str_starts_with((string) $lot['lot_code'], 'GRN-'), 'the lot gets a GRN number');

    $test->throws(static fn () => $checked('storage_releases', [
        'gate_pass_no' => '', 'lot_id' => (string) $lotId, 'quantity' => '1200', 'released_at' => $day(-3) . ' 10:00:00', 'collected_by' => 'Someone',
    ]), 'a gate pass cannot release more than is in the bay');

    $passId = $checked('storage_releases', [
        'gate_pass_no' => '', 'lot_id' => (string) $lotId, 'quantity' => '400', 'released_at' => $day(-3) . ' 10:00:00',
        'collected_by' => 'Eric Mugisha', 'collected_id_no' => '1199080012345678', 'vehicle_plate' => 'RAC 777B',
    ]);
    $test->same('partly_released', (string) $one('SELECT status FROM storage_lots WHERE id = ?', [$lotId]), 'part released leaves the lot partly released');
    $test->same(600.0, Models\Storage::onHand($lotId), '600 bags are left in the bay');
    $test->throws(static fn () => Models\LogisticsData::remove('storage_lots', $lotId, 'test'), 'a lot with gate passes cannot be deleted');

    // ------------------------------------------------------------- charges
    $lot = $pdo->query("SELECT * FROM storage_lots WHERE id = {$lotId}")->fetch();
    $charge = Models\Storage::charge($lot, $day(-10), $day(0));
    $test->same(4, $charge['days'], 'four days are charged after seven free days out of eleven');
    $test->same(2800.0, (float) $charge['units'], '1,000 bags on release day then 600 bags for three days is 2,800 bag-days');
    $test->same(28000.0, $charge['amount'], 'at 10 a bag a day that is 28,000');

    $bill = Models\Storage::bill($customerId);
    $test->assert($bill['ok'] && count($bill['invoices']) === 1, 'billing storage raises one draft invoice');
    $invoiceId = $bill['invoices'][0] ?? 0;
    $test->same(28000.0, (float) $one('SELECT subtotal FROM invoices WHERE id = ?', [$invoiceId]), 'the invoice carries the storage charge');
    $test->same('draft', (string) $one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]), 'finance checks it before it is issued');
    $test->same($day(0), (string) $one('SELECT billed_until FROM storage_lots WHERE id = ?', [$lotId]), 'the lot remembers it is billed up to today');
    $test->assert(!Models\Storage::bill($customerId)['ok'], 'billing again the same day charges nothing twice');

    $checked('storage_releases', [
        'gate_pass_no' => '', 'lot_id' => (string) $lotId, 'quantity' => '600', 'released_at' => $day(0) . ' 08:00:00', 'collected_by' => 'Eric Mugisha',
    ]);
    $test->same('released', (string) $one('SELECT status FROM storage_lots WHERE id = ?', [$lotId]), 'the last gate pass closes the lot');
    $test->same(0.0, Models\Storage::onHand($lotId), 'nothing is left in the bay');

    // Printed documents.
    ob_start();
    (new Controllers\OperationsController())->storageGrn(['id' => (string) $lotId]);
    $grn = (string) ob_get_clean();
    $test->assert(str_contains($grn, 'GOODS RECEIVED NOTE') && str_contains($grn, 'Cement 50 kg bags'), 'the GRN prints');
    ob_start();
    (new Controllers\OperationsController())->storagePass(['id' => (string) $passId]);
    $pass = (string) ob_get_clean();
    $test->assert(str_contains($pass, 'GATE PASS') && str_contains($pass, 'Eric Mugisha') && str_contains($pass, '600'), 'the gate pass prints with what was left after it');

    // ---------------------------------------------------------- containers
    $containerId = $checked('containers', [
        'tracking_code' => '', 'container_no' => 'MSCU 765432-1', 'container_size' => '40ft', 'customer_id' => (string) $customerId,
        'picked_up_at' => $day(-16) . ' 07:00:00', 'picked_up_from' => 'Dar es Salaam port', 'free_days' => '14',
        'detention_rate' => '50', 'currency' => 'USD', 'return_depot' => 'MSC empty depot, Kigali',
    ]);
    $container = $pdo->query("SELECT * FROM containers WHERE id = {$containerId}")->fetch();
    $test->same('out_full', (string) $container['status'], 'a container starts out full');
    $position = Models\Containers::position($container);
    $test->same(17, $position['days_out'], 'it has been out 17 days counting the day it left');
    $test->same(3, $position['detention_days'], 'three days are past the free time');
    $test->same(150.0, $position['detention'], 'at 50 a day that is 150 of detention');

    $test->throws(static fn () => $checked('containers', [
        'tracking_code' => 'X', 'container_no' => 'MSCU 1', 'picked_up_at' => $day(-1) . ' 07:00:00', 'free_days' => '14', 'returned_at' => $day(-5) . ' 07:00:00',
    ]), 'a container cannot come back before it left');

    // A second box, one free day left: warned about, not yet charged.
    $checked('containers', [
        'tracking_code' => '', 'container_no' => 'MAEU 111222-3', 'picked_up_at' => $day(-13) . ' 07:00:00', 'free_days' => '14', 'detention_rate' => '40', 'currency' => 'USD',
    ]);
    Models\Notifier::refreshOperationalAlerts();
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE title = 'Container on detention' AND message LIKE '%MSCU 765432-1%'") > 0, 'the office is warned the box is on detention');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE title = 'Container free time ending' AND message LIKE '%MAEU 111222-3%'") > 0, 'the office is warned before free time runs out');

    // The lists work out free days left; a box or a lot still inside its free
    // time must not break them (free_days is unsigned in the database).
    $checked('storage_lots', [
        'lot_code' => '', 'customer_id' => (string) $customerId, 'warehouse_id' => (string) $warehouseId, 'description' => 'Fresh lot',
        'quantity_received' => '10', 'unit' => 'Bags', 'received_at' => $day(0) . ' 08:00:00', 'free_days' => '7', 'charge_basis' => 'per_unit_day', 'daily_rate' => '1', 'currency' => 'RWF',
    ]);
    foreach (['storage_lots', 'containers'] as $listed) {
        try {
            $rows = Models\LogisticsData::listing($listed, [], current_context())['rows'];
            $test->assert($rows !== [], "the {$listed} list opens while something is inside its free days");
        } catch (Throwable $exception) {
            $test->assert(false, "the {$listed} list opens while something is inside its free days: " . $exception->getMessage());
        }
    }

    $test->assert(Models\Workflow::apply('containers', $containerId, 'return')['ok'], 'the empty return is recorded');
    $test->same('returned', (string) $one('SELECT status FROM containers WHERE id = ?', [$containerId]), 'the container is back');

    $disbursementId = Models\Containers::recharge($containerId);
    $disbursement = $pdo->query("SELECT * FROM disbursements WHERE id = {$disbursementId}")->fetch();
    $test->same('Container detention', (string) $disbursement['category'], 'detention becomes a disbursement');
    $test->same(150.0, (float) $disbursement['amount'], 'for the detention owed');
    $test->same('USD', (string) $disbursement['currency'], 'in the line\'s currency');
    $test->throws(static fn () => Models\Containers::recharge($containerId), 'it cannot be recharged twice');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
