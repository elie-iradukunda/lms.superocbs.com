<?php

declare(strict_types=1);

namespace Core;

/**
 * Per-session CSRF token. Every state-changing POST carries it in a hidden
 * `_token` field (or an `X-CSRF-Token` header for JSON calls) and the router
 * rejects the request when it does not match.
 */
final class Csrf
{
    private const SESSION_KEY = 'logistics_csrf_token';

    public static function token(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Called after a privilege change (login/logout) so an old token cannot be replayed. */
    public static function rotate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    public static function check(?string $candidate = null): bool
    {
        $candidate ??= (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($expected) && $candidate !== '' && hash_equals($expected, $candidate);
    }

    /** Hidden input for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
