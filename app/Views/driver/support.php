<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $driver @var ?array $trip @var list<array<string, mixed>> $requests */
use Models\Currency;
use Models\DriverSupport;

$pageScripts = ['/assets/js/lms-driver.js'];
$icons = ['money' => 'dollar-sign', 'breakdown' => 'tool', 'accident' => 'alert-octagon', 'police_border' => 'flag', 'health' => 'heart', 'documents' => 'file-text', 'other' => 'help-circle'];
?>
<div class="container-fluid lms-app">
  <a class="lms-app-back" href="<?= url('my-trips') ?>"><i class="fe fe-arrow-left mr-1"></i>Back</a>
  <h3 class="mb-3"><i class="fe fe-alert-triangle mr-2"></i>Report a problem / Ask for support</h3>
  <div class="lms-offline-note alert alert-warning d-none" data-offline-note><i class="fe fe-wifi-off mr-1"></i>No signal: your request is kept and sent when the signal returns. In an emergency, call the OM.</div>

  <form method="post" action="<?= url(['my-trips', 'support']) ?>" enctype="multipart/form-data" class="card shadow-sm mb-3" data-offline data-geo>
    <?= csrf_field() ?>
    <input type="hidden" name="lat"><input type="hidden" name="lng">
    <div class="card-body">
      <label>What is it? <span class="lms-required">*</span></label>
      <div class="lms-app-choices mb-3">
        <?php foreach (DriverSupport::TYPES as $key => $label): ?>
          <label class="lms-app-choice"><input type="radio" name="type" value="<?= e($key) ?>" required><span><i class="fe fe-<?= e($icons[$key] ?? 'circle') ?>"></i><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="form-group"><label>What happened, what you need <span class="lms-required">*</span></label><textarea class="form-control" name="details" rows="3" required></textarea></div>
      <div class="form-row lms-money-fields">
        <div class="col-7 form-group"><label>Amount needed</label><input class="form-control" type="number" inputmode="decimal" min="0" step="1" name="amount"></div>
        <div class="col-5 form-group"><label>Currency</label><select class="form-control" name="currency"><?php foreach (Currency::options() as $code => $label): ?><option value="<?= e($code) ?>" <?= $code === \Models\Company::baseCurrency() ? 'selected' : '' ?>><?= e($code) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-group"><label>Where are you?</label><input class="form-control" name="location" placeholder="Town, border post, km mark"><small class="text-muted" data-geo-status></small></div>
      <div class="form-group"><label>How urgent</label><select class="form-control" name="urgency"><option value="urgent">Urgent</option><option value="emergency">Emergency</option><option value="normal">Can wait</option></select></div>
      <div class="form-group"><label>Photos</label><input class="form-control-file" type="file" name="photos[]" accept="image/*" capture="environment" multiple></div>
      <button class="btn btn-danger btn-lg btn-block" type="submit"><i class="fe fe-send mr-2"></i>Send to the OM</button>
    </div>
  </form>

  <?php if ($requests !== []): ?>
    <div class="card shadow-sm"><div class="card-header"><strong>My requests</strong></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($requests as $request): ?>
          <li class="list-group-item"><strong><?= e($request['request_code']) ?></strong> · <?= e(DriverSupport::TYPES[$request['support_type']] ?? '') ?> · <?= e(human_date($request['created_at'], true)) ?>
            <span class="badge badge-<?= ['open' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'rejected' => 'danger'][$request['status']] ?? 'light' ?> float-right"><?= e(['open' => 'Sent', 'in_progress' => 'Being handled', 'resolved' => 'Resolved', 'rejected' => 'Refused'][$request['status']] ?? $request['status']) ?></span>
            <div class="small text-muted"><?= e($request['details']) ?></div>
            <?php if ($request['response'] || $request['rejection_reason']): ?><div class="small"><strong>Answer:</strong> <?= e($request['response'] ?: $request['rejection_reason']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
