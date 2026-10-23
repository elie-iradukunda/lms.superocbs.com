<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var string $period */
/** @var list<array<string, mixed>> $rows */
/** @var ?array $run */
use Models\Currency;

$closed = $run !== null && $run['status'] === 'closed';
$totals = [];
foreach ($rows as $row) {
    $totals[$row['currency']]['salary'] = ($totals[$row['currency']]['salary'] ?? 0) + (float) $row['salary'];
    foreach ($row['deducted'] as $code => $amount) {
        $totals[$code]['deducted'] = ($totals[$code]['deducted'] ?? 0) + (float) $amount;
    }
}
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">HR · <?= e(current_company_name()) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-users mr-2"></i>Payroll list — <?= e(date('F Y', (int) strtotime($period . '-01'))) ?> <?php if ($closed): ?><span class="badge badge-success align-middle">Closed</span><?php endif; ?></h2>
      <p class="lms-page-sub">Every driver employed in the month, with the salary and the deductions authorized since the last list (shortages). Export it for the payroll; close the month when it is paid, so the deductions are marked as taken.</p>
    </div>
    <div class="lms-page-actions">
      <form method="get" action="<?= url('payroll') ?>" class="d-flex" style="gap:6px"><input class="form-control" type="month" name="period" value="<?= e($period) ?>"><button class="btn btn-outline-secondary" type="submit">Show</button></form>
      <a class="btn btn-outline-secondary" href="<?= url(['payroll', 'export'], ['period' => $period]) ?>"><i class="fe fe-download fe-12 mr-1"></i>CSV</a>
    </div>
  </div>

  <div class="card shadow-sm">
    <?php if ($rows === []): ?>
      <div class="card-body text-center text-muted py-5">No driver employed in this month.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Driver</th><th>Paid by</th><th class="text-right">Salary</th><th class="text-right">Deductions</th><th class="text-right">Net</th><th>Details</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><strong><?= e($row['full_name']) ?></strong><small class="d-block text-muted"><?= e($row['national_id'] ?: $row['passport_number'] ?: '') ?><?= $row['status'] === 'left' ? ' · left ' . e(human_date($row['left_on'])) : '' ?></small></td>
              <td><?= e(strtoupper((string) $row['payment_method'])) ?><small class="d-block text-muted"><?= e((string) ($row['momo_number'] ?? '')) ?></small></td>
              <td class="text-right"><?= (float) $row['salary'] > 0 ? e(Currency::format((float) $row['salary'], $row['currency'])) : '<span class="text-muted">not set</span>' ?></td>
              <td class="text-right"><?= $row['deducted'] === [] ? '—' : e(implode(' · ', array_map(static fn (string $c, float $a): string => Currency::format($a, $c), array_keys($row['deducted']), $row['deducted']))) ?></td>
              <td class="text-right"><strong><?= e(Currency::format((float) $row['net'], $row['currency'])) ?></strong><?= $row['other_currency'] ? '<small class="d-block text-warning">another currency to settle</small>' : '' ?></td>
              <td class="small"><?php foreach ($row['deductions'] as $item): ?><div><?= $item['request_id'] ? '<a href="' . e(url(['form_requests', $item['request_id']])) . '">' . e($item['description']) . '</a>' : e($item['description']) ?></div><?php endforeach; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <?php foreach ($totals as $code => $total): ?>
            <tr class="font-weight-bold"><td colspan="2">Total <?= e($code) ?></td><td class="text-right"><?= e(Currency::format((float) ($total['salary'] ?? 0), $code)) ?></td><td class="text-right"><?= e(Currency::format((float) ($total['deducted'] ?? 0), $code)) ?></td><td class="text-right"><?= e(Currency::format((float) ($total['salary'] ?? 0) - (float) ($total['deducted'] ?? 0), $code)) ?></td><td></td></tr>
          <?php endforeach; ?>
        </tfoot>
      </table></div>
      <?php if (!$closed && role_can('payroll', 'edit')): ?>
        <form method="post" action="<?= url(['payroll', 'close']) ?>" class="card-footer text-right">
          <?= csrf_field() ?><input type="hidden" name="period" value="<?= e($period) ?>">
          <button class="btn btn-primary" type="submit" onclick="return confirm('Close <?= e($period) ?>? The deductions on this list are marked as taken.')"><i class="fe fe-lock fe-12 mr-1"></i>Close the month</button>
        </form>
      <?php elseif ($closed): ?>
        <div class="card-footer small text-muted">Closed <?= e(human_date($run['closed_at'], true)) ?>.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
