<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$tone = ['sent' => 'success', 'queued' => 'warning', 'failed' => 'danger', 'skipped' => 'secondary'];
$statusLabel = ['sent' => 'Sent', 'queued' => 'Queued', 'failed' => 'Failed', 'skipped' => 'Not sent'];
$status = (string) $message['status'];
?>
<div class="container-fluid lms-detail-page">

  <div class="lms-page-head">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb lms-crumbs">
          <li class="breadcrumb-item"><a href="<?= url('email') ?>">Email outbox</a></li>
          <li class="breadcrumb-item active">Message</li>
        </ol>
      </nav>
      <h2 class="lms-page-title">
        <i class="fe fe-mail mr-2"></i><?= e($message['subject']) ?>
        <span class="badge badge-<?= e($tone[$status] ?? 'secondary') ?> lms-status-badge"><?= e($statusLabel[$status] ?? ucfirst($status)) ?></span>
      </h2>
      <p class="lms-page-sub">To <?= e($message['to_email']) ?><?= $message['to_name'] ? ' (' . e($message['to_name']) . ')' : '' ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('email') ?>"><i class="fe fe-arrow-left fe-12 mr-1"></i>Back</a>
      <?php if ($status !== 'sent' && can_edit('email')): ?>
        <form method="post" action="<?= url(['email', $message['id'], 'retry']) ?>" class="d-inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><i class="fe fe-send fe-12 mr-1"></i>Send it now</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($message['error'])): ?>
    <div class="alert alert-danger"><i class="fe fe-alert-octagon mr-2"></i><strong>Why it did not go:</strong> <?= e($message['error']) ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col-xl-8">
      <section class="card shadow-sm lms-section">
        <header class="lms-section-head">
          <div><h3><i class="fe fe-eye fe-16 mr-2"></i>As it arrives</h3><p>The message exactly as a mail client renders it.</p></div>
        </header>
        <div class="card-body p-0">
          <iframe src="<?= url(['email', $message['id'], 'preview']) ?>" title="Email preview"
                  style="width:100%;height:560px;border:0;display:block;background:#f1f4f7"></iframe>
        </div>
      </section>
    </div>

    <div class="col-xl-4">
      <section class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-info fe-16 mr-2"></i>Delivery</h3></div></header>
        <div class="card-body">
          <dl class="row lms-facts mb-0">
            <dt class="col-sm-5">Created</dt>
            <dd class="col-sm-7"><?= e(date('d M Y H:i', (int) strtotime((string) $message['created_at']))) ?></dd>

            <dt class="col-sm-5">Sent</dt>
            <dd class="col-sm-7"><?= $message['sent_at'] ? e(date('d M Y H:i', (int) strtotime((string) $message['sent_at']))) : '<span class="text-muted">Not yet</span>' ?></dd>

            <dt class="col-sm-5">Attempts</dt>
            <dd class="col-sm-7"><?= (int) $message['attempts'] ?></dd>

            <dt class="col-sm-5">Kind</dt>
            <dd class="col-sm-7"><?= e(ucfirst(str_replace('_', ' ', (string) $message['category']))) ?></dd>

            <dt class="col-sm-5">Provider id</dt>
            <dd class="col-sm-7"><?= $message['provider_id'] ? '<code>' . e($message['provider_id']) . '</code>' : '<span class="text-muted">None</span>' ?></dd>

            <dt class="col-sm-5">About</dt>
            <dd class="col-sm-7">
              <?php if (!empty($message['entity_id'])): ?>
                <?= e($message['entity_id']) ?>
                <?php if (!empty($message['entity_type'])): ?><small class="d-block text-muted"><?= e(ucfirst(str_replace('_', ' ', (string) $message['entity_type']))) ?></small><?php endif; ?>
              <?php else: ?>
                <span class="text-muted">Nothing in particular</span>
              <?php endif; ?>
            </dd>

            <dt class="col-sm-5">Message key</dt>
            <dd class="col-sm-7"><code class="small"><?= e($message['message_key']) ?></code></dd>
          </dl>
          <p class="small text-muted mt-3 mb-0">
            The key is what stops the same message being sent twice: a second attempt to record it is ignored.
          </p>
        </div>
      </section>

      <section class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-type fe-16 mr-2"></i>Plain text</h3><p>What a client that refuses HTML shows.</p></div></header>
        <div class="card-body">
          <pre class="small mb-0" style="white-space:pre-wrap;word-break:break-word"><?= e($message['body_text'] ?? '') ?></pre>
        </div>
      </section>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
