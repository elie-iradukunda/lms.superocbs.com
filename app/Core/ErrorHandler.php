<?php

declare(strict_types=1);

namespace Core;

/**
 * What a visitor sees when something goes wrong.
 *
 * PHP's default is to print the exception, the file path and the whole stack
 * trace onto the page. On a laptop that is convenient; on a server it hands a
 * stranger the directory layout, the database user and the shape of the code —
 * before they have even signed in.
 *
 * So: a plain page saying what happened and what to do, with the detail written
 * to the error log where the administrator can read it. `APP_DEBUG=1` puts the
 * detail back on screen, for the machine you are developing on.
 */
final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || PHP_SAPI === 'cli') {
            return;
        }

        self::$registered = true;
        $debug = (bool) \config('app.debug', false);

        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_exception_handler(static function (\Throwable $exception) use ($debug): void {
            self::report($exception, $debug);
        });

        // A fatal error never reaches the exception handler, so the same page is
        // rendered from the shutdown hook rather than leaving a blank screen.
        register_shutdown_function(static function () use ($debug): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::report(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']), $debug);
        });
    }

    private static function report(\Throwable $exception, bool $debug): void
    {
        error_log(sprintf(
            'LMS %s: %s in %s:%d%s%s',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL,
            $exception->getTraceAsString()
        ));

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        // A database that cannot be reached is the one failure worth naming, so
        // the person reading the page is not left guessing at an outage.
        $unreachable = self::looksLikeDatabase($exception);

        $title = $unreachable ? 'The system cannot reach its database' : 'Something went wrong';
        $explanation = $unreachable
            ? 'The application is running but the database is not answering. Nothing has been lost; it will work again as soon as the database is back.'
            : 'The page could not be produced. The details have been written to the server error log.';

        $advice = $unreachable && self::isLocal()
            ? 'On this machine that usually means MySQL is not started. Open the XAMPP control panel and start MySQL, then reload.'
            : 'If this keeps happening, tell your administrator what you were doing when it appeared.';

        echo self::page($title, $explanation, $advice, $debug ? $exception : null);
        exit(1);
    }

    private static function looksLikeDatabase(\Throwable $exception): bool
    {
        do {
            if ($exception instanceof \PDOException) {
                return true;
            }
            if (str_contains($exception->getMessage(), 'database connection failed')) {
                return true;
            }
        } while ($exception = $exception->getPrevious());

        return false;
    }

    private static function isLocal(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        return $host === '' || str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
    }

    private static function page(string $title, string $explanation, string $advice, ?\Throwable $exception): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $base = rtrim((string) \config('app.base_url', ''), '/');

        $detail = '';
        if ($exception !== null) {
            $detail = '<div class="detail"><p class="label">Shown because APP_DEBUG is on</p><pre>'
                . $e(sprintf("%s: %s\nin %s:%d\n\n%s", $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString()))
                . '</pre></div>';
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $e($title) . '</title>'
            . '<style>'
            . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;'
            . 'background:#eef1f4;color:#22303c;font-family:"Plus Jakarta Sans",Segoe UI,Helvetica,Arial,sans-serif}'
            . 'main{max-width:560px;background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 14px rgba(20,30,40,.08)}'
            . 'h1{margin:0 0 12px;font-size:20px;line-height:1.35}'
            . 'p{margin:0 0 12px;font-size:15px;line-height:1.6;color:#4a5560;font-weight:500}'
            . '.advice{padding:12px 14px;background:#f7f9fb;border-left:3px solid #a96207;border-radius:0 6px 6px 0;color:#3d454d}'
            . 'a{display:inline-block;margin-top:18px;padding:10px 20px;background:#a96207;color:#fff;'
            . 'text-decoration:none;border-radius:6px;font-size:14px;font-weight:600}'
            . '.detail{margin-top:22px;border-top:1px solid #e6ebf0;padding-top:16px}'
            . '.label{font-size:11px;letter-spacing:.8px;text-transform:uppercase;color:#8a949e;font-weight:700}'
            . 'pre{overflow:auto;max-height:320px;background:#1f262d;color:#e7ecf0;padding:14px;border-radius:6px;'
            . 'font-size:12px;line-height:1.5;white-space:pre-wrap;word-break:break-word}'
            . '</style></head><body><main>'
            . '<h1>' . $e($title) . '</h1>'
            . '<p>' . $e($explanation) . '</p>'
            . '<p class="advice">' . $e($advice) . '</p>'
            . '<a href="' . $e($base === '' ? '/' : $base . '/') . '">Back to sign in</a>'
            . $detail
            . '</main></body></html>';
    }
}
