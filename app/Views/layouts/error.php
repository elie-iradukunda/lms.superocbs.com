<?php require __DIR__ . '/header.php'; ?>
<section class="empty-state"><span class="empty-kicker">404</span><h2><?= htmlspecialchars($title) ?></h2><p><?= htmlspecialchars($message) ?></p><a class="button button-primary" href="<?= url('dashboard') ?>">Back to dashboard</a></section>
<?php require __DIR__ . '/footer.php'; ?>
