<?php
/**
 * The readiness analysis: everything the system checked before a trip may
 * start, grouped, each point passed, a warning or failed, with its figures.
 *
 * @var array $analysis from Models\DispatchReadiness
 * @var string $readinessTitle optional heading
 */
$icons = ['ok' => 'check-circle', 'warn' => 'alert-triangle', 'fail' => 'x-circle'];
$byGroup = [];
foreach ($analysis['items'] as $item) {
    $byGroup[$item['group']][] = $item;
}
$failed = count($analysis['failures']);
$warned = count(array_filter($analysis['items'], static fn (array $i): bool => $i['state'] === 'warn'));
?>
<section class="card shadow-sm lms-readiness <?= $analysis['ready'] ? 'is-ready' : 'is-blocked' ?>">
  <header class="lms-readiness-head">
    <div>
      <h3><i class="fe fe-<?= $analysis['ready'] ? 'check-circle' : 'shield-off' ?> fe-16 mr-2"></i><?= e($readinessTitle ?? 'Ready to start the trip?') ?></h3>
      <p class="mb-0">
        <?php if ($analysis['ready']): ?>
          Everything the system can check is in order<?= $warned > 0 ? sprintf(', with %d warning%s to look at', $warned, $warned === 1 ? '' : 's') : '' ?>.
        <?php else: ?>
          <?= $failed ?> thing<?= $failed === 1 ? '' : 's' ?> must be fixed before the truck can leave<?= $warned > 0 ? sprintf(', and %d warning%s', $warned, $warned === 1 ? '' : 's') : '' ?>.
        <?php endif; ?>
      </p>
    </div>
    <span class="lms-readiness-badge"><?= $analysis['ready'] ? 'Ready' : 'Not ready' ?></span>
  </header>
  <div class="lms-readiness-grid">
    <?php foreach ($analysis['groups'] as $group => $groupTitle): ?>
      <?php if (empty($byGroup[$group])) { continue; } ?>
      <?php $groupFails = count(array_filter($byGroup[$group], static fn (array $i): bool => $i['state'] === 'fail')); ?>
      <div class="lms-readiness-group">
        <h4><?= e($groupTitle) ?><span class="<?= $groupFails > 0 ? 'text-danger' : 'text-success' ?>"><?= $groupFails > 0 ? $groupFails . ' to fix' : 'OK' ?></span></h4>
        <ul>
          <?php foreach ($byGroup[$group] as $item): ?>
            <?php
            // Everything the window shows when this line is opened.
            $more = ($item['more'] ?? []) + ['title' => $item['label'], 'state' => $item['state'], 'detail' => $item['detail'], 'group' => $groupTitle];
            ?>
            <li class="is-<?= e($item['state']) ?>">
              <button type="button" class="lms-readiness-item" data-more="<?= e((string) json_encode($more, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                <i class="fe fe-<?= e($icons[$item['state']] ?? 'circle') ?>"></i>
                <div><strong><?= e($item['label']) ?><?php if (!empty($item['more']['file'])): ?> <i class="fe fe-paperclip lms-readiness-clip" title="A scan is attached"></i><?php endif; ?></strong><?php if ($item['detail'] !== ''): ?><span><?= e($item['detail']) ?></span><?php endif; ?></div>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</section>
