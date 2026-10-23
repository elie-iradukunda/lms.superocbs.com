<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $driver @var ?array $trip @var ?array $vehicle */
use Models\Handover;

$pageScripts = ['/assets/js/lms-driver.js'];
?>
<div class="container-fluid lms-app">
  <a class="lms-app-back" href="<?= url('my-trips') ?>"><i class="fe fe-arrow-left mr-1"></i>Back</a>
  <h3 class="mb-3"><i class="fe fe-key mr-2"></i>Hand over truck</h3>
  <div class="lms-offline-note alert alert-warning d-none" data-offline-note><i class="fe fe-wifi-off mr-1"></i>No signal: the handover is kept and sent when the signal returns.</div>
  <?php if ($vehicle === null): ?>
    <div class="card shadow-sm"><div class="card-body text-center py-5 text-muted">You have no truck to hand over.</div></div>
  <?php else: ?>
    <form method="post" action="<?= url(['my-trips', 'handover']) ?>" enctype="multipart/form-data" class="card shadow-sm" data-offline data-geo>
      <?= csrf_field() ?>
      <input type="hidden" name="lat"><input type="hidden" name="lng">
      <div class="card-body">
        <p class="mb-3"><strong><?= e($vehicle['plate_number']) ?></strong><?= $trip !== null ? ' · trip ' . e($trip['reference_code']) : '' ?>. The trip ends when the OM confirms this handover.</p>
        <div class="form-group"><label>Where is it parked? <span class="lms-required">*</span></label><input class="form-control form-control-lg" name="parked_at" required placeholder="Kigali yard, bay 4"></div>
        <div class="form-group"><label>Kilometres on the clock <span class="lms-required">*</span></label><input class="form-control form-control-lg" type="number" inputmode="numeric" min="<?= (int) $vehicle['mileage'] ?>" name="odometer" required placeholder="<?= (int) $vehicle['mileage'] ?>"></div>
        <label>Fuel level <span class="lms-required">*</span></label>
        <div class="lms-app-choices mb-3"><?php foreach (Handover::FUEL_LEVELS as $key => $label): ?><label class="lms-app-choice"><input type="radio" name="fuel_level" value="<?= e($key) ?>" required><span><?= e($label) ?></span></label><?php endforeach; ?></div>
        <div class="form-group"><label>Who took the key? <span class="lms-required">*</span></label><input class="form-control form-control-lg" name="key_received_by" required></div>
        <label>Photos (at least two) <span class="lms-required">*</span></label>
        <div class="row">
          <?php foreach (['front' => 'Front', 'back' => 'Back', 'left' => 'Left side', 'right' => 'Right side'] as $side => $label): ?>
            <div class="col-6 form-group"><small class="d-block"><?= e($label) ?></small><input class="form-control-file" type="file" name="photo_<?= $side ?>" accept="image/*" capture="environment"></div>
          <?php endforeach; ?>
        </div>
        <div class="form-group"><label>Anything to report</label><textarea class="form-control" name="notes" rows="2" placeholder="Damage, warning lights, tyres"></textarea></div>
        <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fe fe-send mr-2"></i>Hand over</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
