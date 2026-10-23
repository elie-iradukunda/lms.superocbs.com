<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var array<string, mixed> $batch
 * @var list<array<string, mixed>> $lines
 */
use Models\Currency;

$id = (int) $batch['id'];
$canPay = role_can('payment_batches', 'edit');
$isCash = $batch['method'] === 'cash';
?>
<div class="container-fluid lms-detail-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker"><?= e(strtoupper((string) $batch['method'])) ?> batch · <?= e($batch['company_name']) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-layers mr-2"></i><?= e($batch['batch_no']) ?></h2>
      <p class="lms-page-sub"><?= (int) $batch['line_count'] ?> payee<?= (int) $batch['line_count'] === 1 ? '' : 's' ?> · <?= e(Currency::format((float) $batch['total_amount'], (string) $batch['currency'])) ?> · built by <?= e($batch['built_name'] ?? '') ?> <?= e(human_date($batch['built_at'], true)) ?><?= $batch['method_name'] ? ' · from ' . e($batch['method_name']) : '' ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('payment_batches') ?>">Payments</a>
      <?php if (!$isCash): ?><a class="btn btn-outline-primary" href="<?= url(['payment_batches', $id, 'mtn']) ?>"><i class="fe fe-download fe-12 mr-1"></i>MTN bulk file</a><?php endif; ?>
      <?php if (!empty($batch['proof_file'])): ?><a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= e(\Models\Uploads::url($batch['proof_file'])) ?>"><i class="fe fe-paperclip fe-12 mr-1"></i>MTN proof</a><?php endif; ?>
    </div>
  </div>

  <?php if (!$isCash && $batch['status'] === 'built'): ?>
    <div class="alert alert-info">
      <strong>In MTN:</strong> upload the file, then initiate → verify → approve → execute as usual.
      <strong>Back here:</strong> enter MTN's batch number, upload the proof or statement, and tick any line that failed.
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url(['payment_batches', $id, 'record']) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card shadow-sm mb-3">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Request</th><th>Paid to</th><th>MoMo number</th><th>Registered name</th><th class="text-right">Amount</th><th>Status</th><th>Money received</th><?php if (!$isCash && $batch['status'] === 'built' && $canPay): ?><th>Failed?</th><?php endif; ?><?php if ($isCash): ?><th>Pay at the counter</th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($lines as $line): ?>
            <tr>
              <td><a href="<?= url(['form_requests', $line['request_id']]) ?>"><?= e($line['form_code'] . ' ' . $line['request_no']) ?></a></td>
              <td><strong><?= e($line['payee_name']) ?></strong></td>
              <td><?= e(\Models\Payments::msisdn((string) $line['momo_number'])) ?></td>
              <td><?= e($line['momo_name'] ?? '') ?><?php if (!empty($line['momo_name']) && strcasecmp(trim((string) $line['momo_name']), trim((string) $line['payee_name'])) !== 0): ?> <span class="badge badge-warning" title="The name MTN has on the number differs from the payee">check</span><?php endif; ?></td>
              <td class="text-right lms-num"><?= e(Currency::format((float) $line['amount'], (string) $line['currency'])) ?></td>
              <td><span class="badge badge-<?= ['paid' => 'success', 'failed' => 'danger'][$line['status']] ?? 'light' ?>"><?= e(ucfirst($line['status'])) ?></span><?= $line['failure_reason'] ? '<small class="d-block text-danger">' . e($line['failure_reason']) . '</small>' : '' ?><?= $line['receipt_no'] ? '<small class="d-block"><a target="_blank" rel="noopener" href="' . url(['payment_batches', $id, 'lines', $line['id'], 'receipt']) . '">Receipt ' . e($line['receipt_no']) . '</a></small>' : '' ?></td>
              <td><?= $line['confirmed_at'] ? '<span class="text-success"><i class="fe fe-check"></i> ' . e(human_date($line['confirmed_at'], true)) . '</span><small class="d-block text-muted">' . e(['app' => 'In the app', 'pin' => 'With PIN', 'signature' => 'Signed'][$line['confirmed_via']] ?? '') . '</small>' : ($line['status'] === 'paid' ? '<span class="text-warning">Waiting</span>' : '—') ?></td>
              <?php if (!$isCash && $batch['status'] === 'built' && $canPay): ?>
                <td style="min-width:220px">
                  <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="f<?= (int) $line['id'] ?>" name="failed[<?= (int) $line['id'] ?>]" value="1"><label class="custom-control-label" for="f<?= (int) $line['id'] ?>">Failed</label></div>
                  <input class="form-control form-control-sm mt-1" name="failure_reason[<?= (int) $line['id'] ?>]" placeholder="Reason, if it failed">
                </td>
              <?php endif; ?>
              <?php if ($isCash): ?>
                <td style="min-width:260px">
                  <?php if ($line['status'] !== 'paid' && $canPay): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#cash<?= (int) $line['id'] ?>">Pay cash</button>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$isCash && $batch['status'] === 'built' && $canPay): ?>
        <div class="card-body border-top">
          <div class="row">
            <div class="col-md-4 form-group"><label>MTN batch number <span class="lms-required">*</span></label><input class="form-control" name="mtn_batch_no" required></div>
            <div class="col-md-5 form-group"><label>MTN proof or statement <span class="lms-required">*</span></label><input class="form-control-file" type="file" name="proof" required accept=".pdf,.png,.jpg,.jpeg,.webp,.xls,.xlsx,.csv"></div>
            <div class="col-md-3 form-group d-flex align-items-end"><button class="btn btn-success btn-block" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Record payment</button></div>
          </div>
          <small class="text-muted">Every line not ticked as failed is marked paid, posted to the books, and its payee is asked to confirm the money arrived.</small>
        </div>
      <?php elseif (!empty($batch['mtn_batch_no'])): ?>
        <div class="card-body border-top small">MTN batch <strong><?= e($batch['mtn_batch_no']) ?></strong> recorded by <?= e($batch['recorded_name'] ?? '') ?> <?= e(human_date($batch['recorded_at'], true)) ?>.</div>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($isCash && $canPay): ?>
    <?php foreach ($lines as $line): ?>
      <?php if ($line['status'] === 'paid') { continue; } ?>
      <div class="collapse" id="cash<?= (int) $line['id'] ?>">
        <form method="post" action="<?= url(['payment_batches', $id, 'lines', $line['id'], 'cash']) ?>" class="card shadow-sm mb-3 lms-cash-form">
          <?= csrf_field() ?>
          <div class="card-header"><strong>Pay <?= e($line['payee_name']) ?>: <?= e(Currency::format((float) $line['amount'], (string) $line['currency'])) ?></strong></div>
          <div class="card-body row">
            <div class="col-md-4 form-group">
              <label>The driver's PIN</label>
              <input class="form-control" type="password" name="pin" inputmode="numeric" autocomplete="off" placeholder="Typed by the person paid">
              <small class="text-muted">Or sign below on the screen.</small>
            </div>
            <div class="col-md-8 form-group">
              <label>Signature of the person paid</label>
              <canvas class="lms-sign-pad" width="460" height="140" style="border:1px dashed #9aa5b4;border-radius:6px;touch-action:none;max-width:100%;background:#fff"></canvas>
              <input type="hidden" name="signature">
              <div><button type="button" class="btn btn-sm btn-link lms-sign-clear">Clear</button></div>
            </div>
          </div>
          <div class="card-footer text-right"><button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Paid — print the receipt after</button></div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('.lms-cash-form').forEach(function (form) {
  var canvas = form.querySelector('.lms-sign-pad'), ctx = canvas.getContext('2d'), drawing = false, used = false;
  ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1b2a3a';
  function point(e) { var r = canvas.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return [(t.clientX - r.left) * canvas.width / r.width, (t.clientY - r.top) * canvas.height / r.height]; }
  function start(e) { drawing = true; used = true; var p = point(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); e.preventDefault(); }
  function move(e) { if (!drawing) { return; } var p = point(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); e.preventDefault(); }
  function end() { drawing = false; }
  canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, {passive: false}); canvas.addEventListener('touchmove', move, {passive: false}); canvas.addEventListener('touchend', end);
  form.querySelector('.lms-sign-clear').addEventListener('click', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); used = false; });
  form.addEventListener('submit', function () { if (used) { form.querySelector('input[name=signature]').value = canvas.toDataURL('image/png'); } });
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
