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

  <form id="lms-settings-form" method="post" action="<?= url('settings') ?>" class="lms-form" enctype="multipart/form-data">
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
                  <?php $key = (string) $setting['setting_key']; ?>
                  <?php if ($setting['input_type'] === 'image'): ?>
                    <?php
                    $image = \Models\CompanyImage::dataUri($key);
                    $what = $key === \Models\CompanyImage::SEAL ? 'seal' : 'logo';
                    ?>
                    <div class="col-md-6 form-group lms-field">
                      <label for="s_<?= e($key) ?>"><?= e($setting['setting_label']) ?></label>
                      <div class="lms-seal-box lms-image-<?= e($what) ?>">
                        <?php if ($image !== null): ?>
                          <img src="<?= $image ?>" alt="Company <?= e($what) ?>" class="lms-seal-preview" id="preview_<?= e($key) ?>">
                        <?php else: ?>
                          <span class="lms-seal-empty" id="empty_<?= e($key) ?>">No <?= e($what) ?> uploaded</span>
                          <img alt="" class="lms-seal-preview d-none" id="preview_<?= e($key) ?>">
                        <?php endif; ?>
                      </div>
                      <?php if ($canEdit): ?>
                        <input class="form-control-file mt-2 lms-image-input" type="file" id="s_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/png,image/jpeg" data-preview="preview_<?= e($key) ?>" data-empty="empty_<?= e($key) ?>">
                        <small class="form-text text-muted">PNG or JPG, up to 2 MB</small>
                        <?php if ($image !== null): ?>
                          <div class="custom-control custom-checkbox mt-1">
                            <input type="checkbox" class="custom-control-input" id="remove_<?= e($key) ?>" name="remove_image[<?= e($key) ?>]" value="1">
                            <label class="custom-control-label" for="remove_<?= e($key) ?>">Remove the <?= e($what) ?></label>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                    <?php continue; ?>
                  <?php endif; ?>
                  <?php if ($setting['input_type'] === 'color'): ?>
                    <?php
                    $hex = strtoupper(ltrim((string) ($setting['setting_value'] ?? ''), '#'));
                    $hex = preg_match('/^[0-9A-F]{6}$/', $hex) === 1 ? $hex : 'AD7D00';
                    // Colours that read well on white paper and behind white text.
                    $swatches = [
                        'AD7D00' => 'Gold', '1F3A5F' => 'Navy', '1565C0' => 'Blue', '0277BD' => 'Sky blue',
                        '00796B' => 'Teal', '2E7D32' => 'Green', '558B2F' => 'Olive', 'C62828' => 'Red',
                        '7B1F2B' => 'Maroon', 'E65100' => 'Orange', '5E35B1' => 'Purple', '37474F' => 'Charcoal',
                    ];
                    ?>
                    <div class="col-md-6 form-group lms-field">
                      <label for="s_<?= e($key) ?>_picker"><?= e($setting['setting_label']) ?></label>
                      <div class="lms-swatches" role="radiogroup">
                        <?php foreach ($swatches as $code => $name): ?>
                          <button type="button" class="lms-swatch<?= $code === $hex ? ' is-active' : '' ?>" style="background:#<?= e($code) ?>" data-hex="<?= e($code) ?>" title="<?= e($name) ?>" aria-label="<?= e($name) ?>" <?= $canEdit ? '' : 'disabled' ?>></button>
                        <?php endforeach; ?>
                      </div>
                      <div class="lms-colour-row">
                        <input type="color" id="s_<?= e($key) ?>_picker" class="lms-colour-picker" value="#<?= e($hex) ?>" title="Any other colour" <?= $canEdit ? '' : 'disabled' ?>>
                        <input type="text" class="form-control lms-colour-hex" id="s_<?= e($key) ?>" name="settings[<?= e($key) ?>]" value="<?= e($hex) ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" <?= $canEdit ? '' : 'readonly disabled' ?>>
                        <span class="lms-colour-sample" style="background:#<?= e($hex) ?>">Sample</span>
                      </div>
                    </div>
                    <?php continue; ?>
                  <?php endif; ?>
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
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Colour: a swatch, the picker or the typed code all set the same value.
    document.querySelectorAll('.lms-colour-hex').forEach(function (hex) {
      var field = hex.closest('.lms-field');
      var picker = field.querySelector('.lms-colour-picker');
      var sample = field.querySelector('.lms-colour-sample');
      var swatches = field.querySelectorAll('.lms-swatch');
      var show = function (code) {
        code = code.replace('#', '').toUpperCase();
        if (!/^[0-9A-F]{6}$/.test(code)) { return; }
        hex.value = code;
        picker.value = '#' + code;
        sample.style.background = '#' + code;
        swatches.forEach(function (s) { s.classList.toggle('is-active', s.getAttribute('data-hex') === code); });
      };
      swatches.forEach(function (s) { s.addEventListener('click', function () { show(s.getAttribute('data-hex')); }); });
      picker.addEventListener('input', function () { show(picker.value); });
      hex.addEventListener('input', function () { show(hex.value); });
    });

    // Logo and seal: show the chosen file before it is saved.
    document.querySelectorAll('.lms-image-input').forEach(function (input) {
      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        var preview = document.getElementById(input.getAttribute('data-preview'));
        var empty = document.getElementById(input.getAttribute('data-empty'));
        if (!file || !preview) { return; }
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
        if (empty) { empty.classList.add('d-none'); }
      });
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
