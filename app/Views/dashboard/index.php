<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid">
  <?php if ($denied !== ''): ?>
    <div class="alert alert-warning">
      <i class="fe fe-lock mr-2"></i>
      <strong><?= e(ucfirst(str_replace('_', ' ', $denied))) ?></strong> is not available for the <?= e(role_label()) ?> role.
      <?php if (can_view('users')): ?><a class="alert-link ml-2" href="<?= url('permissions', ['role' => current_role()]) ?>">Review role permissions</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="row justify-content-center"><div class="col-12"><div class="row align-items-center mb-2"><div class="col"><h2 class="h5 page-title"><?= htmlspecialchars($title) ?> <small class="text-muted">· <?= e(current_company_name()) ?></small></h2><p class="small text-muted mb-0"><?= e($roleSubtitle ?? '') ?></p></div></div></div>
  <?php if (!empty($home)): ?>
    <?php // What this person must do next, each with a count (design rule 3). ?>
    <div class="col-12 mb-3"><div class="lms-home-cards">
      <?php foreach ($home as $card): ?>
        <a class="lms-home-card lms-home-<?= e($card['tone']) ?>" href="<?= e($card['url']) ?>">
          <span class="lms-home-icon"><i class="fe fe-<?= e($card['icon']) ?>"></i></span>
          <span class="lms-home-body"><strong class="lms-home-count"><?= e((string) $card['count']) ?></strong><span class="lms-home-label"><?= e($card['label']) ?></span><?php if ($card['detail'] !== ''): ?><small class="lms-home-detail"><?= e($card['detail']) ?></small><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </div></div>
  <?php endif; ?>
  <?php
  // A driver's trips and papers have their own page ("My trips & papers"); the
  // dashboard only says where he is going next and links to it. Never let it
  // stop the dashboard from opening.
  if (($role ?? '') === 'driver' && current_driver_id() !== null) {
      try {
          $nextTrip = null;
          foreach (\Models\DriverWorkspace::trips((int) current_driver_id()) as $candidate) {
              if (in_array($candidate['status'], ['requested', 'approved', 'loading', 'in_transit'], true)) {
                  $nextTrip = $candidate;
                  break;
              }
          }
          ?>
          <div class="col-12 mb-3">
            <div class="card shadow-sm lms-mt-live">
              <div class="card-body d-flex justify-content-between align-items-center flex-wrap" style="gap:12px">
                <div>
                  <?php if ($nextTrip !== null): ?>
                    <div class="small text-muted text-uppercase font-weight-bold">Your trip now</div>
                    <div class="h5 mb-1"><?= e($nextTrip['reference_code']) ?> · <?= e($nextTrip['pickup_location']) ?> <i class="fe fe-arrow-right"></i> <?= e($nextTrip['destination']) ?></div>
                    <div class="small text-muted">Truck <?= e($nextTrip['plate_number'] ?? '—') ?> · <?= e(\Models\Schema::label((string) $nextTrip['status'])) ?><?= $nextTrip['planned_arrival_at'] ? ' · arrive by ' . e(date('D d M, H:i', strtotime((string) $nextTrip['planned_arrival_at']))) : '' ?></div>
                  <?php else: ?>
                    <div class="h6 mb-0">No trip for you right now.</div>
                  <?php endif; ?>
                </div>
                <a class="btn btn-primary" href="<?= url('my-trips') ?>"><i class="fe fe-truck fe-12 mr-1"></i>My trips &amp; papers</a>
              </div>
            </div>
          </div>
          <?php
      } catch (\Throwable $panelError) {
          error_log(sprintf('LMS driver card: %s: %s in %s:%d', $panelError::class, $panelError->getMessage(), $panelError->getFile(), $panelError->getLine()));
      }
  }
  ?>
  <div class="col-12"><div class="row">
    <?php foreach ($metrics as $index => $metric): ?><div class="col-md-6 col-xl-3 mb-4"><div class="card shadow border-0 <?= $index === 2 ? 'bg-primary text-white' : '' ?>"><div class="card-body"><div class="row align-items-center"><div class="col-3 text-center"><span class="circle circle-sm <?= $index === 2 ? 'bg-primary-light' : 'bg-primary' ?>"><i class="fe fe-<?= htmlspecialchars($metric['icon']) ?> fe-16 text-white mb-0"></i></span></div><div class="col pr-0"><p class="small <?= $index === 2 ? 'text-white' : 'text-muted' ?> mb-0"><?= htmlspecialchars($metric['label']) ?></p><span class="h3 mb-0 <?= $index === 2 ? 'text-white' : '' ?>"><?= htmlspecialchars($metric['value']) ?></span><span class="small <?= $index === 2 ? 'text-white' : 'text-success' ?> ml-2"><?= htmlspecialchars($metric['trend']) ?></span></div></div></div></div></div><?php endforeach; ?>
  </div></div>

  <?php if (($clearanceBoard ?? []) !== [] && current_role() === 'clearance_officer'): ?>
    <div class="col-12 mb-4">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><i class="fe fe-flag fe-12 mr-1"></i>Clearance board</strong><a class="small" href="<?= url('crossings') ?>">All clearance files</a></div>
        <div class="lms-board">
          <?php foreach ($clearanceBoard as $stage): ?>
            <section class="lms-board-col">
              <header><?= e($stage['label']) ?> <span class="badge badge-pill badge-light"><?= count($stage['files']) ?></span></header>
              <?php if ($stage['files'] === []): ?><p class="small text-muted mb-0">None</p><?php endif; ?>
              <?php foreach ($stage['files'] as $file): ?>
                <a class="lms-board-card<?= $file['hold_reason'] ? ' is-held' : '' ?>" href="<?= url(['crossings', (int) $file['id']]) ?>">
                  <strong><?= e($file['reference']) ?></strong> <small><?= e(ucfirst((string) $file['direction'])) ?></small>
                  <span><?= e($file['post_name']) ?><?= $file['plate_number'] ? ' · ' . e($file['plate_number']) : '' ?></span>
                  <span><?= $file['trip'] ? e($file['trip']) . ' (' . e(\Models\Schema::label((string) $file['trip_status'])) . ')' : e((string) $file['shipment_code']) ?></span>
                  <?php if ((int) $file['papers_open'] > 0): ?><em><?= (int) $file['papers_open'] ?> paper<?= (int) $file['papers_open'] === 1 ? '' : 's' ?> open</em><?php endif; ?>
                  <?php if ($file['hold_reason']): ?><em class="text-danger">Held: <?= e($file['hold_reason']) ?></em><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </section>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (($workboard ?? []) !== []): ?>
    <div class="col-12">
      <div class="lms-workboard">
        <?php foreach ($workboard as $panel): ?>
          <section class="card shadow-sm lms-panel">
            <header class="lms-panel-head">
              <span><i class="fe fe-<?= e($panel['icon']) ?> fe-14 mr-1"></i><?= e($panel['title']) ?></span>
              <?php if ($panel['count'] !== null): ?><span class="badge badge-pill <?= (int) $panel['count'] > 0 ? 'badge-primary' : 'badge-light' ?>"><?= (int) $panel['count'] ?></span><?php endif; ?>
            </header>
            <?php if ($panel['items'] === []): ?>
              <p class="lms-panel-empty"><i class="fe fe-check-circle mr-1"></i><?= e($panel['empty']) ?></p>
            <?php else: ?>
              <ul class="lms-panel-list">
                <?php foreach ($panel['items'] as $item): ?>
                  <li class="lms-panel-item lms-tone-<?= e($item['tone'] ?? 'primary') ?>">
                    <?php if (!empty($item['url'])): ?><a href="<?= e($item['url']) ?>"><?php else: ?><div><?php endif; ?>
                      <strong><?= e($item['title']) ?></strong>
                      <?php if (!empty($item['badge'])): ?><span class="lms-panel-badge"><?= e($item['badge']) ?></span><?php endif; ?>
                      <small><?= e($item['sub']) ?></small>
                    <?php if (!empty($item['url'])): ?></a><?php else: ?></div><?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if (!empty($panel['more'])): ?><a class="lms-panel-more" href="<?= e($panel['more']['url']) ?>"><?= e($panel['more']['label']) ?> <i class="fe fe-arrow-right fe-12"></i></a><?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($charts !== []): ?>
  <?php $moneyCharts = array_filter($charts, static fn (array $c): bool => ($c['format'] ?? '') === 'rwf'); ?>
  <?php if ($moneyCharts !== [] && count($currencies ?? []) > 1): ?>
    <div class="col-12 mb-2">
      <form method="get" class="form-inline lms-chart-currency">
        <label class="small text-muted mr-2" for="chartCurrency">Money charts in</label>
        <select class="custom-select custom-select-sm" name="currency" id="chartCurrency" onchange="this.form.submit()">
          <?php foreach ($currencies as $code => $label): ?>
            <option value="<?= e($code) ?>"<?= $code === ($chartCurrency ?? '') ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="small text-muted ml-2">Each currency is shown on its own; amounts are never added across currencies.</span>
      </form>
    </div>
  <?php endif; ?>
  <div class="col-12">
    <div class="lms-charts" data-currency="<?= e($chartCurrency ?? 'RWF') ?>">
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
