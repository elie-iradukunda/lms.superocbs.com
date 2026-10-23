<?php require __DIR__ . '/_head.php'; ?>
<?php
/**
 * @var string $code
 * @var ?array $result
 * @var ?string $error
 * @var string $notice
 * @var list<string> $uploadable
 */
?>
<div class="portal-card mb-3">
  <h1 class="h4 mb-1">Track a shipment</h1>
  <p class="text-muted">Use the job or shipment code on your booking, notice or delivery note, with the email or phone the goods were booked with.</p>
  <?php if ($notice !== ''): ?><div class="alert alert-info"><?= e($notice) ?></div><?php endif; ?>
  <?php if ($error !== null): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= url('track') ?>" class="form-row align-items-end">
    <?= csrf_field() ?>
    <div class="form-group col-md-4"><label for="code">Job or shipment code</label><input class="form-control" id="code" name="code" value="<?= e($code) ?>" placeholder="FJ-2026-0001 or SHP-2026-0001" required></div>
    <div class="form-group col-md-5"><label for="contact">Email or phone</label><input class="form-control" id="contact" name="contact" required autocomplete="email"></div>
    <div class="form-group col-md-3"><button class="btn btn-primary btn-block" type="submit"><i class="fe fe-search fe-12 mr-1"></i>Track</button></div>
  </form>
</div>

<?php if ($result !== null): ?>
  <div class="portal-card mb-3">
    <div class="d-flex justify-content-between flex-wrap" style="gap:8px">
      <div>
        <h2 class="h5 mb-0"><?= e($result['job']['code'] ?? $result['code']) ?></h2>
        <div class="text-muted"><?= e($result['from']) ?> <i class="fe fe-arrow-right"></i> <?= e($result['to']) ?> · <?= e($result['goods']) ?></div>
        <?php if ($result['job'] !== null): ?>
          <div class="small text-muted mt-1">
            <?= e($result['job']['mode']) ?><?= $result['job']['vessel'] !== '' ? ' · ' . e($result['job']['vessel']) : '' ?><?= $result['job']['bl'] !== '' ? ' · B/L ' . e($result['job']['bl']) : '' ?><?= $result['job']['containers'] !== '' ? ' · ' . e($result['job']['containers']) : '' ?>
          </div>
        <?php endif; ?>
      </div>
      <span class="badge badge-pill badge-<?= $result['status_key'] === 'delivered' ? 'success' : 'primary' ?> align-self-start" style="font-size:14px"><?= e($result['job']['status'] ?? $result['status']) ?></span>
    </div>
    <div class="row mt-3">
      <div class="col-md-6">
        <h3 class="h6 text-uppercase text-muted">The journey</h3>
        <?php if ($result['timeline'] === []): ?>
          <p class="text-muted small">The truck has not left yet. You will see each border and stop here as it is reached.</p>
        <?php else: ?>
          <ul class="portal-steps">
            <?php foreach ($result['timeline'] as $step): ?>
              <li class="<?= $step['done'] ? 'is-done' : '' ?>"><strong><?= e($step['what']) ?></strong><?php if ($step['when']): ?><span class="d-block small text-muted"><?= e(human_date((string) $step['when'], true)) ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="col-md-6">
        <h3 class="h6 text-uppercase text-muted">Customs</h3>
        <?php if ($result['clearances'] === []): ?>
          <p class="text-muted small">No clearance file is open yet.</p>
        <?php else: ?>
          <ul class="list-unstyled small">
            <?php foreach ($result['clearances'] as $clearance): ?><li><strong><?= e($clearance['post']) ?></strong>: <?= e($clearance['status']) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <h3 class="h6 text-uppercase text-muted mt-3">Arrival</h3>
        <p class="small mb-1"><?= $result['arrived'] ? 'Arrived ' . e(human_date((string) $result['arrived'], true)) : ($result['expected'] ? 'Expected ' . e(human_date((string) $result['expected'], true)) : (!empty($result['job']['eta']) ? 'Vessel / flight expected ' . e(human_date((string) $result['job']['eta'])) : 'Date to be confirmed')) ?></p>
        <?php if ($result['last_seen'] !== null): ?>
          <p class="small text-muted mb-0"><i class="fe fe-map-pin mr-1"></i>Truck last reported <?= e(time_ago((string) $result['last_seen']['at'])) ?>.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="portal-card">
    <h3 class="h6 text-uppercase text-muted">Your papers</h3>
    <?php if ($result['papers'] === []): ?>
      <p class="small text-muted">We have no paper for this shipment yet.</p>
    <?php else: ?>
      <ul class="list-unstyled small mb-3">
        <?php foreach ($result['papers'] as $paper): ?><li><strong><?= e($paper['type']) ?></strong>: <?= e($paper['status']) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="small text-muted mb-2">Send us a paper (bill of lading, commercial invoice, packing list, IDF…). Our team checks it before it is used.</p>
    <form method="post" action="<?= url(['track', 'upload']) ?>" enctype="multipart/form-data" class="form-row align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="shipment" value="<?= (int) $result['shipment_id'] ?>">
      <div class="form-group col-md-4"><label for="document_type">Paper</label>
        <select class="form-control" id="document_type" name="document_type" required>
          <?php foreach ($uploadable as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-group col-md-3"><label for="document_number">Number</label><input class="form-control" id="document_number" name="document_number" maxlength="60"></div>
      <div class="form-group col-md-3"><label for="document">File (PDF or photo)</label><input class="form-control-file" type="file" id="document" name="document" accept="application/pdf,image/*" required></div>
      <div class="form-group col-md-2"><button class="btn btn-outline-primary btn-block" type="submit"><i class="fe fe-upload fe-12 mr-1"></i>Send</button></div>
    </form>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
