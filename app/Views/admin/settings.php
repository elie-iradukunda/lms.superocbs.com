<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$icons = ['Company' => 'home', 'Finance' => 'dollar-sign', 'Operations' => 'activity', 'Security' => 'lock'];
$hints = [
    'Company' => 'Printed on invoices and used as the name of this installation.',
    'Finance' => 'Currency, default VAT and invoice numbering. Changing the currency symbol changes every amount on screen.',
    'Operations' => 'How far ahead the dashboard warns about expiries, and how much lateness still counts as on time.',
    'Security' => 'How many failed sign-ins are allowed before an account is locked, and for how long.',
];
$groupNames = array_keys($grouped);
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Administration</p>
      <h2 class="lms-page-title"><i class="fe fe-settings mr-2"></i>Company settings</h2>
    </div>
    <?php if ($canEdit): ?>
      <div class="lms-page-actions">
        <button class="btn btn-primary" type="submit" form="lms-settings-form"><i class="fe fe-save fe-12 mr-1"></i>Save settings</button>
      </div>
    <?php endif; ?>
  </div>

  <form id="lms-settings-form" method="post" action="<?= url('settings') ?>" class="lms-form">
    <?= csrf_field() ?>

    <?php // Every group on one page: scroll down and save once. ?>
    <section class="card shadow-sm lms-section lms-form-single">
        <?php foreach ($groupNames as $index => $groupName): $settings = $grouped[$groupName]; ?>
          <header class="lms-section-head<?= $index > 0 ? ' lms-section-head-divided' : '' ?>">
            <div>
              <span class="lms-section-num"><?= $index + 1 ?></span>
              <div>
                <h3><i class="fe fe-<?= e($icons[$groupName] ?? 'circle') ?> fe-16 mr-2"></i><?= e($groupName) ?></h3>
                <?php if (isset($hints[$groupName])): ?><p><?= e($hints[$groupName]) ?></p><?php endif; ?>
              </div>
            </div>
          </header>
            <div class="card-body">
              <div class="row">
                <?php foreach ($settings as $setting): ?>
                  <div class="col-md-6 form-group lms-field">
                    <label for="s_<?= e($setting['setting_key']) ?>"><?= e($setting['setting_label']) ?></label>
                    <input
                      class="form-control"
                      type="<?= $setting['input_type'] === 'number' ? 'number' : 'text' ?>"
                      id="s_<?= e($setting['setting_key']) ?>"
                      name="settings[<?= e($setting['setting_key']) ?>]"
                      value="<?= e($setting['setting_value'] ?? '') ?>"
                      <?= $canEdit ? '' : 'readonly disabled' ?>>
                    <small class="form-text text-muted"><?= e($setting['setting_key']) ?></small>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if ($canEdit): ?>
      <div class="lms-form-bar">
        <span class="small text-muted">Changes apply to every user straight away.</span>
        <div><button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i>Save settings</button></div>
      </div>
    <?php endif; ?>
  </form>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
