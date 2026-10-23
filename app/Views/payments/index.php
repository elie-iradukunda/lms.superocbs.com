<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var list<array<string, mixed>> $toPay
 * @var list<array<string, mixed>> $batches
 * @var array<string, float> $cash  cash box balance per currency
 * @var array<int, string> $methods
 */
use Models\Currency;

$canBuild = role_can('payment_batches', 'create');
$owed = [];
foreach ($toPay as $row) {
    $owed[$row['currency']] = ($owed[$row['currency']] ?? 0) + (float) $row['amount'];
}
$statusTone = ['built' => 'warning', 'recorded' => 'success', 'partly_failed' => 'danger', 'cancelled' => 'secondary'];
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Cash office</p>
      <h2 class="lms-page-title"><i class="fe fe-dollar-sign mr-2"></i>Payments</h2>
      <p class="lms-page-sub">Authorized requests, one line per payee. Tick the lines to pay together and build the batch: one currency, one method, one company per batch. Failed lines come back here with their reason.</p>
    </div>
  </div>

  <div class="lms-highlights">
    <?php foreach ($owed as $code => $sum): ?>
      <div class="lms-highlight"><span class="lms-highlight-icon"><i class="fe fe-clock fe-16"></i></span><div><small>To pay (<?= e($code) ?>)</small><strong><?= e(Currency::format($sum, (string) $code)) ?></strong></div></div>
    <?php endforeach; ?>
    <?php foreach ($cash as $code => $balance): ?>
      <div class="lms-highlight"><span class="lms-highlight-icon"><i class="fe fe-archive fe-16"></i></span><div><small>Cash box (<?= e($code) ?>)</small><strong><?= e(Currency::format($balance, (string) $code)) ?></strong></div></div>
    <?php endforeach; ?>
    <?php if ($owed === []): ?><div class="lms-highlight"><span class="lms-highlight-icon"><i class="fe fe-check fe-16"></i></span><div><small>To pay</small><strong>Nothing waiting</strong></div></div><?php endif; ?>
  </div>

  <form method="post" action="<?= url(['payment_batches', 'build']) ?>" class="card shadow-sm mb-4">
    <?= csrf_field() ?>
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
      <strong><i class="fe fe-list fe-14 mr-1"></i>To pay</strong>
      <div class="d-flex flex-wrap" style="gap:8px">
        <select class="form-control form-control-sm" style="width:auto" onchange="location.href='<?= url('payment_batches') ?>?currency=' + this.value + '&method=<?= e($_GET['method'] ?? '') ?>'">
          <option value="">Every currency</option>
          <?php foreach (Currency::options() as $code => $label): ?><option value="<?= e($code) ?>" <?= ($_GET['currency'] ?? '') === $code ? 'selected' : '' ?>><?= e($code) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control form-control-sm" style="width:auto" onchange="location.href='<?= url('payment_batches') ?>?method=' + this.value + '&currency=<?= e($_GET['currency'] ?? '') ?>'">
          <option value="">MoMo, cash and bank</option>
          <?php foreach (['momo' => 'MoMo', 'cash' => 'Cash', 'bank' => 'Bank'] as $key => $label): ?><option value="<?= $key ?>" <?= ($_GET['method'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th style="width:32px"><input type="checkbox" onclick="document.querySelectorAll('.lms-pay-key').forEach(function (b) { b.checked = this.checked; }, this)" aria-label="Tick all"></th><th>Request</th><th>Payee</th><th>MoMo / method</th><th>Trucks</th><th>Authorized</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
        <?php if ($toPay === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Nothing is waiting to be paid.</td></tr><?php endif; ?>
        <?php foreach ($toPay as $row): ?>
          <tr class="<?= (int) $row['has_failed'] === 1 ? 'table-danger' : '' ?>">
            <td><input type="checkbox" class="lms-pay-key" name="keys[]" value="<?= e($row['key']) ?>" <?= $canBuild ? '' : 'disabled' ?>></td>
            <td><a href="<?= url(['form_requests', $row['request_id']]) ?>"><strong><?= e($row['form_code'] . ' ' . $row['request_no']) ?></strong></a><small class="d-block text-muted"><?= e($row['title']) ?></small></td>
            <td><?= e($row['payee_name']) ?><?= (int) $row['has_failed'] === 1 ? ' <span class="badge badge-danger">Failed before</span>' : '' ?></td>
            <td><?= $row['payment_method'] === 'momo' ? e($row['momo_number']) . '<small class="d-block text-muted">' . e($row['momo_name'] ?? '') . '</small>' : e(ucfirst((string) $row['payment_method'])) ?></td>
            <td><?= e($row['trucks'] ?? '') ?></td>
            <td><?= e(human_date($row['authorized_at'] ?? null)) ?></td>
            <td class="text-right lms-num"><strong><?= e(Currency::format((float) $row['amount'], (string) $row['currency'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($canBuild && $toPay !== []): ?>
      <div class="card-body border-top d-flex flex-wrap align-items-end" style="gap:10px">
        <div><label class="small mb-1">Paid from</label>
          <select class="form-control" name="payment_method_id"><option value="">The default MoMo, cash or bank account</option><?php foreach ($methods as $methodId => $label): ?><option value="<?= (int) $methodId ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="flex-fill"><label class="small mb-1">Note</label><input class="form-control" name="notes" placeholder="Optional"></div>
        <button class="btn btn-primary" type="submit"><i class="fe fe-layers fe-12 mr-1"></i>Build batch</button>
      </div>
    <?php endif; ?>
  </form>

  <div class="card shadow-sm">
    <div class="card-header"><strong><i class="fe fe-archive fe-14 mr-1"></i>Batches</strong></div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Batch</th><th>Method</th><th>MTN batch</th><th>Lines</th><th class="text-right">Total</th><th>Built</th><th>Status</th><th>Not yet confirmed</th></tr></thead>
        <tbody>
        <?php if ($batches === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No batch yet.</td></tr><?php endif; ?>
        <?php foreach ($batches as $batch): ?>
          <tr>
            <td><a href="<?= url(['payment_batches', $batch['id']]) ?>"><strong><?= e($batch['batch_no']) ?></strong></a></td>
            <td><?= e(strtoupper((string) $batch['method'])) ?></td>
            <td><?= e($batch['mtn_batch_no'] ?? '—') ?></td>
            <td><?= (int) $batch['line_count'] ?><?= (int) $batch['failed_lines'] > 0 ? ' <span class="badge badge-danger">' . (int) $batch['failed_lines'] . ' failed</span>' : '' ?></td>
            <td class="text-right lms-num"><?= e(Currency::format((float) $batch['total_amount'], (string) $batch['currency'])) ?></td>
            <td><?= e($batch['built_name'] ?? '') ?><small class="d-block text-muted"><?= e(human_date($batch['built_at'], true)) ?></small></td>
            <td><span class="badge badge-<?= e($statusTone[$batch['status']] ?? 'light') ?>"><?= e(['built' => 'Waiting for MTN', 'recorded' => 'Recorded', 'partly_failed' => 'Some lines failed', 'cancelled' => 'Cancelled'][$batch['status']] ?? $batch['status']) ?></span></td>
            <td><?= (int) $batch['unconfirmed'] > 0 ? '<span class="text-warning">' . (int) $batch['unconfirmed'] . ' driver' . ((int) $batch['unconfirmed'] === 1 ? '' : 's') . '</span>' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
