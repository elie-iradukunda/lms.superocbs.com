<?php
/**
 * Delivery note: printed (or saved as PDF from the browser) and signed by the
 * receiver. Everything on it comes from the delivery, its shipment and its trip,
 * so nobody fills a paper form by hand.
 *
 * @var array $note
 * @var array $company
 */
$v = static fn (?string $value, string $empty = '—'): string => e(($value ?? '') !== '' ? $value : $empty);
$dt = static fn (?string $value): string => ($value ?? '') !== '' ? e(date('d M Y, H:i', strtotime((string) $value))) : '—';
$lpo = ($note['customer_reference'] ?? '') !== '' ? $note['customer_reference'] : ($note['request_lpo'] ?? '');
$sizes = ['20ft' => '20 ft', '40ft' => '40 ft', '40ft_hc' => '40 ft HC', '45ft' => '45 ft', 'loose' => 'Loose'];
$delivered = ($note['status'] ?? '') === 'delivered';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delivery note <?= e($note['delivery_code']) ?></title>
<style>
  :root { --brand: <?= e($company['color']) ?>; --ink: #1d2733; --muted: #5b6670; --line: #d5dbe1; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #eef1f4; color: var(--ink); font: 13px/1.45 "Segoe UI", Arial, sans-serif; }
  .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar button, .toolbar a { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 8px 14px; border-radius: 6px; font: inherit; cursor: pointer; text-decoration: none; }
  .toolbar .primary { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; }
  .sheet { width: 210mm; min-height: 297mm; margin: 12px auto 24px; background: #fff; padding: 14mm 14mm 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
  header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--brand); padding-bottom: 10px; }
  header img { max-height: 54px; max-width: 160px; margin-bottom: 6px; display: block; }
  .company { font-size: 18px; font-weight: 700; }
  .company-details { color: var(--muted); font-size: 11px; max-width: 105mm; }
  .doc-title { text-align: right; }
  .doc-title h1 { margin: 0; font-size: 22px; letter-spacing: 1px; color: var(--brand); }
  .doc-title .no { font-size: 15px; font-weight: 700; }
  .doc-title .meta { color: var(--muted); font-size: 11px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
  .box { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; }
  .box h3 { margin: 0 0 6px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--brand); }
  .row { display: flex; gap: 8px; padding: 1.5px 0; }
  .row span:first-child { color: var(--muted); min-width: 30mm; }
  .row span:last-child { font-weight: 600; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th { background: var(--ink); color: #fff; text-align: left; font-size: 11px; padding: 7px 8px; }
  td { border-bottom: 1px solid var(--line); padding: 8px; vertical-align: top; }
  td.num, th.num { text-align: right; }
  .instructions { margin-top: 10px; font-size: 12px; }
  .signame { float: right; font-size: 11px; font-weight: 600; margin-top: 14px; }
  .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 18px; }
  .sign { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; min-height: 40mm; }
  .sign h3 { margin: 0 0 8px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--brand); }
  .line { border-bottom: 1px dotted #9aa4ad; height: 22px; margin-bottom: 2px; font-weight: 600; padding-top: 4px; }
  .label { color: var(--muted); font-size: 10.5px; margin-bottom: 4px; }
  .sign { position: relative; }
  .seal { position: absolute; right: 8px; bottom: 8px; max-height: 26mm; max-width: 40mm; mix-blend-mode: multiply; transform: rotate(-6deg); }
  .sigline { height: 34px; position: relative; }
  .sigline .sig { position: absolute; left: 0; bottom: 1px; max-height: 32px; max-width: 70%; mix-blend-mode: multiply; }
  .stamp { border: 1px dashed #9aa4ad; height: 22mm; display: flex; align-items: center; justify-content: center; color: #9aa4ad; font-size: 11px; }
  footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid var(--line); color: var(--muted); font-size: 10.5px; display: flex; justify-content: space-between; }
  .status { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background: #e8f5ec; color: #2f855a; }
  .status.pending { background: #fff4e5; color: #b7791f; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
    @page { size: A4; margin: 12mm; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <a href="<?= url(['deliveries', $note['id']]) ?>">Back to delivery</a>
  <button class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="sheet">
  <header>
    <div>
      <?php if (!empty($company['logo'])): ?><img src="<?= $company['logo'] ?>" alt=""><?php endif; ?>
      <div class="company"><?= e($company['name']) ?></div>
      <div class="company-details"><?= e($company['details']) ?></div>
    </div>
    <div class="doc-title">
      <h1>DELIVERY NOTE</h1>
      <div class="no"><?= e($note['delivery_code']) ?></div>
      <div class="meta">Date: <?= e(date('d M Y')) ?></div>
      <div class="meta">Status: <span class="status<?= $delivered ? '' : ' pending' ?>"><?= e(ucwords(str_replace('_', ' ', (string) $note['status']))) ?></span></div>
    </div>
  </header>

  <div class="grid">
    <div class="box">
      <h3>Customer</h3>
      <div class="row"><span>Name</span><span><?= $v($note['customer_name']) ?></span></div>
      <div class="row"><span>TIN</span><span><?= $v($note['customer_tin']) ?></span></div>
      <div class="row"><span>LPO / reference</span><span><?= $v($lpo) ?></span></div>
      <div class="row"><span>Request</span><span><?= $v($note['request_code']) ?></span></div>
    </div>
    <div class="box">
      <h3>Deliver to</h3>
      <div class="row"><span>Receiver</span><span><?= $v($note['recipient_name']) ?></span></div>
      <div class="row"><span>Phone</span><span><?= $v($note['recipient_phone']) ?></span></div>
      <div class="row"><span>Address</span><span><?= $v($note['destination']) ?></span></div>
      <div class="row"><span>Planned for</span><span><?= $dt($note['planned_at']) ?></span></div>
    </div>
    <div class="box">
      <h3>Transport</h3>
      <div class="row"><span>Trip</span><span><?= $v($note['trip_code']) ?></span></div>
      <div class="row"><span>From</span><span><?= $v($note['origin'] ?: $note['pickup_location']) ?></span></div>
      <div class="row"><span>Truck</span><span><?= $v($note['plate_number']) ?><?= ($note['make'] ?? '') !== '' ? ' · ' . e(trim($note['make'] . ' ' . $note['model'])) : '' ?></span></div>
      <div class="row"><span>Trailer</span><span><?= $v($note['trailer_plate']) ?></span></div>
      <div class="row"><span>Departed</span><span><?= $dt($note['departure_at']) ?></span></div>
    </div>
    <div class="box">
      <h3>Driver</h3>
      <div class="row"><span>Name</span><span><?= $v($note['driver_name']) ?></span></div>
      <div class="row"><span>Phone</span><span><?= $v($note['driver_phone']) ?></span></div>
      <div class="row"><span>Licence</span><span><?= $v($note['license_number']) ?></span></div>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Shipment</th><th>Description of goods</th><th>Container / seal</th><th class="num">Packages</th><th class="num">Weight (kg)</th><th class="num">Volume (m³)</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><?= $v($note['shipment_code']) ?></td>
        <td><?= $v($note['cargo_description']) ?><?= !empty($note['is_hazardous']) ? '<br><strong>HAZARDOUS</strong>' : '' ?><?= ($note['temperature_min_c'] ?? null) !== null ? '<br>Keep between ' . e((float) $note['temperature_min_c']) . ' and ' . e((float) $note['temperature_max_c']) . ' °C' : '' ?></td>
        <td>
          <?= $v($note['container_no']) ?><?= ($note['container_size'] ?? '') !== '' ? ' (' . e($sizes[$note['container_size']] ?? $note['container_size']) . ')' : '' ?>
          <br><span style="color:#5b6670">Seal:</span> <?= $v($note['seal_no']) ?>
          <?php if (($note['bill_of_lading'] ?? '') !== ''): ?><br><span style="color:#5b6670">B/L:</span> <?= e($note['bill_of_lading']) ?><?php endif; ?>
        </td>
        <td class="num"><?= $note['packages_count'] !== null ? e(number_format((float) $note['packages_count'])) : '—' ?></td>
        <td class="num"><?= $note['weight_kg'] !== null ? e(number_format((float) $note['weight_kg'], 2)) : '—' ?></td>
        <td class="num"><?= $note['volume_m3'] !== null ? e(number_format((float) $note['volume_m3'], 3)) : '—' ?></td>
      </tr>
    </tbody>
  </table>

  <?php if (($note['special_instructions'] ?? '') !== ''): ?>
    <div class="instructions"><strong>Handling instructions:</strong> <?= e($note['special_instructions']) ?></div>
  <?php endif; ?>

  <div class="signatures">
    <div class="sign">
      <h3>Dispatched by</h3>
      <div class="line"><?= e($company['name']) ?></div><div class="label">Company</div>
      <div class="line sigline"><?php if (!empty($signatures['dispatched'])): ?><img class="sig" src="<?= $signatures['dispatched'] ?>" alt="Signature"><?php endif; ?><?= !empty($note['dispatched_at']) && !empty($note['dispatched_by_name']) ? '<span class="signame">' . e((string) $note['dispatched_by_name']) . '</span>' : '' ?></div><div class="label">Name and signature</div>
      <?php if (!empty($company['seal'])): ?><img class="seal" src="<?= $company['seal'] ?>" alt="Company seal"><?php endif; ?>
    </div>
    <div class="sign">
      <h3>Driver</h3>
      <div class="line"><?= e((string) ($note['driver_name'] ?? '')) ?></div><div class="label">Name</div>
      <div class="line sigline"><?php if (!empty($signatures['driver'])): ?><img class="sig" src="<?= $signatures['driver'] ?>" alt="Signature"><?php endif; ?></div><div class="label">Signature</div>
    </div>
    <div class="sign">
      <h3>Received in good order</h3>
      <div class="line"><?= e((string) ($note['collected_by'] ?: ($delivered ? $note['recipient_name'] : ''))) ?></div><div class="label">Name</div>
      <div class="line"><?= e((string) ($note['collected_id_no'] ?? '')) ?></div><div class="label">ID number</div>
      <div class="line"><?= $delivered && $note['delivered_at'] ? $dt($note['delivered_at']) : '' ?></div><div class="label">Date and time</div>
      <div class="line sigline"><?php if (!empty($signatures['received'])): ?><img class="sig" src="<?= $signatures['received'] ?>" alt="Signature"><?php endif; ?></div><div class="label">Signature</div>
      <div class="stamp">Company stamp</div>
    </div>
  </div>

  <footer>
    <span>Goods received in good order and condition unless noted above. Claims must be raised within 24 hours of delivery.</span>
    <span><?= e($note['delivery_code']) ?></span>
  </footer>
</div>
</body>
</html>
