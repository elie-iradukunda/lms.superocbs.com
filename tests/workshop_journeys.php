<?php

declare(strict_types=1);

/**
 * The garage and the long haul:
 *
 *   a job card in our own workshop, brake pads off our shelf and two hours of
 *   labour -> refused while the shelf is short -> approved -> pads leave the
 *   store, the books split Inventory from Payables, the truck gets its next
 *   service date;
 *   Dar es Salaam -> Rusumo -> Kigali as a journey of two legs -> the first leg
 *   cannot finish while carrying the cargo -> handover -> the second leg
 *   delivers -> the journey completes and is billed once.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_workshop');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Workshop and journey tests');
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

    // ------------------------------------------------------------ workshop
    $warehouseId = (int) $one("SELECT id FROM warehouses WHERE deleted_at IS NULL AND status = 'active' ORDER BY id LIMIT 1");
    $itemId = $checked('warehouse', [
        'sku' => 'BRK-PAD-01', 'item_name' => 'Brake pads (set)', 'warehouse_id' => (string) $warehouseId, 'category' => 'Spare parts',
        'unit_of_measure' => (string) array_key_first(Models\Lookup::options('unit_of_measure')), 'status' => 'in_stock', 'quantity' => '10', 'minimum_level' => '2', 'unit_cost' => '25000',
    ]);
    $test->same(10.0, (float) $one('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]), 'ten sets of pads are on the shelf');

    $vehicleId = (int) $one("SELECT id FROM vehicles WHERE deleted_at IS NULL ORDER BY id LIMIT 1");
    $orderId = $checked('maintenance', [
        'work_order_code' => '', 'vehicle_id' => (string) $vehicleId, 'service_name' => 'Brake overhaul', 'maintenance_type' => 'corrective',
        'priority' => 'high', 'workshop' => 'in_house', 'complaint' => 'Brakes pulling left', 'diagnosis' => 'Front pads worn',
        'work_done' => 'Replaced front and rear pads', 'labour_hours' => '2', 'estimated_cost' => '110000', 'status' => 'in_progress',
        'due_date' => date('Y-m-d'), 'odometer_reading' => '182500', 'next_service_date' => date('Y-m-d', strtotime('+90 days')),
    ]);
    $test->same('in_house', (string) $one('SELECT workshop FROM maintenance_orders WHERE id = ?', [$orderId]), 'the job card is in our own workshop');

    Models\LogisticsData::saveLines('maintenance', $orderId, [
        ['line_type' => 'part', 'item_id' => (string) $itemId, 'part_name' => 'Brake pads', 'quantity' => '20', 'unit_cost' => ''],
        ['line_type' => 'labour', 'part_name' => 'Mechanic labour', 'quantity' => '2', 'unit_cost' => '10000'],
    ]);
    $test->same(25000.0, (float) $one('SELECT unit_cost FROM maintenance_parts WHERE maintenance_id = ? AND item_id IS NOT NULL', [$orderId]), 'a stock part with no price takes the stock card cost');
    $test->assert(!Models\Workflow::apply('maintenance', $orderId, 'approve')['ok'], 'the job cannot close while it needs more pads than the shelf holds');
    $test->same(10.0, (float) $one('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]), 'a refused approval leaves the shelf alone');

    Models\LogisticsData::saveLines('maintenance', $orderId, [
        ['line_type' => 'part', 'item_id' => (string) $itemId, 'part_name' => 'Brake pads', 'quantity' => '4', 'unit_cost' => ''],
        ['line_type' => 'labour', 'part_name' => 'Mechanic labour', 'quantity' => '2', 'unit_cost' => '10000'],
    ]);
    $test->same(120000.0, (float) $one('SELECT actual_cost FROM maintenance_orders WHERE id = ?', [$orderId]), 'the job costs 4 sets at 25,000 plus 2 hours at 10,000');
    $result = Models\Workflow::apply('maintenance', $orderId, 'approve');
    $test->assert($result['ok'], 'the fleet manager approves the cost');
    $test->assert(str_contains($result['message'], 'taken out of stock'), 'the message says the parts left the store');
    $test->same(6.0, (float) $one('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]), 'four sets left the shelf');
    $test->same(1, (int) $one("SELECT COUNT(*) FROM stock_movements WHERE reference_type = 'maintenance' AND item_id = ?", [$itemId]), 'one stock movement names the work order');

    $lines = [];
    foreach ($pdo->query("SELECT a.account_code, l.debit - l.credit AS net FROM gl_journal_entries e
                            INNER JOIN gl_journal_lines l ON l.entry_id = e.id INNER JOIN gl_accounts a ON a.id = l.account_id
                           WHERE e.source_type = 'maintenance' AND e.source_id = {$orderId} AND e.deleted_at IS NULL")->fetchAll() as $line) {
        $lines[$line['account_code']] = round((float) $line['net'], 2);
    }
    ksort($lines);
    $test->same(['1200' => -100000.0, '2000' => -20000.0, '6000' => 120000.0], $lines, 'the pads come out of Inventory and only the labour is owed');
    $test->same(date('Y-m-d', strtotime('+90 days')), (string) $one('SELECT next_service_date FROM vehicles WHERE id = ?', [$vehicleId]), 'the truck takes the next service date from the card');

    // ------------------------------------------------------------- journey
    $customerId = $checked('customers', [
        'customer_code' => '', 'customer_name' => 'Journey Test Hardware Ltd', 'customer_type' => 'corporate',
        'phone' => '+250 788 500 600', 'email' => 'journey@test.rw', 'payment_terms_days' => '30', 'currency' => 'USD', 'status' => 'active',
    ]);
    $journeyId = $checked('journeys', [
        'journey_code' => '', 'customer_id' => (string) $customerId, 'origin' => 'Dar es Salaam port', 'route_via' => 'Rusumo',
        'final_destination' => 'Kigali', 'planned_start' => date('Y-m-d'),
    ]);
    $test->same('planned', (string) $one('SELECT status FROM journeys WHERE id = ?', [$journeyId]), 'a new journey is planned');

    $drivers = $pdo->query('SELECT id FROM drivers WHERE deleted_at IS NULL ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    $leg1 = $checked('trips', [
        'reference_code' => '', 'journey_id' => (string) $journeyId, 'leg_no' => '1', 'customer_id' => (string) $customerId, 'trip_type' => 'delivery',
        'pickup_location' => 'Dar es Salaam port', 'destination' => 'Rusumo', 'driver_id' => (string) $drivers[0], 'status' => 'requested',
    ]);
    $next = Models\Journey::nextLegDefaults($journeyId);
    $test->same('Rusumo', $next['pickup_location'] ?? null, 'the next leg starts where the first one ends');
    $test->same(2, $next['leg_no'] ?? null, 'and is leg 2');
    $leg2 = $checked('trips', [
        'reference_code' => '', 'journey_id' => (string) $journeyId, 'leg_no' => '2', 'customer_id' => (string) $customerId, 'trip_type' => 'delivery',
        'pickup_location' => 'Rusumo', 'destination' => 'Kigali', 'driver_id' => (string) $drivers[1], 'status' => 'requested',
    ]);

    $shipmentId = $checked('shipments', [
        'shipment_code' => '', 'customer_id' => (string) $customerId, 'trip_id' => (string) $leg1, 'consignee_name' => 'Journey Test Hardware',
        'origin' => 'Dar es Salaam port', 'destination' => 'Kigali', 'cargo_type' => 'general', 'cargo_description' => 'Roofing sheets',
        'packages_count' => '200', 'weight_kg' => '15000', 'currency' => 'USD', 'status' => 'booked',
    ]);
    $pdo->prepare('UPDATE shipments SET quoted_amount = 4200 WHERE id = ?')->execute([$shipmentId]);

    // The first truck is on the road.
    $pdo->prepare("UPDATE trips SET status = 'in_transit', departure_at = NOW() WHERE id = ?")->execute([$leg1]);
    $pdo->prepare("UPDATE shipments SET status = 'in_transit' WHERE id = ?")->execute([$shipmentId]);
    Models\Journey::syncForTrip($leg1);
    $test->same('in_progress', (string) $one('SELECT status FROM journeys WHERE id = ?', [$journeyId]), 'the journey is in progress once a leg moves');

    $refused = Models\Workflow::apply('trips', $leg1, 'complete');
    $test->assert(!$refused['ok'] && str_contains($refused['message'], 'Hand cargo to next leg'), 'leg 1 cannot finish with the cargo still aboard');
    $test->same('in_transit', (string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]), 'so the cargo is not "delivered" at the border');

    $handover = Models\Journey::handOver($leg1);
    $test->assert($handover['ok'], 'the cargo is handed to the second truck at Rusumo');
    $test->same($leg2, (int) $one('SELECT trip_id FROM shipments WHERE id = ?', [$shipmentId]), 'the shipment is now on leg 2');
    $test->same('loaded', (string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]), 'and loaded, waiting for leg 2 to leave');
    $test->same(2, (int) $one('SELECT COUNT(*) FROM shipment_legs WHERE shipment_id = ?', [$shipmentId]), 'its path through both trucks is kept');
    $test->same('Rusumo', (string) $one('SELECT handover_point FROM shipment_legs WHERE shipment_id = ? AND trip_id = ?', [$shipmentId, $leg1]), 'with where it changed trucks');

    $test->assert(Models\Workflow::apply('trips', $leg1, 'complete')['ok'], 'leg 1 now completes');
    $test->same('loaded', (string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]), 'completing leg 1 does not touch cargo on leg 2');

    $pdo->prepare("UPDATE trips SET status = 'in_transit', departure_at = NOW() WHERE id = ?")->execute([$leg2]);
    $test->assert(Models\Workflow::apply('trips', $leg2, 'complete')['ok'], 'leg 2 arrives in Kigali');
    $test->same('delivered', (string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]), 'the cargo is delivered at the end of the last leg');
    $test->same('completed', (string) $one('SELECT status FROM journeys WHERE id = ?', [$journeyId]), 'the journey completes with its last leg');

    $billing = Models\Billing::invoiceTrip($leg2);
    $test->assert($billing['ok'] && count($billing['invoices']) === 1, 'billing a leg bills the whole journey once');
    $invoice = $pdo->query('SELECT * FROM invoices WHERE id = ' . (int) ($billing['invoices'][0]['id'] ?? 0))->fetch();
    $test->same($journeyId, (int) $invoice['journey_id'], 'the invoice carries the journey');
    $test->same($leg1, (int) $invoice['trip_id'], 'and sits on the first leg');
    $test->same(4200.0, (float) $invoice['subtotal'], 'at the quoted price for the shipment');
    $again = Models\Billing::invoiceTrip($leg1);
    $test->assert(($again['invoices'][0]['created'] ?? true) === false, 'billing again finds the existing invoice');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
