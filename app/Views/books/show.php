<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$doc = $book['doc'];
$spec = \Models\Books::CATALOGUE[$book['key']];
$ranged = (bool) $spec['ranged'];
$needsAccount = in_array($book['key'], ['account_statement', 'general_ledger'], true);
$exportQuery = ['from' => $book['from'], 'to' => $book['to'], 'currency' => $book['currency']];
if ($book['account_id'] !== null) {
    $exportQuery['account_id'] = $book['account_id'];
}
// Nothing is converted, so each currency is its own set of books. The selector
// only appears once there is a second one to choose.
$currencies = $book['currencies'];
?>
<div class="container-fluid lms-list-page lms-book-page">

  <div class="lms-page-head fl-no-print">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb lms-crumbs">
          <li class="breadcrumb-item"><a href="<?= url('books') ?>">Accounting books</a></li>
          <li class="breadcrumb-item active"><?= e($book['label']) ?></li>
        </ol>
      </nav>
      <h2 class="lms-page-title"><i class="fe fe-<?= e($spec['icon']) ?> mr-2"></i><?= e($book['label']) ?></h2>
      <p class="lms-page-sub"><?= e($spec['description']) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-danger" href="<?= e(url(['books', 'export', $book['key'], 'pdf'], $exportQuery)) ?>"><i class="fe fe-file-text fe-12 mr-1"></i>PDF</a>
      <a class="btn btn-outline-success" href="<?= e(url(['books', 'export', $book['key'], 'xlsx'], $exportQuery)) ?>"><i class="fe fe-grid fe-12 mr-1"></i>Excel</a>
      <a class="btn btn-outline-secondary" href="<?= e(url(['books', 'export', $book['key'], 'csv'], $exportQuery)) ?>"><i class="fe fe-download fe-12 mr-1"></i>CSV</a>
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fe fe-printer fe-12 mr-1"></i>Print</button>
    </div>
  </div>

  <?php if (!$balanced): ?>
    <div class="alert alert-danger fl-no-print">
      <i class="fe fe-alert-octagon mr-2"></i>
      <strong>The ledger does not balance.</strong> Check the <a href="<?= url(['books', 'view', 'trial_balance']) ?>" class="alert-link">trial balance</a> before using these figures.
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">

      <form method="get" action="<?= url(['books', 'view', $book['key']]) ?>" class="lms-filter-bar fl-no-print">
        <?php if ($ranged): ?>
          <div class="form-group mb-0 mr-2">
            <label class="small text-muted mb-1 d-block" for="from">From</label>
            <input type="date" id="from" name="from" class="form-control" value="<?= e($book['from']) ?>">
          </div>
        <?php endif; ?>
        <div class="form-group mb-0 mr-2">
          <label class="small text-muted mb-1 d-block" for="to"><?= $ranged ? 'To' : 'As at' ?></label>
          <input type="date" id="to" name="to" class="form-control" value="<?= e($book['to']) ?>">
        </div>

        <?php if (count($currencies) > 1): ?>
          <div class="form-group mb-0 mr-2">
            <label class="small text-muted mb-1 d-block" for="currency">Currency</label>
            <select id="currency" name="currency" class="form-control">
              <?php foreach ($currencies as $code): ?>
                <option value="<?= e($code) ?>" <?= $book['currency'] === $code ? 'selected' : '' ?>><?= e($code) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="currency" value="<?= e($book['currency']) ?>">
        <?php endif; ?>

        <?php if ($needsAccount): ?>
          <div class="form-group mb-0 mr-2">
            <label class="small text-muted mb-1 d-block" for="account_id">Account</label>
            <select id="account_id" name="account_id" class="form-control lms-select">
              <option value=""><?= $book['key'] === 'general_ledger' ? 'Every account' : 'Choose an account' ?></option>
              <?php foreach ($accountOptions as $id => $label): ?>
                <option value="<?= (int) $id ?>" <?= $book['account_id'] === (int) $id ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="form-group mb-0 mr-2">
          <label class="small text-muted mb-1 d-block" for="jump">Book</label>
          <select id="jump" class="form-control" onchange="if(this.value){window.location=this.value;}">
            <?php foreach ($catalogue as $key => $other): ?>
              <option value="<?= e(url(['books', 'view', $key], ['from' => $book['from'], 'to' => $book['to']])) ?>" <?= $key === $book['key'] ? 'selected' : '' ?>><?= e($other['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn btn-primary align-self-end" type="submit">Run</button>

        <?php if ($ranged): ?>
          <?php foreach ([
              ['This month', date('Y-m-01'), date('Y-m-t')],
              ['This quarter', date('Y-m-01', (int) strtotime('-' . (((int) date('n') - 1) % 3) . ' months')), date('Y-m-d')],
              ['This year', date('Y-01-01'), date('Y-m-d')],
          ] as [$label, $f, $t]): ?>
            <a class="btn btn-link btn-sm align-self-end" href="<?= url(['books', 'view', $book['key']], ['from' => $f, 'to' => $t, 'currency' => $book['currency']] + ($book['account_id'] !== null ? ['account_id' => $book['account_id']] : [])) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        <?php endif; ?>
      </form>

      <div class="lms-book-head">
        <h3><?= e($doc['company']) ?></h3>
        <?php if (($doc['company_details'] ?? '') !== ''): ?><p class="lms-book-company"><?= e($doc['company_details']) ?></p><?php endif; ?>
        <h4><?= e($doc['title']) ?></h4>
        <?php if (($doc['period'] ?? '') !== ''): ?><p><?= e($doc['period']) ?></p><?php endif; ?>
        <?php if (($doc['basis'] ?? '') !== ''): ?><small><?= e($doc['basis']) ?></small><?php endif; ?>
      </div>

      <?php require __DIR__ . '/../partials/report_table.php'; ?>

      <?php if ($book['note'] !== ''): ?>
        <p class="small text-muted mt-3 mb-0"><i class="fe fe-info fe-12 mr-1"></i><?= e($book['note']) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
