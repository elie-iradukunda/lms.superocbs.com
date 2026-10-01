<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid lms-form-page">

  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Accounting</p>
      <h2 class="lms-page-title"><i class="fe fe-upload mr-2"></i>Import chart of accounts</h2>
      <p class="lms-page-sub">Bring your accounts, and optionally their opening balances, in from Excel instead of typing them one by one.</p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('accounts') ?>"><i class="fe fe-arrow-left fe-12 mr-1"></i>Chart of accounts</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger lms-error-block">
      <strong><i class="fe fe-alert-circle mr-2"></i>Nothing was imported. Fix <?= count($errors) === 1 ? 'this' : 'these ' . count($errors) . ' things' ?> in the file and upload it again:</strong>
      <ul class="mb-0 mt-2"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if (!empty($summary)): ?>
    <section class="card shadow-sm lms-section">
      <header class="lms-section-head"><div><span class="lms-section-num"><i class="fe fe-check fe-12"></i></span><div>
        <h3>Import finished</h3>
        <p><?= (int) $summary['added'] ?> added · <?= (int) $summary['updated'] ?> updated · <?= (int) $summary['skipped'] ?> left unchanged</p>
      </div></div></header>
      <div class="card-body">
        <?php if (!empty($summary['opening'])): $o = $summary['opening']; ?>
          <div class="alert alert-<?= $o['difference'] == 0 ? 'success' : 'warning' ?>">
            Opening balances posted as one journal entry dated <strong><?= e($o['date']) ?></strong>:
            debits <?= e(number_format($o['debits'], 2)) ?>, credits <?= e(number_format($o['credits'], 2)) ?>.
            <?php if ($o['difference'] != 0): ?>
              The file did not balance, so the difference of <strong><?= e(number_format(abs($o['difference']), 2)) ?></strong> was put to <strong>3100 Retained earnings</strong>. Check it with your accountant.
            <?php endif; ?>
            <a href="<?= url(['journal', $o['entry_id']]) ?>">Open the entry</a>.
          </div>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Code</th><th>Account</th><th>Result</th></tr></thead>
            <tbody>
              <?php foreach ($summary['lines'] as $line): ?>
                <tr><td><code><?= e($line['code']) ?></code></td><td><?= e($line['name']) ?></td><td><?= e($line['result']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <section class="card shadow-sm lms-section lms-form-single">
    <header class="lms-section-head"><div><span class="lms-section-num">1</span><div>
      <h3><i class="fe fe-download fe-16 mr-2"></i>Download the template</h3>
      <p>It already contains your current chart, in the exact layout the import reads. Add rows, change names, and fill the opening balance columns if you are moving from another system.</p>
    </div></div></header>
    <div class="card-body">
      <a class="btn btn-outline-primary" href="<?= url(['accounts', 'import', 'template']) ?>"><i class="fe fe-file fe-12 mr-1"></i>Download template (.xlsx)</a>
      <div class="table-responsive mt-3">
        <table class="table table-sm small mb-0">
          <thead><tr><th>Column</th><th>What to put in it</th></tr></thead>
          <tbody>
            <tr><td><strong>Account code</strong> *</td><td>Unique code, e.g. 6600. An existing code updates that account.</td></tr>
            <tr><td><strong>Account name</strong> *</td><td>e.g. Security and guarding</td></tr>
            <tr><td><strong>Type</strong> *</td><td>asset, liability, equity, income, cost_of_sales or expense</td></tr>
            <tr><td>Normal balance</td><td>debit or credit. Blank: worked out from the type.</td></tr>
            <tr><td>Statement section</td><td>Cash and bank, Accounts receivable, Other current assets, Fixed assets, Accounts payable, Other current liabilities, Long term liabilities, Equity, Income, Cost of sales, Operating expenses, Other income, Other expenses. Blank: worked out from the type.</td></tr>
            <tr><td>Section order</td><td>Number; lower prints first. Blank: the section's usual place.</td></tr>
            <tr><td>Parent code</td><td>The heading account it sits under (optional).</td></tr>
            <tr><td>Heading (yes/no)</td><td>yes for a heading that nothing posts to.</td></tr>
            <tr><td>Bank or cash (yes/no)</td><td>yes for bank accounts, cash and mobile money.</td></tr>
            <tr><td>Cash flow</td><td>operating, investing, financing, cash or none. Blank: worked out from the section.</td></tr>
            <tr><td>Description</td><td>What the account is for (optional).</td></tr>
            <tr><td>Opening debit / Opening credit</td><td>Balance brought forward, one side only (optional). Posted as one journal entry.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <header class="lms-section-head lms-section-head-divided"><div><span class="lms-section-num">2</span><div>
      <h3><i class="fe fe-upload fe-16 mr-2"></i>Upload the filled file</h3>
      <p>The whole file is checked first. If any row is wrong nothing is saved, and every problem is listed with its row number.</p>
    </div></div></header>
    <div class="card-body">
      <form method="post" action="<?= url(['accounts', 'import']) ?>" enctype="multipart/form-data" class="lms-form">
        <?= csrf_field() ?>
        <div class="row">
          <div class="col-md-6 form-group lms-field">
            <label for="import_file">Excel or CSV file <span class="lms-required">*</span></label>
            <input class="form-control" type="file" id="import_file" name="file" accept=".xlsx,.csv" required>
            <small class="form-text text-muted">.xlsx or .csv, up to 5 MB.</small>
          </div>
          <div class="col-md-3 form-group lms-field">
            <label for="opening_date">Opening balance date</label>
            <input class="form-control" type="date" id="opening_date" name="opening_date" value="<?= e(date('Y') . '-01-01') ?>">
            <small class="form-text text-muted">Only used when the file has opening balances.</small>
          </div>
          <div class="col-md-3 form-group lms-field">
            <label class="d-block">Existing accounts</label>
            <div class="custom-control custom-checkbox mt-2">
              <input class="custom-control-input" type="checkbox" id="update_existing" name="update_existing" value="1" checked>
              <label class="custom-control-label" for="update_existing">Update accounts already in the chart</label>
            </div>
          </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fe fe-upload fe-12 mr-1"></i>Import</button>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
