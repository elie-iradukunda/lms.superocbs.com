<?php

/** @var Core\Router $router */

use Controllers\AccountController;
use Controllers\AuditController;
use Controllers\BooksController;
use Controllers\DashboardController;
use Controllers\FileController;
use Controllers\HomeController;
use Controllers\JournalController;
use Controllers\LogisticsController;
use Controllers\MailController;
use Controllers\NotificationController;
use Controllers\PermissionController;
use Controllers\ReportController;
use Controllers\SettingsController;

// ------------------------------------------------------------- public pages
$router->get('/', [HomeController::class, 'index'], ['public' => true]);
$router->add(['GET', 'POST'], '/login', [HomeController::class, 'login'], ['public' => true, 'csrf' => false]);
$router->get('/logout', [HomeController::class, 'logout'], ['public' => true]);
$router->add(['GET', 'POST'], '/forgot-password', [HomeController::class, 'forgotPassword'], ['public' => true, 'csrf' => false]);
$router->add(['GET', 'POST'], '/reset-password/{token}', [HomeController::class, 'resetPassword'], ['public' => true, 'csrf' => false]);

// ------------------------------------------------------------------ account
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/account', [AccountController::class, 'profile'], ['permission' => 'dashboard']);
$router->post('/account', [AccountController::class, 'updateProfile'], ['permission' => 'dashboard']);
$router->get('/account/password', [AccountController::class, 'password'], ['permission' => 'dashboard']);
$router->post('/account/password', [AccountController::class, 'updatePassword'], ['permission' => 'dashboard']);

// ------------------------------------------------------------ notifications
$router->post('/notifications/read', [NotificationController::class, 'read'], ['permission' => 'dashboard']);
$router->post('/notifications/read-all', [NotificationController::class, 'readAll'], ['permission' => 'dashboard']);

// --------------------------------------------------- private file downloads
$router->get('/files/{module}/{name}', [FileController::class, 'show'], ['permission' => 'dashboard']);

// A profile photograph: anyone signed in may see a colleague's face.
$router->get('/avatar/{name}', [FileController::class, 'avatar'], ['permission' => 'dashboard']);

// ------------------------------------------------------------------ reports
// Live reports first, so "/reports/view" is never read as a catalogue id.
$router->get('/reports', [ReportController::class, 'index'], ['permission' => 'reports']);
$router->get('/reports/view/{key}', [ReportController::class, 'show'], ['permission' => 'reports']);
$router->get('/reports/export/{key}', [ReportController::class, 'export'], ['permission' => 'reports']);
foreach ([['', 'index', 'view'], ['/create', 'create', 'create']] as [$suffix, $action, $ability]) {
    $router->get("/reports/catalogue{$suffix}", [LogisticsController::class, $action], ['permission' => 'reports', 'ability' => $ability, 'defaults' => ['module' => 'reports']]);
}
$router->post('/reports/catalogue', [LogisticsController::class, 'store'], ['permission' => 'reports', 'ability' => 'create', 'defaults' => ['module' => 'reports']]);
$router->get('/reports/catalogue/{id}', [LogisticsController::class, 'details'], ['permission' => 'reports', 'defaults' => ['module' => 'reports']]);
$router->get('/reports/catalogue/{id}/edit', [LogisticsController::class, 'edit'], ['permission' => 'reports', 'ability' => 'edit', 'defaults' => ['module' => 'reports']]);
$router->post('/reports/catalogue/{id}', [LogisticsController::class, 'update'], ['permission' => 'reports', 'ability' => 'edit', 'defaults' => ['module' => 'reports']]);
$router->post('/reports/catalogue/{id}/delete', [LogisticsController::class, 'delete'], ['permission' => 'reports', 'ability' => 'delete', 'defaults' => ['module' => 'reports']]);

// --------------------------------------------------------- accounting
// The books come before the generic module routes so "/books/view" is never
// read as a record id.
$router->get('/books', [BooksController::class, 'index'], ['permission' => 'books']);
$router->get('/books/view/{key}', [BooksController::class, 'show'], ['permission' => 'books']);
$router->get('/books/export/{key}/{format}', [BooksController::class, 'export'], ['permission' => 'books']);
$router->post('/books/sync', [BooksController::class, 'sync'], ['permission' => 'journal', 'ability' => 'edit']);

$router->get('/journal', [JournalController::class, 'index'], ['permission' => 'journal']);
$router->get('/journal/create', [JournalController::class, 'create'], ['permission' => 'journal', 'ability' => 'create']);
$router->post('/journal', [JournalController::class, 'store'], ['permission' => 'journal', 'ability' => 'create']);
$router->get('/journal/{id}', [JournalController::class, 'show'], ['permission' => 'journal']);
$router->post('/journal/{id}/reverse', [JournalController::class, 'reverse'], ['permission' => 'journal', 'ability' => 'approve']);

// ------------------------------------------------------- administration
$router->get('/settings', [SettingsController::class, 'index'], ['permission' => 'settings']);
$router->post('/settings', [SettingsController::class, 'update'], ['permission' => 'settings', 'ability' => 'edit']);
$router->get('/permissions', [PermissionController::class, 'index'], ['permission' => 'users']);
$router->post('/permissions', [PermissionController::class, 'update'], ['permission' => 'users', 'ability' => 'edit']);
$router->get('/email', [MailController::class, 'index'], ['permission' => 'email']);
$router->post('/email/test', [MailController::class, 'test'], ['permission' => 'email', 'ability' => 'edit']);
$router->post('/email/flush', [MailController::class, 'flush'], ['permission' => 'email', 'ability' => 'edit']);
$router->get('/email/{id}', [MailController::class, 'show'], ['permission' => 'email']);
$router->get('/email/{id}/preview', [MailController::class, 'preview'], ['permission' => 'email']);
$router->post('/email/{id}/retry', [MailController::class, 'retry'], ['permission' => 'email', 'ability' => 'edit']);
$router->get('/audit', [AuditController::class, 'index'], ['permission' => 'audit']);
$router->get('/audit/export', [AuditController::class, 'export'], ['permission' => 'audit']);

// --------------------------------------------------------- generic modules
// Every module below is driven by Models\Schema: one controller, one set of
// routes, and a permission check per ability.
$modules = [
    'vehicles', 'drivers', 'vehicle_documents', 'maintenance',
    'requests', 'trips', 'shipments', 'deliveries', 'crossings', 'border_posts',
    'customers', 'rates', 'invoices', 'payments',
    'fuel', 'expenses',
    'warehouses', 'warehouse', 'movements', 'procurement', 'suppliers',
    'users', 'accounts', 'payment_methods', 'cheques', 'cheque_books', 'budgets', 'currencies', 'lookups',
];

foreach ($modules as $module) {
    $base = ['defaults' => ['module' => $module], 'permission' => $module];

    $router->get("/{$module}", [LogisticsController::class, 'index'], $base);
    $router->get("/{$module}/create", [LogisticsController::class, 'create'], $base + ['ability' => 'create']);
    $router->post("/{$module}", [LogisticsController::class, 'store'], $base + ['ability' => 'create']);
    $router->get("/{$module}/{id}", [LogisticsController::class, 'details'], $base);
    $router->get("/{$module}/{id}/edit", [LogisticsController::class, 'edit'], $base + ['ability' => 'edit']);
    $router->post("/{$module}/{id}", [LogisticsController::class, 'update'], $base + ['ability' => 'edit']);
    $router->post("/{$module}/{id}/lines", [LogisticsController::class, 'lines'], $base + ['ability' => 'edit']);
    $router->post("/{$module}/{id}/toggle", [LogisticsController::class, 'toggle'], $base + ['ability' => 'edit']);
    $router->post("/{$module}/{id}/delete", [LogisticsController::class, 'delete'], $base + ['ability' => 'delete']);
    $router->post("/{$module}/{id}/action/{action}", [LogisticsController::class, 'action'], $base + ['ability' => 'approve']);
    $router->get("/{$module}/export/csv", [LogisticsController::class, 'export'], $base);
}
