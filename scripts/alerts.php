<?php

declare(strict_types=1);

/**
 * Raises the warnings that depend on the date rather than on anybody's action.
 *
 * A licence expiring, an insurance certificate running out, a service falling
 * due, stock under its minimum, an invoice going past its due date: none of
 * these happen because somebody pressed a button, they happen because a day
 * passed. Until now they were only noticed when somebody signed in, so a
 * company that took a week off came back to paperwork that had already lapsed.
 *
 * Run it once a day, from Task Scheduler on Windows or cron elsewhere:
 *
 *   php scripts/alerts.php
 *
 * It is safe to run as often as you like. Each warning is raised once per
 * expiry date per role, so running it hourly does not send anybody the same
 * message twice.
 *
 * Mail is queued, not sent, unless --send is passed — on a schedule you would
 * normally run this and then scripts/mail.php, which drains the queue and
 * retries anything the network refused.
 */

require __DIR__ . '/../bootstrap.php';

use Core\Database;
use Models\Notifier;
use Support\Mailer;

$send = in_array('--send', array_slice($argv, 1), true);

$pdo = Database::connection();
$before = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();

// Alerts are raised for whoever holds the role, so this runs as nobody in
// particular; the notifier does not read the session.
Notifier::refreshOperationalAlerts();

// Approvals: reminders half way through the time given, escalation when it runs out.
$approvals = Models\Approvals::sweep();
if (array_sum($approvals) > 0) {
    printf("Approvals: %d opened, %d reminded, %d escalated.\n", $approvals['opened'], $approvals['reminded'], $approvals['escalated']);
}

// The ERI specification's clock (sections 7, 9, 16 and 19).
// Requests waiting at a step are raised again until they are handled, and the
// backup is told once the backup time has passed.
$reminded = Models\Approver::sweep();
// A trip past the maximum days back from the turnaround point.
$late = Models\TripLife::lateAlerts();
// Urgent driver support left unhandled goes to the backup.
$escalated = Models\DriverSupport::escalate();
// A driver on a trip whose phone has not been heard from.
$unreachable = Models\DriverWatch::sweep();
// A truck that reached its service gets a draft service request (F3).
$services = Models\Garage::raiseDueServices();
if ($reminded + $late + $escalated + $unreachable + $services > 0) {
    printf("Requests reminded: %d. Late trips: %d. Support escalated: %d. Drivers unreachable: %d. Service requests raised: %d.\n", $reminded, $late, $escalated, $unreachable, $services);
}
// WhatsApp messages waiting (the provider may have been unreachable).
$whatsApp = Support\WhatsApp::flush(100);
if ($whatsApp > 0) {
    printf("WhatsApp: %d sent.\n", $whatsApp);
}

$raised = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() - $before;

if ($raised === 0) {
    echo "Nothing new to warn about. Everything in date.\n";
} else {
    printf("%d new warning(s):\n\n", $raised);

    $statement = $pdo->prepare(
        'SELECT title, message, role_key, severity
           FROM notifications
          ORDER BY id DESC
          LIMIT ' . $raised
    );
    $statement->execute();

    foreach (array_reverse($statement->fetchAll(PDO::FETCH_ASSOC)) as $row) {
        printf("  [%-7s] %-26s %s\n", $row['severity'], $row['role_key'], $row['message']);
    }
}

$waiting = (int) $pdo->query("SELECT COUNT(*) FROM email_outbox WHERE status IN ('queued', 'skipped')")->fetchColumn();

if ($waiting === 0) {
    exit(0);
}

if (!$send) {
    printf("\n%d message(s) are waiting in the outbox. Run scripts/mail.php to send them.\n", $waiting);
    exit(0);
}

// Sending from the console is refused by default so a test run can never post
// real mail. Asked for explicitly, it is turned on for this process only.
$GLOBALS['config']['mail']['send_in_cli'] = true;
printf("\nSending %d queued message(s)...\n", $waiting);
printf("%d sent.\n", Mailer::flush(100));
