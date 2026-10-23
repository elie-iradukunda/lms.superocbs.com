<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Account</p>
      <h2 class="lms-page-title"><i class="fe fe-user mr-2"></i>My profile</h2>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url(['account', 'password']) ?>"><i class="fe fe-key fe-12 mr-1"></i>Change password</a>
      <button class="btn btn-primary" type="submit" form="lms-profile-form"><i class="fe fe-save fe-12 mr-1"></i>Save profile</button>
    </div>
  </div>

  <form id="lms-profile-form" method="post" action="<?= url('account') ?>" class="lms-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <section class="card shadow-sm lms-section lms-form-tabs">
      <ul class="nav nav-tabs lms-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="tab-details" data-toggle="tab" href="#pane-details" role="tab" aria-controls="pane-details" aria-selected="true"><i class="fe fe-user fe-14 mr-2"></i>Your details</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-signature" data-toggle="tab" href="#pane-signature" role="tab" aria-controls="pane-signature" aria-selected="false"><i class="fe fe-edit-3 fe-14 mr-2"></i>My signature</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-access" data-toggle="tab" href="#pane-access" role="tab" aria-controls="pane-access" aria-selected="false"><i class="fe fe-shield fe-14 mr-2"></i>Access</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-abilities" data-toggle="tab" href="#pane-abilities" role="tab" aria-controls="pane-abilities" aria-selected="false"><i class="fe fe-list fe-14 mr-2"></i>What you may do</a></li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-details" role="tabpanel" aria-labelledby="tab-details">
          <div class="card-body">
            <p class="lms-tab-hint">These appear on records you create and on the audit trail.</p>
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
              <div class="col-md-4 form-group lms-field">
                <label for="whatsapp_number">WhatsApp number</label>
                <input class="form-control" type="tel" id="whatsapp_number" name="whatsapp_number" value="<?= e($user['whatsapp_number'] ?? '') ?>" placeholder="Blank: the phone above">
              </div>
              <div class="col-md-8 form-group lms-field">
                <label>Also send me on WhatsApp</label>
                <?php $myTopics = \Support\WhatsApp::topicsOf($user['whatsapp_topics'] ?? null, current_role()); ?>
                <input type="hidden" name="whatsapp_topics_sent" value="1">
                <div class="row">
                  <?php foreach (\Support\WhatsApp::TOPICS as $topicKey => $topicLabel): ?>
                    <div class="col-md-6"><div class="custom-control custom-checkbox">
                      <input type="checkbox" class="custom-control-input" id="wa_<?= e($topicKey) ?>" name="whatsapp_topics[]" value="<?= e($topicKey) ?>" <?= in_array($topicKey, $myTopics, true) ? 'checked' : '' ?>>
                      <label class="custom-control-label" for="wa_<?= e($topicKey) ?>"><?= e($topicLabel) ?></label>
                    </div></div>
                  <?php endforeach; ?>
                </div>
                <small class="form-text text-muted">A short message with a link, from the company's WhatsApp Business number. Everything still comes to the bell.</small>
              </div>
            </div>
          </div>
        </div>

        <?php $signature = \Models\Signature::forUser((int) $user['id']); ?>
        <div class="tab-pane fade" id="pane-signature" role="tabpanel" aria-labelledby="tab-signature">
          <div class="card-body">
            <div class="row">
              <div class="col-lg-5 mb-3">
                <label class="d-block">Your signature now</label>
                <div class="lms-sign-current">
                  <?php if ($signature !== null): ?>
                    <img src="<?= $signature ?>" alt="Your signature">
                  <?php else: ?>
                    <span class="text-muted">No signature yet</span>
                  <?php endif; ?>
                </div>
                <?php if ($signature !== null): ?>
                  <button type="submit" form="lms-signature-form" name="remove_signature" value="1" class="btn btn-sm btn-link text-danger px-0 mt-1">Remove my signature</button>
                <?php endif; ?>
              </div>
              <div class="col-lg-7">
                <label class="d-block">Draw it here</label>
                <canvas id="lms-sign-pad" class="lms-sign-pad" width="600" height="200"></canvas>
                <div class="d-flex flex-wrap align-items-center mt-2" style="gap: 8px">
                  <button type="button" class="btn btn-sm btn-light" id="lms-sign-clear"><i class="fe fe-rotate-ccw fe-12 mr-1"></i>Clear</button>
                  <span class="text-muted small">or</span>
                  <label class="btn btn-sm btn-outline-primary mb-0" for="signature_file"><i class="fe fe-upload fe-12 mr-1"></i>Upload a picture</label>
                  <input type="file" id="signature_file" name="signature_file" form="lms-signature-form" accept="image/png,image/jpeg" class="d-none">
                  <span class="small text-muted" id="lms-sign-chosen"></span>
                </div>
                <input type="hidden" name="signature_drawn" id="signature_drawn" form="lms-signature-form">
                <button type="submit" form="lms-signature-form" class="btn btn-primary mt-3" id="lms-sign-save"><i class="fe fe-save fe-12 mr-1"></i>Save my signature</button>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="pane-access" role="tabpanel" aria-labelledby="tab-access">
          <div class="card-body">
            <p class="lms-tab-hint">Managed by an administrator.</p>
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

        <div class="tab-pane fade" id="pane-abilities" role="tabpanel" aria-labelledby="tab-abilities">
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
    </section>

    <p class="small text-muted mt-n2"><span class="lms-required">*</span> marks a required field</p>
  </form>
  <form id="lms-signature-form" method="post" action="<?= url(['account', 'signature']) ?>" enctype="multipart/form-data"><?= csrf_field() ?></form>
</div>
<script>
  // The signature pad: a finger on a phone, a mouse or a pen on a computer.
  document.addEventListener('DOMContentLoaded', function () {
    // Back on the tab you were using after saving.
    if (location.hash === '#pane-signature' && window.jQuery) { jQuery('#tab-signature').tab('show'); }
    var pad = document.getElementById('lms-sign-pad');
    if (!pad) { return; }
    var ctx = pad.getContext('2d');
    var drawing = false, drawn = false, last = null;
    ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#14234b';
    var point = function (event) {
      var rect = pad.getBoundingClientRect();
      var source = event.touches ? event.touches[0] : event;
      return { x: (source.clientX - rect.left) * pad.width / rect.width, y: (source.clientY - rect.top) * pad.height / rect.height };
    };
    var start = function (event) { event.preventDefault(); drawing = true; last = point(event); };
    var move = function (event) {
      if (!drawing) { return; }
      event.preventDefault();
      var p = point(event);
      ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
      last = p; drawn = true;
    };
    var stop = function () { drawing = false; };
    pad.addEventListener('mousedown', start); pad.addEventListener('mousemove', move); window.addEventListener('mouseup', stop);
    pad.addEventListener('touchstart', start, { passive: false }); pad.addEventListener('touchmove', move, { passive: false }); pad.addEventListener('touchend', stop);
    document.getElementById('lms-sign-clear').addEventListener('click', function () { ctx.clearRect(0, 0, pad.width, pad.height); drawn = false; });
    document.getElementById('signature_file').addEventListener('change', function () {
      document.getElementById('lms-sign-chosen').textContent = this.files[0] ? this.files[0].name : '';
    });
    document.getElementById('lms-signature-form').addEventListener('submit', function (event) {
      var removing = event.submitter && event.submitter.name === 'remove_signature';
      if (!removing && drawn) { document.getElementById('signature_drawn').value = pad.toDataURL('image/png'); }
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
