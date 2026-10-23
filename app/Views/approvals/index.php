<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var list<array<string, mixed>> $waiting steps this role signs now
 * @var list<array<string, mixed>> $raised  what I raised that is waiting
 * @var list<array<string, mixed>> $decided what I signed or sent back lately
 */
$since = static fn (?string $at): string => $at ? time_ago($at) : '—';
$overdue = count(array_filter($waiting, static fn (array $s): bool => !empty($s['overdue'])));
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Approvals</p>
      <h2 class="lms-page-title"><i class="fe fe-check-square mr-2"></i>Waiting for you</h2>
      <p class="lms-page-sub">Each record waits here until your department signs its step. The next department is told the moment you sign; nothing moves on until every step is signed.</p>
    </div>
    <?php if (can_view('approval_policies')): ?>
      <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url('approval_policies') ?>"><i class="fe fe-sliders fe-12 mr-1"></i>Approval rules</a></div>
    <?php endif; ?>
  </div>

  <div class="lms-highlights">
    <div class="lms-highlight"><span class="lms-highlight-icon"><i class="fe fe-inbox fe-16"></i></span><div><small>Waiting for <?= e(role_label()) ?></small><strong><?= count($waiting) ?></strong></div></div>
    <div class="lms-highlight"><span class="lms-highlight-icon"><i class="fe fe-alert-triangle fe-16"></i></span><div><small>Past their time</small><strong class="<?= $overdue > 0 ? 'text-danger' : '' ?>"><?= $overdue ?></strong></div></div>
    <div class="lms-highlight"><span class="lms-highlight-icon"><i class="fe fe-send fe-16"></i></span><div><small>Raised by me, waiting</small><strong><?= count($raised) ?></strong></div></div>
  </div>

  <?php if (!empty($requests)): ?>
    <div class="card shadow-sm mb-4 border-warning">
      <div class="card-header"><strong><i class="fe fe-file-text fe-14 mr-1"></i>Requests waiting for you (<?= count($requests) ?>)</strong></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Request</th><th>What</th><th class="text-right">Amount</th><th>Your step</th><th>Waiting</th><th class="text-right"></th></tr></thead>
          <tbody>
          <?php foreach ($requests as $item): ?>
            <tr>
              <td><a href="<?= url(['form_requests', $item['id']]) ?>"><strong><?= e(\Models\RequestForms::label($item)) ?></strong></a><small class="d-block text-muted"><?= e($item['company_short'] ?? '') ?></small></td>
              <td><?= e($item['title']) ?><small class="d-block text-muted">Prepared by <?= e($item['prepared_name'] ?? '—') ?></small></td>
              <td class="text-right"><?= (int) $item['is_money'] === 1 ? e(\Models\Currency::format((float) $item['total_amount'], (string) $item['currency'])) : '—' ?></td>
              <td><span class="badge badge-warning"><?= e(\Models\RequestForms::STEP_LABELS[$item['step']] ?? $item['step']) ?></span></td>
              <td><?= e(time_ago($item['waiting_since'] ?? null)) ?></td>
              <td class="text-right text-nowrap">
                <?php if ($item['step'] === 'pay'): ?>
                  <a class="btn btn-sm btn-primary" href="<?= url('payment_batches') ?>">To pay</a>
                <?php else: ?>
                  <a class="btn btn-sm btn-primary" href="<?= url(['form_requests', $item['id']]) ?>">Open and sign</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($journals)): ?>
    <div class="card shadow-sm mb-4 border-warning">
      <div class="card-header"><strong><i class="fe fe-book-open fe-14 mr-1"></i>Manual journals waiting for you (<?= count($journals) ?>)</strong></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Journal</th><th>Memo</th><th class="text-right">Amount</th><th>Your step</th><th>Waiting</th><th class="text-right"></th></tr></thead>
          <tbody>
          <?php foreach ($journals as $journal): ?>
            <tr>
              <td><a href="<?= url(['journal', 'drafts', $journal['id']]) ?>"><strong><?= e($journal['draft_no']) ?></strong></a></td>
              <td><?= e($journal['memo']) ?><small class="d-block text-muted">Prepared by <?= e($journal['prepared_name'] ?? '—') ?></small></td>
              <td class="text-right"><?= e(\Models\Currency::format((float) $journal['total'], (string) $journal['currency'])) ?></td>
              <td><span class="badge badge-warning"><?= $journal['status'] === 'sent' ? 'Verify' : 'Authorize' ?></span></td>
              <td><?= e(time_ago($journal['status'] === 'sent' ? $journal['prepared_at'] : $journal['verified_at'])) ?></td>
              <td class="text-right"><a class="btn btn-sm btn-primary" href="<?= url(['journal', 'drafts', $journal['id']]) ?>">Open and sign</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Waiting for your signature</strong></div>
    <?php if ($waiting === []): ?>
      <div class="card-body text-center py-5 text-muted"><i class="fe fe-check-circle fe-32 d-block mb-2 text-success"></i>Nothing is waiting for you.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 lms-approval-table">
          <thead><tr><th>Record</th><th>Step</th><th>Amount</th><th>Raised by</th><th>Waiting</th><th>Decide by</th><th class="text-right">Decision</th></tr></thead>
          <tbody>
          <?php foreach ($waiting as $step): ?>
            <tr class="<?= !empty($step['overdue']) ? 'table-danger' : '' ?>">
              <td>
                <small class="text-muted d-block"><?= e($step['module_title']) ?></small>
                <a href="<?= e($step['url']) ?>"><strong><?= e($step['record_code']) ?></strong></a>
              </td>
              <td><span class="badge badge-pill badge-light">Step <?= (int) $step['position'] ?> of <?= (int) $step['total'] ?></span><div class="small"><?= e($step['step_label']) ?><?= current_role() === 'super_admin' ? ' · ' . e($step['role_name']) : '' ?></div></td>
              <td><?= $step['money'] !== '' ? e($step['money']) : '<span class="text-muted">—</span>' ?></td>
              <td><?= e($step['requester_name'] ?: '—') ?></td>
              <td><?= e($since($step['activated_at'])) ?></td>
              <td><?= $step['due_at'] ? e(human_date((string) $step['due_at'], true)) : '—' ?><?= !empty($step['overdue']) ? ' <span class="badge badge-danger">Late</span>' : '' ?></td>
              <td class="text-right text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e($step['url']) ?>"><i class="fe fe-eye fe-12 mr-1"></i>Open</a>
                <form method="post" action="<?= e($step['action_url']) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="back" value="approvals">
                  <button type="submit" class="btn btn-sm btn-success"><i class="fe fe-check fe-12 mr-1"></i><?= $step['action'] === 'verify' ? 'Verify' : 'Approve' ?></button>
                </form>
                <?php if ($step['reject_url'] !== null): ?>
                  <form method="post" action="<?= e($step['reject_url']) ?>" class="d-inline reason-form" data-reason-title="Send back <?= e($step['record_code']) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="approvals">
                    <input type="hidden" name="reason">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fe fe-x fe-12 mr-1"></i>Send back</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="row">
    <div class="col-lg-6">
      <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Raised by me, still waiting</strong></div>
        <?php if ($raised === []): ?>
          <div class="card-body text-muted small">Nothing you raised is waiting.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($raised as $step): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><a href="<?= e($step['url']) ?>"><strong><?= e($step['record_code']) ?></strong></a> <small class="text-muted"><?= e($step['module_title']) ?></small>
                  <span class="d-block small">At step <?= (int) $step['position'] ?> of <?= (int) $step['total'] ?>: <?= e($step['step_label']) ?> (<?= e($step['role_name']) ?>)</span></span>
                <small class="text-muted"><?= e($since($step['activated_at'])) ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Signed by me lately</strong></div>
        <?php if ($decided === []): ?>
          <div class="card-body text-muted small">You have not signed anything yet.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($decided as $step): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><a href="<?= e($step['url']) ?>"><strong><?= e($step['record_code']) ?></strong></a> <small class="text-muted"><?= e($step['step_label']) ?></small>
                  <?php if (!empty($step['comment'])): ?><span class="d-block small text-muted">“<?= e($step['comment']) ?>”</span><?php endif; ?></span>
                <span><span class="badge badge-pill badge-<?= $step['status'] === 'approved' ? 'success' : 'danger' ?>"><?= $step['status'] === 'approved' ? 'Signed' : 'Sent back' ?></span>
                  <small class="text-muted d-block text-right"><?= e($since($step['decided_at'])) ?></small></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
