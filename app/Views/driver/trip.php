<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var array<string, mixed> $driver
 * @var ?array $trip
 * @var list<array<string, mixed>> $legs
 * @var ?array $om
 * @var ?array $money
 * @var list<array<string, mixed>> $toConfirm
 * @var list<array<string, mixed>> $events
 */
use Models\Currency;
use Models\TripLife;

$pageScripts = ['/assets/js/lms-driver.js'];
$driverWords = [
    'depart' => ['Departed', 'truck'],
    'arrive_turnaround' => ['Arrived ' . ($trip['turnaround_point'] ?? 'at the turnaround point'), 'flag'],
    'start_loading' => ['Loading started', 'download'],
    'loaded' => ['Loading done', 'check-square'],
    'arrive_customer' => ['At the customer: offloading', 'upload'],
    'arrive_base' => ['Back at base', 'home'],
];
?>
<div class="container-fluid lms-app">
  <a class="lms-app-back" href="<?= url('my-trips') ?>"><i class="fe fe-arrow-left mr-1"></i>Back</a>
  <div class="lms-offline-note alert alert-warning d-none" data-offline-note><i class="fe fe-wifi-off mr-1"></i>No signal: your taps are kept and sent when the signal returns.</div>
  <div class="lms-outbox alert alert-info d-none" data-outbox-note></div>

  <?php if ($trip === null): ?>
    <div class="card shadow-sm"><div class="card-body text-center py-5"><i class="fe fe-coffee fe-32 d-block mb-2 text-muted"></i>No trip right now. The OM will send you the next one.</div></div>
  <?php else: ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h3 class="mb-1"><?= e($trip['reference_code']) ?> <span class="badge badge-primary"><?= e(TripLife::STATUS[$trip['status']] ?? $trip['status']) ?></span></h3>
        <p class="mb-2"><strong><?= e($trip['route_code'] . ' — ' . $trip['route_name']) ?></strong></p>
        <dl class="row mb-0">
          <dt class="col-5">Truck</dt><dd class="col-7"><?= e($trip['plate_number']) ?><?= $trip['trailer_number'] ? ' + ' . e($trip['trailer_number']) : '' ?></dd>
          <dt class="col-5">Cargo</dt><dd class="col-7"><?= e(trim(($trip['cargo_quantity'] !== null ? rtrim(rtrim((string) $trip['cargo_quantity'], '0'), '.') . ' ' . $trip['cargo_type'] : '') . ' ' . ($trip['cargo_summary'] ?? '') . ' ' . ($trip['container_numbers'] ?? ''))) ?: '—' ?></dd>
          <dt class="col-5">Customer</dt><dd class="col-7"><?= e($trip['customer_name'] ?? '—') ?></dd>
          <dt class="col-5">Expected</dt><dd class="col-7"><?= e(rtrim(rtrim((string) $trip['expected_days'], '0'), '.')) ?> days<?= $trip['max_return_days'] !== null ? ', back from ' . e($trip['turnaround_point'] ?: 'turnaround') . ' within ' . e(rtrim(rtrim((string) $trip['max_return_days'], '0'), '.')) . ' days' : '' ?></dd>
          <?php if ($trip['return_due_at']): ?><dt class="col-5">Back by</dt><dd class="col-7"><strong><?= e(human_date($trip['return_due_at'], true)) ?></strong></dd><?php endif; ?>
          <?php if ($om !== null): ?><dt class="col-5">OM</dt><dd class="col-7"><?= e($om['full_name']) ?><?php if ($om['phone']): ?> · <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $om['phone'])) ?>"><i class="fe fe-phone"></i> Call</a><?php endif; ?><?php if ($om['whatsapp_number'] ?: $om['phone']): ?> · <a href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) ($om['whatsapp_number'] ?: $om['phone']))) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd><?php endif; ?>
        </dl>
      </div>
    </div>

    <?php if ($trip['status'] === 'planned' && $money !== null): ?>
      <div class="alert alert-info"><i class="fe fe-clock mr-1"></i><?= !$money['authorized'] ? 'The trip money is being approved.' : (!$money['paid'] ? 'The money is approved and waiting for the Cashier.' : 'The money was sent: tap "Money received" when it arrives.') ?></div>
    <?php endif; ?>

    <?php foreach ($toConfirm as $line): ?>
      <form method="post" action="<?= url(['my-trips', 'money', $line['id'], 'confirm']) ?>" class="lms-app-money" data-offline>
        <?= csrf_field() ?>
        <div><strong><?= e(Currency::format((float) $line['amount'], (string) $line['currency'])) ?></strong> sent to you <small class="d-block"><?= e($line['request_no']) ?></small></div>
        <button class="btn btn-success btn-lg" type="submit"><i class="fe fe-check mr-1"></i>Money received</button>
      </form>
    <?php endforeach; ?>

    <?php foreach (TripLife::ACTIONS as $action => [$from]): ?>
      <?php if (!in_array($trip['status'], $from, true)) { continue; } ?>
      <form method="post" action="<?= url(['my-trips', 'trip', $trip['id'], 'act', $action]) ?>" enctype="multipart/form-data" class="card shadow-sm mb-2 lms-app-step" data-offline data-geo>
        <?= csrf_field() ?>
        <input type="hidden" name="lat"><input type="hidden" name="lng">
        <div class="card-body">
          <?php if (in_array($action, ['depart', 'arrive_base'], true)): ?>
            <label class="small mb-1">Kilometres on the clock</label>
            <input class="form-control form-control-lg mb-2" type="number" inputmode="numeric" min="0" name="odometer" placeholder="<?= e((string) $trip['vehicle_mileage']) ?>">
          <?php endif; ?>
          <?php if ($action === 'loaded'): ?>
            <label class="small mb-1">Quantity loaded <span class="lms-required">*</span></label>
            <input class="form-control form-control-lg mb-2" type="number" inputmode="decimal" min="0" step="0.01" name="quantity" required value="<?= e((string) ($trip['cargo_quantity'] ?? '')) ?>">
            <label class="small mb-1">Photo of the loading document</label>
            <input class="form-control-file mb-2" type="file" name="photo" accept="image/*" capture="environment">
          <?php endif; ?>
          <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fe fe-<?= e($driverWords[$action][1]) ?> mr-2"></i><?= e($driverWords[$action][0]) ?></button>
        </div>
      </form>
    <?php endforeach; ?>

    <?php if ($trip['status'] === 'offloading'): ?><a class="btn btn-outline-primary btn-lg btn-block mb-2" href="<?= url(['my-trips', 'delivery']) ?>"><i class="fe fe-clipboard mr-2"></i>Fill the delivery note</a><?php endif; ?>
    <?php if (in_array($trip['status'], ['returning', 'arrived'], true)): ?><a class="btn btn-outline-primary btn-lg btn-block mb-2" href="<?= url(['my-trips', 'handover']) ?>"><i class="fe fe-key mr-2"></i>Hand over the truck</a><?php endif; ?>

    <?php if ($legs !== []): ?>
      <div class="card shadow-sm mb-3"><div class="card-header"><strong>Route</strong></div>
        <ul class="list-group list-group-flush small"><?php foreach ($legs as $leg): ?><li class="list-group-item"><?= (int) $leg['leg_no'] ?>. <?= e($leg['from_place']) ?> → <?= e($leg['to_place']) ?><?= $leg['post_name'] ? ' · border ' . e($leg['post_name']) : '' ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>What happened</strong></div>
      <ul class="list-group list-group-flush small"><?php foreach (array_slice($events, 0, 12) as $event): ?><li class="list-group-item"><strong><?= e(ucfirst(str_replace('_', ' ', (string) $event['event_type']))) ?></strong> · <?= e(human_date($event['happened_at'], true)) ?><?= $event['note'] ? '<div class="text-muted">' . e($event['note']) . '</div>' : '' ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
