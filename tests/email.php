<?php

declare(strict_types=1);

/**
 * Email delivery: that every message is written down before it is sent, that
 * the right people get it, that nobody gets it twice, and that a console run
 * never posts mail to real people.
 *
 * Nothing here actually sends: MAIL_SEND_IN_CLI is left unset, so messages are
 * recorded and marked skipped. That is the same guard that stops a migration or
 * a test suite mailing the company.
 */

require __DIR__ . '/support.php';

[$pdo, $root, $dbName] = test_database('logistics_mvc_email');

try {
    require __DIR__ . '/../bootstrap.php';

    $test = new TestRun('Email tests');
    $accounts = (new Models\UserRepository())->activeLoginAccounts();
    test_sign_in($accounts['super_admin'], 'super_admin', 1);

    $count = static fn (string $where = '1 = 1', array $p = []): int => (static function () use ($pdo, $where, $p): int {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM email_outbox WHERE {$where}");
        $statement->execute($p);
        return (int) $statement->fetchColumn();
    })();

    $latest = static function () use ($pdo): ?array {
        $row = $pdo->query('SELECT * FROM email_outbox ORDER BY id DESC LIMIT 1')->fetch();
        return $row === false ? null : $row;
    };

    // ------------------------------------------------------ the outbox itself
    $test->same(0, $count(), 'the outbox starts empty');

    $id = Support\Mailer::queue([
        'key' => 'unit-one',
        'to' => 'someone@example.rw',
        'to_name' => 'Someone',
        'subject' => 'First message',
        'heading' => 'First message',
        'lines' => ['A line of text.'],
        'facts' => ['Reference' => 'REF-1'],
        'action' => ['label' => 'Open it', 'path' => 'dashboard'],
    ]);
    $test->assert($id !== null, 'a message is written to the outbox');
    $test->same(1, $count(), 'exactly one row was written');

    $again = Support\Mailer::queue(['key' => 'unit-one', 'to' => 'someone@example.rw', 'subject' => 'First message']);
    $test->same(null, $again, 'the same key is not recorded twice');
    $test->same(1, $count(), 'and no second row appears');

    $test->same(null, Support\Mailer::queue(['key' => 'bad-address', 'to' => 'not-an-email', 'subject' => 'x']), 'an invalid address is refused');
    $test->same(null, Support\Mailer::queue(['key' => 'no-address', 'to' => '', 'subject' => 'x']), 'an empty address is refused');

    // -------------------------------------------------- what the letter says
    $message = $latest();
    $test->assert(str_contains((string) $message['body_html'], 'First message'), 'the heading reaches the HTML');
    $test->assert(str_contains((string) $message['body_html'], 'A line of text.'), 'the body text reaches the HTML');
    $test->assert(str_contains((string) $message['body_html'], 'REF-1'), 'the facts table reaches the HTML');
    $test->assert(str_contains((string) $message['body_html'], 'Open it'), 'the action button reaches the HTML');
    $test->assert(str_contains((string) $message['body_html'], strtoupper(Models\Settings::get('company_name'))) || str_contains((string) $message['body_html'], Models\Settings::get('company_name')), 'the company name is on the letter');
    $test->assert(str_contains((string) $message['body_text'], 'A line of text.'), 'a plain text version is stored as well');
    $test->assert(str_starts_with((string) $message['body_html'], '<!doctype html>'), 'the stored body is a whole document');

    // ------------------------------------------- a console run does not send
    $result = Support\Mailer::send([
        'key' => 'unit-two',
        'to' => 'someone@example.rw',
        'subject' => 'Second message',
        'lines' => ['Another line.'],
    ]);
    $test->assert($result['queued'], 'send() records the message');
    $test->assert(!$result['sent'], 'a console run does not post mail');
    $test->same('skipped', (string) $latest()['status'], 'and the row says it was not sent');
    $test->same(0, Support\Mailer::flush(), 'flushing on the console sends nothing either');

    // -------------------------------------------------------- the recipients
    $driverId = (int) $accounts['driver']['id'];
    $before = $count();

    Models\Notifier::toUser($driverId, 'Just for you', 'A message meant for one person.', 'trips', 'info', 'trips', 'TRP-1');
    $test->same($before + 1, $count(), 'a notification to one person queues one email');
    $test->same($driverId, (int) $latest()['user_id'], 'the recipient is recorded on the row');

    // In test mode every message is addressed to one mailbox, so the address to
    // expect is the redirect when one is set and the person's own otherwise.
    $redirect = trim((string) config('mail.redirect_all_to', ''));
    $test->same(
        $redirect !== '' ? $redirect : (string) $accounts['driver']['email'],
        (string) $latest()['to_email'],
        'and it is addressed to that person, or to the redirect while testing'
    );

    $financeUsers = (int) $pdo->query(
        "SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id
          WHERE r.role_key = 'finance' AND u.status = 'active' AND u.deleted_at IS NULL AND u.notify_by_email = 1"
    )->fetchColumn();

    $before = $count();
    Models\Notifier::toRole('finance', 'For the finance team', 'A message meant for a whole role.', 'expenses', 'info');
    $test->same($before + $financeUsers, $count(), 'a notification to a role queues one email per person in it');

    // ------------------------------------------------ opting out is respected
    $pdo->prepare('UPDATE users SET notify_by_email = 0 WHERE id = ?')->execute([$driverId]);
    $before = $count();
    Models\Notifier::toUser($driverId, 'Should not arrive', 'This person turned email off.', 'trips');
    $test->same($before, $count(), 'someone who has turned email off gets none');
    $pdo->prepare('UPDATE users SET notify_by_email = 1 WHERE id = ?')->execute([$driverId]);

    $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$driverId]);
    $before = $count();
    Models\Notifier::toUser($driverId, 'Should not arrive either', 'This account is inactive.', 'trips');
    $test->same($before, $count(), 'an inactive account gets none');
    $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$driverId]);

    // ------------------------------------------------- the severity threshold
    Models\Settings::put('email_min_severity', 'danger');
    $test->assert(!Support\Mailer::passesThreshold('info'), 'below the threshold an info message is not emailed');
    $test->assert(Support\Mailer::passesThreshold('danger'), 'at the threshold it is');

    $before = $count();
    Models\Notifier::toUser($driverId, 'Quiet update', 'Only worth the bell.', 'trips', 'info');
    $test->same($before, $count(), 'a quiet notification stays out of the inbox');

    Models\Notifier::toUser($driverId, 'Urgent update', 'Worth an email.', 'trips', 'danger');
    $test->same($before + 1, $count(), 'an urgent one is emailed');
    Models\Settings::put('email_min_severity', 'info');

    // ------------------------------------------------------- switched off
    Models\Settings::put('email_enabled', '0');
    $test->assert(!Support\Mailer::enabled(), 'turning the setting off turns email off');
    $before = $count();
    Models\Notifier::toUser($driverId, 'While off', 'Nothing should be queued.', 'trips', 'danger');
    $test->same($before, $count(), 'nothing is queued while email is off');
    Models\Settings::put('email_enabled', '1');

    // ------------------------------------------------- a real operational path
    // Approving an expense must tell the person who submitted it.
    $expense = $pdo->query("SELECT id, reference_code, submitted_by FROM expenses WHERE status = 'pending' AND submitted_by IS NOT NULL AND deleted_at IS NULL LIMIT 1")->fetch();
    if ($expense !== false) {
        $before = $count();
        $outcome = Models\Workflow::apply('expenses', (int) $expense['id'], 'approve');
        $test->assert($outcome['ok'], 'the expense was approved');
        $test->assert($count() > $before, 'approving an expense queues an email');
        $test->same(
            (string) $expense['reference_code'],
            (string) $latest()['entity_id'],
            'and the email carries the reference of what it is about'
        );
    }

    // A password reset must email the link rather than only showing it.
    $user = (new Models\UserRepository())->findActiveByEmail('emmanuel@itec.rw');
    $token = (new Models\UserRepository())->createResetToken((int) $user['id']);
    $before = $count();
    Support\Mailer::send([
        'key' => 'reset-' . substr(hash('sha256', $token), 0, 40),
        'category' => 'password_reset',
        'to' => (string) $user['email'],
        'subject' => 'Reset your LMS password',
        'heading' => 'Reset your password',
        'lines' => ['Use the button to choose a new password.'],
        'action' => ['label' => 'Choose a new password', 'url' => Support\Mailer::link('reset-password/' . $token)],
    ]);
    $test->same($before + 1, $count(), 'a password reset queues a message');
    $test->same('password_reset', (string) $latest()['category'], 'recorded under the right category');
    $test->assert(str_contains((string) $latest()['body_html'], $token), 'the reset link carries the single-use token');

    // ----------------------------------------------------------- redirection
    // While testing, everything is addressed to one mailbox and says so.
    if (trim((string) config('mail.redirect_all_to', '')) !== '') {
        $test->same(
            trim((string) config('mail.redirect_all_to')),
            (string) $latest()['to_email'],
            'test mode redirects every message to one address'
        );
        $test->assert(str_contains((string) $latest()['body_html'], 'Test mode'), 'and the letter says it was redirected');
    }

    // ------------------------------------------------------------- integrity
    $test->same(
        0,
        (int) $pdo->query('SELECT COUNT(*) FROM (SELECT message_key FROM email_outbox GROUP BY message_key HAVING COUNT(*) > 1) d')->fetchColumn(),
        'no message key was used twice'
    );
    $test->same(
        0,
        (int) $pdo->query("SELECT COUNT(*) FROM email_outbox WHERE to_email = '' OR subject = '' OR body_html = ''")->fetchColumn(),
        'every queued message has an address, a subject and a body'
    );

    $test->finish();
} finally {
    $pdo = null;
    $root->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}
