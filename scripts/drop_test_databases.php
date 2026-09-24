<?php

declare(strict_types=1);

/**
 * Drop the throwaway databases the test suites left behind.
 *
 * Each suite builds its own database (`logistics_mvc_smoke_20260921120418_c3a9…`)
 * and dropped it in a `finally` block — which PHP skips when a script ends with
 * exit(), as every suite does. One database was stranded per run. The suites
 * register the drop on shutdown now, so this is a one-off tidy-up of the pile
 * that built up before, and a safety net if one ever escapes again.
 *
 * Usage:
 *   php scripts/drop_test_databases.php            lists what it would drop
 *   php scripts/drop_test_databases.php --force    drops them
 *
 * The real database is matched by name and never touched: only names carrying a
 * suite prefix AND a timestamp suffix qualify.
 */

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
$live = $db['name'];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO(sprintf('mysql:host=%s;charset=%s', $db['host'], $db['charset'] ?? 'utf8mb4'), $db['user'], $db['pass'], $options);
} catch (Throwable $exception) {
    fwrite(STDERR, "Cannot reach MySQL: {$exception->getMessage()}\n");
    fwrite(STDERR, "Start MySQL in the XAMPP control panel and run this again.\n");
    exit(1);
}

// The suite names, followed by the timestamp and random suffix test_database()
// appends. Anything without both parts is somebody's real database.
$pattern = '/^logistics_mvc_(smoke|seed|workflow|security|scenario|accounting|email|http|migrate)_\d{14}(_[0-9a-f]{6})?$/';

$all = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
$stale = array_values(array_filter(
    $all,
    static fn (string $name): bool => $name !== $live && preg_match($pattern, $name) === 1
));

if ($stale === []) {
    echo "No leftover test databases. Nothing to do.\n";
    exit(0);
}

echo count($stale) . " leftover test database(s):\n";
foreach ($stale as $name) {
    $size = $pdo->prepare(
        'SELECT COALESCE(ROUND(SUM(data_length + index_length) / 1024 / 1024, 1), 0)
           FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?'
    );
    $size->execute([$name]);
    printf("  %-52s %6s MB\n", $name, $size->fetchColumn());
}

if (!in_array('--force', $argv ?? [], true)) {
    echo "\nNothing was changed. Run it again with --force to drop them.\n";
    echo "Your working database ({$live}) is not in the list and is never touched.\n";
    exit(0);
}

$dropped = 0;
foreach ($stale as $name) {
    try {
        $pdo->exec("DROP DATABASE `{$name}`");
        $dropped++;
    } catch (Throwable $exception) {
        fwrite(STDERR, "Could not drop {$name}: {$exception->getMessage()}\n");
    }
}

printf("\nDropped %d of %d. %s is untouched.\n", $dropped, count($stale), $live);
