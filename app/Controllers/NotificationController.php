<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\Notifier;

/** Marking updates as read; before this the bell count could only ever go up. */
final class NotificationController
{
    public function read(): void
    {
        $key = trim((string) ($_POST['notification_key'] ?? ''));
        if ($key !== '') {
            Notifier::markRead((int) \current_user_id(), \current_role(), $key);
        }

        $this->back((string) ($_POST['redirect'] ?? ''));
    }

    public function readAll(): void
    {
        $cleared = Notifier::markAllRead((int) \current_user_id(), \current_role());
        Flash::success($cleared === 0 ? 'You had no unread updates.' : sprintf('%d update(s) marked as read.', $cleared));

        $this->back((string) ($_POST['redirect'] ?? ''));
    }

    /** Only ever returns the user to a path inside this app. */
    private function back(string $redirect): never
    {
        $route = trim($redirect, '/');
        $safe = preg_match('#^[A-Za-z0-9_/-]*$#', $route) === 1 && !str_contains($route, '..');

        header('Location: ' . \url($safe && $route !== '' ? explode('/', $route) : 'dashboard'));
        exit;
    }
}
