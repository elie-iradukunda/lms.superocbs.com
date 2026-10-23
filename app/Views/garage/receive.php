<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $order */
/** @var list<array<string, mixed>> $lines */
/** @var list<array<string, mixed>> $receipts */
use Models\Currency;
use Models\RequestForms;

$open = $order['status'] === 'authorized';
$number = static fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Store · goods received</p>
      <h2 class="lms-page-title"><i class="fe fe-download mr-2"></i><?= e(RequestForms::label($order)) ?></h2>
      <p class="lms-page-sub"><?= e($order['title']) ?> · <?= e($order['supplier_name'] ?? 'no supplier') ?> · <?= e(Currency::format((float) $order['total_amount'], (string) $order['currency'])) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('store_issues') ?>">Store</a>
      <a class="btn btn-outline-secondary" href="<?= url(['form_requests', $order['id']]) ?>">The order</a>
    </div>
  </div>

  <?php if (!$open): ?>
    <div class="alert alert-info">This order is <?= e(strtolower(RequestForms::STATUS[$order['status']] ?? $order['status'])) ?>: nothing more can be received against it.</div>
  <?php endif; ?>

  <form method="post" action="<?= url(['goods_receipts', $order['id']]) ?>">
    <?= csrf_field() ?>
    <section class="card shadow-sm lms-section">
      <div class="card-header"><strong>What arrived</strong> <span class="text-muted small">— stock lines go into the store at the order price; work lines are confirmed as done</span></div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Line</th><th class="text-right">Ordered</th><th class="text-right">Received</th><th class="text-right">Price</th><th style="width:140px">Received now</th></tr></thead>
        <tbody>
          <?php foreach ($lines as $line): ?>
            <?php $left = max(0, (float) $line['quantity'] - (float) $line['done_quantity']); ?>
            <tr>
              <td><?= e($line['description']) ?><small class="d-block text-muted"><?= $line['item_id'] ? 'Into the store' : 'Work or service' ?><?= $line['plate_number'] ? ' · ' . e($line['plate_number']) : '' ?></small></td>
              <td class="text-right"><?= e($number((float) $line['quantity'])) ?> <?= e((string) ($line['unit'] ?? '')) ?></td>
              <td class="text-right"><?= e($number((float) $line['done_quantity'])) ?></td>
              <td class="text-right"><?= e(number_format((float) $line['unit_price'], 2)) ?></td>
              <td><?php if ($open && $left > 0): ?><input class="form-control form-control-sm text-right" type="number" step="0.01" min="0" max="<?= e((string) $left) ?>" name="qty[<?= (int) $line['id'] ?>]" value="<?= e((string) $left) ?>"><?php else: ?><span class="text-muted small">done</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($open && role_can('goods_receipts', 'create')): ?>
        <div class="card-body row border-top">
          <div class="col-md-4 form-group"><label>Supplier's delivery note number</label><input class="form-control" name="delivery_ref"></div>
          <div class="col-md-8 form-group"><label>Notes</label><input class="form-control" name="notes" placeholder="Water separator not delivered"></div>
        </div>
        <div class="card-footer text-right"><button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Record received</button></div>
      <?php endif; ?>
    </section>
  </form>

  <?php if ($receipts !== []): ?>
    <section class="card shadow-sm lms-section">
      <div class="card-header"><strong>Received so far</strong></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Receipt</th><th>Date</th><th>Delivery note</th><th class="text-right">Value</th><th>By</th></tr></thead>
        <tbody>
          <?php foreach ($receipts as $receipt): ?>
            <tr>
              <td><?= e($receipt['receipt_no']) ?></td>
              <td><?= e(human_date($receipt['received_on'])) ?></td>
              <td><?= e($receipt['delivery_ref'] ?? '—') ?></td>
              <td class="text-right"><?= e(Currency::format((float) $receipt['total_amount'], (string) $receipt['currency'])) ?></td>
              <td><?= e($receipt['received_name'] ?? '') ?><?= $receipt['entry_id'] && can_view('journal') ? ' · <a href="' . e(url(['journal', $receipt['entry_id']])) . '">entry</a>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
