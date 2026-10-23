<?php

declare(strict_types=1);

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_clearance');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Clearance workflow tests');
    $accounts = demo_accounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $shipmentId = (int) $pdo->query("SELECT id FROM shipments WHERE shipment_code = 'SHP-2026-0001'")->fetchColumn();
    $postId = (int) $pdo->query('SELECT id FROM border_posts ORDER BY id LIMIT 1')->fetchColumn();

    $clearanceId = Models\LogisticsData::save('crossings', null, [
        'reference' => 'CLR-WF-001',
        'shipment_id' => (string) $shipmentId,
        'border_post_id' => (string) $postId,
        'direction' => 'import',
        'currency' => 'RWF',
        'declaration_no' => 'RRA-TEST-001',
    ]);

    $test->same('draft', (string) $pdo->query("SELECT status FROM border_crossings WHERE id = {$clearanceId}")->fetchColumn(), 'a new clearance starts in draft');
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'prepare_documents')['ok'], 'a draft clearance can start document collection');
    $test->same('documents_pending', (string) $pdo->query("SELECT status FROM border_crossings WHERE id = {$clearanceId}")->fetchColumn(), 'document collection updates clearance status');

    $beforeDocument = Models\Workflow::apply('crossings', $clearanceId, 'submit_declaration');
    $test->assert(!$beforeDocument['ok'], 'a declaration cannot be submitted without a valid document');

    // A new file starts with its checklist of papers, all waiting.
    $checklist = $pdo->query("SELECT document_type FROM border_documents WHERE crossing_id = {$clearanceId} ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $test->assert(in_array('Import declaration', $checklist, true) && in_array('Commercial invoice', $checklist, true) && in_array('Packing list', $checklist, true), 'an import file opens with its checklist: ' . implode(', ', $checklist));

    // A paper is valid when the clearance department has checked its scan.
    $papers = static fn (array $types): array => array_map('intval', $pdo->query(
        "SELECT id FROM border_documents WHERE crossing_id = {$clearanceId} AND document_type IN ('" . implode("','", $types) . "')"
    )->fetchAll(PDO::FETCH_COLUMN));
    $invoicePaper = $papers(['Commercial invoice'])[0];
    $noScan = Models\Workflow::apply('clearance_documents', $invoicePaper, 'verify');
    $test->assert(!$noScan['ok'], 'a paper without its scan cannot be verified');
    $pdo->exec("UPDATE border_documents SET document_file = 'clearance_documents/test.pdf' WHERE crossing_id = {$clearanceId}");
    $test->assert(test_sign_all('clearance_documents', $invoicePaper, 'verify')['ok'], 'the commercial invoice is verified');

    $oneOfMany = Models\Workflow::apply('crossings', $clearanceId, 'submit_declaration');
    $test->assert(!$oneOfMany['ok'] && str_contains($oneOfMany['message'], 'Packing list'), 'the declaration waits for every supporting paper: ' . $oneOfMany['message']);

    $supporting = $pdo->query("SELECT id FROM border_documents WHERE crossing_id = {$clearanceId} AND status = 'pending' AND document_type NOT IN ('Import declaration', 'Tax payment receipt')")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($supporting as $paperId) {
        test_sign_all('clearance_documents', (int) $paperId, 'verify');
    }

    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'submit_declaration')['ok'], 'a declaration with a valid document can be submitted');
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'start_review')['ok'], 'a submitted declaration can enter review');
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'assess_duties')['ok'], 'a reviewed declaration can move to duties assessment');
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'request_payment')['ok'], 'assessed duties can move to payment pending');
    $withoutReceipt = Models\Workflow::apply('crossings', $clearanceId, 'clear');
    $test->assert(!$withoutReceipt['ok'], 'customs does not clear while the declaration and tax receipt are not checked');
    foreach ($papers(['Import declaration', 'Tax payment receipt']) as $paperId) {
        test_sign_all('clearance_documents', $paperId, 'verify');
    }
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'clear')['ok'], 'a documented clearance with no unpaid charges can clear');
    $test->same('cleared', (string) $pdo->query("SELECT status FROM shipments WHERE id = {$shipmentId}")->fetchColumn(), 'clearing updates the linked shipment');

    $withoutRelease = Models\Workflow::apply('crossings', $clearanceId, 'release');
    $test->assert(!$withoutRelease['ok'], 'a clearance cannot release without a formal release record');

    Models\LogisticsData::save('clearance_releases', null, [
        'crossing_id' => (string) $clearanceId,
        'release_number' => 'REL-WF-001',
        'release_date' => date('Y-m-d'),
        'released_by' => (string) current_user_id(),
    ]);

    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'release')['ok'], 'a clearance with a formal release can release');
    // Released at a border while its truck is still on the road, the load is in
    // transit again; with no running trip it simply reads Released.
    $tripRunning = (string) $pdo->query("SELECT t.status FROM shipments s LEFT JOIN trips t ON t.id = s.trip_id WHERE s.id = {$shipmentId}")->fetchColumn() === 'in_transit';
    $test->same($tripRunning ? 'in_transit' : 'released', (string) $pdo->query("SELECT status FROM shipments WHERE id = {$shipmentId}")->fetchColumn(), 'release updates the linked shipment');
    $test->same('released', (string) $pdo->query("SELECT status FROM border_crossings WHERE id = {$clearanceId}")->fetchColumn(), 'the clearance itself reads Released');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
