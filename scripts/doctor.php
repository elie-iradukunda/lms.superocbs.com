<?php

declare(strict_types=1);

/**
 * Does this database have everything the code expects?
 *
 * Run it after pulling a new version, after a deploy, or when a page fails with
 * "unknown column". It compares the running database against what the code
 * actually asks for — every migration file, every table and column the module
 * registry reads, every permission a route checks — and says which of them is
 * missing rather than leaving it to be discovered by a user.
 *
 * It changes nothing. When something is missing the fix is almost always the
 * same one line, which it prints.
 *
 * Usage:
 *   php scripts/doctor.php
 */

require __DIR__ . '/../bootstrap.php';

$problems = [];
$notes = [];
$ok = static fn (string $line): string => "  \u{2713} {$line}";
$bad = static fn (string $line): string => "  \u{2717} {$line}";

try {
    $pdo = Core\Database::connection();
} catch (Throwable $exception) {
    fwrite(STDERR, "Cannot reach the database: {$exception->getMessage()}\n");
    fwrite(STDERR, "Check LOGISTICS_DB_* in .env, and that MySQL is running.\n");
    exit(1);
}

echo "LMS database check\n";
printf("Database: %s on %s\n", $pdo->query('SELECT DATABASE()')->fetchColumn(), $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
echo str_repeat('-', 64) . "\n\n";

// ------------------------------------------------------------- migrations
echo "Migrations\n";

$files = array_map('basename', glob(__DIR__ . '/../database/migrations/*.sql') ?: []);
sort($files);

try {
    $applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable) {
    $applied = [];
    $problems[] = 'The migrations table does not exist. Run: php scripts/migrate.php';
}

$pending = array_values(array_diff($files, $applied));
$unknown = array_values(array_diff($applied, $files));

if ($pending === []) {
    echo $ok(count($files) . ' migration(s), all applied') . "\n";
} else {
    echo $bad(count($pending) . ' migration(s) not applied yet:') . "\n";
    foreach ($pending as $name) {
        echo "      {$name}\n";
    }
    $problems[] = 'Run: php scripts/migrate.php';
}

if ($unknown !== []) {
    $notes[] = sprintf('%d migration(s) recorded here but missing from the repository: %s. This database is ahead of the code.', count($unknown), implode(', ', $unknown));
}

// ------------------------------------------------- tables and columns
echo "\nTables and columns the modules read\n";

$columns = [];
foreach ($pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()') as $row) {
    $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
}

$missingTables = [];
$missingColumns = [];

foreach (Models\Schema::all() as $key => $module) {
    $table = $module['table'];

    if (!isset($columns[$table])) {
        $missingTables[] = sprintf('%s (needed by the %s page)', $table, $module['title']);
        continue;
    }

    // Field names are column names, except for the ones the list query aliases.
    foreach (array_keys($module['fields']) as $field) {
        if (!isset($columns[$table][$field])) {
            $missingColumns[] = sprintf('%s.%s (the "%s" field on %s)', $table, $field, $module['fields'][$field]['label'], $module['title']);
        }
    }

    if (isset($module['lines']['table']) && !isset($columns[$module['lines']['table']])) {
        $missingTables[] = sprintf('%s (the line items under %s)', $module['lines']['table'], $module['title']);
    }
}

// Tables no module owns but the system still depends on.
foreach (['migrations', 'roles', 'users', 'permissions', 'role_permissions', 'company_settings',
          'notifications', 'audit_logs', 'login_attempts', 'password_resets', 'email_outbox',
          'gl_accounts', 'gl_journal_entries', 'gl_journal_lines', 'gl_fiscal_periods',
          'payment_methods', 'lookup_values'] as $table) {
    if (!isset($columns[$table])) {
        $missingTables[] = $table;
    }
}

if ($missingTables === [] && $missingColumns === []) {
    echo $ok(count($columns) . ' tables present, every field the modules read has a column') . "\n";
} else {
    foreach ($missingTables as $line) {
        echo $bad('missing table ' . $line) . "\n";
    }
    foreach ($missingColumns as $line) {
        echo $bad('missing column ' . $line) . "\n";
    }
    $problems[] = 'Run: php scripts/migrate.php';
}

// --------------------------------------------------------- permissions
echo "\nPermissions\n";

$known = [];
try {
    $known = $pdo->query('SELECT permission_key FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable) {
    // Already reported as a missing table.
}

$needed = array_unique(array_merge(
    array_map(static fn (array $m): string => $m['permission'], Models\Schema::all()),
    ['dashboard', 'reports', 'settings', 'audit', 'email', 'books', 'journal']
));
$missingPermissions = array_values(array_diff($needed, $known));

if ($missingPermissions === []) {
    echo $ok(count($known) . ' permission(s) defined, every module has one') . "\n";
} else {
    echo $bad('no permission row for: ' . implode(', ', $missingPermissions)) . "\n";
    $problems[] = 'Run: php scripts/migrate.php';
}

$noAdmin = (int) $pdo->query(
    "SELECT COUNT(*) FROM roles r
       LEFT JOIN role_permissions rp ON rp.role_id = r.id AND rp.permission_key = 'users' AND rp.can_view = 1
      WHERE r.role_key = 'super_admin' AND rp.role_id IS NULL"
)->fetchColumn();
if ($noAdmin > 0) {
    echo $bad('the super admin role cannot open Users — nobody can administer this system') . "\n";
    $problems[] = 'Run: php scripts/migrate.php';
}

// -------------------------------------------------- reference data
echo "\nReference data\n";

foreach ([
    'users' => ['At least one active login', "SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL"],
    'gl_accounts' => ['A chart of accounts', 'SELECT COUNT(*) FROM gl_accounts WHERE deleted_at IS NULL'],
    'lookup_values' => ['Reference lists', 'SELECT COUNT(*) FROM lookup_values WHERE deleted_at IS NULL'],
    'payment_methods' => ['Payment methods', 'SELECT COUNT(*) FROM payment_methods WHERE deleted_at IS NULL'],
] as $table => [$label, $sql]) {
    if (!isset($columns[$table])) {
        continue;
    }
    $count = (int) $pdo->query($sql)->fetchColumn();
    echo $count > 0 ? $ok("{$label}: {$count}") . "\n" : $bad("{$label}: none") . "\n";
    if ($count === 0) {
        $problems[] = "There is no {$label} yet. Run: php scripts/migrate.php";
    }
}

$unmapped = isset($columns['payment_methods'])
    ? (int) $pdo->query('SELECT COUNT(*) FROM payment_methods WHERE deleted_at IS NULL AND is_active = 1 AND gl_account_id IS NULL')->fetchColumn()
    : 0;
if ($unmapped > 0) {
    $notes[] = sprintf('%d active payment method(s) do not name an account, so money on them posts to the default bank account. Set them under Accounting, Payment methods.', $unmapped);
}

// --------------------------------------------------------- environment
echo "\nEnvironment\n";

foreach ([
    'APP_URL' => [config('app.url'), 'Links inside emails will fall back to the running request, and will be empty when mail is sent from the console.'],
    'RESEND_API_KEY' => [config('mail.api_key'), 'Email is switched off: messages are recorded in the outbox and none are sent.'],
    'SMTP_FROM_EMAIL' => [config('mail.from_email'), 'No address to send from, so nothing can be sent.'],
] as $key => [$value, $consequence]) {
    echo trim((string) $value) !== '' ? $ok("{$key} is set") . "\n" : $bad("{$key} is empty — {$consequence}") . "\n";
}

if (trim((string) config('mail.redirect_all_to')) !== '') {
    $notes[] = sprintf('Test mode is on: every email goes to %s instead of its real recipient. Clear MAIL_REDIRECT_ALL_TO before going live.', config('mail.redirect_all_to'));
}

if (config('app.debug')) {
    $notes[] = 'APP_DEBUG is on, which shows full errors on screen. Turn it off on anything the public can reach.';
}

$uploads = (string) config('uploads.path');
if (!is_dir($uploads)) {
    $notes[] = sprintf('The upload folder %s does not exist yet. It is created on the first upload, provided the web server may write there.', $uploads);
} elseif (!is_writable($uploads)) {
    echo $bad("the upload folder {$uploads} is not writable — proof of delivery files cannot be saved") . "\n";
    $problems[] = 'Give the web server write access to ' . $uploads;
}

// ------------------------------------------------------------- ledger
if (isset($columns['gl_journal_lines'])) {
    echo "\nAccounting\n";
    $sides = $pdo->query(
        "SELECT COALESCE(SUM(l.debit), 0) d, COALESCE(SUM(l.credit), 0) c
           FROM gl_journal_lines l
           INNER JOIN gl_journal_entries e ON e.id = l.entry_id
          WHERE e.status = 'posted' AND e.deleted_at IS NULL"
    )->fetch();
    $difference = abs((float) $sides['d'] - (float) $sides['c']);
    echo $difference < 0.005
        ? $ok(sprintf('the ledger balances (%s on each side)', number_format((float) $sides['d'], 2))) . "\n"
        : $bad(sprintf('the ledger is out by %s — open the trial balance', number_format($difference, 2))) . "\n";
}

// --------------------------------------------------- doors left open
//
// The seed ships with passwords printed in a file that is in the repository.
// They are right for a system nobody has deployed and wrong the moment it is
// reachable, so the check belongs here rather than in somebody's memory.
try {
    echo "
Accounts
";
    $seeded = ['Admin@123', 'Password@123', 'Logistics@123', 'Fleet@123', 'Finance@123', 'Driver@123', 'Warehouse@123', 'admin123', 'password'];
    $openAccounts = 0;
    $forced = 0;

    foreach ($pdo->query('SELECT password_hash, must_change_password FROM users WHERE deleted_at IS NULL') as $user) {
        foreach ($seeded as $guess) {
            if (password_verify($guess, (string) $user['password_hash'])) {
                $openAccounts++;
                $forced += (int) $user['must_change_password'] === 1 ? 1 : 0;
                break;
            }
        }
    }

    if ($openAccounts === 0) {
        echo $ok('no account is on a password that ships with the code') . "
";
    } elseif ($openAccounts === $forced) {
        echo $ok(sprintf('%d account(s) still on a seeded password, all required to change at next sign-in', $openAccounts)) . "
";
    } else {
        echo $bad(sprintf('%d account(s) can sign in with a password published in this repository', $openAccounts - $forced)) . "
";
        $problems[] = 'Run: php scripts/secure.php --apply';
    }
} catch (Throwable $exception) {
    $notes[] = 'The accounts could not be checked: ' . $exception->getMessage();
}

// ------------------------------------------------------------ verdict
echo "\n" . str_repeat('-', 64) . "\n";

foreach (array_unique($notes) as $note) {
    echo "Note: {$note}\n";
}

if ($problems === []) {
    echo "\nNothing is missing. This database matches the code.\n";
    exit(0);
}

echo "\n" . count(array_unique($problems)) . " thing(s) to fix:\n";
foreach (array_unique($problems) as $problem) {
    echo "  - {$problem}\n";
}
exit(1);
