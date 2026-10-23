<?php

/** @var Core\Router $router */

use Controllers\AccountController;
use Controllers\ApprovalController;
use Controllers\AuditController;
use Controllers\BankController;
use Controllers\BrandController;
use Controllers\BooksController;
use Controllers\DashboardController;
use Controllers\DriverController;
use Controllers\FileController;
use Controllers\HomeController;
use Controllers\ImportController;
use Controllers\OperationsController;
use Controllers\JournalController;
use Controllers\LogisticsController;
use Controllers\MailController;
use Controllers\NotificationController;
use Controllers\PermissionController;
use Controllers\PortalController;
use Controllers\ReportController;
use Controllers\SettingsController;
use Controllers\TrackingController;

// ------------------------------------------------------------- public pages
$router->get('/', [HomeController::class, 'index'], ['public' => true]);
$router->add(['GET', 'POST'], '/login', [HomeController::class, 'login'], ['public' => true, 'csrf' => false]);
$router->add(['GET', 'POST'], '/login/verify', [HomeController::class, 'verify'], ['public' => true, 'csrf' => false]);
$router->get('/logout', [HomeController::class, 'logout'], ['public' => true]);
$router->add(['GET', 'POST'], '/forgot-password', [HomeController::class, 'forgotPassword'], ['public' => true, 'csrf' => false]);
$router->add(['GET', 'POST'], '/reset-password/{token}', [HomeController::class, 'resetPassword'], ['public' => true, 'csrf' => false]);
$router->get('/brand/logo', [BrandController::class, 'logo'], ['public' => true]);

// The customer's side: ask for transport, and follow a shipment.
$router->add(['GET', 'POST'], '/request-transport', [PortalController::class, 'request'], ['public' => true]);
$router->add(['GET', 'POST'], '/track', [PortalController::class, 'track'], ['public' => true]);
$router->post('/track/upload', [PortalController::class, 'upload'], ['public' => true]);

// ------------------------------------------------------------------ account
$router->get('/dashboard', [DashboardController::class, 'index']);
// The driver's app (section 16): five buttons.
$router->get('/my-trips', [\Controllers\DriverAppController::class, 'home'], ['permission' => 'dashboard']);
$router->get('/my-trips/ping', [\Controllers\DriverAppController::class, 'ping'], ['permission' => 'dashboard']);
$router->get('/my-trips/trip', [\Controllers\DriverAppController::class, 'trip'], ['permission' => 'dashboard']);
$router->post('/my-trips/trip/{id}/act/{action}', [\Controllers\DriverAppController::class, 'act'], ['permission' => 'dashboard']);
$router->post('/my-trips/money/{line}/confirm', [\Controllers\DriverAppController::class, 'confirmMoney'], ['permission' => 'dashboard']);
$router->get('/my-trips/delivery', [\Controllers\DriverAppController::class, 'delivery'], ['permission' => 'dashboard']);
$router->post('/my-trips/delivery', [\Controllers\DriverAppController::class, 'storeDelivery'], ['permission' => 'dashboard']);
$router->get('/my-trips/support', [\Controllers\DriverAppController::class, 'support'], ['permission' => 'dashboard']);
$router->post('/my-trips/support', [\Controllers\DriverAppController::class, 'storeSupport'], ['permission' => 'dashboard']);
$router->get('/my-trips/handover', [\Controllers\DriverAppController::class, 'handover'], ['permission' => 'dashboard']);
$router->post('/my-trips/handover', [\Controllers\DriverAppController::class, 'storeHandover'], ['permission' => 'dashboard']);
$router->get('/my-trips/profile', [\Controllers\DriverAppController::class, 'profile'], ['permission' => 'dashboard']);
$router->post('/my-trips/profile/momo', [\Controllers\DriverAppController::class, 'changeMomo'], ['permission' => 'dashboard']);
$router->post('/my-trips/profile/pin', [\Controllers\DriverAppController::class, 'setPin'], ['permission' => 'dashboard']);
$router->post('/my-trips/shortage/{id}/explain', [\Controllers\DriverAppController::class, 'explainShortage'], ['permission' => 'dashboard']);
$router->post('/my-trips/{id}/position', [DriverController::class, 'position'], ['permission' => 'dashboard']);

// ---------------------------------------------------------------- approvals
$router->get('/approvals', [ApprovalController::class, 'index'], ['permission' => 'dashboard']);

// --------------------------------------------------------------- tracking
$router->get('/tracking', [TrackingController::class, 'index'], ['permission' => 'tracking']);
$router->get('/tracking/positions', [TrackingController::class, 'positions'], ['permission' => 'tracking']);
$router->get('/trips/{id}/trail', [TrackingController::class, 'trail'], ['permission' => 'trips']);
$router->get('/account', [AccountController::class, 'profile'], ['permission' => 'dashboard']);
$router->post('/account', [AccountController::class, 'updateProfile'], ['permission' => 'dashboard']);
$router->post('/account/signature', [AccountController::class, 'saveSignature'], ['permission' => 'dashboard']);
$router->get('/account/password', [AccountController::class, 'password'], ['permission' => 'dashboard']);
$router->post('/account/password', [AccountController::class, 'updatePassword'], ['permission' => 'dashboard']);

// ------------------------------------------------------------ notifications
$router->get('/notifications', [NotificationController::class, 'index'], ['permission' => 'dashboard']);
$router->get('/notifications/feed', [NotificationController::class, 'feed'], ['permission' => 'dashboard']);
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
$router->get('/journal/drafts/{id}', [JournalController::class, 'draft'], ['permission' => 'journal']);
$router->post('/journal/drafts/{id}/{action}', [JournalController::class, 'act'], ['permission' => 'journal']);
$router->get('/journal/{id}', [JournalController::class, 'show'], ['permission' => 'journal']);
$router->post('/journal/{id}/reverse', [JournalController::class, 'reverse'], ['permission' => 'journal', 'ability' => 'approve']);

// ------------------------------------------------------- administration
$router->get('/settings', [SettingsController::class, 'index'], ['permission' => 'settings']);
$router->post('/settings', [SettingsController::class, 'update'], ['permission' => 'settings', 'ability' => 'edit']);
$router->get('/permissions', [PermissionController::class, 'index'], ['permission' => 'users']);
$router->post('/permissions', [PermissionController::class, 'update'], ['permission' => 'users', 'ability' => 'edit']);
$router->post('/permissions/roles/{id}/rename', [PermissionController::class, 'renameRole'], ['permission' => 'users', 'ability' => 'edit']);
$router->get('/email', [MailController::class, 'index'], ['permission' => 'email']);
$router->post('/email/test', [MailController::class, 'test'], ['permission' => 'email', 'ability' => 'edit']);
$router->post('/email/flush', [MailController::class, 'flush'], ['permission' => 'email', 'ability' => 'edit']);
$router->get('/email/{id}', [MailController::class, 'show'], ['permission' => 'email']);
$router->get('/email/{id}/preview', [MailController::class, 'preview'], ['permission' => 'email']);
$router->post('/email/{id}/retry', [MailController::class, 'retry'], ['permission' => 'email', 'ability' => 'edit']);
$router->get('/audit', [AuditController::class, 'index'], ['permission' => 'audit']);
$router->get('/audit/export', [AuditController::class, 'export'], ['permission' => 'audit']);

// ------------------------------------------------- documents from a job
// Registered before the generic module routes so "/deliveries/5/note" is never
// read as anything else.
$router->get('/deliveries/{id}/note', [OperationsController::class, 'deliveryNote'], ['permission' => 'deliveries']);
$router->post('/trips/{id}/invoice', [OperationsController::class, 'invoiceTrip'], ['permission' => 'invoices', 'ability' => 'create']);
$router->get('/pre_dispatch_checks/readiness', [OperationsController::class, 'readiness'], ['permission' => 'pre_dispatch_checks']);
$router->get('/trips/{id}/report', [OperationsController::class, 'tripReport'], ['permission' => 'trips']);
$router->get('/trips/{id}/report/{format}', [OperationsController::class, 'tripReport'], ['permission' => 'trips']);
$router->get('/crossings/{id}/agent-email', [OperationsController::class, 'composeAgent'], ['permission' => 'crossings', 'ability' => 'edit']);
$router->post('/crossings/{id}/agent-email', [OperationsController::class, 'sendAgent'], ['permission' => 'crossings', 'ability' => 'edit']);
$router->get('/invoices/{id}/print', [OperationsController::class, 'invoicePrint'], ['permission' => 'invoices']);
$router->post('/invoices/{id}/disbursements', [OperationsController::class, 'invoiceDisbursements'], ['permission' => 'invoices', 'ability' => 'edit']);
$router->post('/trips/{id}/handover', [OperationsController::class, 'handOver'], ['permission' => 'trips', 'ability' => 'edit']);
$router->post('/journeys/{id}/invoice', [OperationsController::class, 'invoiceJourney'], ['permission' => 'invoices', 'ability' => 'create']);
$router->post('/containers/{id}/recharge', [OperationsController::class, 'rechargeContainer'], ['permission' => 'disbursements', 'ability' => 'create']);
$router->get('/bank_statements/{id}/reconcile', [BankController::class, 'reconcile'], ['permission' => 'bank_statements']);
$router->post('/bank_statements/{id}/reconcile/{op}', [BankController::class, 'act'], ['permission' => 'bank_statements', 'ability' => 'edit']);
$router->get('/price_offers/{id}/email', [OperationsController::class, 'composeOffer'], ['permission' => 'price_offers', 'ability' => 'approve']);
$router->post('/price_offers/{id}/email', [OperationsController::class, 'sendOffer'], ['permission' => 'price_offers', 'ability' => 'approve']);
$router->get('/price_offers/{id}/lpo', [OperationsController::class, 'lpoForm'], ['permission' => 'price_offers']);
$router->get('/price_offers/{id}/lpo-email', [OperationsController::class, 'composeLpo'], ['permission' => 'price_offers', 'ability' => 'approve']);
$router->post('/price_offers/{id}/lpo-email', [OperationsController::class, 'sendLpo'], ['permission' => 'price_offers', 'ability' => 'approve']);
$router->post('/report_emails/{id}/send', [OperationsController::class, 'sendReport'], ['permission' => 'report_emails', 'ability' => 'edit']);

// ------------------------------------------------- requests F1-F15 (section 8)
$router->get('/form_requests', [\Controllers\RequestController::class, 'index'], ['permission' => 'form_requests']);
$router->get('/form_requests/create', [\Controllers\RequestController::class, 'create'], ['permission' => 'form_requests', 'ability' => 'create']);
$router->post('/form_requests', [\Controllers\RequestController::class, 'store'], ['permission' => 'form_requests', 'ability' => 'create']);
$router->get('/form_requests/{id}', [\Controllers\RequestController::class, 'show'], ['permission' => 'form_requests']);
$router->get('/form_requests/{id}/edit', [\Controllers\RequestController::class, 'edit'], ['permission' => 'form_requests', 'ability' => 'create']);
$router->get('/form_requests/{id}/pdf', [\Controllers\RequestController::class, 'pdf'], ['permission' => 'form_requests']);
$router->post('/form_requests/{id}', [\Controllers\RequestController::class, 'update'], ['permission' => 'form_requests', 'ability' => 'create']);
// Who may sign is decided by the approver settings, not by a page permission.
$router->post('/form_requests/{id}/act/{action}', [\Controllers\RequestController::class, 'act'], ['permission' => 'form_requests']);

// ----------------------------------------------------------- trips (section 7)
$router->get('/trip_planner', [\Controllers\TripPlannerController::class, 'planner'], ['permission' => 'trip_planner', 'ability' => 'create']);
$router->post('/trip_planner', [\Controllers\TripPlannerController::class, 'store'], ['permission' => 'trip_planner', 'ability' => 'create']);
$router->post('/trip_planner/check', [\Controllers\TripPlannerController::class, 'check'], ['permission' => 'trip_planner', 'ability' => 'create']);
// "Create trip" on the list opens the planner; the trip page is the trip's own.
$router->get('/trips/create', [\Controllers\TripPlannerController::class, 'planner'], ['permission' => 'trip_planner', 'ability' => 'create']);
$router->get('/trips/{id}', [\Controllers\TripPlannerController::class, 'show'], ['permission' => 'trips']);
$router->get('/trips/{id}/sheet', [\Controllers\TripPlannerController::class, 'sheet'], ['permission' => 'trips']);
$router->post('/trips/{id}/act/{action}', [\Controllers\TripPlannerController::class, 'act'], ['permission' => 'trips', 'ability' => 'edit']);
$router->post('/trips/{id}/report', [\Controllers\TripPlannerController::class, 'report'], ['permission' => 'trips', 'ability' => 'edit']);
$router->post('/trips/{id}/replace', [\Controllers\TripPlannerController::class, 'replace'], ['permission' => 'trips', 'ability' => 'approve']);
$router->post('/trips/{id}/cancel', [\Controllers\TripPlannerController::class, 'cancel'], ['permission' => 'trips', 'ability' => 'approve']);

$router->get('/fuel_orders/{id}/confirm', [\Controllers\FuelOrderController::class, 'confirmForm'], ['permission' => 'fuel_orders', 'ability' => 'edit']);
$router->post('/fuel_orders/{id}/confirm', [\Controllers\FuelOrderController::class, 'confirm'], ['permission' => 'fuel_orders', 'ability' => 'edit']);

$router->get('/support_requests/{id}/decide', [\Controllers\SupportController::class, 'form'], ['permission' => 'support_requests']);
$router->post('/support_requests/{id}/decide', [\Controllers\SupportController::class, 'decide'], ['permission' => 'support_requests']);

// --------------------------------------------- opening balances (section 14)
$router->get('/opening_balances', [\Controllers\OpeningController::class, 'index'], ['permission' => 'opening_balances']);
$router->get('/opening_balances/template/{kind}', [\Controllers\OpeningController::class, 'template'], ['permission' => 'opening_balances']);
$router->post('/opening_balances', [\Controllers\OpeningController::class, 'store'], ['permission' => 'opening_balances', 'ability' => 'create']);
$router->post('/opening_balances/{id}/{action}', [\Controllers\OpeningController::class, 'act'], ['permission' => 'opening_balances']);

// ----------------------------------------------------- payments (section 10)
$router->get('/payment_batches', [\Controllers\PaymentController::class, 'index'], ['permission' => 'payment_batches']);
$router->post('/payment_batches/build', [\Controllers\PaymentController::class, 'build'], ['permission' => 'payment_batches', 'ability' => 'create']);
$router->get('/payment_batches/{id}', [\Controllers\PaymentController::class, 'show'], ['permission' => 'payment_batches']);
$router->get('/payment_batches/{id}/mtn', [\Controllers\PaymentController::class, 'mtnFile'], ['permission' => 'payment_batches', 'ability' => 'create']);
$router->post('/payment_batches/{id}/record', [\Controllers\PaymentController::class, 'record'], ['permission' => 'payment_batches', 'ability' => 'edit']);
$router->post('/payment_batches/{id}/lines/{line}/cash', [\Controllers\PaymentController::class, 'payCash'], ['permission' => 'payment_batches', 'ability' => 'edit']);
$router->get('/payment_batches/{id}/lines/{line}/receipt', [\Controllers\PaymentController::class, 'receipt'], ['permission' => 'payment_batches']);

// ------------------------------------------- garage and store (section 12)
$router->get('/garage', [\Controllers\GarageController::class, 'board'], ['permission' => 'garage']);
$router->get('/garage/{id}', [\Controllers\GarageController::class, 'show'], ['permission' => 'garage']);
$router->post('/garage/{id}/{action}', [\Controllers\GarageController::class, 'act'], ['permission' => 'garage', 'ability' => 'edit']);
$router->get('/store_issues', [\Controllers\GarageController::class, 'issues'], ['permission' => 'store_issues']);
$router->get('/store_issues/{id}', [\Controllers\GarageController::class, 'issue'], ['permission' => 'store_issues']);
$router->post('/store_issues/{id}/{action}', [\Controllers\GarageController::class, 'issueAct'], ['permission' => 'store_issues', 'ability' => 'edit']);
$router->get('/goods_receipts/{id}', [\Controllers\GarageController::class, 'receiveForm'], ['permission' => 'goods_receipts']);
$router->post('/goods_receipts/{id}', [\Controllers\GarageController::class, 'receive'], ['permission' => 'goods_receipts', 'ability' => 'create']);
$router->get('/stock_counts', [\Controllers\GarageController::class, 'counts'], ['permission' => 'stock_counts']);
$router->post('/stock_counts', [\Controllers\GarageController::class, 'startCount'], ['permission' => 'stock_counts', 'ability' => 'create']);
$router->get('/stock_counts/{id}', [\Controllers\GarageController::class, 'count'], ['permission' => 'stock_counts']);
$router->post('/stock_counts/{id}/{action}', [\Controllers\GarageController::class, 'countAct'], ['permission' => 'stock_counts']);
$router->get('/service_logs/{kind}', [\Controllers\GarageController::class, 'logsForm'], ['permission' => 'form_requests', 'ability' => 'create']);
$router->post('/service_logs/{kind}', [\Controllers\GarageController::class, 'logsBuild'], ['permission' => 'form_requests', 'ability' => 'create']);

// ------------------------------- invoicing and payroll (sections 6 and 13)
$router->get('/billing', [\Controllers\BillingController::class, 'index'], ['permission' => 'billing']);
$router->post('/billing', [\Controllers\BillingController::class, 'build'], ['permission' => 'billing', 'ability' => 'create']);
$router->get('/payroll', [\Controllers\BillingController::class, 'payroll'], ['permission' => 'payroll']);
$router->get('/payroll/export', [\Controllers\BillingController::class, 'payrollExport'], ['permission' => 'payroll']);
$router->post('/payroll/close', [\Controllers\BillingController::class, 'payrollClose'], ['permission' => 'payroll', 'ability' => 'edit']);
$router->get('/intercompany', [\Controllers\BillingController::class, 'intercompany'], ['permission' => 'intercompany']);

// ------------------------------- customer and supplier portal (13, 18)
$router->get('/portal', [\Controllers\AccountPortalController::class, 'index'], ['permission' => 'portal']);
$router->get('/portal/documents/{id}/pdf', [\Controllers\AccountPortalController::class, 'pdf'], ['permission' => 'portal']);
$router->get('/portal/documents/{id}/photo', [\Controllers\AccountPortalController::class, 'photo'], ['permission' => 'portal']);
$router->post('/portal/orders/{id}/invoice', [\Controllers\AccountPortalController::class, 'invoice'], ['permission' => 'portal']);
$router->post('/portal/orders/{id}/{answer}', [\Controllers\AccountPortalController::class, 'answer'], ['permission' => 'portal']);

// ------------------------------------------------------- data imports
$router->get('/accounts/import', [ImportController::class, 'accountsForm'], ['permission' => 'accounts', 'ability' => 'create']);
$router->get('/accounts/import/template', [ImportController::class, 'accountsTemplate'], ['permission' => 'accounts', 'ability' => 'create']);
$router->post('/accounts/import', [ImportController::class, 'accountsImport'], ['permission' => 'accounts', 'ability' => 'create']);

// --------------------------------------------------------- generic modules
// Every module below is driven by Models\Schema: one controller, one set of
// routes, and a permission check per ability.
$modules = [
    'vehicles', 'trailers', 'drivers', 'driver_documents', 'vehicle_documents', 'document_types', 'driver_momo_changes', 'gps_providers', 'maintenance',
    'requests', 'journeys', 'trips', 'shipments', 'deliveries', 'crossings', 'border_posts',
    'shipment_documents', 'pre_dispatch_checks', 'support_requests',
    'clearance_documents', 'clearance_inspections', 'clearance_payments', 'clearance_releases',
    'customers', 'rates', 'price_offers', 'invoices', 'payments',
    'fuel', 'expenses', 'supplier_bills', 'supplier_payments', 'disbursements', 'bank_statements',
    'warehouses', 'warehouse', 'movements', 'procurement', 'suppliers', 'containers',
    'routes', 'route_rates', 'service_rates', 'fuel_orders', 'vehicle_handovers', 'tyre_logs', 'crane_logs', 'shortage_rules', 'form_types', 'approval_step_settings', 'approval_delegations', 'ledger_mappings', 'whatsapp_outbox', 'currency_rates',
    'companies', 'departments', 'users', 'accounts', 'payment_methods', 'cheques', 'cheque_books', 'budgets', 'currencies', 'lookups',
    'report_emails', 'approval_policies', 'freight_jobs', 'cargo_claims',
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
