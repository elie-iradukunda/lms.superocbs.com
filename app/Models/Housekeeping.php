<?php

declare(strict_types=1);

namespace Models;

/**
 * The work the clock does: approval reminders and escalations, clearance
 * files standing still, trucks gone quiet, papers expiring.
 *
 * scripts/alerts.php runs it on a schedule where the server has one. Where it
 * has not (shared hosting without cron), the pages people already have open
 * ask for new updates every minute, and that request runs it at most once
 * every few minutes for everybody.
 */
final class Housekeeping
{
    private const EVERY_SECONDS = 300;

    /** @return array<string, int>|null what was done, or null when it was not yet time */
    public static function tick(bool $force = false): ?array
    {
        // The storage folder first; where the host does not let the site write
        // there, the system's temporary folder, so the clock still runs.
        $handle = @fopen(dirname(__DIR__, 2) . '/storage/housekeeping.stamp', 'c+')
            ?: @fopen(rtrim(sys_get_temp_dir(), '/\\') . '/lms-housekeeping-' . md5(__DIR__) . '.stamp', 'c+');
        if ($handle === false) {
            return $force ? self::run() : null;
        }

        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return null;
            }
            $last = (int) trim((string) stream_get_contents($handle));
            if (!$force && $last > 0 && time() - $last < self::EVERY_SECONDS) {
                return null;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) time());
            fflush($handle);

            return self::run();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<string, int> */
    public static function run(): array
    {
        $was = \Support\Mailer::defer(true);
        try {
            $approvals = Approvals::sweep();
            Notifier::refreshOperationalAlerts();
        } finally {
            \Support\Mailer::defer($was);
        }
        // What the clock raised, and what sign-ins held back, goes out now in a small batch.
        $approvals['emailed'] = \Support\Mailer::flushDeferred(20);

        return $approvals;
    }
}
