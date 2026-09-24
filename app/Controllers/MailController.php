<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\AuditLog;
use Models\Settings;
use PDO;
use Support\Mailer;

/**
 * The email outbox.
 *
 * Every message the system meant to send is here, with what happened to it. An
 * administrator can see at a glance whether the driver was actually told, retry
 * anything the provider refused, and send a test message without waiting for a
 * real event to fire one.
 */
final class MailController
{
    private const PER_PAGE = 30;

    public function index(): void
    {
        [$where, $parameters] = $this->conditions();

        $total = (int) $this->scalar("SELECT COUNT(*) FROM email_outbox WHERE {$where}", $parameters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
        $offset = ($page - 1) * self::PER_PAGE;

        $statement = Database::connection()->prepare(
            "SELECT * FROM email_outbox WHERE {$where} ORDER BY id DESC LIMIT " . self::PER_PAGE . " OFFSET {$offset}"
        );
        $statement->execute($parameters);

        $counts = Database::connection()
            ->query('SELECT status, COUNT(*) n FROM email_outbox GROUP BY status')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        \view('admin/email', [
            'title' => 'Email outbox',
            'messages' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => $counts,
            'search' => (string) ($_GET['q'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'configured' => \config('mail.api_key', '') !== '',
            'switchedOn' => Settings::int('email_enabled', 1) === 1,
            'redirect' => (string) \config('mail.redirect_all_to', ''),
            'canSend' => \can_edit('email'),
        ]);
    }

    /** Shows one message exactly as it was built, so a layout problem is visible. */
    public function show(array $params): void
    {
        $statement = Database::connection()->prepare('SELECT * FROM email_outbox WHERE id = ? LIMIT 1');
        $statement->execute([(int) ($params['id'] ?? 0)]);
        $message = $statement->fetch();

        if ($message === false) {
            Flash::error('That message is not in the outbox.');
            $this->redirect(\url('email'));
        }

        \view('admin/email_message', ['title' => (string) $message['subject'], 'message' => $message]);
    }

    /** The rendered HTML on its own, for the preview frame. */
    public function preview(array $params): void
    {
        $statement = Database::connection()->prepare('SELECT subject, body_html FROM email_outbox WHERE id = ? LIMIT 1');
        $statement->execute([(int) ($params['id'] ?? 0)]);
        $message = $statement->fetch();

        header('Content-Type: text/html; charset=utf-8');
        // The stored body is the whole document, and it never reaches the app's
        // own pages, so it is echoed as it would arrive in an inbox.
        echo $message === false ? '<p>Not found.</p>' : $message['body_html'];
        exit;
    }

    public function retry(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (Mailer::retry($id)) {
            AuditLog::record('email.retried', 'email', (string) $id);
            Flash::success('The message was sent.');
        } else {
            Flash::error('It still could not be sent. The reason is on the message.');
        }

        $this->redirect(\url(['email', $id]));
    }

    public function flush(): void
    {
        $sent = Mailer::flush();
        AuditLog::record('email.flushed', 'email', null, null, ['sent' => $sent]);

        Flash::success($sent === 0
            ? 'Nothing was waiting, or email is switched off.'
            : sprintf('%d message(s) sent.', $sent));

        $this->redirect(\url('email'));
    }

    /** Proves the whole path works without waiting for a real event. */
    public function test(): void
    {
        $address = trim((string) ($_POST['to'] ?? \current_user_email()));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            Flash::error('That is not a valid email address.');
            $this->redirect(\url('email'));
        }

        $result = Mailer::send([
            'key' => 'test-' . bin2hex(random_bytes(6)),
            'category' => 'test',
            'to' => $address,
            'to_name' => \current_user_name(),
            'user_id' => \current_user_id(),
            'subject' => 'LMS test message',
            'heading' => 'Email is working',
            'lines' => [
                sprintf('This is a test sent by %s from the email outbox.', \current_user_name()),
                'If you are reading it, the sending address, the API key and the template are all in order.',
            ],
            'facts' => [
                'Sent at' => date('d M Y H:i'),
                'From' => \config('mail.from_name', '') . ' <' . \config('mail.from_email', '') . '>',
            ],
            'action' => ['label' => 'Open LMS', 'url' => Mailer::link('dashboard')],
        ]);

        AuditLog::record('email.tested', 'email', $address, null, ['sent' => $result['sent']]);

        $result['sent']
            ? Flash::success(sprintf('Test message sent to %s.', $address))
            : Flash::error('The test message was queued but not sent: ' . $result['reason']);

        $this->redirect(\url('email'));
    }

    // --------------------------------------------------------------- helpers

    private function conditions(): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(subject LIKE ? OR to_email LIKE ? OR entity_id LIKE ? OR error LIKE ?)';
            $like = '%' . $search . '%';
            array_push($parameters, $like, $like, $like, $like);
        }

        $status = (string) ($_GET['status'] ?? '');
        if (in_array($status, ['queued', 'sent', 'failed', 'skipped'], true)) {
            $conditions[] = 'status = ?';
            $parameters[] = $status;
        }

        $category = (string) ($_GET['category'] ?? '');
        if (in_array($category, ['notification', 'alert', 'password_reset', 'new_account', 'invoice', 'manual', 'test'], true)) {
            $conditions[] = 'category = ?';
            $parameters[] = $category;
        }

        return [implode(' AND ', $conditions), $parameters];
    }

    private function scalar(string $sql, array $parameters): mixed
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
