<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var list<array<string, mixed>> $counts */
/** @var array<int, string> $warehouses */
use Models\Store;
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Store · <?= e(current_company_name()) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-check-square mr-2"></i>Stock counts</h2>
      <p class="lms-page-sub">Count a store, explain every difference, and have it approved. Only then does the stock change and the difference post to the books.</p>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url('store_issues') ?>">Store</a></div>
  </div>

  <?php if (role_can('stock_counts', 'create') && $warehouses !== []): ?>
    <form method="post" action="<?= url('stock_counts') ?>" class="card shadow-sm mb-4">
      <?= csrf_field() ?>
      <div class="card-body row align-items-end">
        <div class="col-md-4 form-group mb-md-0"><label>Store to count</label><select class="form-control" name="warehouse_id" required><?php foreach ($warehouses as $id => $name): ?><option value="<?= (int) $id ?>"><?= e($name) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-5 form-group mb-md-0"><label>Notes</label><input class="form-control" name="notes" placeholder="Month-end count, October"></div>
        <div class="col-md-3"><button class="btn btn-primary btn-block" type="submit"><i class="fe fe-plus fe-12 mr-1"></i>Start a count</button></div>
      </div>
    </form>
  <?php endif; ?>

  <div class="card shadow-sm">
    <?php if ($counts === []): ?>
      <div class="card-body text-center text-muted py-5">No count yet.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Count</th><th>Store</th><th>Date</th><th>Differences</th><th>Counted by</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($counts as $count): ?>
            <?php [$label, $tone] = Store::COUNT_STATUS[$count['status']]; ?>
            <tr>
              <td><a href="<?= url(['stock_counts', $count['id']]) ?>"><strong><?= e($count['count_no']) ?></strong></a></td>
              <td><?= e($count['warehouse_name'] ?? '') ?></td>
              <td><?= e(human_date($count['counted_on'])) ?></td>
              <td><?= (int) $count['differences'] ?></td>
              <td><?= e($count['counted_name'] ?? '') ?></td>
              <td><span class="badge badge-<?= e($tone) ?>"><?= e($label) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
