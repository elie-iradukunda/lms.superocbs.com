<?php

declare(strict_types=1);

/**
 * Sends whatever is still sitting in the outbox.
 *
 * Every message is written to the outbox first and handed to the provider
 * second, so nothing is lost when the link is down — but until now nothing
 * picked the queue back up either. A blip while a trip was being dispatched
 * left the driver's sheet and the customers' letters sitting there, and the
 * only way out was for an administrator to open the outbox page and press
 * retry on each one.
 *
 * This is that page, as a command. Run it after a network problem, or leave it
 * on a schedule so the queue drains by itself:
 *
 *   php scripts/mail.php            send what is waiting
 *   php scripts/mail.php --list     show what is waiting, send nothing
 *   php scripts/mail.php --limit=10 send at most ten
 *
 * Sending from the console is normally refused, because a test run or a
 * migration must never post real mail to real customers. This script is the one
 * place that is deliberately allowed, so it says so on the way past.
 */

require __DIR__ . '/../bootstrap.php';

use Core\Database;
use Support\Mailer;

$arguments = array_slice($argv, 1);
$listOnly = in_array('--list', $arguments, true);
$limit = 50;

foreach ($arguments as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches) === 1) {
        $limit = max(1, (int) $matches[1]);
    }
}

$pdo = Database::connection();
$waiting = $pdo->query(
    "SELECT id, message_key, to_email, subject, status, attempts, error
       FROM email_outbox
      WHERE status IN ('queued', 'skipped')
      ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

if ($waiting === []) {
    echo "The outbox is empty. Nothing is waiting to go out.\n";
    exit(0);
}

printf("%d message(s) waiting:\n\n", count($waiting));
foreach ($waiting as $row) {
    printf("  %-5s %-34s %s\n", '#' . $row['id'], $row['to_email'], $row['subject']);
    if (trim((string) $row['error']) !== '') {
        printf("        last attempt (%d): %s\n", (int) $row['attempts'], $row['error']);
    }
}

if ($listOnly) {
    echo "\nNothing was sent. Run without --list to send them.\n";
    exit(0);
}

// Whether mail works at all is not a single config key: it needs an API key and
// the setting an administrator can switch off. Mailer is what knows about both.
if (config('mail.api_key', '') === '') {
    echo "\nNo API key is configured (RESEND_API_KEY). Nothing was sent.\n";
    exit(1);
}

if (!Mailer::enabled()) {
    echo "\nEmail is switched off under Settings. Nothing was sent.\n";
    exit(1);
}

// Sending from the console is refused by default so that a test run can never
// post real mail. Here it is the whole point, so it is turned on for this
// process only — the .env file is not touched.
$GLOBALS['config']['mail']['send_in_cli'] = true;

echo "\nSending...\n";
$sent = Mailer::flush($limit);

$left = (int) $pdo->query("SELECT COUNT(*) FROM email_outbox WHERE status IN ('queued', 'skipped')")->fetchColumn();

printf("\n%d sent, %d still waiting.\n", $sent, $left);

if ($left > 0) {
    echo "Run it again once the connection is back. Nothing is lost in the meantime.\n";

    foreach ($pdo->query(
        "SELECT id, to_email, error FROM email_outbox
          WHERE status IN ('queued', 'skipped') ORDER BY id LIMIT 5"
    ) as $row) {
        printf("  #%-4s %-34s %s\n", $row['id'], $row['to_email'], $row['error']);
    }
}

exit($left > 0 ? 1 : 0);
