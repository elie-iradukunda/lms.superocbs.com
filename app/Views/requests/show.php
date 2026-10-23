<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var array<string, mixed> $request
 * @var list<array<string, mixed>> $lines
 * @var list<array<string, mixed>> $events
 * @var ?string $step     the step the request waits at
 * @var ?string $refusal  why I may not sign it, when I may not
 * @var list<string> $problems
 * @var list<array<string, mixed>> $payments
 * @var list<array<string, mixed>> $entries
 */
use Models\Currency;
use Models\RequestForms;
use Models\RequestFormConfig;
use Support\Words;

$id = (int) $request['id'];
$money = (int) $request['is_money'] === 1;
$statusTone = ['draft' => 'secondary', 'sent' => 'warning', 'verified' => 'info', 'authorized' => 'primary', 'partly_paid' => 'primary', 'paid' => 'success', 'returned' => 'danger', 'cancelled' => 'secondary', 'closed' => 'success'];
$editable = in_array($request['status'], ['draft', 'returned'], true) && ((int) $request['created_by'] === (int) current_user_id() || (int) ($request['prepared_by'] ?? 0) === (int) current_user_id() || can_edit('form_requests'));
$amount = static fn (float $value): string => Currency::format($value, (string) $request['currency']);
$stepWords = ['prepared' => 'Prepared', 'resent' => 'Corrected and resent', 'verified' => 'Verified', 'authorized' => 'Authorized and stamped', 'paid' => 'Paid in full', 'returned' => 'Sent back', 'cancelled' => 'Cancelled', 'created' => 'Created', 'confirmed' => 'Confirmed', 'received' => 'Received', 'forwarded' => 'Forwarded', 'note' => 'Note'];
$stepIcons = ['prepared' => 'send', 'resent' => 'repeat', 'verified' => 'check', 'authorized' => 'award', 'paid' => 'dollar-sign', 'returned' => 'corner-up-left', 'cancelled' => 'x', 'created' => 'file-plus', 'confirmed' => 'check-circle'];
$config = RequestFormConfig::for((string) $request['form_code']);
$payload = is_array($request['payload']) ? $request['payload'] : [];
?>
<div class="container-fluid lms-detail-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker"><?= e($request['form_code'] . ' — ' . $request['form_name']) ?> · <?= e($request['company_name']) ?></p>
      <h2 class="lms-page-title"><?= e($request['request_no'] ?: 'Draft #' . $id) ?> <span class="badge badge-<?= e($statusTone[$request['status']] ?? 'secondary') ?> ml-2"><?= e(RequestForms::STATUS[$request['status']] ?? $request['status']) ?></span></h2>
      <p class="lms-page-sub"><?= e($request['title']) ?><?= (int) $request['version'] > 1 ? ' · version ' . (int) $request['version'] : '' ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('form_requests') ?>">All requests</a>
      <?php if ($request['status'] !== 'draft'): ?><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= url(['form_requests', $id, 'pdf']) ?>"><i class="fe fe-printer fe-12 mr-1"></i>PDF</a><?php endif; ?>
      <?php if ($editable): ?><a class="btn btn-outline-primary" href="<?= url(['form_requests', $id, 'edit']) ?>"><i class="fe fe-edit-2 fe-12 mr-1"></i>Edit</a><?php endif; ?>
      <?php if ($request['form_code'] === 'F5' && $request['status'] === 'authorized' && can_view('goods_receipts')): ?><a class="btn btn-primary" href="<?= url(['goods_receipts', $id]) ?>"><i class="fe fe-download fe-12 mr-1"></i>Receive</a><?php endif; ?>
      <?php if ($request['form_code'] === 'F4' && can_view('store_issues')): ?><a class="btn btn-primary" href="<?= url(['store_issues', $id]) ?>"><i class="fe fe-package fe-12 mr-1"></i>Store issue</a><?php endif; ?>
      <?php if ($request['job_card_id'] && can_view('garage')): ?><a class="btn btn-outline-secondary" href="<?= url(['garage', $request['job_card_id']]) ?>"><i class="fe fe-tool fe-12 mr-1"></i>Job card</a><?php endif; ?>
    </div>
  </div>

  <?php $supplierAnswer = (is_array($request['payload'] ?? null) ? $request['payload'] : (json_decode((string) ($request['payload'] ?? ''), true) ?: []))['supplier_answer'] ?? null; ?>
  <?php if (is_array($supplierAnswer)): ?>
    <div class="alert alert-<?= $supplierAnswer['answer'] === 'accept' ? 'success' : 'danger' ?>"><i class="fe fe-<?= $supplierAnswer['answer'] === 'accept' ? 'check' : 'x' ?> mr-1"></i>The supplier <?= $supplierAnswer['answer'] === 'accept' ? 'accepted' : 'declined' ?> this order <?= e(human_date($supplierAnswer['at'], true)) ?><?= !empty($supplierAnswer['reason']) ? ': ' . e($supplierAnswer['reason']) : '.' ?></div>
  <?php endif; ?>

  <?php if ($request['status'] === 'returned' && !empty($request['returned_reason'])): ?>
    <div class="alert alert-danger"><strong><i class="fe fe-corner-up-left mr-1"></i>Sent back:</strong> <?= e($request['returned_reason']) ?></div>
  <?php endif; ?>

  <?php if ($problems !== []): ?>
    <div class="alert alert-warning lms-error-block">
      <strong>Before it can be sent</strong>
      <ul class="mb-0 mt-2"><?php foreach ($problems as $problem): ?><li><?= e($problem) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php // What the signed-in person can do with this request now. ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap align-items-center" style="gap:10px">
      <?php if (in_array($request['status'], ['draft', 'returned'], true) && $editable): ?>
        <form method="post" action="<?= url(['form_requests', $id, 'act', 'send']) ?>" class="d-inline"><?= csrf_field() ?>
          <button class="btn btn-primary" type="submit" <?= $problems !== [] ? 'disabled' : '' ?>><i class="fe fe-send fe-12 mr-1"></i><?= $request['status'] === 'returned' ? 'Correct and resend' : 'Send' ?></button>
        </form>
        <form method="post" action="<?= url(['form_requests', $id, 'act', 'cancel']) ?>" class="d-inline reason-form" data-reason-title="Cancel this request"><?= csrf_field() ?><input type="hidden" name="reason">
          <button class="btn btn-outline-danger" type="submit"><i class="fe fe-x fe-12 mr-1"></i>Cancel request</button>
        </form>
        <span class="small text-muted">Once sent it is locked, and your name and signature go on it.</span>
      <?php elseif ($step !== null && $step !== 'pay' && $refusal === null): ?>
        <form method="post" action="<?= url(['form_requests', $id, 'act', $step]) ?>" class="d-flex flex-wrap align-items-center" style="gap:8px"><?= csrf_field() ?>
          <input type="text" name="comment" class="form-control" style="max-width:280px" placeholder="Comment (optional)">
          <button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i><?= e(['verify' => 'Verify', 'authorize' => 'Authorize and stamp', 'confirm' => 'Confirm'][$step]) ?></button>
        </form>
        <form method="post" action="<?= url(['form_requests', $id, 'act', 'send-back']) ?>" class="d-inline reason-form" data-reason-title="<?= $step === 'verify' ? 'Reject' : 'Send back' ?> <?= e($request['request_no']) ?>"><?= csrf_field() ?><input type="hidden" name="reason">
          <button class="btn btn-outline-danger" type="submit"><i class="fe fe-corner-up-left fe-12 mr-1"></i><?= $step === 'verify' ? 'Reject with a reason' : 'Send back with a reason' ?></button>
        </form>
        <span class="small text-muted"><?= $step === 'verify' ? 'Verifying posts the books.' : ($step === 'authorize' ? 'Authorizing makes it final and applies the company stamp.' : '') ?></span>
      <?php elseif ($step === 'pay'): ?>
        <span><i class="fe fe-dollar-sign mr-1"></i>Waiting for the Cashier.</span>
        <?php if (can_view('payment_batches')): ?><a class="btn btn-primary" href="<?= url('payment_batches') ?>">Go to To pay</a><?php endif; ?>
      <?php elseif ($step !== null): ?>
        <span class="text-muted"><i class="fe fe-clock mr-1"></i>Waiting: <?= e(RequestForms::STEP_LABELS[$step] ?? $step) ?>.<?= $refusal !== null ? ' ' . e($refusal) : '' ?></span>
      <?php else: ?>
        <span class="text-muted"><i class="fe fe-check-circle mr-1"></i>Nothing is waiting on this request.</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-8">
      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-info fe-16 mr-2"></i>Details</h3></div></header>
        <div class="card-body">
          <dl class="row mb-0 small">
            <?php foreach (array_filter([
                'Route' => trim(($request['route_code'] ?? '') . ' ' . ($request['route_name'] ?? '')),
                'Trip' => $request['trip_code'] ?? '',
                'Truck' => $request['plate_number'] ?? '',
                'Driver' => $request['driver_name'] ?? '',
                'Supplier' => $request['supplier_name'] ?? '',
                'Customer' => $request['customer_name'] ?? '',
                'Sent to' => $request['to_company'] ?? '',
                'Period' => $request['period_from'] ? human_date($request['period_from']) . ' – ' . human_date($request['period_to'] ?: $request['period_from']) : '',
                'Needed on' => $request['needed_on'] ? human_date($request['needed_on']) : '',
                'Odometer' => isset($payload['odometer']) ? number_format((float) $payload['odometer']) . ' km' : '',
                'Problem' => $payload['problem'] ?? '',
                'Support for' => isset($payload['support_type']) ? (RequestFormConfig::SUPPORT_TYPES[$payload['support_type']] ?? $payload['support_type']) : '',
                'Location' => $payload['location'] ?? '',
                'Supplier invoice' => trim(($payload['invoice_no'] ?? '') . (isset($payload['invoice_date']) ? ' of ' . human_date($payload['invoice_date']) : '')),
                'Shipment' => $payload['shipment_ref'] ?? '',
                'Why it differs' => $request['non_standard_reason'] ?? '',
                'Notes' => $request['notes'] ?? '',
            ], static fn ($v): bool => trim((string) $v) !== '') as $label => $value): ?>
              <dt class="col-sm-3"><?= e($label) ?></dt><dd class="col-sm-9"><?= nl2br(e((string) $value)) ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-list fe-16 mr-2"></i>Lines</h3></div></header>
        <div class="table-responsive">
          <table class="table table-sm mb-0 lms-table">
            <thead><tr><th>#</th><th>Truck</th><th>Driver</th><th>Description</th><th class="text-right">Qty</th><?php if ($money): ?><th class="text-right">Unit price</th><th class="text-right">Amount</th><th>Paid to</th><th>Payment</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($lines as $index => $line): ?>
              <?php
              $differs = ($line['standard_quantity'] !== null && abs((float) $line['standard_quantity'] - (float) $line['quantity']) > 0.004)
                  || ($line['standard_amount'] !== null && abs((float) $line['standard_amount'] - (float) $line['amount']) > 0.004);
              $extra = is_array($line['extra']) ? array_diff_key($line['extra'], ['supplier_id' => 1, 'rate_id' => 1]) : [];
              ?>
              <tr class="<?= $differs ? 'table-warning' : '' ?>">
                <td><?= $index + 1 ?></td>
                <td><?= e(trim(($line['plate_number'] ?? '') . ($line['trailer_number'] ? ' / ' . $line['trailer_number'] : ''))) ?: '—' ?></td>
                <td><?= e($line['driver_name'] ?? '—') ?></td>
                <td><?= e($line['description']) ?>
                  <?php if ($extra !== []): ?><small class="d-block text-muted"><?= e(implode(' · ', array_map(static fn ($k, $v): string => ucfirst(str_replace('_', ' ', (string) $k)) . ': ' . $v, array_keys($extra), $extra))) ?></small><?php endif; ?>
                  <?php if ($differs): ?><small class="d-block text-warning">Rate card: <?= $line['standard_quantity'] !== null ? e(rtrim(rtrim(number_format((float) $line['standard_quantity'], 2), '0'), '.')) . ' ' . e($line['unit'] ?? '') : '' ?><?= $line['standard_amount'] !== null ? e($amount((float) $line['standard_amount'])) : '' ?></small><?php endif; ?>
                </td>
                <td class="text-right lms-num"><?= e(rtrim(rtrim(number_format((float) $line['quantity'], 2), '0'), '.')) ?> <?= e($line['unit'] ?? '') ?></td>
                <?php if ($money): ?>
                  <td class="text-right lms-num"><?= (float) $line['unit_price'] != 0 ? e(number_format((float) $line['unit_price'], 2)) : '' ?></td>
                  <td class="text-right lms-num"><strong><?= (float) $line['amount'] != 0 ? e(number_format((float) $line['amount'], 2)) : '' ?></strong></td>
                  <td><?= e($line['payee_name'] ?? '') ?><?php if (!empty($line['momo_number'])): ?><small class="d-block text-muted">MoMo <?= e($line['momo_number']) ?><?= $line['momo_name'] ? ' (' . e($line['momo_name']) . ')' : '' ?></small><?php elseif ($line['payment_method'] === 'cash'): ?><small class="d-block text-muted">Cash</small><?php endif; ?></td>
                  <td><?php if ($line['pay_status'] !== 'not_payable'): ?><span class="badge badge-<?= ['paid' => 'success', 'failed' => 'danger', 'in_batch' => 'info', 'unpaid' => 'light'][$line['pay_status']] ?? 'light' ?>"><?= e(ucfirst(str_replace('_', ' ', $line['pay_status']))) ?></span><?php endif; ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body border-top">
          <?php if ($money && (float) $request['total_amount'] > 0): ?>
            <div class="d-flex justify-content-between"><strong>Total</strong><strong class="lms-num"><?= e($amount((float) $request['total_amount'])) ?></strong></div>
            <div class="small text-muted"><?= e(Words::amount((float) $request['total_amount'], (string) $request['currency'])) ?> only.</div>
            <?php if ((float) $request['paid_amount'] > 0): ?><div class="small mt-1">Paid so far: <strong><?= e($amount((float) $request['paid_amount'])) ?></strong></div><?php endif; ?>
          <?php endif; ?>
          <?php if ((float) ($request['total_quantity'] ?? 0) > 0): ?>
            <div class="d-flex justify-content-between mt-1"><strong>Total fuel</strong><strong class="lms-num"><?= e(rtrim(rtrim(number_format((float) $request['total_quantity'], 2), '0'), '.')) ?> L</strong></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($request['form_code'] === 'F12' && !empty($request['parent_request_id'])): ?>
        <?php $match = \Models\SupplierPortal::match($id); $differences = array_filter($match, static fn (array $m): bool => $m['problems'] !== []); ?>
        <div class="card shadow-sm mb-3 <?= $differences !== [] ? 'border-warning' : '' ?>">
          <header class="lms-section-head"><div><h3><i class="fe fe-check-square fe-16 mr-2"></i>Order, received, invoiced</h3><p><?= $differences === [] ? 'Every line matches the order and what was received.' : count($differences) . ' line(s) differ: check them before verifying.' ?></p></div></header>
          <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Line</th><th class="text-right">Ordered</th><th class="text-right">Received</th><th class="text-right">Invoiced</th><th class="text-right">Order price</th><th class="text-right">Invoice price</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($match as $m): ?>
                <tr class="<?= $m['problems'] !== [] ? 'table-warning' : '' ?>">
                  <td><?= e($m['description']) ?></td>
                  <td class="text-right"><?= e(rtrim(rtrim(number_format($m['ordered'], 2), '0'), '.')) ?></td>
                  <td class="text-right"><?= e(rtrim(rtrim(number_format($m['received'], 2), '0'), '.')) ?></td>
                  <td class="text-right"><?= e(rtrim(rtrim(number_format($m['invoiced'], 2), '0'), '.')) ?></td>
                  <td class="text-right"><?= e(number_format($m['ordered_price'], 2)) ?></td>
                  <td class="text-right"><?= e(number_format($m['invoiced_price'], 2)) ?></td>
                  <td class="small text-warning"><?= e(implode('; ', $m['problems'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($payload['attachments'])): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-paperclip fe-16 mr-2"></i>Attachments</h3></div></header>
          <div class="card-body d-flex flex-wrap" style="gap:10px">
            <?php foreach ($payload['attachments'] as $file): ?>
              <a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?= e(\Models\Uploads::url($file)) ?>"><i class="fe fe-file fe-12 mr-1"></i><?= e(basename($file)) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($payments !== []): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-dollar-sign fe-16 mr-2"></i>Payments</h3></div></header>
          <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Batch</th><th>MTN batch</th><th>Paid to</th><th class="text-right">Amount</th><th>Status</th><th>Money received</th></tr></thead>
            <tbody><?php foreach ($payments as $payment): ?>
              <tr>
                <td><?= can_view('payment_batches') ? '<a href="' . url(['payment_batches', $payment['batch_id']]) . '">' . e($payment['batch_no']) . '</a>' : e($payment['batch_no']) ?></td>
                <td><?= e($payment['mtn_batch_no'] ?? '—') ?></td>
                <td><?= e($payment['payee_name']) ?></td>
                <td class="text-right lms-num"><?= e(Currency::format((float) $payment['amount'], (string) $payment['currency'])) ?></td>
                <td><span class="badge badge-<?= ['paid' => 'success', 'failed' => 'danger'][$payment['status']] ?? 'light' ?>"><?= e(ucfirst($payment['status'])) ?></span><?= $payment['failure_reason'] ? '<small class="d-block text-danger">' . e($payment['failure_reason']) . '</small>' : '' ?></td>
                <td><?= $payment['confirmed_at'] ? '<span class="text-success"><i class="fe fe-check"></i> ' . e(human_date($payment['confirmed_at'], true)) . '</span>' : ($payment['status'] === 'paid' ? '<span class="text-warning">Not yet confirmed</span>' : '—') ?></td>
              </tr>
            <?php endforeach; ?></tbody>
          </table></div>
        </div>
      <?php endif; ?>

      <?php if ($entries !== []): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-book fe-16 mr-2"></i>In the books</h3></div></header>
          <ul class="list-group list-group-flush">
            <?php foreach ($entries as $entry): ?>
              <li class="list-group-item d-flex justify-content-between small">
                <span><a href="<?= url(['journal', $entry['id']]) ?>"><?= e($entry['entry_no']) ?></a> · <?= e($entry['memo']) ?><?= $entry['status'] === 'reversed' ? ' <span class="badge badge-secondary">Reversed</span>' : '' ?></span>
                <span class="lms-num"><?= e(Currency::format((float) $entry['amount'], (string) $entry['currency'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-xl-4">
      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-check-square fe-16 mr-2"></i>Signatures</h3></div></header>
        <ul class="list-group list-group-flush">
          <?php
          $chainSteps = ['prepared' => ['Prepared by', $request['prepared_name'], $request['prepared_at']]];
          if ($request['chain'] === 'full') {
              $chainSteps['verified'] = ['Verified by (Finance)', $request['verified_name'], $request['verified_at']];
          }
          if (in_array($request['chain'], ['full', 'finance_md'], true)) {
              $chainSteps['authorized'] = ['Authorized by (MD, stamp)', $request['authorized_name'], $request['authorized_at']];
          } elseif ($request['chain'] === 'om_confirm') {
              $chainSteps['authorized'] = ['Confirmed by (OM)', $request['authorized_name'], $request['authorized_at']];
          }
          if ((int) $request['goes_to_payment'] === 1) {
              $chainSteps['paid'] = ['Paid by (Cashier)', $request['paid_name'], $request['paid_at']];
          }
          ?>
          <?php foreach ($chainSteps as [$label, $name, $at]): ?>
            <li class="list-group-item">
              <small class="text-muted d-block"><?= e($label) ?></small>
              <?php if ($name): ?><strong><i class="fe fe-check text-success mr-1"></i><?= e($name) ?></strong> <small class="text-muted"><?= e(human_date($at, true)) ?></small>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-activity fe-16 mr-2"></i>History</h3></div></header>
        <div class="card-body lms-timeline">
          <?php foreach ($events as $event): ?>
            <div class="lms-timeline-item">
              <span class="lms-timeline-dot"></span>
              <div>
                <strong><i class="fe fe-<?= e($stepIcons[$event['step']] ?? 'circle') ?> fe-12 mr-1"></i><?= e($stepWords[$event['step']] ?? $event['step']) ?></strong>
                <?php if ((int) $event['version'] > 1): ?><span class="badge badge-light ml-1">v<?= (int) $event['version'] ?></span><?php endif; ?>
                <small class="d-block text-muted"><?= e($event['full_name'] ?? 'System') ?><?= $event['on_behalf_name'] ? ' for ' . e($event['on_behalf_name']) : '' ?> · <?= e(human_date($event['created_at'], true)) ?></small>
                <?php if (!empty($event['reason'])): ?><div class="small mt-1"><?= e($event['reason']) ?></div><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
