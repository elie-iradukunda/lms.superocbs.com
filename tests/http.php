<?php

declare(strict_types=1);

/**
 * End-to-end HTTP test.
 *
 * Boots PHP's built-in server against a throwaway database, signs in as a real
 * user, and walks every page: lists, create forms, record pages, edit forms,
 * reports and the API. It also checks the things only a real request can show:
 * a POST without a CSRF token is refused, a role cannot reach a module it has no
 * permission for, a driver cannot open another driver's record, and an upload
 * path cannot be walked out of the uploads folder.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_http');

// The test process itself needs the autoloader so it can read Schema and
// ReportData to work out which pages should exist.
require __DIR__ . '/../bootstrap.php';

$port = 8000 + random_int(400, 999);
$base = "http://127.0.0.1:{$port}";
$server = null;
$pipes = [];

/** One HTTP request through a shared cookie jar. */
function http_call(string $base, string $path, array $options = []): array
{
    static $jar = null;
    $jar ??= tempnam(sys_get_temp_dir(), 'lmsjar');

    $handle = curl_init($base . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        // PHP's built-in server handles one connection at a time, so a kept-alive
        // socket from the previous request stalls the next one. Close each time.
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_HTTPHEADER => ['Connection: close'],
    ]);

    if (isset($options['post'])) {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($options['post']));
    }

    $body = (string) curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $location = (string) curl_getinfo($handle, CURLINFO_REDIRECT_URL);
    $seconds = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME);
    curl_close($handle);

    // A running commentary, so a run that stalls says where it stalled.
    fwrite(STDOUT, sprintf("  %-4s %6.2fs %s%s\n", $status ?: 'ERR', $seconds, $path, $error === '' ? '' : "  [{$error}]"));
    flush();

    return ['status' => $status, 'body' => $body, 'location' => $location];
}

/** Pulls the CSRF token out of a rendered page. */
function csrf_from(string $html): string
{
    return preg_match('/name="_token" value="([a-f0-9]+)"/', $html, $m) === 1 ? $m[1] : '';
}

try {
    // The server's output goes to a file, never to a pipe: an unread pipe fills
    // its buffer and the server blocks forever on its next log line.
    $logFile = tempnam(sys_get_temp_dir(), 'lmssrv');
    $descriptors = [1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']];
    $environment = array_merge(getenv(), [
        'LOGISTICS_DB_HOST' => '127.0.0.1',
        'LOGISTICS_DB_NAME' => $dbName,
        'LOGISTICS_DB_USER' => 'root',
        'LOGISTICS_DB_PASS' => '',
    ]);

    $server = proc_open(
        sprintf('%s -S 127.0.0.1:%d -t public public/index.php', escapeshellarg(PHP_BINARY), $port),
        $descriptors,
        $pipes,
        dirname(__DIR__),
        $environment
    );

    if (!is_resource($server)) {
        fwrite(STDERR, "FAIL: the test web server could not be started.\n");
        exit(1);
    }

    // Wait for the server to answer rather than sleeping a fixed amount.
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        usleep(200000);
        if (http_call($base, '/api/health')['status'] === 200) {
            $ready = true;
            break;
        }
    }

    if (!$ready) {
        fwrite(STDERR, "FAIL: the test web server never answered on port {$port}.\n");
        exit(1);
    }

    $test = new TestRun('HTTP tests');

    /** Fetches a page and fails on a bad status or a PHP notice leaking into the HTML. */
    $page = static function (string $path, string $label = '') use ($base, $test): string {
        $response = http_call($base, $path);
        $label = $label === '' ? $path : $label;
        $test->same(200, $response['status'], "GET {$label} returns 200");
        $test->assert(
            preg_match('/Fatal error|Parse error|Uncaught|Undefined (variable|array key|property)|SQLSTATE/i', $response['body']) !== 1,
            "GET {$label} renders without a PHP error"
        );
        return $response['body'];
    };

    // -------------------------------------------------------------- sign in
    $home = $page('/', 'the home page');
    $token = csrf_from($home);
    $test->assert($token !== '', 'the login form carries a CSRF token');

    $badToken = http_call($base, '/login', ['post' => ['_token' => 'wrong', 'email' => 'admin@itec.rw', 'password' => 'password']]);
    $test->assert(str_contains($badToken['location'], 'login_error=token'), 'signing in with a bad CSRF token is refused');

    $badPassword = http_call($base, '/login', ['post' => ['_token' => csrf_from(http_call($base, '/')['body']), 'email' => 'admin@itec.rw', 'password' => 'wrong-password']]);
    $test->assert(str_contains($badPassword['location'], 'login_error=1'), 'signing in with a wrong password is refused');

    $login = http_call($base, '/login', ['post' => ['_token' => csrf_from(http_call($base, '/')['body']), 'email' => 'admin@itec.rw', 'password' => 'password']]);
    $test->assert(str_contains($login['location'], '/dashboard'), 'signing in with the right password reaches the dashboard');

    // ---------------------------------------------------------- every page
    foreach (['/dashboard', '/account', '/account/password', '/reports', '/settings', '/permissions', '/audit'] as $path) {
        $page($path);
    }

    // One module per area by default, which keeps a run to a few seconds. Pass
    // `--all` to walk all nineteen, which is what a release check should do.
    $walkAll = in_array('--all', $argv, true);
    $sample = ['vehicles', 'trips', 'shipments', 'deliveries', 'customers', 'invoices', 'warehouse', 'movements', 'users'];
    $modules = array_filter(
        array_keys(Models\Schema::all()),
        static fn (string $key): bool => $key !== 'reports' && ($walkAll || in_array($key, $sample, true))
    );

    foreach ($modules as $moduleKey) {

        $list = $page("/{$moduleKey}", "the {$moduleKey} list");
        $test->assert(str_contains($list, 'lms-table'), "the {$moduleKey} list renders its table");
        $page("/{$moduleKey}/create", "the {$moduleKey} create form");

        $module = Models\Schema::get($moduleKey);
        // The stock ledger is not soft-deleted, so it has no deleted_at column.
        $alive = empty($module['soft_delete']) ? '1 = 1' : 'deleted_at IS NULL';
        $id = (int) $pdo->query("SELECT id FROM `{$module['table']}` WHERE {$alive} ORDER BY id LIMIT 1")->fetchColumn();
        if ($id > 0) {
            $detail = $page("/{$moduleKey}/{$id}", "the {$moduleKey} record page");
            $test->assert(str_contains($detail, 'lms-facts'), "the {$moduleKey} record page shows its fields");
            $page("/{$moduleKey}/{$id}/edit", "the {$moduleKey} edit form");
        }

        $export = http_call($base, "/{$moduleKey}/export/csv");
        $test->same(200, $export['status'], "the {$moduleKey} CSV export responds");
    }

    $page('/reports/catalogue', 'the saved report catalogue');
    $reportKeys = $walkAll ? Models\ReportData::keys() : ['delivery_performance', 'trip_profitability'];
    foreach ($reportKeys as $reportKey) {
        $report = $page("/reports/view/{$reportKey}", "the {$reportKey} report");
        $test->assert(str_contains($report, 'Export CSV'), "the {$reportKey} report offers a CSV export");
        $test->same(200, http_call($base, "/reports/export/{$reportKey}")['status'], "the {$reportKey} report exports");
    }

    // ----------------------------------------------------------------- CSRF
    $vehicleId = (int) $pdo->query('SELECT id FROM vehicles ORDER BY id LIMIT 1')->fetchColumn();
    $plateBefore = (string) $pdo->query("SELECT plate_number FROM vehicles WHERE id = {$vehicleId}")->fetchColumn();

    http_call($base, "/vehicles/{$vehicleId}", ['post' => ['f' => ['plate_number' => 'HACKED', 'vehicle_type' => 'Pickup', 'status' => 'available']]]);
    $test->same($plateBefore, (string) $pdo->query("SELECT plate_number FROM vehicles WHERE id = {$vehicleId}")->fetchColumn(), 'a POST with no CSRF token changes nothing');

    http_call($base, "/vehicles/{$vehicleId}/delete", ['post' => ['reason' => 'Trying it on']]);
    $test->same(null, $pdo->query("SELECT deleted_at FROM vehicles WHERE id = {$vehicleId}")->fetchColumn(), 'a delete with no CSRF token does not remove the record');

    // ------------------------------------------------------- real write path
    $createForm = http_call($base, '/vehicles/create')['body'];
    $createToken = csrf_from($createForm);
    $created = http_call($base, '/vehicles', ['post' => [
        '_token' => $createToken,
        'f' => ['plate_number' => 'RHT 777H', 'vehicle_type' => 'Van', 'status' => 'available', 'mileage' => '10', 'fuel_type' => 'diesel', 'ownership' => 'owned'],
    ]]);
    $test->assert($created['status'] === 302, 'creating a vehicle redirects to its record');
    $test->same(1, (int) $pdo->query("SELECT COUNT(*) FROM vehicles WHERE plate_number = 'RHT 777H'")->fetchColumn(), 'the vehicle was created through the form');

    $invalid = http_call($base, '/vehicles', ['post' => ['_token' => $createToken, 'f' => ['plate_number' => '', 'vehicle_type' => '', 'status' => '']]]);
    $test->same(200, $invalid['status'], 'an invalid create returns the form rather than redirecting');
    $test->assert(str_contains($invalid['body'], 'need'), 'an invalid create explains what is missing');

    $deleteNoReason = http_call($base, "/vehicles/{$vehicleId}/delete", ['post' => ['_token' => $createToken, 'reason' => '']]);
    $test->same(null, $pdo->query("SELECT deleted_at FROM vehicles WHERE id = {$vehicleId}")->fetchColumn(), 'a delete without a reason is refused');

    // ----------------------------------------------------- file downloads
    $test->same(404, http_call($base, '/files/deliveries/..%2F..%2Fconfig%2Fconfig.php')['status'], 'a path that walks out of the uploads folder is refused');
    $test->same(404, http_call($base, '/files/deliveries/nothing-here.pdf')['status'], 'a file that does not exist returns 404');
    $test->same(404, http_call($base, '/files/not_a_module/x.pdf')['status'], 'a file under an unknown module is refused');

    // -------------------------------------------------- permission and scope
    $logout = http_call($base, '/logout');
    $test->assert(str_contains($logout['location'], 'logged_out=1'), 'signing out returns to the home page');
    $test->assert(str_contains(http_call($base, '/vehicles')['location'], 'login_required'), 'a signed-out visitor is sent to sign in');

    $driverLogin = http_call($base, '/login', ['post' => ['_token' => csrf_from(http_call($base, '/')['body']), 'email' => 'samuel@itec.rw', 'password' => 'password']]);
    $test->assert(str_contains($driverLogin['location'], '/dashboard'), 'the driver can sign in');

    $denied = http_call($base, '/users');
    $test->assert(str_contains($denied['location'], 'denied=users'), 'a driver asking for users is sent back with an explanation');
    $test->assert(str_contains(http_call($base, '/invoices')['location'], 'denied=invoices'), 'a driver asking for invoices is refused');

    $samuelId = (int) $pdo->query("SELECT d.id FROM drivers d INNER JOIN users u ON u.id = d.user_id WHERE u.email = 'samuel@itec.rw'")->fetchColumn();
    $otherTripId = (int) $pdo->query("SELECT id FROM trips WHERE driver_id IS NOT NULL AND driver_id <> {$samuelId} AND deleted_at IS NULL LIMIT 1")->fetchColumn();
    $ownTripId = (int) $pdo->query("SELECT id FROM trips WHERE driver_id = {$samuelId} AND deleted_at IS NULL LIMIT 1")->fetchColumn();

    $test->same(302, http_call($base, "/trips/{$otherTripId}")['status'], 'a driver opening another driver trip is redirected away');
    $test->same(200, http_call($base, "/trips/{$ownTripId}")['status'], 'a driver can open their own trip');

    $driverApi = json_decode(http_call($base, '/api/my/trips')['body'], true);
    $test->assert(is_array($driverApi) && isset($driverApi['trips']), 'the driver API returns their trips');
    foreach ($driverApi['trips'] ?? [] as $apiTrip) {
        $owner = (int) $pdo->query("SELECT driver_id FROM trips WHERE id = {$apiTrip['id']}")->fetchColumn();
        $test->same($samuelId, $owner, 'the driver API only returns that driver trips');
    }

    $test->same(403, http_call($base, '/api/modules/users')['status'], 'the API refuses a module the role may not see');

    $test->finish();
} finally {
    if (is_resource($server)) {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            // proc_open runs the command through cmd.exe, so the handle points at
            // the shell rather than at php.exe. Kill the shell's whole tree, then
            // whatever is still holding the port, or proc_close blocks forever
            // waiting for a server that nobody stopped.
            $status = proc_get_status($server);
            if ($status['running'] ?? false) {
                exec('taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>NUL');
            }

            $lines = [];
            exec('netstat -ano -p TCP', $lines);
            foreach ($lines as $line) {
                if (preg_match('/127\.0\.0\.1:' . $port . '\s+\S+\s+LISTENING\s+(\d+)/', $line, $match) === 1) {
                    exec('taskkill /F /PID ' . (int) $match[1] . ' 2>NUL');
                }
            }
        } else {
            proc_terminate($server, 9);
        }

        proc_close($server);
    }

    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
