<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$query = array_intersect_key($_GET, array_flip(['q', 'status', 'category']));
$statuses = ['sent' => 'Sent', 'queued' => 'Queued', 'failed' => 'Failed', 'skipped' => 'Not sent'];
$categories = [
    'notification' => 'Notification', 'alert' => 'Operational alert', 'password_reset' => 'Password reset',
    'new_account' => 'New account', 'invoice' => 'Invoice', 'manual' => 'Manual', 'test' => 'Test',
];
$tone = ['sent' => 'success', 'queued' => 'warning', 'failed' => 'danger', 'skipped' => 'secondary'];
?>
<div class="container-fluid lms-list-page">

  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Administration</p>
      <h2 class="lms-page-title"><i class="fe fe-mail mr-2"></i>Email outbox</h2>
      <p class="lms-page-sub">Every message the system meant to send, and what happened to it. A message is written here first and only then handed to the provider, so nothing is lost when mail is off.</p>
    </div>
    <?php if ($canSend): ?>
      <div class="lms-page-actions">
        <form method="post" action="<?= url(['email', 'test']) ?>" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="to" value="<?= e(current_user_email()) ?>">
          <button type="submit" class="btn btn-outline-secondary"><i class="fe fe-send fe-12 mr-1"></i>Send a test to myself</button>
        </form>
        <form method="post" action="<?= url(['email', 'flush']) ?>" class="d-inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><i class="fe fe-refresh-cw fe-12 mr-1"></i>Send everything waiting</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$configured): ?>
    <div class="alert alert-danger">
      <i class="fe fe-alert-octagon mr-2"></i>
      <strong>No mail provider is configured.</strong> Messages are still recorded here, but none are sent.
      Put <code>RESEND_API_KEY</code> in the <code>.env</code> file at the project root and reload the page.
    </div>
  <?php elseif (!$switchedOn): ?>
    <div class="alert alert-warning">
      <i class="fe fe-bell-off mr-2"></i>
      <strong>Email notifications are switched off.</strong> Turn <em>Send email notifications</em> back on under
      <a href="<?= url('settings') ?>" class="alert-link">Company settings</a>.
    </div>
  <?php endif; ?>

  <?php if ($redirect !== ''): ?>
    <div class="alert alert-warning">
      <i class="fe fe-corner-down-right mr-2"></i>
      <strong>Test mode.</strong> Every message is being redirected to <strong><?= e($redirect) ?></strong>
      instead of its real recipient. Clear <code>MAIL_REDIRECT_ALL_TO</code> in <code>.env</code> before going live.
    </div>
  <?php endif; ?>

  <div class="lms-highlights">
    <?php foreach ($statuses as $key => $label): ?>
      <div class="lms-highlight">
        <span class="lms-highlight-icon"><i class="fe fe-<?= $key === 'sent' ? 'check' : ($key === 'failed' ? 'x' : 'clock') ?> fe-16"></i></span>
        <div><small><?= e($label) ?></small><strong><?= number_format((int) ($counts[$key] ?? 0)) ?></strong></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="get" action="<?= url('email') ?>" class="lms-filter-bar">
        <div class="lms-filter-search">
          <i class="fe fe-search fe-16"></i>
          <input type="search" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search subject, address, reference or error...">
        </div>
        <select name="status" class="form-control lms-filter-select">
          <option value="">Any status</option>
          <?php foreach ($statuses as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="category" class="form-control lms-filter-select">
          <option value="">Any kind</option>
          <?php foreach ($categories as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary" type="submit">Apply</button>
        <?php if ($query !== []): ?><a class="btn btn-link text-muted" href="<?= url('email') ?>">Clear</a><?php endif; ?>
      </form>

      <p class="lms-result-count"><strong><?= number_format($total) ?></strong> message<?= $total === 1 ? '' : 's' ?></p>

      <div class="table-responsive">
        <table class="table table-hover lms-table">
          <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Kind</th><th>Status</th><th>Detail</th><th class="text-right">Open</th></tr></thead>
          <tbody>
            <?php if ($messages === []): ?>
              <tr><td colspan="7" class="lms-empty">
                <i class="fe fe-inbox fe-32"></i>
                <p class="mb-1"><strong>Nothing here yet</strong></p>
                <p class="text-muted small mb-0">Approve something, or send a test message, and it will appear.</p>
              </td></tr>
            <?php endif; ?>

            <?php foreach ($messages as $message): ?>
              <tr>
                <td class="text-nowrap">
                  <?= e(date('d M H:i', (int) strtotime((string) $message['created_at']))) ?>
                  <small class="d-block text-muted"><?= e(time_ago((string) $message['created_at'])) ?></small>
                </td>
                <td>
                  <?= e($message['to_email']) ?>
                  <?php if (!empty($message['to_name'])): ?><small class="d-block text-muted"><?= e($message['to_name']) ?></small><?php endif; ?>
                </td>
                <td><a href="<?= url(['email', $message['id']]) ?>" class="lms-row-link"><strong><?= e($message['subject']) ?></strong></a></td>
                <td><?= e($categories[(string) $message['category']] ?? ucfirst((string) $message['category'])) ?></td>
                <td><span class="badge badge-<?= e($tone[(string) $message['status']] ?? 'secondary') ?>"><?= e($statuses[(string) $message['status']] ?? ucfirst((string) $message['status'])) ?></span></td>
                <td class="small text-muted"><?= $message['error'] ? e($message['error']) : ($message['provider_id'] ? e($message['provider_id']) : '&mdash;') ?></td>
                <td class="text-right"><a href="<?= url(['email', $message['id']]) ?>" class="btn btn-sm btn-link"><i class="fe fe-eye fe-16"></i></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="lms-pagination">
          <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e(url('email', $query + ['page' => max(1, $page - 1)])) ?>">Previous</a>
          <span class="small text-muted">Page <?= $page ?> of <?= $pages ?></span>
          <a class="btn btn-sm btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= e(url('email', $query + ['page' => min($pages, $page + 1)])) ?>">Next</a>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
