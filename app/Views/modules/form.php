<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$isEdit = $recordId !== null;
$cancelUrl = $isEdit ? url([$moduleKey, $recordId]) : url($moduleKey);
$saveLabel = $isEdit ? 'Save changes' : 'Create ' . strtolower($module['singular']);
?>
<div class="container-fluid lms-form-page">

  <div class="lms-page-head">
    <div>
      <h2 class="lms-page-title"><i class="fe fe-<?= e($module['icon']) ?> mr-2"></i><?= e($title) ?></h2>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= $cancelUrl ?>">Cancel</a>
      <button class="btn btn-primary" type="submit" form="lms-record-form"><i class="fe fe-save fe-12 mr-1"></i><?= e($saveLabel) ?></button>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger lms-error-block">
      <strong><i class="fe fe-alert-circle mr-2"></i><?= count($errors) ?> thing<?= count($errors) === 1 ? '' : 's' ?> need<?= count($errors) === 1 ? 's' : '' ?> fixing before this can be saved</strong>
      <ul class="mb-0 mt-2"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form id="lms-record-form" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="lms-form" novalidate<?= $isEdit || !empty($errors) ? '' : ' data-new-record' ?>>
    <?= csrf_field() ?>

    <?php if (!empty($module['lines']) && $isEdit): ?>
      <p class="small text-muted mb-3"><i class="fe fe-info fe-12 mr-1"></i><?= e($module['lines']['title']) ?> are added on the record page after saving.</p>
    <?php endif; ?>

    <?php // One form, every section on the same page: scroll down, fill in, save. ?>
    <section class="card shadow-sm lms-section lms-form-single">
      <?php foreach ($module['sections'] as $index => $section): ?>
        <?php if (!$isEdit && !empty($section['hide_on_create'])) { continue; } ?>
        <header class="lms-section-head<?= $index > 0 ? ' lms-section-head-divided' : '' ?>">
          <div>
            <span class="lms-section-num"><?= $index + 1 ?></span>
            <div>
              <h3><i class="fe fe-<?= e($section['icon'] ?? 'circle') ?> fe-16 mr-2"></i><?= e($section['title']) ?></h3>
            </div>
          </div>
        </header>
        <div class="card-body">
          <div class="row">
            <?php foreach ($section['fields'] as $name): ?>
              <?php
              $field = $module['fields'][$name] ?? null;
              if ($field === null) {
                  continue;
              }
              // A reference the system numbers itself (TRP-2026-0007, INV-…) is
              // nothing to fill in: a new form does not show it, and an edit form
              // shows it without letting anyone change it.
              if (!empty($field['auto'])) {
                  if (!$isEdit) {
                      continue;
                  }
                  $field['readonly'] = true;
                  $field['readonly_note'] = 'given by the system';
              }
              $value = $record[$name] ?? '';
              require __DIR__ . '/../partials/field.php';
              ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <div class="lms-form-bar">
      <span class="small text-muted"><span class="lms-required">*</span> marks a required field</span>
      <div>
        <a class="btn btn-light" href="<?= $cancelUrl ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i><?= e($saveLabel) ?></button>
      </div>
    </div>
  </form>
</div>
<script>
  // Fields that only apply to some choices hide themselves when the choice does
  // not match. A field carries data-show-when='{"controlling_field":["value",…]}';
  // this reveals it only while every controlling field holds one of those values,
  // and disables the inputs of a hidden field so a value left from another choice
  // is never submitted or saved. Works on create and on edit (it runs on load).
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('lms-record-form');
    if (!form) { return; }
    var conditionals = Array.prototype.slice.call(form.querySelectorAll('[data-show-when]'));
    if (conditionals.length === 0) { return; }

    var controllers = {};
    var rules = conditionals.map(function (wrapper) {
      var map;
      try { map = JSON.parse(wrapper.getAttribute('data-show-when')); } catch (e) { map = {}; }
      Object.keys(map).forEach(function (fieldName) {
        controllers[fieldName] = form.querySelector('#f_' + fieldName);
      });
      return { wrapper: wrapper, map: map };
    });

    var apply = function () {
      rules.forEach(function (rule) {
        var show = Object.keys(rule.map).every(function (fieldName) {
          var el = controllers[fieldName];
          if (!el) { return false; }
          return rule.map[fieldName].indexOf(el.value) !== -1;
        });
        rule.wrapper.classList.toggle('d-none', !show);
        rule.wrapper.querySelectorAll('input, select, textarea').forEach(function (input) {
          input.disabled = !show;
        });
      });
    };

    Object.keys(controllers).forEach(function (fieldName) {
      var el = controllers[fieldName];
      if (!el) { return; }
      el.addEventListener('change', apply);
      el.addEventListener('input', apply);
      if (window.jQuery) { window.jQuery(el).on('change', apply); }
    });

    apply();
  });
</script>
<?php if ($moduleKey === 'pre_dispatch_checks'): ?>
<script>
  // Dispatch check: choosing the shipment shows everything the system checked
  // (load against the truck, every paper, truck, driver, route), and the four
  // boxes it can verify follow that analysis. People tick only what they see
  // at the truck.
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('lms-record-form');
    var shipment = form && form.querySelector('[name="f[shipment_id]"]');
    if (!shipment) { return; }
    var panel = document.createElement('div');
    panel.id = 'lms-readiness-live';
    form.insertBefore(panel, form.querySelector('.lms-form-single'));
    var auto = ['documents_verified', 'vehicle_verified', 'driver_verified', 'route_verified'];
    auto.forEach(function (name) {
      var box = form.querySelector('input[type="checkbox"][name="f[' + name + ']"]');
      if (!box) { return; }
      box.closest('.custom-control').classList.add('lms-auto-tick');
      var note = document.createElement('small');
      note.className = 'lms-auto-note';
      note.textContent = 'Checked by the system';
      box.closest('.custom-control').after(note);
    });
    var load = function () {
      if (!shipment.value) { panel.innerHTML = ''; return; }
      fetch('<?= e(url(['pre_dispatch_checks', 'readiness'])) ?>?shipment_id=' + encodeURIComponent(shipment.value), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          panel.innerHTML = data.html || '';
          auto.forEach(function (name) {
            var box = form.querySelector('input[type="checkbox"][name="f[' + name + ']"]');
            if (box && data.ticks && name in data.ticks) { box.checked = data.ticks[name] === 1; }
          });
        })
        .catch(function () { panel.innerHTML = ''; });
    };
    shipment.addEventListener('change', load);
    if (window.jQuery) { window.jQuery(shipment).on('change', load); }
    load();
  });
</script>
<?php endif; ?>
<?php if ($moduleKey === 'rates'): ?>
<script>
  // Rate card: type what a full load costs, and the price per kg (or the price
  // for the trip) fills itself in, the same way the server works it out on save.
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('lms-record-form');
    if (!form) { return; }
    var get = function (name) { return form.querySelector('[name="f[' + name + ']"]'); };
    var kg = get('full_load_kg'), price = get('full_load_price'), basis = get('rate_type'), amount = get('rate_amount');
    if (!kg || !price || !basis || !amount) { return; }
    var note = document.createElement('small');
    note.className = 'd-block text-success mt-1';
    amount.closest('.form-group, .lms-field, div').appendChild(note);
    var work = function () {
      var k = parseFloat(kg.value), p = parseFloat(price.value), b = basis.value;
      note.textContent = '';
      if (b === 'per_kg' && k > 0 && p > 0) {
        var unit = Math.round(p / k * 10000) / 10000;
        amount.value = unit;
        note.textContent = 'Worked out: ' + p.toLocaleString() + ' ÷ ' + k.toLocaleString() + ' kg = ' + unit + ' per kg';
      } else if ((b === 'per_trip' || b === 'per_day') && p > 0) {
        amount.value = p;
        note.textContent = 'The price of the full truck';
      }
    };
    [kg, price, basis].forEach(function (el) { el.addEventListener('input', work); el.addEventListener('change', work); });
    work();
  });
</script>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
