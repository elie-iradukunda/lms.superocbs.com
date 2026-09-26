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

/** Display names for the roles. What each role may *do* now lives in `role_permissions`. */
$roleDefinitions = [
    'super_admin' => ['label' => 'Super Admin'],
    'logistics_manager' => ['label' => 'Logistics Manager'],
    'fleet_manager' => ['label' => 'Fleet Manager'],
    'warehouse_manager' => ['label' => 'Warehouse Manager'],
    'driver' => ['label' => 'Driver'],
    'finance' => ['label' => 'Finance'],
    'management' => ['label' => 'Management'],
];

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
    return ['role' => current_role(), 'user_id' => current_user_id(), 'driver_id' => current_driver_id()];
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
            ['vehicles', 'Vehicles'], ['drivers', 'Drivers'], ['maintenance', 'Maintenance'], ['vehicle_documents', 'Vehicle documents'],
        ]],
        ['label' => 'Transport', 'icon' => 'map-pin', 'id' => 'transportMenu', 'items' => [
            ['requests', 'Transport requests'], ['trips', 'Trips'], ['shipments', 'Shipments'], ['shipment_documents', 'Shipment documents'], ['pre_dispatch_checks', 'Pre-dispatch checks'], ['deliveries', 'Deliveries'], ['crossings', 'Customs clearance'], ['clearance_documents', 'Clearance documents'], ['clearance_inspections', 'Inspections'], ['clearance_payments', 'Clearance payments'], ['clearance_releases', 'Releases'], ['border_posts', 'Border posts'],
        ]],
        ['label' => 'Commercial', 'icon' => 'briefcase', 'id' => 'commercialMenu', 'items' => [
            ['customers', 'Customers'], ['rates', 'Rate cards'], ['invoices', 'Invoices'], ['payments', 'Payments received'],
        ]],
        ['label' => 'Finance', 'icon' => 'credit-card', 'id' => 'financeMenu', 'items' => [
            ['fuel', 'Fuel management'], ['expenses', 'Logistics expenses'],
        ]],
        ['label' => 'Warehouse', 'icon' => 'package', 'id' => 'warehouseMenu', 'items' => [
            ['warehouses', 'Warehouses'], ['warehouse', 'Inventory'], ['movements', 'Stock movements'], ['procurement', 'Procurement'], ['suppliers', 'Suppliers'],
        ]],
        ['label' => 'Accounting', 'icon' => 'book', 'id' => 'accountingMenu', 'items' => [
            ['books', 'Accounting books'], ['journal', 'Journal'], ['accounts', 'Chart of accounts'], ['cheques', 'Cheques'], ['cheque_books', 'Cheque books'], ['budgets', 'Budget'], ['currencies', 'Currencies'], ['currency_rates', 'Exchange rates'], ['payment_methods', 'Payment methods'],
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
