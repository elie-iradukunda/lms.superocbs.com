<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Account</p>
      <h2 class="lms-page-title"><i class="fe fe-key mr-2"></i>Change password</h2>
      <p class="lms-page-sub">Set a password only you know. Seeded accounts all start with the same one.</p>
    </div>
    <?php if (!$forced): ?>
      <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url('account') ?>">Back to profile</a></div>
    <?php endif; ?>
  </div>

  <?php if ($forced): ?>
    <div class="alert alert-warning">
      <i class="fe fe-alert-triangle mr-2"></i>
      <strong>A new password is required.</strong> Your account still uses the one-time password it was created with. You cannot use the rest of the system until you change it.
    </div>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <div class="alert alert-danger"><i class="fe fe-alert-circle mr-2"></i><?= e($error) ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col-xl-7">
      <form method="post" action="<?= url(['account', 'password']) ?>" class="lms-form">
        <?= csrf_field() ?>
        <section class="card shadow-sm lms-section">
          <header class="lms-section-head">
            <span class="lms-section-num"><i class="fe fe-lock"></i></span>
            <div><h3>New password</h3><p>You will stay signed in; only your password changes.</p></div>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-md-12 form-group lms-field is-required">
                <label for="current_password">Current password <span class="lms-required">*</span></label>
                <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
              </div>
              <div class="col-md-6 form-group lms-field is-required">
                <label for="password">New password <span class="lms-required">*</span></label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required minlength="10">
              </div>
              <div class="col-md-6 form-group lms-field is-required">
                <label for="password_confirmation">Repeat new password <span class="lms-required">*</span></label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required minlength="10">
              </div>
            </div>
          </div>
        </section>

        <div class="lms-form-bar">
          <span class="small text-muted"><span class="lms-required">*</span> marks a required field</span>
          <button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i>Change password</button>
        </div>
      </form>
    </div>

    <div class="col-xl-5">
      <div class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-check-circle fe-16 mr-2"></i>What is accepted</h3></div></header>
        <div class="card-body">
          <ul class="lms-rule-list mb-0">
            <li>At least 10 characters</li>
            <li>At least one capital letter</li>
            <li>At least one lower-case letter</li>
            <li>At least one digit</li>
            <li>Different from your current password</li>
            <li>Not an obvious word such as "password"</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
