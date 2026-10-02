<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * The driver's page: trips on the road now, trips just finished, and his papers.
 *
 * @var list<array<string, mixed>> $active
 * @var list<array<string, mixed>> $done
 * @var list<array{plate: string, make: string, documents: list<array>}> $trucks
 * @var array{licence: ?array, documents: list<array>} $own
 */
$when = static fn (?string $value): string => ($value ?? '') !== '' ? date('D d M, H:i', strtotime((string) $value)) : '—';
$badge = static fn (string $status): string => '<span class="badge badge-pill badge-' . e(\Models\Schema::tone($status)) . '">' . e(\Models\Schema::label($status)) . '</span>';
$docRows = static function (array $documents) use ($badge): string {
    if ($documents === []) {
        return '<p class="text-muted small mb-0">None on file yet. Ask the office to add them.</p>';
    }
    $html = '';
    foreach ($documents as $document) {
        $expiry = (string) ($document['expiry_date'] ?? '');
        $soon = $expiry !== '' && strtotime($expiry) < strtotime('+30 days');
        $html .= '<div class="lms-mt-doc">'
            . '<div class="lms-mt-doc-name"><strong>' . e(\Models\Schema::label((string) $document['document_type'])) . '</strong>'
            . '<span>' . e((string) ($document['document_number'] ?? '')) . ($expiry !== '' ? ' · expires <b class="' . ($soon ? 'text-danger' : '') . '">' . e($expiry) . '</b>' : '') . '</span></div>'
            . '<div class="lms-mt-doc-actions">' . $badge((string) $document['status'])
            . ($document['open_url'] ? ' <a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="' . e($document['open_url']) . '"><i class="fe fe-eye fe-12 mr-1"></i>Open</a>' : ' <span class="text-muted small">no scan</span>')
            . '</div></div>';
    }
    return $html;
};
$tripCard = static function (array $trip, bool $live) use ($when, $badge, $docRows): void {
    $loads = $trip['shipments'];
    $missing = array_sum(array_map(static fn (array $s): int => count($s['missing'] ?? []), $loads));
    ?>
    <section class="card shadow-sm lms-mt-trip<?= $live ? ' lms-mt-live' : '' ?>">
      <div class="lms-mt-head">
        <div>
          <div class="lms-mt-ref"><a href="<?= url(['trips', $trip['id']]) ?>"><?= e($trip['reference_code']) ?></a> <?= $badge((string) $trip['status']) ?></div>
          <div class="lms-mt-route"><?= e($trip['pickup_location']) ?> <i class="fe fe-arrow-right mx-1"></i> <?= e($trip['destination']) ?></div>
          <div class="lms-mt-meta">
            <span><i class="fe fe-truck"></i> <?= e($trip['plate_number'] ?? 'No truck yet') ?></span>
            <?php if (!empty($trip['customer_name'])): ?><span><i class="fe fe-briefcase"></i> <?= e($trip['customer_name']) ?></span><?php endif; ?>
            <span><i class="fe fe-log-out"></i> Leave <?= e($when($trip['departure_at'] ?: $trip['planned_departure_at'])) ?></span>
            <span><i class="fe fe-flag"></i> Arrive by <?= e($when($trip['planned_arrival_at'])) ?></span>
          </div>
        </div>
        <?php if ($live): ?>
          <div class="lms-mt-actions">
            <?php if (can_create('fuel')): ?><a class="btn btn-outline-primary" href="<?= url(['fuel', 'create'], ['trip_id' => $trip['id']]) ?>"><i class="fe fe-droplet fe-12 mr-1"></i>Record fuel</a><?php endif; ?>
            <?php if (can_create('expenses')): ?><a class="btn btn-outline-primary" href="<?= url(['expenses', 'create'], ['trip_id' => $trip['id']]) ?>"><i class="fe fe-credit-card fe-12 mr-1"></i>Record expense</a><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($missing > 0 && $live): ?>
        <div class="alert alert-warning mx-3 mb-0"><i class="fe fe-alert-triangle mr-2"></i><?= (int) $missing ?> shipping paper<?= $missing === 1 ? ' is' : 's are' ?> still missing on this trip. Call the office before you reach the border.</div>
      <?php endif; ?>

      <div class="lms-mt-body">
        <?php if ($trip['stops'] !== []): ?>
          <div class="lms-mt-block">
            <h4><i class="fe fe-map-pin"></i> Route</h4>
            <ol class="lms-mt-stops">
              <?php foreach ($trip['stops'] as $stop): ?>
                <li class="is-<?= e((string) $stop['status']) ?>">
                  <div><strong><?= e($stop['location_name']) ?></strong> <span class="text-muted small"><?= e(\Models\Schema::label((string) $stop['stop_type'])) ?></span></div>
                  <div class="small text-muted">
                    <?= $stop['contact_name'] ? e($stop['contact_name']) : '' ?>
                    <?php if ($stop['contact_phone']): ?> · <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $stop['contact_phone'])) ?>"><?= e($stop['contact_phone']) ?></a><?php endif; ?>
                    <?= $stop['planned_arrival_at'] ? ' · by ' . e($when($stop['planned_arrival_at'])) : '' ?>
                    <?= $badge((string) $stop['status']) ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php endif; ?>

        <?php foreach ($loads as $load): ?>
          <div class="lms-mt-block">
            <h4><i class="fe fe-package"></i> Load <?= e($load['shipment_code']) ?> <?= $badge((string) $load['status']) ?></h4>
            <div class="lms-mt-facts">
              <div><span>Goods</span><b><?= e($load['cargo_description']) ?></b></div>
              <div><span>Weight</span><b><?= e(number_format((float) $load['weight_kg'])) ?> kg<?= $load['packages_count'] ? ' · ' . e((string) $load['packages_count']) . ' pkgs' : '' ?></b></div>
              <?php if ($load['container_no']): ?><div><span>Container</span><b><?= e($load['container_no']) ?><?= $load['container_size'] ? ' (' . e($load['container_size']) . ')' : '' ?></b></div><?php endif; ?>
              <?php if ($load['seal_no']): ?><div><span>Seal</span><b><?= e($load['seal_no']) ?></b></div><?php endif; ?>
              <?php if ($load['trailer_plate']): ?><div><span>Trailer</span><b><?= e($load['trailer_plate']) ?></b></div><?php endif; ?>
              <div><span>Hand over to</span><b><?= e($load['consignee_name']) ?><?php if ($load['consignee_phone']): ?> · <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $load['consignee_phone'])) ?>"><?= e($load['consignee_phone']) ?></a><?php endif; ?></b></div>
              <div><span>At</span><b><?= e($load['destination']) ?></b></div>
            </div>
            <?php if (($load['special_instructions'] ?? '') !== ''): ?><div class="alert alert-info py-2 small mb-2"><i class="fe fe-info mr-1"></i><?= e($load['special_instructions']) ?></div><?php endif; ?>
            <?php if ($live && $load['missing'] !== []): ?><div class="small text-danger mb-2"><i class="fe fe-alert-circle mr-1"></i>Missing: <?= e(implode(', ', $load['missing'])) ?></div><?php endif; ?>
            <?= $docRows($load['documents']) ?>
          </div>
        <?php endforeach; ?>

        <?php foreach ($trip['crossings'] as $crossing): ?>
          <div class="lms-mt-block">
            <h4><i class="fe fe-shield"></i> Border: <?= e($crossing['post_name']) ?> <?= $badge((string) $crossing['status']) ?></h4>
            <div class="lms-mt-facts">
              <div><span>Clearance</span><b><?= e($crossing['reference']) ?></b></div>
              <div><span>Declaration</span><b><?= e($crossing['declaration_no'] ?? '—') ?></b></div>
              <?php if ($crossing['seal_no']): ?><div><span>Seal</span><b><?= e($crossing['seal_no']) ?></b></div><?php endif; ?>
            </div>
            <?= $docRows($crossing['documents']) ?>
          </div>
        <?php endforeach; ?>

        <?php if ($trip['deliveries'] !== []): ?>
          <div class="lms-mt-block">
            <h4><i class="fe fe-check-square"></i> Deliveries</h4>
            <?php foreach ($trip['deliveries'] as $delivery): ?>
              <div class="lms-mt-doc">
                <div class="lms-mt-doc-name"><strong><a href="<?= url(['deliveries', $delivery['id']]) ?>"><?= e($delivery['delivery_code']) ?></a></strong><span><?= e($delivery['recipient_name']) ?> · <?= e($delivery['destination']) ?></span></div>
                <div class="lms-mt-doc-actions"><?= $badge((string) $delivery['status']) ?>
                  <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= url(['deliveries', $delivery['id'], 'note']) ?>"><i class="fe fe-printer fe-12 mr-1"></i>Delivery note</a></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php
};
$truckDocs = array_sum(array_map(static fn (array $t): int => count($t['documents']), $trucks));
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Driver</p>
      <h2 class="lms-page-title"><i class="fe fe-truck mr-2"></i>My trips &amp; papers</h2>
      <p class="lms-page-sub">Everything you need on the road. Show the papers at the port, the weighbridge and the border straight from your phone.</p>
    </div>
  </div>

  <ul class="nav nav-pills lms-mt-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#mt-now" role="tab"><i class="fe fe-navigation fe-12 mr-1"></i>On the road <span class="badge badge-light ml-1"><?= count($active) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#mt-done" role="tab"><i class="fe fe-check fe-12 mr-1"></i>Just finished <span class="badge badge-light ml-1"><?= count($done) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#mt-papers" role="tab"><i class="fe fe-file-text fe-12 mr-1"></i>My papers <span class="badge badge-light ml-1"><?= count($own['documents'] ?? []) + $truckDocs ?></span></a></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="mt-now" role="tabpanel">
      <?php if ($active === []): ?>
        <div class="card shadow-sm"><div class="card-body text-center py-5">
          <i class="fe fe-coffee fe-32 text-muted"></i>
          <h5 class="mt-3 mb-1">No trip for you right now</h5>
          <p class="text-muted mb-0">When the office plans a trip for you it appears here with the route, the loads and every paper.</p>
        </div></div>
      <?php endif; ?>
      <?php foreach ($active as $trip) { $tripCard($trip, true); } ?>
    </div>

    <div class="tab-pane fade" id="mt-done" role="tabpanel">
      <?php if ($done === []): ?>
        <div class="card shadow-sm"><div class="card-body text-muted">No trip finished in the last few days.</div></div>
      <?php endif; ?>
      <?php foreach ($done as $trip) { $tripCard($trip, false); } ?>
    </div>

    <div class="tab-pane fade" id="mt-papers" role="tabpanel">
      <section class="card shadow-sm lms-mt-trip">
        <div class="lms-mt-head"><div><div class="lms-mt-ref"><i class="fe fe-user mr-1"></i>My own papers</div></div></div>
        <div class="lms-mt-body">
          <?php if (!empty($own['licence'])): $licence = $own['licence']; $soon = !empty($licence['license_expiry']) && strtotime((string) $licence['license_expiry']) < strtotime('+60 days'); ?>
            <div class="lms-mt-facts mb-2">
              <div><span>Driving licence</span><b><?= e($licence['license_number'] ?? '—') ?></b></div>
              <div><span>Class</span><b><?= e((string) ($licence['license_class'] ?? '—')) ?></b></div>
              <div><span>Expires</span><b class="<?= $soon ? 'text-danger' : '' ?>"><?= e((string) ($licence['license_expiry'] ?? '—')) ?></b></div>
            </div>
          <?php endif; ?>
          <?= $docRows($own['documents'] ?? []) ?>
        </div>
      </section>

      <?php foreach ($trucks as $truck): ?>
        <section class="card shadow-sm lms-mt-trip">
          <div class="lms-mt-head"><div><div class="lms-mt-ref"><i class="fe fe-truck mr-1"></i>Truck <?= e($truck['plate']) ?></div><?php if ($truck['make'] !== ''): ?><div class="lms-mt-meta"><span><?= e($truck['make']) ?></span></div><?php endif; ?></div></div>
          <div class="lms-mt-body"><?= $docRows($truck['documents']) ?></div>
        </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
  // Remember the tab, so coming back from a document opens the same one.
  document.addEventListener('DOMContentLoaded', function () {
    try {
      var saved = sessionStorage.getItem('lms-my-trips-tab');
      if (saved && window.jQuery) { jQuery('.lms-mt-tabs a[href="' + saved + '"]').tab('show'); }
      document.querySelectorAll('.lms-mt-tabs a').forEach(function (a) {
        a.addEventListener('click', function () { try { sessionStorage.setItem('lms-my-trips-tab', a.getAttribute('href')); } catch (e) {} });
      });
    } catch (e) {}
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
