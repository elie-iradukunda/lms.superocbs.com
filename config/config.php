<?php

declare(strict_types=1);

// Secrets live in .env on the machine, not in the repository. Anything already
// in the environment wins, so a test run can still point at its own database.
(require __DIR__ . '/env.php')(dirname(__DIR__) . '/.env');

/**
 * Every value here comes from the environment.
 *
 * The fallbacks are deliberately neutral: nothing names a host, a folder, a
 * company or a mailbox, because this file is committed and the same copy runs on
 * a laptop under XAMPP and on a customer's server. Anything installation
 * specific belongs in .env — see .env.example for the full list.
 */
$env = static fn (string $key, string $default = ''): string => (string) (getenv($key) !== false && getenv($key) !== '' ? getenv($key) : $default);

return [
    'app' => [
        'name' => $env('APP_NAME', 'LMS'),

        // The path the app is served under. Empty means the domain root, which
        // is what a real deployment normally looks like; a sub-folder install
        // (XAMPP, shared hosting) sets LOGISTICS_BASE_URL=/folder.
        'base_url' => rtrim($env('LOGISTICS_BASE_URL'), '/'),

        // Links inside an email cannot be relative, so the public address of
        // this installation is configured rather than guessed. Left empty, the
        // running request is used, which is right in a browser and absent on the
        // console — set it before relying on mailed links.
        'url' => rtrim($env('APP_URL'), '/'),

        // Whether to show the full error on screen. Off unless asked for.
        'debug' => $env('APP_DEBUG') === '1',
    ],
    'db' => [
        'host' => $env('LOGISTICS_DB_HOST', '127.0.0.1'),
        'port' => $env('LOGISTICS_DB_PORT', '3306'),
        'name' => $env('LOGISTICS_DB_NAME', 'logistics_mvc'),
        'user' => $env('LOGISTICS_DB_USER', 'root'),
        'pass' => $env('LOGISTICS_DB_PASS'),
        'charset' => $env('LOGISTICS_DB_CHARSET', 'utf8mb4'),
    ],
    'mail' => [
        // Resend's HTTP API. Without a key the system still records every
        // message it meant to send, so nothing is lost while mail is off.
        'api_key' => $env('RESEND_API_KEY'),

        // The address mail is sent from. It must belong to a domain verified
        // with the provider, so there is no sensible default.
        'from_email' => $env('SMTP_FROM_EMAIL'),
        'from_name' => $env('SMTP_FROM_NAME'),
        'reply_to' => $env('INITIAL_REPLY_TO'),
        'endpoint' => $env('MAIL_ENDPOINT', 'https://api.resend.com/emails'),

        // Somewhere to send everything while testing, instead of to real people.
        // Leave blank in production.
        'redirect_all_to' => $env('MAIL_REDIRECT_ALL_TO'),

        // A command-line run queues its messages but does not send them, so a
        // test suite or a migration never posts mail to real people. Set
        // MAIL_SEND_IN_CLI=1 to send from the console on purpose.
        'send_in_cli' => $env('MAIL_SEND_IN_CLI') === '1',
    ],
    'uploads' => [
        // Where uploaded proof files and receipts are written.
        'path' => $env('UPLOAD_PATH', dirname(__DIR__) . '/storage/uploads'),
        'max_bytes' => (int) $env('UPLOAD_MAX_BYTES', '5242880'),
    ],
];
