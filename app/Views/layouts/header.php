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
$searchIndex[] = ['label' => 'Approvals', 'url' => url('approvals'), 'group' => 'Overview', 'keywords' => 'approve sign inbox waiting'];
$searchIndex[] = ['label' => 'All updates', 'url' => url('notifications'), 'group' => 'Overview', 'keywords' => 'notifications alerts bell'];
if (can_view('tracking')) {
    $searchIndex[] = ['label' => 'Live tracking', 'url' => url('tracking'), 'group' => 'Transport', 'keywords' => 'gps map where truck position'];
}
foreach ([['companies', 'Companies', 'Administration'], ['departments', 'Departments', 'Administration'], ['users', 'Users', 'Administration'], ['permissions', 'Role permissions', 'Administration'], ['approval_policies', 'Approval rules', 'Administration'], ['lookups', 'Reference lists', 'Administration'], ['settings', 'Company settings', 'Administration'], ['audit', 'Audit trail', 'Administration'], ['email', 'Email outbox', 'Administration']] as [$route, $label, $group]) {
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
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">
  <meta name="description" content="LMS - Logistics Management System">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="lms-base" content="<?= e($baseUrl) ?>">
  <title><?= e(($title ?? 'Dashboard') . ' | LMS') ?></title>
  <link rel="icon" href="<?= $baseUrl ?>/assets/img/logistics-logo.svg">
  <?php /* The site can be put on a phone's home screen and opens like an app. */ ?>
  <link rel="manifest" href="<?= $baseUrl ?>/manifest.webmanifest">
  <meta name="theme-color" content="#1f4e8c">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="LMS">
  <link rel="apple-touch-icon" href="<?= $baseUrl ?>/assets/img/app-icon-192.png">
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
      <li class="nav-item" data-install-app hidden>
        <a class="nav-link logistics-top-control" href="#" role="button" title="Install on this phone" aria-label="Install on this phone"><i class="fe fe-smartphone fe-16"></i></a>
      </li>
      <li class="nav-item dropdown lms-notify">
        <a class="nav-link logistics-top-control lms-bell dropdown-toggle" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Updates" title="Updates">
          <i class="fe fe-bell fe-16"></i><?php if ($unreadNotifications > 0): ?><em class="lms-bell-badge"><?= $unreadNotifications > 9 ? '9+' : (int) $unreadNotifications ?></em><?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right lms-notify-menu" data-read-url="<?= e(url(['notifications', 'read'])) ?>" data-token="<?= e(csrf_token()) ?>">
          <div class="lms-notify-head">
            <strong>Updates</strong>
            <a class="lms-notify-all" href="<?= url('notifications') ?>">See all</a>
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
              $canOpen = $route !== '' && can_view($route === 'my-trips' ? 'dashboard' : $route);
              $severity = in_array($notification['severity'], ['danger', 'warning', 'success'], true) ? $notification['severity'] : 'primary';
              // Straight to the record when the update names one: by id, or by searching its code.
              $entityRef = trim((string) ($notification['entity_id'] ?? ''));
              $openUrl = '';
              if ($canOpen) {
                  $openUrl = match (true) {
                      $entityRef === '' || $route === 'my-trips' => url($route),
                      ctype_digit($entityRef) => url([$route, $entityRef]),
                      default => url($route, ['q' => $entityRef]),
                  };
              }
              $createdAt = (string) ($notification['created_at'] ?? '');
              ?>
              <div class="lms-notify-row">
                <a class="lms-notify-item<?= empty($notification['is_read']) ? ' is-unread' : '' ?>" href="<?= $openUrl !== '' ? e($openUrl) : '#' ?>"
                   data-key="<?= e($notification['notification_key']) ?>"
                   data-title="<?= e($notification['title']) ?>"
                   data-message="<?= e($notification['message']) ?>"
                   data-severity="<?= e($severity) ?>"
                   data-when="<?= $createdAt !== '' ? e(date('l j F Y, H:i', (int) strtotime($createdAt))) : '' ?>"
                   data-ago="<?= e(time_ago($notification['created_at'] ?? null)) ?>"
                   data-area="<?= e($route !== '' ? \Models\Schema::label(str_replace('-', '_', $route)) : '') ?>"
                   data-ref="<?= e($entityRef !== '' && !ctype_digit($entityRef) ? $entityRef : '') ?>"
                   data-open="<?= e($openUrl) ?>">
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
      <?php /* The company being worked in; the head of the business switches between them or looks at the whole group. */ ?>
      <?php if (can_see_all_companies() && count(\Models\Company::options()) > 1): ?>
      <li class="nav-item dropdown"><a class="nav-link logistics-top-control dropdown-toggle" href="#" data-toggle="dropdown" title="Company"><i class="fe fe-globe fe-16 mr-1"></i><span>Company</span><strong><?= e(current_company_name()) ?></strong></a><div class="dropdown-menu dropdown-menu-right"><h6 class="dropdown-header">Work in</h6><?php foreach (\Models\Company::options() as $companyId => $companyLabel): ?><a class="dropdown-item <?= !company_view_all() && current_company_id() === $companyId ? 'active' : '' ?>" href="<?= e(url(current_route(), ['switch_company' => $companyId])) ?>"><?= e($companyLabel) ?></a><?php endforeach; ?><div class="dropdown-divider"></div><a class="dropdown-item <?= company_view_all() ? 'active' : '' ?>" href="<?= e(url(current_route(), ['switch_company' => 'all'])) ?>">All companies</a></div></li>
      <?php elseif (is_logged_in()): ?>
      <li class="nav-item"><span class="nav-link logistics-top-control lms-role-static" title="Your company"><i class="fe fe-globe fe-16 mr-1"></i><span>Company</span><strong><?= e(current_company_name()) ?></strong></span></li>
      <?php endif; ?>
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
      <div class="w-100 mb-3 d-flex"><a class="navbar-brand lms-brand flex-fill" href="<?= url('dashboard') ?>"><img class="lms-brand-full" src="<?= asset('assets/img/lms-logo-white.png') ?>" alt="LMS — Logistics Management System"><img class="lms-brand-mark" src="<?= asset('assets/img/lms-logo-mark.png') ?>" alt="LMS"></a></div>
      <ul class="navbar-nav flex-fill w-100 mb-2">
        <li class="nav-item w-100"><a class="nav-link<?= nav_active('dashboard') ?>" href="<?= url('dashboard') ?>"><i class="fe fe-home fe-16"></i><span class="ml-3 item-text">Dashboard</span></a></li>
        <?php if (current_role() !== 'driver'): ?>
          <?php $myApprovals = \Models\Approvals::countFor(current_role()) + \Models\RequestForms::inboxCount() + \Models\ManualJournals::inboxCount(); ?>
          <li class="nav-item w-100"><a class="nav-link lms-nav-count<?= nav_active('approvals') ?>" href="<?= url('approvals') ?>" data-approvals-link><i class="fe fe-check-square fe-16"></i><span class="ml-3 item-text">Approvals</span><?php if ($myApprovals > 0): ?><em class="lms-approvals-badge"><?= $myApprovals > 9 ? '9+' : $myApprovals ?></em><?php endif; ?></a></li>
        <?php endif; ?>
        <?php if (can_view('tracking')): ?>
          <li class="nav-item w-100"><a class="nav-link<?= nav_active('tracking') ?>" href="<?= url('tracking') ?>"><i class="fe fe-map fe-16"></i><span class="ml-3 item-text">Live tracking</span></a></li>
        <?php endif; ?>
        <?php if (current_role() === 'driver' && current_driver_id() !== null): ?>
          <li class="nav-item w-100"><a class="nav-link<?= nav_active('my-trips') ?>" href="<?= url('my-trips') ?>"><i class="fe fe-truck fe-16"></i><span class="ml-3 item-text">My trips &amp; papers</span></a></li>
        <?php endif; ?>

        <?php foreach ($navigation as $group): ?>
          <li class="nav-item dropdown">
            <a href="#<?= e($group['id']) ?>" data-toggle="collapse" class="dropdown-toggle nav-link<?= nav_group($group['routes']) ? ' has-active' : '' ?>">
              <i class="fe fe-<?= e($group['icon']) ?> fe-16"></i><span class="ml-3 item-text"><?= e($group['label']) ?></span>
            </a>
            <ul class="collapse<?= nav_group($group['routes']) ? ' show' : '' ?> list-unstyled pl-4 w-100" id="<?= e($group['id']) ?>">
              <?php foreach ($group['items'] as [$route, $label]): ?>
                <li class="nav-item"><a class="nav-link pl-3<?= nav_active($route) ?>" href="<?= url($route) ?>" title="<?= e($label) ?>"><span class="ml-1 item-text"><?= e($label) ?></span></a></li>
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
                <li class="nav-item"><a class="nav-link pl-3<?= (current_route() === 'reports' && (($_GET['key'] ?? '') === $reportKey || str_ends_with($_SERVER['REQUEST_URI'] ?? '', '/' . $reportKey))) ? ' active' : '' ?>" href="<?= url(['reports', 'view', $reportKey]) ?>" title="<?= e($reportLabel) ?>"><span class="ml-1 item-text"><?= e($reportLabel) ?></span></a></li>
              <?php endforeach; ?>
              <?php if (can_view('report_emails')): ?>
                <li class="nav-item"><a class="nav-link pl-3<?= nav_active('report_emails', '') ?>" href="<?= url('report_emails') ?>"><span class="ml-1 item-text"><i class="fe fe-mail fe-12 mr-1"></i>Report emails</span></a></li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>

        <?php if (can_view('users') || can_view('companies') || can_view('document_types') || can_view('form_types') || can_view('approval_delegations') || can_view('lookups') || can_view('settings') || can_view('audit') || can_view('email') || can_view('approval_policies')): ?>
          <li class="nav-item dropdown">
            <a href="#adminMenu" data-toggle="collapse" class="dropdown-toggle nav-link<?= nav_group(['companies', 'departments', 'users', 'permissions', 'document_types', 'form_types', 'approval_step_settings', 'approval_delegations', 'gps_providers', 'whatsapp_outbox', 'lookups', 'settings', 'audit', 'email', 'approval_policies']) ? ' has-active' : '' ?>"><i class="fe fe-shield fe-16"></i><span class="ml-3 item-text">Administration</span></a>
            <ul class="collapse<?= nav_group(['companies', 'departments', 'users', 'permissions', 'document_types', 'form_types', 'approval_step_settings', 'approval_delegations', 'gps_providers', 'whatsapp_outbox', 'lookups', 'settings', 'audit', 'email', 'approval_policies']) ? ' show' : '' ?> list-unstyled pl-4 w-100" id="adminMenu">
              <?php if (can_view('companies')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('companies') ?>" href="<?= url('companies') ?>"><span class="ml-1 item-text">Companies</span></a></li><?php endif; ?>
              <?php if (can_view('departments')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('departments') ?>" href="<?= url('departments') ?>"><span class="ml-1 item-text">Departments</span></a></li><?php endif; ?>
              <?php if (can_view('users')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('users') ?>" href="<?= url('users') ?>"><span class="ml-1 item-text">Users</span></a></li><?php endif; ?>
              <?php if (can_view('users')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('permissions') ?>" href="<?= url('permissions') ?>"><span class="ml-1 item-text">Role permissions</span></a></li><?php endif; ?>
              <?php if (can_view('approval_policies')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('approval_policies') ?>" href="<?= url('approval_policies') ?>"><span class="ml-1 item-text">Approval rules</span></a></li><?php endif; ?>
              <?php if (can_view('lookups')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('lookups') ?>" href="<?= url('lookups') ?>"><span class="ml-1 item-text">Reference lists</span></a></li><?php endif; ?>
              <?php if (can_view('document_types')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('document_types') ?>" href="<?= url('document_types') ?>"><span class="ml-1 item-text">Document types</span></a></li><?php endif; ?>
              <?php if (can_view('form_types')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('form_types') ?>" href="<?= url('form_types') ?>"><span class="ml-1 item-text">Request forms</span></a></li><?php endif; ?>
              <?php if (can_view('approval_step_settings')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('approval_step_settings') ?>" href="<?= url('approval_step_settings') ?>"><span class="ml-1 item-text">Approvers per step</span></a></li><?php endif; ?>
              <?php if (can_view('approval_delegations')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('approval_delegations') ?>" href="<?= url('approval_delegations') ?>"><span class="ml-1 item-text">Delegations</span></a></li><?php endif; ?>
              <?php if (can_view('gps_providers')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('gps_providers') ?>" href="<?= url('gps_providers') ?>"><span class="ml-1 item-text">GPS providers</span></a></li><?php endif; ?>
              <?php if (can_view('settings')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('settings') ?>" href="<?= url('settings') ?>"><span class="ml-1 item-text">Company settings</span></a></li><?php endif; ?>
              <?php if (can_view('email')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('email') ?>" href="<?= url('email') ?>"><span class="ml-1 item-text">Email outbox</span></a></li><?php endif; ?>
              <?php if (can_view('whatsapp_outbox')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('whatsapp_outbox') ?>" href="<?= url('whatsapp_outbox') ?>"><span class="ml-1 item-text">WhatsApp outbox</span></a></li><?php endif; ?>
              <?php if (can_view('audit')): ?><li class="nav-item"><a class="nav-link pl-3<?= nav_active('audit') ?>" href="<?= url('audit') ?>"><span class="ml-1 item-text">Audit trail</span></a></li><?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav flex-fill w-100 mb-2"><li class="nav-item w-100"><a class="nav-link" href="<?= url('logout') ?>"><i class="fe fe-power fe-16"></i><span class="ml-3 item-text">Logout</span></a></li></ul>
    </nav>
  </aside>
  <main role="main" class="main-content">
    <?php
    // The sidebar group the current page belongs to, repeated across the top
    // of the page as plain links, so its sister pages are one click away
    // without opening the sidebar.
    $sectionLinks = [];
    $requestPath = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
    foreach ($navigation as $group) {
        if (nav_group($group['routes'])) {
            foreach ($group['items'] as [$route, $label]) {
                $sectionLinks[] = [url($route), $label, current_route() === $route];
            }
        }
    }
    if (current_route() === 'reports' && can_view('reports')) {
        $sectionLinks[] = [url('reports'), 'All reports', $requestPath === rtrim(url('reports'), '/')];
        foreach (\Models\ReportData::availableFor(current_role()) as $reportKey => $reportLabel) {
            $sectionLinks[] = [url(['reports', 'view', $reportKey]), $reportLabel, str_ends_with($requestPath, '/reports/view/' . $reportKey)];
        }
    }
    if (nav_group(['companies', 'departments', 'users', 'permissions', 'document_types', 'form_types', 'approval_step_settings', 'approval_delegations', 'gps_providers', 'whatsapp_outbox', 'lookups', 'settings', 'audit', 'email', 'approval_policies'])) {
        foreach ([['companies', 'Companies', 'companies'], ['departments', 'Departments', 'departments'], ['users', 'Users', 'users'], ['permissions', 'Role permissions', 'users'], ['document_types', 'Document types', 'document_types'], ['form_types', 'Request forms', 'form_types'], ['approval_step_settings', 'Approvers per step', 'approval_step_settings'], ['approval_delegations', 'Delegations', 'approval_delegations'], ['gps_providers', 'GPS providers', 'gps_providers'], ['approval_policies', 'Approval rules', 'approval_policies'], ['lookups', 'Reference lists', 'lookups'], ['settings', 'Company settings', 'settings'], ['email', 'Email outbox', 'email'], ['audit', 'Audit trail', 'audit']] as [$route, $label, $permission]) {
            if (can_view($permission)) {
                $sectionLinks[] = [url($route), $label, current_route() === $route];
            }
        }
    }
    ?>
    <?php if (count($sectionLinks) > 1): ?>
      <div class="container-fluid">
        <nav class="lms-section-nav" aria-label="Pages in this section">
          <?php foreach ($sectionLinks as [$href, $label, $isActive]): ?>
            <a href="<?= e($href) ?>"<?= $isActive ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
    <?php endif; ?>
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
