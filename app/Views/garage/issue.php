<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $note */
/** @var list<array<string, mixed>> $lines */
/** @var list<array<string, mixed>> $events */
use Models\RequestForms;

$open = $note['status'] === 'sent';
$number = static fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
$short = array_filter($lines, static fn (array $l): bool => $l['remaining'] > 0 && (float) ($l['stock']['on_hand'] ?? 0) < $l['remaining']);
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Store · stores issue note (F4)</p>
      <h2 class="lms-page-title"><i class="fe fe-package mr-2"></i><?= e(RequestForms::label($note)) ?> <span class="badge badge-<?= $open ? 'warning' : 'success' ?> align-middle"><?= e($open ? 'To issue' : (RequestForms::STATUS[$note['status']] ?? $note['status'])) ?></span></h2>
      <p class="lms-page-sub"><?= e($note['title']) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('store_issues') ?>">Store</a>
      <a class="btn btn-outline-secondary" href="<?= url(['form_requests', $note['id'], 'pdf']) ?>"><i class="fe fe-file-text fe-12 mr-1"></i>PDF</a>
      <?php if ($note['job_card_id'] && can_view('garage')): ?><a class="btn btn-outline-secondary" href="<?= url(['garage', $note['job_card_id']]) ?>"><i class="fe fe-tool fe-12 mr-1"></i>Job card</a><?php endif; ?>
    </div>
  </div>

  <?php if ($open && $short !== []): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap" style="gap:8px">
      <span><i class="fe fe-alert-triangle mr-1"></i><?= count($short) ?> line(s) are not fully in stock.</span>
      <?php if (can_create('form_requests')): ?>
        <form method="post" action="<?= url(['store_issues', $note['id'], 'order']) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-warning" type="submit">Order what is missing (F5)</button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url(['store_issues', $note['id'], 'issue']) ?>">
    <?= csrf_field() ?>
    <section class="card shadow-sm lms-section">
      <div class="card-header"><strong>Truck <?= e($note['plate_number'] ?? '—') ?></strong> <span class="text-muted small">— stock goes down and each part's value is charged to the truck</span></div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Item</th><th class="text-right">Asked</th><th class="text-right">Issued</th><th class="text-right">In store</th><th style="width:140px">Issue now</th></tr></thead>
        <tbody>
          <?php foreach ($lines as $line): ?>
            <?php $onHand = (float) ($line['stock']['on_hand'] ?? 0); ?>
            <tr>
              <td><?= e($line['description']) ?><?php if ($line['stock']): ?><small class="d-block text-muted"><?= e($line['stock']['sku']) ?> · <?= e($line['stock']['warehouse_name'] ?? '') ?></small><?php else: ?><small class="d-block text-danger">Not linked to a store item</small><?php endif; ?></td>
              <td class="text-right"><?= e($number((float) $line['quantity'])) ?> <?= e((string) ($line['unit'] ?? '')) ?></td>
              <td class="text-right"><?= e($number((float) $line['done_quantity'])) ?></td>
              <td class="text-right <?= $onHand < $line['remaining'] ? 'text-danger' : '' ?>"><?= $line['stock'] ? e($number($onHand)) : '—' ?></td>
              <td>
                <?php if ($open && $line['remaining'] > 0 && $line['stock']): ?>
                  <input class="form-control form-control-sm text-right" type="number" step="0.01" min="0" max="<?= e((string) min($line['remaining'], $onHand)) ?>" name="qty[<?= (int) $line['id'] ?>]" value="<?= e((string) min($line['remaining'], max(0, $onHand))) ?>">
                <?php else: ?>
                  <span class="text-muted small"><?= $line['remaining'] > 0 ? '—' : 'done' ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($open && role_can('store_issues', 'edit')): ?>
        <div class="card-footer text-right"><button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Issue</button></div>
      <?php endif; ?>
    </section>
  </form>

  <?php if ($open && role_can('store_issues', 'edit')): ?>
    <form method="post" action="<?= url(['store_issues', $note['id'], 'withdraw']) ?>" class="card shadow-sm lms-section">
      <?= csrf_field() ?>
      <div class="card-body d-flex flex-wrap" style="gap:8px">
        <input class="form-control" style="flex:1;min-width:240px" name="reason" required placeholder="Why the rest is not needed (e.g. part fitted from the old truck)">
        <button class="btn btn-outline-danger" type="submit">Withdraw what is left</button>
      </div>
    </form>
  <?php endif; ?>

  <section class="card shadow-sm lms-section">
    <div class="card-header"><strong>History</strong></div>
    <ul class="list-group list-group-flush small">
      <?php foreach ($events as $event): ?>
        <li class="list-group-item"><strong><?= e(ucfirst(str_replace('_', ' ', (string) $event['step']))) ?></strong> · <?= e($event['full_name'] ?? '') ?> · <?= e(human_date($event['created_at'], true)) ?><?= $event['reason'] ? ' — ' . e($event['reason']) : '' ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
