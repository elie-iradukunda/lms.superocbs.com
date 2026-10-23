<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var list<array<string, mixed>> $routes
 * @var ?array $selectedRoute
 * @var string $departure
 * @var array{vehicles: list<array>, trailers: list<array>, drivers: list<array>} $candidates
 * @var array<string, array> $standards per fleet
 * @var array<int, array> $customers
 * @var array<string, mixed> $plan
 * @var list<string> $errors
 */
use Models\Fleet;

$rows = $plan['rows'] ?? [[]];
if ($rows === []) {
    $rows = [[]];
}
$option = static function (array $record, string $label, string $current, array $data = []): string {
    $problems = $record['problems'] ?? [];
    $attributes = ' data-problems="' . e((string) json_encode($problems)) . '"';
    foreach ($data as $key => $value) {
        $attributes .= ' data-' . $key . '="' . e((string) $value) . '"';
    }

    return '<option value="' . (int) $record['id'] . '"' . $attributes . ((string) $record['id'] === $current ? ' selected' : '') . '>'
        . e($label . ($problems !== [] ? '  ⚠ ' . count($problems) : '')) . '</option>';
};
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Trips</p>
      <h2 class="lms-page-title"><i class="fe fe-navigation mr-2"></i>New trip</h2>
      <p class="lms-page-sub">Pick the route and cargo, then each truck with its trailer and driver.</p>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= url('trips') ?>">Trips</a></div>
  </div>

  <?php if ($errors !== []): ?>
    <div class="alert alert-danger lms-error-block"><strong><i class="fe fe-alert-circle mr-2"></i>The trip was not created</strong><ul class="mb-0 mt-2"><?php foreach (preg_split('/(?<=\.)\s+/', implode(' ', $errors)) as $message): ?><?php if (trim($message) !== ''): ?><li><?= e($message) ?></li><?php endif; ?><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="<?= url('trip_planner') ?>" id="lms-planner" class="lms-form">
    <?= csrf_field() ?>
    <section class="card shadow-sm lms-section mb-3">
      <header class="lms-section-head"><div><span class="lms-section-num">1</span><div><h3><i class="fe fe-map fe-16 mr-2"></i>Route and cargo</h3></div></div></header>
      <div class="card-body"><div class="row">
        <div class="col-md-4 form-group"><label>Route <span class="lms-required">*</span></label>
          <select class="form-control" name="route_id" id="lms-route" required>
            <option value="">Choose the route</option>
            <?php foreach ($routes as $item): ?><option value="<?= (int) $item['id'] ?>" <?= $selectedRoute !== null && (int) $selectedRoute['id'] === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['route_code'] . ' — ' . $item['route_name']) ?></option><?php endforeach; ?>
          </select>
          <?php if ($routes === []): ?><small class="text-warning">No route yet: Admin adds them under Trips &rarr; Routes.</small><?php endif; ?>
        </div>
        <div class="col-md-3 form-group"><label>Departure</label><input class="form-control" type="datetime-local" name="departure_at" id="lms-departure" value="<?= e($departure) ?>"></div>
        <div class="col-md-5 form-group"><label>Customer</label>
          <select class="form-control" name="customer_id"><option value="">None (own cargo, empty run)</option><?php foreach ($customers as $customerId => $customer): ?><option value="<?= (int) $customerId ?>" <?= (string) ($plan['customer_id'] ?? '') === (string) $customerId ? 'selected' : '' ?>><?= e($customer['label']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 form-group"><label>Cargo in <span class="lms-required">*</span></label>
          <select class="form-control" name="cargo_type" id="lms-cargo-type" required>
            <?php foreach (['' => 'Choose', 'litres' => 'Litres (tanker)', 'tonnes' => 'Tonnes', 'containers' => 'Containers', 'other' => 'Other / empty'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($plan['cargo_type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-md-3 form-group"><label>Quantity per truck</label><input class="form-control" type="number" min="0" step="0.01" name="cargo_quantity" value="<?= e($plan['cargo_quantity'] ?? '') ?>" placeholder="36000"></div>
        <div class="col-md-6 form-group"><label>Cargo</label><input class="form-control" name="cargo_summary" value="<?= e($plan['cargo_summary'] ?? '') ?>" placeholder="Diesel for Jambo oil factory"></div>
      </div>
      <?php if ($selectedRoute !== null): ?>
        <p class="small text-muted mb-0"><i class="fe fe-info fe-12 mr-1"></i><?= e($selectedRoute['route_code']) ?>: expected <?= e(rtrim(rtrim((string) $selectedRoute['expected_days'], '0'), '.')) ?> days<?= $selectedRoute['max_return_days'] !== null ? ', at most ' . e(rtrim(rtrim((string) $selectedRoute['max_return_days'], '0'), '.')) . ' days back from ' . e($selectedRoute['turnaround_point'] ?: 'the turnaround point') : '' ?>.
          <?php $all = $standards['all'] ?? []; ?>
          Rate card: <?= $all['fuel'] ? e(rtrim(rtrim((string) $all['fuel'], '0'), '.')) . ' L at ' . e($all['fuel_point']) : 'no departure fuel' ?><?= ($all['top_up'] ?? []) !== [] ? ' + ' . e(implode(', ', $all['top_up'])) . ' as top-up' : '' ?>; road toll <?= $all['road_toll'] !== null ? e(number_format((float) $all['road_toll'])) . ' ' . e($all['road_toll_currency']) : '—' ?>; mileage <?= $all['mileage'] !== null ? e(number_format((float) $all['mileage'])) . ' ' . e($all['mileage_currency']) : '—' ?>.</p>
      <?php endif; ?>
      </div>
    </section>

    <section class="card shadow-sm lms-section mb-3">
      <header class="lms-section-head"><div><span class="lms-section-num">2</span><div><h3><i class="fe fe-truck fe-16 mr-2"></i>Trucks, trailers and drivers</h3><p>A ⚠ means that truck cannot go — pick it to see why.</p></div></div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="lms-add-truck"><i class="fe fe-plus fe-12 mr-1"></i>Add a truck</button></header>
      <div class="table-responsive">
        <table class="table mb-0 lms-planner-rows">
          <thead><tr><th>Truck</th><th>Trailer</th><th>Driver</th><th class="lms-col-qty">Load</th><th class="lms-col-container">Container</th><th>Fuel (L)</th><th>Road toll</th><th>Mileage</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $index => $row): ?>
            <tr>
              <td style="min-width:150px">
                <select class="form-control" name="rows[<?= $index ?>][vehicle_id]" data-role="vehicle"><option value="">Truck</option>
                  <?php foreach ($candidates['vehicles'] as $vehicle): ?><?= $option($vehicle, $vehicle['plate_number'] . ' (' . (Fleet::FLEETS[$vehicle['fleet']] ?? $vehicle['fleet']) . ')', (string) ($row['vehicle_id'] ?? ''), ['fleet' => $vehicle['fleet'], 'trailer' => $vehicle['trailer_id'] ?? '', 'driver' => $vehicle['assigned_driver_id'] ?? '', 'service' => $vehicle['service']['state'], 'service-label' => $vehicle['service']['label']]) ?><?php endforeach; ?>
                </select>
                <input class="form-control form-control-sm mt-1 lms-service-reason" name="rows[<?= $index ?>][service_due_reason]" value="<?= e($row['service_due_reason'] ?? '') ?>" placeholder="Service due: why it will be serviced on return" style="display:none">
              </td>
              <td style="min-width:130px"><select class="form-control" name="rows[<?= $index ?>][trailer_id]" data-role="trailer"><option value="">No trailer</option><?php foreach ($candidates['trailers'] as $trailer): ?><?= $option($trailer, $trailer['trailer_number'], (string) ($row['trailer_id'] ?? '')) ?><?php endforeach; ?></select></td>
              <td style="min-width:170px"><select class="form-control" name="rows[<?= $index ?>][driver_id]" data-role="driver"><option value="">Driver</option><?php foreach ($candidates['drivers'] as $driver): ?><?= $option($driver, $driver['full_name'] . ($driver['payment_method'] === 'cash' ? ' (cash)' : ''), (string) ($row['driver_id'] ?? '')) ?><?php endforeach; ?></select></td>
              <td class="lms-col-qty" style="min-width:90px"><input class="form-control" type="number" min="0" step="0.01" name="rows[<?= $index ?>][cargo_quantity]" value="<?= e($row['cargo_quantity'] ?? '') ?>" placeholder="As above"></td>
              <td class="lms-col-container" style="min-width:170px"><input class="form-control mb-1" name="rows[<?= $index ?>][container_numbers]" value="<?= e($row['container_numbers'] ?? '') ?>" placeholder="MSKU1234567"><select class="form-control" name="rows[<?= $index ?>][container_size]"><option value="">Size</option><?php foreach (Fleet::CONTAINER_SIZES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($row['container_size'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
              <td style="min-width:90px"><input class="form-control lms-std" data-std="fuel" type="number" min="0" step="1" name="rows[<?= $index ?>][fuel_litres]" value="<?= e($row['fuel_litres'] ?? '') ?>"></td>
              <td style="min-width:110px"><input class="form-control lms-std" data-std="road_toll" type="number" min="0" step="1" name="rows[<?= $index ?>][road_toll]" value="<?= e($row['road_toll'] ?? '') ?>"></td>
              <td style="min-width:110px"><input class="form-control lms-std" data-std="mileage" type="number" min="0" step="1" name="rows[<?= $index ?>][mileage]" value="<?= e($row['mileage'] ?? '') ?>"></td>
              <td><button type="button" class="btn btn-sm btn-link text-danger lms-remove-truck" title="Remove"><i class="fe fe-trash-2"></i></button></td>
            </tr>
            <tr class="lms-row-problems"><td colspan="9" class="pt-0 border-top-0"><ul class="small text-danger mb-0"></ul></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body border-top">
        <div class="form-group mb-2"><label>Why an amount differs from the rate card</label><input class="form-control" name="non_standard_reason" value="<?= e($plan['non_standard_reason'] ?? '') ?>" placeholder="Only when you change fuel, road toll or mileage: detour, waiting, load"></div>
        <div class="form-group mb-0"><label>Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($plan['notes'] ?? '') ?></textarea></div>
      </div>
    </section>

    <div class="alert d-none" id="lms-plan-check"></div>

    <div class="lms-form-bar">
      <button type="button" class="btn btn-outline-primary" id="lms-check"><i class="fe fe-check-circle fe-12 mr-1"></i>Check the plan</button>
      <button type="submit" class="btn btn-primary"><i class="fe fe-send fe-12 mr-1"></i>Create trip &amp; send money request</button>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('lms-planner');
  var standards = <?= json_encode($standards, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var body = form.querySelector('.lms-planner-rows tbody');

  document.getElementById('lms-route').addEventListener('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('route_id', this.value);
    url.searchParams.set('departure_at', document.getElementById('lms-departure').value);
    window.location.href = url.toString();
  });

  function cargoColumns() {
    var type = document.getElementById('lms-cargo-type').value;
    form.querySelectorAll('.lms-col-container').forEach(function (cell) { cell.style.display = type === 'containers' ? '' : 'none'; });
  }
  document.getElementById('lms-cargo-type').addEventListener('change', cargoColumns);
  cargoColumns();

  function rowProblems(row) {
    var list = row.nextElementSibling.querySelector('ul');
    list.innerHTML = '';
    row.querySelectorAll('select[data-role]').forEach(function (select) {
      var option = select.selectedOptions[0];
      if (!option || !option.dataset.problems) { return; }
      JSON.parse(option.dataset.problems).forEach(function (text) { var li = document.createElement('li'); li.textContent = text; list.appendChild(li); });
    });
    var truck = row.querySelector('select[data-role=vehicle]').selectedOptions[0];
    var reason = row.querySelector('.lms-service-reason');
    reason.style.display = truck && truck.dataset.service === 'due' ? '' : 'none';
    if (truck && truck.dataset.service === 'due') { reason.placeholder = truck.dataset.serviceLabel + ' — why it will be serviced on return'; }
  }

  function fillStandards(row) {
    var truck = row.querySelector('select[data-role=vehicle]').selectedOptions[0];
    var fleet = truck && truck.dataset.fleet && standards[truck.dataset.fleet] ? truck.dataset.fleet : 'all';
    var set = standards[fleet] || standards.all || {};
    row.querySelectorAll('.lms-std').forEach(function (input) {
      var value = set[input.dataset.std];
      if (input.dataset.typed !== '1' && (input.value === '' || input.dataset.auto === '1') && value !== null && value !== undefined) {
        input.value = Math.round(parseFloat(value));
        input.dataset.auto = '1';
      }
    });
  }

  body.addEventListener('change', function (e) {
    var row = e.target.closest('tr');
    if (!row || row.classList.contains('lms-row-problems')) { return; }
    var role = e.target.getAttribute('data-role');
    if (role === 'vehicle') {
      var option = e.target.selectedOptions[0];
      var trailer = row.querySelector('select[data-role=trailer]'), driver = row.querySelector('select[data-role=driver]');
      if (option && option.dataset.trailer && !trailer.value) { trailer.value = option.dataset.trailer; }
      if (option && option.dataset.driver && !driver.value) { driver.value = option.dataset.driver; }
      fillStandards(row);
    }
    rowProblems(row);
  });
  body.addEventListener('input', function (e) { if (e.target.classList.contains('lms-std')) { e.target.dataset.typed = '1'; delete e.target.dataset.auto; } });

  var next = body.querySelectorAll('tr:not(.lms-row-problems)').length;
  document.getElementById('lms-add-truck').addEventListener('click', function () {
    var rows = body.querySelectorAll('tr:not(.lms-row-problems)');
    var row = rows[rows.length - 1].cloneNode(true), problems = rows[rows.length - 1].nextElementSibling.cloneNode(true);
    row.querySelectorAll('input, select').forEach(function (input) {
      input.name = input.name.replace(/rows\[\d+\]/, 'rows[' + next + ']');
      if (input.tagName === 'SELECT') { input.selectedIndex = 0; } else { input.value = ''; delete input.dataset.typed; }
    });
    problems.querySelector('ul').innerHTML = '';
    body.appendChild(row); body.appendChild(problems);
    row.querySelector('.lms-service-reason').style.display = 'none';
    next++;
    cargoColumns();
  });
  body.addEventListener('click', function (e) {
    var button = e.target.closest('.lms-remove-truck');
    if (!button || body.querySelectorAll('tr:not(.lms-row-problems)').length < 2) { return; }
    var row = button.closest('tr');
    row.nextElementSibling.remove(); row.remove();
  });

  document.getElementById('lms-check').addEventListener('click', function () {
    var box = document.getElementById('lms-plan-check');
    fetch('<?= e(url(['trip_planner', 'check'])) ?>', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        box.classList.remove('d-none', 'alert-success', 'alert-danger');
        if (!data.problems.length) { box.classList.add('alert-success'); box.textContent = 'Everything is in order: the trips can be created.'; return; }
        box.classList.add('alert-danger');
        box.innerHTML = '<strong>To fix first</strong><ul class="mb-0 mt-1"></ul>';
        data.problems.forEach(function (text) { var li = document.createElement('li'); li.textContent = text; box.querySelector('ul').appendChild(li); });
      });
  });

  body.querySelectorAll('tr:not(.lms-row-problems)').forEach(function (row) { fillStandards(row); rowProblems(row); });
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
