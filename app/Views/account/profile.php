<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Account</p>
      <h2 class="lms-page-title"><i class="fe fe-user mr-2"></i>My profile</h2>
      <p class="lms-page-sub">Your own details. Your role and permissions are managed by an administrator.</p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url(['account', 'password']) ?>"><i class="fe fe-key fe-12 mr-1"></i>Change password</a>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-8">
      <form method="post" action="<?= url('account') ?>" class="lms-form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <section class="card shadow-sm lms-section">
          <header class="lms-section-head">
            <span class="lms-section-num">1</span>
            <div><h3><i class="fe fe-user fe-16 mr-2"></i>Your details</h3><p>These appear on records you create and on the audit trail.</p></div>
          </header>
          <div class="card-body">
            <?php
            // The photograph, beside the name it belongs to. Everyone was shown
            // the same stock face before this; an account with no photo of its
            // own now shows its own initials rather than a stranger.
            $photo = \Models\Avatar::url($user['avatar_path'] ?? null);
            ?>
            <div class="lms-photo-row">
              <div class="lms-photo-current">
                <?php if ($photo !== null): ?>
                  <img src="<?= e($photo) ?>" alt="<?= e($user['full_name']) ?>" class="lms-photo-img" id="photoPreview">
                <?php else: ?>
                  <span class="lms-photo-img lms-initials lms-initials-lg" style="background: <?= e(\Models\Avatar::tint((string) $user['full_name'])) ?>" id="photoInitials"><?= e(\Models\Avatar::initials((string) $user['full_name'])) ?></span>
                  <img src="" alt="" class="lms-photo-img d-none" id="photoPreview">
                <?php endif; ?>
              </div>
              <div class="lms-photo-actions">
                <label class="btn btn-sm btn-outline-primary mb-1" for="avatar">
                  <i class="fe fe-camera fe-14 mr-1"></i><?= $photo === null ? 'Add a photo' : 'Change photo' ?>
                </label>
                <input type="file" id="avatar" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="d-none">
                <?php if ($photo !== null): ?>
                  <button type="submit" name="remove_photo" value="1" class="btn btn-sm btn-link text-danger mb-1 px-2">Remove</button>
                <?php endif; ?>
                <small class="form-text text-muted mb-0">
                  PNG, JPG, WEBP or GIF, up to 2 MB. Only people signed in to this system can see it.
                </small>
                <small class="form-text text-muted mb-0" id="photoChosen"></small>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group lms-field is-required">
                <label for="full_name">Full name <span class="lms-required">*</span></label>
                <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" required>
              </div>
              <div class="col-md-6 form-group lms-field">
                <label for="phone">Phone</label>
                <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+250 788 000 000">
              </div>
              <div class="col-md-6 form-group lms-field">
                <label for="job_title">Job title</label>
                <input class="form-control" type="text" id="job_title" name="job_title" value="<?= e($user['job_title'] ?? '') ?>">
                <small class="form-text text-muted">How you are described on reports you own.</small>
              </div>
              <div class="col-md-6 form-group lms-field">
                <label for="email_readonly">Email (login name)</label>
                <input class="form-control" type="email" id="email_readonly" value="<?= e($user['email']) ?>" readonly disabled>
                <small class="form-text text-muted">Ask an administrator to change your login email.</small>
              </div>
              <div class="col-md-12 form-group lms-field">
                <label>Email notifications</label>
                <div class="custom-control custom-switch mt-1">
                  <input type="hidden" name="notify_by_email" value="0">
                  <input type="checkbox" class="custom-control-input" id="notify_by_email" name="notify_by_email" value="1"
                         <?= (int) ($user['notify_by_email'] ?? 1) === 1 ? 'checked' : '' ?>>
                  <label class="custom-control-label" for="notify_by_email">
                    Send me an email as well as the on-screen bell
                  </label>
                </div>
                <small class="form-text text-muted">
                  Turn this off and you still get every update on the bell; only the email stops.
                  Password resets are always sent, because you cannot sign in to read the bell.
                </small>
              </div>
            </div>
          </div>
        </section>

        <div class="lms-form-bar">
          <span class="small text-muted"><span class="lms-required">*</span> marks a required field</span>
          <button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i>Save profile</button>
        </div>
      </form>
    </div>

    <div class="col-xl-4">
      <div class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-shield fe-16 mr-2"></i>Access</h3></div></header>
        <div class="card-body">
          <dl class="row lms-facts mb-0">
            <dt class="col-sm-5">Role</dt><dd class="col-sm-7"><span class="badge badge-primary"><?= e($user['role_name']) ?></span></dd>
            <dt class="col-sm-5">Department</dt><dd class="col-sm-7"><?= e($user['department'] ?? 'Not set') ?></dd>
            <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><span class="badge badge-<?= e(\Models\Schema::tone((string) $user['status'])) ?>"><?= e(\Models\Schema::label((string) $user['status'])) ?></span></dd>
            <dt class="col-sm-5">Privilege</dt><dd class="col-sm-7"><?= (int) $user['prvg'] === 1 ? 'May switch roles' : 'Standard' ?></dd>
            <dt class="col-sm-5">Driver profile</dt><dd class="col-sm-7"><?= $driverId === null ? '<span class="text-muted">Not linked</span>' : e('#' . $driverId) ?></dd>
            <dt class="col-sm-5">Last login</dt><dd class="col-sm-7"><?= $user['last_login_at'] ? e(date('d M Y H:i', (int) strtotime((string) $user['last_login_at']))) : 'First session' ?></dd>
            <dt class="col-sm-5">Password set</dt><dd class="col-sm-7"><?= $user['password_changed_at'] ? e(time_ago((string) $user['password_changed_at'])) : '<span class="text-muted">Unknown</span>' ?></dd>
          </dl>
        </div>
      </div>

      <div class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-list fe-16 mr-2"></i>What you may do</h3></div></header>
        <div class="card-body">
          <ul class="list-unstyled small mb-0 lms-permission-list">
            <?php foreach (\Models\Permission::forRole(current_role()) as $permissionKey => $abilities): ?>
              <li>
                <strong><?= e(ucfirst(str_replace('_', ' ', (string) $permissionKey))) ?></strong>
                <span class="text-muted"><?= e(implode(', ', array_keys(array_filter($abilities)))) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
