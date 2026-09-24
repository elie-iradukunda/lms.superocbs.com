<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$format = static function (mixed $value, array $spec): string {
    if ($value === null || $value === '') {
        return '<span class="text-muted">—</span>';
    }

    return match ($spec['type']) {
        'code' => '<strong>' . e($value) . '</strong>',
        'badge' => '<span class="badge badge-' . e(\Models\Schema::tone((string) $value)) . '">' . e(\Models\Schema::label((string) $value)) . '</span>',
        'label' => e(\Models\Schema::label((string) $value)),
        'money' => '<span class="lms-num">' . e(money((float) $value)) . '</span>',
        'number' => '<span class="lms-num">' . e(number_format((float) $value)) . '</span>',
        'decimal' => '<span class="lms-num">' . e(rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.')) . '</span>',
        'percent' => '<span class="lms-num lms-pct" data-value="' . e($value) . '">' . e($value) . '%</span>',
        'datetime' => e(date('d M Y H:i', (int) strtotime((string) $value))),
        'date' => e(date('d M Y', (int) strtotime((string) $value))),
        default => e($value),
    };
};
?>
<div class="container-fluid lms-list-page">

  <div class="lms-page-head">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb lms-crumbs">
          <li class="breadcrumb-item"><a href="<?= url('reports') ?>">Reports</a></li>
          <li class="breadcrumb-item active"><?= e($report['label']) ?></li>
        </ol>
      </nav>
      <h2 class="lms-page-title"><i class="fe fe-bar-chart-2 mr-2"></i><?= e($report['label']) ?></h2>
      <p class="lms-page-sub"><?= e($report['description']) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url(['reports', 'export', $report['key']], ['from' => $report['from'], 'to' => $report['to']]) ?>"><i class="fe fe-download fe-12 mr-1"></i>Export CSV</a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="get" action="<?= url(['reports', 'view', $report['key']]) ?>" class="lms-filter-bar">
        <div class="form-group mb-0 mr-2">
          <label class="small text-muted mb-1 d-block" for="from">From</label>
          <input type="date" id="from" name="from" class="form-control" value="<?= e($report['from']) ?>">
        </div>
        <div class="form-group mb-0 mr-2">
          <label class="small text-muted mb-1 d-block" for="to">To</label>
          <input type="date" id="to" name="to" class="form-control" value="<?= e($report['to']) ?>">
        </div>
        <div class="form-group mb-0 mr-2">
          <label class="small text-muted mb-1 d-block" for="jump">Report</label>
          <select id="jump" class="form-control" onchange="if(this.value){window.location=this.value;}">
            <?php foreach ($available as $key => $label): ?>
              <option value="<?= e(url(['reports', 'view', $key], ['from' => $report['from'], 'to' => $report['to']])) ?>" <?= $key === $report['key'] ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary align-self-end" type="submit">Run</button>
        <?php foreach ([['This month', date('Y-m-01'), date('Y-m-t')], ['Last 3 months', date('Y-m-01', strtotime('-2 months')), date('Y-m-d')], ['This year', date('Y-01-01'), date('Y-m-d')]] as [$label, $f, $t]): ?>
          <a class="btn btn-link btn-sm align-self-end" href="<?= url(['reports', 'view', $report['key']], ['from' => $f, 'to' => $t]) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </form>

      <?php if ($report['totals'] !== []): ?>
        <div class="lms-highlights mt-3">
          <?php foreach ($report['totals'] as $label => $value): ?>
            <div class="lms-highlight">
              <span class="lms-highlight-icon"><i class="fe fe-hash fe-16"></i></span>
              <div>
                <small><?= e(ucfirst(str_replace('_', ' ', (string) $label))) ?></small>
                <strong><?= e(is_float($value) || str_contains((string) $label, 'cost') || str_contains((string) $label, 'revenue') || str_contains((string) $label, 'margin') || str_contains((string) $label, 'total') || str_contains((string) $label, 'approved') ? money((float) $value) : number_format((float) $value)) ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p class="lms-result-count mt-3">
        Period <strong><?= e(date('d M Y', (int) strtotime($report['from']))) ?></strong> to <strong><?= e(date('d M Y', (int) strtotime($report['to']))) ?></strong>
        &middot; <?= count($report['rows']) ?> row<?= count($report['rows']) === 1 ? '' : 's' ?>
      </p>

      <div class="table-responsive">
        <table class="table table-hover lms-table">
          <thead>
            <tr>
              <?php foreach ($report['columns'] as $spec): ?>
                <th class="<?= in_array($spec['type'], ['money', 'number', 'decimal', 'percent'], true) ? 'text-right' : '' ?>"><?= e($spec['label']) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($report['rows'] === []): ?>
              <tr><td colspan="<?= count($report['columns']) ?>" class="lms-empty">
                <i class="fe fe-inbox fe-32"></i>
                <p class="mb-1"><strong>No data in this period</strong></p>
                <p class="text-muted small mb-0">Try a wider date range, or check that the underlying records exist.</p>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($report['rows'] as $row): ?>
              <tr>
                <?php foreach ($report['columns'] as $column => $spec): ?>
                  <td class="<?= in_array($spec['type'], ['money', 'number', 'decimal', 'percent'], true) ? 'text-right' : '' ?>"><?= $format($row[$column] ?? null, $spec) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($report['key'] === 'delivery_performance' || $report['key'] === 'driver_performance'): ?>
        <p class="small text-muted mb-0"><i class="fe fe-info fe-12 mr-1"></i>On-time is measured against the trip's planned arrival plus a <?= e(\Models\Settings::get('on_time_grace_minutes', '30')) ?> minute grace period. Deliveries on trips with no planned arrival are excluded from that rate.</p>
      <?php endif; ?>
      <?php if ($report['key'] === 'fuel_consumption'): ?>
        <p class="small text-muted mb-0"><i class="fe fe-info fe-12 mr-1"></i>Litres per 100 km and cost per kilometre will appear here once route distance is added.</p>
      <?php endif; ?>
      <?php if ($report['key'] === 'trip_profitability'): ?>
        <p class="small text-muted mb-0"><i class="fe fe-info fe-12 mr-1"></i>Revenue is the invoice value net of VAT, since the tax belongs to the revenue authority; cost is approved expenses plus fuel booked against the trip.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
