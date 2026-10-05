<?php
/**
 * The invoice as a document: printed, saved as PDF from the browser, or handed
 * to the customer. The company seal goes on once the invoice is issued; a draft
 * or a cancelled invoice says so across the page and is never sealed.
 *
 * @var array $invoice
 * @var array $lines
 * @var array $payTo
 * @var string $currency
 * @var array $company
 */
$v = static fn (?string $value, string $empty = '—'): string => e(($value ?? '') !== '' ? $value : $empty);
$date = static fn (?string $value): string => ($value ?? '') !== '' ? e(date('d M Y', strtotime((string) $value))) : '—';
$money = static fn (float $amount): string => e(\Models\Currency::format($amount, $currency));
$status = (string) $invoice['status'];
$watermark = match ($status) {
    'draft' => 'DRAFT',
    'cancelled' => 'CANCELLED',
    default => '',
};
$sealed = $watermark === '' && !empty($company['seal']);
$paid = (float) $invoice['amount_paid'];
$balance = max(0.0, (float) $invoice['total_amount'] - $paid);
$statusLabel = ['draft' => 'Draft', 'issued' => 'Issued', 'partially_paid' => 'Partly paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'][$status] ?? ucfirst($status);
$hasDisbursements = in_array('disbursement', array_column($lines, 'line_kind'), true);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e($invoice['invoice_number']) ?></title>
<style>
  :root { --brand: <?= e($company['color']) ?>; --ink: #1d2733; --muted: #5b6670; --line: #d5dbe1; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #eef1f4; color: var(--ink); font: 13px/1.45 "Segoe UI", Arial, sans-serif; }
  .toolbar { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar button, .toolbar a { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 8px 14px; border-radius: 6px; font: inherit; cursor: pointer; text-decoration: none; }
  .toolbar .primary { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; }
  .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 12px auto 24px; background: #fff; padding: 14mm 14mm 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; }
  .watermark { position: absolute; top: 42%; left: 50%; transform: translate(-50%, -50%) rotate(-28deg); font-size: 120px; font-weight: 800; letter-spacing: 8px; color: rgba(200, 40, 40, .09); pointer-events: none; white-space: nowrap; }
  header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--brand); padding-bottom: 10px; }
  header img { max-height: 54px; max-width: 160px; margin-bottom: 6px; display: block; }
  .company { font-size: 18px; font-weight: 700; }
  .company-details { color: var(--muted); font-size: 11px; max-width: 105mm; }
  .doc-title { text-align: right; }
  .doc-title h1 { margin: 0; font-size: 26px; letter-spacing: 2px; color: var(--brand); }
  .doc-title .no { font-size: 15px; font-weight: 700; }
  .status { display: inline-block; margin-top: 4px; padding: 2px 9px; border-radius: 10px; font-size: 11px; font-weight: 700; background: #eef2f6; color: #4a5560; }
  .status.paid { background: #e8f5ec; color: #2f855a; }
  .status.overdue, .status.cancelled { background: #fdecec; color: #c53030; }
  .grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 10px; margin-top: 14px; }
  .box { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; }
  .box h3 { margin: 0 0 6px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--brand); }
  .bill-name { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
  .row { display: flex; gap: 8px; padding: 1.5px 0; }
  .row span:first-child { color: var(--muted); min-width: 30mm; }
  .row span:last-child { font-weight: 600; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 14px; }
  .items th { background: var(--ink); color: #fff; text-align: left; font-size: 11px; padding: 7px 8px; }
  .items td { border-bottom: 1px solid var(--line); padding: 8px; vertical-align: top; }
  .items .num { text-align: right; white-space: nowrap; }
  .items .kind { color: var(--muted); font-size: 11px; }
  .bottom { display: grid; grid-template-columns: 1fr 78mm; gap: 14px; margin-top: 12px; align-items: start; }
  table.totals { width: 100%; border-collapse: collapse; }
  .totals td { padding: 5px 8px; }
  .totals td:last-child { text-align: right; font-weight: 600; white-space: nowrap; }
  .totals .grand td { border-top: 2px solid var(--ink); font-size: 15px; font-weight: 800; }
  .totals .due td { background: #f4f6f8; font-weight: 800; }
  .pay p { margin: 0 0 6px; }
  .pay strong { display: block; }
  .note { margin-top: 10px; font-size: 12px; color: var(--muted); }
  .sign-area { display: grid; grid-template-columns: 1fr 70mm; gap: 14px; margin-top: 22px; align-items: end; }
  .sign { position: relative; min-height: 42mm; border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; }
  .sign h3 { margin: 0 0 6px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--brand); }
  .sign .who { font-weight: 700; }
  .sign .sig-line { position: absolute; left: 10px; right: 10px; bottom: 22px; border-bottom: 1px dotted #9aa4ad; }
  .sign .sig-label { position: absolute; left: 10px; bottom: 6px; color: var(--muted); font-size: 10.5px; }
  .sign .sig { position: absolute; left: 10px; bottom: 24px; max-height: 16mm; max-width: 55%; mix-blend-mode: multiply; }
  .seal { position: absolute; right: 8px; bottom: 6px; max-height: 34mm; max-width: 52mm; mix-blend-mode: multiply; transform: rotate(-6deg); }
  .thanks { font-size: 12px; color: var(--muted); }
  footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid var(--line); color: var(--muted); font-size: 10.5px; display: flex; justify-content: space-between; }
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
  <a href="<?= url(['invoices', $invoice['id']]) ?>">Back to invoice</a>
  <button class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="sheet">
  <?php if ($watermark !== ''): ?><div class="watermark"><?= e($watermark) ?></div><?php endif; ?>

  <header>
    <div>
      <?php if (!empty($company['logo'])): ?><img src="<?= $company['logo'] ?>" alt=""><?php endif; ?>
      <div class="company"><?= e($company['name']) ?></div>
      <div class="company-details"><?= e($company['details']) ?></div>
    </div>
    <div class="doc-title">
      <h1>INVOICE</h1>
      <div class="no"><?= e($invoice['invoice_number']) ?></div>
      <span class="status <?= e($status) ?>"><?= e($statusLabel) ?></span>
    </div>
  </header>

  <div class="grid">
    <div class="box">
      <h3>Bill to</h3>
      <div class="bill-name"><?= $v($invoice['customer_name']) ?></div>
      <?php if (($invoice['customer_address'] ?? '') !== ''): ?><div><?= e($invoice['customer_address']) ?></div><?php endif; ?>
      <div class="row"><span>TIN</span><span><?= $v($invoice['customer_tin']) ?></span></div>
      <?php if (($invoice['contact_name'] ?? '') !== ''): ?><div class="row"><span>Attention</span><span><?= e($invoice['contact_name']) ?></span></div><?php endif; ?>
      <?php if (($invoice['customer_phone'] ?? '') !== '' || ($invoice['customer_email'] ?? '') !== ''): ?>
        <div class="row"><span>Contact</span><span><?= e(implode('  ·  ', array_filter([(string) $invoice['customer_phone'], (string) $invoice['customer_email']]))) ?></span></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <h3>Invoice details</h3>
      <div class="row"><span>Invoice date</span><span><?= $date($invoice['issue_date']) ?></span></div>
      <div class="row"><span>Payment due</span><span><?= $date($invoice['due_date']) ?></span></div>
      <div class="row"><span>Your order (LPO)</span><span><?= $v($invoice['lpo_number']) ?></span></div>
      <?php if (($invoice['trip_code'] ?? '') !== ''): ?><div class="row"><span>Trip</span><span><?= e($invoice['trip_code']) ?></span></div><?php endif; ?>
      <?php if (($invoice['journey_code'] ?? '') !== ''): ?><div class="row"><span>Journey</span><span><?= e($invoice['journey_code']) ?></span></div><?php endif; ?>
      <div class="row"><span>Currency</span><span><?= e($currency) ?></span></div>
    </div>
  </div>

  <table class="items">
    <thead>
      <tr><th style="width:8mm">#</th><th>Description</th><th class="num" style="width:18mm">Qty</th><th class="num" style="width:34mm">Unit price</th><th class="num" style="width:36mm">Amount</th></tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $n => $line): ?>
        <tr>
          <td><?= $n + 1 ?></td>
          <td><?= e((string) $line['description']) ?><?php if ($line['line_kind'] === 'disbursement'): ?><div class="kind">Paid on your behalf, recharged at cost</div><?php endif; ?></td>
          <td class="num"><?= e(rtrim(rtrim(number_format((float) $line['quantity'], 2), '0'), '.')) ?></td>
          <td class="num"><?= $money((float) $line['unit_price']) ?></td>
          <td class="num"><?= $money((float) $line['line_total']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($lines === []): ?>
        <tr><td colspan="5" style="color:#9aa4ad;text-align:center">No lines on this invoice yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="bottom">
    <div>
      <?php if ($payTo !== []): ?>
        <div class="box pay">
          <h3>How to pay</h3>
          <?php foreach ($payTo as $method): ?>
            <p><strong><?= e($method['name']) ?></strong><?= e($method['details'] !== '' ? $method['details'] : 'Ask us for the details') ?></p>
          <?php endforeach; ?>
          <p class="thanks">Please quote <strong style="display:inline"><?= e($invoice['invoice_number']) ?></strong> with your payment.</p>
        </div>
      <?php endif; ?>
      <?php if ($hasDisbursements): ?><div class="note">Charges paid on your behalf (duty, port and government fees) are recharged at cost, without VAT; the receipts are available on request.</div><?php endif; ?>
      <?php if (trim((string) ($invoice['notes'] ?? '')) !== ''): ?><div class="note"><?= nl2br(e((string) $invoice['notes'])) ?></div><?php endif; ?>
    </div>
    <table class="totals">
      <tr><td>Subtotal</td><td><?= $money((float) $invoice['subtotal']) ?></td></tr>
      <tr><td>VAT at <?= e(rtrim(rtrim(number_format((float) $invoice['tax_rate'], 2, '.', ''), '0'), '.')) ?>%</td><td><?= $money((float) $invoice['tax_amount']) ?></td></tr>
      <tr class="grand"><td>Total</td><td><?= $money((float) $invoice['total_amount']) ?></td></tr>
      <?php if ($paid > 0): ?>
        <tr><td>Paid</td><td><?= $money($paid) ?></td></tr>
        <tr class="due"><td>Balance due</td><td><?= $money($balance) ?></td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="sign-area">
    <div class="thanks">Thank you for your business. If anything on this invoice looks wrong, please contact us within 7 days of receiving it.</div>
    <div class="sign">
      <h3>For <?= e($company['name']) ?></h3>
      <?php if (($invoice['issued_by_name'] ?? '') !== ''): ?><div class="who"><?= e($invoice['issued_by_name']) ?></div><?php endif; ?>
      <?php if (!empty($issuerSignature)): ?><img class="sig" src="<?= $issuerSignature ?>" alt="Signature"><?php endif; ?>
      <?php if ($sealed): ?><img class="seal" src="<?= $company['seal'] ?>" alt="Company seal"><?php endif; ?>
      <div class="sig-line"></div>
      <div class="sig-label">Authorised signature and stamp</div>
    </div>
  </div>

  <footer>
    <span><?= e($company['name']) ?><?= ($company['details'] ?? '') !== '' ? '  ·  ' . e($company['details']) : '' ?></span>
    <span><?= e($invoice['invoice_number']) ?></span>
  </footer>
</div>
</body>
</html>
