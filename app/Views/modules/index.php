<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$rows = $listing['rows'];
$query = array_intersect_key($_GET, array_flip(array_merge(['q', 'per_page', 'sort', 'dir'], array_keys($module['filters']))));
$canToggle = in_array($moduleKey, ['vehicles', 'drivers', 'customers', 'suppliers', 'users', 'rates', 'warehouse', 'shipments'], true) && can_edit($moduleKey);

/** Builds a link to this same list with one parameter changed. */
$link = static function (array $changes) use ($moduleKey, $query): string {
    $merged = array_filter(array_merge($query, $changes), static fn ($value): bool => $value !== '' && $value !== null);
    return url($moduleKey, $merged);
};

$sortLink = static function (string $column) use ($listing, $link): string {
    $dir = ($listing['sort'] === $column && $listing['dir'] === 'asc') ? 'desc' : 'asc';
    return $link(['sort' => $column, 'dir' => $dir, 'page' => null]);
};

/** One cell, formatted by the column type declared in the schema. */
$cell = static function (mixed $value, array $spec): string {
    if ($value === null || $value === '') {
        return '<span class="text-muted">' . e($spec['empty'] ?? '—') . '</span>';
    }

    // A column may carry its own value => label map, for keys the schema names
    // but the database stores raw (which reference list an entry belongs to).
    if (isset($spec['map']) && array_key_exists((string) $value, $spec['map'])) {
        $value = $spec['map'][(string) $value];
    }

    return match ($spec['type'] ?? 'text') {
        'code' => '<strong>' . e($value) . '</strong>',
        'badge' => '<span class="badge badge-' . e(\Models\Schema::tone((string) $value)) . '">' . e(\Models\Schema::label((string) $value)) . '</span>',
        'label' => e(\Models\Schema::label((string) $value)),
        'money' => '<span class="lms-num">' . e(money((float) $value)) . '</span>',
        'number' => '<span class="lms-num">' . e(number_format((float) $value)) . (isset($spec['suffix']) ? ' ' . e($spec['suffix']) : '') . '</span>',
        'decimal' => '<span class="lms-num">' . e(rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.')) . (isset($spec['suffix']) ? ' ' . e($spec['suffix']) : '') . '</span>',
        'date' => e(date('d M Y', (int) strtotime((string) $value))),
        'datetime' => e(date('d M Y H:i', (int) strtotime((string) $value))),
        'rating' => str_repeat('★', max(0, min(5, (int) $value))) . '<span class="text-muted">' . str_repeat('☆', 5 - max(0, min(5, (int) $value))) . '</span>',
        'privilege' => (int) $value === 1 ? '<span class="badge badge-info">Privileged</span>' : '<span class="badge badge-light">Standard</span>',
        'file' => '<span class="badge badge-light">Attached</span>',
        'yesno' => (int) $value === 1 ? '<span class="badge badge-success">Yes</span>' : '<span class="text-muted">No</span>',
        'expiry' => (static function (string $date): string {
            $days = days_until($date);
            $shown = e(date('d M Y', (int) strtotime($date)));
            if ($days === null) {
                return $shown;
            }
            if ($days < 0) {
                return $shown . ' <span class="badge badge-danger">' . abs($days) . 'd late</span>';
            }
            if ($days <= 30) {
                return $shown . ' <span class="badge badge-warning">' . $days . 'd</span>';
            }
            return $shown;
        })((string) $value),
        default => e($value),
    };
};
?>
<div class="container-fluid lms-list-page">

  <div class="lms-page-head">
    <div>
      <p class="lms-kicker"><?= e($module['kicker']) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-<?= e($module['icon']) ?> mr-2"></i><?= e($module['title']) ?></h2>
      <p class="lms-page-sub"><?= e($module['description']) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= e(url([$moduleKey, 'export', 'csv'], $query)) ?>"><i class="fe fe-download fe-12 mr-1"></i>Export CSV</a>
      <?php if (can_create($moduleKey)): ?>
        <a class="btn btn-primary" href="<?= url([$moduleKey, 'create']) ?>"><i class="fe fe-plus fe-12 mr-1"></i><?= e($module['button']) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">

      <form method="get" action="<?= url($moduleKey) ?>" class="lms-filter-bar">
        <div class="lms-filter-search">
          <i class="fe fe-search fe-16"></i>
          <input type="search" name="q" class="form-control" value="<?= e($listing['search']) ?>" placeholder="Search <?= e(strtolower($module['title'])) ?>...">
        </div>
        <?php foreach ($module['filters'] as $name => $filter): ?>
          <select name="<?= e($name) ?>" class="form-control lms-filter-select" onchange="this.form.submit()">
            <option value="">All <?= e(strtolower($filter['label'])) ?></option>
            <?php foreach ($filter['options'] as $optionValue => $optionLabel): ?>
              <option value="<?= e($optionValue) ?>" <?= ($listing['filters'][$name] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endforeach; ?>
        <select name="per_page" class="form-control lms-filter-select lms-filter-narrow" onchange="this.form.submit()">
          <?php foreach (\Models\LogisticsData::PER_PAGE_CHOICES as $choice): ?>
            <option value="<?= $choice ?>" <?= $listing['per_page'] === $choice ? 'selected' : '' ?>><?= $choice ?> / page</option>
          <?php endforeach; ?>
        </select>
        <?php if ($listing['sort'] !== ''): ?><input type="hidden" name="sort" value="<?= e($listing['sort']) ?>"><input type="hidden" name="dir" value="<?= e($listing['dir']) ?>"><?php endif; ?>
        <button class="btn btn-outline-primary" type="submit">Apply</button>
        <?php if ($listing['search'] !== '' || $listing['filters'] !== []): ?>
          <a class="btn btn-link text-muted" href="<?= url($moduleKey) ?>">Clear</a>
        <?php endif; ?>
      </form>

      <p class="lms-result-count">
        <?php if ($listing['total'] === 0): ?>
          <?php /* "No records match" would blame a search nobody made on a module that is simply still empty. */ ?>
          <?= $listing['search'] !== '' || $listing['filters'] !== [] ? 'No records match.' : 'Nothing recorded yet.' ?>
        <?php else: ?>
          Showing <strong><?= number_format(($listing['page'] - 1) * $listing['per_page'] + 1) ?>&ndash;<?= number_format(min($listing['page'] * $listing['per_page'], $listing['total'])) ?></strong>
          of <strong><?= number_format($listing['total']) ?></strong> record<?= $listing['total'] === 1 ? '' : 's' ?>
        <?php endif; ?>
        <?php if (current_role() === 'driver'): ?><span class="badge badge-light ml-2">Scoped to your own work</span><?php endif; ?>
      </p>

      <div class="table-responsive">
        <table class="table table-hover lms-table">
          <thead>
            <tr>
              <?php foreach ($module['list'] as $column => $spec): ?>
                <th class="<?= in_array($spec['type'] ?? '', ['money', 'number', 'decimal'], true) ? 'text-right' : '' ?>">
                  <a href="<?= e($sortLink($column)) ?>" class="lms-sort<?= $listing['sort'] === $column ? ' is-sorted' : '' ?>">
                    <?= e($spec['label']) ?>
                    <?php if ($listing['sort'] === $column): ?><i class="fe fe-chevron-<?= $listing['dir'] === 'asc' ? 'up' : 'down' ?> fe-12"></i><?php endif; ?>
                  </a>
                </th>
              <?php endforeach; ?>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="<?= count($module['list']) + 1 ?>" class="lms-empty">
                <i class="fe fe-inbox fe-32"></i>
                <p class="mb-1"><strong>Nothing here yet</strong></p>
                <p class="text-muted small mb-2"><?= $listing['search'] !== '' || $listing['filters'] !== [] ? 'No record matches the current search and filters.' : $module['description'] ?></p>
                <?php if (can_create($moduleKey)): ?><a class="btn btn-sm btn-primary" href="<?= url([$moduleKey, 'create']) ?>"><?= e($module['button']) ?></a><?php endif; ?>
              </td></tr>
            <?php endif; ?>

            <?php foreach ($rows as $row): ?>
              <tr>
                <?php $firstColumn = true; ?>
                <?php foreach ($module['list'] as $column => $spec): ?>
                  <td class="<?= in_array($spec['type'] ?? '', ['money', 'number', 'decimal'], true) ? 'text-right' : '' ?>">
                    <?php if ($firstColumn): ?>
                      <a href="<?= url([$moduleKey, $row['id']]) ?>" class="lms-row-link"><?= $cell($row[$column] ?? null, $spec) ?></a>
                    <?php elseif (($spec['type'] ?? '') === 'file' && !empty($row[$column])): ?>
                      <a href="<?= e(url(array_merge(['files'], explode('/', (string) $row[$column])))) ?>" target="_blank" rel="noopener" class="badge badge-light"><i class="fe fe-paperclip fe-12"></i> Open</a>
                    <?php else: ?>
                      <?= $cell($row[$column] ?? null, $spec) ?>
                    <?php endif; ?>
                  </td>
                  <?php $firstColumn = false; ?>
                <?php endforeach; ?>
                <td class="text-right text-nowrap lms-row-actions">
                  <a href="<?= url([$moduleKey, $row['id']]) ?>" class="btn btn-sm btn-link" title="View"><i class="fe fe-eye fe-16"></i></a>
                  <?php if (can_edit($moduleKey)): ?>
                    <a href="<?= url([$moduleKey, $row['id'], 'edit']) ?>" class="btn btn-sm btn-link" title="Edit"><i class="fe fe-edit-2 fe-16"></i></a>
                  <?php endif; ?>
                  <?php if ($canToggle && isset($row['status'])): ?>
                    <?php $isOn = !in_array(strtolower((string) $row['status']), ['inactive', 'off_duty', 'cancelled', 'out_of_stock', 'draft'], true); ?>
                    <form method="post" action="<?= url([$moduleKey, $row['id'], 'toggle']) ?>" class="d-inline switch-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="status" value="<?= $isOn ? '1' : '0' ?>">
                      <div class="custom-control custom-switch d-inline-block align-middle">
                        <input type="checkbox" class="custom-control-input status-switch" id="sw-<?= e($moduleKey . '-' . $row['id']) ?>" <?= $isOn ? 'checked' : '' ?> aria-label="Toggle status">
                        <label class="custom-control-label" for="sw-<?= e($moduleKey . '-' . $row['id']) ?>"></label>
                      </div>
                    </form>
                  <?php endif; ?>
                  <?php if (can_delete($moduleKey)): ?>
                    <form method="post" action="<?= url([$moduleKey, $row['id'], 'delete']) ?>" class="d-inline delete-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="reason">
                      <button class="btn btn-sm btn-link text-danger" type="submit" title="Delete"><i class="fe fe-trash-2 fe-16"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($listing['pages'] > 1): ?>
        <nav class="lms-pagination" aria-label="Pagination">
          <a class="btn btn-sm btn-outline-secondary <?= $listing['page'] <= 1 ? 'disabled' : '' ?>" href="<?= e($link(['page' => max(1, $listing['page'] - 1)])) ?>">Previous</a>
          <span class="lms-pagination-pages">
            <?php
            $start = max(1, $listing['page'] - 2);
            $end = min($listing['pages'], $start + 4);
            $start = max(1, $end - 4);
            for ($p = $start; $p <= $end; $p++):
            ?>
              <a class="lms-page<?= $p === $listing['page'] ? ' is-current' : '' ?>" href="<?= e($link(['page' => $p])) ?>"><?= $p ?></a>
            <?php endfor; ?>
          </span>
          <span class="small text-muted">Page <?= $listing['page'] ?> of <?= $listing['pages'] ?></span>
          <a class="btn btn-sm btn-outline-secondary <?= $listing['page'] >= $listing['pages'] ? 'disabled' : '' ?>" href="<?= e($link(['page' => min($listing['pages'], $listing['page'] + 1)])) ?>">Next</a>
        </nav>
      <?php endif; ?>
    </div>
  </div>

  <?php if (can_view('audit') && $audit !== []): ?>
    <div class="card shadow-sm mt-3">
      <header class="lms-section-head"><div><h3><i class="fe fe-activity fe-16 mr-2"></i>Recent activity</h3></div><a class="btn btn-sm btn-outline-secondary" href="<?= url('audit') ?>">Full audit trail</a></header>
      <div class="card-body lms-timeline lms-timeline-compact">
        <?php foreach ($audit as $entry): ?>
          <div class="lms-timeline-item">
            <span class="lms-timeline-dot"></span>
            <div>
              <strong><?= e(ucfirst(str_replace(['.', '_'], ' ', (string) $entry['action_name']))) ?></strong>
              <?php if (!empty($entry['entity_id'])): ?><span class="badge badge-light ml-1"><?= e($entry['entity_id']) ?></span><?php endif; ?>
              <small class="d-block text-muted"><?= e($entry['actor']) ?> &middot; <?= e(time_ago($entry['created_at'])) ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
