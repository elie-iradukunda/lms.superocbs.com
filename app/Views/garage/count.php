<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $count */
use Models\Store;

[$label, $tone] = Store::COUNT_STATUS[$count['status']];
$editable = in_array($count['status'], ['draft', 'rejected'], true) && role_can('stock_counts', 'edit');
$number = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Store · stock count</p>
      <h2 class="lms-page-title"><i class="fe fe-check-square mr-2"></i><?= e($count['count_no']) ?> <span class="badge badge-<?= e($tone) ?> align-middle"><?= e($label) ?></span></h2>
      <p class="lms-page-sub"><?= e($count['warehouse_name'] ?? '') ?> · <?= e(human_date($count['counted_on'])) ?> · counted by <?= e($count['counted_name'] ?? '') ?><?= $count['decided_name'] ? ' · decided by ' . e($count['decided_name']) : '' ?></p>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url('stock_counts') ?>">Stock counts</a></div>
  </div>

  <?php if ($count['status'] === 'rejected' && $count['decision_note']): ?>
    <div class="alert alert-danger"><strong>Sent back:</strong> <?= e($count['decision_note']) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= url(['stock_counts', $count['id'], 'save']) ?>">
    <?= csrf_field() ?>
    <section class="card shadow-sm lms-section">
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Item</th><th class="text-right">System</th><th style="width:130px">Counted</th><th class="text-right">Difference</th><th>Why it differs</th></tr></thead>
        <tbody>
          <?php foreach ($count['lines'] as $line): ?>
            <?php $counted = $line['counted_quantity'] !== null ? (float) $line['counted_quantity'] : null; $difference = $counted === null ? null : $counted - (float) $line['system_quantity']; ?>
            <tr>
              <td><?= e($line['item_name']) ?><small class="d-block text-muted"><?= e($line['sku']) ?> · <?= e($line['unit_of_measure'] ?? '') ?></small></td>
              <td class="text-right"><?= e($number((float) $line['system_quantity'])) ?></td>
              <td><?php if ($editable): ?><input class="form-control form-control-sm text-right" type="number" step="0.01" min="0" name="counted[<?= (int) $line['id'] ?>]" value="<?= e($number($counted)) ?>"><?php else: ?><?= e($number($counted)) ?><?php endif; ?></td>
              <td class="text-right <?= $difference !== null && abs($difference) > 0.004 ? ($difference < 0 ? 'text-danger' : 'text-success') : '' ?>"><?= $difference === null ? '' : e(($difference > 0 ? '+' : '') . $number($difference)) ?></td>
              <td><?php if ($editable): ?><input class="form-control form-control-sm" name="reason[<?= (int) $line['id'] ?>]" value="<?= e((string) ($line['reason'] ?? '')) ?>"><?php else: ?><?= e((string) ($line['reason'] ?? '')) ?><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($editable): ?>
        <div class="card-footer d-flex justify-content-end" style="gap:8px">
          <button class="btn btn-outline-primary" type="submit">Save</button>
          <button class="btn btn-primary" type="submit" formaction="<?= url(['stock_counts', $count['id'], 'submit']) ?>">Submit for approval</button>
        </div>
      <?php endif; ?>
    </section>
  </form>

  <?php if ($count['status'] === 'submitted' && role_can('stock_counts', 'approve')): ?>
    <section class="card shadow-sm lms-section">
      <div class="card-body d-flex flex-wrap align-items-start" style="gap:8px">
        <form method="post" action="<?= url(['stock_counts', $count['id'], 'approve']) ?>"><?= csrf_field() ?><button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Approve and adjust stock</button></form>
        <form method="post" action="<?= url(['stock_counts', $count['id'], 'reject']) ?>" class="d-flex" style="gap:6px;flex:1;min-width:260px"><?= csrf_field() ?>
          <input class="form-control" name="reason_text" required placeholder="What to recount or explain">
          <button class="btn btn-outline-danger" type="submit">Send back</button>
        </form>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
