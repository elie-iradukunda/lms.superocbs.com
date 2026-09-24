<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$icons = [
    'vehicle_utilization' => 'truck',
    'fuel_consumption' => 'droplet',
    'delivery_performance' => 'check-square',
    'maintenance_cost' => 'tool',
    'driver_performance' => 'user',
    'inventory_movement' => 'package',
    'trip_profitability' => 'trending-up',
    'expense_summary' => 'credit-card',
];
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Insights</p>
      <h2 class="lms-page-title"><i class="fe fe-bar-chart-2 mr-2"></i>Reports</h2>
      <p class="lms-page-sub">Each report runs against live operational data. Pick a period, read it on screen, export it as CSV.</p>
    </div>
    <?php if ($canEditCatalogue): ?>
      <div class="lms-page-actions">
        <a class="btn btn-outline-secondary" href="<?= url(['reports', 'catalogue']) ?>"><i class="fe fe-list fe-12 mr-1"></i>Saved report catalogue</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($available === []): ?>
    <div class="alert alert-info">No report is available for the <?= e(role_label()) ?> role.</div>
  <?php endif; ?>

  <div class="row">
    <?php foreach ($available as $key => $label): ?>
      <div class="col-md-6 col-xl-4 mb-4">
        <a class="card shadow-sm lms-report-card h-100" href="<?= url(['reports', 'view', $key]) ?>">
          <div class="card-body">
            <span class="lms-report-icon"><i class="fe fe-<?= e($icons[$key] ?? 'bar-chart-2') ?> fe-24"></i></span>
            <h5 class="mt-3 mb-2"><?= e($label) ?></h5>
            <p class="small text-muted mb-3"><?= e(\Models\ReportData::description($key)) ?></p>
            <span class="lms-report-open">Run report <i class="fe fe-arrow-right fe-12"></i></span>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($catalogue !== []): ?>
    <div class="card shadow-sm">
      <header class="lms-section-head">
        <div><h3><i class="fe fe-bookmark fe-16 mr-2"></i>Saved report definitions</h3><p>Who owns each report, how often it runs and when it last ran.</p></div>
        <?php if ($canEditCatalogue): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url(['reports', 'catalogue']) ?>">Manage</a><?php endif; ?>
      </header>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover lms-table mb-0">
            <thead><tr><th>Report</th><th>What it answers</th><th>Period</th><th>Owner</th><th>Last run</th><th class="text-right">Open</th></tr></thead>
            <tbody>
              <?php foreach ($catalogue as $entry): ?>
                <tr>
                  <td><strong><?= e($entry['report_name']) ?></strong></td>
                  <td class="small text-muted"><?= e($entry['description'] ?? '') ?></td>
                  <td><?= e($entry['period_label']) ?></td>
                  <td><?= e($entry['owner_name']) ?></td>
                  <td><?= $entry['last_generated_at'] ? e(date('d M Y H:i', (int) strtotime((string) $entry['last_generated_at']))) : '<span class="text-muted">Never</span>' ?></td>
                  <td class="text-right">
                    <?php if (!empty($entry['report_key']) && isset($available[$entry['report_key']])): ?>
                      <a class="btn btn-sm btn-link" href="<?= url(['reports', 'view', $entry['report_key']]) ?>">Run</a>
                    <?php else: ?>
                      <span class="text-muted small">No live query linked</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
