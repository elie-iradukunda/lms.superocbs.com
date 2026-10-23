<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var string $name */
/** @var list<array<string, mixed>> $orders */
/** @var list<array<string, mixed>> $invoices */
/** @var list<array<string, mixed>> $payments */
use Models\Currency;
use Models\RequestForms;

$number = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Supplier portal</p>
      <h2 class="lms-page-title"><i class="fe fe-shopping-cart mr-2"></i><?= e($name) ?></h2>
      <p class="lms-page-sub">The orders we sent you: accept or decline each one, and send your invoice against it. Payments we made to you are listed at the bottom.</p>
    </div>
  </div>

  <?php if ($orders === []): ?>
    <div class="card shadow-sm mb-4"><div class="card-body text-center text-muted py-5">No order yet.</div></div>
  <?php endif; ?>
  <?php foreach ($orders as $order): ?>
    <?php $answer = $order['payload']['supplier_answer'] ?? null; $open = $order['status'] === 'authorized' && $answer === null; $canInvoice = in_array($order['status'], ['authorized', 'closed'], true) && ($answer['answer'] ?? 'accept') === 'accept' && (int) $order['invoiced'] === 0; ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between flex-wrap" style="gap:8px">
        <span><strong><?= e(RequestForms::label($order)) ?></strong> · <?= e($order['company_name'] ?? '') ?> · <?= e(human_date($order['authorized_at'] ?? $order['created_at'])) ?><?= $order['plate_number'] ? ' · ' . e($order['plate_number']) : '' ?>
          <span class="badge badge-<?= $order['status'] === 'cancelled' ? 'secondary' : ($answer === null ? 'warning' : 'success') ?>"><?= e($order['status'] === 'cancelled' ? 'Cancelled' : ($answer === null ? 'To answer' : ($answer['answer'] === 'accept' ? 'Accepted' : 'Declined'))) ?></span>
          <?= (int) $order['invoiced'] > 0 ? '<span class="badge badge-info">Invoiced</span>' : '' ?></span>
        <span><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= url(['portal', 'documents', $order['id'], 'pdf']) ?>"><i class="fe fe-file-text fe-12 mr-1"></i>Order PDF</a></span>
      </div>
      <form method="post" action="<?= url(['portal', 'orders', $order['id'], 'invoice']) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="table-responsive"><table class="table table-sm mb-0">
          <thead><tr><th>Item</th><th class="text-right">Ordered</th><th class="text-right">Price</th><th class="text-right">Received</th><?php if ($canInvoice): ?><th style="width:110px">Invoice qty</th><th style="width:130px">Invoice price</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($order['lines'] as $line): ?>
              <tr>
                <td><?= e($line['description']) ?></td>
                <td class="text-right"><?= e($number($line['quantity'])) ?> <?= e((string) ($line['unit'] ?? '')) ?></td>
                <td class="text-right"><?= e(Currency::format((float) $line['unit_price'], (string) $order['currency'])) ?></td>
                <td class="text-right"><?= e($number($line['done_quantity'])) ?></td>
                <?php if ($canInvoice): ?>
                  <td><input class="form-control form-control-sm text-right" type="number" step="0.01" min="0" name="qty[<?= (int) $line['id'] ?>]" value="<?= e($number((float) $line['done_quantity'] ?: (float) $line['quantity'])) ?>"></td>
                  <td><input class="form-control form-control-sm text-right" type="number" step="0.01" min="0" name="price[<?= (int) $line['id'] ?>]" value="<?= e((string) $line['unit_price']) ?>"></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if ($canInvoice): ?>
          <div class="card-body border-top row">
            <div class="col-md-3 form-group"><label>Your invoice number</label><input class="form-control" name="invoice_no" required></div>
            <div class="col-md-3 form-group"><label>Invoice date</label><input class="form-control" type="date" name="invoice_date" value="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-md-4 form-group"><label>Scan of the invoice</label><input class="form-control-file" type="file" name="scan" accept=".pdf,image/*" required></div>
            <div class="col-md-2 form-group d-flex align-items-end"><button class="btn btn-primary btn-block" type="submit">Send invoice</button></div>
          </div>
        <?php endif; ?>
      </form>
      <?php if ($open): ?>
        <div class="card-footer d-flex flex-wrap" style="gap:8px">
          <form method="post" action="<?= url(['portal', 'orders', $order['id'], 'accept']) ?>"><?= csrf_field() ?><button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Accept the order</button></form>
          <form method="post" action="<?= url(['portal', 'orders', $order['id'], 'decline']) ?>" class="d-flex" style="gap:6px;flex:1;min-width:260px"><?= csrf_field() ?>
            <input class="form-control" name="reason" required placeholder="Why you cannot supply it">
            <button class="btn btn-outline-danger" type="submit">Decline</button>
          </form>
        </div>
      <?php elseif (is_array($answer) && $answer['answer'] === 'decline'): ?>
        <div class="card-footer small text-muted">Declined: <?= e((string) $answer['reason']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>My invoices</strong></div>
    <?php if ($invoices === []): ?>
      <div class="card-body text-center text-muted py-4">No invoice sent yet.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Our reference</th><th>Your invoice</th><th>Order</th><th class="text-right">Amount</th><th class="text-right">Paid</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($invoices as $invoice): ?>
            <?php $data = json_decode((string) ($invoice['payload'] ?? ''), true) ?: []; ?>
            <tr>
              <td><?= e($invoice['request_no'] ?? '') ?></td>
              <td><?= e((string) ($data['invoice_no'] ?? '')) ?></td>
              <td><?= e($invoice['order_no'] ?? '') ?></td>
              <td class="text-right"><?= e(Currency::format((float) $invoice['total_amount'], (string) $invoice['currency'])) ?></td>
              <td class="text-right"><?= e(Currency::format((float) $invoice['paid_amount'], (string) $invoice['currency'])) ?></td>
              <td><span class="badge badge-light"><?= e(RequestForms::STATUS[$invoice['status']] ?? $invoice['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-header"><strong>Payments to me</strong></div>
    <?php if ($payments === []): ?>
      <div class="card-body text-center text-muted py-4">No payment yet.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Paid</th><th>For</th><th>How</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><?= e(human_date($payment['paid_at'], true)) ?></td>
              <td><?= e($payment['form_code'] . ' ' . $payment['request_no']) ?></td>
              <td><?= e(strtoupper((string) $payment['method'])) ?> <?= e($payment['mtn_batch_no'] ?? $payment['batch_no']) ?></td>
              <td class="text-right"><?= e(Currency::format((float) $payment['amount'], (string) $payment['currency'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
