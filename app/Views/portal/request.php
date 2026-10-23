<?php require __DIR__ . '/_head.php'; ?>
<?php
/**
 * @var array<string, string> $input
 * @var list<string> $errors
 * @var ?string $sent
 */
$v = static fn (string $name): string => e($input[$name] ?? '');
?>
<div class="portal-card">
  <?php if ($sent !== null): ?>
    <div class="text-center py-4">
      <i class="fe fe-check-circle fe-32 text-success"></i>
      <h2 class="h4 mt-3">Thank you, we have your request</h2>
      <?php if ($sent !== 'ok'): ?><p class="mb-1">Your reference is <strong><?= e($sent) ?></strong>.</p><?php endif; ?>
      <p class="text-muted">Our operations team will call you with the price and the truck. Once your goods are booked you can follow them on <a href="<?= url('track') ?>">Track a shipment</a>.</p>
      <a class="btn btn-outline-primary mt-2" href="<?= url('request-transport') ?>">Send another request</a>
    </div>
  <?php else: ?>
    <h1 class="h4 mb-1">Request transport</h1>
    <p class="text-muted">Tell us what to move, from where to where, and when. We reply with a price and a truck.</p>
    <?php if ($errors !== []): ?>
      <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="<?= url('request-transport') ?>" novalidate>
      <?= csrf_field() ?>
      <div style="position:absolute;left:-5000px" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <h2 class="h6 text-uppercase text-muted mt-3">You</h2>
      <div class="form-row">
        <div class="form-group col-md-6"><label for="company_name">Company</label><input class="form-control" id="company_name" name="company_name" value="<?= $v('company_name') ?>" maxlength="180"></div>
        <div class="form-group col-md-6"><label for="contact_name">Your name *</label><input class="form-control" id="contact_name" name="contact_name" value="<?= $v('contact_name') ?>" required maxlength="150"></div>
        <div class="form-group col-md-6"><label for="email">Email *</label><input class="form-control" type="email" id="email" name="email" value="<?= $v('email') ?>" required maxlength="190"></div>
        <div class="form-group col-md-6"><label for="phone">Phone *</label><input class="form-control" type="tel" id="phone" name="phone" value="<?= $v('phone') ?>" required maxlength="40" placeholder="+250 7.. / +254 7.. / +255 7.."></div>
      </div>
      <h2 class="h6 text-uppercase text-muted mt-2">The move</h2>
      <div class="form-row">
        <div class="form-group col-md-6"><label for="service_type">Service</label>
          <select class="form-control" id="service_type" name="service_type">
            <option value="">Choose…</option>
            <?php foreach (\Controllers\PortalController::SERVICES as $key => $label): ?><option value="<?= e($key) ?>"<?= ($input['service_type'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group col-md-6"><label for="load_type">Type of load</label>
          <select class="form-control" id="load_type" name="load_type">
            <option value="">Choose…</option>
            <?php foreach (\Models\FreightJobs::LOAD_TYPES as $key => $label): ?><option value="<?= e($key) ?>"<?= ($input['load_type'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group col-md-6"><label for="pickup_location">Collect from *</label><input class="form-control" id="pickup_location" name="pickup_location" value="<?= $v('pickup_location') ?>" required placeholder="Mombasa port"></div>
        <div class="form-group col-md-6"><label for="destination">Deliver to *</label><input class="form-control" id="destination" name="destination" value="<?= $v('destination') ?>" required placeholder="Kigali"></div>
        <div class="form-group col-md-12"><label for="cargo_description">What the goods are *</label><input class="form-control" id="cargo_description" name="cargo_description" value="<?= $v('cargo_description') ?>" required maxlength="255" placeholder="1 x 40ft container of building materials"></div>
        <div class="form-group col-md-4"><label for="weight_kg">Weight (kg)</label><input class="form-control" type="number" min="0" step="1" id="weight_kg" name="weight_kg" value="<?= $v('weight_kg') ?>"></div>
        <div class="form-group col-md-4"><label for="packages_count">Packages</label><input class="form-control" type="number" min="0" step="1" id="packages_count" name="packages_count" value="<?= $v('packages_count') ?>"></div>
        <div class="form-group col-md-4"><label for="required_date">Needed by *</label><input class="form-control" type="date" id="required_date" name="required_date" value="<?= $v('required_date') ?>" min="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group col-md-12"><label for="notes">Anything else</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Customs status, bill of lading number, special handling"><?= $v('notes') ?></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg"><i class="fe fe-send fe-12 mr-1"></i>Send request</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php'; ?>
