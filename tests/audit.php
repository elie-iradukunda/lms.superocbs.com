<?php

declare(strict_types=1);

/**
 * Does every part of this system actually work?
 *
 * The other suites each know one subject well. This one is the sweep before a
 * deploy: it walks every module in the registry, every report in the catalogue,
 * every accounting book and every message the system can send, and checks that
 * each one can be opened, filtered, exported and read without falling over.
 *
 * It is deliberately broad rather than deep. A page that throws, a column that
 * names a field nobody defined, a report whose SQL no longer matches its table,
 * a permission a route needs but no role was ever granted: those are the faults
 * that survive every narrow test and are found by a user on the first day.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('logistics_mvc_audit');
require __DIR__ . '/../bootstrap.php';

$test = new TestRun('Whole-system audit');
$accounts = (new Models\UserRepository())->activeLoginAccounts();
test_sign_in($accounts['super_admin'], 'super_admin', 1);

// --------------------------------------------------------------- the modules

$modules = Models\Schema::all();
$test->assert(count($modules) >= 25, sprintf('the registry holds every module (%d)', count($modules)));

foreach ($modules as $key => $module) {
    // Every column a list shows, a filter narrows by or a form writes has to be
    // a real column, or the page dies the first time somebody opens it.
    try {
        $listing = Models\LogisticsData::listing($key, [], \current_context());
        $test->assert(is_array($listing['rows']), sprintf('%s: the list opens', $key));
    } catch (Throwable $exception) {
        $test->assert(false, sprintf('%s: the list opens (%s)', $key, $exception->getMessage()));
        continue;
    }

    // A section that names a field the module does not define renders a blank
    // gap on the form and silently drops whatever the user typed into it.
    $fields = Models\Schema::fields($key);
    foreach ($module['sections'] as $section) {
        foreach ($section['fields'] as $name) {
            $test->assert(isset($fields[$name]), sprintf('%s: section "%s" names a field that exists (%s)', $key, $section['title'], $name));
        }
    }

    // Every field on the form has to be a column of the table behind it.
    $columns = Models\LogisticsData::columnMeta($module['table']);
    foreach ($fields as $name => $field) {
        $test->assert(isset($columns[$name]), sprintf('%s: %s is a real column', $key, $name));
    }

    // A relation has to point at a table the registry will actually read.
    foreach ($fields as $name => $field) {
        if (($field['type'] ?? '') !== 'relation') {
            continue;
        }
        $options = Models\LogisticsData::relationOptions($field['relation']);
        $test->assert(is_array($options), sprintf('%s: %s offers a list', $key, $name));
    }

    // Filters narrow the same list rather than throwing on an unknown column.
    foreach (($module['filters'] ?? []) as $name => $filter) {
        $first = array_key_first($filter['options'] ?? []);
        if ($first === null) {
            continue;
        }
        try {
            Models\LogisticsData::listing($key, [$name => (string) $first], \current_context());
            $test->assert(true, sprintf('%s: the %s filter works', $key, $name));
        } catch (Throwable $exception) {
            $test->assert(false, sprintf('%s: the %s filter works (%s)', $key, $name, $exception->getMessage()));
        }
    }

    // And the search box searches.
    try {
        Models\LogisticsData::listing($key, ['q' => 'zz'], \current_context());
        $test->assert(true, sprintf('%s: search runs', $key));
    } catch (Throwable $exception) {
        $test->assert(false, sprintf('%s: search runs (%s)', $key, $exception->getMessage()));
    }
}

// ---------------------------------------------------------------- the reports

foreach (Models\ReportData::keys() as $key) {
    try {
        $report = Models\ReportData::run($key);
        $test->assert(isset($report['rows']) && is_array($report['rows']), sprintf('report %s builds', $key));
        $test->assert(($report['columns'] ?? []) !== [], sprintf('report %s has columns', $key));
    } catch (Throwable $exception) {
        $test->assert(false, sprintf('report %s builds (%s)', $key, $exception->getMessage()));
    }
}

// ------------------------------------------------------------------ the books

foreach (Models\Books::CATALOGUE as $key => $spec) {
    try {
        $book = Models\Books::build($key, []);
        $test->assert(isset($book['doc']['rows']), sprintf('book %s builds', $key));
        $test->assert($book['currency'] !== '', sprintf('book %s says which currency it is in', $key));
        $test->assert(str_contains((string) $book['doc']['period'], $book['currency']), sprintf('book %s prints the currency on the page', $key));
    } catch (Throwable $exception) {
        $test->assert(false, sprintf('book %s builds (%s)', $key, $exception->getMessage()));
    }
}

$test->assert(Models\Ledger::isBalanced(), 'the books balance');

// Each currency is read on its own, and a currency with no entries is refused.
$test->assert(Models\Books::currenciesInUse() !== [], 'the ledger reports which currencies it holds');
$madeUp = Models\Books::build('trial_balance', ['currency' => 'XXX']);
$test->assert($madeUp['currency'] !== 'XXX', 'a currency the books do not hold is refused');

// ------------------------------------------------------------ the permissions

$roles = $pdo->query('SELECT role_key FROM roles')->fetchAll(PDO::FETCH_COLUMN);
foreach ($modules as $key => $module) {
    $granted = (int) $pdo->query(
        "SELECT COUNT(*) FROM role_permissions WHERE permission_key = " . $pdo->quote($key)
    )->fetchColumn();
    $test->assert($granted > 0, sprintf('%s: at least one role can reach it', $key));
}

// A role that can see nothing is a login that opens on an empty screen.
foreach ($roles as $role) {
    $visible = (int) $pdo->query(
        "SELECT COUNT(*) FROM role_permissions rp
           INNER JOIN roles r ON r.id = rp.role_id
          WHERE r.role_key = " . $pdo->quote((string) $role) . " AND rp.can_view = 1"
    )->fetchColumn();
    $test->assert($visible > 0, sprintf('role %s can see something', $role));
}

// ----------------------------------------------------------------- the emails

// Every message the system composes has to render, in both HTML and plain text,
// without a missing key turning into a blank page in somebody's inbox.
$id = Support\Mailer::queue([
    'key' => 'audit-render',
    'category' => 'notification',
    'to' => 'audit@example.test',
    'to_name' => 'Audit',
    'subject' => 'Audit',
    'heading' => 'Audit',
    'lines' => ['A line.'],
    'items' => [
        ['title' => 'One', 'columns' => ['a' => 'A'], 'rows' => [['a' => '1']]],
        ['title' => 'Two', 'columns' => ['b' => 'B'], 'rows' => [['b' => '2']]],
    ],
    'facts' => ['Key' => 'Value'],
    'closing' => ['A closing line.'],
]);
$test->assert($id !== null, 'a message can be composed and queued');

$written = $pdo->query('SELECT body_html, body_text FROM email_outbox WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
$test->assert(str_contains((string) $written['body_html'], 'One') && str_contains((string) $written['body_html'], 'Two'), 'a message can carry several tables');
$test->assert(str_contains((string) $written['body_text'], 'A closing line.'), 'and reads as plain text too');

$test->finish();
