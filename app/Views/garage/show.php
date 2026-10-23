<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var array<string, mixed> $job */
/** @var list<array<string, mixed>> $parts */
/** @var list<array<string, mixed>> $notes */
use Models\Company;
use Models\Currency;
use Models\RequestForms;

$open = in_array($job['status'], ['open', 'scheduled', 'in_progress'], true);
$base = Company::baseCurrency((int) $job['company_id']);
$statusLabel = ['open' => 'Open', 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Closed', 'cancelled' => 'Cancelled'];
$total = array_sum(array_map(static fn (array $p): float => (float) $p['line_total'], $parts));
?>
<div class="container-fluid lms-form-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Garage · job card</p>
      <h2 class="lms-page-title"><i class="fe fe-tool mr-2"></i><?= e($job['work_order_code']) ?> <span class="badge badge-<?= $open ? 'warning' : 'success' ?> align-middle"><?= e($statusLabel[$job['status']] ?? $job['status']) ?></span></h2>
      <p class="lms-page-sub"><?= e($job['service_name']) ?> · <?= e($job['plate_number'] ?? 'no truck') ?><?= $job['trailer_number'] ? ' / ' . e($job['trailer_number']) : '' ?><?= $job['owner_company'] ? ' · truck of ' . e($job['owner_company']) : '' ?></p>
    </div>
    <div class="lms-page-actions">
      <a class="btn btn-outline-secondary" href="<?= url('garage') ?>">Garage</a>
      <?php if ($job['status'] === 'open' && role_can('garage', 'edit')): ?>
        <form method="post" action="<?= url(['garage', $job['id'], 'start']) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><i class="fe fe-play fe-12 mr-1"></i>Start work</button></form>
      <?php endif; ?>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <section class="card shadow-sm lms-section">
        <div class="card-header"><strong>The job</strong></div>
        <div class="card-body">
          <div class="row small">
            <div class="col-md-4 mb-2"><span class="text-muted d-block">Truck km now</span><?= $job['mileage'] !== null ? e(number_format((int) $job['mileage'])) . ' km' : '—' ?></div>
            <div class="col-md-4 mb-2"><span class="text-muted d-block">Opened</span><?= e(human_date($job['started_at'] ?? $job['created_at'], true)) ?></div>
            <div class="col-md-4 mb-2"><span class="text-muted d-block">From</span><?php if ($job['request_id']): ?><a href="<?= url(['form_requests', $job['request_id']]) ?>"><?= e(trim(($job['request_form'] ?? '') . ' ' . ($job['request_no'] ?? ''))) ?></a><?php else: ?>—<?php endif; ?></div>
            <div class="col-md-12 mb-2"><span class="text-muted d-block">Problem</span><?= nl2br(e((string) ($job['complaint'] ?: '—'))) ?></div>
            <?php if (!$open): ?>
              <div class="col-md-12 mb-2"><span class="text-muted d-block">Work done</span><?= nl2br(e((string) $job['work_done'])) ?></div>
              <div class="col-md-4"><span class="text-muted d-block">Odometer</span><?= $job['odometer_reading'] ? e(number_format((int) $job['odometer_reading'])) . ' km' : '—' ?></div>
              <div class="col-md-4"><span class="text-muted d-block">Closed</span><?= e(human_date($job['completed_at'], true)) ?> · <?= e($job['closed_name'] ?? '') ?></div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="card shadow-sm lms-section">
        <div class="card-header"><strong>Parts issued from the store</strong> <span class="text-muted small">— charged to the truck</span></div>
        <?php if ($parts === []): ?>
          <div class="card-body text-muted small">Nothing issued yet.</div>
        <?php else: ?>
          <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Part</th><th class="text-right">Qty</th><th class="text-right">Unit cost</th><th class="text-right">Total</th><th>Issued</th></tr></thead>
            <tbody>
              <?php foreach ($parts as $part): ?>
                <tr>
                  <td><?= e($part['part_name']) ?><?= $part['part_number'] ? ' <small class="text-muted">' . e($part['part_number']) . '</small>' : '' ?></td>
                  <td class="text-right"><?= e(rtrim(rtrim(number_format((float) $part['quantity'], 2), '0'), '.')) ?></td>
                  <td class="text-right"><?= e(number_format((float) $part['unit_cost'], 2)) ?></td>
                  <td class="text-right"><?= e(number_format((float) $part['line_total'], 2)) ?></td>
                  <td><?= e(human_date($part['issued_at'] ?? $part['created_at'], true)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="font-weight-bold"><td colspan="3">Total</td><td class="text-right"><?= e(Currency::format($total, $base)) ?></td><td></td></tr></tfoot>
          </table></div>
        <?php endif; ?>
      </section>

      <?php if ($open && role_can('garage', 'edit')): ?>
        <section class="card shadow-sm lms-section">
          <div class="card-header"><strong>Close the job card</strong></div>
          <form method="post" action="<?= url(['garage', $job['id'], 'close']) ?>">
            <?= csrf_field() ?>
            <div class="card-body row">
              <div class="col-md-12 form-group lms-field is-required"><label>Work done <span class="lms-required">*</span></label><textarea class="form-control" name="work_done" rows="3" required placeholder="Replaced diesel filter and 2 oil filters, changed 25 L engine oil"></textarea></div>
              <div class="col-md-12 form-group"><label>What was found</label><textarea class="form-control" name="diagnosis" rows="2"><?= e((string) ($job['diagnosis'] ?? '')) ?></textarea></div>
              <div class="col-md-4 form-group lms-field is-required"><label>Odometer <span class="lms-required">*</span></label><div class="input-group"><input class="form-control" type="number" min="0" name="odometer" value="<?= e((string) ($job['odometer_reading'] ?: $job['mileage'])) ?>" required><div class="input-group-append"><span class="input-group-text">km</span></div></div></div>
              <div class="col-md-4 form-group"><label>Labour hours</label><input class="form-control" type="number" step="0.5" min="0" name="labour_hours" value="<?= e((string) ($job['labour_hours'] ?? '')) ?>"></div>
              <div class="col-md-4 form-group d-flex align-items-end"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="service_done" name="service_done" value="1" <?= $job['maintenance_type'] === 'preventive' ? 'checked' : '' ?>><label class="custom-control-label" for="service_done">This was the truck's service (next one set an interval later)</label></div></div>
            </div>
            <div class="card-footer text-right"><button class="btn btn-success" type="submit"><i class="fe fe-check fe-12 mr-1"></i>Close the job card</button></div>
          </form>
        </section>
      <?php endif; ?>
    </div>

    <div class="col-lg-4">
      <section class="card shadow-sm lms-section">
        <div class="card-header"><strong>Paperwork</strong></div>
        <ul class="list-group list-group-flush">
          <?php foreach ($notes as $note): ?>
            <?php $isNote = $note['form_code'] === 'F4'; ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>
                <a href="<?= $isNote && can_view('store_issues') ? url(['store_issues', $note['id']]) : url(['form_requests', $note['id']]) ?>"><?= e(RequestForms::label($note)) ?></a>
                <small class="d-block text-muted"><?= e($note['title']) ?></small>
              </span>
              <span class="badge badge-light"><?= e(RequestForms::STATUS[$note['status']] ?? $note['status']) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if ($notes === []): ?><li class="list-group-item text-muted small">No issue note or order.</li><?php endif; ?>
        </ul>
      </section>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
