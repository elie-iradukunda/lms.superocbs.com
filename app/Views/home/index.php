<?php
/**
 * The front door.
 *
 * There is nothing to read here before signing in: no marketing page, no list of
 * accounts. The address bar's root is the sign-in form, and everything else in
 * the system sits behind it.
 */
$baseUrl = config('app.base_url', '');
$company = company_name();
$minutes = isset($_GET['minutes']) ? (int) $_GET['minutes'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Sign in | <?= e($company) ?></title>
  <link rel="icon" href="<?= $baseUrl ?>/assets/img/logistics-logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/feather.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app-light.css">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-modules.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-background.css') ?>">
</head>
<body class="lms-auth-page">
  <main class="lms-auth-card" id="login">
    <span class="lms-auth-brand"><img src="<?= $baseUrl ?>/assets/img/logistics-logo.svg" alt="" height="38"></span>
    <h1>Sign in</h1>
    <p class="text-muted mb-4"><?= e($company) ?> &mdash; logistics operations</p>

    <?php if ($loginError === 'locked'): ?>
      <div class="alert alert-danger">
        <strong>This account is locked.</strong>
        <p class="small mb-0">Too many sign-ins failed in a row. Try again
        <?= $minutes > 0 ? 'in about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') : 'once an administrator unlocks it' ?>.</p>
      </div>
    <?php elseif ($loginError === 'token'): ?>
      <div class="alert alert-danger">
        <strong>The form expired.</strong>
        <p class="small mb-0">Your session timed out before the form was sent. Please sign in again.</p>
      </div>
    <?php elseif ($loginError !== ''): ?>
      <div class="alert alert-danger">That email and password do not match an active account.</div>
    <?php elseif ($loginRequired): ?>
      <div class="alert alert-info">Please sign in to open that page.</div>
    <?php elseif ($loggedOut): ?>
      <div class="alert alert-success">You have been signed out.</div>
    <?php endif; ?>

    <?php if ($accounts === []): ?>
      <div class="alert alert-warning">
        <strong>No active accounts yet.</strong>
        <p class="small mb-0">Run <code>php scripts/migrate.php</code> to create the database and its first login.</p>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= url('login') ?>" autocomplete="on">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="loginEmail">Email</label>
        <input class="form-control" id="loginEmail" name="email" type="email" value="<?= e((string) ($_GET['email'] ?? '')) ?>"
               autocomplete="username" autofocus required>
      </div>
      <div class="form-group">
        <label for="loginPassword">Password</label>
        <input class="form-control" id="loginPassword" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit"><i class="fe fe-log-in fe-16 mr-2"></i>Sign in</button>
    </form>

    <p class="mt-3 mb-0 small"><a href="<?= url('forgot-password') ?>">Forgotten your password?</a></p>
  </main>
</body>
</html>
