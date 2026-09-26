<?php
/**
 * The front door.
 *
 * There is nothing to read here before signing in: no marketing page, no list of
 * accounts. The address bar's root is the sign-in form, and everything else in
 * the system sits behind it.
 */
$baseUrl = config('app.base_url', '');
$company = company_name();
$minutes = isset($_GET['minutes']) ? (int) $_GET['minutes'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Sign in | <?= e($company) ?></title>
  <link rel="icon" href="<?= $baseUrl ?>/assets/img/logistics-logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <?php /* Claude's own typefaces (Styrene, Tiempos) are commercial; these are
           the nearest free ones: a serif for headings, a clean sans for text. */ ?>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/feather.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app-light.css">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-modules.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-background.css') ?>">
</head>
<body class="lms-auth-page">
  <main class="lms-auth-split" id="login">
  <?php /* The brand side, cut from the form side along a "/" slant. Decoration
           only: everything a person needs is on the form side. */ ?>
  <section class="lms-auth-side" aria-hidden="true">
    <div class="lms-auth-side-inner">
      <span class="lms-auth-mark">L</span>
      <div>
        <p class="lms-auth-side-name">LMS</p>
        <p class="lms-auth-side-tag">Logistics Management System</p>
      </div>
    </div>

    <?php
    // One load's journey through the system, told as the truck drives it.
    // Without GSAP (or with reduced motion) the five steps show as a list.
    $story = [
        ['Request & dispatch', 'A transport request becomes a trip, with a vehicle and driver assigned.'],
        ['On the road', 'Fuel, expenses and border clearance are recorded along the way.'],
        ['Stored & tracked', 'Goods are received at the warehouse and stock updates itself.'],
        ['Delivered & invoiced', 'Proof of delivery is captured and the invoice is issued.'],
        ['Paid & reported', 'Payments post to the books; reports show cost and profit per trip.'],
    ];
    ?>
    <div class="lms-auth-story" id="lmsStory">
      <ol>
        <?php foreach ($story as $index => [$storyTitle, $storyText]): ?>
          <li>
            <strong><?= e($storyTitle) ?></strong>
            <span class="lms-story-text"><?= e($storyText) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="lms-story-dots"><?= str_repeat('<span></span>', count($story)) ?></div>
    </div>

    <?php /* A truck doing the rounds: depot, warehouse, customer, and back.
             GSAP drives it; without GSAP it stays parked at the depot. */ ?>
    <svg class="lms-auth-scene" viewBox="0 0 320 152" role="presentation" focusable="false">
      <?php foreach ([['x' => 12, 'label' => 'Depot'], ['x' => 128, 'label' => 'Warehouse'], ['x' => 244, 'label' => 'Customer']] as $house): ?>
        <g class="lms-scene-house" transform="translate(<?= $house['x'] ?> 0)">
          <polygon points="-4,80 32,56 68,80" class="lms-scene-roof"/>
          <rect x="0" y="80" width="64" height="44" class="lms-scene-wall"/>
          <rect x="18" y="94" width="28" height="30" class="lms-scene-door"/>
          <line x1="18" y1="102" x2="46" y2="102" class="lms-scene-slat"/>
          <line x1="18" y1="110" x2="46" y2="110" class="lms-scene-slat"/>
          <line x1="18" y1="118" x2="46" y2="118" class="lms-scene-slat"/>
          <text x="32" y="150" text-anchor="middle" class="lms-scene-label"><?= e($house['label']) ?></text>
        </g>
      <?php endforeach; ?>

      <rect x="0" y="124" width="320" height="12" class="lms-scene-road"/>
      <line x1="0" y1="130" x2="320" y2="130" class="lms-scene-lane"/>

      <g id="lmsTruck" transform="translate(22 100)">
        <g id="lmsTruckBody">
          <rect x="0" y="0" width="30" height="19" rx="2" class="lms-scene-box"/>
          <rect x="30" y="6" width="13" height="13" rx="2.5" class="lms-scene-box"/>
          <rect x="33" y="8.5" width="7" height="5" rx="1" class="lms-scene-window"/>
        </g>
        <g class="lms-scene-wheel" transform="translate(9 21)"><circle r="4.2"/><line x1="-4.2" y1="0" x2="4.2" y2="0"/></g>
        <g class="lms-scene-wheel" transform="translate(35 21)"><circle r="4.2"/><line x1="-4.2" y1="0" x2="4.2" y2="0"/></g>
      </g>
    </svg>
  </section>

  <section class="lms-auth-card lms-auth-form">
    <span class="lms-auth-brand"><img src="<?= $baseUrl ?>/assets/img/logistics-logo.svg" alt="" height="38"></span>
    <h1 class="mb-4">Sign in</h1>

    <?php if ($loginError === 'locked'): ?>
      <div class="alert alert-danger">
        <strong>This account is locked.</strong>
        <p class="small mb-0">Too many sign-ins failed in a row. Try again
        <?= $minutes > 0 ? 'in about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') : 'once an administrator unlocks it' ?>.</p>
      </div>
    <?php elseif ($loginError === 'token'): ?>
      <div class="alert alert-danger">
        <strong>The form expired.</strong>
        <p class="small mb-0">Your session timed out before the form was sent. Please sign in again.</p>
      </div>
    <?php elseif ($loginError !== ''): ?>
      <div class="alert alert-danger">That email and password do not match an active account.</div>
    <?php elseif ($loginRequired): ?>
      <div class="alert alert-info">Please sign in to open that page.</div>
    <?php elseif ($loggedOut): ?>
      <div class="alert alert-success">You have been signed out.</div>
    <?php endif; ?>

    <?php if ($accounts === []): ?>
      <?php
      // No accounts has two very different causes and they need opposite
      // actions, so the page says which one it met rather than guessing.
      $fault = database_fault_hint();
      ?>
      <div class="alert alert-warning">
        <?php if ($fault !== ''): ?>
          <strong>The system cannot read its database.</strong>
          <p class="small mb-0"><?= e($fault) ?></p>
        <?php else: ?>
          <strong>No active accounts yet.</strong>
          <p class="small mb-0">The database is connected but has nothing in it. Import <code>database/install.sql</code>, or run <code>php scripts/migrate.php</code>.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= url('login') ?>" autocomplete="on">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="loginEmail">Email</label>
        <input class="form-control" id="loginEmail" name="email" type="email" value="<?= e((string) ($_GET['email'] ?? '')) ?>"
               autocomplete="username" autofocus required>
      </div>
      <div class="form-group">
        <label for="loginPassword">Password</label>
        <input class="form-control" id="loginPassword" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit"><i class="fe fe-log-in fe-16 mr-2"></i>Sign in</button>
    </form>

    <p class="mt-3 mb-0 small"><a href="<?= url('forgot-password') ?>">Forgotten your password?</a></p>

    <p class="lms-auth-credit">Powered by ITEC Ltd &middot; &copy; <?= date('Y') ?> ITEC Ltd. All rights reserved.</p>
  </section>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script>
  // Depot -> warehouse -> customer -> back to the depot, pausing at each door,
  // with the story caption changing as the truck reaches each step. Left as a
  // plain list when GSAP did not load or the visitor asked for less motion.
  (function () {
    if (!window.gsap || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    var truck = document.getElementById('lmsTruck');
    var body = document.getElementById('lmsTruckBody');
    var wheels = truck.querySelectorAll('.lms-scene-wheel');
    var story = document.getElementById('lmsStory');
    var steps = story.querySelectorAll('li');
    var dots = story.querySelectorAll('.lms-story-dots span');
    var stops = [22, 138, 254];
    var current = -1;

    story.classList.add('is-animated');
    gsap.set(steps, {autoAlpha: 0});
    gsap.set(truck, {x: stops[0], y: 100, transformOrigin: '50% 50%'});
    gsap.set(wheels, {transformOrigin: '50% 50%'});
    gsap.to(body, {y: -0.8, duration: 0.18, repeat: -1, yoyo: true, ease: 'sine.inOut'});

    function show(index) {
      if (index === current) { return; }
      if (current >= 0) { gsap.to(steps[current], {autoAlpha: 0, y: -8, duration: 0.3, ease: 'power1.in'}); }
      gsap.fromTo(steps[index], {autoAlpha: 0, y: 12}, {autoAlpha: 1, y: 0, duration: 0.5, delay: 0.15, ease: 'power2.out'});
      Array.prototype.forEach.call(dots, function (dot, n) { dot.classList.toggle('is-active', n === index); });
      current = index;
    }

    var tl = gsap.timeline({repeat: -1});
    var lastX = stops[0];

    function wait(seconds) { tl.to({}, {duration: seconds}); }

    function drive(to, facing) {
      var distance = Math.abs(to - lastX);
      var time = 0.8 + distance / 60;
      tl.to(truck, {scaleX: facing, duration: 0.25, ease: 'power1.inOut'})
        .to(truck, {x: to, duration: time, ease: 'power2.inOut'})
        .to(wheels, {rotation: (facing > 0 ? '+=' : '-=') + Math.round(distance * 6), duration: time, ease: 'power2.inOut'}, '<');
      lastX = to;
    }

    tl.call(show, [0]); wait(2.6);                         // 1 at the depot
    tl.call(show, [1]); drive(stops[1], 1); wait(0.4);      // 2 on the road
    tl.call(show, [2]); wait(2.6);                         // 3 at the warehouse
    tl.call(show, [3]); drive(stops[2], 1); wait(2.4);      // 4 on to the customer
    tl.call(show, [4]); drive(stops[0], -1); wait(1.2);     // 5 back to the depot
    tl.to(truck, {scaleX: 1, duration: 0.25, ease: 'power1.inOut'});
  })();
  </script>
</body>
</html>
