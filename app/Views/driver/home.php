<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var array<string, mixed> $driver
 * @var ?array $trip
 * @var list<array<string, mixed>> $toConfirm
 * @var list<array<string, mixed>> $support
 */
use Models\Currency;
use Models\TripLife;

$pageScripts = ['/assets/js/lms-driver.js'];
?>
<div class="container-fluid lms-app">
  <div class="lms-app-hello">
    <p class="lms-kicker mb-0">Hello</p>
    <h2 class="mb-1"><?= e($driver['full_name']) ?></h2>
    <p class="text-muted mb-0"><?= $trip !== null ? e($trip['reference_code'] . ' · ' . ($trip['route_code'] ?? '') . ' · ' . ($trip['plate_number'] ?? '')) . ' · <strong>' . e(TripLife::STATUS[$trip['status']] ?? $trip['status']) . '</strong>' : 'No trip right now.' ?></p>
  </div>

  <div class="lms-offline-note alert alert-warning d-none" data-offline-note><i class="fe fe-wifi-off mr-1"></i>No signal: what you send is kept on the phone and sent when the signal returns.</div>
  <div class="lms-outbox alert alert-info d-none" data-outbox-note></div>

  <?php foreach ($toConfirm as $line): ?>
    <form method="post" action="<?= url(['my-trips', 'money', $line['id'], 'confirm']) ?>" class="lms-app-money" data-offline>
      <?= csrf_field() ?>
      <div>
        <strong><?= e(Currency::format((float) $line['amount'], (string) $line['currency'])) ?></strong> sent to you
        <small class="d-block"><?= e($line['form_code'] . ' ' . $line['request_no']) ?> · <?= e(strtoupper((string) $line['method'])) ?> <?= e($line['mtn_batch_no'] ?? $line['batch_no']) ?></small>
      </div>
      <button class="btn btn-success btn-lg" type="submit"><i class="fe fe-check mr-1"></i>Money received</button>
    </form>
  <?php endforeach; ?>

  <div class="lms-app-grid">
    <a class="lms-app-btn" href="<?= url(['my-trips', 'trip']) ?>"><i class="fe fe-navigation"></i><span>My trip</span></a>
    <a class="lms-app-btn" href="<?= url(['my-trips', 'delivery']) ?>"><i class="fe fe-clipboard"></i><span>Delivery note</span></a>
    <a class="lms-app-btn lms-app-btn-alert" href="<?= url(['my-trips', 'support']) ?>"><i class="fe fe-alert-triangle"></i><span>Report a problem / Ask for support</span></a>
    <a class="lms-app-btn" href="<?= url(['my-trips', 'handover']) ?>"><i class="fe fe-key"></i><span>Hand over truck</span></a>
    <a class="lms-app-btn" href="<?= url(['my-trips', 'profile']) ?>"><i class="fe fe-user"></i><span>My profile</span></a>
  </div>

  <?php foreach ($shortages ?? [] as $shortage): ?>
    <?php $open = in_array($shortage['status'], ['sent', 'verified', 'returned'], true); ?>
    <div class="card shadow-sm mt-3 border-warning">
      <div class="card-body">
        <strong><i class="fe fe-alert-circle mr-1"></i>Shortage on <?= e($shortage['trip_code'] ?? '') ?></strong>
        <span class="badge badge-<?= $open ? 'warning' : ($shortage['status'] === 'cancelled' ? 'secondary' : 'danger') ?> float-right"><?= e($open ? 'Being checked' : ($shortage['status'] === 'cancelled' ? 'Dropped' : 'Deducted on salary')) ?></span>
        <p class="mb-1 small">Loaded <?= e((string) ($shortage['payload']['loaded'] ?? '')) ?>, delivered <?= e((string) ($shortage['payload']['delivered'] ?? '')) ?> <?= e((string) ($shortage['payload']['unit'] ?? '')) ?>: <?= e(Currency::format((float) $shortage['total_amount'], (string) $shortage['currency'])) ?></p>
        <?php if (!empty($shortage['payload']['explanation'])): ?>
          <p class="small text-muted mb-0">You explained: <?= e($shortage['payload']['explanation']) ?></p>
        <?php elseif ($open): ?>
          <form method="post" action="<?= url(['my-trips', 'shortage', $shortage['id'], 'explain']) ?>" data-offline>
            <?= csrf_field() ?>
            <textarea class="form-control mb-2" name="explanation" rows="2" required placeholder="What happened (meter reading at the customer, leak, seal broken...)"></textarea>
            <button class="btn btn-outline-primary btn-block" type="submit">Send my explanation</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($support !== []): ?>
    <div class="card shadow-sm mt-3">
      <div class="card-header"><strong>My requests</strong></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($support as $request): ?>
          <li class="list-group-item"><strong><?= e($request['request_code']) ?></strong> <?= e(\Models\DriverSupport::TYPES[$request['support_type']] ?? '') ?>
            <span class="badge badge-<?= ['open' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'rejected' => 'danger'][$request['status']] ?? 'light' ?> float-right"><?= e(['open' => 'Sent', 'in_progress' => 'Being handled', 'resolved' => 'Resolved', 'rejected' => 'Refused'][$request['status']] ?? $request['status']) ?></span>
            <?php if ($request['response'] || $request['rejection_reason']): ?><small class="d-block text-muted"><?= e($request['response'] ?: $request['rejection_reason']) ?></small><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
