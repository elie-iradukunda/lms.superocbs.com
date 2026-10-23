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
        $add('Maximum load', $record['max_payload_t'] !== null ? rtrim(rtrim((string) $record['max_payload_t'], '0'), '.') . ' t' : '', 'box');
        $add('Driver', $display['assigned_driver_id'] ?: 'Unassigned', 'user');
        $add('Service', \Models\Fleet::serviceState($record)['label'], 'tool');
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
        // How far the truck has come, from the clock at the start and the latest stop reading.
        $distance = \Models\TripProgress::distance((int) $record['id']);
        if ($distance['start_km'] !== null) {
            $add($distance['end_km'] !== null ? 'Distance driven' : 'Driven so far',
                number_format((int) $distance['driven']) . ' km' . ($distance['last_place'] && $distance['last_place'] !== 'the start' && $distance['end_km'] === null ? ' · at ' . $distance['last_place'] : ''), 'activity');
        }
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
        $add('Delivered', $record['delivered_at'] ? date('d M Y, H:i', (int) strtotime((string) $record['delivered_at'])) : '', 'check');
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
        $add('Location', (string) $record['location'], 'map-pin');
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
        $add('Phone (login)', (string) $record['phone'], 'phone');
        $add('Paid by', $record['payment_method'] === 'cash' ? 'Cash' : 'MoMo ' . (string) $record['momo_number'], 'smartphone');
        $add('Licence', trim((string) $record['license_number'] . ($record['license_expiry'] ? ' · ' . human_date((string) $record['license_expiry']) : '')), 'credit-card');
        $add('Login', !empty($record['user_id']) ? 'Created' : 'Not created (no phone)', 'log-in');
        $add('Last seen in the app', $record['last_seen_at'] ? time_ago((string) $record['last_seen_at']) : 'Never', 'radio');
        break;
}

// Approval and rejection facts, shown wherever the record carries them.
$approvalNotes = [];
// Only where someone decides: on a clearance, approved_at is customs' review clock, shown with the other times.
if (array_key_exists('approved_at', $record) && array_key_exists('approved_by', $record) && $record['approved_at'] !== null) {
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
      // A button that only makes sense when a field is filled in, such as
      // the handover on a trip that is a leg of a journey.
      foreach ($link['when_set'] ?? [] as $field) {
          if (empty($record[$field])) {
              return false;
          }
      }
      return role_can($link['permission'] ?? '', $link['ability'] ?? 'view');
  }));
  ?>

  <?php
  // Steps someone else takes: say who, rather than leaving the buttons out unexplained.
  $whoActs = [];
  if ($actions !== [] && !can_approve($moduleKey)) {
      $people = \Core\Database::connection()->prepare(
          "SELECT r.role_name, GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') AS names
             FROM role_permissions p
             JOIN roles r ON r.id = p.role_id
             LEFT JOIN users u ON u.role_id = r.id AND u.deleted_at IS NULL AND u.status = 'active'
            WHERE p.permission_key = ? AND p.can_approve = 1 AND r.role_key <> 'super_admin'
            GROUP BY r.id, r.role_name ORDER BY r.id"
      );
      $people->execute([$moduleKey]);
      foreach ($people->fetchAll(PDO::FETCH_ASSOC) as $who) {
          // A handful of names helps; every driver in the fleet does not.
          $names = $who['names'] ? explode(', ', (string) $who['names']) : [];
          $whoActs[] = $who['role_name'] . ($names !== [] && count($names) <= 3 ? ' (' . implode(', ', $names) . ')' : '');
      }
  }
  ?>
  <?php if (($actions !== [] && can_approve($moduleKey)) || $links !== [] || $whoActs !== []): ?>
    <div class="card shadow-sm lms-action-bar">
      <div class="card-body">
        <div class="lms-action-bar-inner">
          <div>
            <strong>What happens next</strong>
            <p class="small text-muted mb-0">These buttons also update everything connected to this record.</p>
            <?php if ($whoActs !== []): ?>
              <p class="small mb-0 mt-1"><i class="fe fe-user-check fe-12 mr-1"></i>The next step (<?= e(implode(', ', array_map(static fn ($key) => $module['actions'][$key]['label'] ?? ucfirst($key), array_keys($actions)))) ?>) is taken by: <strong><?= e(implode('; ', $whoActs)) ?></strong>.</p>
            <?php endif; ?>
          </div>
          <div class="lms-action-buttons">
            <?php foreach ($links as $link): ?>
              <?php $query = []; ?>
              <?php foreach ($link['carry'] ?? [] as $param => $column) { $query[$param] = $record[$column] ?? null; } ?>
              <?php // ':id' in a route stands for this record, for pages that belong to it (a delivery note). ?>
              <?php // ':trip_id' and the like stand for that column of this record. ?>
              <?php $route = is_array($link['route']) ? array_map(static fn ($part) => is_string($part) && str_starts_with($part, ':') ? (string) ($record[substr($part, 1)] ?? '') : $part, $link['route']) : $link['route']; ?>
              <?php if (($link['method'] ?? 'get') === 'post'): ?>
                <?php // A link that creates something (an invoice from a trip) must be a POST with its token. ?>
                <form method="post" action="<?= url($route, $query) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-<?= e($link['tone'] ?? 'secondary') ?>" title="<?= e($link['hint'] ?? '') ?>">
                    <?php if (!empty($link['icon'])): ?><i class="fe fe-<?= e($link['icon']) ?> fe-12 mr-1"></i><?php endif; ?><?= e($link['label']) ?>
                  </button>
                </form>
              <?php else: ?>
              <a class="btn btn-<?= e($link['tone'] ?? 'secondary') ?>" href="<?= url($route, $query) ?>" title="<?= e($link['hint'] ?? '') ?>"<?= !empty($link['new_tab']) ? ' target="_blank" rel="noopener"' : '' ?>>
                <?php if (!empty($link['icon'])): ?><i class="fe fe-<?= e($link['icon']) ?> fe-12 mr-1"></i><?php endif; ?><?= e($link['label']) ?>
              </a>
              <?php endif; ?>
            <?php endforeach; ?>

            <?php if (!can_approve($moduleKey)) { $actions = []; } ?>
            <?php foreach ($actions as $actionKey => $rule): ?>
              <?php
              $spec = $module['actions'][$actionKey] ?? [];
              $label = $spec['label'] ?? ucfirst($actionKey);
              foreach ($spec['label_when_set'] ?? [] as $field => $other) {
                  if (!empty($record[$field])) {
                      $label = $other;
                  }
              }
              $buttonTone = $spec['tone'] ?? 'primary';
              $needsReason = !empty($rule['needs_reason']);
              ?>
              <?php if (!empty($spec['page'])): ?>
                <?php // An action with a page of its own first: the email to see and edit before it goes. ?>
                <a class="btn btn-<?= e($buttonTone) ?>" href="<?= url([$moduleKey, $record['id'], $spec['page']]) ?>"><?= e($label) ?></a>
                <?php continue; ?>
              <?php endif; ?>
              <?php if (!empty($spec['form'])): ?>
                <?php // An action that asks for a few fields first opens them in a small window. ?>
                <button type="button" class="btn btn-<?= e($buttonTone) ?>" data-toggle="modal" data-target="#actionForm-<?= e($actionKey) ?>"><?= e($label) ?></button>
                <?php $actionForms[$actionKey] = $spec + ['label' => $label]; ?>
                <?php continue; ?>
              <?php endif; ?>
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

  <?php
  // Who has signed, who is next, and how long they have: each round of
  // approvals on this record, the current one first.
  $approvalRounds = \Models\Approvals::supports($moduleKey) ? \Models\Approvals::timeline($moduleKey, (int) $record['id']) : [];
  if ($approvalRounds !== []):
      $current = $approvalRounds[0];
      $stepTone = ['approved' => 'success', 'pending' => 'warning', 'waiting' => 'light', 'rejected' => 'danger', 'cancelled' => 'secondary'];
      $stepWord = ['approved' => 'Signed', 'pending' => 'Waiting now', 'waiting' => 'Next', 'rejected' => 'Sent back', 'cancelled' => 'Cancelled'];
  ?>
    <div class="card shadow-sm lms-approval-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2" style="gap:8px">
          <strong><i class="fe fe-check-square fe-12 mr-1"></i>Approvals<?= count($approvalRounds) > 1 ? ' · round ' . (int) $current['round'] : '' ?></strong>
          <a class="small" href="<?= url('approvals') ?>">My approvals inbox</a>
        </div>
        <ol class="lms-approval-steps">
          <?php foreach ($current['steps'] as $n => $step): ?>
            <?php $late = $step['status'] === 'pending' && $step['due_at'] !== null && strtotime((string) $step['due_at']) < time(); ?>
            <li class="is-<?= e((string) $step['status']) ?>">
              <span class="lms-approval-num"><?= $step['status'] === 'approved' ? '<i class="fe fe-check"></i>' : ($step['status'] === 'rejected' ? '<i class="fe fe-x"></i>' : $n + 1) ?></span>
              <div>
                <strong><?= e($step['step_label']) ?></strong>
                <span class="d-block small text-muted"><?= e($step['role_name']) ?></span>
                <span class="badge badge-pill badge-<?= e($stepTone[$step['status']] ?? 'light') ?>"><?= e($stepWord[$step['status']] ?? $step['status']) ?></span>
                <?php if ($step['decided_at'] !== null && in_array($step['status'], ['approved', 'rejected'], true)): ?>
                  <span class="d-block small"><?= e($step['decider_name'] ?? '') ?>, <?= e(human_date((string) $step['decided_at'], true)) ?></span>
                <?php elseif ($step['status'] === 'pending' && $step['due_at'] !== null): ?>
                  <span class="d-block small <?= $late ? 'text-danger' : 'text-muted' ?>">Decide by <?= e(human_date((string) $step['due_at'], true)) ?><?= $late ? ' (late)' : '' ?></span>
                <?php endif; ?>
                <?php if (!empty($step['comment'])): ?><span class="d-block small text-muted">“<?= e($step['comment']) ?>”</span><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
        <?php if (count($approvalRounds) > 1): ?>
          <details class="small mt-2"><summary>Earlier rounds</summary>
            <?php foreach (array_slice($approvalRounds, 1) as $round): ?>
              <div class="mt-1">Round <?= (int) $round['round'] ?>:
                <?= e(implode(' · ', array_map(static fn (array $s): string => $s['step_label'] . ' ' . strtolower($stepWord[$s['status']] ?? $s['status']) . ($s['decider_name'] ? ' by ' . $s['decider_name'] : '') . ($s['comment'] ? ' (' . $s['comment'] . ')' : ''), $round['steps']))) ?>
              </div>
            <?php endforeach; ?>
          </details>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($actionForms ?? [] as $formKey => $formSpec): ?>
    <div class="modal fade" id="actionForm-<?= e($formKey) ?>" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
      <form method="post" action="<?= url([$moduleKey, $record['id'], 'action', $formKey]) ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <div>
            <small class="text-muted"><?= e((string) ($record[$module['code']] ?? '')) ?></small>
            <h5 class="modal-title"><?= e($formSpec['form_title'] ?? $formSpec['label']) ?></h5>
          </div>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <?php foreach ($formSpec['form'] as $name): ?>
              <?php
              $field = $module['fields'][$name] ?? null;
              if ($field === null) {
                  continue;
              }
              unset($field['readonly']);
              $value = $record[$name] ?? '';
              // Money given starts as the amount asked for, in its currency.
              if ($name === 'amount_given') {
                  $value = $value !== '' && $value !== null ? $value : ($record['amount_requested'] ?? '');
                  $field['suffix'] = (string) ($record['currency'] ?? '');
              }
              require __DIR__ . '/../partials/field.php';
              ?>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-<?= e($formSpec['tone'] ?? 'primary') ?>"><?= e($formSpec['form_submit'] ?? $formSpec['label']) ?></button>
        </div>
      </form>
    </div></div></div>
  <?php endforeach; ?>

  <?php
  // A driver reads his trip here; what he does on the road is on My trips.
  if ($moduleKey === 'trips' && current_role() === 'driver'): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between" style="gap: 10px">
      <span><i class="fe fe-info mr-2"></i>This trip is planned by the office. Start it, and mark each stop as you reach it, from My trips.</span>
      <a class="btn btn-sm btn-primary" href="<?= url('my-trips') ?>"><i class="fe fe-truck fe-12 mr-1"></i>My trips</a>
    </div>
  <?php endif;

  // Before a truck leaves: everything the system checked, on the check itself
  // and on the trip, next to the Dispatch button.
  $analysis = null;
  if ($moduleKey === 'pre_dispatch_checks' && !empty($record['shipment_id'])) {
      $analysis = \Models\DispatchReadiness::forShipment((int) $record['shipment_id']);
  } elseif ($moduleKey === 'trips' && in_array((string) ($record['status'] ?? ''), ['requested', 'approved', 'loading'], true)) {
      $analysis = \Models\DispatchReadiness::forTrip((int) $record['id']);
  }
  if ($analysis !== null) {
      require __DIR__ . '/../partials/readiness.php';
  }

  // The road the truck has taken, and where it is now.
  if ($moduleKey === 'trips' && in_array((string) ($record['status'] ?? ''), ['approved', 'loading', 'in_transit', 'delivered'], true)):
      $pageScripts = array_merge($pageScripts ?? [], ['/assets/vendor/leaflet/leaflet.js', '/assets/js/lms-map.js']);
      $lastSeen = \Models\Tracking::lastForTrip((int) $record['id']);
  ?>
    <link rel="stylesheet" href="<?= asset('assets/vendor/leaflet/leaflet.css') ?>">
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fe fe-map fe-12 mr-1"></i>Where the truck is</strong>
        <small class="text-muted"><?= $lastSeen !== null ? 'Last position ' . e(time_ago((string) $lastSeen['recorded_at'])) : 'No position reported yet' ?></small>
      </div>
      <div class="lms-map" data-lms-map data-source="<?= e(url(['trips', (int) $record['id'], 'trail'])) ?>" data-refresh="<?= $record['status'] === 'in_transit' ? 60 : 0 ?>"
           data-empty-text="Positions appear here once the driver shares the location on My trips, or the truck's GPS tracker reports."></div>
    </div>
  <?php endif; ?>
  <?php
  ?>

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
                      <?php
                      // A photo is worth seeing without a click: an image file is
                      // shown as a thumbnail that opens full size, while a PDF or
                      // a document keeps the plain "Open" link.
                      $fileUrl = url(array_merge(['files'], explode('/', $shown)));
                      $isImage = in_array(strtolower(pathinfo($shown, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                      ?>
                      <?php if ($isImage): ?>
                        <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener" class="lms-photo-thumb" title="Open <?= e(strtolower($field['label'])) ?> full size">
                          <img src="<?= e($fileUrl) ?>" alt="<?= e($field['label']) ?>" loading="lazy">
                        </a>
                      <?php else: ?>
                        <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener" class="lms-file-link">
                          <i class="fe fe-paperclip fe-12 mr-1"></i>Open <?= e(strtolower($field['label'])) ?>
                        </a>
                      <?php endif; ?>
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

      <?php
      // The line editor (stops, invoice lines, bill lines, parts) and the
      // related lists are wide tables: they are drawn below, across the full
      // width of the page, instead of in the narrow column beside History.
      ob_start();
      ?>
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
                        <?php if (isset($lineSpec['total_column']) && !isset($lineSpec['line_amount'])): ?><th class="text-right">Line total</th><?php endif; ?>
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
                                  <option value=""><?= !empty($spec['required']) ? 'Select...' : 'None' ?></option>
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
                                  type="<?= in_array($spec['type'], ['decimal', 'money'], true) ? 'number' : ($spec['type'] === 'datetime' ? 'datetime-local' : ($spec['type'] === 'date' ? 'date' : 'text')) ?>"
                                  <?= in_array($spec['type'], ['decimal', 'money'], true) ? 'step="0.01"' : '' ?>
                                  name="lines[<?= $index ?>][<?= e($column) ?>]"
                                  value="<?= e($spec['type'] === 'datetime' && $lineValue !== '' ? str_replace(' ', 'T', substr($lineValue, 0, 16)) : $lineValue) ?>">
                              <?php endif; ?>
                            </td>
                          <?php endforeach; ?>
                          <?php if (isset($lineSpec['total_column']) && !isset($lineSpec['line_amount'])): ?><td class="text-right align-middle lms-line-total"><?= isset($line['line_total']) ? e(money((float) $line['line_total'], true)) : '' ?></td><?php endif; ?>
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
                <tbody><?php foreach ($lines as $line): ?><tr><?php foreach ($lineSpec['columns'] as $column => $spec): ?><?php
                  // Read as words and dates, not stored codes.
                  $raw = (string) ($line[$column] ?? '');
                  $shown = match (true) {
                      $raw === '' => '—',
                      ($spec['type'] ?? '') === 'select' => (string) ($spec['options'][$raw] ?? \Models\Schema::label($raw)),
                      ($spec['type'] ?? '') === 'datetime' => date('d M Y, H:i', (int) strtotime($raw)),
                      default => $raw,
                  };
                  ?><td><?= e($shown) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
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
      <?php $wideSections = (string) ob_get_clean(); ?>
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
                  <?php
                  // "workflow.crossings.clear" reads as the button that was pressed: "Mark cleared".
                  $actionName = (string) $entry['action_name'];
                  $parts = explode('.', $actionName);
                  $pressed = count($parts) === 3 && $parts[0] === 'workflow' ? ($module['actions'][$parts[2]]['label'] ?? null) : null;
                  ?>
                  <strong><?= e($pressed ?? ucfirst(str_replace(['.', '_'], ' ', $actionName))) ?></strong>
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

  <?php if (trim($wideSections) !== ''): ?>
    <div class="row">
      <div class="col-12 lms-wide-sections"><?= $wideSections ?></div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
