<?php
$baseUrl = config('app.base_url', '');
$notifications = current_notifications();
$unreadNotifications = unread_notification_count();
$navigation = navigation();
$flashes = flash_messages();

// Quick-jump index, limited to the pages this role may actually open.
$searchIndex = [];
foreach ($navigation as $group) {
    foreach ($group['items'] as [$route, $label]) {
        $searchIndex[] = ['label' => $label, 'url' => url($route), 'group' => $group['label'], 'keywords' => $route];
    }
}
$searchIndex[] = ['label' => 'Dashboard', 'url' => url('dashboard'), 'group' => 'Overview', 'keywords' => 'home kpi charts'];
if (can_view('reports')) {
    $searchIndex[] = ['label' => 'All reports', 'url' => url('reports'), 'group' => 'Reports', 'keywords' => 'report export csv'];
    foreach (\Models\ReportData::availableFor(current_role()) as $reportKey => $reportLabel) {
        $searchIndex[] = ['label' => $reportLabel, 'url' => url(['reports', 'view', $reportKey]), 'group' => 'Reports', 'keywords' => 'report'];
    }
}
foreach ([['users', 'Users', 'Administration'], ['permissions', 'Role permissions', 'Administration'], ['lookups', 'Reference lists', 'Administration'], ['settings', 'Company settings', 'Administration'], ['audit', 'Audit trail', 'Administration'], ['email', 'Email outbox', 'Administration']] as [$route, $label, $group]) {
    if (can_view($route === 'permissions' ? 'users' : $route)) {
        $searchIndex[] = ['label' => $label, 'url' => url($route), 'group' => $group, 'keywords' => $route];
    }
}
$searchIndex[] = ['label' => 'My profile', 'url' => url('account'), 'group' => 'Account', 'keywords' => 'profile password'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="LMS - Logistics Management System">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e(($title ?? 'Dashboard') . ' | LMS') ?></title>
  <link rel="icon" href="<?= $baseUrl ?>/assets/img/logistics-logo.svg">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/simplebar.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/feather.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/select2.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/daterangepicker.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app-light.css" id="lightTheme">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app-dark.css" id="darkTheme" disabled>
  <?php /* Our own stylesheets change often; the version is the file's own
           timestamp, so an edit reaches the browser without a hard refresh. */ ?>
  <link rel="stylesheet" href="<?= asset('assets/css/logistics.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-modules.css') ?>">
  <link rel="stylesheet" href="<?= asset('assets/css/logistics-background.css') ?>">
</head>
<body class="vertical">
<script>try { if (localStorage.getItem('mode') === 'dark') { document.body.classList.add('dark'); } } catch (e) {}</script>
<div class="wrapper">
  <nav class="topnav navbar navbar-light">
    <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar"><i class="fe fe-menu navbar-toggler-icon"></i></button>
    <form class="lms-search" role="search" autocomplete="off" onsubmit="return false" data-pages="<?= e(json_encode($searchIndex)) ?>">
      <i class="fe fe-search fe-16"></i>
      <input type="search" class="form-control" placeholder="Search pages... (press /)" aria-label="Search pages">
      <ul class="lms-search-results" hidden></ul>
    </form>
    <ul class="nav ml-auto">
      <li class="nav-item dropdown lms-notify">
        <a class="nav-link logistics-top-control lms-bell dropdown-toggle" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Updates" title="Updates">
          <i class="fe fe-bell fe-16"></i><?php if ($unreadNotifications > 0): ?><em class="lms-bell-badge"><?= $unreadNotifications > 9 ? '9+' : (int) $unreadNotifications ?></em><?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right lms-notify-menu">
          <div class="lms-notify-head">
            <strong>Updates</strong>
            <?php if ($unreadNotifications > 0): ?>
              <form method="post" action="<?= url(['notifications', 'read-all']) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= e(current_route()) ?>">
                <button type="submit" class="lms-notify-clear"><?= (int) $unreadNotifications ?> new &middot; mark all read</button>
              </form>
            <?php endif; ?>
          </div>
          <div class="lms-notify-list">
            <?php if ($notifications === []): ?>
              <div class="lms-notify-empty"><i class="fe fe-bell-off fe-24"></i><span>You're all caught up.</span></div>
            <?php endif; ?>
            <?php foreach ($notifications as $notification): ?>
              <?php
              $route = (string) ($notification['link_route'] ?? '');
              $canOpen = $route !== '' && can_view($route);
              $severity = in_array($notification['severity'], ['danger', 'warning', 'success'], true) ? $notification['severity'] : 'primary';
              ?>
              <div class="lms-notify-row">
                <a class="lms-notify-item<?= empty($notification['is_read']) ? ' is-unread' : '' ?>" href="<?= $canOpen ? e(url($route)) : '#' ?>">
                  <i class="lms-notify-dot lms-notify-dot-<?= $severity ?>"></i>
                  <span class="lms-notify-body">
                    <strong><?= e($notification['title']) ?></strong>
                    <small><?= e($notification['message']) ?></small>
                    <em><?= e(time_ago($notification['created_at'] ?? null)) ?></em>
                  </span>
                </a>
                <?php if (empty($notification['is_read'])): ?>
                  <form method="post" action="<?= url(['notifications', 'read']) ?>" class="lms-notify-tick">
                    <?= csrf_field() ?>
                    <input type="hidden" name="notification_key" value="<?= e($notification['notification_key']) ?>">
                    <input type="hidden" name="redirect" value="<?= e(current_route()) ?>">
                    <button type="submit" title="Mark as read" aria-label="Mark as read"><i class="fe fe-check"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </li>
      <?php if (can_switch_role()): ?>
      <li class="nav-item dropdown"><a class="nav-link logistics-top-control dropdown-toggle" href="#" data-toggle="dropdown"><i class="fe fe-users fe-16 mr-1"></i><span>Role</span><strong><?= e(role_label()) ?></strong></a><div class="dropdown-menu dropdown-menu-right"><h6 class="dropdown-header">Switch role</h6><?php foreach (role_definitions() as $roleKey => $roleDefinition): ?><a class="dropdown-item <?= current_role() === $roleKey ? 'active' : '' ?>" href="<?= url('dashboard', ['switch_role' => $roleKey]) ?>"><?= e($roleDefinition['label']) ?></a><?php endforeach; ?></div></li>
      <?php else: ?>
      <li class="nav-item"><span class="nav-link logistics-top-control lms-role-static" title="Your role"><i class="fe fe-users fe-16 mr-1"></i><span>Role</span><strong><?= e(role_label()) ?></strong></span></li>
      <?php endif; ?>
      <li class="nav-item"><a class="nav-link logistics-top-control logistics-theme-icon" href="#" id="modeSwitcher" data-mode="dark" aria-label="Switch light or dark theme" title="Switch theme"><i class="fe fe-sun fe-16"></i></a></li>
      <li class="nav-item dropdown">
        <?php
        // Everyone used to be shown the same stock photograph of a stranger.
        // Now it is their own photo, or their own initials when they have not
        // added one — which at least belongs to them.
        $myPhoto = \Models\Avatar::url($_SESSION['logistics_user_avatar'] ?? null);
        ?>
        <a class="nav-link dropdown-toggle text-muted pr-0" href="#" data-toggle="dropdown">
          <?php if ($myPhoto !== null): ?>
            <span class="avatar avatar-sm mt-2"><img src="<?= e($myPhoto) ?>" alt="<?= e(current_user_name()) ?>" class="avatar-img rounded-circle"></span>
          <?php else: ?>
            <span class="avatar avatar-sm mt-2 lms-initials" style="background: <?= e(\Models\Avatar::tint(current_user_name())) ?>" title="<?= e(current_user_name()) ?>"><?= e(\Models\Avatar::initials(current_user_name())) ?></span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <h6 class="dropdown-header"><?= e(current_user_name()) ?><small class="d-block text-muted"><?= e(current_user_email()) ?></small></h6>
          <a class="dropdown-item" href="<?= url('account') ?>"><i class="fe fe-user fe-16 mr-2"></i> My profile</a>
          <a class="dropdown-item" href="<?= url(['account', 'password']) ?>"><i class="fe fe-key fe-16 mr-2"></i> Change password</a>
          <?php if (can_view('settings')): ?><a class="dropdown-item" href="<?= url('settings') ?>"><i class="fe fe-settings fe-16 mr-2"></i> Company settings</a><?php endif; ?>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?= url('logout') ?>"><i class="fe fe-power fe-16 mr-2"></i> Logout</a>
        </div>
      </li>
    </ul>
  </nav>
  <aside class="sidebar-left border-right bg-white shadow" id="leftSidebar" data-simplebar>
    <nav class="vertnav navbar navbar-light">
      <div class="w-100 mb-4 d-flex"><a class="navbar-brand mx-auto mt-2 flex-fill text-center" href="<?= url('dashboard') ?>"><img src="<?= $baseUrl ?>/assets/img/logistics-logo.svg" width="86%" alt="LMS"></a></div>
      <ul class="navbar-nav flex-fill w-100 mb-2">
        <li class="nav-item w-100"><a class="nav-link<?= nav_active('dashboard') ?>" href="<?= url('dashboard') ?>"><i class="fe fe-home fe-16"></i><span class="ml-3 item-text">Dashboard</span></a></li>

        <?php foreach ($navigation as $group): ?>
          <li class="nav-item dropdown">
            <a href="#<?= e($group['id']) ?>" data-toggle="collapse" class="dropdown-toggle nav-link<?= nav_group($group['routes']) ? ' has-active' : '' ?>">
              <i class="fe fe-<?= e($group['icon']) ?> fe-16"></i><span class="ml-3 item-text"><?= e($group['label']) ?></span>
            </a>
            <ul class="collapse<?= nav_group($group['routes']) ? ' show' : '' ?> list-unstyled pl-4 w-100" id="<?= e($group['id']) ?>">
              <?php foreach ($group['items'] as [$route, $label]): ?>
                <li class="nav-item"><a class="nav-link pl-3<?= nav_active($route) ?>" href="<?= url($route) ?>"><span class="ml-1 item-text"><?= e($label) ?></span></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
        <?php endforeach; ?>

        <?php if (can_view('reports')): ?>
          <li class="nav-item dropdown">
            <a href="#reportsMenu" data-toggle="collapse" class="dropdown-toggle nav-link<?= nav_group(['reports']) ? ' has-active' : '' ?>"><i class="fe fe-bar-chart-2 fe-16"></i><span class="ml-3 item-text">Reports</span></a>
            <ul class="collapse<?= nav_group(['reports']) ? ' show' : '' ?> list-unstyled pl-4 w-100 logistics-report-menu" id="reportsMenu">
              <li class="nav-item"><a class="nav-link pl-3<?= nav_active('reports', '') ?>" href="<?= url('reports') ?>"><span class="ml-1 item-text">All reports</span></a></li>
              <?php foreach (\Models\ReportData::availableFor(current_role()) as $reportKey => $reportLabel): ?>
                <li class="nav-item"><a class="nav-link pl-3<?= (current_route() === 'reports' && (($_GET['key'] ?? '') === $reportKey || str_ends_with($_SERVER['REQUEST_URI'] ?? '', '/' . $reportKey))) ? ' active' : '' ?>" href="<?= url(['reports', 'view', $reportKey]) ?>"><span class="ml-1 item-text"><?= e($reportLabel) ?></span></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
        <?php endif; ?>

        <?php if (can_view('users') || can_view('lookups') || can_view('settings') || can_view('audit') || can_view('email')): ?>
          <li class="nav-item dropdown">
            <a href="#adminMenu" data-toggle="collapse" class="dropdown-toggle nav-link<?= nav_group(['users', 'permissions', 'lookups', 'settings', 'audit', 'email']) ? ' has-active' : '' ?>"><i class="fe fe-shield fe-16"></i><span class="ml-3 item-text">Administration</span></a>
            <ul class="collapse<?= nav_group(['users', 'permissions', 'lookups', 'settings', 'audit', 'email']) ? ' show' : '' ?> list-unstyled pl-4 w-100" id="adminMenu">
              <?php if (can_view('users')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('users') ?>" href="<?= url('users') ?>"><span class="ml-1 item-text">Users</span></a></li><?php endif; ?>
              <?php if (can_view('users')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('permissions') ?>" href="<?= url('permissions') ?>"><span class="ml-1 item-text">Role permissions</span></a></li><?php endif; ?>
              <?php if (can_view('lookups')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('lookups') ?>" href="<?= url('lookups') ?>"><span class="ml-1 item-text">Reference lists</span></a></li><?php endif; ?>
              <?php if (can_view('settings')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('settings') ?>" href="<?= url('settings') ?>"><span class="ml-1 item-text">Company settings</span></a></li><?php endif; ?>
              <?php if (can_view('email')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('email') ?>" href="<?= url('email') ?>"><span class="ml-1 item-text">Email outbox</span></a></li><?php endif; ?>
              <?php if (can_view('audit')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('audit') ?>" href="<?= url('audit') ?>"><span class="ml-1 item-text">Audit trail</span></a></li><?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav flex-fill w-100 mb-2"><li class="nav-item w-100"><a class="nav-link" href="<?= url('logout') ?>"><i class="fe fe-power fe-16"></i><span class="ml-3 item-text">Logout</span></a></li></ul>
    </nav>
  </aside>
  <main role="main" class="main-content">
    <?php if ($flashes !== []): ?>
      <div class="container-fluid pt-3">
        <?php foreach ($flashes as $flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show lms-flash" role="alert">
            <i class="fe fe-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'alert-octagon' : 'info') ?> mr-2"></i>
            <?= e($flash['message']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
