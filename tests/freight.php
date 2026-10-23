<?php

declare(strict_types=1);

/**
 * Freight forwarding: a sea job from booking to delivery, its shipment, the
 * milestones the customer is emailed, the rules at each step, the customer's
 * tracking page by job code, and a cargo claim.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_freight');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Freight forwarding tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $one = static function (string $sql, array $params = []) use ($pdo): mixed {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    };

    $customerId = Models\LogisticsData::save('customers', null, [
        'customer_code' => '', 'customer_name' => 'Mombasa Importers Ltd', 'customer_type' => 'corporate', 'contact_name' => 'Amina Odhiambo',
        'phone' => '+254 722 000 111', 'email' => 'imports@mombasa-test.ke', 'payment_terms_days' => '30', 'currency' => 'USD', 'status' => 'active',
    ]);

    // ----------------------------------------------------------- the job
    $jobId = Models\LogisticsData::save('freight_jobs', null, [
        'job_code' => '', 'customer_id' => (string) $customerId, 'direction' => 'import', 'mode' => 'sea', 'scope' => 'door_to_door', 'incoterm' => 'CIF',
        'load_type' => 'fcl', 'commodity' => 'Building materials', 'packages' => '1', 'weight_kg' => '24000', 'containers_count' => '1',
        'container_numbers' => 'MSKU1234567', 'origin' => 'Guangzhou', 'port_of_loading' => 'Nansha', 'port_of_discharge' => 'Mombasa',
        'final_destination' => 'Kigali', 'eta' => date('Y-m-d H:i:s', strtotime('+5 days')), 'currency' => 'USD', 'notify_customer' => '1',
        'insured' => '1', 'insurer' => 'Test Insurance', 'insured_value' => '50000', 'insurance_currency' => 'USD',
    ]);
    $job = $pdo->query("SELECT * FROM freight_jobs WHERE id = {$jobId}")->fetch();
    $test->assert(str_starts_with((string) $job['job_code'], 'FJ-'), 'a job gets its FJ reference');
    $test->same('booked', (string) $job['status'], 'a new job is booked');
    $test->assert((int) $job['shipment_id'] > 0, 'the job opens its shipment');
    $shipment = $pdo->query('SELECT * FROM shipments WHERE id = ' . (int) $job['shipment_id'])->fetch();
    $test->same('Mombasa', (string) $shipment['origin'], 'an import shipment starts inland at the port of discharge');
    $test->same('Kigali', (string) $shipment['destination'], 'and ends at the final destination');
    $test->same('MSKU1234567', (string) $shipment['container_no'], 'the shipment carries the container');
    $test->same($jobId, (int) $shipment['freight_job_id'], 'the shipment points back at its job');

    // ------------------------------------------------------- milestones
    $noBl = Models\Workflow::apply('freight_jobs', $jobId, 'depart');
    $test->assert(!$noBl['ok'] && str_contains($noBl['message'], 'bill of lading'), 'the vessel does not sail on the system without its bill of lading');
    $pdo->prepare("UPDATE freight_jobs SET master_bl = 'MAEU123456789' WHERE id = ?")->execute([$jobId]);

    $emails = static fn (): int => (int) $one("SELECT COUNT(*) FROM email_outbox WHERE entity_type = 'freight_jobs'");
    $before = $emails();
    $departed = Models\Workflow::apply('freight_jobs', $jobId, 'depart');
    $test->assert($departed['ok'], 'with its B/L the cargo leaves: ' . $departed['message']);
    $test->assert($emails() === $before + 1, 'the customer is emailed the milestone');
    $test->assert($one('SELECT departed_at FROM freight_jobs WHERE id = ?', [$jobId]) !== null, 'the departure is stamped');

    $arrived = Models\Workflow::apply('freight_jobs', $jobId, 'arrive');
    $test->assert($arrived['ok'], 'the vessel arrives');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE role_key = 'clearance_officer' AND title LIKE 'Cargo arrived:%'") >= 1, 'the clearance department is told the cargo has arrived');
    $test->same('booked', (string) $one('SELECT status FROM shipments WHERE id = ?', [(int) $job['shipment_id']]), 'the arrival books the shipment');

    $test->assert(Models\Workflow::apply('freight_jobs', $jobId, 'start_customs')['ok'], 'the entry is lodged');
    $noEntry = Models\Workflow::apply('freight_jobs', $jobId, 'clear');
    $test->assert(!$noEntry['ok'] && str_contains($noEntry['message'], 'entry number'), 'clearing needs the customs entry number');
    $pdo->prepare("UPDATE freight_jobs SET entry_no = 'KRA-2026-000123', idf_no = 'IDF-E-2026-555' WHERE id = ?")->execute([$jobId]);

    // An open clearance file on the shipment holds the job.
    $postId = (int) Models\LogisticsData::save('border_posts', null, ['post_name' => 'Mombasa Port', 'country_a' => 'Kenya', 'country_b' => 'Sea', 'is_active' => '1']);
    $fileId = Models\LogisticsData::save('crossings', null, [
        'reference' => '', 'shipment_id' => (string) $job['shipment_id'], 'border_post_id' => (string) $postId, 'direction' => 'import', 'customs_country' => 'Kenya', 'currency' => 'KES',
    ]);
    $checklist = $pdo->query("SELECT document_type FROM border_documents WHERE crossing_id = {$fileId}")->fetchAll(PDO::FETCH_COLUMN);
    $test->assert(in_array('Import Declaration Form (IDF)', $checklist, true), 'a Kenyan import file asks for the IDF');
    $held = Models\Workflow::apply('freight_jobs', $jobId, 'clear');
    $test->assert(!$held['ok'] && str_contains($held['message'], 'still open'), 'the job is not cleared while its clearance file is open');
    $pdo->prepare("UPDATE border_crossings SET status = 'cleared' WHERE id = ?")->execute([$fileId]);
    $cleared = Models\Workflow::apply('freight_jobs', $jobId, 'clear');
    $test->assert($cleared['ok'], 'with the file cleared the job is cleared: ' . $cleared['message']);
    $test->same('cleared', (string) $one('SELECT status FROM shipments WHERE id = ?', [(int) $job['shipment_id']]), 'the shipment reads cleared');

    $noTruck = Models\Workflow::apply('freight_jobs', $jobId, 'go_inland');
    $test->assert(!$noTruck['ok'] && str_contains($noTruck['message'], 'trip'), 'it goes inland only on a trip');
    $tripId = (int) $one("SELECT id FROM trips WHERE deleted_at IS NULL ORDER BY id LIMIT 1");
    $pdo->prepare('UPDATE shipments SET trip_id = ? WHERE id = ?')->execute([$tripId, (int) $job['shipment_id']]);
    $test->assert(Models\Workflow::apply('freight_jobs', $jobId, 'go_inland')['ok'], 'loaded on a trip, it goes inland');
    $test->assert(Models\Workflow::apply('freight_jobs', $jobId, 'deliver')['ok'], 'it is delivered');
    $test->same('delivered', (string) $one('SELECT status FROM shipments WHERE id = ?', [(int) $job['shipment_id']]), 'delivering the job delivers its shipment');
    $test->assert($emails() >= $before + 5, 'the customer heard every milestone');

    // --------------------------------------------- the customer's tracking page
    $find = new ReflectionMethod(Controllers\PortalController::class, 'find');
    $byJob = $find->invoke(null, strtoupper((string) $job['job_code']), 'imports@mombasa-test.ke');
    $test->assert($byJob !== null && $byJob['job']['code'] === $job['job_code'], 'the customer finds the shipment by the job code');
    $test->assert($byJob !== null && $byJob['timeline'][0]['what'] === 'Booked' && $byJob['timeline'][1]['done'], 'the tracking page shows the sea leg');
    $test->same(null, $find->invoke(null, strtoupper((string) $job['job_code']), 'someone@else.com'), 'a stranger with the code but not the contact sees nothing');
    $byPhone = $find->invoke(null, strtoupper((string) $shipment['shipment_code']), '0722 000 111');
    $test->assert($byPhone !== null, 'the phone number works too, written the local way');

    // ------------------------------------------------------- a cargo claim
    $claimId = Models\LogisticsData::save('cargo_claims', null, [
        'claim_code' => '', 'freight_job_id' => (string) $jobId, 'shipment_id' => (string) $job['shipment_id'], 'customer_id' => (string) $customerId,
        'claim_type' => 'damage', 'incident_date' => date('Y-m-d'), 'description' => 'Two pallets crushed', 'claimed_amount' => '1200', 'currency' => 'USD',
    ]);
    $test->assert(str_starts_with((string) $one('SELECT claim_code FROM cargo_claims WHERE id = ?', [$claimId]), 'CLM-'), 'a claim gets its CLM reference');
    $test->assert(Models\Workflow::apply('cargo_claims', $claimId, 'submit')['ok'], 'the claim is sent to the insurer');
    $unpaid = Models\Workflow::apply('cargo_claims', $claimId, 'settle');
    $test->assert(!$unpaid['ok'], 'a claim is not settled without the amount paid');
    $pdo->prepare('UPDATE cargo_claims SET settled_amount = 1000 WHERE id = ?')->execute([$claimId]);
    $test->assert(Models\Workflow::apply('cargo_claims', $claimId, 'settle')['ok'], 'with the amount, it is settled');
    $test->assert((int) $one("SELECT COUNT(*) FROM notifications WHERE role_key = 'finance' AND title LIKE 'Cargo claim settled:%'") === 1, 'finance is told what came in');

    $test->assert(Models\Workflow::apply('freight_jobs', $jobId, 'close')['ok'], 'the job is closed');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
