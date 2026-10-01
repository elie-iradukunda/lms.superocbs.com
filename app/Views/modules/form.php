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
      <?php if (!empty($module['description'])): ?><p class="lms-page-sub"><?= e($module['description']) ?></p><?php endif; ?>
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

  <form id="lms-record-form" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="lms-form" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($module['lines']) && $isEdit): ?>
      <p class="small text-muted mb-3"><i class="fe fe-info fe-12 mr-1"></i><?= e($module['lines']['title']) ?> are added on the record page after saving.</p>
    <?php endif; ?>

    <?php // One form, every section on the same page: scroll down, fill in, save. ?>
    <section class="card shadow-sm lms-section lms-form-single">
      <?php foreach ($module['sections'] as $index => $section): ?>
        <header class="lms-section-head<?= $index > 0 ? ' lms-section-head-divided' : '' ?>">
          <div>
            <span class="lms-section-num"><?= $index + 1 ?></span>
            <div>
              <h3><i class="fe fe-<?= e($section['icon'] ?? 'circle') ?> fe-16 mr-2"></i><?= e($section['title']) ?></h3>
              <?php if (!empty($section['hint'])): ?><p><?= e($section['hint']) ?></p><?php endif; ?>
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
<?php require __DIR__ . '/../layouts/footer.php'; ?>
