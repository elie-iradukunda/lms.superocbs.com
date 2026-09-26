<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$isEdit = $recordId !== null;

// Sections are shown as tabs, so a field that failed validation may be on a
// tab nobody is looking at. Every message starts with the field's label, which
// is enough to flag the tab and open the first one that needs attention.
$sectionHasError = [];
foreach ($module['sections'] as $index => $section) {
    foreach ($section['fields'] as $name) {
        $label = $module['fields'][$name]['label'] ?? null;
        foreach ($label === null ? [] : ($errors ?? []) as $message) {
            if (str_starts_with((string) $message, $label . ' ')) {
                $sectionHasError[$index] = true;
                break 2;
            }
        }
    }
}
$activeSection = $sectionHasError === [] ? 0 : min(array_keys($sectionHasError));
?>
<div class="container-fluid lms-form-page">

  <div class="lms-page-head">
    <div>
      <h2 class="lms-page-title"><i class="fe fe-<?= e($module['icon']) ?> mr-2"></i><?= e($title) ?></h2>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= $isEdit ? url([$moduleKey, $recordId]) : url($moduleKey) ?>">Cancel</a>
      <button class="btn btn-primary" type="submit" form="lms-record-form"><i class="fe fe-save fe-12 mr-1"></i><?= $isEdit ? 'Save changes' : 'Create ' . strtolower($module['singular']) ?></button>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger lms-error-block">
      <strong><i class="fe fe-alert-circle mr-2"></i><?= count($errors) ?> thing<?= count($errors) === 1 ? '' : 's' ?> need<?= count($errors) === 1 ? 's' : '' ?> fixing before this can be saved</strong>
      <ul class="mb-0 mt-2"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form id="lms-record-form" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="lms-form" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($module['lines']) && $isEdit): ?>
      <p class="small text-muted mb-3"><i class="fe fe-info fe-12 mr-1"></i><?= e($module['lines']['title']) ?> are added on the record page after saving.</p>
    <?php endif; ?>

    <section class="card shadow-sm lms-section lms-form-tabs">
      <ul class="nav nav-tabs lms-tabs" role="tablist">
        <?php foreach ($module['sections'] as $index => $section): ?>
          <li class="nav-item">
            <a class="nav-link<?= $index === $activeSection ? ' active' : '' ?>" id="tab-sec-<?= $index ?>" data-toggle="tab" href="#sec-<?= $index ?>" role="tab" aria-controls="sec-<?= $index ?>" aria-selected="<?= $index === $activeSection ? 'true' : 'false' ?>">
              <i class="fe fe-<?= e($section['icon'] ?? 'circle') ?> fe-14 mr-2"></i><?= e($section['title']) ?>
              <?php if (!empty($sectionHasError[$index])): ?><span class="lms-tab-error" title="Has a field to fix"></span><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="tab-content">
        <?php foreach ($module['sections'] as $index => $section): ?>
          <div class="tab-pane fade<?= $index === $activeSection ? ' show active' : '' ?>" id="sec-<?= $index ?>" role="tabpanel" aria-labelledby="tab-sec-<?= $index ?>">
            <div class="card-body">
              <?php if (!empty($section['hint'])): ?><p class="lms-tab-hint"><?= e($section['hint']) ?></p><?php endif; ?>
              <div class="row">
                <?php foreach ($section['fields'] as $name): ?>
                  <?php
                  $field = $module['fields'][$name] ?? null;
                  if ($field === null) {
                      continue;
                  }
                  $value = $record[$name] ?? '';
                  require __DIR__ . '/../partials/field.php';
                  ?>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <p class="small text-muted mt-n2"><span class="lms-required">*</span> marks a required field</p>
  </form>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
