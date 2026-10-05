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
            <?php if (($trip['distance']['start_km'] ?? null) !== null): ?><span class="lms-mt-km"><i class="fe fe-activity"></i> Started at <?= e(number_format((int) $trip['distance']['start_km'])) ?> km · <strong><?= e(number_format((int) ($trip['distance']['driven'] ?? 0))) ?> km driven</strong></span><?php endif; ?>
          </div>
        </div>
        <?php if ($live): ?>
          <div class="lms-mt-actions">
            <?php if ($trip['readiness'] !== null && $trip['readiness']['ready']): ?>
              <button class="btn btn-success lms-mt-start" type="button" data-start-trip data-url="<?= url(['my-trips', $trip['id'], 'start']) ?>" data-trip="<?= e($trip['reference_code']) ?>" data-from="<?= e($trip['pickup_location']) ?>" data-km="<?= e((string) ($trip['truck_mileage'] ?? '')) ?>"><i class="fe fe-play fe-12 mr-1"></i>Start trip</button>
            <?php endif; ?>
            <a class="btn btn-primary" target="_blank" rel="noopener" href="<?= url(['trips', $trip['id'], 'report', 'pdf']) ?>"><i class="fe fe-file-text fe-12 mr-1"></i>Trip report</a>
            <?php if (can_create('support_requests')): ?><a class="btn btn-outline-danger" href="<?= url(['support_requests', 'create'], ['trip_id' => $trip['id']]) ?>"><i class="fe fe-life-buoy fe-12 mr-1"></i>Ask for support</a><?php endif; ?>
            <?php if (can_create('fuel')): ?><a class="btn btn-outline-primary" href="<?= url(['fuel', 'create'], ['trip_id' => $trip['id']]) ?>"><i class="fe fe-droplet fe-12 mr-1"></i>Record fuel</a><?php endif; ?>
            <?php if (can_create('expenses')): ?><a class="btn btn-outline-primary" href="<?= url(['expenses', 'create'], ['trip_id' => $trip['id']]) ?>"><i class="fe fe-credit-card fe-12 mr-1"></i>Record expense</a><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($live && $trip['readiness'] !== null && !$trip['readiness']['ready']): ?>
        <div class="alert alert-secondary mx-3 mb-0"><i class="fe fe-clock mr-2"></i><strong>Not ready to start yet.</strong> The office is finishing: <?= e(implode('; ', array_slice($trip['readiness']['failures'], 0, 2))) ?>. You will get the trip report and a message when it is ready.</div>
      <?php endif; ?>
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
                  <?php if ($live && $trip['status'] === 'in_transit'): ?>
                    <div class="lms-mt-stop-actions">
                      <?php if (empty($stop['actual_arrival_at'])): ?>
                        <button class="btn btn-sm btn-primary" type="button" data-stop-confirm="arrive" data-url="<?= url(['my-trips', $trip['id'], 'stops', $stop['id'], 'arrive']) ?>" data-place="<?= e($stop['location_name']) ?>" data-trip="<?= e($trip['reference_code']) ?>"><i class="fe fe-map-pin fe-12 mr-1"></i>I have arrived</button>
                      <?php elseif (empty($stop['departed_at'])): ?>
                        <span class="small text-success">Arrived <?= e(date('H:i', strtotime((string) $stop['actual_arrival_at']))) ?><?= $stop['arrival_mileage'] ? ' · ' . e(number_format((int) $stop['arrival_mileage'])) . ' km' : '' ?><?= $stop['arrival_condition'] ? ' · ' . e(\Models\TripProgress::CONDITIONS[$stop['arrival_condition']] ?? '') : '' ?></span>
                        <?php if (!\Models\TripProgress::handedOver((int) $trip["id"], $stop)): ?>
                          <span class="small text-muted"><i class="fe fe-package fe-12 mr-1"></i>Unload first. You can leave once the office records the goods as dropped or delivered.</span>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-stop-confirm="leave" data-url="<?= url(['my-trips', $trip['id'], 'stops', $stop['id'], 'leave']) ?>" data-place="<?= e($stop['location_name']) ?>" data-trip="<?= e($trip['reference_code']) ?>"><i class="fe fe-log-out fe-12 mr-1"></i>Leaving</button>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="small text-muted">Arrived <?= e(date('D H:i', strtotime((string) $stop['actual_arrival_at']))) ?> · left <?= e(date('D H:i', strtotime((string) $stop['departed_at']))) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <div class="small text-muted">
                    <?= implode(' · ', array_filter([
                        $stop['contact_name'] ? e($stop['contact_name']) : '',
                        $stop['contact_phone'] ? '<a href="tel:' . e(preg_replace('/\s+/', '', (string) $stop['contact_phone'])) . '">' . e($stop['contact_phone']) . '</a>' : '',
                        $stop['planned_arrival_at'] ? 'by ' . e($when($stop['planned_arrival_at'])) : '',
                    ])) ?>
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

        <?php if ($trip['support'] !== []): ?>
          <div class="lms-mt-block">
            <h4><i class="fe fe-life-buoy"></i> Support you asked for</h4>
            <?php foreach ($trip['support'] as $help): ?>
              <div class="lms-mt-doc">
                <div class="lms-mt-doc-name"><strong><?= e($help['request_code']) ?> · <?= e($help['title']) ?></strong>
                  <span><?= e(\Models\Schema::SUPPORT_TYPES[$help['support_type']] ?? '') ?><?= (float) $help['amount_requested'] > 0 ? ' · asked ' . e(\Models\Currency::format((float) $help['amount_requested'], (string) ($help['currency'] ?: \Models\Currency::base()))) : '' ?> · sent to <?= e($help['assigned_name'] ?? 'the office') ?></span>
                  <?php if (trim((string) $help['response']) !== '' || (float) $help['amount_given'] > 0 || $help['rejection_reason']): ?>
                    <span class="lms-mt-answer"><b><?= e($help['responded_name'] ?? 'Office') ?>:</b> <?= e(trim((string) ($help['rejection_reason'] ?: $help['response']))) ?><?= (float) $help['amount_given'] > 0 ? ' · ' . e(\Models\Currency::format((float) $help['amount_given'], (string) ($help['currency'] ?: \Models\Currency::base()))) . ($help['given_method'] ? ' by ' . e($help['given_method']) : '') . ($help['given_reference'] ? ' (' . e($help['given_reference']) . ')' : '') : '' ?></span>
                  <?php endif; ?>
                </div>
                <div class="lms-mt-doc-actions"><?= $badge((string) $help['status']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

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
<?php // One small window for both buttons, so a stop is never recorded by a slip of the finger. ?>
<div class="modal fade" id="stopConfirmModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" id="stopConfirmForm">
    <?= csrf_field() ?>
    <div class="modal-header">
      <div>
        <small class="text-muted" id="stopConfirmTrip"></small>
        <h5 class="modal-title" id="stopConfirmTitle"></h5>
      </div>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="modal-body">
      <p class="mb-3" id="stopConfirmText"></p>
      <div data-arrive-only>
        <div class="form-group">
          <label for="stopMileage">Kilometres on the clock <span class="text-muted small">(if you can)</span></label>
          <input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="mileage" id="stopMileage" placeholder="186900">
        </div>
        <div class="form-group">
          <label class="d-block">How is everything?</label>
          <div class="btn-group btn-group-toggle d-flex lms-stop-condition" data-toggle="buttons">
            <?php foreach (\Models\TripProgress::CONDITIONS as $value => $wording): ?>
              <label class="btn btn-outline-<?= $value === 'fine' ? 'success' : ($value === 'problem' ? 'warning' : 'danger') ?> flex-fill<?= $value === 'fine' ? ' active' : '' ?>">
                <input type="radio" name="condition" value="<?= e($value) ?>"<?= $value === 'fine' ? ' checked' : '' ?>> <?= e($wording) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="form-group mb-0">
        <label for="stopNote">Note for the office <span class="text-muted small">(optional)</span></label>
        <textarea class="form-control" name="note" id="stopNote" rows="2" maxlength="250"></textarea>
      </div>
      <p class="small text-muted mt-3 mb-0"><i class="fe fe-clock mr-1"></i>The date and time are recorded automatically, and the company is told straight away.</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-primary" id="stopConfirmSubmit"></button>
    </div>
  </form>
</div></div></div>
<div class="modal fade" id="startTripModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" id="startTripForm">
    <?= csrf_field() ?>
    <div class="modal-header">
      <div>
        <small class="text-muted" id="startTripCode"></small>
        <h5 class="modal-title">Start the trip now?</h5>
      </div>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="modal-body">
      <p class="mb-3" id="startTripText"></p>
      <div class="form-group mb-0">
        <label for="startTripKm">Kilometres on the clock now <span class="lms-required">*</span></label>
        <input class="form-control form-control-lg" type="number" inputmode="numeric" min="0" step="1" name="mileage" id="startTripKm" required>
        <small class="form-text text-muted" id="startTripLast"></small>
      </div>
      <p class="small text-muted mt-3 mb-0"><i class="fe fe-clock mr-1"></i>The date and time are recorded automatically. The distance at every stop is counted from this reading.</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-success" id="startTripSubmit"><i class="fe fe-play fe-12 mr-1"></i>Yes, start the trip</button>
    </div>
  </form>
</div></div></div>
<script>
  // "Start trip" asks for the kilometres on the clock before anything is recorded.
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('startTripForm');
    document.querySelectorAll('[data-start-trip]').forEach(function (button) {
      button.addEventListener('click', function () {
        var km = button.getAttribute('data-km');
        form.setAttribute('action', button.getAttribute('data-url'));
        document.getElementById('startTripCode').textContent = button.getAttribute('data-trip');
        document.getElementById('startTripText').textContent = 'Press this only when you are driving out of ' + button.getAttribute('data-from') + '. The company and the customer are told you are on your way.';
        document.getElementById('startTripKm').value = km;
        document.getElementById('startTripLast').textContent = km ? 'The truck\'s last reading was ' + Number(km).toLocaleString() + ' km. Check the clock and correct it if needed.' : '';
        document.getElementById('startTripSubmit').disabled = false;
        if (window.jQuery) { jQuery('#startTripModal').modal('show'); }
      });
    });
    form.addEventListener('submit', function () { document.getElementById('startTripSubmit').disabled = true; });
  });
</script>
<script>
  // "I have arrived" / "Leaving" open the small window; only its button records the stop.
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('stopConfirmForm');
    document.querySelectorAll('[data-stop-confirm]').forEach(function (button) {
      button.addEventListener('click', function () {
        var arriving = button.getAttribute('data-stop-confirm') === 'arrive';
        var place = button.getAttribute('data-place');
        form.reset();
        form.setAttribute('action', button.getAttribute('data-url'));
        document.getElementById('stopConfirmTrip').textContent = button.getAttribute('data-trip');
        document.getElementById('stopConfirmTitle').textContent = (arriving ? 'Arrived at ' : 'Leaving ') + place + '?';
        document.getElementById('stopConfirmText').textContent = arriving
          ? 'You are telling the company you have reached ' + place + ' now.'
          : 'You are telling the company you are leaving ' + place + ' now.';
        document.getElementById('stopNote').setAttribute('placeholder', arriving ? 'Queue of 40 trucks at the border' : 'Customs cleared, going on to Kampala');
        form.querySelectorAll('[data-arrive-only]').forEach(function (el) { el.style.display = arriving ? '' : 'none'; });
        form.querySelectorAll('.lms-stop-condition label').forEach(function (label, i) { label.classList.toggle('active', i === 0); });
        var submit = document.getElementById('stopConfirmSubmit');
        submit.disabled = false;
        submit.textContent = arriving ? 'Yes, I have arrived' : 'Yes, I am leaving';
        if (window.jQuery) { jQuery('#stopConfirmModal').modal('show'); }
      });
    });
    form.addEventListener('submit', function () { document.getElementById('stopConfirmSubmit').disabled = true; });
  });
</script>
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
