<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $journal */
/** @var string|null $step */
/** @var array<int|string, string> $accountOptions */
use Models\Currency;
use Models\ManualJournals;

[$statusLabel, $tone] = ManualJournals::STATUS[$journal['status']];
$currency = (string) $journal['currency'];
$debits = array_sum(array_map(static fn (array $line): float => (float) $line['debit'], $journal['lines']));
$credits = array_sum(array_map(static fn (array $line): float => (float) $line['credit'], $journal['lines']));
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Accounting · manual journal</p>
      <h2 class="lms-page-title"><i class="fe fe-book-open mr-2"></i><?= e($journal['draft_no']) ?> <span class="badge badge-<?= e($tone) ?> align-middle"><?= e($statusLabel) ?></span></h2>
      <p class="lms-page-sub"><?= e($journal['memo']) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('journal') ?>">Journal</a>
      <?php if (!empty($journal['entry_id'])): ?>
        <a class="btn btn-primary" href="<?= url(['journal', $journal['entry_id']]) ?>"><i class="fe fe-book fe-12 mr-1"></i>Posted entry</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($journal['status'] === 'refused'): ?>
    <div class="alert alert-danger"><strong>Refused by <?= e($journal['refused_name'] ?? '') ?></strong> <?= e(human_date($journal['refused_at'], true)) ?>: <?= e($journal['refused_reason']) ?></div>
  <?php endif; ?>

  <section class="card shadow-sm lms-section">
    <div class="card-body">
      <div class="row small">
        <div class="col-md-3"><span class="text-muted d-block">Entry date</span><strong><?= e(human_date($journal['entry_date'])) ?></strong></div>
        <div class="col-md-3"><span class="text-muted d-block">Currency</span><strong><?= e($currency) ?></strong></div>
        <div class="col-md-3"><span class="text-muted d-block">Reference</span><strong><?= e($journal['reference'] ?: '—') ?></strong></div>
        <div class="col-md-3"><span class="text-muted d-block">Amount</span><strong><?= e(Currency::format((float) $journal['total'], $currency)) ?></strong></div>
      </div>
    </div>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead><tr><th>Account</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
      <tbody>
        <?php foreach ($journal['lines'] as $line): ?>
          <tr>
            <td><?= e($accountOptions[(int) $line['account']] ?? ('#' . (int) $line['account'])) ?></td>
            <td><?= e((string) ($line['description'] ?? '')) ?></td>
            <td class="text-right"><?= (float) $line['debit'] > 0 ? e(number_format((float) $line['debit'], 2)) : '' ?></td>
            <td class="text-right"><?= (float) $line['credit'] > 0 ? e(number_format((float) $line['credit'], 2)) : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr class="font-weight-bold"><td colspan="2">Total</td><td class="text-right"><?= e(number_format($debits, 2)) ?></td><td class="text-right"><?= e(number_format($credits, 2)) ?></td></tr></tfoot>
    </table></div>
  </section>

  <section class="card shadow-sm lms-section">
    <div class="card-header"><strong>Signatures</strong></div>
    <div class="card-body">
      <div class="row small">
        <div class="col-md-4"><span class="text-muted d-block">Prepared</span><?= e($journal['prepared_name'] ?? '') ?><br><?= e(human_date($journal['prepared_at'], true)) ?></div>
        <div class="col-md-4"><span class="text-muted d-block">Verified (Finance)</span><?= $journal['verified_by'] ? e($journal['verified_name']) . '<br>' . e(human_date($journal['verified_at'], true)) : '<span class="text-muted">waiting</span>' ?></div>
        <div class="col-md-4"><span class="text-muted d-block">Authorized (MD)</span><?= $journal['authorized_by'] ? e($journal['authorized_name']) . '<br>' . e(human_date($journal['authorized_at'], true)) : '<span class="text-muted">waiting</span>' ?></div>
      </div>
    </div>
    <?php if ($step !== null || (in_array($journal['status'], ['sent', 'verified'], true) && (int) $journal['prepared_by'] === (int) current_user_id())): ?>
      <div class="card-footer d-flex flex-wrap align-items-start" style="gap:8px">
        <?php if ($step !== null): ?>
          <form method="post" action="<?= url(['journal', 'drafts', $journal['id'], $step]) ?>"><?= csrf_field() ?>
            <button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i><?= $step === 'verify' ? 'Verify' : 'Authorize and post' ?></button>
          </form>
          <form method="post" action="<?= url(['journal', 'drafts', $journal['id'], 'refuse']) ?>" class="d-flex" style="gap:6px"><?= csrf_field() ?>
            <input class="form-control" name="reason" required placeholder="Why it is refused">
            <button class="btn btn-outline-danger" type="submit">Refuse</button>
          </form>
        <?php endif; ?>
        <?php if (in_array($journal['status'], ['sent', 'verified'], true) && (int) $journal['prepared_by'] === (int) current_user_id()): ?>
          <form method="post" action="<?= url(['journal', 'drafts', $journal['id'], 'cancel']) ?>" class="ml-auto"><?= csrf_field() ?>
            <button class="btn btn-outline-secondary" type="submit">Withdraw</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
