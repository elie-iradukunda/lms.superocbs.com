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
      <p class="lms-page-sub">Values the whole system reads at runtime. They used to be constants inside the code.</p>
    </div>
  </div>

  <form method="post" action="<?= url('settings') ?>" class="lms-form">
    <?= csrf_field() ?>

    <?php if ($canEdit): ?>
      <div class="lms-form-bar lms-form-bar-top">
        <span class="small text-muted">Changes apply to every user straight away.</span>
        <button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i>Save settings</button>
      </div>
    <?php endif; ?>

    <ul class="nav nav-tabs lms-settings-tabs" role="tablist">
      <?php foreach ($groupNames as $index => $groupName): ?>
        <li class="nav-item">
          <a class="nav-link<?= $index === 0 ? ' active' : '' ?>" id="tab-<?= e($groupName) ?>-toggle" data-toggle="tab" href="#tab-<?= e($groupName) ?>" role="tab" aria-controls="tab-<?= e($groupName) ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
            <i class="fe fe-<?= e($icons[$groupName] ?? 'circle') ?> mr-1"></i><?= e($groupName) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content lms-settings-panes">
      <?php foreach ($grouped as $groupName => $settings): ?>
        <div class="tab-pane fade<?= $groupName === $groupNames[0] ? ' show active' : '' ?>" id="tab-<?= e($groupName) ?>" role="tabpanel" aria-labelledby="tab-<?= e($groupName) ?>-toggle">
          <?php if (isset($hints[$groupName])): ?><p class="lms-settings-hint"><?= e($hints[$groupName]) ?></p><?php endif; ?>
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
    </div>
  </form>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
