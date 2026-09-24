<?php
/**
 * Renders a report document on screen.
 *
 * The same array the PDF and the Excel writers read, so what is on the page and
 * what is in the download are the same report, not two that resemble each other.
 *
 * Expects: $doc
 */
$numeric = ['money', 'number', 'decimal'];
?>
<div class="table-responsive">
  <table class="table lms-book">
    <thead>
      <tr>
        <?php foreach ($doc['columns'] as $index => $column): ?>
          <?php $right = ($column['align'] ?? ($index === 0 ? 'left' : 'right')) === 'right'; ?>
          <th class="<?= $right ? 'text-right' : '' ?>"><?= e($column['label']) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if ($doc['rows'] === []): ?>
        <tr><td colspan="<?= count($doc['columns']) ?>" class="lms-empty">
          <i class="fe fe-inbox fe-32"></i>
          <p class="mb-1"><strong>Nothing to report for this period</strong></p>
          <p class="text-muted small mb-0">Widen the dates, or post some entries first.</p>
        </td></tr>
      <?php endif; ?>

      <?php foreach ($doc['rows'] as $row): ?>
        <?php if ($row['type'] === 'blank'): ?>
          <tr class="lms-book-blank"><td colspan="<?= count($doc['columns']) ?>">&nbsp;</td></tr>
          <?php continue; ?>
        <?php endif; ?>

        <tr class="lms-book-<?= e($row['type']) ?>">
          <?php foreach ($doc['columns'] as $index => $column): ?>
            <?php
            $value = $row['cells'][$index] ?? null;
            $isNumber = is_int($value) || is_float($value);
            $right = $isNumber || ($column['align'] ?? ($index === 0 ? 'left' : 'right')) === 'right';
            $indent = $index === 0 ? $row['indent'] : 0;
            ?>
            <td class="<?= $right ? 'text-right lms-num' : '' ?>">
              <?php if ($value === null || $value === ''): ?>
                <?php if ($index === 0 && $indent > 0): ?><span style="padding-left:<?= $indent * 14 ?>px"></span><?php endif; ?>
              <?php elseif ($isNumber): ?>
                <span class="<?= (float) $value < 0 ? 'lms-book-negative' : '' ?>"><?= e(\Support\Report::money($value)) ?></span>
              <?php else: ?>
                <span style="padding-left:<?= $indent * 14 ?>px"><?= e($value) ?></span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
