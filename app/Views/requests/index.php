<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var list<array<string, mixed>> $requests
 * @var list<array<string, mixed>> $inbox     requests waiting for me
 * @var array<string, string> $forms
 * @var array<string, string> $startable    forms I may start here
 */
use Models\Currency;
use Models\RequestForms;

$statusTone = ['draft' => 'secondary', 'sent' => 'warning', 'verified' => 'info', 'authorized' => 'primary', 'partly_paid' => 'primary', 'paid' => 'success', 'returned' => 'danger', 'cancelled' => 'secondary', 'closed' => 'success'];
$filters = array_intersect_key($_GET, array_flip(['form', 'status', 'currency', 'q']));
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Requests and approvals</p>
      <h2 class="lms-page-title"><i class="fe fe-file-text mr-2"></i>Requests (F1–F15)</h2>
      <p class="lms-page-sub">Every money request follows one chain: Prepared → Verified by Finance → Authorized by the MD with the stamp → Paid by the Cashier.</p>
    </div>
    <div class="lms-page-actions">
      <?php if (can_view('trip_planner') && role_can('trip_planner', 'create')): ?>
        <a class="btn btn-outline-primary" href="<?= url('trip_planner') ?>"><i class="fe fe-navigation fe-12 mr-1"></i>New trip (creates F1)</a>
      <?php endif; ?>
      <?php if ($startable !== [] && can_create('form_requests')): ?>
        <div class="dropdown d-inline-block">
          <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown"><i class="fe fe-plus fe-12 mr-1"></i>New request</button>
          <div class="dropdown-menu dropdown-menu-right">
            <?php foreach ($startable as $code => $label): ?>
              <a class="dropdown-item" href="<?= e(url(['form_requests', 'create'], ['form' => $code])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($inbox !== []): ?>
    <div class="card shadow-sm mb-4 border-warning">
      <div class="card-header"><strong><i class="fe fe-inbox fe-14 mr-1"></i>Waiting for you (<?= count($inbox) ?>)</strong></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Request</th><th>What</th><th class="text-right">Amount</th><th>Your step</th><th>Waiting</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($inbox as $item): ?>
            <tr>
              <td><a href="<?= url(['form_requests', $item['id']]) ?>"><strong><?= e(RequestForms::label($item)) ?></strong></a><small class="d-block text-muted"><?= e($item['company_short'] ?? '') ?></small></td>
              <td><?= e($item['title']) ?><small class="d-block text-muted">Prepared by <?= e($item['prepared_name'] ?? '—') ?></small></td>
              <td class="text-right lms-num"><?= (int) $item['is_money'] === 1 ? e(Currency::format((float) $item['total_amount'], (string) $item['currency'])) : '—' ?></td>
              <td><span class="badge badge-warning"><?= e(RequestForms::STEP_LABELS[$item['step']] ?? $item['step']) ?></span></td>
              <td><?= e(time_ago($item['waiting_since'] ?? null)) ?></td>
              <td class="text-right"><a class="btn btn-sm btn-primary" href="<?= url(['form_requests', $item['id']]) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="get" action="<?= url('form_requests') ?>" class="lms-filter-bar">
        <div class="lms-filter-search"><i class="fe fe-search fe-16"></i><input type="search" name="q" class="form-control" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search number or subject..."></div>
        <select name="form" class="form-control lms-filter-select" onchange="this.form.submit()">
          <option value="">All forms</option>
          <?php foreach ($forms as $code => $label): ?><option value="<?= e($code) ?>" <?= ($_GET['form'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <select name="status" class="form-control lms-filter-select" onchange="this.form.submit()">
          <option value="">Every status</option>
          <?php foreach (RequestForms::STATUS as $key => $label): ?><option value="<?= e($key) ?>" <?= ($_GET['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <select name="currency" class="form-control lms-filter-select lms-filter-narrow" onchange="this.form.submit()">
          <option value="">Every currency</option>
          <?php foreach (Currency::options() as $code => $label): ?><option value="<?= e($code) ?>" <?= ($_GET['currency'] ?? '') === $code ? 'selected' : '' ?>><?= e($code) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary" type="submit">Apply</button>
        <?php if ($filters !== []): ?><a class="btn btn-link text-muted" href="<?= url('form_requests') ?>">Clear</a><?php endif; ?>
      </form>

      <?php
      // Money per currency, never added across currencies.
      $totals = [];
      foreach ($requests as $row) {
          if ((int) $row['is_money'] === 1 && !in_array($row['status'], ['cancelled', 'draft'], true)) {
              $totals[$row['currency']] = ($totals[$row['currency']] ?? 0) + (float) $row['total_amount'];
          }
      }
      ?>
      <p class="lms-result-count"><?= count($requests) ?> request<?= count($requests) === 1 ? '' : 's' ?><?php foreach ($totals as $code => $sum): ?> · <strong><?= e(Currency::format($sum, (string) $code)) ?></strong><?php endforeach; ?></p>

      <div class="table-responsive">
        <table class="table table-hover lms-table">
          <thead><tr><th>Number</th><th>Form</th><th>Subject</th><th>Company</th><th>Prepared by</th><th>Cur.</th><th class="text-right">Amount</th><th>Status</th><th class="text-right"></th></tr></thead>
          <tbody>
          <?php if ($requests === []): ?>
            <tr><td colspan="9" class="lms-empty"><i class="fe fe-inbox fe-32"></i><p class="mb-1"><strong>No requests yet</strong></p><p class="text-muted small">A trip creates its F1; the other forms start from "New request".</p></td></tr>
          <?php endif; ?>
          <?php foreach ($requests as $row): ?>
            <tr>
              <td><a class="lms-row-link" href="<?= url(['form_requests', $row['id']]) ?>"><strong><?= e($row['request_no'] ?: 'Draft #' . $row['id']) ?></strong></a></td>
              <td><span class="badge badge-light"><?= e($row['form_code']) ?></span></td>
              <td><?= e($row['title']) ?></td>
              <td><?= e($row['company'] ?? '') ?></td>
              <td><?= e($row['prepared_name'] ?? '—') ?><small class="d-block text-muted"><?= e(human_date($row['prepared_at'] ?? $row['created_at'])) ?></small></td>
              <td><?= e($row['currency']) ?></td>
              <td class="text-right lms-num"><?= (int) $row['is_money'] === 1 ? e(number_format((float) $row['total_amount'])) : ($row['total_quantity'] ? e(rtrim(rtrim(number_format((float) $row['total_quantity'], 2), '0'), '.')) . ' L' : '—') ?></td>
              <td><span class="badge badge-<?= e($statusTone[$row['status']] ?? 'secondary') ?>"><?= e(RequestForms::STATUS[$row['status']] ?? $row['status']) ?></span></td>
              <td class="text-right text-nowrap">
                <a class="btn btn-sm btn-link" href="<?= url(['form_requests', $row['id']]) ?>" title="Open"><i class="fe fe-eye fe-16"></i></a>
                <?php if (!in_array($row['status'], ['draft'], true)): ?><a class="btn btn-sm btn-link" target="_blank" rel="noopener" href="<?= url(['form_requests', $row['id'], 'pdf']) ?>" title="PDF"><i class="fe fe-printer fe-16"></i></a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
