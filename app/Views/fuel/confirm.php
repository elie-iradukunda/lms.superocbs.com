<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php /** @var array<string, mixed> $order */ ?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Fuel order <?= e($order['order_code']) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-droplet mr-2"></i>Confirm litres given</h2>
      <p class="lms-page-sub"><?= e($order['plate_number']) ?> at <?= e($order['fuel_point'] ?: ($order['supplier_name'] ?? 'the station')) ?><?= $order['trip_code'] ? ' · trip ' . e($order['trip_code']) : '' ?> · ordered <strong><?= e(rtrim(rtrim((string) $order['litres_ordered'], '0'), '.')) ?> L</strong></p>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url(['fuel_orders', $order['id']]) ?>">Cancel</a></div>
  </div>
  <form method="post" action="<?= url(['fuel_orders', $order['id'], 'confirm']) ?>" class="card shadow-sm">
    <?= csrf_field() ?>
    <div class="card-body row">
      <div class="col-md-4 form-group"><label>Litres actually given <span class="lms-required">*</span></label><div class="input-group"><input class="form-control" type="number" min="0" step="0.01" name="litres" required value="<?= e((string) ($order['litres_delivered'] ?? $order['litres_ordered'])) ?>"><div class="input-group-append"><span class="input-group-text">L</span></div></div></div>
      <div class="col-md-4 form-group"><label>Price per litre (from the invoice, if known)</label><input class="form-control" type="number" min="0" step="0.0001" name="unit_price" value="<?= e((string) ($order['unit_price'] ?? '')) ?>"></div>
      <div class="col-md-4 form-group"><label>Note</label><input class="form-control" name="note" placeholder="Pump ticket no."></div>
    </div>
    <div class="card-footer text-right"><button class="btn btn-primary" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Confirm</button></div>
  </form>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
