<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var array<string, mixed> $driver
 * @var list<array<string, mixed>> $documents
 * @var list<array<string, mixed>> $payments
 * @var list<array<string, mixed>> $changes
 * @var list<array<string, mixed>> $deductions
 */
use Models\Currency;

$pageScripts = ['/assets/js/lms-driver.js'];
$pending = array_values(array_filter($changes, static fn (array $c): bool => $c['status'] === 'pending'));
?>
<div class="container-fluid lms-app">
  <a class="lms-app-back" href="<?= url('my-trips') ?>"><i class="fe fe-arrow-left mr-1"></i>Back</a>
  <h3 class="mb-3"><i class="fe fe-user mr-2"></i>My profile</h3>

  <div class="card shadow-sm mb-3"><div class="card-body">
    <strong><?= e($driver['full_name']) ?></strong>
    <div class="small text-muted"><?= e($driver['phone'] ?? '') ?> · <?= $driver['payment_method'] === 'cash' ? 'Paid in cash' : 'MoMo ' . e($driver['momo_number'] ?? '—') . ' (' . e($driver['momo_name'] ?? '') . ')' ?></div>
  </div></div>

  <div class="card shadow-sm mb-3"><div class="card-header"><strong>My documents</strong></div>
    <ul class="list-group list-group-flush">
      <?php if ($documents === []): ?><li class="list-group-item text-muted small">None on file: ask HR.</li><?php endif; ?>
      <?php foreach ($documents as $document): ?>
        <?php $days = days_until($document['expiry_date']); ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span><?= e($document['type_name']) ?><small class="d-block text-muted"><?= e($document['document_number'] ?? '') ?></small></span>
          <span class="text-right">
            <span class="badge badge-<?= $days === null ? 'light' : ($days < 0 ? 'danger' : ($days <= 30 ? 'warning' : 'success')) ?>"><?= $document['expiry_date'] ? e(human_date($document['expiry_date'])) : 'No expiry' ?></span>
            <?php if (!empty($document['document_file'])): ?><a class="d-block small" target="_blank" rel="noopener" href="<?= e(url(array_merge(['files'], explode('/', (string) $document['document_file'])))) ?>">Open</a><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card shadow-sm mb-3"><div class="card-header"><strong>Money sent to me</strong></div>
    <ul class="list-group list-group-flush">
      <?php if ($payments === []): ?><li class="list-group-item text-muted small">Nothing yet.</li><?php endif; ?>
      <?php foreach (array_slice($payments, 0, 15) as $payment): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span><?= e(Currency::format((float) $payment['amount'], (string) $payment['currency'])) ?><small class="d-block text-muted"><?= e($payment['form_code'] . ' ' . $payment['request_no']) ?> · <?= e(human_date($payment['paid_at'] ?? $payment['created_at'])) ?></small></span>
          <?php if ($payment['status'] === 'paid' && $payment['confirmed_at'] === null): ?>
            <form method="post" action="<?= url(['my-trips', 'money', $payment['id'], 'confirm']) ?>" data-offline><?= csrf_field() ?><button class="btn btn-sm btn-success" type="submit">Money received</button></form>
          <?php else: ?>
            <span class="badge badge-<?= $payment['confirmed_at'] ? 'success' : ($payment['status'] === 'failed' ? 'danger' : 'light') ?>"><?= $payment['confirmed_at'] ? 'Received' : e(ucfirst($payment['status'])) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if ($deductions !== []): ?>
    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Deductions</strong></div>
      <ul class="list-group list-group-flush small"><?php foreach ($deductions as $deduction): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($deduction['title']) ?></span><span><?= e(Currency::format((float) $deduction['total_amount'], (string) $deduction['currency'])) ?></span></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-3"><div class="card-header"><strong>Change my phone or MoMo number</strong></div>
    <div class="card-body">
      <?php if ($pending !== []): ?>
        <div class="alert alert-info small mb-0">Waiting for the OM: <?= e($pending[0]['new_number']) ?> (<?= e($pending[0]['new_name']) ?>). You are paid on the old number until it is approved.</div>
      <?php else: ?>
        <form method="post" action="<?= url(['my-trips', 'profile', 'momo']) ?>">
          <?= csrf_field() ?>
          <div class="form-group"><label>New MoMo number</label><input class="form-control" type="tel" name="new_number" required></div>
          <div class="form-group"><label>Name MTN has on that number</label><input class="form-control" name="new_name" required></div>
          <div class="form-group"><label>New phone (if it changes too)</label><input class="form-control" type="tel" name="new_phone"></div>
          <button class="btn btn-outline-primary btn-block" type="submit">Send to the OM</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm mb-3"><div class="card-header"><strong>My cash PIN</strong></div>
    <form method="post" action="<?= url(['my-trips', 'profile', 'pin']) ?>" class="card-body">
      <?= csrf_field() ?>
      <p class="small text-muted"><?= empty($driver['cash_pin_hash']) ? 'Set a PIN to confirm cash taken at the cash office.' : 'Your PIN is set. Type a new one to change it.' ?></p>
      <div class="form-row"><div class="col-6"><input class="form-control" type="password" inputmode="numeric" name="pin" placeholder="4-6 digits" required></div><div class="col-6"><input class="form-control" type="password" inputmode="numeric" name="pin_confirm" placeholder="Again" required></div></div>
      <button class="btn btn-outline-primary btn-block mt-2" type="submit">Save PIN</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
