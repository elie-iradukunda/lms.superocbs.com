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
    <h1>Enter your sign-in code</h1>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($emailed)): ?>
      <p class="text-muted">A 6-digit code was sent to your email. It expires in 10 minutes.</p>
    <?php else: ?>
      <div class="alert alert-warning small mb-3">The code could not be emailed. Ask an administrator to check Administration &rarr; Email outbox.</div>
    <?php endif; ?>

    <?php if (!empty($shown)): ?>
      <div class="alert alert-info small">Test installation: your code is <strong><?= e($shown) ?></strong>.</div>
    <?php endif; ?>

    <form method="post" action="<?= url(['login', 'verify']) ?>">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="code">Code</label>
        <input class="form-control form-control-lg text-center" type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <p class="mt-3 mb-0"><a href="<?= url('logout') ?>">Cancel and go back</a></p>
  </main>
</body>
</html>
