<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $request @var list<array<string, mixed>> $users @var list<?string> $photos */
use Models\Currency;
use Models\DriverSupport;
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Driver support · <?= e(DriverSupport::TYPES[$request['support_type']] ?? '') ?> · <?= e(ucfirst((string) $request['urgency'])) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-life-buoy mr-2"></i><?= e($request['request_code']) ?></h2>
      <p class="lms-page-sub"><?= e($request['driver_name']) ?><?= $request['trip_code'] ? ' · ' . e($request['trip_code']) . ' (' . e($request['plate_number']) . ')' : '' ?> · <?= e(human_date($request['created_at'], true)) ?></p>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url(['support_requests', $request['id']]) ?>">Back</a></div>
  </div>

  <div class="card shadow-sm mb-3"><div class="card-body">
    <p class="mb-2"><?= nl2br(e($request['details'])) ?></p>
    <?php if ((float) ($request['amount_requested'] ?? 0) > 0): ?><p class="mb-2"><strong>Needs <?= e(Currency::format((float) $request['amount_requested'], (string) $request['currency'])) ?></strong></p><?php endif; ?>
    <?php if ($request['location'] || $request['latitude']): ?><p class="mb-2 small"><i class="fe fe-map-pin mr-1"></i><?= e($request['location'] ?? '') ?><?php if ($request['latitude']): ?> · <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= e($request['latitude'] . ',' . $request['longitude']) ?>">On the map</a><?php endif; ?></p><?php endif; ?>
    <div class="d-flex flex-wrap" style="gap:8px"><?php foreach (array_filter($photos) as $photo): ?><a target="_blank" rel="noopener" href="<?= e($photo) ?>"><img src="<?= e($photo) ?>" alt="" style="height:90px;border-radius:6px"></a><?php endforeach; ?></div>
  </div></div>

  <?php if ($request['status'] === 'open'): ?>
    <form method="post" action="<?= url(['support_requests', $request['id'], 'decide']) ?>" class="card shadow-sm">
      <?= csrf_field() ?>
      <div class="card-header"><strong>Your decision</strong></div>
      <div class="card-body row">
        <div class="col-md-4 form-group"><label>Forward to</label><select class="form-control" name="forward_role"><?php foreach (DriverSupport::FORWARD_TO as $key => $label): ?><option value="<?= e($key) ?>" <?= ($request['support_type'] === 'money' && $key === 'finance') || ($request['support_type'] === 'breakdown' && $key === 'warehouse_manager') || ($request['support_type'] === 'accident' && $key === 'management') ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 form-group"><label>A named person (optional)</label><select class="form-control" name="assignee"><option value="">Everyone in that department</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['full_name'] . ' — ' . $user['role_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 form-group"><label>Note (the reason, when you reject)</label><input class="form-control" name="note"></div>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <button class="btn btn-outline-danger" type="submit" name="decision" value="reject"><i class="fe fe-x fe-12 mr-1"></i>Reject</button>
        <button class="btn btn-success" type="submit" name="decision" value="approve"><i class="fe fe-check fe-12 mr-1"></i>Approve and forward<?= (float) ($request['amount_requested'] ?? 0) > 0 ? ' (money goes as an F10)' : '' ?></button>
      </div>
    </form>
  <?php elseif ($request['status'] === 'in_progress'): ?>
    <form method="post" action="<?= url(['support_requests', $request['id'], 'decide']) ?>" class="card shadow-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="decision" value="resolve">
      <div class="card-header"><strong>Mark it resolved</strong></div>
      <div class="card-body"><label>What was done</label><textarea class="form-control" name="note" rows="3" required></textarea></div>
      <div class="card-footer text-right"><button class="btn btn-success" type="submit">Resolved — tell the driver</button></div>
    </form>
  <?php else: ?>
    <div class="alert alert-light">This request is <?= e($request['status']) ?>.</div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
