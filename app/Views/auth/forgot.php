<?php $baseUrl = config('app.base_url', ''); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | LMS</title>
  <link rel="icon" href="<?= $baseUrl ?>/assets/img/logistics-logo.svg">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/feather.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app-light.css">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-modules.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-background.css') ?>">
</head>
<body class="lms-auth-page">
  <main class="lms-auth-card">
    <a class="lms-auth-brand" href="<?= url('') ?>"><img src="<?= $baseUrl ?>/assets/img/logistics-logo.svg" alt="LMS" height="38"></a>
    <h1>Reset your password</h1>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($sent)): ?>
      <?php if (!empty($emailed)): ?>
        <div class="alert alert-success">
          <strong>Check your inbox.</strong>
          <p class="small mb-0">If <strong><?= e($email ?? '') ?></strong> belongs to an active account, a
          reset link is on its way to it. The link works once and expires in an hour.</p>
        </div>
        <p class="small text-muted">Nothing after a few minutes? Look in the spam folder, or ask an
        administrator to check Administration &rarr; Email outbox.</p>
      <?php else: ?>
        <p class="text-muted">If <strong><?= e($email ?? '') ?></strong> belongs to an active account, a reset link has been created for it.</p>
      <?php endif; ?>
      <?php if (!empty($token)): ?>
        <div class="alert alert-warning">
          <strong>The link could not be emailed.</strong>
          <p class="small mb-2">Email is switched off, or the provider refused the message, so the link is shown here for an administrator to pass on. It is valid for one hour and can be used once.</p>
          <code class="lms-auth-token"><?= e(url(['reset-password', $token])) ?></code>
        </div>
        <a class="btn btn-primary btn-block" href="<?= url(['reset-password', $token]) ?>">Open the reset page</a>
      <?php endif; ?>
      <p class="mt-3 mb-0"><a href="<?= url('') ?>">Back to sign in</a></p>
    <?php else: ?>
      <p class="text-muted">Enter the email you sign in with and we will create a single-use reset link.</p>
      <form method="post" action="<?= url('forgot-password') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email" required autofocus placeholder="you@company.rw">
        </div>
        <button class="btn btn-primary btn-block" type="submit">Create reset link</button>
      </form>
      <p class="mt-3 mb-0"><a href="<?= url('') ?>">Back to sign in</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
