<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$cur = (string) $statement['currency'];
$amount = static fn ($value): string => number_format((float) $value, 2);
$open = $statement['status'] !== 'reconciled';
$canEdit = role_can('bank_statements', 'edit');
$s = $summary;
$unmatchedEntries = array_values(array_filter($bookEntries, static fn (array $e): bool => $e['matched_statement_id'] === null));
?>
<div class="container-fluid lms-form-page">

  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Bank reconciliation</p>
      <h2 class="lms-page-title"><i class="fe fe-check-square mr-2"></i><?= e($statement['statement_code']) ?> · <?= e($statement['account_code'] . ' ' . $statement['account_name']) ?></h2>
      <p class="lms-page-sub">
        Statement from <?= e($statement['period_from']) ?> to <?= e($statement['period_to']) ?>, in <?= e($cur) ?>.
        <?php if (!$open): ?>
          <span class="badge badge-success">Reconciled</span> by <?= e($statement['reconciled_by_name'] ?? 'someone') ?> on <?= e($statement['reconciled_at']) ?>.
        <?php endif; ?>
      </p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url(['bank_statements', $statement['id']]) ?>"><i class="fe fe-arrow-left fe-12 mr-1"></i>Statement</a>
      <?php if ($canEdit && $open): ?>
        <form method="post" action="<?= url(['bank_statements', $statement['id'], 'reconcile', 'auto']) ?>" class="d-inline">
          <?= csrf_field() ?>
          <button class="btn btn-outline-primary" type="submit"><i class="fe fe-zap fe-12 mr-1"></i>Match automatically</button>
        </form>
        <form method="post" action="<?= url(['bank_statements', $statement['id'], 'reconcile', 'complete']) ?>" class="d-inline">
          <?= csrf_field() ?>
          <button class="btn btn-success" type="submit" <?= abs($s['difference']) > 0.004 || $s['unmatched_bank'] > 0 ? 'disabled title="Clear the difference and the unmatched bank lines first"' : '' ?>><i class="fe fe-check fe-12 mr-1"></i>Mark reconciled</button>
        </form>
      <?php elseif ($canEdit): ?>
        <form method="post" action="<?= url(['bank_statements', $statement['id'], 'reconcile', 'reopen']) ?>" class="d-inline" onsubmit="return confirm('Reopen this reconciliation?');">
          <?= csrf_field() ?>
          <button class="btn btn-outline-danger" type="submit"><i class="fe fe-unlock fe-12 mr-1"></i>Reopen</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($importErrors)): ?>
    <div class="alert alert-danger"><strong>The file was not imported:</strong>
      <ul class="mb-0 mt-2"><?php foreach ($importErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <section class="card shadow-sm lms-section">
    <div class="card-body">
      <div class="row text-center">
        <div class="col-6 col-md-2 mb-2"><div class="small text-muted">Bank says (closing)</div><div class="h5 mb-0"><?= e($amount($s['closing'])) ?></div></div>
        <div class="col-6 col-md-2 mb-2"><div class="small text-muted">Books say</div><div class="h5 mb-0"><?= e($amount($s['ledger'])) ?></div></div>
        <div class="col-6 col-md-2 mb-2"><div class="small text-muted">In books, not at bank yet</div><div class="h5 mb-0"><?= e($amount($s['in_books_only'])) ?></div><div class="small text-muted"><?= (int) $s['unmatched_books'] ?> item(s)</div></div>
        <div class="col-6 col-md-2 mb-2"><div class="small text-muted">At bank, not in books</div><div class="h5 mb-0"><?= e($amount($s['at_bank_only'])) ?></div><div class="small text-muted"><?= (int) $s['unmatched_bank'] ?> line(s)</div></div>
        <div class="col-6 col-md-2 mb-2"><div class="small text-muted">Bank should show</div><div class="h5 mb-0"><?= e($amount($s['expected'])) ?></div></div>
        <div class="col-6 col-md-2 mb-2"><div class="small text-muted">Difference</div><div class="h5 mb-0 <?= abs($s['difference']) > 0.004 ? 'text-danger' : 'text-success' ?>"><?= e($amount($s['difference'])) ?></div></div>
      </div>
      <?php if (abs($s['opening_check']) > 0.004 && $bankLines !== []): ?>
        <div class="alert alert-warning mb-0 mt-2 small">
          Opening balance <?= e($amount($statement['opening_balance'])) ?> plus the lines (<?= e($amount($s['statement_total'])) ?>) does not give the closing balance — it is out by <?= e($amount($s['opening_check'])) ?>. A line is missing or typed wrong.
        </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="row">
    <div class="col-lg-7">
      <section class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-file-text fe-16 mr-2"></i>Bank statement lines</h3>
          <p>Money in is positive, money out negative. Match each line to the entry that recorded it, or post it to the books.</p></div></header>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Date</th><th>Description</th><th class="text-right">Amount</th><th>In the books as</th></tr></thead>
              <tbody>
                <?php if ($bankLines === []): ?>
                  <tr><td colspan="4" class="text-muted p-3">No lines yet. Import the bank's export below, or add lines on the statement page.</td></tr>
                <?php endif; ?>
                <?php foreach ($bankLines as $line): ?>
                  <tr>
                    <td class="text-nowrap"><?= e($line['line_date']) ?></td>
                    <td><?= e($line['description']) ?><?php if (!empty($line['reference'])): ?><div class="small text-muted"><?= e($line['reference']) ?></div><?php endif; ?></td>
                    <td class="text-right text-nowrap <?= (float) $line['amount'] < 0 ? 'text-danger' : 'text-success' ?>"><?= e($amount($line['amount'])) ?></td>
                    <td style="min-width:260px">
                      <?php if ($line['matched_entry_id'] !== null): ?>
                        <a href="<?= url(['journal', $line['matched_entry_id']]) ?>"><code><?= e($line['entry_no']) ?></code></a>
                        <span class="small text-muted"><?= e(mb_strimwidth((string) $line['memo'], 0, 40, '…')) ?></span>
                        <?php if ($canEdit && $open): ?>
                          <form method="post" action="<?= url(['bank_statements', $statement['id'], 'reconcile', 'unmatch']) ?>" class="d-inline">
                            <?= csrf_field() ?><input type="hidden" name="line_id" value="<?= (int) $line['id'] ?>">
                            <button class="btn btn-link btn-sm text-danger p-0 ml-1" type="submit" title="Remove this match">&times;</button>
                          </form>
                        <?php endif; ?>
                      <?php elseif ($canEdit && $open): ?>
                        <?php $candidates = array_filter($unmatchedEntries, static fn (array $e): bool => abs((float) $e['amount'] - (float) $line['amount']) < 0.005); ?>
                        <?php if ($candidates !== []): ?>
                          <form method="post" action="<?= url(['bank_statements', $statement['id'], 'reconcile', 'match']) ?>" class="form-inline mb-1">
                            <?= csrf_field() ?><input type="hidden" name="line_id" value="<?= (int) $line['id'] ?>">
                            <select name="entry_id" class="form-control form-control-sm mr-1" style="max-width:190px">
                              <?php foreach ($candidates as $entry): ?>
                                <option value="<?= (int) $entry['id'] ?>"><?= e($entry['entry_date'] . ' ' . $entry['entry_no'] . ' ' . mb_strimwidth((string) $entry['memo'], 0, 30, '…')) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Match</button>
                          </form>
                        <?php endif; ?>
                        <form method="post" action="<?= url(['bank_statements', $statement['id'], 'reconcile', 'post']) ?>" class="form-inline">
                          <?= csrf_field() ?><input type="hidden" name="line_id" value="<?= (int) $line['id'] ?>">
                          <select name="account_id" class="form-control form-control-sm mr-1" style="max-width:190px" required>
                            <option value="">Post to account…</option>
                            <?php foreach ($accounts as $accountId => $label): ?>
                              <?php if ((int) $accountId !== (int) $statement['account_id']): ?>
                                <option value="<?= (int) $accountId ?>"><?= e($label) ?></option>
                              <?php endif; ?>
                            <?php endforeach; ?>
                          </select>
                          <button class="btn btn-sm btn-outline-secondary" type="submit" title="Bank charges, interest, a transfer nobody recorded">Post</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted small">Not matched</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <?php if ($canEdit && $open): ?>
        <section class="card shadow-sm lms-section">
          <header class="lms-section-head"><div><h3><i class="fe fe-upload fe-16 mr-2"></i>Import the bank's export</h3>
            <p>Excel or CSV with a <strong>Date</strong> column, a <strong>Description</strong>, an optional <strong>Reference</strong>, and either one <strong>Amount</strong> column (negative for money out) or <strong>Credit</strong> and <strong>Debit</strong> columns. Dates like 05/10/2026 are read day first.</p></div></header>
          <div class="card-body">
            <form method="post" action="<?= url(['bank_statements', $statement['id'], 'reconcile', 'import']) ?>" enctype="multipart/form-data" class="form-inline">
              <?= csrf_field() ?>
              <input class="form-control mr-2 mb-2" type="file" name="file" accept=".xlsx,.csv" required>
              <button class="btn btn-primary mb-2" type="submit"><i class="fe fe-upload fe-12 mr-1"></i>Import lines</button>
            </form>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <div class="col-lg-5">
      <section class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-book fe-16 mr-2"></i>In the books, not matched yet</h3>
          <p>Receipts and payments recorded for this account that the bank has not shown. A cheque not yet cashed stays here until next month.</p></div></header>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Date</th><th>Entry</th><th class="text-right">Amount</th></tr></thead>
              <tbody>
                <?php if ($unmatchedEntries === []): ?>
                  <tr><td colspan="3" class="text-muted p-3">Everything in the books is matched.</td></tr>
                <?php endif; ?>
                <?php foreach ($unmatchedEntries as $entry): ?>
                  <tr>
                    <td class="text-nowrap"><?= e($entry['entry_date']) ?></td>
                    <td><a href="<?= url(['journal', $entry['id']]) ?>"><code><?= e($entry['entry_no']) ?></code></a>
                      <div class="small text-muted"><?= e(mb_strimwidth((string) $entry['memo'], 0, 60, '…')) ?></div></td>
                    <td class="text-right text-nowrap <?= (float) $entry['amount'] < 0 ? 'text-danger' : 'text-success' ?>"><?= e($amount($entry['amount'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
