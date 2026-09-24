<?php

declare(strict_types=1);

use Core\Router;

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// With `php -S ... public/index.php` let the built-in server serve real files (assets) itself.
if (PHP_SAPI === 'cli-server' && $requestPath !== '/' && is_file(__DIR__ . $requestPath)) {
    return false;
}

require dirname(__DIR__) . '/bootstrap.php';

$base = rtrim((string) config('app.base_url', ''), '/');
if ($base !== '' && strncasecmp($requestPath, $base, strlen($base)) === 0) {
    $requestPath = substr($requestPath, strlen($base));
}

$path = '/' . trim($requestPath, '/');
$isApi = $path === '/api' || str_starts_with($path, '/api/');

$router = new Router($isApi);
require dirname(__DIR__) . '/routers/' . ($isApi ? 'api' : 'web') . '.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $isApi ? substr($path, 4) : $path);
