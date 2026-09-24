<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid lms-form-page">

  <div class="lms-page-head">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb lms-crumbs">
          <li class="breadcrumb-item"><a href="<?= url('journal') ?>">Journal</a></li>
          <li class="breadcrumb-item active">New entry</li>
        </ol>
      </nav>
      <h2 class="lms-page-title"><i class="fe fe-book-open mr-2"></i>New journal entry</h2>
      <p class="lms-page-sub">For the corrections, accruals and adjustments no operational document produces. Everything else posts itself.</p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('journal') ?>">Cancel</a>
    </div>
  </div>

  <?php if ($errors !== []): ?>
    <div class="alert alert-danger lms-error-block">
      <strong><i class="fe fe-alert-circle mr-2"></i><?= count($errors) ?> thing<?= count($errors) === 1 ? '' : 's' ?> need<?= count($errors) === 1 ? 's' : '' ?> fixing before this can be posted</strong>
      <ul class="mb-0 mt-2"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url('journal') ?>" class="lms-form lms-journal-form">
    <?= csrf_field() ?>

    <section class="card shadow-sm lms-section">
      <header class="lms-section-head">
        <span class="lms-section-num">1</span>
        <div><h3><i class="fe fe-file-text fe-16 mr-2"></i>The entry</h3><p>The memo is what an auditor reads first, so say what happened, not just "adjustment".</p></div>
      </header>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 form-group lms-field is-required">
            <label for="entry_date">Entry date <span class="lms-required">*</span></label>
            <input class="form-control" type="date" id="entry_date" name="entry_date" value="<?= e($entry['entry_date']) ?>" required>
            <small class="form-text text-muted">A closed period will refuse the posting.</small>
          </div>
          <div class="col-md-6 form-group lms-field is-required">
            <label for="memo">Memo <span class="lms-required">*</span></label>
            <input class="form-control" type="text" id="memo" name="memo" value="<?= e($entry['memo']) ?>" required placeholder="Accrue September workshop invoice not yet received">
          </div>
          <div class="col-md-3 form-group lms-field">
            <label for="reference">Reference</label>
            <input class="form-control" type="text" id="reference" name="reference" value="<?= e($entry['reference']) ?>" placeholder="ADJ-09">
          </div>
        </div>
      </div>
    </section>

    <section class="card shadow-sm lms-section">
      <header class="lms-section-head">
        <span class="lms-section-num">2</span>
        <div><h3><i class="fe fe-list fe-16 mr-2"></i>Lines</h3><p>At least two lines, and the two columns must agree before this can be posted.</p></div>
      </header>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm lms-lines-table lms-journal-lines">
            <thead>
              <tr>
                <th style="width:34%">Account <span class="lms-required">*</span></th>
                <th style="width:34%">Description</th>
                <th style="width:14%" class="text-right">Debit</th>
                <th style="width:14%" class="text-right">Credit</th>
                <th style="width:4%"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lines as $index => $line): ?>
                <tr class="lms-line-row">
                  <td>
                    <select class="form-control form-control-sm" name="lines[<?= $index ?>][account_id]">
                      <option value="">Choose an account</option>
                      <?php foreach ($accountOptions as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= (string) ($line['account_id'] ?? '') === (string) $id ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input class="form-control form-control-sm" type="text" name="lines[<?= $index ?>][description]" value="<?= e($line['description'] ?? '') ?>"></td>
                  <td><input class="form-control form-control-sm text-right lms-jrn-debit" type="number" step="0.01" min="0" name="lines[<?= $index ?>][debit]" value="<?= e($line['debit'] ?? '') ?>"></td>
                  <td><input class="form-control form-control-sm text-right lms-jrn-credit" type="number" step="0.01" min="0" name="lines[<?= $index ?>][credit]" value="<?= e($line['credit'] ?? '') ?>"></td>
                  <td class="text-right align-middle"><button type="button" class="btn btn-link btn-sm text-danger p-0 lms-line-clear" title="Clear this line">&times;</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="lms-book-grand">
                <td colspan="2"><strong>TOTAL</strong></td>
                <td class="text-right lms-num"><strong id="jrnDebitTotal">0.00</strong></td>
                <td class="text-right lms-num"><strong id="jrnCreditTotal">0.00</strong></td>
                <td></td>
              </tr>
              <tr>
                <td colspan="5" class="text-right">
                  <span id="jrnBalance" class="badge badge-secondary">Enter the two sides</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary lms-jrn-add"><i class="fe fe-plus fe-12 mr-1"></i>Add line</button>
      </div>
    </section>

    <div class="lms-form-bar">
      <span class="small text-muted">An entry that does not balance is refused, on the page and again in the ledger.</span>
      <div>
        <a class="btn btn-light" href="<?= url('journal') ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i>Post entry</button>
      </div>
    </div>
  </form>
</div>

<script>
// Running totals, so the person typing sees the entry balance before they send
// it rather than after the server refuses it.
(function () {
  var form = document.querySelector('.lms-journal-form');
  if (!form) { return; }

  function money(n) { return n.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

  function recalc() {
    var debit = 0, credit = 0;
    form.querySelectorAll('.lms-jrn-debit').forEach(function (i) { debit += parseFloat(i.value) || 0; });
    form.querySelectorAll('.lms-jrn-credit').forEach(function (i) { credit += parseFloat(i.value) || 0; });

    document.getElementById('jrnDebitTotal').textContent = money(debit);
    document.getElementById('jrnCreditTotal').textContent = money(credit);

    var badge = document.getElementById('jrnBalance');
    var difference = Math.round((debit - credit) * 100) / 100;

    if (debit === 0 && credit === 0) {
      badge.className = 'badge badge-secondary';
      badge.textContent = 'Enter the two sides';
    } else if (difference === 0) {
      badge.className = 'badge badge-success';
      badge.textContent = 'Balanced';
    } else {
      badge.className = 'badge badge-danger';
      badge.textContent = 'Out of balance by ' + money(Math.abs(difference))
        + (difference > 0 ? ' — credits are short' : ' — debits are short');
    }
  }

  // Typing in one side clears the other: a line is one or the other, never both.
  form.addEventListener('input', function (event) {
    if (event.target.classList.contains('lms-jrn-debit') && event.target.value) {
      event.target.closest('tr').querySelector('.lms-jrn-credit').value = '';
    }
    if (event.target.classList.contains('lms-jrn-credit') && event.target.value) {
      event.target.closest('tr').querySelector('.lms-jrn-debit').value = '';
    }
    recalc();
  });

  form.addEventListener('click', function (event) {
    if (!event.target.closest('.lms-jrn-add')) { return; }
    var body = form.querySelector('tbody');
    var row = body.rows[body.rows.length - 1].cloneNode(true);
    var next = body.rows.length;
    row.querySelectorAll('select, input').forEach(function (field) {
      field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + next + ']');
      field.value = '';
    });
    body.appendChild(row);
  });

  recalc();
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
