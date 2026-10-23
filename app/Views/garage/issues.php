<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var list<array<string, mixed>> $notes */
/** @var list<array<string, mixed>> $toReceive */
use Models\Currency;
use Models\RequestForms;
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Store · <?= e(current_company_name()) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-package mr-2"></i>Store</h2>
      <p class="lms-page-sub">Parts leave the store only against an issue note, linked to a truck and its job card. Deliveries come in against an authorized purchase order.</p>
    </div>
    <div class="lms-page-actions">
      <?php if (can_view('warehouse')): ?><a class="btn btn-outline-secondary" href="<?= url('warehouse') ?>"><i class="fe fe-list fe-12 mr-1"></i>Items and stock</a><?php endif; ?>
      <?php if (can_view('stock_counts')): ?><a class="btn btn-outline-secondary" href="<?= url('stock_counts') ?>"><i class="fe fe-check-square fe-12 mr-1"></i>Stock counts</a><?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Issue notes waiting (<?= count($notes) ?>)</strong></div>
    <?php if ($notes === []): ?>
      <div class="card-body text-center text-muted py-4">Nothing to issue.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Issue note</th><th>Truck</th><th>Job card</th><th>Waiting</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($notes as $note): ?>
            <tr>
              <td><a href="<?= url(['store_issues', $note['id']]) ?>"><strong><?= e(RequestForms::label($note)) ?></strong></a><small class="d-block text-muted"><?= e($note['title']) ?></small></td>
              <td><?= e($note['plate_number'] ?? '—') ?></td>
              <td><?php if ($note['job_card_id']): ?><a href="<?= url(['garage', $note['job_card_id']]) ?>"><?= e($note['work_order_code'] ?? '') ?></a><?php else: ?>—<?php endif; ?></td>
              <td><?= e(time_ago($note['waiting_since'] ?? $note['created_at'])) ?></td>
              <td class="text-right"><a class="btn btn-sm btn-primary" href="<?= url(['store_issues', $note['id']]) ?>">Issue</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <?php if (can_view('goods_receipts')): ?>
    <div class="card shadow-sm">
      <div class="card-header"><strong>Purchase orders to receive (<?= count($toReceive) ?>)</strong></div>
      <?php if ($toReceive === []): ?>
        <div class="card-body text-center text-muted py-4">No authorized order is waiting for delivery.</div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-hover mb-0">
          <thead><tr><th>Order</th><th>Supplier</th><th>Truck</th><th class="text-right">Value</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($toReceive as $order): ?>
              <tr>
                <td><a href="<?= url(['form_requests', $order['id']]) ?>"><strong><?= e(RequestForms::label($order)) ?></strong></a><small class="d-block text-muted"><?= e($order['title']) ?></small></td>
                <td><?= e($order['supplier_name'] ?? '—') ?></td>
                <td><?= e($order['plate_number'] ?? '—') ?></td>
                <td class="text-right"><?= e(Currency::format((float) $order['total_amount'], (string) $order['currency'])) ?></td>
                <td class="text-right"><a class="btn btn-sm btn-primary" href="<?= url(['goods_receipts', $order['id']]) ?>">Receive</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
