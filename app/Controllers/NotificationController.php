<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\Approvals;
use Models\Housekeeping;
use Models\Notification;
use Models\Notifier;

/** Every update a person has had, the live feed the pages poll, and marking them read. */
final class NotificationController
{
    private const PER_PAGE = 30;

    public function index(): void
    {
        $model = new Notification();
        $unreadOnly = ($_GET['show'] ?? '') === 'unread';
        $total = $model->total(\current_user_id(), \current_role(), $unreadOnly);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

        \view('notifications/index', [
            'title' => 'Updates',
            'rows' => $model->page(\current_user_id(), \current_role(), $unreadOnly, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'unreadOnly' => $unreadOnly,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'unread' => $model->unreadCount(\current_user_id(), \current_role()),
        ]);
    }

    /**
     * What an open page needs every minute: how many updates are unread, how
     * many approvals wait for this person, and the new updates since the last
     * look so they can pop up. It also lets the clock run where the server has
     * no scheduler.
     */
    public function feed(): void
    {
        try {
            Housekeeping::tick();
        } catch (\Throwable $exception) {
            error_log('Housekeeping: ' . $exception->getMessage());
        }

        $model = new Notification();
        $after = (int) ($_GET['after'] ?? 0);
        $fresh = $after > 0 ? $model->newerThan(\current_user_id(), \current_role(), $after) : [];

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'unread' => $model->unreadCount(\current_user_id(), \current_role()),
            'approvals' => Approvals::countFor(\current_role()) + \Models\RequestForms::inboxCount() + \Models\ManualJournals::inboxCount(),
            'latest' => $model->latestId(),
            'items' => array_map(static fn (array $n): array => [
                'id' => (int) $n['id'],
                'key' => (string) $n['notification_key'],
                'title' => (string) $n['title'],
                'message' => (string) $n['message'],
                'severity' => (string) $n['severity'],
                'url' => self::link($n),
            ], $fresh),
        ]);
        exit;
    }

    public function read(): void
    {
        $key = trim((string) ($_POST['notification_key'] ?? ''));
        if ($key !== '') {
            Notifier::markRead((int) \current_user_id(), \current_role(), $key);
        }

        // Opening an update in its window reads it without leaving the page.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'unread' => \unread_notification_count()]);
            exit;
        }

        $this->back((string) ($_POST['redirect'] ?? ''));
    }

    public function readAll(): void
    {
        $cleared = Notifier::markAllRead((int) \current_user_id(), \current_role());
        Flash::success($cleared === 0 ? 'You had no unread updates.' : sprintf('%d update(s) marked as read.', $cleared));

        $this->back((string) ($_POST['redirect'] ?? ''));
    }

    /** The page an update opens: the record itself when it names one. */
    public static function link(array $notification): string
    {
        $route = (string) ($notification['link_route'] ?? '');
        if ($route === '' || !\can_view($route === 'my-trips' ? 'dashboard' : $route)) {
            return '';
        }
        $ref = trim((string) ($notification['entity_id'] ?? ''));

        return match (true) {
            $ref === '' || $route === 'my-trips' => \url($route),
            ctype_digit($ref) => \url([$route, $ref]),
            default => \url($route, ['q' => $ref]),
        };
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
