<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var ?int $id
 * @var array<string, mixed> $request
 * @var array<string, mixed> $type
 * @var array<string, mixed> $config
 * @var list<array<string, mixed>> $lines
 * @var array<string, array<int, array>> $lists
 * @var list<string> $errors
 */
use Models\Currency;
use Models\RequestForms;
use Models\RequestFormConfig;

$code = (string) $request['form_code'];
$payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
$value = static fn (string $key): string => (string) ($request[$key] ?? $payload[$key] ?? '');
$money = (int) $type['is_money'] === 1;
$columns = $config['columns'];
$action = $id === null ? url('form_requests') : url(['form_requests', $id]);
$lines = $lines !== [] ? $lines : [['item_code' => $config['item_default'], 'quantity' => '', 'unit' => $config['unit']]];
$firstLine = $lines[0];
$payeeLine = array_values(array_filter($lines, static fn (array $l): bool => !empty($l['payee_name'])))[0] ?? [];

$select = static function (string $name, array $options, string $current, string $empty = '', array $attributes = []): string {
    $html = '<select class="form-control" name="' . e($name) . '"';
    foreach ($attributes as $attribute => $attributeValue) {
        $html .= ' ' . $attribute . '="' . e($attributeValue) . '"';
    }
    $html .= '>';
    if ($empty !== '') {
        $html .= '<option value="">' . e($empty) . '</option>';
    }
    foreach ($options as $key => $option) {
        $label = is_array($option) ? (string) $option['label'] : (string) $option;
        $data = '';
        if (is_array($option)) {
            foreach (['unit', 'unit_cost', 'amount', 'currency', 'rate_type', 'payment_method', 'momo_number', 'momo_name', 'vehicle_id', 'driver_id', 'trailer_id', 'supplier_id'] as $field) {
                if (isset($option[$field]) && $option[$field] !== null && $option[$field] !== '') {
                    $data .= ' data-' . str_replace('_', '-', $field) . '="' . e((string) $option[$field]) . '"';
                }
            }
        }
        $html .= '<option value="' . e((string) $key) . '"' . $data . ((string) $key === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    }

    return $html . '</select>';
};

$rates = array_filter($lists['rates'], static fn (array $rate): bool => $config['rate_type'] === null || $rate['rate_type'] === $config['rate_type']);
$itemOptions = array_intersect_key(RequestForms::ITEMS, array_flip($config['items']));

/** One cell of the line table. */
$cell = static function (string $column, array $line, string $index) use ($select, $lists, $rates, $itemOptions, $config): string {
    $extra = is_array($line['extra'] ?? null) ? $line['extra'] : [];
    $name = static fn (string $field): string => 'lines[' . $index . '][' . $field . ']';
    $text = static fn (string $field, string $current, string $type = 'text', string $placeholder = '', string $class = ''): string =>
        '<input class="form-control ' . $class . '" type="' . $type . '" name="' . e($name($field)) . '" value="' . e($current) . '" placeholder="' . e($placeholder) . '"' . ($type === 'number' ? ' step="0.01" min="0"' : '') . '>';

    return match ($column) {
        'vehicle_id' => $select($name('vehicle_id'), $lists['vehicles'], (string) ($line['vehicle_id'] ?? ''), 'Truck', ['data-role' => 'vehicle']),
        'trailer_id' => $select($name('trailer_id'), $lists['trailers'], (string) ($line['trailer_id'] ?? ''), 'Trailer'),
        'driver_id' => $select($name('driver_id'), $lists['drivers'], (string) ($line['driver_id'] ?? ''), 'Driver', ['data-role' => 'driver']),
        'trip_id' => $select($name('trip_id'), $lists['trips'], (string) ($line['trip_id'] ?? ''), 'Trip', ['data-role' => 'trip']),
        'item_code' => $select($name('item_code'), $itemOptions, (string) ($line['item_code'] ?? $config['item_default']), ''),
        'item_id' => $select($name('item_id'), $lists['items'], (string) ($line['item_id'] ?? ''), 'From the catalogue', ['data-role' => 'item']),
        'description' => $text('description', (string) ($line['description'] ?? ''), 'text', 'What'),
        'quantity' => $text('quantity', isset($line['quantity']) && $line['quantity'] !== '' ? rtrim(rtrim((string) $line['quantity'], '0'), '.') : '', 'number', $config['unit'] === 'L' ? 'Litres' : 'Qty', 'lms-req-qty'),
        'unit' => $text('unit', (string) ($line['unit'] ?? $config['unit'] ?? ''), 'text', 'Unit', 'lms-req-unit'),
        'unit_price' => $text('unit_price', isset($line['unit_price']) && (float) $line['unit_price'] != 0 ? (string) $line['unit_price'] : '', 'number', 'Price', 'lms-req-price'),
        'amount' => $text('amount', isset($line['amount']) && (float) $line['amount'] != 0 ? (string) $line['amount'] : '', 'number', 'Amount', 'lms-req-amount'),
        'date' => $text('date', (string) ($extra['date'] ?? ''), 'date'),
        'position' => $select($name('position'), RequestFormConfig::POSITIONS, (string) ($extra['position'] ?? ''), 'Position'),
        'container_no' => $text('container_no', (string) ($extra['container_no'] ?? ''), 'text', 'MSKU1234567'),
        'operation' => $select($name('operation'), ['loading' => 'Loading', 'offloading' => 'Offloading'], (string) ($extra['operation'] ?? ''), 'Operation'),
        'size' => $select($name('size'), ['20ft' => '20 ft', '40ft' => '40 ft'], (string) ($extra['size'] ?? ''), 'Size'),
        'rate_id' => $select($name('rate_id'), $rates, (string) ($extra['rate_id'] ?? ''), 'Rate card', ['data-role' => 'rate']),
        'payee' => '<input class="form-control mb-1" name="' . e($name('payee_name')) . '" value="' . e((string) ($line['payee_name'] ?? '')) . '" placeholder="Paid to">'
            . '<input type="hidden" name="' . e($name('payee_type')) . '" value="' . e((string) ($line['payee_type'] ?? 'driver')) . '">'
            . '<input type="hidden" name="' . e($name('payee_id')) . '" value="' . e((string) ($line['payee_id'] ?? '')) . '">'
            . $select($name('payment_method'), ['momo' => 'MoMo', 'cash' => 'Cash', 'bank' => 'Bank'], (string) ($line['payment_method'] ?? 'momo'))
            . '<input class="form-control mt-1" name="' . e($name('momo_number')) . '" value="' . e((string) ($line['momo_number'] ?? '')) . '" placeholder="MoMo number">'
            . '<input type="hidden" name="' . e($name('momo_name')) . '" value="' . e((string) ($line['momo_name'] ?? '')) . '">',
        default => '',
    } . ($column === 'quantity' ? '<input type="hidden" name="' . e($name('standard_quantity')) . '" value="' . e((string) ($line['standard_quantity'] ?? '')) . '">' : '')
      . ($column === 'amount' ? '<input type="hidden" name="' . e($name('standard_amount')) . '" value="' . e((string) ($line['standard_amount'] ?? '')) . '">' : '');
};

$headings = [
    'vehicle_id' => 'Truck', 'trailer_id' => 'Trailer', 'driver_id' => 'Driver', 'trip_id' => 'Trip', 'item_code' => 'Kind', 'item_id' => 'Catalogue item',
    'description' => 'Description', 'quantity' => $config['unit'] === 'L' ? 'Litres' : 'Qty', 'unit' => 'Unit', 'unit_price' => 'Unit price', 'amount' => 'Amount',
    'date' => 'Date', 'position' => 'Tyre position', 'container_no' => 'Container no.', 'operation' => 'Loading / offloading', 'size' => 'Size', 'rate_id' => 'Rate', 'payee' => 'Paid to',
];
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker"><?= e(\Models\Company::name(current_company_id())) ?></p>
      <h2 class="lms-page-title"><i class="fe fe-file-text mr-2"></i><?= e(($id === null ? 'New ' : 'Edit ') . $type['form_name']) ?></h2>
      <?php if (!empty($type['description'])): ?><p class="lms-page-sub"><?= e($type['description']) ?></p><?php endif; ?>
    </div>
    <div class="lms-page-actions"><a class="btn btn-outline-secondary" href="<?= $id === null ? url('form_requests') : url(['form_requests', $id]) ?>">Cancel</a></div>
  </div>

  <?php if ($errors !== []): ?>
    <div class="alert alert-danger lms-error-block"><strong><i class="fe fe-alert-circle mr-2"></i>This could not be saved</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="lms-form" id="lms-request-form">
    <?= csrf_field() ?>
    <input type="hidden" name="form_code" value="<?= e($code) ?>">

    <section class="card shadow-sm lms-section mb-3">
      <header class="lms-section-head"><div><span class="lms-section-num">1</span><div><h3><i class="fe fe-edit fe-16 mr-2"></i>The request</h3></div></div></header>
      <div class="card-body"><div class="row">
        <div class="col-md-<?= $money ? 9 : 12 ?> form-group">
          <label>Subject <span class="lms-required">*</span></label>
          <input class="form-control" name="title" value="<?= e($value('title')) ?>" required placeholder="<?= e($type['form_name']) ?>">
        </div>
        <?php if ($money): ?>
          <div class="col-md-3 form-group"><label>Currency <span class="lms-required">*</span></label><?= $select('currency', Currency::options(), $value('currency') ?: \Models\Company::baseCurrency()) ?></div>
        <?php endif; ?>

        <?php foreach ($config['header'] as $field): ?>
          <?php if ($field === 'payee') { continue; } ?>
          <?php
          [$label, $input, $width] = match ($field) {
              'route_id' => ['Route', $select('route_id', $lists['routes'], $value('route_id'), 'Choose'), 4],
              'supplier_id' => [$config['supplier_label'], $select('supplier_id', $lists['suppliers'], $value('supplier_id'), 'Choose', ['data-role' => 'supplier']), 4],
              'customer_id' => ['Customer', $select('customer_id', $lists['customers'], $value('customer_id'), 'None'), 4],
              'vehicle_id' => ['Truck', $select('vehicle_id', $lists['vehicles'], $value('vehicle_id'), 'Choose'), 4],
              'driver_id' => ['Driver', $select('driver_id', $lists['drivers'], $value('driver_id'), 'Choose'), 4],
              'trip_id' => ['Trip', $select('trip_id', $lists['trips'], $value('trip_id'), 'None'), 4],
              'to_company_id' => ['Send to company', $select('to_company_id', $lists['companies'], $value('to_company_id'), 'Choose'), 4],
              'parent_request_id' => ['Against order (PO / fuel order)', $select('parent_request_id', $lists['orders'], $value('parent_request_id'), 'None'), 6],
              'period_from' => ['Period from', '<input class="form-control" type="date" name="period_from" value="' . e(substr($value('period_from'), 0, 10)) . '">', 3],
              'period_to' => ['Period to', '<input class="form-control" type="date" name="period_to" value="' . e(substr($value('period_to'), 0, 10)) . '">', 3],
              'needed_on' => ['Needed on', '<input class="form-control" type="date" name="needed_on" value="' . e(substr($value('needed_on'), 0, 10)) . '">', 3],
              'odometer' => ['Odometer', '<div class="input-group"><input class="form-control" type="number" min="0" name="odometer" value="' . e($value('odometer')) . '"><div class="input-group-append"><span class="input-group-text">km</span></div></div>', 3],
              'problem' => ['What is wrong', '<textarea class="form-control" name="problem" rows="2">' . e($value('problem')) . '</textarea>', 12],
              'support_type' => ['Support for', $select('support_type', RequestFormConfig::SUPPORT_TYPES, $value('support_type'), 'Choose'), 4],
              'location' => ['Where', '<input class="form-control" name="location" value="' . e($value('location')) . '" placeholder="Malaba border">', 4],
              'invoice_no' => ['Supplier invoice no.', '<input class="form-control" name="invoice_no" value="' . e($value('invoice_no')) . '">', 3],
              'invoice_date' => ['Invoice date', '<input class="form-control" type="date" name="invoice_date" value="' . e($value('invoice_date')) . '">', 3],
              'shipment_ref' => ['Shipment file / BoL', '<input class="form-control" name="shipment_ref" value="' . e($value('shipment_ref')) . '">', 4],
              'non_standard_reason' => ['Why an amount differs from the rate card', '<input class="form-control" name="non_standard_reason" value="' . e($value('non_standard_reason')) . '" placeholder="Detour, waiting, load">', 12],
              'notes' => ['Notes', '<textarea class="form-control" name="notes" rows="2">' . e($value('notes')) . '</textarea>', 12],
              default => [$field, '', 4],
          };
          ?>
          <div class="col-md-<?= $width ?> form-group"><label><?= e($label) ?></label><?= $input ?></div>
        <?php endforeach; ?>
      </div></div>
    </section>

    <?php if ($config['payee'] === 'header' && $config['payee_from'] === null || $config['payee'] === 'supplier'): ?>
      <section class="card shadow-sm lms-section mb-3">
        <header class="lms-section-head"><div><span class="lms-section-num">2</span><div><h3><i class="fe fe-user fe-16 mr-2"></i><?= e($config['payee_label']) ?></h3></div></div></header>
        <div class="card-body"><div class="row">
          <?php if ($config['payee'] === 'header'): ?>
            <div class="col-md-3 form-group"><label>Is</label><?= $select('payee_type', ['supplier' => 'A registered supplier', 'employee' => 'An employee', 'other' => 'Someone else'], (string) ($payeeLine['payee_type'] ?? 'supplier')) ?></div>
            <div class="col-md-3 form-group"><label>Supplier</label><?= $select('payee_id', $lists['suppliers'], (string) ($payeeLine['payee_id'] ?? ''), 'Choose', ['data-role' => 'payee-supplier']) ?></div>
            <div class="col-md-3 form-group"><label>Name <span class="lms-required">*</span></label><input class="form-control" name="payee_name" value="<?= e((string) ($payeeLine['payee_name'] ?? '')) ?>"></div>
          <?php endif; ?>
          <div class="col-md-3 form-group"><label>Paid by</label><?= $select('payment_method', ['momo' => 'MTN MoMo', 'cash' => 'Cash', 'bank' => 'Bank transfer'], (string) ($payeeLine['payment_method'] ?? ($config['payee'] === 'supplier' ? 'bank' : 'momo'))) ?></div>
          <div class="col-md-3 form-group"><label>MoMo number</label><input class="form-control" name="momo_number" value="<?= e((string) ($payeeLine['momo_number'] ?? '')) ?>"></div>
          <?php if ($config['payee'] === 'header'): ?>
            <div class="col-md-3 form-group"><label>Name registered on MoMo</label><input class="form-control" name="momo_name" value="<?= e((string) ($payeeLine['momo_name'] ?? '')) ?>"></div>
          <?php endif; ?>
        </div></div>
      </section>
    <?php endif; ?>

    <section class="card shadow-sm lms-section mb-3">
      <header class="lms-section-head"><div><span class="lms-section-num"><?= $config['payee'] === 'header' && $config['payee_from'] === null || $config['payee'] === 'supplier' ? 3 : 2 ?></span><div><h3><i class="fe fe-list fe-16 mr-2"></i>Lines</h3></div></div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="lms-req-add"><i class="fe fe-plus fe-12 mr-1"></i>Add a line</button></header>
      <div class="table-responsive">
        <table class="table table-sm mb-0 lms-req-lines">
          <thead><tr><?php foreach ($columns as $column): ?><th><?= e($config['labels'][$column] ?? $headings[$column] ?? $column) ?></th><?php endforeach; ?><th></th></tr></thead>
          <tbody>
            <?php foreach ($lines as $index => $line): ?>
              <tr><?php foreach ($columns as $column): ?><td><?= $cell($column, $line, (string) $index) ?></td><?php endforeach; ?>
                <td><button type="button" class="btn btn-sm btn-link text-danger lms-req-remove" title="Remove"><i class="fe fe-trash-2"></i></button>
                  <?php foreach (['item_code' => $config['item_default'], 'trip_id' => '', 'vehicle_id' => '', 'driver_id' => ''] as $hidden => $default): ?>
                    <?php if (!in_array($hidden, $columns, true) && !in_array($hidden, $config['header'], true)): ?><input type="hidden" name="lines[<?= $index ?>][<?= $hidden ?>]" value="<?= e((string) ($line[$hidden] ?? $default)) ?>"><?php endif; ?>
                  <?php endforeach; ?>
                </td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($money): ?><div class="card-body border-top text-right"><strong>Total: <span id="lms-req-total">0</span></strong></div><?php endif; ?>
    </section>

    <section class="card shadow-sm lms-section mb-3">
      <header class="lms-section-head"><div><span class="lms-section-num"><i class="fe fe-paperclip fe-12"></i></span><div><h3>Attachments</h3></div></div></header>
      <div class="card-body"><input type="file" class="form-control-file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx"><small class="text-muted">Scans, photos, quotations. PDF, image, Word or Excel.</small></div>
    </section>

    <div class="lms-form-bar">
      <span class="small text-muted">Saved as a draft, it stays yours to change. Sent, it is locked and goes to <?= e(strtolower(RequestForms::STEP_LABELS[$type['chain'] === 'om_confirm' ? 'confirm' : ($type['chain'] === 'finance_md' ? 'authorize' : 'verify')])) ?>.</span>
      <div>
        <button class="btn btn-light" type="submit" name="draft" value="1"><i class="fe fe-save fe-12 mr-1"></i>Save draft</button>
        <button class="btn btn-primary" type="submit" name="send" value="1"><i class="fe fe-send fe-12 mr-1"></i>Save and send</button>
      </div>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('lms-request-form');
  var body = form.querySelector('.lms-req-lines tbody');
  var next = body.querySelectorAll('tr').length;

  function number(input) { return input ? (parseFloat(String(input.value).replace(/,/g, '')) || 0) : 0; }
  function recalc() {
    var total = 0;
    body.querySelectorAll('tr').forEach(function (row) {
      var qty = row.querySelector('.lms-req-qty'), price = row.querySelector('.lms-req-price'), amount = row.querySelector('.lms-req-amount');
      if (amount && price && amount.dataset.typed !== '1') {
        var value = (qty ? (number(qty) || 1) : 1) * number(price);
        amount.value = value ? value.toFixed(2) : amount.value;
      }
      total += number(amount);
    });
    var shown = document.getElementById('lms-req-total');
    if (shown) { shown.textContent = total.toLocaleString(undefined, {maximumFractionDigits: 2}); }
  }

  form.addEventListener('input', function (e) {
    if (e.target.classList.contains('lms-req-amount')) { e.target.dataset.typed = '1'; }
    recalc();
  });
  form.addEventListener('change', function (e) {
    var select = e.target, row = select.closest('tr'), option = select.selectedOptions ? select.selectedOptions[0] : null;
    if (!option) { return; }
    var role = select.getAttribute('data-role');
    if (role === 'rate' && row) {
      var price = row.querySelector('.lms-req-price'), amount = row.querySelector('.lms-req-amount');
      if (price && option.dataset.amount) { price.value = option.dataset.amount; }
      else if (amount && option.dataset.amount) { amount.value = option.dataset.amount; amount.dataset.typed = ''; }
    }
    if (role === 'item' && row) {
      var unit = row.querySelector('.lms-req-unit'), cost = row.querySelector('.lms-req-price');
      if (unit && option.dataset.unit) { unit.value = option.dataset.unit; }
      if (cost && option.dataset.unitCost && !cost.value) { cost.value = option.dataset.unitCost; }
    }
    if (role === 'vehicle' && row) {
      // A truck brings its trailer and its usual driver with it.
      var trailer = row.querySelector('select[name$="[trailer_id]"]'), driver = row.querySelector('select[name$="[driver_id]"]');
      if (trailer && option.dataset.trailerId && !trailer.value) { trailer.value = option.dataset.trailerId; }
      if (driver && option.dataset.driverId && !driver.value) { driver.value = option.dataset.driverId; driver.dispatchEvent(new Event('change', {bubbles: true})); }
    }
    if (role === 'driver' && row) {
      var payee = row.querySelector('input[name$="[payee_name]"]');
      if (payee && !payee.value) {
        payee.value = option.textContent;
        row.querySelector('input[name$="[payee_id]"]').value = option.value;
        var method = row.querySelector('select[name$="[payment_method]"]');
        if (method && option.dataset.paymentMethod) { method.value = option.dataset.paymentMethod === 'cash' ? 'cash' : 'momo'; }
        row.querySelector('input[name$="[momo_number]"]').value = option.dataset.momoNumber || '';
        row.querySelector('input[name$="[momo_name]"]').value = option.dataset.momoName || '';
      }
    }
    if (role === 'payee-supplier') {
      var name = form.querySelector('input[name="payee_name"]');
      if (name && !name.value) { name.value = option.textContent; }
    }
    recalc();
  });

  document.getElementById('lms-req-add').addEventListener('click', function () {
    var last = body.querySelector('tr:last-child');
    var row = last.cloneNode(true);
    row.querySelectorAll('input, select').forEach(function (input) {
      input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + next + ']');
      if (input.type === 'hidden' && /\[(item_code)\]$/.test(input.name)) { return; }
      if (input.tagName === 'SELECT') { input.selectedIndex = /payment_method|item_code/.test(input.name) ? input.selectedIndex : 0; }
      else { input.value = /\[unit\]$/.test(input.name) ? input.value : ''; delete input.dataset.typed; }
    });
    body.appendChild(row);
    next++;
  });
  body.addEventListener('click', function (e) {
    var button = e.target.closest('.lms-req-remove');
    if (!button) { return; }
    if (body.querySelectorAll('tr').length > 1) { button.closest('tr').remove(); }
    else { button.closest('tr').querySelectorAll('input:not([type=hidden])').forEach(function (i) { i.value = ''; }); }
    recalc();
  });
  recalc();
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
