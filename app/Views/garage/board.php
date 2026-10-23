<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var list<array<string, mixed>> $open */
/** @var list<array<string, mixed>> $closed */
$statusTone = ['open' => 'warning', 'scheduled' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary'];
$statusLabel = ['open' => 'Open', 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Closed', 'cancelled' => 'Cancelled'];
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Garage · <?= e(current_company_name()) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-tool mr-2"></i>Job cards</h2>
    </div>
    <div class="lms-page-actions">
      <?php if (can_create('form_requests')): ?>
        <a class="btn btn-primary" href="<?= url(['form_requests', 'create'], ['form' => 'F3']) ?>"><i class="fe fe-plus fe-12 mr-1"></i>New service request</a>
      <?php endif; ?>
      <?php if (can_view('tyre_logs')): ?><a class="btn btn-outline-secondary" href="<?= url('tyre_logs') ?>"><i class="fe fe-disc fe-12 mr-1"></i>Tyre log</a><?php endif; ?>
      <?php if (can_view('store_issues')): ?><a class="btn btn-outline-secondary" href="<?= url('store_issues') ?>"><i class="fe fe-package fe-12 mr-1"></i>Store</a><?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>In the garage now (<?= count($open) ?>)</strong></div>
    <?php if ($open === []): ?>
      <div class="card-body text-center text-muted py-4">No job card is open.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Job card</th><th>Truck</th><th>What</th><th>Opened</th><th>Parts</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($open as $job): ?>
            <tr>
              <td><a href="<?= url(['garage', $job['id']]) ?>"><strong><?= e($job['work_order_code']) ?></strong></a></td>
              <td><?= e($job['plate_number'] ?? '—') ?><?php if (!empty($job['owner_company'])): ?> <span class="badge badge-light"><?= e($job['owner_company']) ?></span><?php endif; ?></td>
              <td>
                <?php $priority = (string) ($job['priority'] ?? 'normal'); ?>
                <?php if ($priority === 'urgent' || $priority === 'high'): ?>
                  <span class="badge badge-<?= $priority === 'urgent' ? 'danger' : 'warning' ?> mr-1"><?= $priority === 'urgent' ? 'Urgent' : 'High' ?></span>
                <?php endif; ?>
                <?= e($job['service_name']) ?>
                <small class="d-block text-muted"><?= e(mb_strimwidth((string) ($job['complaint'] ?? ''), 0, 90, '…')) ?></small>
                <?php if (!empty($job['due_date']) && $job['due_date'] < date('Y-m-d')): ?>
                  <small class="d-block text-danger"><i class="fe fe-clock fe-12 mr-1"></i>Due <?= e(human_date($job['due_date'])) ?> — overdue</small>
                <?php endif; ?>
              </td>
              <td><?= e(time_ago($job['started_at'] ?? $job['created_at'])) ?></td>
              <td><?= (int) $job['notes_open'] > 0 ? '<span class="badge badge-warning">Waiting in the store</span>' : '<span class="text-muted small">—</span>' ?></td>
              <td><span class="badge badge-<?= e($statusTone[$job['status']] ?? 'light') ?>"><?= e($statusLabel[$job['status']] ?? $job['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-header"><strong>Closed in the last 30 days</strong></div>
    <?php if ($closed === []): ?>
      <div class="card-body text-center text-muted py-4">Nothing closed recently.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Job card</th><th>Truck</th><th>What</th><th>Closed</th><th class="text-right">Parts cost</th></tr></thead>
        <tbody>
          <?php foreach ($closed as $job): ?>
            <tr>
              <td><a href="<?= url(['garage', $job['id']]) ?>"><?= e($job['work_order_code']) ?></a></td>
              <td><?= e($job['plate_number'] ?? '—') ?></td>
              <td><?= e($job['service_name']) ?></td>
              <td><?= e(human_date($job['completed_at'], true)) ?></td>
              <td class="text-right"><?= $job['actual_cost'] !== null ? e(Models\Currency::format((float) $job['actual_cost'], Models\Company::baseCurrency((int) $job['company_id']))) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
