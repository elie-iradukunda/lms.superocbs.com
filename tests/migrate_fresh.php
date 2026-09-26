<?php

declare(strict_types=1);

/**
 * A fresh install: scripts/migrate.php builds the whole database from nothing,
 * and running it a second time changes nothing.
 */

$dbName = 'logistics_mvc_migrate_' . date('YmdHis');
putenv('LOGISTICS_DB_HOST=127.0.0.1');
putenv('LOGISTICS_DB_NAME=' . $dbName);
putenv('LOGISTICS_DB_USER=root');
putenv('LOGISTICS_DB_PASS=');

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
$rootDsn = sprintf('mysql:host=%s;charset=%s', $db['host'], $db['charset']);
$dbDsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $dbName, $db['charset']);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$cleanup = new PDO($rootDsn, $db['user'], $db['pass'], $options);
$cleanup->exec("DROP DATABASE IF EXISTS `{$dbName}`");
$pdo = null;

// exit() skips `finally`, so the drop is registered where it always runs.
register_shutdown_function(static function () use ($cleanup, $dbName): void {
    try {
        $cleanup->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    } catch (Throwable) {
    }
});

try {
    ob_start();
    require __DIR__ . '/../scripts/migrate.php';
    $firstRun = (string) ob_get_clean();

    $pdo = new PDO($dbDsn, $db['user'], $db['pass'], $options);

    $tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
    $snapshot = static function () use ($pdo, $tables): array {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        }
        return $counts;
    };
    $afterFirst = $snapshot();

    ob_start();
    require __DIR__ . '/../scripts/migrate.php';
    $secondRun = (string) ob_get_clean();
    $afterSecond = $snapshot();

    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$failures): void {
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $assert(str_contains($firstRun, 'Imported database/schema.sql'), 'a fresh run imports the schema');

    // What a real company gets: the roles, the logins, the chart of accounts and
    // the reference lists, and none of somebody else's vehicles and trips.
    foreach (['seed.sql', 'seed_accounting.sql'] as $seed) {
        $assert(str_contains($firstRun, "Loaded database/{$seed}"), "a fresh run loads {$seed}");
    }
    foreach (['seed_demo_base.sql', 'seed_extended.sql'] as $seed) {
        $assert(!str_contains($firstRun, "Loaded database/{$seed}"), "a plain run leaves {$seed} out");
    }

    $migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
    foreach ($migrationFiles as $migration) {
        $name = basename($migration);
        $assert(str_contains($firstRun, "Applied {$name}"), "a fresh run applies {$name}");
        $assert(str_contains($secondRun, "Skipped {$name}"), "a second run skips {$name}");
    }

    $assert((int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() === count($migrationFiles), 'every migration is recorded');

    // The tables the depth migrations introduce must exist on a fresh install.
    foreach ([
        'customers', 'shipments', 'trip_stops', 'vehicle_documents', 'maintenance_parts',
        'stock_movements', 'purchase_request_lines', 'rate_cards', 'invoices', 'invoice_lines',
        'payments', 'permissions', 'role_permissions', 'company_settings', 'login_attempts', 'password_resets',
    ] as $table) {
        $assert(in_array($table, $tables, true), "a fresh install creates the {$table} table");
    }

    $assert((int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() === 7, 'seven roles are created');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 7, 'seven users are created');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn() >= 28, 'every module has a permission row');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn() >= 60, 'the role matrix is populated');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM company_settings')->fetchColumn() >= 15, 'company settings are populated');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn() >= 5, 'the payment methods are seeded');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM lookup_values')->fetchColumn() >= 60, 'the reference lists are seeded');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM gl_accounts')->fetchColumn() >= 30, 'the chart of accounts is seeded');

    // Nothing operational: a new company starts with an empty working set.
    foreach (['vehicles', 'drivers', 'trips', 'shipments', 'deliveries', 'invoices', 'payments', 'inventory_items', 'warehouses'] as $empty) {
        $assert((int) $pdo->query("SELECT COUNT(*) FROM {$empty}")->fetchColumn() === 0, "a fresh install has no {$empty}");
    }

    // Every table holds the same rows on a second run, which is what makes the
    // seeds safe to re-run. The audit trail is the exception: it is a log, so a
    // second run legitimately adds the entry recording that it happened.
    foreach ($afterFirst as $table => $count) {
        if ($table === 'audit_logs') {
            $assert(($afterSecond[$table] ?? 0) >= $count, 'the audit trail records the second run rather than being rewritten');
            continue;
        }

        $assert(($afterSecond[$table] ?? -1) === $count, "{$table} keeps {$count} rows on a second run");
    }

    // The ledger is posted as part of setting up, so the books are not empty the
    // first time anyone opens them.
    $assert(str_contains($firstRun, 'to the ledger') || str_contains($firstRun, 'already up to date'), 'a fresh run reaches the ledger step');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM gl_journal_entries WHERE deleted_at IS NULL")->fetchColumn() === 0, 'a fresh install starts with an empty ledger');

    $sides = $pdo->query(
        "SELECT COALESCE(SUM(l.debit), 0) d, COALESCE(SUM(l.credit), 0) c
           FROM gl_journal_lines l
           INNER JOIN gl_journal_entries e ON e.id = l.entry_id
          WHERE e.status = 'posted' AND e.deleted_at IS NULL"
    )->fetch();
    $assert(abs((float) $sides['d'] - (float) $sides['c']) < 0.004, 'the ledger balances after a fresh setup');

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        exit(1);
    }

    printf("Fresh migration tests passed (%d tables verified).\n", count($tables));
} finally {
    $pdo = null;
    $cleanup = new PDO($rootDsn, $db['user'], $db['pass'], $options);
    $cleanup->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
