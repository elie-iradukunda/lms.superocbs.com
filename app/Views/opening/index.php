<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var list<array<string, mixed>> $openings */
use Models\Currency;
use Models\OpeningBalances;
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Accounting · moving off Tally</p>
      <h2 class="lms-page-title"><i class="fe fe-log-in mr-2"></i>Opening balances — <?= e(current_company_name()) ?></h2>
      <p class="lms-page-sub">At the go-live date (best the first of a month), export from Tally the trial balance and the open customer and supplier balances, load them here, check the totals match Tally, and have the MD sign them off. From then on everything is entered in the LMS only.</p>
    </div>
  </div>

  <?php if (role_can('opening_balances', 'create')): ?>
    <form method="post" action="<?= url('opening_balances') ?>" enctype="multipart/form-data" class="card shadow-sm mb-4">
      <?= csrf_field() ?>
      <div class="card-header"><strong>Load from Tally</strong></div>
      <div class="card-body row">
        <div class="col-md-4 form-group"><label>What</label><select class="form-control" name="kind"><?php foreach (OpeningBalances::KINDS as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
          <small class="d-block mt-1">Templates: <?php foreach (array_keys(OpeningBalances::KINDS) as $key): ?><a class="mr-2" href="<?= url(['opening_balances', 'template', $key]) ?>"><?= e($key) ?>.csv</a><?php endforeach; ?></small></div>
        <div class="col-md-3 form-group"><label>Go-live date</label><input class="form-control" type="date" name="as_of" required value="<?= e(date('Y-m-01')) ?>"></div>
        <div class="col-md-5 form-group"><label>CSV file</label><input class="form-control-file" type="file" name="file" accept=".csv,text/csv"></div>
        <div class="col-md-12 form-group"><label>…or paste the rows (with the header row)</label><textarea class="form-control" name="pasted" rows="4" placeholder="account_code,currency,debit,credit,description"></textarea></div>
        <div class="col-md-12 form-group"><label>Notes</label><input class="form-control" name="notes"></div>
      </div>
      <div class="card-footer text-right"><button class="btn btn-primary" type="submit"><i class="fe fe-upload fe-12 mr-1"></i>Load</button></div>
    </form>
  <?php endif; ?>

  <?php if ($openings === []): ?>
    <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">Nothing loaded yet for <?= e(current_company_name()) ?>.</div></div>
  <?php endif; ?>
  <?php foreach ($openings as $opening): ?>
    <?php $totals = OpeningBalances::totals((string) $opening['kind'], $opening['rows']); ?>
    <div class="card shadow-sm mb-3" id="ob<?= (int) $opening['id'] ?>">
      <div class="card-header d-flex justify-content-between flex-wrap" style="gap:8px">
        <span><strong><?= e(OpeningBalances::KINDS[$opening['kind']]) ?></strong> as at <?= e(human_date($opening['as_of'])) ?> · <?= count($opening['rows']) ?> lines
          <span class="badge badge-<?= ['draft' => 'warning', 'checked' => 'info', 'signed' => 'success'][$opening['status']] ?>"><?= e(['draft' => 'Loaded, to check', 'checked' => 'Checked, to sign off', 'signed' => 'Signed off and posted'][$opening['status']]) ?></span></span>
        <span>
          <?php if ($opening['status'] === 'draft' && role_can('opening_balances', 'edit')): ?>
            <form method="post" action="<?= url(['opening_balances', $opening['id'], 'check']) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">The totals match Tally</button></form>
          <?php elseif ($opening['status'] === 'checked' && role_can('opening_balances', 'approve')): ?>
            <form method="post" action="<?= url(['opening_balances', $opening['id'], 'sign']) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-success" type="submit">Sign off and post</button></form>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-body small">
        <?php foreach ($totals as $code => $total): ?>
          <div><strong><?= e($code) ?>:</strong>
            <?php if ($opening['kind'] === 'ledger'): ?>debits <?= e(Currency::format($total['debit'], (string) $code)) ?> · credits <?= e(Currency::format($total['credit'], (string) $code)) ?><?= abs($total['debit'] - $total['credit']) > 0.004 ? ' · <span class="text-warning">difference ' . e(Currency::format($total['debit'] - $total['credit'], (string) $code)) . ' goes to Opening balance equity</span>' : ' · balanced' ?>
            <?php else: ?>open <?= e(Currency::format($total['debit'], (string) $code)) ?><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="text-muted mt-1">Loaded by <?= e($opening['prepared_name'] ?? '') ?><?= $opening['checked_name'] ? ' · checked by ' . e($opening['checked_name']) . ' ' . e(human_date($opening['checked_at'], true)) : '' ?><?= $opening['signed_name'] ? ' · signed off by ' . e($opening['signed_name']) . ' ' . e(human_date($opening['signed_at'], true)) : '' ?></div>
      </div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><?php foreach (OpeningBalances::COLUMNS[$opening['kind']] as $column): ?><th><?= e(ucfirst(str_replace('_', ' ', $column))) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach (array_slice($opening['rows'], 0, 50) as $row): ?><tr><?php foreach (OpeningBalances::COLUMNS[$opening['kind']] as $column): ?><td><?= e((string) ($row[$column] ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
      </table></div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
