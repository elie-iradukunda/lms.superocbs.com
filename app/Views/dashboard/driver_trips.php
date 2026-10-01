<?php
/**
 * The driver's road kit: his open trips with the route, the loads, who signs for
 * them, and every document he may be asked for, each one openable from the phone.
 */
$driverTrips = \Models\DriverWorkspace::trips((int) current_driver_id());
$own = \Models\DriverWorkspace::ownDocuments((int) current_driver_id());
$badge = static fn (string $status): string => '<span class="badge badge-' . e(\Models\Schema::TONES[$status] ?? 'secondary') . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
$docList = static function (array $documents) use ($badge): string {
    if ($documents === []) {
        return '<p class="small text-muted mb-0">None on file.</p>';
    }
    $html = '<ul class="list-unstyled small mb-0">';
    foreach ($documents as $document) {
        $html .= '<li class="d-flex justify-content-between align-items-center py-1 border-bottom">'
            . '<span><strong>' . e(ucwords(str_replace('_', ' ', (string) $document['document_type']))) . '</strong>'
            . (($document['document_number'] ?? '') !== '' ? ' · ' . e($document['document_number']) : '')
            . (($document['expiry_date'] ?? '') !== '' ? ' · expires ' . e($document['expiry_date']) : '') . '</span>'
            . '<span>' . $badge((string) $document['status'])
            . ($document['open_url'] ? ' <a class="btn btn-sm btn-outline-primary ml-1" target="_blank" rel="noopener" href="' . e($document['open_url']) . '"><i class="fe fe-eye fe-12"></i> Open</a>' : ' <span class="text-muted ml-1">no scan</span>')
            . '</span></li>';
    }
    return $html . '</ul>';
};
?>
<div class="col-12 mb-3">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><i class="fe fe-truck mr-2"></i>My trips and documents</strong>
      <span class="small text-muted">Show these at the port, the weighbridge and the border</span>
    </div>
    <div class="card-body">
      <?php if ($driverTrips === []): ?>
        <p class="text-muted mb-0">No trip is assigned to you right now.</p>
      <?php endif; ?>

      <?php foreach ($driverTrips as $trip): ?>
        <div class="border rounded p-3 mb-3">
          <div class="d-flex justify-content-between flex-wrap">
            <div>
              <h5 class="mb-1"><a href="<?= url(['trips', $trip['id']]) ?>"><?= e($trip['reference_code']) ?></a> <?= $badge((string) $trip['status']) ?></h5>
              <div class="small text-muted"><?= e($trip['pickup_location']) ?> → <?= e($trip['destination']) ?><?= $trip['customer_name'] ? ' · ' . e($trip['customer_name']) : '' ?></div>
              <div class="small">Truck <strong><?= e($trip['plate_number'] ?? '—') ?></strong><?= $trip['planned_arrival_at'] ? ' · arrive by ' . e(date('d M H:i', strtotime((string) $trip['planned_arrival_at']))) : '' ?></div>
            </div>
            <div class="mt-2 mt-md-0">
              <?php if (can_create('fuel')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url(['fuel', 'create'], ['trip_id' => $trip['id']]) ?>"><i class="fe fe-droplet fe-12"></i> Record fuel</a><?php endif; ?>
              <?php if (can_create('expenses')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url(['expenses', 'create'], ['trip_id' => $trip['id']]) ?>"><i class="fe fe-credit-card fe-12"></i> Record expense</a><?php endif; ?>
            </div>
          </div>

          <?php if ($trip['stops'] !== []): ?>
            <div class="mt-3"><div class="small text-uppercase text-muted font-weight-bold mb-1">Route</div>
              <ol class="small mb-0 pl-3">
                <?php foreach ($trip['stops'] as $stop): ?>
                  <li><?= e(ucfirst((string) $stop['stop_type'])) ?>: <strong><?= e($stop['location_name']) ?></strong>
                    <?= $stop['contact_name'] ? ' · ' . e($stop['contact_name']) : '' ?><?= $stop['contact_phone'] ? ' ' . e($stop['contact_phone']) : '' ?>
                    <?= $badge((string) $stop['status']) ?></li>
                <?php endforeach; ?>
              </ol>
            </div>
          <?php endif; ?>

          <?php foreach ($trip['shipments'] as $shipment): ?>
            <div class="mt-3">
              <div class="small text-uppercase text-muted font-weight-bold mb-1">Load <?= e($shipment['shipment_code']) ?> <?= $badge((string) $shipment['status']) ?></div>
              <div class="small mb-1"><?= e($shipment['cargo_description']) ?> · <?= e(number_format((float) $shipment['weight_kg'])) ?> kg
                <?= $shipment['container_no'] ? ' · container <strong>' . e($shipment['container_no']) . '</strong>' : '' ?>
                <?= $shipment['seal_no'] ? ' · seal <strong>' . e($shipment['seal_no']) . '</strong>' : '' ?>
                <?= $shipment['trailer_plate'] ? ' · trailer ' . e($shipment['trailer_plate']) : '' ?></div>
              <div class="small mb-1">Hand over to <strong><?= e($shipment['consignee_name']) ?></strong> <?= e((string) $shipment['consignee_phone']) ?> at <?= e($shipment['destination']) ?></div>
              <?php if (($shipment['special_instructions'] ?? '') !== ''): ?><div class="small text-warning mb-1"><i class="fe fe-alert-triangle fe-12"></i> <?= e($shipment['special_instructions']) ?></div><?php endif; ?>
              <?php if ($shipment['missing'] !== [] && $shipment['documents'] !== []): ?><div class="small text-danger mb-1">Still missing: <?= e(implode(', ', $shipment['missing'])) ?></div><?php endif; ?>
              <?= $docList($shipment['documents']) ?>
            </div>
          <?php endforeach; ?>

          <?php foreach ($trip['crossings'] as $crossing): ?>
            <div class="mt-3">
              <div class="small text-uppercase text-muted font-weight-bold mb-1">Border: <?= e($crossing['post_name']) ?> · <?= e($crossing['reference']) ?> <?= $badge((string) $crossing['status']) ?></div>
              <div class="small mb-1">Declaration <strong><?= e($crossing['declaration_no'] ?? '—') ?></strong><?= $crossing['seal_no'] ? ' · seal ' . e($crossing['seal_no']) : '' ?></div>
              <?= $docList($crossing['documents']) ?>
            </div>
          <?php endforeach; ?>

          <div class="mt-3">
            <div class="small text-uppercase text-muted font-weight-bold mb-1">Truck papers (<?= e($trip['plate_number'] ?? '') ?>)</div>
            <?= $docList($trip['vehicle_documents']) ?>
          </div>

          <?php if ($trip['deliveries'] !== []): ?>
            <div class="mt-3">
              <div class="small text-uppercase text-muted font-weight-bold mb-1">Deliveries</div>
              <?php foreach ($trip['deliveries'] as $delivery): ?>
                <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                  <span><a href="<?= url(['deliveries', $delivery['id']]) ?>"><?= e($delivery['delivery_code']) ?></a> · <?= e($delivery['recipient_name']) ?> · <?= e($delivery['destination']) ?> <?= $badge((string) $delivery['status']) ?></span>
                  <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= url(['deliveries', $delivery['id'], 'note']) ?>"><i class="fe fe-printer fe-12"></i> Delivery note</a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="small text-uppercase text-muted font-weight-bold mb-1">My own papers</div>
      <?php if (!empty($own['licence'])): ?>
        <div class="small mb-1">Driving licence <strong><?= e($own['licence']['license_number']) ?></strong> (class <?= e((string) $own['licence']['license_class']) ?>), expires <strong><?= e((string) $own['licence']['license_expiry']) ?></strong></div>
      <?php endif; ?>
      <?= $docList($own['documents']) ?>
    </div>
  </div>
</div>
