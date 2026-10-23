<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<int, string> $customers */
/** @var int $customerId */
/** @var string $from */
/** @var string $to */
/** @var list<array<string, mixed>> $trips */
use Models\Currency;

$number = static fn (float $value): string => rtrim(rtrim(number_format($value, 3), '0'), '.');
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Commercial · customer invoice (F13)</p>
      <h2 class="lms-page-title"><i class="fe fe-file-text mr-2"></i>Invoice delivered trips</h2>
      <p class="lms-page-sub">Pick the customer and the period. Every trip whose delivery note the OM checked is listed, priced from the customer's rate card, with any shortage the customer deducts. The invoice goes to the MD; once authorized it posts, is emailed to the customer and appears in their portal.</p>
    </div>
    <div class="lms-page-actions">
      <?php if (can_view('rates')): ?><a class="btn btn-outline-secondary" href="<?= url('rates') ?>"><i class="fe fe-tag fe-12 mr-1"></i>Rate cards</a><?php endif; ?>
      <?php if (can_view('shortage_rules')): ?><a class="btn btn-outline-secondary" href="<?= url('shortage_rules') ?>"><i class="fe fe-alert-circle fe-12 mr-1"></i>Shortage tolerance</a><?php endif; ?>
    </div>
  </div>

  <form method="get" action="<?= url('billing') ?>" class="card shadow-sm mb-4">
    <div class="card-body row align-items-end">
      <div class="col-md-5 form-group mb-md-0"><label>Customer</label><select class="form-control" name="customer_id" required><option value="">Choose</option><?php foreach ($customers as $id => $name): ?><option value="<?= (int) $id ?>" <?= $id === $customerId ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3 form-group mb-md-0"><label>Delivered from</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
      <div class="col-md-2 form-group mb-md-0"><label>to</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
      <div class="col-md-2"><button class="btn btn-outline-primary btn-block" type="submit">List trips</button></div>
    </div>
  </form>

  <?php if ($customerId > 0): ?>
    <form method="post" action="<?= url('billing') ?>" class="card shadow-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="customer_id" value="<?= $customerId ?>"><input type="hidden" name="from" value="<?= e($from) ?>"><input type="hidden" name="to" value="<?= e($to) ?>">
      <?php if ($trips === []): ?>
        <div class="card-body text-center text-muted py-5">No delivered trip waiting to be invoiced for this customer in this period.</div>
      <?php else: ?>
        <div class="table-responsive"><table class="table mb-0">
          <thead><tr><th style="width:32px"></th><th>Trip</th><th>Delivery note</th><th class="text-right">Loaded / delivered</th><th>Price</th><th class="text-right">Amount</th><th class="text-right">Shortage deducted</th></tr></thead>
          <tbody>
            <?php foreach ($trips as $trip): ?>
              <?php $price = $trip['price']; $note = $trip['note']; $assessment = $note['assessment'] ?? null; ?>
              <tr class="<?= $price['problem'] ? 'table-warning' : '' ?>">
                <td><input type="checkbox" name="trip_ids[]" value="<?= (int) $trip['id'] ?>" <?= $price['problem'] ? 'disabled' : 'checked' ?>></td>
                <td><a href="<?= url(['trips', $trip['id']]) ?>"><strong><?= e($trip['reference_code']) ?></strong></a><small class="d-block text-muted"><?= e($trip['plate_number'] ?? '') ?> · <?= e($trip['driver_name'] ?? '') ?> · <?= e($trip['route_code'] ?? '') ?></small></td>
                <td><a href="<?= url(['form_requests', $trip['note_id']]) ?>"><?= e($trip['note_no']) ?></a><small class="d-block text-muted"><?= e(human_date($trip['delivered_at'])) ?></small></td>
                <td class="text-right"><?= e($number((float) ($note['loaded'] ?? 0))) ?> / <?= e($number((float) ($note['delivered'] ?? 0))) ?> <?= e((string) ($note['unit'] ?? '')) ?></td>
                <td><?php if ($price['problem']): ?><span class="text-danger small"><?= e($price['problem']) ?></span><?php else: ?><?= e($price['card']['rate_code']) ?><small class="d-block text-muted"><?= e(Currency::format((float) $price['card']['rate_amount'], $price['currency'])) ?> <?= e($price['basis']) ?></small><?php endif; ?></td>
                <td class="text-right"><?= $price['problem'] ? '—' : e(Currency::format($price['amount'], $price['currency'])) ?></td>
                <td class="text-right"><?= is_array($assessment) && (float) ($assessment['value'] ?? 0) > 0 ? '<span class="text-danger">−' . e(Currency::format((float) $assessment['value'], (string) $assessment['currency'])) . '</span>' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if (role_can('billing', 'create')): ?>
          <div class="card-footer d-flex flex-wrap align-items-center justify-content-end" style="gap:8px">
            <input class="form-control" style="max-width:260px" name="lpo" placeholder="Customer order (LPO) number">
            <button class="btn btn-primary" type="submit"><i class="fe fe-file-plus fe-12 mr-1"></i>Make the invoice</button>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
