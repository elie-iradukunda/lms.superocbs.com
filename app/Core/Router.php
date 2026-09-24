<?php

declare(strict_types=1);

namespace Core;

final class Router
{
    /** @var list<array{methods: list<string>, regex: string, handler: array{0: class-string, 1: string}, public: bool, permission: ?string, ability: string, defaults: array<string, string>, csrf: bool}> */
    private array $routes = [];

    public function __construct(private readonly bool $json = false)
    {
    }

    public function get(string $pattern, array $handler, array $options = []): void
    {
        $this->add('GET', $pattern, $handler, $options);
    }

    public function post(string $pattern, array $handler, array $options = []): void
    {
        $this->add('POST', $pattern, $handler, $options);
    }

    /**
     * Patterns are paths such as `/vehicles/{id}/edit`; each `{name}` matches one URL segment.
     *
     * Options:
     *   public   skips the login check
     *   permission  the permission key required (web routes default to the first path segment)
     *   ability  which right on that permission is needed: view, create, edit, delete or approve
     *   defaults extra handler params
     *   csrf     false to skip the CSRF check on a POST (used only for login)
     */
    public function add(string|array $methods, string $pattern, array $handler, array $options = []): void
    {
        $pattern = '/' . trim($pattern, '/');
        $public = (bool) ($options['public'] ?? false);
        $firstSegment = explode('/', trim($pattern, '/'))[0];

        $this->routes[] = [
            'methods' => array_map('strtoupper', (array) $methods),
            'regex' => $this->compile($pattern),
            'handler' => $handler,
            'public' => $public,
            'permission' => $public ? null : ($options['permission'] ?? ($this->json ? null : $firstSegment)),
            'ability' => $options['ability'] ?? 'view',
            'defaults' => $options['defaults'] ?? [],
            'csrf' => $options['csrf'] ?? true,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $path = '/' . trim($path, '/');
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $pathMatched = true;
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $captured = array_map('rawurldecode', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            $this->run($route, $captured + $route['defaults'], $method);
            return;
        }

        $pathMatched ? $this->fail(405, 'Method not allowed.') : $this->fail(404, 'The requested logistics page does not exist.');
    }

    private function run(array $route, array $params, string $method): void
    {
        if (!$route['public'] && !\is_logged_in()) {
            if ($this->json) {
                $this->fail(401, 'Authentication required.');
                return;
            }

            header('Location: ' . \url('', ['login_required' => 1]) . '#login');
            return;
        }

        // A one-time password must be replaced before anything else can be reached.
        if (!$route['public'] && \must_change_password() && !str_starts_with($params['__route'] ?? '', 'account')) {
            $handlerClass = $route['handler'][0];
            if (!str_contains($handlerClass, 'AccountController') && !str_contains($handlerClass, 'HomeController')) {
                if ($this->json) {
                    $this->fail(403, 'Your password must be changed before the API can be used.');
                    return;
                }
                header('Location: ' . \url(['account', 'password'], ['forced' => 1]));
                return;
            }
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $route['csrf'] && !Csrf::check()) {
            if ($this->json) {
                $this->fail(419, 'The security token is missing or expired.');
                return;
            }

            Flash::error('Your session security token expired. Please try that action again.');
            header('Location: ' . \url(\current_route()));
            return;
        }

        if ($route['permission'] !== null && !\role_can($route['permission'], $route['ability'])) {
            if ($this->json) {
                $this->fail(403, 'This resource is not available for your role.');
                return;
            }

            header('Location: ' . \url('dashboard', ['denied' => $route['permission']]));
            return;
        }

        [$controller, $action] = $route['handler'];
        (new $controller())->{$action}($params);
    }

    private function compile(string $pattern): string
    {
        $parts = preg_split('#(\{\w+\})#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $regex = '';
        foreach ($parts as $part) {
            $regex .= preg_match('#^\{(\w+)\}$#', $part, $name) ? '(?P<' . $name[1] . '>[^/]+)' : preg_quote($part, '#');
        }

        return '#^' . $regex . '$#';
    }

    private function fail(int $status, string $message): void
    {
        http_response_code($status);
        if ($this->json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $message]);
            return;
        }

        \view('layouts/error', ['title' => $status === 404 ? 'Page not found' : 'Request not allowed', 'message' => $message]);
    }
}
