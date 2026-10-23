<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var string $name */
/** @var list<array<string, mixed>> $trips */
/** @var list<array<string, mixed>> $notes */
/** @var list<array<string, mixed>> $invoices */
/** @var array<string, float> $balance */
use Models\Currency;
use Models\TripLife;

$number = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 3), '0'), '.');
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Customer portal</p>
      <h2 class="lms-page-title"><i class="fe fe-briefcase mr-2"></i><?= e($name) ?></h2>
      <p class="lms-page-sub">Your trips, the delivery notes signed at your premises, and your invoices.</p>
    </div>
  </div>

  <div class="lms-home-cards mb-4">
    <span class="lms-home-card lms-home-primary"><span class="lms-home-icon"><i class="fe fe-truck"></i></span><span class="lms-home-body"><strong class="lms-home-count"><?= count(array_filter($trips, static fn (array $t): bool => !in_array($t['status'], ['closed', 'cancelled'], true))) ?></strong><span class="lms-home-label">Trips in progress</span></span></span>
    <span class="lms-home-card lms-home-<?= $balance === [] ? 'success' : 'warning' ?>"><span class="lms-home-icon"><i class="fe fe-dollar-sign"></i></span><span class="lms-home-body"><strong class="lms-home-count"><?= $balance === [] ? '0' : e(implode(' · ', array_map(static fn (string $c, float $v): string => Currency::format($v, $c), array_keys($balance), $balance))) ?></strong><span class="lms-home-label">Balance to pay</span></span></span>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Invoices</strong></div>
    <?php if ($invoices === []): ?>
      <div class="card-body text-muted text-center py-4">No invoice yet.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Invoice</th><th>From</th><th>Date</th><th>Due</th><th class="text-right">Amount</th><th class="text-right">Paid</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($invoices as $invoice): ?>
            <tr>
              <td><strong><?= e($invoice['invoice_number']) ?></strong><?= $invoice['lpo_number'] ? '<small class="d-block text-muted">LPO ' . e($invoice['lpo_number']) . '</small>' : '' ?></td>
              <td><?= e($invoice['company_name'] ?? '') ?></td>
              <td><?= e(human_date($invoice['issue_date'])) ?></td>
              <td><?= e(human_date($invoice['due_date'])) ?></td>
              <td class="text-right"><?= e(Currency::format((float) $invoice['total_amount'], (string) $invoice['currency'])) ?></td>
              <td class="text-right"><?= e(Currency::format((float) $invoice['amount_paid'], (string) $invoice['currency'])) ?></td>
              <td><span class="badge badge-<?= ['paid' => 'success', 'overdue' => 'danger', 'partially_paid' => 'info'][$invoice['status']] ?? 'warning' ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $invoice['status']))) ?></span></td>
              <td class="text-right"><?php if ($invoice['request_id']): ?><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= url(['portal', 'documents', $invoice['request_id'], 'pdf']) ?>"><i class="fe fe-file-text fe-12 mr-1"></i>PDF</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Delivery notes</strong></div>
    <?php if ($notes === []): ?>
      <div class="card-body text-muted text-center py-4">No delivery note yet.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Delivery note</th><th>Trip</th><th>Date</th><th class="text-right">Loaded</th><th class="text-right">Delivered</th><th class="text-right">Short</th><th>Received by</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($notes as $note): ?>
            <?php $data = $note['data']; ?>
            <tr>
              <td><?= e($note['request_no']) ?></td>
              <td><?= e($note['reference_code'] ?? '') ?></td>
              <td><?= e(human_date($note['created_at'])) ?></td>
              <td class="text-right"><?= e($number($data['loaded'] ?? 0)) ?> <?= e((string) ($data['unit'] ?? '')) ?></td>
              <td class="text-right"><?= e($number($data['delivered'] ?? 0)) ?></td>
              <td class="text-right <?= (float) ($data['shortage'] ?? 0) > 0 ? 'text-danger' : '' ?>"><?= e($number($data['shortage'] ?? 0)) ?></td>
              <td><?= e((string) ($data['received_by'] ?? '')) ?></td>
              <td class="text-right text-nowrap">
                <?php if (!empty($data['photo'])): ?><a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?= url(['portal', 'documents', $note['id'], 'photo']) ?>"><i class="fe fe-camera fe-12"></i></a><?php endif; ?>
                <a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?= url(['portal', 'documents', $note['id'], 'pdf']) ?>"><i class="fe fe-file-text fe-12"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-header"><strong>Trips</strong></div>
    <?php if ($trips === []): ?>
      <div class="card-body text-muted text-center py-4">No trip yet.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Trip</th><th>Truck</th><th>Cargo</th><th>Left</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($trips as $trip): ?>
            <tr>
              <td><?= e($trip['reference_code']) ?></td>
              <td><?= e($trip['plate_number'] ?? '') ?></td>
              <td><?= e(trim(($trip['cargo_summary'] ?? '') . ' ' . ($trip['container_numbers'] ?? ''))) ?></td>
              <td><?= $trip['departure_at'] ? e(human_date($trip['departure_at'], true)) : '—' ?></td>
              <td><span class="badge badge-light"><?= e(TripLife::STATUS[$trip['status']] ?? $trip['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
