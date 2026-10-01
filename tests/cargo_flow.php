<?php

declare(strict_types=1);

/**
 * One cargo job from the customer's order to the money in the bank, the way the
 * client walked through it at the review meeting:
 *
 *   LPO on the request -> approval -> trip -> shipment with container -> pre-dispatch
 *   check -> dispatch -> delivery note -> delivery -> trip completed -> invoice raised
 *   from the trip in the customer's currency -> issued -> paid in parts -> books
 *
 * plus the chart-of-accounts import and the scheduled report emails.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_cargo_flow');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Cargo flow tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $one = static function (string $sql, array $params = []) use ($pdo): mixed {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    };
    $row = static function (string $sql, array $params = []) use ($pdo): array {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: [];
    };

    // ------------------------------------------------------------ customer
    $customerId = Models\LogisticsData::save('customers', null, [
        'customer_code' => '', 'customer_name' => 'Flow Test Cement Ltd', 'customer_type' => 'corporate',
        'phone' => '+250 788 000 111', 'email' => 'flow@test.rw', 'payment_terms_days' => '15',
        'currency' => 'USD', 'status' => 'active',
    ]);
    $test->assert($customerId > 0, 'a USD customer can be created');

    // ------------------------------------------------- request with an LPO
    $requestId = Models\LogisticsData::save('requests', null, [
        'reference_code' => '', 'customer_id' => (string) $customerId, 'requested_by_contact' => 'Flow Buyer',
        'lpo_number' => 'LPO-FLOW-001', 'priority' => 'normal',
        'pickup_location' => 'Kigali', 'destination' => 'Goma', 'required_date' => date('Y-m-d', strtotime('+2 days')),
        'cargo_description' => 'Cement, 100 bags', 'weight_kg' => '800', 'packages_count' => '100',
        'currency' => 'USD', 'status' => 'pending',
    ]);
    $test->same('LPO-FLOW-001', (string) $one('SELECT lpo_number FROM transport_requests WHERE id = ?', [$requestId]), 'the request keeps the customer LPO number');
    $test->assert(Models\Workflow::apply('requests', $requestId, 'approve')['ok'], 'the customer accepting approves the request');

    // ------------------------------------------------------- trip + cargo
    $vehicleId = (int) $one("SELECT id FROM vehicles WHERE plate_number = 'RAB 332M'");
    $driverId = (int) $one("SELECT id FROM drivers WHERE license_number = 'RWA-DL-1028'");
    $pdo->prepare("UPDATE vehicles SET status = 'available' WHERE id = ?")->execute([$vehicleId]);
    $pdo->prepare("UPDATE drivers SET status = 'available', license_expiry = ? WHERE id = ?")->execute([date('Y-m-d', strtotime('+1 year')), $driverId]);
    $pdo->exec("UPDATE trips SET status = 'delivered' WHERE status IN ('in_transit','loading')");

    $tripId = Models\LogisticsData::save('trips', null, [
        'reference_code' => '', 'request_id' => (string) $requestId, 'customer_id' => (string) $customerId,
        'trip_type' => 'delivery', 'pickup_location' => 'Kigali', 'destination' => 'Goma',
        'vehicle_id' => (string) $vehicleId, 'driver_id' => (string) $driverId, 'status' => 'requested',
    ]);
    $test->same('assigned', (string) $one('SELECT status FROM transport_requests WHERE id = ?', [$requestId]), 'planning the trip assigns the request');

    $shipmentId = Models\LogisticsData::save('shipments', null, [
        'shipment_code' => '', 'customer_id' => (string) $customerId, 'request_id' => (string) $requestId, 'trip_id' => (string) $tripId,
        'consignee_name' => 'Goma Depot', 'origin' => 'Kigali', 'destination' => 'Goma',
        'cargo_type' => 'general', 'cargo_description' => 'Cement, 100 bags', 'packages_count' => '100', 'weight_kg' => '800',
        'currency' => 'USD', 'status' => 'booked',
        'container_no' => 'MSKU 123456-7', 'container_size' => '20ft', 'seal_no' => 'SEAL-99', 'trailer_plate' => 'RL 1234',
        'customer_reference' => 'LPO-FLOW-001',
    ]);
    $shipment = $row('SELECT * FROM shipments WHERE id = ?', [$shipmentId]);
    $test->same('MSKU 123456-7', (string) $shipment['container_no'], 'the shipment stores its container number');
    $test->same('LPO-FLOW-001', (string) $shipment['customer_reference'], 'the shipment carries the LPO');
    // The quote normally comes from the customer's rate card; fix it so the invoice test is exact.
    $pdo->prepare('UPDATE shipments SET quoted_amount = 450.00 WHERE id = ?')->execute([$shipmentId]);

    // ------------------------------------------- pre-dispatch check (bug fix)
    $errors = [];
    try {
        $errors = Models\LogisticsData::validate('pre_dispatch_checks', ['shipment_id' => (string) $shipmentId, 'status' => 'ready', 'cargo_loaded' => '1']);
        $test->assert(true, 'marking a pre-dispatch check ready no longer crashes');
    } catch (Throwable $exception) {
        $test->assert(false, 'marking a pre-dispatch check ready crashed: ' . $exception->getMessage());
    }
    $test->assert(in_array('Every final pre-dispatch item must be confirmed before marking this shipment ready.', $errors, true), 'an incomplete pre-dispatch check is refused with a clear message');

    // ------------------------------------------------------------ dispatch
    $dispatch = Models\Workflow::apply('trips', $tripId, 'dispatch');
    $test->assert($dispatch['ok'], 'the trip dispatches: ' . $dispatch['message']);
    $test->same('on_trip', (string) $one('SELECT status FROM vehicles WHERE id = ?', [$vehicleId]), 'dispatch marks the truck on trip');
    $test->same('in_transit', (string) $one('SELECT status FROM shipments WHERE id = ?', [$shipmentId]), 'dispatch puts the cargo in transit');

    // ------------------------------------------- delivery + delivery note
    $deliveryId = Models\LogisticsData::save('deliveries', null, [
        'delivery_code' => '', 'trip_id' => (string) $tripId, 'shipment_id' => (string) $shipmentId, 'attempt_number' => '1',
        'recipient_name' => 'Goma Depot', 'destination' => 'Goma', 'status' => 'in_transit', 'failure_reason' => 'none',
    ]);

    ob_start();
    (new Controllers\OperationsController())->deliveryNote(['id' => (string) $deliveryId]);
    $note = (string) ob_get_clean();
    $test->assert(str_contains($note, 'DELIVERY NOTE'), 'the delivery note renders');
    $test->assert(str_contains($note, 'MSKU 123456-7') && str_contains($note, 'SEAL-99'), 'the delivery note prints the container and seal');
    $test->assert(str_contains($note, 'LPO-FLOW-001'), 'the delivery note prints the customer LPO');
    $test->assert(str_contains($note, 'RAB 332M'), 'the delivery note prints the truck');

    $test->assert(Models\Workflow::apply('deliveries', $deliveryId, 'complete')['ok'], 'the delivery can be completed');
    $test->assert(Models\Workflow::apply('trips', $tripId, 'complete')['ok'], 'the trip can be completed');
    $test->same('available', (string) $one('SELECT status FROM vehicles WHERE id = ?', [$vehicleId]), 'completing the trip frees the truck');

    // --------------------------------------------- invoice from the trip
    $billing = Models\Billing::invoiceTrip($tripId);
    $test->assert($billing['ok'] && count($billing['invoices']) === 1 && $billing['invoices'][0]['created'], 'one draft invoice is raised from the trip');
    $invoiceId = $billing['invoices'][0]['id'];
    $invoice = $row('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
    $test->same('USD', (string) $invoice['currency'], 'the invoice is in the customer currency');
    $test->same('LPO-FLOW-001', (string) $invoice['lpo_number'], 'the invoice carries the LPO');
    $test->same('draft', (string) $invoice['status'], 'the invoice waits for finance as a draft');
    $test->same(450.0, (float) $invoice['subtotal'], 'the line is priced at the quote');
    $test->same(date('Y-m-d', strtotime('+15 days')), (string) $invoice['due_date'], 'the due date follows the customer payment terms');
    $test->same(531.0, (float) $invoice['total_amount'], 'VAT is added to the total');

    $again = Models\Billing::invoiceTrip($tripId);
    $test->assert($again['ok'] && !$again['invoices'][0]['created'], 'pressing it twice does not raise a second invoice');

    $test->assert(Models\Workflow::apply('invoices', $invoiceId, 'approve')['ok'], 'finance can issue the invoice');
    $test->same('USD', (string) $one("SELECT currency FROM gl_journal_entries WHERE source_type = 'invoice' AND source_id = ?", [$invoiceId]), 'the issued invoice posts to the USD books');

    // ------------------------------------------------------------ payments
    $over = Models\LogisticsData::validate('payments', ['payment_code' => '', 'invoice_id' => (string) $invoiceId, 'amount' => '600', 'method' => 'bank_transfer', 'paid_at' => date('Y-m-d H:i')]);
    $test->assert((bool) array_filter($over, static fn (string $e): bool => str_contains($e, 'still owed')), 'a payment larger than the balance is refused');

    $firstPayment = Models\LogisticsData::save('payments', null, ['payment_code' => '', 'invoice_id' => (string) $invoiceId, 'amount' => '200', 'method' => 'bank_transfer', 'paid_at' => date('Y-m-d H:i')]);
    $test->same('USD', (string) $one('SELECT currency FROM payments WHERE id = ?', [$firstPayment]), 'the payment takes the invoice currency by itself');
    $test->same('partially_paid', (string) $one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]), 'a part payment leaves the invoice partially paid');

    Models\LogisticsData::save('payments', null, ['payment_code' => '', 'invoice_id' => (string) $invoiceId, 'amount' => '331', 'method' => 'bank_transfer', 'paid_at' => date('Y-m-d H:i')]);
    $test->same('paid', (string) $one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]), 'the balance settles the invoice');
    $test->assert(Models\Ledger::isBalanced(), 'the books still balance after the whole job');

    // ---------------------------------------- fuel in another currency
    $fuelId = Models\LogisticsData::save('fuel', null, [
        'reference_code' => '', 'vehicle_id' => (string) $vehicleId, 'station_name' => 'Nyakanazi', 'fuel_type' => 'diesel',
        'purchased_at' => date('Y-m-d H:i'), 'litres' => '100', 'unit_price' => '2950', 'currency' => 'TZS',
        'mileage' => (string) ((int) $one('SELECT mileage FROM vehicles WHERE id = ?', [$vehicleId]) + 50), 'trip_id' => (string) $tripId,
    ]);
    $test->same('TZS', (string) $one('SELECT currency FROM fuel_records WHERE id = ?', [$fuelId]), 'fuel can be recorded in the currency it was paid in');

    // ------------------------------------------- chart of accounts import
    $csv = tempnam(sys_get_temp_dir(), 'coa');
    file_put_contents($csv, "Account code,Account name,Type,Statement section,Opening debit,Opening credit\n"
        . "6610,Security and guarding,expense,Operating expenses,,\n"
        . "1021,Bank - USD account,asset,Cash and bank,5000,\n"
        . "2510,Director loan,liability,Long term liabilities,,5000\n");
    $_FILES['file'] = ['name' => 'chart.csv', 'tmp_name' => $csv, 'size' => filesize($csv), 'error' => UPLOAD_ERR_OK];
    $_POST = ['opening_date' => date('Y') . '-01-01', 'update_existing' => '1'];
    ob_start();
    (new Controllers\ImportController())->accountsImport();
    $page = (string) ob_get_clean();
    $test->assert(str_contains($page, 'Import finished'), 'the chart of accounts import finishes');
    $test->same('Security and guarding', (string) $one("SELECT account_name FROM gl_accounts WHERE account_code = '6610'"), 'imported accounts are added');
    $test->same('Cash and bank', (string) $one("SELECT report_section FROM gl_accounts WHERE account_code = '1021'"), 'the statement section is taken from the file');
    $test->assert((int) $one("SELECT COUNT(*) FROM gl_journal_entries WHERE source_type = 'opening_balance'") === 1, 'opening balances post as one entry');
    $test->assert(Models\Ledger::isBalanced(), 'the books balance after importing opening balances');

    file_put_contents($csv, "Account code,Account name,Type\n6620,,nonsense\n");
    $_FILES['file']['size'] = filesize($csv);
    ob_start();
    (new Controllers\ImportController())->accountsImport();
    $bad = (string) ob_get_clean();
    $test->assert(str_contains($bad, 'Nothing was imported'), 'a wrong file is refused and nothing is saved');
    $test->same(false, $one("SELECT id FROM gl_accounts WHERE account_code = '6620'"), 'a refused file adds no account');
    unlink($csv);

    // ----------------------------------------------- scheduled report emails
    $scheduleId = (int) $one("SELECT id FROM report_subscriptions WHERE report_key = 'trip_profitability' LIMIT 1");
    $test->assert($scheduleId > 0, 'the agreed report schedules are installed');
    $sent = Models\ReportSchedule::send($scheduleId);
    $test->assert($sent['sent'] > 0, 'a scheduled report is written to the outbox for each recipient: ' . $sent['status']);
    $test->assert((int) $one("SELECT COUNT(*) FROM email_outbox WHERE entity_type = 'report_emails'") > 0, 'the report email is in the outbox');
    $test->assert(!Models\ReportSchedule::isDue($row('SELECT * FROM report_subscriptions WHERE id = ?', [$scheduleId])), 'a schedule just sent is not due again the same day');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
