<?php

declare(strict_types=1);

/**
 * Sends the scheduled report emails that are due.
 *
 * Put it on cron once a day, early in the morning (cPanel → Cron Jobs):
 *
 *   0 7 * * *  cd /home/ACCOUNT/lms && /usr/local/bin/php scripts/reports.php
 *
 *   php scripts/reports.php          send what is due today
 *   php scripts/reports.php --force  send every active schedule now
 *   php scripts/reports.php --list   show the schedules, send nothing
 *
 * Daily schedules cover yesterday, weekly ones the last seven days, monthly ones
 * the previous calendar month.
 */

require __DIR__ . '/../bootstrap.php';

use Core\Database;
use Models\ReportSchedule;

$arguments = array_slice($argv, 1);

if (in_array('--list', $arguments, true)) {
    $rows = Database::connection()->query('SELECT * FROM report_subscriptions WHERE deleted_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        echo "No report schedules yet. Add them under Reports → Report emails.\n";
        exit(0);
    }
    foreach ($rows as $row) {
        printf("  #%-3d %-22s %-8s %-6s due:%-3s last:%s\n", $row['id'], $row['report_key'], $row['frequency'],
            $row['is_active'] ? 'on' : 'off', ReportSchedule::isDue($row) ? 'yes' : 'no', $row['last_sent_at'] ?? 'never');
    }
    exit(0);
}

// Report emails are the one thing this script exists to send, so it is allowed
// to post from the console for this process only. The .env is not touched.
$GLOBALS['config']['mail']['send_in_cli'] = true;

$results = ReportSchedule::runDue(in_array('--force', $arguments, true));

if ($results === []) {
    echo "Nothing is due today.\n";
    exit(0);
}

foreach ($results as $result) {
    printf("  #%-3d %-22s %s\n", $result['id'], $result['report'], $result['status']);
}

exit(0);
