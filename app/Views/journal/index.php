<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$query = array_intersect_key($_GET, array_flip(['q', 'source', 'from', 'to']));
$sources = [
    'manual' => 'Manual entry', 'opening' => 'Opening balances', 'invoice' => 'Invoice',
    'payment' => 'Payment', 'expense' => 'Expense', 'fuel' => 'Fuel',
    'maintenance' => 'Maintenance', 'purchase' => 'Goods received', 'reversal' => 'Reversal',
];
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Accounting</p>
      <h2 class="lms-page-title"><i class="fe fe-book-open mr-2"></i>Journal</h2>
      <p class="lms-page-sub">Every entry the ledger holds, in date order. Most are posted by the system when a document is issued, approved or completed.</p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('books') ?>"><i class="fe fe-book fe-12 mr-1"></i>Books</a>
      <a class="btn btn-outline-secondary" href="<?= e(url(['books', 'export', 'journal', 'pdf'], ['from' => $from ?: date('Y-01-01'), 'to' => $to ?: date('Y-m-d')])) ?>"><i class="fe fe-file-text fe-12 mr-1"></i>PDF</a>
      <?php if (can_create('journal')): ?>
        <a class="btn btn-primary" href="<?= url(['journal', 'create']) ?>"><i class="fe fe-plus fe-12 mr-1"></i>New entry</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$balanced): ?>
    <div class="alert alert-danger"><i class="fe fe-alert-octagon mr-2"></i><strong>The ledger does not balance.</strong> Total debits and credits differ across the posted entries.</div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="get" action="<?= url('journal') ?>" class="lms-filter-bar">
        <div class="lms-filter-search">
          <i class="fe fe-search fe-16"></i>
          <input type="search" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search entry number, memo or source reference...">
        </div>
        <select name="source" class="form-control lms-filter-select">
          <option value="">Every source</option>
          <?php foreach ($sources as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $source === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="form-control lms-filter-narrow" value="<?= e($from) ?>" aria-label="From date">
        <input type="date" name="to" class="form-control lms-filter-narrow" value="<?= e($to) ?>" aria-label="To date">
        <button class="btn btn-outline-primary" type="submit">Apply</button>
        <?php if ($query !== []): ?><a class="btn btn-link text-muted" href="<?= url('journal') ?>">Clear</a><?php endif; ?>
      </form>

      <p class="lms-result-count"><strong><?= number_format($entries['total']) ?></strong> entr<?= $entries['total'] === 1 ? 'y' : 'ies' ?></p>

      <div class="table-responsive">
        <table class="table table-hover lms-table">
          <thead>
            <tr>
              <th>Entry</th><th>Date</th><th>Memo</th><th>Source</th><th>Reference</th>
              <th class="text-right">Amount</th><th>Status</th><th class="text-right">Open</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($entries['rows'] === []): ?>
              <tr><td colspan="8" class="lms-empty">
                <i class="fe fe-inbox fe-32"></i>
                <p class="mb-1"><strong>No entry matches</strong></p>
                <p class="text-muted small mb-0">Widen the dates, or post the outstanding documents from the books page.</p>
              </td></tr>
            <?php endif; ?>

            <?php foreach ($entries['rows'] as $entry): ?>
              <tr>
                <td><a href="<?= url(['journal', $entry['id']]) ?>" class="lms-row-link"><strong><?= e($entry['entry_no']) ?></strong></a></td>
                <td class="text-nowrap"><?= e(date('d M Y', (int) strtotime((string) $entry['entry_date']))) ?></td>
                <td><?= e($entry['memo']) ?></td>
                <td><?= e($sources[(string) ($entry['source_type'] ?? 'manual')] ?? ucfirst((string) $entry['source_type'])) ?></td>
                <td><?= $entry['source_code'] ? e($entry['source_code']) : '<span class="text-muted">&mdash;</span>' ?></td>
                <td class="text-right lms-num"><?= e(money((float) $entry['total_debit'])) ?></td>
                <td><span class="badge badge-<?= $entry['status'] === 'reversed' ? 'danger' : 'success' ?>"><?= e(ucfirst((string) $entry['status'])) ?></span></td>
                <td class="text-right"><a href="<?= url(['journal', $entry['id']]) ?>" class="btn btn-sm btn-link"><i class="fe fe-eye fe-16"></i></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($entries['pages'] > 1): ?>
        <nav class="lms-pagination">
          <a class="btn btn-sm btn-outline-secondary <?= $entries['page'] <= 1 ? 'disabled' : '' ?>" href="<?= e(url('journal', $query + ['page' => max(1, $entries['page'] - 1)])) ?>">Previous</a>
          <span class="small text-muted">Page <?= $entries['page'] ?> of <?= $entries['pages'] ?></span>
          <a class="btn btn-sm btn-outline-secondary <?= $entries['page'] >= $entries['pages'] ? 'disabled' : '' ?>" href="<?= e(url('journal', $query + ['page' => min($entries['pages'], $entries['page'] + 1)])) ?>">Next</a>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
