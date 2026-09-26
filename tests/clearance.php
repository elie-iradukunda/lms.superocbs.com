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

    Models\LogisticsData::save('clearance_documents', null, [
        'crossing_id' => (string) $clearanceId,
        'document_type' => 'Commercial invoice',
        'document_no' => 'INV-CLR-001',
        'status' => 'valid',
    ]);

    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'submit_declaration')['ok'], 'a declaration with a valid document can be submitted');
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'start_review')['ok'], 'a submitted declaration can enter review');
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'assess_duties')['ok'], 'a reviewed declaration can move to duties assessment');
    $test->assert(Models\Workflow::apply('crossings', $clearanceId, 'request_payment')['ok'], 'assessed duties can move to payment pending');
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
    $test->same('released', (string) $pdo->query("SELECT status FROM shipments WHERE id = {$shipmentId}")->fetchColumn(), 'release updates the linked shipment');

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
