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
    <h1>Choose a new password</h1>

    <?php if ($error !== null): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url(['reset-password', $token]) ?>">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="password">New password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="10" autocomplete="new-password" autofocus>
      </div>
      <div class="form-group">
        <label for="password_confirmation">Repeat new password</label>
        <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="10" autocomplete="new-password">
      </div>
      <ul class="lms-rule-list small">
        <li>At least 10 characters</li>
        <li>One capital, one lower-case letter and one digit</li>
      </ul>
      <button class="btn btn-primary btn-block" type="submit">Set new password</button>
    </form>
    <p class="mt-3 mb-0"><a href="<?= url('') ?>">Back to sign in</a></p>
  </main>
</body>
</html>
