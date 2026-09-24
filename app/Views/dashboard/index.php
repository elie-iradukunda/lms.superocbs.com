<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid">
  <?php if ($denied !== ''): ?>
    <div class="alert alert-warning">
      <i class="fe fe-lock mr-2"></i>
      <strong><?= e(ucfirst(str_replace('_', ' ', $denied))) ?></strong> is not available for the <?= e(role_label()) ?> role.
      <?php if (can_view('users')): ?><a class="alert-link ml-2" href="<?= url('permissions', ['role' => current_role()]) ?>">Review role permissions</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="row justify-content-center"><div class="col-12"><div class="row align-items-center mb-2"><div class="col"><h2 class="h5 page-title"><?= htmlspecialchars($title) ?></h2><p class="small text-muted mb-0"><?= htmlspecialchars($roleSubtitle) ?></p></div></div></div>
  <div class="col-12"><div class="row">
    <?php foreach ($metrics as $index => $metric): ?><div class="col-md-6 col-xl-3 mb-4"><div class="card shadow border-0 <?= $index === 2 ? 'bg-primary text-white' : '' ?>"><div class="card-body"><div class="row align-items-center"><div class="col-3 text-center"><span class="circle circle-sm <?= $index === 2 ? 'bg-primary-light' : 'bg-primary' ?>"><i class="fe fe-<?= htmlspecialchars($metric['icon']) ?> fe-16 text-white mb-0"></i></span></div><div class="col pr-0"><p class="small <?= $index === 2 ? 'text-white' : 'text-muted' ?> mb-0"><?= htmlspecialchars($metric['label']) ?></p><span class="h3 mb-0 <?= $index === 2 ? 'text-white' : '' ?>"><?= htmlspecialchars($metric['value']) ?></span><span class="small <?= $index === 2 ? 'text-white' : 'text-success' ?> ml-2"><?= htmlspecialchars($metric['trend']) ?></span></div></div></div></div></div><?php endforeach; ?>
  </div></div>

  <?php if ($charts !== []): ?>
  <div class="col-12">
    <div class="lms-charts">
      <?php foreach ($charts as $chart): ?>
        <section class="lms-chart lms-chart-<?= htmlspecialchars($chart['type']) ?>" style="--span:<?= (int) $chart['span'] ?>" data-chart-id="<?= htmlspecialchars($chart['id']) ?>">
          <header class="lms-chart-head">
            <div><h3><?= htmlspecialchars($chart['title']) ?></h3><?php if ($chart['subtitle'] !== ''): ?><p><?= htmlspecialchars($chart['subtitle']) ?></p><?php endif; ?></div>
            <?php if (!in_array($chart['type'], ['meter', 'notice'], true)): ?><button type="button" class="lms-chart-toggle" aria-pressed="false">View as table</button><?php endif; ?>
          </header>
          <div class="lms-chart-body"></div>
        </section>
      <?php endforeach; ?>
    </div>
    <script type="application/json" id="lms-chart-data"><?= json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
  </div>
  <?php endif; ?>

  <div class="col-12"><div class="row">
    <div class="col-md-8"><div class="card shadow"><div class="card-body"><table class="table table-hover logistics-data-table" style="font-size:11px"><thead><tr><th colspan="5">Recent Trips and Deliveries</th></tr><tr><th>#</th><th>Reference</th><th>Route</th><th>Vehicle / Driver</th><th>Status</th></tr></thead><tbody><?php foreach ($recentTrips as $index => $trip): ?><tr><td><?= $index + 1 ?></td><td><strong><?= htmlspecialchars($trip['reference']) ?></strong></td><td><?= htmlspecialchars($trip['route']) ?></td><td><?= htmlspecialchars(trim($trip['vehicle'] . ' / ' . $trip['driver'], ' /')) ?></td><td><span class="badge badge-light"><?= htmlspecialchars($trip['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    <?php
      $attentionIcons = ['warning' => 'alert-triangle', 'critical' => 'alert-octagon', 'success' => 'check-circle', 'primary' => 'navigation'];
      $attentionOpen = array_filter($attention, static fn (array $item): bool => $item['tone'] !== 'success');
    ?>
    <div class="col-md-4"><div class="card shadow eq-card lms-attention-card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <strong class="card-title mb-0">Operational attention</strong>
        <?php if ($attentionOpen !== []): ?><span class="badge badge-pill lms-attention-count"><?= count($attentionOpen) ?> open</span><?php else: ?><span class="small text-muted">All clear</span><?php endif; ?>
      </div>
      <div class="lms-attention-list" data-simplebar style="max-height:360px;overflow-y:auto">
        <?php if ($attention === []): ?>
          <div class="lms-attention-empty"><i class="fe fe-check-circle fe-24"></i><p>Nothing needs attention right now.</p></div>
        <?php endif; ?>
        <?php foreach ($attention as $item): ?>
          <div class="lms-attention-item lms-attention-<?= htmlspecialchars($item['tone']) ?>">
            <span class="lms-attention-icon"><i class="fe fe-<?= htmlspecialchars($attentionIcons[$item['tone']] ?? 'info') ?> fe-16"></i></span>
            <div class="lms-attention-body">
              <strong><?= htmlspecialchars($item['title']) ?></strong>
              <p><?= htmlspecialchars($item['text']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div></div>
  </div></div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
