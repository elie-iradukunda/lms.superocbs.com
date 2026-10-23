<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $driver @var ?array $trip */
$pageScripts = ['/assets/js/lms-driver.js'];
$unit = ['litres' => 'L', 'tonnes' => 't', 'containers' => 'containers'][$trip['cargo_type'] ?? ''] ?? '';
?>
<div class="container-fluid lms-app">
  <a class="lms-app-back" href="<?= url('my-trips') ?>"><i class="fe fe-arrow-left mr-1"></i>Back</a>
  <h3 class="mb-3"><i class="fe fe-clipboard mr-2"></i>Delivery note</h3>
  <div class="lms-offline-note alert alert-warning d-none" data-offline-note><i class="fe fe-wifi-off mr-1"></i>No signal: the note is kept on the phone and sent when the signal returns.</div>
  <?php if ($trip === null || !in_array($trip['status'], ['offloading', 'on_road', 'at_turnaround'], true)): ?>
    <div class="card shadow-sm"><div class="card-body text-center py-5 text-muted">The delivery note is filled at the customer. Tap "At the customer: offloading" on My trip first.</div></div>
  <?php else: ?>
    <form method="post" action="<?= url(['my-trips', 'delivery']) ?>" enctype="multipart/form-data" class="card shadow-sm" data-offline data-geo>
      <?= csrf_field() ?>
      <input type="hidden" name="lat"><input type="hidden" name="lng">
      <div class="card-body">
        <dl class="row small mb-3">
          <dt class="col-5">Trip</dt><dd class="col-7"><?= e($trip['reference_code']) ?></dd>
          <dt class="col-5">Truck</dt><dd class="col-7"><?= e($trip['plate_number']) ?></dd>
          <dt class="col-5">Customer</dt><dd class="col-7"><?= e($trip['customer_name'] ?? '—') ?></dd>
          <dt class="col-5">Cargo</dt><dd class="col-7"><?= e(trim(($trip['cargo_summary'] ?? '') . ' ' . ($trip['container_numbers'] ?? ''))) ?: '—' ?></dd>
        </dl>
        <div class="form-group"><label>Quantity loaded <?= e($unit) ?> <span class="lms-required">*</span></label><input class="form-control form-control-lg" type="number" inputmode="decimal" step="0.001" min="0" name="loaded" id="lms-loaded" required value="<?= e((string) ($trip['loaded_quantity'] ?? $trip['cargo_quantity'] ?? '')) ?>"></div>
        <div class="form-group"><label>Quantity delivered <?= e($unit) ?> <span class="lms-required">*</span></label><input class="form-control form-control-lg" type="number" inputmode="decimal" step="0.001" min="0" name="delivered" id="lms-delivered" required></div>
        <p class="lms-shortage" id="lms-shortage"></p>
        <div class="form-group"><label>Received by (name at the customer) <span class="lms-required">*</span></label><input class="form-control form-control-lg" name="received_by" required></div>
        <div class="form-group"><label>Photo of the customer's signed or stamped copy <span class="lms-required">*</span></label><input class="form-control-file" type="file" name="photo" accept="image/*" capture="environment" required></div>
        <div class="form-group"><label>Note</label><textarea class="form-control" name="note" rows="2"></textarea></div>
        <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fe fe-send mr-2"></i>Send the delivery note</button>
      </div>
    </form>
    <script>
    (function () {
      var loaded = document.getElementById('lms-loaded'), delivered = document.getElementById('lms-delivered'), out = document.getElementById('lms-shortage');
      function show() {
        var l = parseFloat(loaded.value) || 0, d = parseFloat(delivered.value);
        if (isNaN(d)) { out.textContent = ''; return; }
        var s = Math.round((l - d) * 1000) / 1000;
        out.className = 'lms-shortage ' + (s > 0 ? 'text-danger' : 'text-success');
        out.textContent = s > 0 ? 'Shortage: ' + s + ' <?= e($unit) ?>' : 'No shortage';
      }
      loaded.addEventListener('input', show); delivered.addEventListener('input', show);
    })();
    </script>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
