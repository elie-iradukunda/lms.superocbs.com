<?php

declare(strict_types=1);

/**
 * Shared test harness.
 *
 * Builds a throwaway database exactly the way scripts/migrate.php builds a real
 * one: base schema, then every migration in order, then the seed files. Tests
 * that skipped the migrations used to miss every column added after the first
 * release.
 */

/** @return array{0: PDO, 1: PDO, 2: string} [$pdo, $root, $dbName] */
/**
 * A throwaway database for one suite.
 *
 * `$withDemo` is what separates the suites that assert against the worked
 * example from the one that proves a brand new installation can be filled in
 * from the front end with nothing in it.
 */
function test_database(string $prefix, bool $reseedTwice = false, bool $withDemo = true): array
{
    $dbName = $prefix . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);

    putenv('LOGISTICS_DB_HOST=127.0.0.1');
    putenv('LOGISTICS_DB_NAME=' . $dbName);
    putenv('LOGISTICS_DB_USER=root');
    putenv('LOGISTICS_DB_PASS=');

    $config = require __DIR__ . '/../config/config.php';
    $db = $config['db'];
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $root = new PDO(sprintf('mysql:host=%s;charset=%s', $db['host'], $db['charset']), $db['user'], $db['pass'], $options);
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");

    // Every suite ends by calling TestRun::finish(), which calls exit() — and
    // exit() does not run `finally`. Relying on those blocks left one throwaway
    // database behind per run, so the drop is registered here instead, where it
    // survives any way the script ends.
    register_shutdown_function(static function () use ($root, $dbName): void {
        try {
            $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        } catch (Throwable) {
            // A suite that killed the connection has nothing left to clean up with.
        }
    });

    $rewrite = static fn (string $path): string => str_replace('logistics_mvc', $dbName, (string) file_get_contents($path));

    $root->exec($rewrite(__DIR__ . '/../database/schema.sql'));

    $pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $dbName, $db['charset']), $db['user'], $db['pass'], $options);

    $migrations = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
    sort($migrations);
    foreach ($migrations as $migration) {
        $pdo->exec((string) file_get_contents($migration));
    }

    // Most suites assert against the worked example, so they load the demo seeds
    // a plain migration leaves out.
    $seeds = [
        __DIR__ . '/../database/seed.sql',
        __DIR__ . '/../database/seed_accounting.sql',
    ];

    if ($withDemo) {
        array_push(
            $seeds,
            __DIR__ . '/../database/seed_demo_base.sql',
            __DIR__ . '/../database/seed_extended.sql',
            __DIR__ . '/../database/seed_accounting_demo.sql',
        );
    }

    // Running the seeds twice proves they are idempotent.
    foreach (range(1, $reseedTwice ? 2 : 1) as $ignored) {
        foreach ($seeds as $seed) {
            $root->exec($rewrite($seed));
        }
    }

    return [$pdo, $root, $dbName];
}

/** Collects assertion failures and reports them all at the end. */
final class TestRun
{
    /** @var list<string> */
    private array $failures = [];
    private int $passed = 0;

    public function __construct(private readonly string $name)
    {
    }

    public function assert(bool $condition, string $message): void
    {
        $condition ? $this->passed++ : $this->failures[] = $message;
    }

    public function same(mixed $expected, mixed $actual, string $message): void
    {
        $this->assert(
            $expected === $actual,
            sprintf('%s (expected %s, got %s)', $message, var_export($expected, true), var_export($actual, true))
        );
    }

    /** Asserts that a callable throws, which is how the guards are tested. */
    public function throws(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->failures[] = $message . ' (no exception was thrown)';
        } catch (\Throwable) {
            $this->passed++;
        }
    }

    public function finish(): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }
            fwrite(STDERR, sprintf("%s: %d passed, %d failed.\n", $this->name, $this->passed, count($this->failures)));
            exit(1);
        }

        printf("%s passed (%d assertions).\n", $this->name, $this->passed);
        exit(0);
    }
}

/** Signs a role in for the duration of a test, the way the router would. */
function test_sign_in(array $account, string $roleKey, int $prvg = 2): void
{
    $_SESSION['logistics_authenticated'] = true;
    $_SESSION['logistics_role'] = $roleKey;
    $_SESSION['logistics_user_id'] = (int) $account['id'];
    $_SESSION['logistics_user_name'] = (string) $account['name'];
    $_SESSION['logistics_user_email'] = (string) $account['email'];
    $_SESSION['logistics_prvg'] = $prvg;
    unset($_SESSION['logistics_driver_id']);
    \Models\Permission::flush();
}
