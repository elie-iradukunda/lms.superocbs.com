<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var array<string, mixed> $trip
 * @var array<string, mixed> $money   from TripLife::moneyState
 * @var list<array<string, mixed>> $events
 * @var list<array<string, mixed>> $changes
 * @var list<array<string, mixed>> $fuel
 * @var list<array<string, mixed>> $handovers
 * @var list<array<string, mixed>> $requests
 * @var array<string, array> $lists
 */
use Models\Currency;
use Models\Fleet;
use Models\Handover;
use Models\RequestForms;
use Models\TripLife;

$id = (int) $trip['id'];
$canAct = role_can('trips', 'approve') || role_can('trips', 'edit');
$active = in_array($trip['status'], TripLife::ACTIVE, true);
$tone = ['planned' => 'warning', 'ready' => 'info', 'on_road' => 'primary', 'at_turnaround' => 'primary', 'loading' => 'primary', 'offloading' => 'primary', 'returning' => 'info', 'arrived' => 'info', 'closed' => 'success', 'cancelled' => 'secondary'];
$late = $trip['return_due_at'] !== null && strtotime((string) $trip['return_due_at']) < time() && in_array($trip['status'], ['at_turnaround', 'loading', 'on_road', 'offloading', 'returning'], true);
$step = static fn (bool $done, string $label): string => '<li class="list-group-item d-flex justify-content-between"><span>' . e($label) . '</span>' . ($done ? '<i class="fe fe-check-circle text-success"></i>' : '<i class="fe fe-circle text-muted"></i>') . '</li>';
?>
<div class="container-fluid lms-detail-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Trip · <?= e($trip['route_code'] ?? '') ?> <?= e($trip['route_name'] ?? '') ?></p>
      <h2 class="lms-page-title"><i class="fe fe-navigation mr-2"></i><?= e($trip['reference_code']) ?> <span class="badge badge-<?= e($tone[$trip['status']] ?? 'secondary') ?> ml-2"><?= e(TripLife::STATUS[$trip['status']] ?? $trip['status']) ?></span><?= $late ? ' <span class="badge badge-danger">Past the return time</span>' : '' ?></h2>
      <p class="lms-page-sub"><?= e($trip['plate_number'] ?? '') ?><?= $trip['trailer_number'] ? ' + ' . e($trip['trailer_number']) : '' ?> · <?= e($trip['driver_name'] ?? '') ?><?= $trip['driver_phone'] ? ' (' . e($trip['driver_phone']) . ')' : '' ?><?= $trip['customer_name'] ? ' · for ' . e($trip['customer_name']) : '' ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('trips') ?>">Trips</a>
      <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= url(['trips', $id, 'sheet']) ?>"><i class="fe fe-printer fe-12 mr-1"></i>Trip sheet</a>
      <?php if (can_view('tracking')): ?><a class="btn btn-outline-primary" href="<?= url('tracking') ?>"><i class="fe fe-map fe-12 mr-1"></i>Map</a><?php endif; ?>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-8">
      <?php if ($canAct && $active): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-play fe-16 mr-2"></i>Next step</h3><p>The driver taps these in the app; record them here when the driver cannot.</p></div></header>
          <div class="card-body d-flex flex-wrap" style="gap:8px">
            <?php foreach (TripLife::ACTIONS as $action => [$from, $to, $label]): ?>
              <?php if (!in_array($trip['status'], $from, true)) { continue; } ?>
              <form method="post" action="<?= url(['trips', $id, 'act', $action]) ?>" class="d-flex" style="gap:6px"><?= csrf_field() ?>
                <?php if ($action === 'loaded'): ?><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="quantity" placeholder="Loaded" required style="width:110px"><?php endif; ?>
                <?php if (in_array($action, ['depart', 'arrive_base'], true)): ?><input class="form-control form-control-sm" type="number" min="0" name="odometer" placeholder="Odometer" style="width:110px"><?php endif; ?>
                <button class="btn btn-sm btn-primary" type="submit"><?= e($label) ?></button>
              </form>
            <?php endforeach; ?>
            <?php if ($trip['status'] === 'planned'): ?><span class="small text-muted align-self-center">Departure opens once the money is paid and the driver confirms it.</span><?php endif; ?>
            <?php if (in_array($trip['status'], ['returning', 'arrived'], true)): ?><span class="small text-muted align-self-center">The driver ends the trip with the handover in the app; you confirm it.</span><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-info fe-16 mr-2"></i>Trip</h3></div></header>
        <div class="card-body"><dl class="row mb-0 small">
          <?php foreach (array_filter([
              'Route' => trim(($trip['route_code'] ?? '') . ' — ' . ($trip['route_name'] ?? ''), ' —'),
              'Cargo' => trim(($trip['cargo_quantity'] !== null ? rtrim(rtrim(number_format((float) $trip['cargo_quantity'], 2), '0'), '.') . ' ' . ($trip['cargo_type'] ?? '') : '') . ' ' . ($trip['cargo_summary'] ?? '')),
              'Containers' => trim(($trip['container_numbers'] ?? '') . ' ' . (Fleet::CONTAINER_SIZES[$trip['container_size'] ?? ''] ?? '')),
              'Loaded' => $trip['loaded_quantity'] !== null ? rtrim(rtrim(number_format((float) $trip['loaded_quantity'], 2), '0'), '.') : '',
              'Delivered' => $trip['delivered_quantity'] !== null ? rtrim(rtrim(number_format((float) $trip['delivered_quantity'], 2), '0'), '.') : '',
              'Planned departure' => human_date($trip['planned_departure_at'], true),
              'Departed' => human_date($trip['departure_at'], true),
              'At ' . ($trip['turnaround_point'] ?: 'turnaround') => human_date($trip['turnaround_at'], true),
              'Must be back by' => human_date($trip['return_due_at'], true),
              'Expected back' => human_date($trip['expected_back_at'], true),
              'Odometer' => $trip['start_mileage'] ? number_format((int) $trip['start_mileage']) . ($trip['end_mileage'] ? ' → ' . number_format((int) $trip['end_mileage']) : '') . ' km' : '',
              'Kilometres' => $trip['km_driven'] !== null ? number_format((int) $trip['km_driven']) . ' km' : '',
              'Fuel' => $trip['fuel_given_l'] !== null ? rtrim(rtrim((string) $trip['fuel_given_l'], '0'), '.') . ' L given' . ($trip['fuel_expected_l'] !== null ? ', ' . rtrim(rtrim((string) $trip['fuel_expected_l'], '0'), '.') . ' L expected' : '') : '',
              'Service' => $trip['service_due_reason'] ?? '',
              'Notes' => $trip['notes'] ?? '',
          ], static fn ($v): bool => trim((string) $v) !== '') as $label => $value): ?>
            <dt class="col-sm-3"><?= e($label) ?></dt><dd class="col-sm-9"><?= nl2br(e((string) $value)) ?></dd>
          <?php endforeach; ?>
        </dl>
        <?php if (!empty($trip['fuel_flag'])): ?><div class="alert alert-warning mt-2 mb-0 small"><i class="fe fe-droplet mr-1"></i><?= e($trip['fuel_flag']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-file-text fe-16 mr-2"></i>Requests of this trip</h3></div></header>
        <ul class="list-group list-group-flush">
          <?php if ($requests === []): ?><li class="list-group-item text-muted small">None.</li><?php endif; ?>
          <?php foreach ($requests as $request): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><a href="<?= url(['form_requests', $request['id']]) ?>"><strong><?= e($request['form_code'] . ' ' . ($request['request_no'] ?: 'draft #' . $request['id'])) ?></strong></a> <?= e($request['title']) ?></span>
              <span><?= (float) $request['total_amount'] > 0 ? e(Currency::format((float) $request['total_amount'], (string) $request['currency'])) . ' · ' : '' ?><span class="badge badge-light"><?= e(RequestForms::STATUS[$request['status']] ?? $request['status']) ?></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if ($fuel !== []): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-droplet fe-16 mr-2"></i>Fuel orders</h3></div></header>
          <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Order</th><th>Where</th><th>Supplier</th><th class="text-right">Ordered</th><th class="text-right">Given</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($fuel as $order): ?>
              <tr><td><?= e($order['order_code']) ?></td><td><?= e($order['fuel_point'] ?? '') ?></td><td><?= e($order['supplier_name'] ?? '—') ?></td>
                <td class="text-right"><?= e(rtrim(rtrim((string) $order['litres_ordered'], '0'), '.')) ?> L</td>
                <td class="text-right"><?= $order['litres_delivered'] !== null ? e(rtrim(rtrim((string) $order['litres_delivered'], '0'), '.')) . ' L' : '—' ?></td>
                <td><span class="badge badge-light"><?= e(ucfirst($order['status'])) ?></span></td></tr>
            <?php endforeach; ?></tbody>
          </table></div>
        </div>
      <?php endif; ?>

      <?php foreach ($handovers as $handover): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-key fe-16 mr-2"></i>Handover <?= e($handover['handover_code']) ?></h3><p><?= e(ucfirst($handover['status'])) ?><?= $handover['rejection_reason'] ? ': ' . e($handover['rejection_reason']) : '' ?></p></div></header>
          <div class="card-body small">
            Parked at <strong><?= e($handover['parked_at']) ?></strong> · odometer <strong><?= number_format((int) $handover['odometer']) ?> km</strong> · fuel <strong><?= e(Handover::FUEL_LEVELS[$handover['fuel_level']] ?? '') ?></strong> · key with <strong><?= e($handover['key_received_by']) ?></strong>
            <div class="d-flex flex-wrap mt-2" style="gap:8px"><?php foreach (['photo_front', 'photo_back', 'photo_left', 'photo_right'] as $photo): ?><?php if (!empty($handover[$photo])): ?><a target="_blank" rel="noopener" href="<?= e(\Models\Uploads::url($handover[$photo])) ?>"><img src="<?= e(\Models\Uploads::url($handover[$photo])) ?>" alt="" style="height:80px;border-radius:6px"></a><?php endif; ?><?php endforeach; ?></div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($canAct && $active): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-repeat fe-16 mr-2"></i>Replace the driver, the truck or the trailer</h3><p>Breakdown, accident, sickness: the trip keeps one record, with the change, the reason and the time.</p></div></header>
          <form method="post" action="<?= url(['trips', $id, 'replace']) ?>" class="card-body row"><?= csrf_field() ?>
            <div class="col-md-3 form-group"><label>Replace</label><select class="form-control" name="what" id="lms-replace-what"><option value="driver">The driver</option><option value="vehicle">The truck</option><option value="trailer">The trailer</option></select></div>
            <div class="col-md-3 form-group"><label>With</label>
              <select class="form-control lms-replace-list" data-for="driver" name="new_id"><option value="">Choose</option><?php foreach ($lists['drivers'] as $driverId => $driver): ?><?php if ($driverId !== (int) $trip['driver_id']): ?><option value="<?= (int) $driverId ?>"><?= e($driver['label']) ?></option><?php endif; ?><?php endforeach; ?></select>
              <select class="form-control lms-replace-list d-none" data-for="vehicle" disabled name="new_id"><option value="">Choose</option><?php foreach ($lists['vehicles'] as $vehicleId => $vehicle): ?><?php if ($vehicleId !== (int) $trip['vehicle_id']): ?><option value="<?= (int) $vehicleId ?>"><?= e($vehicle['label']) ?></option><?php endif; ?><?php endforeach; ?></select>
              <select class="form-control lms-replace-list d-none" data-for="trailer" disabled name="new_id"><option value="">Choose</option><?php foreach ($lists['trailers'] as $trailerId => $trailer): ?><option value="<?= (int) $trailerId ?>"><?= e($trailer['label']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-2 form-group"><label>Odometer</label><input class="form-control" type="number" min="0" name="odometer"></div>
            <div class="col-md-4 form-group"><label>Reason <span class="lms-required">*</span></label><input class="form-control" name="reason" required></div>
            <div class="col-12 text-right"><button class="btn btn-outline-primary" type="submit">Replace</button></div>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-xl-4">
      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-dollar-sign fe-16 mr-2"></i>Money (control 1)</h3></div></header>
        <ul class="list-group list-group-flush">
          <?= $step($money['authorized'], 'F1 authorized by the MD') ?>
          <?= $step($money['paid'], 'Paid by the Cashier') ?>
          <?= $step($money['confirmed'], 'Driver confirmed the money arrived') ?>
        </ul>
      </div>

      <?php if ($canAct && $active): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-alert-triangle fe-16 mr-2"></i>Delay or problem</h3></div></header>
          <form method="post" action="<?= url(['trips', $id, 'report']) ?>" class="card-body"><?= csrf_field() ?>
            <select class="form-control mb-2" name="kind"><option value="delay">Delay</option><option value="problem">Problem</option></select>
            <textarea class="form-control mb-2" name="note" rows="2" required placeholder="What happened, where"></textarea>
            <button class="btn btn-sm btn-outline-primary" type="submit">Record</button>
          </form>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-3">
        <header class="lms-section-head"><div><h3><i class="fe fe-activity fe-16 mr-2"></i>What happened</h3></div></header>
        <div class="card-body lms-timeline">
          <?php foreach (array_reverse($events) as $event): ?>
            <div class="lms-timeline-item"><span class="lms-timeline-dot"></span><div>
              <strong><?= e(ucfirst(str_replace('_', ' ', (string) $event['event_type']))) ?></strong> <span class="badge badge-light"><?= e($event['source']) ?></span>
              <small class="d-block text-muted"><?= e(human_date($event['happened_at'], true)) ?><?= $event['full_name'] ? ' · ' . e($event['full_name']) : '' ?></small>
              <?php if ($event['note']): ?><div class="small"><?= e($event['note']) ?></div><?php endif; ?>
              <?php if ($event['quantity'] !== null || $event['odometer'] !== null): ?><div class="small text-muted"><?= $event['quantity'] !== null ? e(rtrim(rtrim((string) $event['quantity'], '0'), '.')) : '' ?><?= $event['odometer'] !== null ? ' · ' . number_format((int) $event['odometer']) . ' km' : '' ?></div><?php endif; ?>
            </div></div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($changes !== []): ?>
        <div class="card shadow-sm mb-3">
          <header class="lms-section-head"><div><h3><i class="fe fe-repeat fe-16 mr-2"></i>Changes</h3></div></header>
          <ul class="list-group list-group-flush small"><?php foreach ($changes as $change): ?><li class="list-group-item"><strong><?= e(ucfirst($change['change_type'])) ?> replaced</strong> · <?= e(human_date($change['created_at'], true)) ?> · <?= e($change['by_name'] ?? '') ?><div><?= e($change['reason']) ?></div></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($canAct && in_array($trip['status'], ['planned', 'ready'], true)): ?>
        <form method="post" action="<?= url(['trips', $id, 'cancel']) ?>" class="reason-form" data-reason-title="Cancel <?= e($trip['reference_code']) ?>"><?= csrf_field() ?><input type="hidden" name="reason">
          <button class="btn btn-outline-danger btn-block" type="submit"><i class="fe fe-x fe-12 mr-1"></i>Cancel the trip</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var what = document.getElementById('lms-replace-what');
  if (!what) { return; }
  what.addEventListener('change', function () {
    document.querySelectorAll('.lms-replace-list').forEach(function (list) {
      var on = list.getAttribute('data-for') === what.value;
      list.classList.toggle('d-none', !on); list.disabled = !on;
    });
  });
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
