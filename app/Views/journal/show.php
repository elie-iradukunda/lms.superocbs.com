<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$totalDebit = 0.0;
$totalCredit = 0.0;
foreach ($lines as $line) {
    $totalDebit += (float) $line['debit'];
    $totalCredit += (float) $line['credit'];
}
$sourceRoutes = [
    'invoice' => 'invoices', 'payment' => 'payments', 'expense' => 'expenses',
    'fuel' => 'fuel', 'maintenance' => 'maintenance', 'purchase' => 'procurement',
];
$sourceRoute = $sourceRoutes[(string) $entry['source_type']] ?? null;
?>
<div class="container-fluid lms-detail-page">

  <div class="lms-page-head">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb lms-crumbs">
          <li class="breadcrumb-item"><a href="<?= url('journal') ?>">Journal</a></li>
          <li class="breadcrumb-item active"><?= e($entry['entry_no']) ?></li>
        </ol>
      </nav>
      <h2 class="lms-page-title">
        <i class="fe fe-book-open mr-2"></i><?= e($entry['entry_no']) ?>
        <span class="badge badge-<?= $entry['status'] === 'reversed' ? 'danger' : 'success' ?> lms-status-badge"><?= e(ucfirst((string) $entry['status'])) ?></span>
      </h2>
      <p class="lms-page-sub"><?= e($entry['memo']) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('journal') ?>"><i class="fe fe-arrow-left fe-12 mr-1"></i>Back</a>
      <?php if ($entry['status'] === 'posted' && can_approve('journal')): ?>
        <form method="post" action="<?= url(['journal', $entry['id'], 'reverse']) ?>" class="d-inline reason-form" data-reason-title="Reverse this entry">
          <?= csrf_field() ?>
          <input type="hidden" name="reason">
          <button type="submit" class="btn btn-outline-danger"><i class="fe fe-rotate-ccw fe-12 mr-1"></i>Reverse</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($entry['status'] === 'reversed' && $reversal !== null): ?>
    <div class="alert alert-warning">
      <i class="fe fe-rotate-ccw mr-2"></i>
      This entry was reversed by <a href="<?= url(['journal', $reversal['id']]) ?>" class="alert-link"><?= e($reversal['entry_no']) ?></a>.
      Both remain in the journal, which is what makes the book auditable.
    </div>
  <?php endif; ?>

  <div class="lms-highlights">
    <div class="lms-highlight">
      <span class="lms-highlight-icon"><i class="fe fe-calendar fe-16"></i></span>
      <div><small>Entry date</small><strong><?= e(date('d M Y', (int) strtotime((string) $entry['entry_date']))) ?></strong></div>
    </div>
    <div class="lms-highlight">
      <span class="lms-highlight-icon"><i class="fe fe-dollar-sign fe-16"></i></span>
      <div><small>Amount</small><strong><?= e(money($totalDebit, true)) ?></strong></div>
    </div>
    <div class="lms-highlight">
      <span class="lms-highlight-icon"><i class="fe fe-link fe-16"></i></span>
      <div>
        <small>Source</small>
        <strong>
          <?php if ($sourceRoute !== null && $entry['source_id'] !== null && can_view($sourceRoute)): ?>
            <a href="<?= url([$sourceRoute, $entry['source_id']]) ?>"><?= e($entry['source_code'] ?: ucfirst((string) $entry['source_type'])) ?></a>
          <?php else: ?>
            <?= e($entry['source_code'] ?: ucfirst((string) ($entry['source_type'] ?? 'Manual'))) ?>
          <?php endif; ?>
        </strong>
      </div>
    </div>
    <div class="lms-highlight">
      <span class="lms-highlight-icon"><i class="fe fe-user fe-16"></i></span>
      <div><small>Posted by</small><strong><?= e($entry['posted_by_name']) ?></strong></div>
    </div>
  </div>

  <section class="card shadow-sm lms-section">
    <header class="lms-section-head">
      <div>
        <h3><i class="fe fe-list fe-16 mr-2"></i>Entry lines</h3>
        <p>Both columns must agree, or the entry could not have been posted.</p>
      </div>
    </header>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table lms-book mb-0">
          <thead><tr><th>Account</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
          <tbody>
            <?php foreach ($lines as $line): ?>
              <tr>
                <td>
                  <?php if (can_view('accounts')): ?>
                    <a href="<?= url(['books', 'view', 'account_statement'], ['account_id' => $line['account_id'], 'from' => date('Y-01-01'), 'to' => date('Y-m-d')]) ?>">
                      <strong><?= e($line['account_code']) ?></strong> <?= e($line['account_name']) ?>
                    </a>
                  <?php else: ?>
                    <strong><?= e($line['account_code']) ?></strong> <?= e($line['account_name']) ?>
                  <?php endif; ?>
                </td>
                <td class="text-muted"><?= e($line['description'] ?? '') ?></td>
                <td class="text-right lms-num"><?= (float) $line['debit'] > 0 ? e(money((float) $line['debit'])) : '' ?></td>
                <td class="text-right lms-num"><?= (float) $line['credit'] > 0 ? e(money((float) $line['credit'])) : '' ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="lms-book-grand">
              <td colspan="2"><strong>TOTAL</strong></td>
              <td class="text-right lms-num"><?= e(money($totalDebit)) ?></td>
              <td class="text-right lms-num"><?= e(money($totalCredit)) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
