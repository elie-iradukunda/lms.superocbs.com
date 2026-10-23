<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var bool $unreadOnly
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $unread
 */
$dot = static fn (string $severity): string => in_array($severity, ['danger', 'warning', 'success'], true) ? $severity : 'primary';
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Overview</p>
      <h2 class="lms-page-title"><i class="fe fe-bell mr-2"></i>Updates</h2>
      <p class="lms-page-sub">Everything the system told you: approvals to sign, trucks arriving, papers sent back, clearances moving, alerts from the clock. New ones also pop up in the corner of any page you have open.</p>
    </div>
    <div class="lms-page-actions">
      <button type="button" class="btn btn-outline-secondary" data-desktop-alerts><i class="fe fe-monitor fe-12 mr-1"></i>Also show them on my desktop</button>
      <?php if ($unread > 0): ?>
        <form method="post" action="<?= url(['notifications', 'read-all']) ?>" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="redirect" value="notifications">
          <button type="submit" class="btn btn-primary"><i class="fe fe-check fe-12 mr-1"></i>Mark all <?= (int) $unread ?> as read</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= !$unreadOnly ? ' active' : '' ?>" href="<?= url('notifications') ?>">All <span class="badge badge-light ml-1"><?= $unreadOnly ? '' : (int) $total ?></span></a></li>
    <li class="nav-item"><a class="nav-link<?= $unreadOnly ? ' active' : '' ?>" href="<?= url('notifications', ['show' => 'unread']) ?>">Unread <span class="badge badge-light ml-1"><?= (int) $unread ?></span></a></li>
  </ul>

  <div class="card shadow-sm">
    <?php if ($rows === []): ?>
      <div class="card-body text-center py-5 text-muted"><i class="fe fe-bell-off fe-32 d-block mb-2"></i>You're all caught up.</div>
    <?php else: ?>
      <ul class="list-group list-group-flush lms-updates">
        <?php foreach ($rows as $row): ?>
          <?php $link = \Controllers\NotificationController::link($row); ?>
          <li class="list-group-item d-flex align-items-start<?= empty($row['is_read']) ? ' is-unread' : '' ?>">
            <i class="lms-notify-dot lms-notify-dot-<?= $dot((string) $row['severity']) ?> mt-2 mr-3"></i>
            <div class="flex-fill">
              <div class="d-flex justify-content-between flex-wrap" style="gap:8px">
                <strong><?= e($row['title']) ?></strong>
                <small class="text-muted" title="<?= e(human_date((string) $row['created_at'], true)) ?>"><?= e(time_ago((string) $row['created_at'])) ?></small>
              </div>
              <div class="small"><?= e($row['message']) ?></div>
            </div>
            <div class="ml-3 text-nowrap">
              <?php if ($link !== ''): ?><a class="btn btn-sm btn-outline-primary" href="<?= e($link) ?>">Open</a><?php endif; ?>
              <?php if (empty($row['is_read'])): ?>
                <form method="post" action="<?= url(['notifications', 'read']) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="notification_key" value="<?= e($row['notification_key']) ?>">
                  <input type="hidden" name="redirect" value="notifications">
                  <button type="submit" class="btn btn-sm btn-light" title="Mark as read"><i class="fe fe-check"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="mt-3"><ul class="pagination">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <li class="page-item<?= $p === $page ? ' active' : '' ?>"><a class="page-link" href="<?= url('notifications', array_filter(['show' => $unreadOnly ? 'unread' : null, 'page' => $p])) ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
