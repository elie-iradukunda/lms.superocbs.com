<?php

declare(strict_types=1);

/**
 * Reads `.env` into the environment.
 *
 * The file is not in version control, so secrets - the mail API key, the
 * database password - live on the machine that runs the app rather than in the
 * repository.
 *
 * A value already in the environment always wins. That is what lets the test
 * suite point a run at a throwaway database with putenv() without `.env`
 * quietly putting the real one back.
 *
 * This is required from config/config.php rather than autoloaded, because the
 * configuration is read before the autoloader is registered.
 */

return static function (string $path): void {
    static $loaded = [];

    if (isset($loaded[$path]) || !is_file($path) || !is_readable($path)) {
        return;
    }
    $loaded[$path] = true;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Comments and anything that is not KEY=VALUE.
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            continue;
        }

        // Quoted values may legitimately carry spaces or a '#'.
        if (strlen($value) > 1 && (
            ($value[0] === '"' && str_ends_with($value, '"')) ||
            ($value[0] === "'" && str_ends_with($value, "'"))
        )) {
            $value = substr($value, 1, -1);
        } elseif (str_contains($value, ' #')) {
            $value = trim(substr($value, 0, (int) strpos($value, ' #')));
        }

        // Never overwrite what the caller already set.
        if (getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
};
