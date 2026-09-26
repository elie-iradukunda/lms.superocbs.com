<?php

declare(strict_types=1);

/**
 * The operational rules: status transitions, the guards that block an unsafe
 * dispatch, and the side effects that keep connected records in step.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_workflow');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Workflow tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $scalar = static fn (string $sql, array $p = []): mixed => (static function () use ($pdo, $sql, $p) {
        $statement = $pdo->prepare($sql);
        $statement->execute($p);
        return $statement->fetchColumn();
    })();

    $customerId = (int) $scalar("SELECT id FROM customers WHERE customer_code = 'CUS-2026-0001'");
    $coolingVehicle = (int) $scalar("SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'");   // has a cooling unit
    $plainVehicle = (int) $scalar("SELECT id FROM vehicles WHERE plate_number = 'RAB 118K'");      // no cooling unit, 1200 kg
    $driverA = (int) $scalar("SELECT id FROM drivers WHERE license_number = 'RWA-DL-1028'");
    $driverB = (int) $scalar("SELECT id FROM drivers WHERE license_number = 'RWA-DL-1102'");

    // ------------------------------------------------- request approval chain
    $requestId = Models\LogisticsData::save('requests', null, [
        'reference_code' => 'REQ-WF-001',
        'customer_id' => (string) $customerId,
        'pickup_location' => 'Kigali Central Warehouse',
        'destination' => 'Huye Depot',
        'required_date' => date('Y-m-d', strtotime('+2 days')),
        'priority' => 'high',
        'status' => 'pending',
        'cargo_description' => 'Test consignment',
        'weight_kg' => '800',
    ]);

    $result = Models\Workflow::apply('requests', $requestId, 'approve');
    $test->assert($result['ok'], 'a pending request can be approved');
    $test->assert($scalar('SELECT approved_by FROM transport_requests WHERE id = ?', [$requestId]) !== null, 'approving records who approved it');
    $test->assert($scalar('SELECT approved_at FROM transport_requests WHERE id = ?', [$requestId]) !== null, 'approving records when it was approved');

    $again = Models\Workflow::apply('requests', $requestId, 'approve');
    $test->assert(!$again['ok'], 'an already approved request cannot be approved twice');

    $test->assert(
        (int) $scalar("SELECT COUNT(*) FROM notifications WHERE role_key = 'logistics_manager' AND entity_id = 'REQ-WF-001'") >= 1,
        'approving a request notifies the logistics manager'
    );

    // -------------------------------------------------------- dispatch guards
    $tripId = Models\LogisticsData::save('trips', null, [
        'reference_code' => 'TRP-WF-001',
        'request_id' => (string) $requestId,
        'customer_id' => (string) $customerId,
        'pickup_location' => 'Kigali Central Warehouse',
        'destination' => 'Huye Depot',
        'planned_departure_at' => date('Y-m-d H:i:s'),
        'planned_arrival_at' => date('Y-m-d H:i:s', strtotime('+5 hours')),
        'status' => 'approved',
        'trip_type' => 'delivery',
    ]);

    $noResources = Models\Workflow::apply('trips', $tripId, 'dispatch');
    $test->assert(!$noResources['ok'], 'a trip with no vehicle or driver cannot be dispatched');
    $test->assert(str_contains($noResources['message'], 'vehicle and a driver'), 'the refusal explains what is missing');

    // A cold-chain shipment on a vehicle with no cooling unit must be refused.
    $pdo->prepare('UPDATE trips SET vehicle_id = ?, driver_id = ? WHERE id = ?')->execute([$plainVehicle, $driverA, $tripId]);
    Models\LogisticsData::save('shipments', null, [
        'shipment_code' => 'SHP-WF-001',
        'trip_id' => (string) $tripId,
        'customer_id' => (string) $customerId,
        'consignee_name' => 'Huye Depot Store',
        'origin' => 'Kigali Central Warehouse',
        'destination' => 'Huye Depot',
        'cargo_type' => 'cold_chain',
        'cargo_description' => 'Chilled dairy',
        'packages_count' => '10',
        'weight_kg' => '400',
        'temperature_min_c' => '2',
        'temperature_max_c' => '8',
        'status' => 'booked',
    ]);

    $noCooling = Models\Workflow::apply('trips', $tripId, 'dispatch');
    $test->assert(!$noCooling['ok'], 'cold-chain cargo cannot be dispatched on a vehicle with no cooling unit');
    $test->assert(str_contains($noCooling['message'], 'cooling unit'), 'the refusal names the cooling unit');

    // Overweight for the vehicle must also be refused.
    $pdo->prepare('UPDATE vehicles SET has_cooling_unit = 1 WHERE id = ?')->execute([$plainVehicle]);
    $pdo->prepare("UPDATE shipments SET weight_kg = 5000 WHERE shipment_code = 'SHP-WF-001'")->execute();
    $overweight = Models\Workflow::apply('trips', $tripId, 'dispatch');
    $test->assert(!$overweight['ok'], 'a load heavier than the vehicle capacity is refused');
    $test->assert(str_contains($overweight['message'], 'carries'), 'the refusal compares load against capacity');

    // ------------------------------------------------------ successful dispatch
    $pdo->prepare("UPDATE shipments SET weight_kg = 400 WHERE shipment_code = 'SHP-WF-001'")->execute();
    $pdo->prepare('UPDATE trips SET vehicle_id = ?, driver_id = ? WHERE id = ?')->execute([$coolingVehicle, $driverA, $tripId]);

    $deliveryId = Models\LogisticsData::save('deliveries', null, [
        'delivery_code' => 'DEL-WF-001',
        'trip_id' => (string) $tripId,
        'recipient_name' => 'Huye Depot Store',
        'destination' => 'Huye Depot',
        'status' => 'loading',
        'attempt_number' => '1',
    ]);

    $dispatched = Models\Workflow::apply('trips', $tripId, 'dispatch');
    $test->assert($dispatched['ok'], 'a properly resourced trip dispatches: ' . $dispatched['message']);
    $test->same('on_trip', (string) $scalar('SELECT status FROM vehicles WHERE id = ?', [$coolingVehicle]), 'dispatch puts the vehicle on trip');
    $test->same('on_trip', (string) $scalar('SELECT status FROM drivers WHERE id = ?', [$driverA]), 'dispatch puts the driver on trip');
    $test->same('assigned', (string) $scalar('SELECT status FROM transport_requests WHERE id = ?', [$requestId]), 'dispatch moves the request to assigned');
    $test->same('in_transit', (string) $scalar("SELECT status FROM shipments WHERE shipment_code = 'SHP-WF-001'"), 'dispatch puts the shipment in transit');
    $test->same('in_transit', (string) $scalar('SELECT status FROM deliveries WHERE id = ?', [$deliveryId]), 'dispatch puts the delivery in transit');
    $test->assert($scalar('SELECT dispatched_at FROM trips WHERE id = ?', [$tripId]) !== null, 'dispatch stamps the dispatch time');

    // ------------------------------------------------------- double booking
    $clashId = Models\LogisticsData::save('trips', null, [
        'reference_code' => 'TRP-WF-002',
        'pickup_location' => 'Kigali',
        'destination' => 'Musanze',
        'status' => 'approved',
    ]);
    $pdo->prepare('UPDATE trips SET vehicle_id = ?, driver_id = ? WHERE id = ?')->execute([$coolingVehicle, $driverB, $clashId]);

    $clash = Models\Workflow::apply('trips', $clashId, 'dispatch');
    $test->assert(!$clash['ok'], 'a vehicle already out on another trip cannot be dispatched again');
    $test->assert(str_contains($clash['message'], 'TRP-WF-001'), 'the refusal names the trip holding the vehicle');

    // --------------------------------------------------------- completion
    Models\Workflow::apply('deliveries', $deliveryId, 'complete');
    $test->same('delivered', (string) $scalar('SELECT status FROM deliveries WHERE id = ?', [$deliveryId]), 'a delivery can be completed');
    $test->assert($scalar('SELECT delivered_at FROM deliveries WHERE id = ?', [$deliveryId]) !== null, 'completing a delivery stamps the time');

    $completed = Models\Workflow::apply('trips', $tripId, 'complete');
    $test->assert($completed['ok'], 'an in-transit trip can be completed');
    $test->same('available', (string) $scalar('SELECT status FROM vehicles WHERE id = ?', [$coolingVehicle]), 'completing a trip releases the vehicle');
    $test->same('available', (string) $scalar('SELECT status FROM drivers WHERE id = ?', [$driverA]), 'completing a trip releases the driver');
    $test->assert(str_contains($completed['message'], 'on time') || str_contains($completed['message'], 'after the agreed'), 'completing reports the on-time result');

    // ------------------------------------------------------ reason required
    $noReason = Models\Workflow::apply('trips', $clashId, 'reject', '');
    $test->assert(!$noReason['ok'], 'cancelling without a reason is refused');
    $cancelled = Models\Workflow::apply('trips', $clashId, 'reject', 'No load available.');
    $test->assert($cancelled['ok'], 'cancelling with a reason succeeds');

    // ---------------------------------------------------- expense approval
    test_sign_in($accounts['driver'], 'driver', 2);
    $expenseId = Models\LogisticsData::save('expenses', null, [
        'reference_code' => 'EXP-WF-001',
        'category' => 'Allowance',
        'amount' => '45000',
        'expense_date' => date('Y-m-d'),
        'trip_id' => (string) $tripId,
        'status' => 'pending',
        'payment_method' => 'cash',
    ]);
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $test->same(
        (int) $accounts['driver']['id'],
        (int) $scalar('SELECT submitted_by FROM expenses WHERE id = ?', [$expenseId]),
        'a claim is filed under whoever was signed in, not whoever the form named'
    );

    $approved = Models\Workflow::apply('expenses', $expenseId, 'approve');
    $test->assert($approved['ok'], 'a pending expense can be approved');
    $test->assert($scalar('SELECT approved_by FROM expenses WHERE id = ?', [$expenseId]) !== null, 'the approver is recorded on the expense');
    $test->assert(
        (int) $scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = ?', [$accounts['driver']['id'], 'Expense approved']) === 1,
        'the person who submitted the expense is told it was approved'
    );

    // ------------------------------------------------------- stock ledger
    $itemId = (int) $scalar("SELECT id FROM inventory_items WHERE sku = 'SP-BRK-001'");
    $before = (float) $scalar('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]);

    Models\StockLedger::record($itemId, 'stock_in', 10, 24000.0, 'adjustment', 'WF-TEST');
    $test->same($before + 10, (float) $scalar('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]), 'stock in raises the balance');

    Models\StockLedger::record($itemId, 'stock_out', 4, null, 'adjustment', 'WF-TEST');
    $test->same($before + 6, (float) $scalar('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]), 'stock out lowers the balance');

    $test->throws(
        static fn () => Models\StockLedger::record($itemId, 'stock_out', 100000),
        'a movement that would take stock below zero is refused'
    );

    $balanceAfter = (float) $scalar('SELECT balance_after FROM stock_movements WHERE item_id = ? ORDER BY id DESC LIMIT 1', [$itemId]);
    $test->same($before + 6, $balanceAfter, 'the ledger stores the running balance');

    // A low balance flips the item into reorder on its own.
    $minimum = (float) $scalar('SELECT minimum_level FROM inventory_items WHERE id = ?', [$itemId]);
    Models\StockLedger::record($itemId, 'stock_out', ($before + 6) - $minimum, null, 'adjustment', 'WF-TEST');
    $test->same('reorder', (string) $scalar('SELECT status FROM inventory_items WHERE id = ?', [$itemId]), 'a balance at the minimum sets the item to reorder');

    // ------------------------------------------------- procurement receipt
    $prId = (int) $scalar("SELECT id FROM purchase_requests WHERE request_code = 'PR-0091'");
    $pdo->prepare("UPDATE purchase_requests SET status = 'approved', warehouse_id = 1 WHERE id = ?")->execute([$prId]);
    $pdo->prepare('UPDATE purchase_request_lines SET received_quantity = 0 WHERE purchase_request_id = ?')->execute([$prId]);

    $stockBefore = (float) $scalar('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]);
    $received = Models\Workflow::apply('procurement', $prId, 'receive');
    $test->assert($received['ok'], 'an approved purchase request can be received');
    $test->same($stockBefore + 20, (float) $scalar('SELECT quantity FROM inventory_items WHERE id = ?', [$itemId]), 'receiving goods posts the line quantity into stock');
    $test->assert($scalar('SELECT received_at FROM purchase_requests WHERE id = ?', [$prId]) !== null, 'receiving stamps the receipt time');

    // ------------------------------------------------------------ invoices
    $emptyInvoiceId = Models\LogisticsData::save('invoices', null, [
        'invoice_number' => 'INV-WF-001',
        'customer_id' => (string) $customerId,
        'issue_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'tax_rate' => '18',
        'status' => 'draft',
    ]);
    $emptyIssue = Models\Workflow::apply('invoices', $emptyInvoiceId, 'approve');
    $test->assert(!$emptyIssue['ok'], 'an invoice with no lines cannot be issued');

    Models\LogisticsData::saveLines('invoices', $emptyInvoiceId, [
        ['description' => 'Transport Kigali to Huye', 'quantity' => '1', 'unit_price' => '400000'],
        ['description' => 'Handling', 'quantity' => '2', 'unit_price' => '25000'],
        ['description' => '', 'quantity' => '', 'unit_price' => ''],
    ]);

    $test->same(450000.0, (float) $scalar('SELECT subtotal FROM invoices WHERE id = ?', [$emptyInvoiceId]), 'the invoice subtotal is rebuilt from its lines');
    $test->same(81000.0, (float) $scalar('SELECT tax_amount FROM invoices WHERE id = ?', [$emptyInvoiceId]), 'VAT is calculated from the subtotal');
    $test->same(531000.0, (float) $scalar('SELECT total_amount FROM invoices WHERE id = ?', [$emptyInvoiceId]), 'the invoice total includes VAT');
    $test->same(2, (int) $scalar('SELECT COUNT(*) FROM invoice_lines WHERE invoice_id = ?', [$emptyInvoiceId]), 'blank line rows are ignored');

    $issued = Models\Workflow::apply('invoices', $emptyInvoiceId, 'approve');
    $test->assert($issued['ok'], 'an invoice with lines can be issued');

    // --------------------------------------------- maintenance cost rollup
    $orderId = (int) $scalar("SELECT id FROM maintenance_orders WHERE work_order_code = 'MNT-0079'");
    $pdo->prepare("UPDATE maintenance_orders SET status = 'in_progress', actual_cost = NULL WHERE id = ?")->execute([$orderId]);
    Models\LogisticsData::saveLines('maintenance', $orderId, [
        ['line_type' => 'part', 'part_name' => 'Fuel filter', 'part_number' => 'FF-1', 'quantity' => '2', 'unit_cost' => '21000'],
        ['line_type' => 'labour', 'part_name' => 'Labour', 'part_number' => '', 'quantity' => '3', 'unit_cost' => '12000'],
    ]);
    $done = Models\Workflow::apply('maintenance', $orderId, 'approve');
    $test->assert($done['ok'], 'a work order in progress can be completed');
    $test->same(78000.0, (float) $scalar('SELECT actual_cost FROM maintenance_orders WHERE id = ?', [$orderId]), 'the actual cost comes from the parts and labour lines');

    // --------------------------------------------------- trip profitability
    $pdo->prepare('UPDATE invoices SET trip_id = ? WHERE id = ?')->execute([$tripId, $emptyInvoiceId]);
    $report = Models\ReportData::run('trip_profitability', date('Y-m-d', strtotime('-1 year')), date('Y-m-d'));
    $row = null;
    foreach ($report['rows'] as $candidate) {
        if ($candidate['reference_code'] === 'TRP-WF-001') {
            $row = $candidate;
            break;
        }
    }
    $test->assert($row !== null, 'the trip appears in the profitability report');
    if ($row !== null) {
        // Revenue is the invoice net of VAT: 450,000 of the 531,000 billed.
        // The 81,000 tax is collected for the revenue authority, not earned.
        $test->same(450000.0, (float) $row['revenue'], 'the report reads revenue from the linked invoice, net of VAT');
        $test->same(45000.0, (float) $row['total_cost'], 'the report reads cost from the approved expense');
        $test->same(405000.0, (float) $row['margin'], 'the report works out the margin');
    }

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
