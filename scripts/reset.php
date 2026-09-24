<?php

declare(strict_types=1);

/**
 * Empty the working data and leave the setup standing.
 *
 * What survives is what a company configured rather than recorded: the roles and
 * logins, the permission matrix, the company settings, the chart of accounts and
 * fiscal periods, and the reference lists. Everything else — vehicles, drivers,
 * warehouses, trips, stock, invoices, the ledger, notifications, the audit trail,
 * the email outbox — is emptied, counters included, so the first record anyone
 * enters is number one.
 *
 * Usage:
 *   php scripts/reset.php            shows what would be emptied, changes nothing
 *   php scripts/reset.php --force    does it
 *   php scripts/reset.php --force --keep-audit   leaves the audit trail alone
 *
 * This is not reversible. Take a dump first if the data matters:
 *   mysqldump -u root logistics_mvc > backup.sql
 */

require __DIR__ . '/../bootstrap.php';

$argv ??= [];
$force = in_array('--force', $argv, true);
$keepAudit = in_array('--keep-audit', $argv, true);

/**
 * Configuration, not working data. These tables are never emptied.
 *
 * `users` is on the list because the people are the one thing a company has
 * already set up by the time it wants a clean start.
 */
$keep = [
    'migrations',
    'roles',
    'users',
    'permissions',
    'role_permissions',
    'company_settings',
    'gl_accounts',
    'gl_fiscal_periods',
    'lookup_values',
];

if ($keepAudit) {
    $keep[] = 'audit_logs';
}

try {
    $pdo = Core\Database::connection();
} catch (Throwable $exception) {
    fwrite(STDERR, "Cannot reach the database: {$exception->getMessage()}\n");
    exit(1);
}

$tables = $pdo->query(
    'SELECT TABLE_NAME FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"
      ORDER BY TABLE_NAME'
)->fetchAll(PDO::FETCH_COLUMN);

$counts = [];
foreach ($tables as $table) {
    $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

$toEmpty = array_values(array_filter($tables, static fn (string $t): bool => !in_array($t, $keep, true)));
$rows = array_sum(array_map(static fn (string $t): int => $counts[$t], $toEmpty));

echo "Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

echo "Kept as configuration\n";
foreach ($keep as $table) {
    if (isset($counts[$table])) {
        printf("  %-22s %6d row(s)\n", $table, $counts[$table]);
    }
}

echo "\nEmptied\n";
$emptyAlready = 0;
foreach ($toEmpty as $table) {
    if ($counts[$table] === 0) {
        $emptyAlready++;
        continue;
    }
    printf("  %-22s %6d row(s)\n", $table, $counts[$table]);
}
if ($emptyAlready > 0) {
    printf("  (%d other table(s) already empty)\n", $emptyAlready);
}

if (!$force) {
    echo "\nNothing was changed. Run it again with --force to empty {$rows} row(s).\n";
    exit(0);
}

if ($counts['users'] === 0) {
    fwrite(STDERR, "\nRefusing to reset: there are no users, so nobody could sign in afterwards.\n");
    fwrite(STDERR, "Run php scripts/migrate.php first.\n");
    exit(1);
}

// Foreign keys are switched off for the truncate because the tables are being
// emptied as a set; every reference disappears along with the row that made it.
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$failed = [];
foreach ($toEmpty as $table) {
    try {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
    } catch (Throwable $exception) {
        $failed[$table] = $exception->getMessage();
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

if ($failed !== []) {
    fwrite(STDERR, "\nSome tables could not be emptied:\n");
    foreach ($failed as $table => $message) {
        fwrite(STDERR, "  {$table}: {$message}\n");
    }
    exit(1);
}

// The lists have to exist for the forms to offer anything, and a reset should
// not be a way to lose them.
$added = Models\Lookup::seed();
if ($added > 0) {
    echo "\nRestored {$added} missing reference list entries.\n";
}

printf("\nEmptied %d table(s), %d row(s). %d user account(s) kept.\n", count($toEmpty), $rows, $counts['users']);
echo "The system is ready for real data.\n";
