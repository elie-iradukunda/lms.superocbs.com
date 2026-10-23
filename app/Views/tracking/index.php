<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/**
 * @var list<array<string, mixed>> $fleet last known position of each truck
 * @var int $silence hours after which a truck counts as quiet
 */
?>
<link rel="stylesheet" href="<?= asset('assets/vendor/leaflet/leaflet.css') ?>">
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Transport</p>
      <h2 class="lms-page-title"><i class="fe fe-map mr-2"></i>Live tracking</h2>
      <p class="lms-page-sub">Where every truck is now, from its GPS tracker or from the driver's phone while My trips is open. A truck inside a border post's circle is recorded as arrived there on its own. The map refreshes every minute.</p>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="lms-map lms-map-large" data-lms-map data-source="<?= e(url(['tracking', 'positions'])) ?>" data-refresh="60"></div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between"><strong>Trucks reporting</strong><small class="text-muted">Quiet for more than <?= (int) $silence ?> h is shown in red</small></div>
    <?php if ($fleet === []): ?>
      <div class="card-body text-muted">
        No truck has reported a position in the last week. Positions arrive two ways:
        <ul class="mb-0 mt-2">
          <li><strong>The driver's phone</strong>: when a trip is on the road, My trips asks the driver to share the location and sends it every few minutes while the page is open.</li>
          <li><strong>A GPS tracker</strong>: put the tracker's ID on the vehicle, set the tracker key under Company settings, and point the tracker or your Traccar / Wialon server at <code><?= e(rtrim((string) config('app.url', ''), '/') . '/api/gps/positions') ?></code>.</li>
        </ul>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Truck</th><th>Trip</th><th>Driver</th><th>Last position</th><th>Speed</th><th>From</th></tr></thead>
          <tbody>
          <?php foreach ($fleet as $truck): ?>
            <?php $stale = (int) $truck['minutes_ago'] > $silence * 60; ?>
            <tr>
              <td><strong><?= e($truck['plate_number']) ?></strong></td>
              <td><?php if ($truck['trip']): ?><a href="<?= url(['trips', (int) $truck['trip_id']]) ?>"><?= e($truck['trip']) ?></a><div class="small text-muted"><?= e($truck['pickup_location'] . ' → ' . $truck['destination']) ?></div><?php else: ?><span class="text-muted">Not on a trip</span><?php endif; ?></td>
              <td><?= e($truck['driver'] ?? '—') ?><?php if (!empty($truck['driver_phone'])): ?> · <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $truck['driver_phone'])) ?>"><?= e($truck['driver_phone']) ?></a><?php endif; ?></td>
              <td class="<?= $stale ? 'text-danger' : '' ?>"><?= e(time_ago((string) $truck['recorded_at'])) ?> <small class="text-muted d-block"><?= e(number_format((float) $truck['latitude'], 5)) ?>, <?= e(number_format((float) $truck['longitude'], 5)) ?></small></td>
              <td><?= $truck['speed_kmh'] !== null ? e(number_format((float) $truck['speed_kmh'])) . ' km/h' : '—' ?></td>
              <td><span class="badge badge-pill badge-light"><?= $truck['source'] === 'device' ? 'GPS tracker' : 'Driver phone' ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
