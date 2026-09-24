<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$isEdit = $recordId !== null;
$code = $isEdit ? ($record[$module['code']] ?? '') : '';
$requiredCount = count(array_filter($module['fields'], static fn (array $f): bool => !empty($f['required']) && empty($f['auto'])));
?>
<div class="container-fluid lms-form-page">

  <div class="lms-page-head">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb lms-crumbs">
          <li class="breadcrumb-item"><a href="<?= url($moduleKey) ?>"><?= e($module['title']) ?></a></li>
          <?php if ($isEdit): ?><li class="breadcrumb-item"><a href="<?= url([$moduleKey, $recordId]) ?>"><?= e($code) ?></a></li><?php endif; ?>
          <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit' : 'New' ?></li>
        </ol>
      </nav>
      <h2 class="lms-page-title"><i class="fe fe-<?= e($module['icon']) ?> mr-2"></i><?= e($title) ?></h2>
      <p class="lms-page-sub"><?= e($module['description']) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= $isEdit ? url([$moduleKey, $recordId]) : url($moduleKey) ?>">Cancel</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger lms-error-block">
      <strong><i class="fe fe-alert-circle mr-2"></i><?= count($errors) ?> thing<?= count($errors) === 1 ? '' : 's' ?> need<?= count($errors) === 1 ? 's' : '' ?> fixing before this can be saved</strong>
      <ul class="mb-0 mt-2"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="lms-form" novalidate>
    <?= csrf_field() ?>

    <div class="row">
      <div class="col-xl-9">
        <?php foreach ($module['sections'] as $index => $section): ?>
          <section class="card shadow-sm lms-section">
            <header class="lms-section-head">
              <span class="lms-section-num"><?= $index + 1 ?></span>
              <div>
                <h3><i class="fe fe-<?= e($section['icon'] ?? 'circle') ?> fe-16 mr-2"></i><?= e($section['title']) ?></h3>
                <?php if (!empty($section['hint'])): ?><p><?= e($section['hint']) ?></p><?php endif; ?>
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
          </section>
        <?php endforeach; ?>
      </div>

      <div class="col-xl-3">
        <aside class="lms-form-aside">
          <div class="card shadow-sm">
            <div class="card-body">
              <h6 class="text-uppercase small text-muted mb-3">About this record</h6>
              <p class="small text-muted mb-3"><?= e($module['description']) ?></p>
              <ul class="list-unstyled small mb-0 lms-form-facts">
                <li><i class="fe fe-layers fe-12 mr-2"></i><?= count($module['fields']) ?> fields in <?= count($module['sections']) ?> sections</li>
                <li><i class="fe fe-alert-circle fe-12 mr-2"></i><?= $requiredCount ?> required field<?= $requiredCount === 1 ? '' : 's' ?></li>
                <?php if ($isEdit): ?><li><i class="fe fe-hash fe-12 mr-2"></i>Reference <strong><?= e($code) ?></strong></li><?php endif; ?>
                <?php if (\Models\LogisticsData::fileFields($moduleKey) !== []): ?><li><i class="fe fe-paperclip fe-12 mr-2"></i>Uploads up to 5 MB (PDF, image, Word, Excel)</li><?php endif; ?>
              </ul>
            </div>
          </div>

          <?php if (!empty($module['lines']) && $isEdit): ?>
            <div class="card shadow-sm mt-3">
              <div class="card-body">
                <h6 class="text-uppercase small text-muted mb-2"><?= e($module['lines']['title']) ?></h6>
                <p class="small text-muted mb-0">Add these on the record page after saving.</p>
              </div>
            </div>
          <?php endif; ?>
        </aside>
      </div>
    </div>

    <div class="lms-form-bar">
      <span class="small text-muted"><span class="lms-required">*</span> marks a required field</span>
      <div>
        <a class="btn btn-light" href="<?= $isEdit ? url([$moduleKey, $recordId]) : url($moduleKey) ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="fe fe-save fe-12 mr-1"></i><?= $isEdit ? 'Save changes' : 'Create ' . strtolower($module['singular']) ?></button>
      </div>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
