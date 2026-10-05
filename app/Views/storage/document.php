<?php
/**
 * Goods received note (a storage lot) or gate pass (a release from one), printed
 * or saved as PDF and signed at the bay or the gate.
 *
 * @var string $kind 'grn' or 'pass'
 * @var array $doc lot fields, plus the release when $kind is 'pass'
 * @var array $company
 */
$v = static fn ($value, string $empty = '—'): string => e(($value ?? '') !== '' ? (string) $value : $empty);
$dt = static fn (?string $value): string => ($value ?? '') !== '' ? e(date('d M Y, H:i', strtotime((string) $value))) : '—';
$qty = static fn ($value): string => $value === null ? '—' : e(rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.'));
$isPass = $kind === 'pass';
$number = $isPass ? $doc['gate_pass_no'] : $doc['lot_code'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isPass ? 'Gate pass' : 'Goods received note' ?> <?= e($number) ?></title>
<style>
  :root { --brand: <?= e($company['color']) ?>; --ink: #1d2733; --muted: #5b6670; --line: #d5dbe1; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #eef1f4; color: var(--ink); font: 13px/1.45 "Segoe UI", Arial, sans-serif; }
  .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar button, .toolbar a { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 8px 14px; border-radius: 6px; font: inherit; cursor: pointer; text-decoration: none; }
  .toolbar .primary { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; }
  .sheet { width: 210mm; margin: 12px auto 24px; background: #fff; padding: 14mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
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
  .box h3, .sign h3 { margin: 0 0 6px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--brand); }
  .row { display: flex; gap: 8px; padding: 1.5px 0; }
  .row span:first-child { color: var(--muted); min-width: 32mm; }
  .row span:last-child { font-weight: 600; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th { background: var(--ink); color: #fff; text-align: left; font-size: 11px; padding: 7px 8px; }
  td { border-bottom: 1px solid var(--line); padding: 8px; vertical-align: top; }
  .num { text-align: right; }
  .big { font-size: 20px; font-weight: 700; }
  .sign { position: relative; }
  .seal { position: absolute; right: 8px; bottom: 6px; max-height: 24mm; max-width: 38mm; mix-blend-mode: multiply; transform: rotate(-6deg); }
  .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 18px; }
  .sign { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; min-height: 34mm; }
  .line { border-bottom: 1px dotted #9aa4ad; height: 22px; margin-bottom: 2px; font-weight: 600; padding-top: 4px; }
  .label { color: var(--muted); font-size: 10.5px; margin-bottom: 4px; }
  footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid var(--line); color: var(--muted); font-size: 10.5px; display: flex; justify-content: space-between; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; box-shadow: none; width: auto; padding: 0; }
    @page { size: A4; margin: 12mm; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <a href="<?= $isPass ? url(['storage_releases', $doc['release_id']]) : url(['storage_lots', $doc['id']]) ?>">Back</a>
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
      <h1><?= $isPass ? 'GATE PASS' : 'GOODS RECEIVED NOTE' ?></h1>
      <div class="no"><?= e($number) ?></div>
      <div class="meta">Printed: <?= e(date('d M Y, H:i')) ?></div>
      <?php if ($isPass): ?><div class="meta">Lot: <?= e($doc['lot_code']) ?></div><?php endif; ?>
    </div>
  </header>

  <div class="grid">
    <div class="box">
      <h3>Customer (owner of the goods)</h3>
      <div class="row"><span>Name</span><span><?= $v($doc['customer_name']) ?></span></div>
      <div class="row"><span>TIN</span><span><?= $v($doc['tin_number']) ?></span></div>
      <div class="row"><span>Phone</span><span><?= $v($doc['customer_phone']) ?></span></div>
    </div>
    <div class="box">
      <h3>Warehouse</h3>
      <div class="row"><span>Store</span><span><?= $v($doc['warehouse_name']) ?></span></div>
      <div class="row"><span>Bay / location</span><span><?= $v($doc['bay_location']) ?></span></div>
      <div class="row"><span>Received</span><span><?= $dt($doc['received_at']) ?></span></div>
      <?php if (!empty($doc['shipment_code'])): ?><div class="row"><span>Shipment</span><span><?= e($doc['shipment_code']) ?></span></div><?php endif; ?>
    </div>
    <?php if ($isPass): ?>
      <div class="box">
        <h3>Released to</h3>
        <div class="row"><span>Collected by</span><span><?= $v($doc['collected_by']) ?></span></div>
        <div class="row"><span>ID number</span><span><?= $v($doc['collected_id_no']) ?></span></div>
        <div class="row"><span>Truck</span><span><?= $v($doc['release_plate']) ?></span></div>
        <div class="row"><span>Customer order</span><span><?= $v($doc['customer_order_ref']) ?></span></div>
      </div>
      <div class="box">
        <h3>Release</h3>
        <div class="row"><span>Released at</span><span><?= $dt($doc['released_at']) ?></span></div>
        <div class="row"><span>Authorised by</span><span><?= $v($doc['authorised_name']) ?></span></div>
        <div class="row"><span>Trip</span><span><?= $v($doc['trip_code']) ?></span></div>
      </div>
    <?php else: ?>
      <div class="box">
        <h3>Delivered by</h3>
        <div class="row"><span>Person / driver</span><span><?= $v($doc['received_from']) ?></span></div>
        <div class="row"><span>Truck</span><span><?= $v($doc['vehicle_plate']) ?></span></div>
        <div class="row"><span>Condition</span><span><?= $v($doc['condition_note'], 'Good') ?></span></div>
      </div>
      <div class="box">
        <h3>Storage terms</h3>
        <div class="row"><span>Free days</span><span><?= (int) $doc['free_days'] ?></span></div>
        <div class="row"><span>After that</span><span><?= (float) $doc['daily_rate'] > 0 ? e($doc['currency'] . ' ' . number_format((float) $doc['daily_rate'], 2) . ' ' . strtolower(\Models\Storage::BASIS[$doc['charge_basis']] ?? '')) : 'As agreed' ?></span></div>
      </div>
    <?php endif; ?>
  </div>

  <table>
    <thead>
      <tr><th>Description of goods</th><th class="num">Unit</th>
        <?php if ($isPass): ?><th class="num">Released now</th><th class="num">Left in store after</th><?php else: ?><th class="num">Quantity received</th><th class="num">Weight (kg)</th><th class="num">Volume (m³)</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?= $v($doc['description']) ?></td>
        <td class="num"><?= $v($doc['unit']) ?></td>
        <?php if ($isPass): ?>
          <td class="num big"><?= $qty($doc['quantity']) ?></td>
          <td class="num"><?= $qty($doc['left_after']) ?></td>
        <?php else: ?>
          <td class="num big"><?= $qty($doc['quantity_received']) ?></td>
          <td class="num"><?= $qty($doc['weight_kg']) ?></td>
          <td class="num"><?= $qty($doc['volume_m3']) ?></td>
        <?php endif; ?>
      </tr>
    </tbody>
  </table>

  <div class="signatures">
    <?php if ($isPass): ?>
      <div class="sign"><h3>Storekeeper</h3><div class="line" style="height:34px;position:relative"><?php if (!empty($signature)): ?><img src="<?= $signature ?>" alt="Signature" style="position:absolute;left:0;bottom:1px;max-height:32px;max-width:60%;mix-blend-mode:multiply"><?php endif; ?></div><div class="label">Name and signature</div><?php if (!empty($company['seal'])): ?><img class="seal" src="<?= $company['seal'] ?>" alt="Company seal"><?php endif; ?></div>
      <div class="sign"><h3>Collected by</h3><div class="line"><?= e((string) $doc['collected_by']) ?></div><div class="label">Name</div><div class="line"></div><div class="label">Signature</div></div>
      <div class="sign"><h3>Gate check</h3><div class="line"></div><div class="label">Guard name and signature</div><div class="line"></div><div class="label">Time out</div></div>
    <?php else: ?>
      <div class="sign"><h3>Delivered by</h3><div class="line"><?= e((string) ($doc['received_from'] ?? '')) ?></div><div class="label">Name</div><div class="line"></div><div class="label">Signature</div></div>
      <div class="sign"><h3>Received by (storekeeper)</h3><div class="line"></div><div class="label">Name and signature</div><?php if (!empty($company['seal'])): ?><img class="seal" src="<?= $company['seal'] ?>" alt="Company seal"><?php endif; ?></div>
      <div class="sign"><h3>Remarks</h3><div class="line"></div><div class="line"></div></div>
    <?php endif; ?>
  </div>

  <footer>
    <span><?= $isPass ? 'Valid for one exit only. The gate keeps this copy.' : 'Goods are held at the owner\'s risk unless insured. Quantities as counted at receipt.' ?></span>
    <span><?= e($number) ?></span>
  </footer>
</div>
</body>
</html>
