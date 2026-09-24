<?php

declare(strict_types=1);

namespace Core;

/**
 * One-shot messages that survive the redirect after a POST, so the user sees
 * "Trip TRP-0007 dispatched" instead of a bare `?saved=1`.
 */
final class Flash
{
    private const SESSION_KEY = 'logistics_flash';

    public static function add(string $type, string $message): void
    {
        $_SESSION[self::SESSION_KEY][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('danger', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    /** Returns the queued messages and clears them. */
    public static function take(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        return is_array($messages) ? $messages : [];
    }
}
