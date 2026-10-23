<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var string $kind */
/** @var array<string, string> $meta */
/** @var string $from */
/** @var string $to */
/** @var array<int, array{name: string, rows: list<array<string, mixed>>, totals: array<string, float>}> $bySupplier */
/** @var list<array<string, mixed>> $tyreLife */
use Models\Currency;
use Models\RequestFormConfig;
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker"><?= e($meta['form']) ?> · built from the <?= e($kind) ?> log</p>
      <h2 class="lms-page-title"><i class="fe fe-<?= $kind === 'tyre' ? 'disc' : 'anchor' ?> mr-2"></i><?= e($meta['label']) ?> to pay</h2>
      <p class="lms-page-sub">Choose the period. Each <?= e(strtolower($meta['payee'])) ?> gets one payment request with every unpaid line; a line already on a request is never paid twice.</p>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url($meta['table']) ?>"><?= e($kind === 'tyre' ? 'Tyre log' : 'Crane log') ?></a></div>
  </div>

  <form method="get" action="<?= url(['service_logs', $kind]) ?>" class="card shadow-sm mb-4">
    <div class="card-body row align-items-end">
      <div class="col-md-4 form-group mb-md-0"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
      <div class="col-md-4 form-group mb-md-0"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
      <div class="col-md-4"><button class="btn btn-outline-primary btn-block" type="submit">Show</button></div>
    </div>
  </form>

  <?php if ($bySupplier === []): ?>
    <div class="card shadow-sm mb-4"><div class="card-body text-center text-muted py-5">Nothing unpaid in this period.</div></div>
  <?php endif; ?>
  <?php foreach ($bySupplier as $supplierId => $group): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px">
        <span><strong><?= e($group['name']) ?></strong> · <?= count($group['rows']) ?> line(s) · <?= e(implode(' · ', array_map(static fn (string $c, float $t): string => Currency::format($t, $c), array_keys($group['totals']), $group['totals']))) ?></span>
        <?php if ($supplierId > 0): ?>
          <form method="post" action="<?= url(['service_logs', $kind]) ?>"><?= csrf_field() ?>
            <input type="hidden" name="supplier_id" value="<?= (int) $supplierId ?>"><input type="hidden" name="from" value="<?= e($from) ?>"><input type="hidden" name="to" value="<?= e($to) ?>">
            <button class="btn btn-sm btn-primary" type="submit">Build <?= e($meta['form']) ?></button>
          </form>
        <?php else: ?>
          <span class="text-danger small">Put the <?= e(strtolower($meta['payee'])) ?> on these lines first.</span>
        <?php endif; ?>
      </div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <?php if ($kind === 'tyre'): ?>
          <thead><tr><th>Date</th><th>Truck</th><th>Position</th><th>Km</th><th class="text-right">Tyres</th><th class="text-right">Amount</th></tr></thead>
          <tbody><?php foreach ($group['rows'] as $row): ?><tr><td><?= e(human_date($row['changed_on'])) ?></td><td><?= e(trim(($row['plate_number'] ?? '') . ' ' . ($row['trailer_number'] ?? ''))) ?></td><td><?= e(RequestFormConfig::POSITIONS[$row['position']] ?? $row['position']) ?></td><td><?= $row['odometer_km'] ? e(number_format((int) $row['odometer_km'])) : '—' ?></td><td class="text-right"><?= e(rtrim(rtrim(number_format((float) $row['quantity'], 2), '0'), '.')) ?></td><td class="text-right"><?= e(Currency::format((float) $row['quantity'] * (float) $row['unit_price'], (string) $row['currency'])) ?></td></tr><?php endforeach; ?></tbody>
        <?php else: ?>
          <thead><tr><th>Date</th><th>Truck</th><th>Container</th><th>Lift</th><th>Size</th><th class="text-right">Fee</th></tr></thead>
          <tbody><?php foreach ($group['rows'] as $row): ?><tr><td><?= e(human_date($row['lift_date'])) ?></td><td><?= e($row['plate_number'] ?? '—') ?></td><td><?= e($row['container_no']) ?></td><td><?= e(ucfirst((string) $row['operation'])) ?></td><td><?= e($row['container_size']) ?> ft</td><td class="text-right"><?= e(Currency::format((float) $row['fee'], (string) $row['currency'])) ?></td></tr><?php endforeach; ?></tbody>
        <?php endif; ?>
      </table></div>
    </div>
  <?php endforeach; ?>

  <?php if ($kind === 'tyre' && $tyreLife !== []): ?>
    <div class="card shadow-sm mt-4">
      <div class="card-header"><strong>Tyre life per truck</strong> <span class="text-muted small">— km run before the next change in the same position</span></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Truck</th><th>Position</th><th>Changed</th><th>Km</th><th class="text-right">Ran</th><th>Why</th><th>Brand</th></tr></thead>
        <tbody><?php foreach (array_reverse($tyreLife) as $row): ?><tr><td><?= e($row['plate_number']) ?></td><td><?= e($row['position']) ?></td><td><?= e(human_date($row['changed_on'])) ?></td><td><?= $row['odometer_km'] !== null ? e(number_format($row['odometer_km'])) : '—' ?></td><td class="text-right"><?= $row['ran_km'] !== null ? e(number_format($row['ran_km'])) . ' km' : '—' ?></td><td><?= e($row['reason']) ?></td><td><?= e($row['brand']) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
