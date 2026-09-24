<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Accounting</p>
      <h2 class="lms-page-title"><i class="fe fe-book mr-2"></i>Accounting books</h2>
      <p class="lms-page-sub">The statements the business is kept by. Every figure is derived from the journal, so nothing here is typed in twice.</p>
    </div>
    <div class="lms-page-actions">
      <?php if (can_view('journal')): ?>
        <a class="btn btn-outline-secondary" href="<?= url('journal') ?>"><i class="fe fe-book-open fe-12 mr-1"></i>Journal</a>
      <?php endif; ?>
      <?php if ($canSync): ?>
        <form method="post" action="<?= url(['books', 'sync']) ?>" class="d-inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><i class="fe fe-refresh-cw fe-12 mr-1"></i>Post outstanding documents</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="alert alert-<?= $balanced ? 'success' : 'danger' ?> lms-ledger-state">
    <i class="fe fe-<?= $balanced ? 'check-circle' : 'alert-octagon' ?> mr-2"></i>
    <?php if ($balanced): ?>
      <strong>The ledger balances.</strong> Total debits equal total credits across every posted entry, and the books are prepared on a
      <?= e(strtolower(\Models\Settings::get('accounting_basis', 'Accrual Basis'))) ?> over <?= (int) $accounts ?> accounts.
    <?php else: ?>
      <strong>The ledger does not balance.</strong> Open the trial balance and find the difference before relying on any other book.
    <?php endif; ?>
  </div>

  <div class="row">
    <?php foreach ($catalogue as $key => $book): ?>
      <div class="col-md-6 col-xl-3 mb-4">
        <a class="card shadow-sm lms-report-card h-100" href="<?= url(['books', 'view', $key]) ?>">
          <div class="card-body">
            <span class="lms-report-icon"><i class="fe fe-<?= e($book['icon']) ?> fe-24"></i></span>
            <h5 class="mt-3 mb-2"><?= e($book['label']) ?></h5>
            <p class="small text-muted mb-3"><?= e($book['description']) ?></p>
            <span class="lms-report-open">Open <i class="fe fe-arrow-right fe-12"></i></span>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card shadow-sm">
    <header class="lms-section-head">
      <div>
        <h3><i class="fe fe-info fe-16 mr-2"></i>How the books are kept</h3>
        <p>Nobody in operations types a debit. The ledger follows the work.</p>
      </div>
    </header>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm lms-table mb-0">
          <thead><tr><th>When this happens</th><th>The ledger records</th></tr></thead>
          <tbody>
            <tr><td>An invoice is issued</td><td><strong>Dr</strong> Accounts receivable &nbsp; <strong>Cr</strong> Freight revenue and VAT payable</td></tr>
            <tr><td>A payment is received</td><td><strong>Dr</strong> the bank, cash or mobile money account &nbsp; <strong>Cr</strong> Accounts receivable</td></tr>
            <tr><td>Fuel is bought</td><td><strong>Dr</strong> Fuel &nbsp; <strong>Cr</strong> Accounts payable</td></tr>
            <tr><td>An expense is approved</td><td><strong>Dr</strong> the category's account &nbsp; <strong>Cr</strong> whatever it was paid from</td></tr>
            <tr><td>A work order is completed</td><td><strong>Dr</strong> Vehicle maintenance &nbsp; <strong>Cr</strong> Accounts payable</td></tr>
            <tr><td>Goods are received</td><td><strong>Dr</strong> Inventory &nbsp; <strong>Cr</strong> Accounts payable</td></tr>
          </tbody>
        </table>
      </div>
      <p class="small text-muted mt-3 mb-0">
        A document that is cancelled has its entry removed again. Anything the ledger could not post is recorded in the
        <?php if (can_view('audit')): ?><a href="<?= url('audit', ['q' => 'gl.']) ?>">audit trail</a><?php else: ?>audit trail<?php endif; ?>
        rather than being lost.
      </p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
