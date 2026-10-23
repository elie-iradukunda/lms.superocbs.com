<?php

declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';

// A command-line script that has already printed something cannot start a
// session, and does not need one: $_SESSION is a plain array there, which is all
// the helpers below read.
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

// From here on an uncaught error produces a plain page rather than printing the
// file paths, the database user and the stack trace at whoever is looking. The
// console is left alone: a script wants its error in full.
\Core\ErrorHandler::register();

/**
 * Display names for the roles, read from `roles` so that a role renamed on the
 * permissions page is called by its new name everywhere. The list below is only
 * what a database that cannot be read falls back to. What each role may *do*
 * lives in `role_permissions`.
 */
$roleDefinitions = [
    'super_admin' => ['label' => 'Admin'],
    'management' => ['label' => 'Managing Director'],
    'logistics_manager' => ['label' => 'Operational Manager'],
    'finance' => ['label' => 'Finance Manager'],
    'cashier' => ['label' => 'Cashier'],
    'warehouse_manager' => ['label' => 'Store & garage keeper'],
    'driver' => ['label' => 'Driver'],
    'clearance_officer' => ['label' => 'Declaration Manager'],
    'supplier' => ['label' => 'Supplier'],
    'customer' => ['label' => 'Customer'],
];
try {
    $roleRows = \Core\Database::connection()->query('SELECT role_key, role_name FROM roles ORDER BY id')->fetchAll();
    if ($roleRows !== []) {
        $roleDefinitions = [];
        foreach ($roleRows as $roleRow) {
            $roleDefinitions[(string) $roleRow['role_key']] = ['label' => (string) $roleRow['role_name']];
        }
    }
    unset($roleRows, $roleRow);
} catch (\Throwable) {
    // No database yet (a fresh install, MySQL stopped): keep the names above.
}

// ------------------------------------------------------------------- session

function is_logged_in(): bool
{
    return (bool) ($_SESSION['logistics_authenticated'] ?? false);
}

function current_role(): string
{
    return $_SESSION['logistics_role'] ?? 'logistics_manager';
}

function role_label(?string $roleKey = null): string
{
    global $roleDefinitions;
    return $roleDefinitions[$roleKey ?? current_role()]['label'] ?? 'Logistics Manager';
}

function role_definitions(): array
{
    global $roleDefinitions;
    return $roleDefinitions;
}

function current_user_id(): ?int
{
    return isset($_SESSION['logistics_user_id']) ? (int) $_SESSION['logistics_user_id'] : null;
}

function current_user_name(): string
{
    return $_SESSION['logistics_user_name'] ?? role_label();
}

function current_user_email(): string
{
    return $_SESSION['logistics_user_email'] ?? '';
}

/** users.prvg: 1 = privileged (may switch into any role), 2 = standard (default). */
function current_prvg(): int
{
    return (int) ($_SESSION['logistics_prvg'] ?? 2) === 1 ? 1 : 2;
}

function can_switch_role(): bool
{
    return is_logged_in() && current_prvg() === 1;
}

function must_change_password(): bool
{
    return (bool) ($_SESSION['logistics_must_change_password'] ?? false);
}

/** The driver profile behind the signed-in login, used to scope a driver's own rows. */
function current_driver_id(): ?int
{
    if (!is_logged_in() || current_user_id() === null) {
        return null;
    }

    if (array_key_exists('logistics_driver_id', $_SESSION)) {
        return $_SESSION['logistics_driver_id'] === null ? null : (int) $_SESSION['logistics_driver_id'];
    }

    try {
        $statement = \Core\Database::connection()->prepare('SELECT id FROM drivers WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
        $statement->execute([current_user_id()]);
        $id = $statement->fetchColumn();
        $_SESSION['logistics_driver_id'] = $id === false ? null : (int) $id;
    } catch (\Throwable) {
        $_SESSION['logistics_driver_id'] = null;
    }

    return $_SESSION['logistics_driver_id'];
}

/** Everything a query needs to know about who is asking. */
function current_context(): array
{
    return ['role' => current_role(), 'user_id' => current_user_id(), 'driver_id' => current_driver_id(), 'companies' => company_scope()];
}

// ----------------------------------------------------------------- companies

/** May this person look at every company of the group (the head of the business, Admin)? */
function can_see_all_companies(): bool
{
    return is_logged_in() && role_can('all_companies');
}

/** The company the person belongs to. */
function home_company_id(): int
{
    $id = (int) ($_SESSION['logistics_home_company_id'] ?? 0);

    return $id > 0 ? $id : \Models\Company::defaultId();
}

/** Is the person looking at the whole group rather than one company? */
function company_view_all(): bool
{
    return can_see_all_companies() && (int) ($_SESSION['logistics_company_id'] ?? -1) === 0;
}

/**
 * The company the person is working in: where a new record goes, whose header
 * is printed. Looking at the whole group, new records still go into their own.
 */
function current_company_id(): int
{
    $chosen = (int) ($_SESSION['logistics_company_id'] ?? 0);
    if ($chosen > 0 && (can_see_all_companies() || $chosen === home_company_id())) {
        return $chosen;
    }

    return home_company_id();
}

/** @return list<int>|null the companies a list may show, or null for all of them */
function company_scope(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return company_view_all() ? null : [current_company_id()];
}

function current_company_name(): string
{
    return company_view_all() ? 'All companies' : \Models\Company::name(current_company_id());
}

// --------------------------------------------------------------- permissions

function role_can(string $permission, string $ability = 'view'): bool
{
    return \Models\Permission::allows(current_role(), $permission, $ability);
}

function can_view(string $permission): bool
{
    return role_can($permission, 'view');
}

function can_create(string $permission): bool
{
    return role_can($permission, 'create');
}

function can_edit(string $permission): bool
{
    return role_can($permission, 'edit');
}

function can_delete(string $permission): bool
{
    return role_can($permission, 'delete');
}

function can_approve(string $permission): bool
{
    return role_can($permission, 'approve');
}

// -------------------------------------------------------------------- notify

function current_notifications(int $limit = 8): array
{
    if (!is_logged_in()) {
        return [];
    }

    try {
        return (new \Models\Notification())->forCurrentUser(current_user_id(), current_role(), $limit);
    } catch (\Throwable) {
        return [];
    }
}

function unread_notification_count(): int
{
    if (!is_logged_in()) {
        return 0;
    }

    try {
        return (new \Models\Notification())->unreadCount(current_user_id(), current_role());
    } catch (\Throwable) {
        return 0;
    }
}

// ---------------------------------------------------------------------- CSRF

function csrf_token(): string
{
    return \Core\Csrf::token();
}

function csrf_field(): string
{
    return \Core\Csrf::field();
}

// --------------------------------------------------------------------- flash

function flash_messages(): array
{
    return \Core\Flash::take();
}

// ----------------------------------------------------------------- utilities

if (PHP_SAPI === 'cli-server') {
    $config['app']['base_url'] = '';
}

function config(string $key, mixed $default = null): mixed
{
    global $config;
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function url(string|array $path = '', array $query = []): string
{
    $segments = array_map('rawurlencode', array_map('strval', array_filter((array) $path, static fn ($part): bool => $part !== '' && $part !== null)));
    $url = rtrim((string) config('app.base_url', ''), '/') . '/' . implode('/', $segments);

    return $query === [] ? $url : $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

/**
 * A URL for one of our own assets, stamped with the file's last-changed time.
 *
 * Browsers cache a stylesheet by its URL, so an edit to logistics-modules.css
 * would sit unseen behind the copy already held. The timestamp changes with the
 * file, which makes it a new URL, which makes the browser fetch it.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = __DIR__ . '/public/' . $path;
    $url = rtrim((string) config('app.base_url', ''), '/') . '/' . $path;

    return is_file($file) ? $url . '?v=' . filemtime($file) : $url;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(float|string|null $amount, bool $decimals = false): string
{
    return $amount === null || $amount === '' ? '' : \Models\Settings::money((float) $amount, $decimals);
}

function company_name(): string
{
    try {
        return \Models\Settings::get('company_name', 'LMS Logistics');
    } catch (\Throwable) {
        return 'LMS Logistics';
    }
}

/**
 * Who built the system, as opposed to who is using it.
 *
 * LMS is installed for more than one company, so the footer credits the vendor
 * rather than repeating the customer's own name back at them. Fixed in code on
 * purpose, not a company_settings row: this is not the installer's to change.
 */
function vendor_name(): string
{
    return 'ITEC LTD';
}

/** One way to write a date in letters and emails: "7 Oct 2026", or "7 Oct 2026, 09:09" with the time. */
function human_date(?string $value, bool $withTime = false): string
{
    $stamp = $value !== null && trim($value) !== '' ? strtotime($value) : false;
    if ($stamp === false) {
        return '';
    }

    return date($withTime ? 'j M Y, H:i' : 'j M Y', $stamp);
}

function time_ago(?string $datetime): string
{
    $time = $datetime ? strtotime($datetime) : false;
    if ($time === false) {
        return '';
    }

    $seconds = max(0, time() - $time);

    return match (true) {
        $seconds < 60 => 'Just now',
        $seconds < 3600 => intdiv($seconds, 60) . ' min ago',
        $seconds < 86400 => intdiv($seconds, 3600) . ' h ago',
        $seconds < 604800 => intdiv($seconds, 86400) . ' d ago',
        default => date('d M Y', $time),
    };
}

/** Days until a date: negative when it has already passed. */
function days_until(?string $date): ?int
{
    $time = $date ? strtotime($date) : false;
    if ($time === false) {
        return null;
    }

    return (int) floor(($time - strtotime(date('Y-m-d'))) / 86400);
}

/** First URL segment of the current request without the app base path. */
function current_route(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim((string) config('app.base_url', ''), '/');
    if ($base !== '' && strncasecmp($path, $base, strlen($base)) === 0) {
        $path = substr($path, strlen($base));
    }

    return explode('/', trim($path, '/'))[0] ?? '';
}

function nav_active(string $route, ?string $reportType = null): string
{
    if (current_route() !== $route) {
        return '';
    }
    if ($route === 'reports' && $reportType !== null && (string) ($_GET['report'] ?? '') !== $reportType) {
        return '';
    }

    return ' active';
}

function nav_group(array $routes): bool
{
    return in_array(current_route(), $routes, true);
}

/** The sidebar, built from the permissions the signed-in role actually holds. */
function navigation(): array
{
    $groups = [
        ['label' => 'Fleet management', 'icon' => 'truck', 'id' => 'fleetMenu', 'items' => [
            ['vehicles', 'Vehicles'], ['trailers', 'Trailers'], ['vehicle_documents', 'Truck documents'], ['drivers', 'Drivers'], ['driver_documents', 'Driver documents'], ['driver_momo_changes', 'MoMo changes'], ['garage', 'Garage'], ['maintenance', 'Job cards'], ['tyre_logs', 'Tyre log'], ['payroll', 'Payroll list'],
        ]],
        ['label' => 'Trips', 'icon' => 'navigation', 'id' => 'tripsMenu', 'items' => [
            ['trip_planner', 'New trip'], ['trips', 'Trips'], ['fuel_orders', 'Fuel orders'], ['vehicle_handovers', 'Handovers'], ['support_requests', 'Driver support'], ['crane_logs', 'Crane log'], ['routes', 'Routes'], ['route_rates', 'Route rate card'], ['service_rates', 'Crane and tyre rates'],
        ]],
        ['label' => 'Requests and payments', 'icon' => 'file-text', 'id' => 'requestsMenu', 'items' => [
            ['form_requests', 'Requests (F1-F15)'], ['payment_batches', 'Payments'],
        ]],
        ['label' => 'Transport', 'icon' => 'map-pin', 'id' => 'transportMenu', 'items' => [
            // Short labels: the sidebar is narrow, and a name that wraps onto two
            // lines is hard to scan. The page itself still carries the full title.
            ['requests', 'Transport requests'], ['freight_jobs', 'Freight jobs'], ['journeys', 'Journeys'], ['shipments', 'Shipments'], ['shipment_documents', 'Shipment papers'], ['pre_dispatch_checks', 'Dispatch checks'], ['deliveries', 'Deliveries'], ['crossings', 'Customs clearance'], ['clearance_documents', 'Customs documents'], ['clearance_inspections', 'Inspections'], ['clearance_payments', 'Customs payments'], ['clearance_releases', 'Customs releases'], ['containers', 'Containers'], ['border_posts', 'Border posts'],
        ]],
        ['label' => 'Commercial', 'icon' => 'briefcase', 'id' => 'commercialMenu', 'items' => [
            ['customers', 'Customers'], ['billing', 'Invoice trips (F13)'], ['rates', 'Rate cards'], ['shortage_rules', 'Shortage tolerance'], ['price_offers', 'Price offers'], ['invoices', 'Invoices'], ['payments', 'Payments received'], ['disbursements', 'Disbursements'], ['cargo_claims', 'Cargo claims'],
        ]],
        ['label' => 'Finance', 'icon' => 'credit-card', 'id' => 'financeMenu', 'items' => [
            ['fuel', 'Fuel management'], ['expenses', 'Trip expenses'], ['supplier_bills', 'Supplier bills'], ['supplier_payments', 'Supplier payments'],
        ]],
        ['label' => 'Warehouse', 'icon' => 'package', 'id' => 'warehouseMenu', 'items' => [
            ['store_issues', 'Issue notes'], ['warehouses', 'Stores'], ['warehouse', 'Items and stock'], ['movements', 'Stock movements'], ['stock_counts', 'Stock counts'], ['procurement', 'Procurement'], ['suppliers', 'Suppliers'],
        ]],
        ['label' => 'Accounting', 'icon' => 'book', 'id' => 'accountingMenu', 'items' => [
            ['books', 'Accounting books'], ['journal', 'Journal'], ['accounts', 'Chart of accounts'], ['ledger_mappings', 'Ledger mapping'], ['opening_balances', 'Opening balances'], ['intercompany', 'Inter-company'], ['cheques', 'Cheques'], ['cheque_books', 'Cheque books'], ['bank_statements', 'Bank reconciliation'], ['budgets', 'Budget'], ['currencies', 'Currencies'], ['currency_rates', 'Exchange rates'], ['payment_methods', 'Payment methods'],
        ]],
    ];

    $visible = [];
    foreach ($groups as $group) {
        $items = array_values(array_filter($group['items'], static fn (array $item): bool => can_view($item[0])));
        if ($items !== []) {
            $group['items'] = $items;
            $group['routes'] = array_column($items, 0);
            $visible[] = $group;
        }
    }

    return $visible;
}

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/app/Views/' . $template . '.php';
}

// Role switching stays available to privileged accounts only.
//
// The parameter is `switch_role`, not `role`: an administrator opening the role
// permission matrix for Finance passes `?role=finance` meaning "show me that
// role", and was being switched into it instead — which cost them the very
// permission the page needs, and bounced them to their dashboard.
if (isset($_GET['switch_role'], $roleDefinitions[$_GET['switch_role']]) && can_switch_role()) {
    $_SESSION['logistics_role'] = $_GET['switch_role'];
    try {
        $account = (new \Models\UserRepository())->activeLoginAccounts()[$_GET['switch_role']] ?? null;
        if ($account !== null) {
            $_SESSION['logistics_user_id'] = $account['id'];
            $_SESSION['logistics_user_name'] = $account['name'];
            $_SESSION['logistics_user_email'] = $account['email'];
            unset($_SESSION['logistics_driver_id']);
        }
    } catch (\Throwable) {
        // Keep the role switch even if the account lookup fails.
    }
    \Models\Permission::flush();
}

// Someone allowed to see the whole group switches company from the top bar;
// `all` shows every company at once.
if (isset($_GET['switch_company']) && is_logged_in() && PHP_SAPI !== 'cli') {
    $wanted = (string) $_GET['switch_company'];
    if ($wanted === 'all' && can_see_all_companies()) {
        $_SESSION['logistics_company_id'] = 0;
    } elseif (ctype_digit($wanted) && \Models\Company::find((int) $wanted) !== null
        && (can_see_all_companies() || (int) $wanted === home_company_id())) {
        $_SESSION['logistics_company_id'] = (int) $wanted;
    }
}

// An idle session ends on its own (Administration > Settings, Security).
if (is_logged_in() && PHP_SAPI !== 'cli') {
    $idleLimit = max(5, \Models\Settings::int('session_timeout_minutes', 60)) * 60;
    $lastSeen = (int) ($_SESSION['logistics_last_activity'] ?? time());
    if (time() - $lastSeen > $idleLimit) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: ' . url('', ['timed_out' => 1]) . '#login');
        exit;
    }
    $_SESSION['logistics_last_activity'] = time();
}

/**
 * The database problem the sign-in page ran into, if it ran into one.
 *
 * Set while looking for accounts, read when the page renders. Passing it in
 * would mean threading an error through a helper whose job is to return a list.
 */
function database_fault(?string $message = null): ?string
{
    static $fault = null;

    if ($message !== null) {
        $fault = $message;
    }

    return $fault;
}

/** Which of the two faults it is, in words somebody can act on. */
function database_fault_hint(): string
{
    $fault = (string) database_fault();

    return match (true) {
        $fault === '' => '',
        str_contains($fault, 'Unknown database')
            => 'The database named in .env does not exist on this server. Create it, then import database/install.sql into it.',
        str_contains($fault, 'Access denied')
            => 'The database refused the user name or password in .env. On cPanel both carry your account prefix, as in account_lms and account_lmsuser.',
        str_contains($fault, "doesn't exist") || str_contains($fault, 'Base table')
            => 'The database is there but empty. Import database/install.sql into it.',
        str_contains($fault, 'Connection refused') || str_contains($fault, 'No such host') || str_contains($fault, 'server has gone away')
            => 'The database server could not be reached. Check LOGISTICS_DB_HOST in .env; on most shared hosting it is localhost.',
        default => 'The database could not be opened. The server error log has the reason in full.',
    };
}

function demo_accounts(): array
{
    static $accounts = null;

    if (is_array($accounts)) {
        return $accounts;
    }

    try {
        $accounts = array_intersect_key((new \Models\UserRepository())->activeLoginAccounts(), role_definitions());
    } catch (\Throwable $exception) {
        // Why there are no accounts matters, and the two reasons need opposite
        // actions. An empty table means import the data; a database that cannot
        // be opened means fix .env, and telling that person to run a migration
        // sends them a long way in the wrong direction.
        database_fault($exception->getMessage());
        $accounts = [];
    }

    return $accounts;
}
