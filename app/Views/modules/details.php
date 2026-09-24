<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$status = (string) ($record['status'] ?? '');
$statusLabel = $module['fields']['status']['options'][$status] ?? \Models\Schema::label($status);
$tone = \Models\Schema::tone($status);
$fileFields = \Models\LogisticsData::fileFields($moduleKey);

/** Three or four numbers worth seeing before anything else, per module. */
$highlights = [];
/** Money in the currency the record itself names, not the company default. */
$cash = static function (float $amount) use ($record): string {
    $currencyCode = strtoupper(trim((string) ($record['currency'] ?? '')));

    return $currencyCode === '' || $currencyCode === \Models\Currency::base()
        ? money($amount, true)
        : \Models\Currency::format($amount, $currencyCode);
};

$add = static function (string $label, string $value, string $icon) use (&$highlights): void {
    if (trim($value) !== '') {
        $highlights[] = ['label' => $label, 'value' => $value, 'icon' => $icon];
    }
};
switch ($moduleKey) {
    case 'vehicles':
        $add('Odometer', $record['mileage'] !== null ? number_format((float) $record['mileage']) . ' km' : '', 'activity');
        $add('Payload', $record['capacity_kg'] !== null ? number_format((float) $record['capacity_kg']) . ' kg' : '', 'box');
        $add('Driver', $display['assigned_driver_id'] ?: 'Unassigned', 'user');
        $add('Next service', (string) $record['next_service_date'], 'tool');
        break;
    case 'trips':
        $add('Route', trim((string) $record['pickup_location'] . ' to ' . (string) $record['destination'], ' to '), 'map-pin');
        $add('Vehicle', $display['vehicle_id'] ?: 'Unassigned', 'truck');
        $add('Driver', $display['driver_id'] ?: 'Unassigned', 'user');
        $load = \Models\LogisticsData::tripLoad((int) $record['id']);
        $add(
            $load['loads'] === 1 ? 'Load' : $load['loads'] . ' loads',
            $load['capacity'] === null
                ? number_format($load['weight']) . ' kg'
                : number_format($load['weight']) . ' of ' . number_format($load['capacity']) . ' kg',
            'box'
        );
        $add('Planned arrival', (string) $record['planned_arrival_at'], 'clock');
        break;
    case 'shipments':
        $add('Cargo', (string) $record['cargo_description'], 'box');
        $add('Weight', $record['weight_kg'] !== null ? rtrim(rtrim(number_format((float) $record['weight_kg'], 2, '.', ','), '0'), '.') . ' kg' : '', 'bar-chart');
        $add('Packages', (string) $record['packages_count'], 'package');
        $add('Temperature', $record['temperature_min_c'] !== null ? $record['temperature_min_c'] . ' to ' . $record['temperature_max_c'] . ' C' : '', 'thermometer');
        break;
    case 'deliveries':
        $add('Recipient', (string) $record['recipient_name'], 'user');
        $add('Attempt', (string) $record['attempt_number'], 'repeat');
        $add('Delivered', (string) $record['delivered_at'], 'check');
        $add('Proof', $record['proof_file'] ? 'Attached' : 'Missing', 'paperclip');
        break;
    case 'invoices':
        $add('Total', $cash((float) $record['total_amount']), 'dollar-sign');
        $add('Paid', $cash((float) $record['amount_paid']), 'check-circle');
        $add('Balance', $cash((float) $record['total_amount'] - (float) $record['amount_paid']), 'alert-circle');
        $add('Due', (string) $record['due_date'], 'calendar');
        break;
    case 'expenses':
        $add('Amount', $cash((float) $record['amount']), 'dollar-sign');
        $add('Category', (string) $record['category'], 'tag');
        $add('Trip', $display['trip_id'] ?: 'Not linked', 'navigation');
        $add('Date', (string) $record['expense_date'], 'calendar');
        break;
    case 'fuel':
        $add('Litres', rtrim(rtrim(number_format((float) $record['litres'], 2, '.', ''), '0'), '.') . ' L', 'droplet');
        $add('Total cost', money((float) $record['litres'] * (float) $record['unit_price'], true), 'dollar-sign');
        $add('Odometer', $record['mileage'] !== null ? number_format((float) $record['mileage']) . ' km' : '', 'activity');
        $add('Vehicle', $display['vehicle_id'], 'truck');
        break;
    case 'warehouse':
        $add('On hand', rtrim(rtrim(number_format((float) $record['quantity'], 2, '.', ''), '0'), '.') . ' ' . (string) $record['unit_of_measure'], 'package');
        $add('Minimum', rtrim(rtrim(number_format((float) $record['minimum_level'], 2, '.', ''), '0'), '.'), 'alert-triangle');
        $add('Stock value', money((float) $record['quantity'] * (float) $record['unit_cost'], true), 'dollar-sign');
        $add('Warehouse', $display['warehouse_id'], 'home');
        break;
    case 'maintenance':
        $add('Estimated', money((float) $record['estimated_cost'], true), 'file-text');
        $add('Actual', $record['actual_cost'] !== null ? money((float) $record['actual_cost'], true) : 'Not costed', 'dollar-sign');
        $add('Vehicle', $display['vehicle_id'], 'truck');
        $add('Due', (string) $record['due_date'], 'calendar');
        break;
    case 'warehouses':
        // What is physically in the shed, which is the question a depot manager
        // is actually asked. Our own stock is counted separately below.
        $held = \Models\CargoCustody::heldAt((int) $record['id']);
        $add('Location', (string) $record['location'], 'map-pin');
        $add('Cargo held', $held['loads'] === 0 ? 'Empty' : $held['loads'] . ($held['loads'] === 1 ? ' consignment' : ' consignments'), 'box');
        $add('Weight in the shed', $held['weight'] > 0 ? rtrim(rtrim(number_format($held['weight'], 2, '.', ','), '0'), '.') . ' kg' : '', 'bar-chart');
        $add('Packages', $held['packages'] > 0 ? number_format($held['packages']) : '', 'package');
        break;
    case 'customers':
        $add('Type', $statusLabel === '' ? '' : ($module['fields']['customer_type']['options'][$record['customer_type']] ?? ''), 'briefcase');
        $add('Contact', (string) $record['contact_name'], 'user');
        $add('Terms', (string) $record['payment_terms_days'] . ' days', 'calendar');
        $add('Credit limit', $record['credit_limit'] !== null ? $cash((float) $record['credit_limit']) : 'None set', 'dollar-sign');
        break;
    case 'requests':
        $add('Route', trim((string) $record['pickup_location'] . ' to ' . (string) $record['destination'], ' to '), 'map-pin');
        $add('Required', (string) $record['required_date'], 'calendar');
        $add('Priority', $module['fields']['priority']['options'][$record['priority']] ?? '', 'flag');
        $add('Weight', $record['weight_kg'] !== null ? rtrim(rtrim(number_format((float) $record['weight_kg'], 2, '.', ','), '0'), '.') . ' kg' : '', 'bar-chart');
        break;
    case 'drivers':
        $add('Licence', (string) $record['license_number'], 'credit-card');
        $add('Expires', (string) $record['license_expiry'], 'calendar');
        $add('Phone', (string) $record['phone'], 'phone');
        $add('Login', $display['user_id'] ?: 'Not linked', 'log-in');
        break;
}

// Approval and rejection facts, shown wherever the record carries them.
$approvalNotes = [];
if (array_key_exists('approved_at', $record) && $record['approved_at'] !== null) {
    $approver = '';
    if (!empty($record['approved_by'])) {
        $approver = (string) (\Core\Database::connection()->query('SELECT full_name FROM users WHERE id = ' . (int) $record['approved_by'])->fetchColumn() ?: '');
    }
    $approvalNotes[] = ['label' => 'Decided by', 'value' => $approver !== '' ? $approver . ' on ' . $record['approved_at'] : (string) $record['approved_at']];
}
if (!empty($record['rejection_reason'])) {
    $approvalNotes[] = ['label' => 'Rejection reason', 'value' => (string) $record['rejection_reason']];
}
?>
<div class="container-fluid lms-detail-page">

  <div class="lms-page-head">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb lms-crumbs">
          <li class="breadcrumb-item"><a href="<?= url($moduleKey) ?>"><?= e($module['title']) ?></a></li>
          <li class="breadcrumb-item active" aria-current="page"><?= e($code) ?></li>
        </ol>
      </nav>
      <h2 class="lms-page-title">
        <i class="fe fe-<?= e($module['icon']) ?> mr-2"></i><?= e($code) ?>
        <?php if ($status !== ''): ?><span class="badge badge-<?= e($tone) ?> lms-status-badge"><?= e($statusLabel) ?></span><?php endif; ?>
      </h2>
      <p class="lms-page-sub"><?= e($module['kicker']) ?> &middot; created <?= e(time_ago($record['created_at'] ?? null)) ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url($moduleKey) ?>"><i class="fe fe-arrow-left fe-12 mr-1"></i>Back</a>
      <?php if (can_edit($moduleKey)): ?>
        <a class="btn btn-primary" href="<?= url([$moduleKey, $record['id'], 'edit']) ?>"><i class="fe fe-edit-2 fe-12 mr-1"></i>Edit</a>
      <?php endif; ?>
      <?php if (can_delete($moduleKey)): ?>
        <form method="post" action="<?= url([$moduleKey, $record['id'], 'delete']) ?>" class="d-inline delete-form">
          <?= csrf_field() ?>
          <input type="hidden" name="reason">
          <button type="submit" class="btn btn-outline-danger"><i class="fe fe-trash-2 fe-12 mr-1"></i>Delete</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php
  /**
   * Links are not status changes: they carry this record into another form.
   * They are shown beside the workflow buttons because that is where someone
   * looks for "what happens next", but pressing one changes nothing by itself.
   */
  $links = array_values(array_filter($module['links'], static function (array $link) use ($record): bool {
      foreach ($link['when'] ?? [] as $field => $allowed) {
          if (!in_array((string) ($record[$field] ?? ''), $allowed, true)) {
              return false;
          }
      }
      return role_can($link['permission'] ?? '', $link['ability'] ?? 'view');
  }));
  ?>

  <?php if (($actions !== [] && can_approve($moduleKey)) || $links !== []): ?>
    <div class="card shadow-sm lms-action-bar">
      <div class="card-body">
        <div class="lms-action-bar-inner">
          <div>
            <strong>What happens next</strong>
            <p class="small text-muted mb-0">These buttons also update everything connected to this record.</p>
          </div>
          <div class="lms-action-buttons">
            <?php foreach ($links as $link): ?>
              <?php $query = []; ?>
              <?php foreach ($link['carry'] ?? [] as $param => $column) { $query[$param] = $record[$column] ?? null; } ?>
              <a class="btn btn-<?= e($link['tone'] ?? 'secondary') ?>" href="<?= url($link['route'], $query) ?>" title="<?= e($link['hint'] ?? '') ?>">
                <?php if (!empty($link['icon'])): ?><i class="fe fe-<?= e($link['icon']) ?> fe-12 mr-1"></i><?php endif; ?><?= e($link['label']) ?>
              </a>
            <?php endforeach; ?>

            <?php if (!can_approve($moduleKey)) { $actions = []; } ?>
            <?php foreach ($actions as $actionKey => $rule): ?>
              <?php
              $spec = $module['actions'][$actionKey] ?? [];
              $label = $spec['label'] ?? ucfirst($actionKey);
              $buttonTone = $spec['tone'] ?? 'primary';
              $needsReason = !empty($rule['needs_reason']);
              ?>
              <form method="post" action="<?= url([$moduleKey, $record['id'], 'action', $actionKey]) ?>" class="d-inline <?= $needsReason ? 'reason-form' : '' ?>" data-reason-title="<?= e($label) ?>">
                <?= csrf_field() ?>
                <?php if ($needsReason): ?><input type="hidden" name="reason"><?php endif; ?>
                <button type="submit" class="btn btn-<?= e($buttonTone) ?>"><?= e($label) ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($highlights !== []): ?>
    <div class="lms-highlights">
      <?php foreach ($highlights as $highlight): ?>
        <div class="lms-highlight">
          <span class="lms-highlight-icon"><i class="fe fe-<?= e($highlight['icon']) ?> fe-16"></i></span>
          <div>
            <small><?= e($highlight['label']) ?></small>
            <strong><?= e($highlight['value']) ?></strong>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-xl-8">

      <?php foreach ($module['sections'] as $section): ?>
        <?php
        $visible = array_filter($section['fields'], static fn (string $name): bool => isset($module['fields'][$name]));
        if ($visible === []) {
            continue;
        }
        ?>
        <section class="card shadow-sm lms-section">
          <header class="lms-section-head">
            <div>
              <h3><i class="fe fe-<?= e($section['icon'] ?? 'circle') ?> fe-16 mr-2"></i><?= e($section['title']) ?></h3>
              <?php if (!empty($section['hint'])): ?><p><?= e($section['hint']) ?></p><?php endif; ?>
            </div>
          </header>
          <div class="card-body">
            <dl class="row lms-facts mb-0">
              <?php foreach ($visible as $name): ?>
                <?php
                $field = $module['fields'][$name];
                $shown = $display[$name] ?? '';
                ?>
                <dt class="col-sm-4"><?= e($field['label']) ?></dt>
                <dd class="col-sm-8">
                  <?php if ($field['type'] === 'file'): ?>
                    <?php if ($shown !== ''): ?>
                      <a href="<?= e(url(array_merge(['files'], explode('/', $shown)))) ?>" target="_blank" rel="noopener" class="lms-file-link">
                        <i class="fe fe-paperclip fe-12 mr-1"></i>Open <?= e(strtolower($field['label'])) ?>
                      </a>
                    <?php else: ?>
                      <span class="text-muted">Not attached</span>
                    <?php endif; ?>
                  <?php elseif ($name === 'status' && $shown !== ''): ?>
                    <span class="badge badge-<?= e($tone) ?>"><?= e($shown) ?></span>
                  <?php elseif ($shown === ''): ?>
                    <span class="text-muted">Not set</span>
                  <?php elseif ($field['type'] === 'money'): ?>
                    <?php
                    /* A record that says which money it is must be shown in that
                       money. Otherwise a Nairobi quotation reads "RWF" beside a
                       field that says "KES", and the reader has to guess which
                       one is lying. */
                    $currencyCode = strtoupper(trim((string) ($record['currency'] ?? '')));
                    ?>
                    <?= e($currencyCode === '' || $currencyCode === \Models\Currency::base()
                        ? money((float) $record[$name], true)
                        : \Models\Currency::format((float) $record[$name], $currencyCode)) ?>
                  <?php elseif ($field['type'] === 'decimal'): ?>
                    <?php /* 10000.00 is a database value; 10,000 is a weight. */ ?>
                    <?= e(rtrim(rtrim(number_format((float) $record[$name], 2, '.', ','), '0'), '.')) ?><?= !empty($field['suffix']) ? ' ' . e($field['suffix']) : '' ?>
                  <?php elseif ($field['type'] === 'textarea'): ?>
                    <span class="lms-longtext"><?= nl2br(e($shown)) ?></span>
                  <?php else: ?>
                    <?= e($shown) ?><?= !empty($field['suffix']) ? ' ' . e($field['suffix']) : '' ?>
                  <?php endif; ?>
                </dd>
              <?php endforeach; ?>
            </dl>
          </div>
        </section>
      <?php endforeach; ?>

      <?php if (!empty($module['lines'])): ?>
        <?php $lineSpec = $module['lines']; ?>
        <section class="card shadow-sm lms-section">
          <header class="lms-section-head">
            <div>
              <h3><i class="fe fe-list fe-16 mr-2"></i><?= e($lineSpec['title']) ?></h3>
              <?php if (isset($lineSpec['hint'])): ?><p><?= e($lineSpec['hint']) ?></p><?php endif; ?>
              <?php if (isset($lineSpec['total_column'])): ?><p>The total is written back to the record whenever these lines are saved.</p><?php endif; ?>
            </div>
          </header>
          <div class="card-body">
            <?php if (can_edit($moduleKey)): ?>
              <form method="post" action="<?= url([$moduleKey, $record['id'], 'lines']) ?>" class="lms-lines" data-next-index="<?= count($lines) + 3 ?>">
                <?= csrf_field() ?>
                <div class="table-responsive">
                  <table class="table table-sm lms-lines-table">
                    <thead>
                      <tr>
                        <?php foreach ($lineSpec['columns'] as $column => $spec): ?>
                          <th><?= e($spec['label']) ?><?= !empty($spec['required']) ? ' <span class="lms-required">*</span>' : '' ?></th>
                        <?php endforeach; ?>
                        <?php if (isset($lineSpec['total_column'])): ?><th class="text-right">Line total</th><?php endif; ?>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $rows = $lines;
                      for ($i = 0; $i < 3; $i++) {
                          $rows[] = [];
                      }
                      foreach ($rows as $index => $line):
                      ?>
                        <tr class="lms-line-row">
                          <?php foreach ($lineSpec['columns'] as $column => $spec): ?>
                            <td>
                              <?php $lineValue = (string) ($line[$column] ?? ''); ?>
                              <?php if ($spec['type'] === 'select'): ?>
                                <select class="form-control form-control-sm" name="lines[<?= $index ?>][<?= e($column) ?>]">
                                  <?php foreach ($spec['options'] as $optionValue => $optionLabel): ?>
                                    <option value="<?= e($optionValue) ?>" <?= $lineValue === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                  <?php endforeach; ?>
                                </select>
                              <?php elseif ($spec['type'] === 'relation'): ?>
                                <select class="form-control form-control-sm" name="lines[<?= $index ?>][<?= e($column) ?>]">
                                  <option value="">None</option>
                                  <?php foreach (\Models\LogisticsData::relationOptions($spec['relation']) as $optionValue => $optionLabel): ?>
                                    <option value="<?= e($optionValue) ?>" <?= $lineValue === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                  <?php endforeach; ?>
                                </select>
                              <?php else: ?>
                                <input
                                  class="form-control form-control-sm <?= in_array($spec['type'], ['decimal', 'money'], true) ? 'lms-line-number' : '' ?>"
                                  type="<?= in_array($spec['type'], ['decimal', 'money'], true) ? 'number' : ($spec['type'] === 'datetime' ? 'datetime-local' : 'text') ?>"
                                  <?= in_array($spec['type'], ['decimal', 'money'], true) ? 'step="0.01"' : '' ?>
                                  name="lines[<?= $index ?>][<?= e($column) ?>]"
                                  value="<?= e($spec['type'] === 'datetime' && $lineValue !== '' ? str_replace(' ', 'T', substr($lineValue, 0, 16)) : $lineValue) ?>">
                              <?php endif; ?>
                            </td>
                          <?php endforeach; ?>
                          <?php if (isset($lineSpec['total_column'])): ?><td class="text-right align-middle lms-line-total"><?= isset($line['line_total']) ? e(money((float) $line['line_total'], true)) : '' ?></td><?php endif; ?>
                          <td class="text-right align-middle"><button type="button" class="btn btn-link btn-sm text-danger p-0 lms-line-clear" title="Clear this line">&times;</button></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <button type="button" class="btn btn-sm btn-outline-secondary lms-line-add"><i class="fe fe-plus fe-12 mr-1"></i>Add row</button>
                  <button type="submit" class="btn btn-sm btn-primary"><i class="fe fe-save fe-12 mr-1"></i>Save <?= e(strtolower($lineSpec['title'])) ?></button>
                </div>
                <p class="small text-muted mt-2 mb-0">Empty rows are ignored. Clearing a row and saving removes it.</p>
              </form>
            <?php elseif ($lines === []): ?>
              <?php // Read-only and empty. Say which of the two it is, or the reader
                    // is left wondering where the form went. ?>
              <p class="text-muted mb-0">
                Nothing recorded yet.
                <?= e(role_label()) ?> may read this record but not change it, so there is
                no form here. Ask someone who can edit <?= e(strtolower($module['title'])) ?>,
                or have an administrator grant your role the <strong>edit</strong> right
                on <?= e($module['title']) ?> under Role permissions.
              </p>
            <?php else: ?>
              <div class="table-responsive"><table class="table table-sm">
                <thead><tr><?php foreach ($lineSpec['columns'] as $spec): ?><th><?= e($spec['label']) ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ($lines as $line): ?><tr><?php foreach ($lineSpec['columns'] as $column => $spec): ?><td><?= e((string) ($line[$column] ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
              </table></div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php foreach ($related as $block): ?>
        <section class="card shadow-sm lms-section">
          <header class="lms-section-head">
            <div>
              <h3><i class="fe fe-<?= e($block['icon'] ?? 'link') ?> fe-16 mr-2"></i><?= e($block['title']) ?></h3>
            </div>
            <?php if (!empty($block['module']) && can_view($block['module'])): ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= url($block['module']) ?>">Open module</a>
            <?php endif; ?>
          </header>
          <div class="card-body">
            <?php if ($block['rows'] === []): ?>
              <p class="text-muted mb-0"><?= e($block['empty'] ?? 'Nothing linked yet.') ?></p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 lms-related-table">
                  <thead><tr><?php foreach ($block['columns'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                  <tbody>
                    <?php foreach ($block['rows'] as $row): ?>
                      <tr>
                        <?php $first = true; ?>
                        <?php foreach ($block['columns'] as $column => $label): ?>
                          <td>
                            <?php
                            $cell = (string) ($row[$column] ?? '');
                            $isStatus = in_array($column, ['status', 'priority', 'movement_type'], true);
                            ?>
                            <?php if ($cell === ''): ?>
                              <span class="text-muted">&mdash;</span>
                            <?php elseif ($isStatus): ?>
                              <span class="badge badge-<?= e(\Models\Schema::tone($cell)) ?>"><?= e(\Models\Schema::label($cell)) ?></span>
                            <?php elseif ($first && !empty($block['module']) && isset($row['id']) && can_view($block['module'])): ?>
                              <a href="<?= url([$block['module'], $row['id']]) ?>"><strong><?= e($cell) ?></strong></a>
                            <?php else: ?>
                              <?= e($cell) ?>
                            <?php endif; ?>
                          </td>
                          <?php $first = false; ?>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="col-xl-4">
      <?php if ($approvalNotes !== []): ?>
        <div class="card shadow-sm lms-section">
          <header class="lms-section-head"><div><h3><i class="fe fe-check-circle fe-16 mr-2"></i>Decision</h3></div></header>
          <div class="card-body">
            <dl class="row lms-facts mb-0">
              <?php foreach ($approvalNotes as $note): ?>
                <dt class="col-sm-5"><?= e($note['label']) ?></dt>
                <dd class="col-sm-7"><?= e($note['value']) ?></dd>
              <?php endforeach; ?>
            </dl>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($fileFields !== []): ?>
        <div class="card shadow-sm lms-section">
          <header class="lms-section-head"><div><h3><i class="fe fe-paperclip fe-16 mr-2"></i>Attachments</h3></div></header>
          <div class="card-body">
            <ul class="list-unstyled mb-0 lms-attachments">
              <?php foreach ($fileFields as $name => $field): ?>
                <?php $path = (string) ($record[$name] ?? ''); ?>
                <li>
                  <i class="fe fe-<?= $path === '' ? 'x-circle text-muted' : 'file-text text-success' ?> fe-16 mr-2"></i>
                  <span><?= e($field['label']) ?></span>
                  <?php if ($path !== ''): ?>
                    <a href="<?= e(url(array_merge(['files'], explode('/', $path)))) ?>" target="_blank" rel="noopener">Open</a>
                  <?php else: ?>
                    <em class="text-muted">Missing</em>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm lms-section">
        <header class="lms-section-head"><div><h3><i class="fe fe-clock fe-16 mr-2"></i>History</h3><p>Every recorded action on <?= e($code) ?>.</p></div></header>
        <div class="card-body lms-timeline">
          <?php if ($history === []): ?>
            <p class="text-muted mb-0">Nothing has been recorded against this record yet.</p>
          <?php else: ?>
            <?php foreach ($history as $entry): ?>
              <div class="lms-timeline-item">
                <span class="lms-timeline-dot"></span>
                <div>
                  <strong><?= e(ucfirst(str_replace(['.', '_'], ' ', (string) $entry['action_name']))) ?></strong>
                  <small class="d-block text-muted"><?= e($entry['actor']) ?> &middot; <?= e(time_ago($entry['created_at'])) ?></small>
                  <?php if (!empty($entry['reason'])): ?><em class="d-block small"><?= e($entry['reason']) ?></em><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
