<?php
/**
 * The trip report as a page: read on screen, printed, or saved as PDF, with
 * buttons for the PDF and the Excel workbook made from the same facts.
 *
 * @var array $report from Models\TripReport::data
 * @var array $brand  company name, details, colour, logo
 */
$d = $report;
$t = $d['trip'];
$v = static fn ($value, string $empty = '—'): string => e(trim((string) ($value ?? '')) !== '' ? (string) $value : $empty);
$when = static fn ($value): string => ($value ?? '') !== '' ? e(date(date('H:i:s', strtotime((string) $value)) === '00:00:00' ? 'j M Y' : 'j M Y, H:i', strtotime((string) $value))) : '—';
$label = static fn ($value): string => e(\Models\Schema::label((string) $value));
$num = static fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
$capacity = (float) $t['capacity_kg'];
$pairs = static function (array $pairs) use ($v): string {
    $out = '';
    foreach ($pairs as $k => $value) {
        if (trim((string) ($value ?? '')) === '') {
            continue;
        }
        $out .= '<div class="pair"><span>' . e($k) . '</span><strong>' . $v($value) . '</strong></div>';
    }

    return $out;
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trip report <?= e($t['reference_code']) ?></title>
<style>
  :root { --brand: <?= e($brand['color']) ?>; --ink: #1d2733; --muted: #5b6670; --line: #d5dbe1; --tint: #f1f4f7; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #eef1f4; color: var(--ink); font: 13px/1.45 "Segoe UI", Arial, sans-serif; }
  .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; padding: 0 8px; }
  .toolbar a, .toolbar button { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 8px 14px; border-radius: 6px; font: inherit; cursor: pointer; text-decoration: none; }
  .toolbar .primary { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; }
  .sheet { width: 210mm; max-width: 100%; min-height: 297mm; margin: 12px auto 24px; background: #fff; padding: 13mm 13mm 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
  header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 3px solid var(--brand); padding-bottom: 10px; }
  header img { max-height: 50px; max-width: 150px; display: block; margin-bottom: 6px; }
  .company { font-size: 17px; font-weight: 700; }
  .company-details { color: var(--muted); font-size: 11px; max-width: 100mm; }
  .doc-title { text-align: right; }
  .doc-title h1 { margin: 0; font-size: 24px; letter-spacing: 1.5px; color: var(--brand); }
  .doc-title .no { font-size: 14px; font-weight: 700; }
  .doc-title .meta { color: var(--muted); font-size: 11px; }
  .tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 12px; }
  .tile { background: var(--tint); border-radius: 6px; padding: 8px 10px; }
  .tile span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .7px; color: var(--muted); font-weight: 700; }
  .tile strong { font-size: 13px; }
  h2 { margin: 18px 0 8px; padding: 5px 9px; background: var(--tint); color: var(--brand); font-size: 11.5px; text-transform: uppercase; letter-spacing: .8px; border-radius: 4px; }
  h3 { margin: 12px 0 6px; font-size: 13px; display: flex; justify-content: space-between; }
  h3 em { font-style: normal; color: var(--brand); font-size: 11px; }
  .pairs { display: grid; grid-template-columns: 1fr 1fr; column-gap: 18px; }
  .pair { display: flex; gap: 10px; padding: 3px 0; border-bottom: 1px solid #eef1f4; }
  .pair span { color: var(--muted); min-width: 34mm; font-size: 12px; }
  .pair strong { font-weight: 600; }
  .two { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .two .pairs { grid-template-columns: 1fr; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th { background: var(--ink); color: #fff; text-align: left; font-size: 10.5px; padding: 6px 7px; text-transform: uppercase; letter-spacing: .4px; }
  td { border-bottom: 1px solid var(--line); padding: 6px 7px; vertical-align: top; }
  td.num, th.num { text-align: right; white-space: nowrap; }
  .note { color: var(--muted); font-size: 12px; margin: 6px 0; }
  .ok { color: #2f855a; font-weight: 700; } .bad { color: #c53030; font-weight: 700; } .warn { color: #a86a03; font-weight: 700; }
  .bar { height: 10px; background: #e6eaee; border-radius: 6px; overflow: hidden; }
  .bar span { display: block; height: 100%; background: var(--brand); }
  .bar-words { display: flex; justify-content: space-between; gap: 10px; margin: 6px 0 8px; font-size: 12.5px; }
  .bar-words .next { color: var(--brand); font-weight: 700; }
  .steps { list-style: none; margin: 0; padding: 0; columns: 2; column-gap: 18px; }
  .steps li { display: grid; grid-template-columns: 14px 1fr auto; gap: 8px; align-items: start; padding: 4px 0; border-bottom: 1px solid #eef1f4; break-inside: avoid; font-size: 12px; }
  .steps .dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 3px; border: 2px solid #b9c3ce; background: #fff; }
  .steps .is-done .dot { background: var(--brand); border-color: var(--brand); }
  .steps .is-now .dot { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(0,0,0,.06); }
  .steps .is-now strong { color: var(--brand); }
  .steps .is-todo { color: #8a949e; }
  .steps em { font-style: normal; color: var(--muted); font-size: 11px; }
  .steps time { color: var(--muted); font-size: 11px; white-space: nowrap; }
  .signs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 18px; }
  .sign { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; min-height: 30mm; position: relative; }
  .sign b { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .7px; color: var(--brand); }
  .sign .line { position: absolute; left: 10px; right: 10px; bottom: 20px; border-bottom: 1px dotted #9aa4ad; }
  .sign small { position: absolute; left: 10px; bottom: 5px; color: var(--muted); font-size: 10.5px; }
  .sign .sig { position: absolute; left: 10px; right: 10px; bottom: 22px; margin: 0 auto; max-height: 15mm; max-width: calc(100% - 20px); mix-blend-mode: multiply; }
  footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid var(--line); color: var(--muted); font-size: 10.5px; display: flex; justify-content: space-between; }
  @media (max-width: 700px) {
    .sheet { padding: 14px; min-height: 0; }
    .tiles, .pairs, .two, .signs { grid-template-columns: 1fr; }
    .steps { columns: 1; }
    table { display: block; overflow-x: auto; }
  }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
    h2 { break-after: avoid; }
    tr, .pair { break-inside: avoid; }
    @page { size: A4; margin: 11mm; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <a href="<?= url(['trips', $t['id']]) ?>">Back to trip</a>
  <a href="<?= url(['trips', $t['id'], 'report', 'xlsx']) ?>">Excel</a>
  <a href="<?= url(['trips', $t['id'], 'report', 'pdf']) ?>" target="_blank" rel="noopener">PDF</a>
  <button class="primary" onclick="window.print()">Print</button>
</div>

<div class="sheet">
  <header>
    <div>
      <?php if (!empty($brand['logo'])): ?><img src="<?= $brand['logo'] ?>" alt=""><?php endif; ?>
      <div class="company"><?= e($d['company']['name']) ?></div>
      <div class="company-details"><?= e($d['company']['details']) ?></div>
    </div>
    <div class="doc-title">
      <h1>TRIP REPORT</h1>
      <div class="no"><?= e($t['reference_code']) ?> · <?= e(strtoupper(\Models\Schema::label((string) $t['status']))) ?></div>
      <div class="meta"><?= $d['commercial'] ? 'Office copy' : 'Driver copy' ?> · generated <?= e(date('j M Y, H:i', strtotime((string) $d['generated_at']))) ?></div>
    </div>
  </header>

  <div class="tiles">
    <div class="tile"><span>Route</span><strong><?= e($t['pickup_location'] . ' to ' . $t['destination']) ?></strong></div>
    <div class="tile"><span>Truck</span><strong><?= $v($t['plate_number']) ?></strong></div>
    <div class="tile"><span>Driver</span><strong><?= $v($t['driver_name']) ?></strong></div>
    <div class="tile"><span>Load</span><strong><?= e(number_format((float) $d['totals']['weight'])) ?> kg<?= $capacity > 0 ? ' of ' . e(number_format($capacity)) . ' (' . (int) round((float) $d['totals']['weight'] / $capacity * 100) . '%)' : '' ?></strong></div>
    <div class="tile"><span>Leaves</span><strong><?= $when($t['departure_at'] ?: $t['planned_departure_at']) ?></strong></div>
    <div class="tile"><span>Arrives</span><strong><?= $when($t['arrival_at'] ?: $t['planned_arrival_at']) ?></strong></div>
    <?php $dist = $d['distance'] ?? []; ?>
    <div class="tile"><span>Distance</span><strong><?= ($dist['start_km'] ?? null) !== null ? e(number_format((int) $dist['driven']) . ' km ' . (($dist['end_km'] ?? null) !== null ? 'in all' : 'so far')) . '<br><small style="font-weight:400;color:#5b6670">from ' . e(number_format((int) $dist['start_km'])) . ' km on the clock</small>' : 'Not started' ?></strong></div>
  </div>

  <?php $p = $d['progress']; ?>
  <h2>Progress · <?= (int) $p['done'] ?> of <?= (int) $p['total'] ?> steps · <?= (int) $p['percent'] ?>%</h2>
  <div class="bar"><span style="width: <?= (int) $p['percent'] ?>%"></span></div>
  <div class="bar-words"><span>Reached: <strong><?= $v($p['reached']) ?></strong></span><span class="next"><?= $p['current'] === 'All done' ? 'All done' : 'Next: ' . e($p['current']) ?></span></div>
  <ol class="steps">
    <?php foreach ($p['stages'] as $stage): ?>
      <li class="is-<?= e($stage['state']) ?>">
        <span class="dot"></span>
        <div><strong><?= e($stage['label']) ?></strong><?php if ($stage['detail'] !== ''): ?> <em><?= e($stage['detail']) ?></em><?php endif; ?></div>
        <time><?= $stage['when'] ? $when($stage['when']) : ($stage['state'] === 'now' ? 'next' : '') ?></time>
      </li>
    <?php endforeach; ?>
  </ol>

  <h2>The trip</h2>
  <div class="pairs"><?= $pairs([
      'Trip' => $t['reference_code'], 'Status' => \Models\Schema::label((string) $t['status']), 'Type' => \Models\Schema::label((string) $t['trip_type']),
      'Customer' => $t['customer_name'], 'Request' => $t['request_code'], 'Customer LPO' => $t['request_lpo'],
      'Journey' => $t['journey_code'] ? $t['journey_code'] . ($t['leg_no'] ? ', leg ' . $t['leg_no'] : '') : '', 'Planned by' => $t['planned_by'],
      'Planned departure' => $t['planned_departure_at'] ? date('j M Y, H:i', strtotime((string) $t['planned_departure_at'])) : '',
      'Left' => ($t['departure_at'] ?: $t['dispatched_at']) ? date('j M Y, H:i', strtotime((string) ($t['departure_at'] ?: $t['dispatched_at']))) : '',
      'Planned arrival' => $t['planned_arrival_at'] ? date('j M Y, H:i', strtotime((string) $t['planned_arrival_at'])) : '',
      'Arrived' => $t['arrival_at'] ? date('j M Y, H:i', strtotime((string) $t['arrival_at'])) : '',
      'Dispatched by' => $t['dispatched_by'], 'Cargo' => $t['cargo_summary'],
  ]) ?></div>
  <?php if (trim((string) $t['notes']) !== ''): ?><p class="note"><strong>Instructions from the office:</strong> <?= e($t['notes']) ?></p><?php endif; ?>

  <h2>Truck and driver</h2>
  <div class="two">
    <div class="pairs"><?= $pairs([
        'Truck' => $t['plate_number'], 'Type' => trim($t['vehicle_type'] . ' · ' . trim($t['make'] . ' ' . $t['model']), ' ·'),
        'Carries' => $capacity > 0 ? number_format($capacity) . ' kg' . ((float) $t['capacity_m3'] > 0 ? ' · ' . $num((float) $t['capacity_m3']) . ' m³' : '') : '',
        'Cooling unit' => $t['plate_number'] ? ((int) $t['has_cooling_unit'] === 1 ? 'Yes' : 'No') : '',
        'Mileage at start' => $t['mileage'] !== null ? number_format((int) $t['mileage']) . ' km' : '',
    ]) ?></div>
    <div class="pairs"><?= $pairs([
        'Driver' => $t['driver_name'], 'Phone' => $t['driver_phone'],
        'Licence' => trim(($t['license_number'] ?? '') . ($t['license_class'] ? ' (class ' . $t['license_class'] . ')' : '')),
        'Licence expires' => $t['license_expiry'], 'Emergency contact' => trim(($t['emergency_contact'] ?? '') . ' ' . ($t['emergency_phone'] ?? '')),
    ]) ?></div>
  </div>

  <h2>Route and stops</h2>
  <?php if ($d['stops'] === []): ?>
    <p class="note">Straight run: <?= e($t['pickup_location']) ?> to <?= e($t['destination']) ?>, no stops recorded.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Type</th><th>Place</th><th>Contact</th><th>Planned</th><th>Actual</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($d['stops'] as $n => $s): ?>
          <tr><td><?= $n + 1 ?></td><td><?= $label($s['stop_type']) ?></td><td><strong><?= e($s['location_name']) ?></strong></td>
            <td><?= $v($s['contact_name'], '') ?><?= ($s['contact_phone'] ?? '') !== '' ? '<br>' . e($s['contact_phone']) : '' ?></td>
            <td><?= $when($s['planned_arrival_at']) ?></td><td><?= $when($s['actual_arrival_at']) ?><?= !empty($s['arrival_mileage']) ? '<br>' . e(number_format((int) $s['arrival_mileage'])) . ' km' . ($t['start_mileage'] !== null ? ' <small>(+' . e(number_format((int) $s['arrival_mileage'] - (int) $t['start_mileage'])) . ')</small>' : '') : '' ?></td><td><?= $label($s['status']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>Loads on board · <?= (int) $d['totals']['loads'] ?> · <?= e(number_format((float) $d['totals']['weight'])) ?> kg · <?= e(number_format((float) $d['totals']['packages'])) ?> packages</h2>
  <?php if ($d['shipments'] === []): ?><p class="note">No load on this trip yet.</p><?php endif; ?>
  <?php foreach ($d['shipments'] as $s): ?>
    <h3><span><?= e($s['shipment_code']) ?> · <?= $v($s['customer_name'], 'Internal') ?></span><em><?= $label($s['status']) ?></em></h3>
    <div class="pairs"><?= $pairs([
        'Goods' => $s['cargo_description'], 'Cargo type' => \Models\Schema::label((string) $s['cargo_type']) . ((int) $s['is_hazardous'] === 1 ? ' · HAZARDOUS' : ''),
        'Packages' => $s['packages_count'] !== null ? number_format((float) $s['packages_count']) : '', 'Gross weight' => $s['weight_kg'] !== null ? number_format((float) $s['weight_kg']) . ' kg' : '',
        'Volume' => (float) $s['volume_m3'] > 0 ? $num((float) $s['volume_m3']) . ' m³' : '',
        'Declared value' => (float) $s['declared_value'] > 0 ? \Models\Currency::format((float) $s['declared_value'], (string) ($s['currency'] ?: \Models\Currency::base())) : '',
        'From' => $s['from_depot'] ?: $s['origin'], 'Drops at' => $s['to_depot'] ?: $s['destination'],
        'Receiver' => trim(($s['consignee_name'] ?? '') . ' ' . ($s['consignee_phone'] ?? '')),
        'Container' => trim(($s['container_no'] ?? '') . ($s['container_size'] ? ' (' . \Models\Schema::label((string) $s['container_size']) . ')' : '')),
        'Seal' => $s['seal_no'], 'Trailer' => $s['trailer_plate'], 'Bill of lading' => $s['bill_of_lading'], 'Customer LPO' => $s['customer_reference'],
        'Price' => $d['commercial'] && $s['quoted_amount'] !== null ? \Models\Currency::format((float) $s['quoted_amount'], (string) ($s['currency'] ?: \Models\Currency::base())) : '',
    ]) ?></div>
    <?php if (trim((string) $s['special_instructions']) !== ''): ?><p class="note"><strong>Handling:</strong> <?= e($s['special_instructions']) ?></p><?php endif; ?>
  <?php endforeach; ?>

  <h2>Papers carried</h2>
  <?php if ($d['papers'] === []): ?><p class="note">No papers recorded.</p><?php else: ?>
    <table>
      <thead><tr><th>For</th><th>Paper</th><th>Number</th><th>Issued</th><th>Expires</th><th>Status</th><th>Scan</th></tr></thead>
      <tbody>
        <?php foreach ($d['papers'] as $p): ?>
          <?php $expired = ($p['expires'] ?? '') !== '' && $p['expires'] < date('Y-m-d'); ?>
          <tr><td><strong><?= e($p['kind']) ?></strong></td><td><?= e($p['type']) ?></td><td><?= $v($p['number']) ?></td><td><?= $v($p['issued']) ?></td>
            <td class="<?= $expired ? 'bad' : '' ?>"><?= $v($p['expires'], 'No expiry') ?></td><td class="<?= $p['status'] === 'valid' ? 'ok' : 'bad' ?>"><?= $label($p['status']) ?></td><td><?= $p['scan'] ? 'Yes' : 'No' ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>Final check before leaving</h2>
  <?php if ($d['checks'] === []): ?><p class="note">No dispatch check recorded.</p><?php endif; ?>
  <?php foreach ($d['checks'] as $c): ?>
    <?php $yes = static fn ($x): string => (int) $x === 1 ? '<span class="ok">Yes</span>' : '<span class="bad">No</span>'; ?>
    <table>
      <thead><tr><th>Shipment</th><th>Cargo loaded</th><th>Quantity</th><th>Packaging</th><th>Papers*</th><th>Truck*</th><th>Driver*</th><th>Route*</th><th>Result</th></tr></thead>
      <tbody><tr><td><strong><?= e($c['shipment']) ?></strong></td><td><?= $yes($c['cargo_loaded']) ?></td><td><?= $yes($c['quantity_verified']) ?></td><td><?= $yes($c['packaging_verified']) ?></td>
        <td><?= $yes($c['documents_verified']) ?></td><td><?= $yes($c['vehicle_verified']) ?></td><td><?= $yes($c['driver_verified']) ?></td><td><?= $yes($c['route_verified']) ?></td><td><strong><?= $label($c['status']) ?></strong></td></tr></tbody>
    </table>
    <p class="note">* checked by the system. Checked by <?= $v($c['checked_by_name']) ?> on <?= $when($c['checked_at']) ?><?= trim((string) $c['notes']) !== '' ? '. Notes: ' . e($c['notes']) : '' ?></p>
  <?php endforeach; ?>
  <?php if ($d['readiness'] !== null): ?>
    <?php $open = array_filter($d['readiness']['items'], static fn (array $i): bool => $i['state'] !== 'ok'); ?>
    <p><strong class="<?= $d['readiness']['ready'] ? 'ok' : 'bad' ?>">System check now: <?= $d['readiness']['ready'] ? 'READY' : 'NOT READY' ?></strong>
      · <?= count($d['readiness']['items']) - count($open) ?> of <?= count($d['readiness']['items']) ?> points in order</p>
    <?php foreach ($open as $item): ?>
      <p class="note"><span class="<?= $item['state'] === 'fail' ? 'bad' : 'warn' ?>"><?= $item['state'] === 'fail' ? 'Must fix' : 'Warning' ?></span> <?= e($item['label'] . ': ' . $item['detail']) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2>Border clearances</h2>
  <?php if ($d['crossings'] === []): ?><p class="note">None recorded yet. Each border clearance is added here as the truck crosses.</p><?php else: ?>
    <table>
      <thead><tr><th>Clearance</th><th>Border</th><th>Direction</th><th>Declaration</th><th>Status</th><th>Released</th><?php if ($d['commercial']): ?><th class="num">Charges</th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach ($d['crossings'] as $x): ?>
          <tr><td><strong><?= e($x['reference']) ?></strong></td><td><?= $v($x['post_name']) ?></td><td><?= $label($x['direction']) ?> <?= e($x['customs_country'] ?? '') ?></td><td><?= $v($x['declaration_no']) ?></td><td><?= $label($x['status']) ?></td><td><?= $when($x['released_at'] ?: $x['cleared_at']) ?></td>
            <?php if ($d['commercial']): ?><td class="num"><?= (float) $x['charges_total'] > 0 ? e(\Models\Currency::format((float) $x['charges_total'], (string) ($x['currency'] ?: \Models\Currency::base()))) : '—' ?></td><?php endif; ?></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>Deliveries</h2>
  <?php if ($d['deliveries'] === []): ?><p class="note">Not delivered yet.</p><?php else: ?>
    <table>
      <thead><tr><th>Delivery</th><th>Shipment</th><th>Receiver</th><th>Where</th><th>Planned</th><th>Delivered</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($d['deliveries'] as $dl): ?>
          <tr><td><strong><?= e($dl['delivery_code']) ?></strong></td><td><?= $v($dl['shipment_code']) ?></td><td><?= e($dl['recipient_name']) ?><?= ($dl['recipient_phone'] ?? '') !== '' ? '<br>' . e($dl['recipient_phone']) : '' ?></td>
            <td><?= e($dl['destination']) ?></td><td><?= $when($dl['planned_at']) ?></td><td><?= $when($dl['delivered_at']) ?></td><td><?= $label($dl['status']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>On the road: fuel and expenses</h2>
  <?php if ($d['fuel'] === [] && $d['expenses'] === []): ?>
    <p class="note">Nothing recorded yet. Fuel and expenses the driver records on the trip are added here.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>When</th><th>What</th><th>Detail</th><th class="num">Amount</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($d['fuel'] as $f): ?>
          <tr><td><?= $when($f['purchased_at']) ?></td><td>Fuel</td><td><?= e($num((float) $f['litres'])) ?> L at <?= $v($f['station_name'], 'a station') ?><?= $f['mileage'] ? ' · ' . e(number_format((int) $f['mileage'])) . ' km' : '' ?></td>
            <td class="num"><?= e(\Models\Currency::format(round((float) $f['litres'] * (float) $f['unit_price'], 2), (string) ($f['currency'] ?: \Models\Currency::base()))) ?></td><td>Recorded</td></tr>
        <?php endforeach; ?>
        <?php foreach ($d['expenses'] as $x): ?>
          <tr><td><?= $when($x['expense_date']) ?></td><td><?= e($x['category']) ?></td><td><?= $v(trim((string) ($x['notes'] ?? '')) ?: $x['reference_code']) ?></td>
            <td class="num"><?= e(\Models\Currency::format((float) $x['amount'], (string) ($x['currency'] ?: \Models\Currency::base()))) ?></td><td><?= $label($x['status']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!empty($d['support'])): ?>
    <h2>Support asked for on the road</h2>
    <table>
      <thead><tr><th>Request</th><th>What</th><th class="num">Asked</th><th>Sent to</th><th>Status</th><th>Answer</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($d['support']) as $help): ?>
          <?php $cur = (string) ($help['currency'] ?: \Models\Currency::base()); ?>
          <tr>
            <td><strong><?= e($help['request_code']) ?></strong><br><?= $when($help['created_at']) ?></td>
            <td><?= e(\Models\Schema::SUPPORT_TYPES[$help['support_type']] ?? '') ?><br><strong><?= e($help['title']) ?></strong><?= $help['location'] ? '<br>At ' . e($help['location']) : '' ?></td>
            <td class="num"><?= (float) $help['amount_requested'] > 0 ? e(\Models\Currency::format((float) $help['amount_requested'], $cur)) : '—' ?></td>
            <td><?= $v($help['assigned_name'], 'Office') ?></td>
            <td><?= e(\Models\Schema::SUPPORT_STATUS[$help['status']] ?? $help['status']) ?></td>
            <td><?= e($help['status'] === 'rejected' ? 'Refused: ' . ($help['rejection_reason'] ?? '') : (string) $help['response']) ?>
              <?= (float) $help['amount_given'] > 0 ? '<br><strong>' . e(\Models\Currency::format((float) $help['amount_given'], $cur)) . '</strong>' . ($help['given_method'] ? ' by ' . e($help['given_method']) : '') . ($help['given_reference'] ? ' (' . e($help['given_reference']) . ')' : '') : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($d['containers'] !== [] || $d['storage'] !== []): ?>
    <h2>Container and storage</h2>
    <?php if ($d['containers'] !== []): ?>
      <table>
        <thead><tr><th>Container</th><th>Size</th><th>Picked up</th><th>Unloaded</th><th>Returned</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($d['containers'] as $c): ?>
          <tr><td><strong><?= e($c['container_no']) ?></strong></td><td><?= $label($c['container_size']) ?></td><td><?= $when($c['picked_up_at']) ?></td><td><?= $when($c['unloaded_at']) ?></td><td><?= $when($c['returned_at']) ?></td><td><?= $label($c['status']) ?> · <?= (int) $c['free_days'] ?> free days</td></tr>
        <?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
    <?php if ($d['storage'] !== []): ?>
      <table>
        <thead><tr><th>Lot</th><th>Warehouse</th><th>Goods</th><th>Quantity</th><th>Received</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($d['storage'] as $lot): ?>
          <tr><td><strong><?= e($lot['lot_code']) ?></strong></td><td><?= $v($lot['warehouse_name']) ?></td><td><?= e($lot['description']) ?></td>
            <td><?= e($num((float) $lot['quantity_received']) . ' ' . $lot['unit']) ?> in, <?= e($num((float) $lot['quantity_released'])) ?> out</td><td><?= $when($lot['received_at']) ?></td><td><?= $label($lot['status']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>

  <h2>Timeline: what happened, in order</h2>
  <?php if ($d['timeline'] === []): ?><p class="note">Nothing recorded yet.</p><?php else: ?>
    <table>
      <thead><tr><th style="width:34mm">When</th><th>What</th><th>Detail</th></tr></thead>
      <tbody><?php foreach ($d['timeline'] as $event): ?><tr><td><?= $when($event['when']) ?></td><td><strong><?= e($event['what']) ?></strong></td><td><?= e($event['detail']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
  <?php endif; ?>

  <?php if ($d['commercial'] && $d['invoices'] !== []): ?>
    <h2>Invoice and payments</h2>
    <table>
      <thead><tr><th>Invoice</th><th>Status</th><th>Issued</th><th>Due</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th></tr></thead>
      <tbody>
        <?php foreach ($d['invoices'] as $invoice): ?>
          <?php $cur = (string) ($invoice['currency'] ?: \Models\Currency::base()); ?>
          <tr><td><strong><?= e($invoice['invoice_number']) ?></strong></td><td><?= $label($invoice['status']) ?></td><td><?= $v($invoice['issue_date']) ?></td><td><?= $v($invoice['due_date']) ?></td>
            <td class="num"><?= e(\Models\Currency::format((float) $invoice['total_amount'], $cur)) ?></td><td class="num"><?= e(\Models\Currency::format((float) $invoice['amount_paid'], $cur)) ?></td>
            <td class="num"><strong><?= e(\Models\Currency::format(max(0, (float) $invoice['total_amount'] - (float) $invoice['amount_paid']), $cur)) ?></strong></td></tr>
          <?php foreach ($invoice['payments'] as $payment): ?>
            <tr><td>&nbsp;&nbsp;<?= e($payment['payment_code']) ?></td><td>Payment</td><td colspan="2"><?= $when($payment['paid_at']) ?> · <?= e($payment['method']) ?> <?= e($payment['reference'] ?? '') ?></td><td></td>
              <td class="num"><?= e(\Models\Currency::format((float) $payment['amount'], (string) ($payment['currency'] ?: $cur))) ?></td><td></td></tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($d['commercial'] && $d['money'] !== null): ?>
    <?php $m = $d['money']; ?>
    <h2>Money (office copy only)</h2>
    <table>
      <thead><tr><th>Item</th><th>Basis</th><th class="num">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($m['lines'] as $line): ?><tr><td><strong><?= e($line['what']) ?></strong></td><td><?= e($line['basis']) ?></td><td class="num"><?= e(\Models\Currency::format($line['amount'], $line['currency'])) ?></td></tr><?php endforeach; ?>
        <?php foreach ($m['cost_lines'] as $line): ?><tr><td><?= e($line['what']) ?></td><td>Cost</td><td class="num">- <?= e(\Models\Currency::format($line['amount'], $line['currency'])) ?></td></tr><?php endforeach; ?>
        <?php foreach ($m['margin'] as $currency => $margin): ?>
          <tr><td colspan="2"><strong>Margin so far</strong> (revenue <?= e(\Models\Currency::format((float) ($m['revenue'][$currency] ?? 0), $currency)) ?>, costs <?= e(\Models\Currency::format((float) ($m['costs'][$currency] ?? 0), $currency)) ?>)</td>
            <td class="num <?= $margin >= 0 ? 'ok' : 'bad' ?>"><?= e(\Models\Currency::format((float) $margin, $currency)) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($m['recharge'] !== []): ?>
      <p class="note">Paid for the customer, to be recharged at cost on the invoice:</p>
      <table><tbody><?php foreach ($m['recharge'] as $line): ?><tr><td><?= e($line['what']) ?></td><td><?= e($line['status']) ?></td><td class="num"><?= e(\Models\Currency::format($line['amount'], $line['currency'])) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
    <p class="note">Amounts are shown in the currency they were charged or paid in; each currency is kept in its own books, nothing is converted.</p>
  <?php endif; ?>

  <?php
  // A signature shows only once that person has done their part.
  $signed = $d['signatures'] ?? [];
  $signBox = static function (string $title, string $who, ?string $path) use ($v): string {
      $image = \Models\Signature::dataUri($path);

      return '<div class="sign"><b>' . e($title) . '</b>' . $v($who, '')
          . ($image !== null ? '<img class="sig" src="' . $image . '" alt="Signature">' : '')
          . '<div class="line"></div><small>Signature and date</small></div>';
  };
  ?>
  <div class="signs">
    <?= $signBox('Dispatched by', (string) ($t['dispatched_by'] ?? ''), $signed['dispatched'] ?? null) ?>
    <?= $signBox('Driver', (string) ($t['driver_name'] ?? ''), $signed['driver'] ?? null) ?>
    <?= $signBox('Received at destination', (string) ($signed['received_name'] ?? ''), $signed['received'] ?? null) ?>
  </div>

  <footer><span><?= e($d['company']['name']) ?> · Trip report <?= e($t['reference_code']) ?> · <?= $d['commercial'] ? 'office copy' : 'driver copy' ?></span><span><?= e($t['reference_code']) ?></span></footer>
</div>
</body>
</html>
