<?php
/**
 * One form control, rendered from its Schema definition.
 *
 * Expects: $name, $field, $value, $moduleKey.
 * Every control carries its label, required marker, unit suffix and help text,
 * so a form explains itself instead of showing a row of bare boxes.
 */
$id = 'f_' . $name;
$inputName = 'f[' . $name . ']';
$required = !empty($field['required']) && empty($field['auto']);
$help = $field['help'] ?? '';
$suffix = $field['suffix'] ?? '';
$readonly = !empty($field['readonly']);
$width = (int) ($field['width'] ?? 6);
$placeholder = $field['placeholder'] ?? '';
$value = $value ?? '';

$openGroup = static function (string $suffix): string {
    return $suffix === '' ? '' : '<div class="input-group">';
};
$closeGroup = static function (string $suffix): string {
    return $suffix === '' ? '' : '<div class="input-group-append"><span class="input-group-text">' . e($suffix) . '</span></div></div>';
};
?>
<div class="col-md-<?= $width ?> form-group lms-field<?= $required ? ' is-required' : '' ?>">
  <label for="<?= e($id) ?>">
    <?= e($field['label']) ?>
    <?php if ($required): ?><span class="lms-required" title="Required">*</span><?php endif; ?>
    <?php if ($readonly): ?><span class="badge badge-light ml-1"><?= e($field['readonly_note'] ?? 'calculated') ?></span><?php endif; ?>
  </label>

  <?php if ($readonly): ?>
    <?php
    // A locked field shows what it stands for, not what is stored: the name
    // behind a relation id, the label behind an option key.
    $shown = match ($field['type']) {
        'relation' => \Models\LogisticsData::relationLabel($field, $value),
        'select' => (string) ($field['options'][$value] ?? ($value === '' ? '' : \Models\Schema::label((string) $value))),
        'checkbox' => (int) $value === 1 ? 'Yes' : 'No',
        default => (string) $value,
    };
    ?>
    <input class="form-control" type="text" id="<?= e($id) ?>" value="<?= e($shown) ?>" readonly disabled>

  <?php elseif ($field['type'] === 'file'): ?>
    <?php $current = (string) $value; ?>
    <?php if ($current !== ''): ?>
      <p class="small mb-1 lms-file-current">
        <i class="fe fe-paperclip fe-12 mr-1"></i>
        <a href="<?= e(url(array_merge(['files'], explode('/', $current)))) ?>" target="_blank" rel="noopener">Open current file</a>
        <span class="text-muted">&mdash; uploading a new one replaces it</span>
      </p>
    <?php endif; ?>
    <input type="hidden" name="existing_<?= e($name) ?>" value="<?= e($current) ?>">
    <div class="custom-file">
      <input class="custom-file-input" type="file" id="<?= e($id) ?>" name="file_<?= e($name) ?>" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx">
      <label class="custom-file-label" for="<?= e($id) ?>">Choose file</label>
    </div>

  <?php elseif ($field['type'] === 'checkbox'): ?>
    <div class="custom-control custom-switch mt-2">
      <input type="hidden" name="<?= e($inputName) ?>" value="0">
      <input type="checkbox" class="custom-control-input" id="<?= e($id) ?>" name="<?= e($inputName) ?>" value="1" <?= (string) $value === '1' ? 'checked' : '' ?>>
      <label class="custom-control-label" for="<?= e($id) ?>"><?= e($field['label']) ?></label>
    </div>

  <?php elseif ($field['type'] === 'select' || $field['type'] === 'relation'): ?>
    <?php
    $options = $field['type'] === 'select'
        ? ($field['options'] ?? [])
        : \Models\LogisticsData::relationOptions($field['relation']);
    $emptyLabel = $field['type'] === 'relation'
        ? ($field['relation']['empty'] ?? 'None')
        : 'Select ' . strtolower($field['label']);
    // A dependent relation carries the parent of each option with it, so the
    // browser can narrow the list the moment the parent field is chosen.
    $dependsOn = $field['type'] === 'relation' ? ($field['relation']['depends_on']['field'] ?? null) : null;
    $parents = $dependsOn === null ? [] : \Models\LogisticsData::relationParents($field['relation']);
    // What choosing this option answers on the rest of the form.
    $fills = $field['type'] === 'relation' ? \Models\LogisticsData::relationFills($field['relation']) : [];
    ?>
    <select class="form-control lms-select" id="<?= e($id) ?>" name="<?= e($inputName) ?>" <?= $required ? 'required' : '' ?>
      <?= $dependsOn === null ? '' : 'data-depends-on="f_' . e($dependsOn) . '"' ?>>
      <?php if ($emptyLabel !== null): ?><option value=""><?= e($required ? 'Select ' . strtolower($field['label']) : $emptyLabel) ?></option><?php endif; ?>
      <?php foreach ($options as $optionValue => $optionLabel): ?>
        <option value="<?= e($optionValue) ?>"
          <?= $dependsOn === null ? '' : 'data-parent="' . e($parents[(int) $optionValue] ?? '') . '"' ?>
          <?= isset($fills[(int) $optionValue]) ? 'data-fills="' . e((string) json_encode($fills[(int) $optionValue])) . '"' : '' ?>
          <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($options === []): ?><small class="form-text text-warning">No option exists yet. Create one first.</small><?php endif; ?>

  <?php elseif ($field['type'] === 'textarea'): ?>
    <textarea class="form-control" id="<?= e($id) ?>" name="<?= e($inputName) ?>" rows="3" placeholder="<?= e($placeholder) ?>" <?= $required ? 'required' : '' ?>><?= e($value) ?></textarea>

  <?php else: ?>
    <?php
    $inputType = match ($field['type']) {
        'date' => 'date',
        'datetime' => 'datetime-local',
        'number', 'decimal', 'money' => 'number',
        'email' => 'email',
        'tel' => 'tel',
        default => 'text',
    };
    $inputValue = $value;
    if ($field['type'] === 'datetime' && $value !== '') {
        $inputValue = str_replace(' ', 'T', substr((string) $value, 0, 16));
    }
    if ($field['type'] === 'date' && $value !== '') {
        $inputValue = substr((string) $value, 0, 10);
    }
    $step = $field['type'] === 'number' ? '1' : ($field['type'] === 'money' ? '0.01' : '0.001');
    ?>
    <?= $openGroup($suffix) ?>
    <input
      class="form-control"
      type="<?= $inputType ?>"
      id="<?= e($id) ?>"
      name="<?= e($inputName) ?>"
      value="<?= e($inputValue) ?>"
      placeholder="<?= e($placeholder) ?>"
      <?= in_array($field['type'], ['number', 'decimal', 'money'], true) ? 'step="' . $step . '"' : '' ?>
      <?= isset($field['min']) ? 'min="' . e($field['min']) . '"' : '' ?>
      <?= isset($field['max']) ? 'max="' . e($field['max']) . '"' : '' ?>
      <?= $required ? 'required' : '' ?>>
    <?= $closeGroup($suffix) ?>
  <?php endif; ?>

  <?php if (!empty($field['auto'])): ?>
    <small class="form-text text-muted"><i class="fe fe-zap fe-12 mr-1"></i>Leave blank and a reference is generated for you.</small>
  <?php endif; ?>
  <?php if ($help !== ''): ?>
    <small class="form-text text-muted"><?= e($help) ?></small>
  <?php endif; ?>
</div>
