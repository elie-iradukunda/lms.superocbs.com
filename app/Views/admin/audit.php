<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$query = array_intersect_key($_GET, array_flip(['q', 'entity', 'from', 'to']));
$tone = static function (string $action): string {
    return match (true) {
        str_contains($action, 'deleted') || str_contains($action, 'failed') || str_contains($action, 'reject') => 'danger',
        str_contains($action, 'created') || str_contains($action, 'approve') || str_contains($action, 'login') => 'success',
        str_contains($action, 'updated') || str_contains($action, 'status') => 'info',
        str_contains($action, 'export') || str_contains($action, 'opened') => 'secondary',
        default => 'primary',
    };
};
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Administration</p>
      <h2 class="lms-page-title"><i class="fe fe-activity mr-2"></i>Audit trail</h2>
      <p class="lms-page-sub">Every login, record change, approval, deletion, file download and export, with who did it and why.</p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= e(url(['audit', 'export'], $query)) ?>"><i class="fe fe-download fe-12 mr-1"></i>Export CSV</a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="get" action="<?= url('audit') ?>" class="lms-filter-bar">
        <div class="lms-filter-search">
          <i class="fe fe-search fe-16"></i>
          <input type="search" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search action, reference, reason or person...">
        </div>
        <select name="entity" class="form-control lms-filter-select">
          <option value="">All areas</option>
          <?php foreach ($entities as $option): ?>
            <option value="<?= e($option) ?>" <?= $entity === $option ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $option))) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="form-control lms-filter-narrow" value="<?= e($_GET['from'] ?? '') ?>" aria-label="From date">
        <input type="date" name="to" class="form-control lms-filter-narrow" value="<?= e($_GET['to'] ?? '') ?>" aria-label="To date">
        <button class="btn btn-outline-primary" type="submit">Apply</button>
        <?php if ($query !== []): ?><a class="btn btn-link text-muted" href="<?= url('audit') ?>">Clear</a><?php endif; ?>
      </form>

      <p class="lms-result-count"><strong><?= number_format($total) ?></strong> entr<?= $total === 1 ? 'y' : 'ies' ?></p>

      <div class="table-responsive">
        <table class="table table-hover lms-table">
          <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Area</th><th>Reference</th><th>Reason / detail</th></tr></thead>
          <tbody>
            <?php if ($entries === []): ?>
              <tr><td colspan="6" class="lms-empty"><i class="fe fe-inbox fe-32"></i><p class="mb-0"><strong>No audit entry matches</strong></p></td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $entry): ?>
              <tr>
                <td class="text-nowrap"><?= e(date('d M Y H:i', (int) strtotime((string) $entry['created_at']))) ?><small class="d-block text-muted"><?= e(time_ago((string) $entry['created_at'])) ?></small></td>
                <td><?= e($entry['actor']) ?><?php if (!empty($entry['actor_email'])): ?><small class="d-block text-muted"><?= e($entry['actor_email']) ?></small><?php endif; ?></td>
                <td><span class="badge badge-<?= e($tone((string) $entry['action_name'])) ?>"><?= e(str_replace(['.', '_'], [' ', ' '], (string) $entry['action_name'])) ?></span></td>
                <td><?= e(ucfirst(str_replace('_', ' ', (string) $entry['entity_type']))) ?></td>
                <td><?= $entry['entity_id'] ? '<strong>' . e($entry['entity_id']) . '</strong>' : '<span class="text-muted">—</span>' ?></td>
                <td class="small">
                  <?php if (!empty($entry['reason'])): ?><div><?= e($entry['reason']) ?></div><?php endif; ?>
                  <?php if (!empty($entry['metadata'])): ?>
                    <?php $meta = json_decode((string) $entry['metadata'], true); ?>
                    <?php if (is_array($meta)): ?>
                      <span class="text-muted">
                        <?php foreach ($meta as $metaKey => $metaValue): ?>
                          <?= e($metaKey) ?>: <?= e(is_scalar($metaValue) ? (string) $metaValue : json_encode($metaValue)) ?><?= $metaKey !== array_key_last($meta) ? ' · ' : '' ?>
                        <?php endforeach; ?>
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if (empty($entry['reason']) && empty($entry['metadata'])): ?><span class="text-muted">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="lms-pagination">
          <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e(url('audit', $query + ['page' => max(1, $page - 1)])) ?>">Previous</a>
          <span class="small text-muted">Page <?= $page ?> of <?= $pages ?></span>
          <a class="btn btn-sm btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= e(url('audit', $query + ['page' => min($pages, $page + 1)])) ?>">Next</a>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
