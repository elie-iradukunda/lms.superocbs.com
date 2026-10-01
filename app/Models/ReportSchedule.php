<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * Reports that arrive by email on a schedule: the daily fuel report to the
 * fleet manager, the weekly trip profitability to management, the monthly
 * expense summary to finance.
 *
 * Each subscription names a report, how often, and who receives it (addresses
 * and/or whole roles). scripts/reports.php runs every morning from cron and
 * sends whatever is due; the "Send now" button sends one immediately.
 *
 * The report is set out as a table inside the email itself, so it reads on a
 * phone without opening anything, with a button to the full report.
 */
final class ReportSchedule
{
    public const FREQUENCIES = ['daily' => 'Daily (yesterday)', 'weekly' => 'Weekly (last 7 days)', 'monthly' => 'Monthly (last month)'];

    private const MAX_ROWS = 60;

    /**
     * Sends every active subscription that is due (or all of them with $force).
     *
     * @return list<array{id: int, report: string, sent: int, status: string}>
     */
    public static function runDue(bool $force = false): array
    {
        $rows = Database::connection()->query(
            'SELECT * FROM report_subscriptions WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            if (!$force && !self::isDue($row)) {
                continue;
            }
            $results[] = self::send((int) $row['id']);
        }

        return $results;
    }

    /** @return array{id: int, report: string, sent: int, status: string} */
    public static function send(int $id): array
    {
        $db = Database::connection();
        $statement = $db->prepare('SELECT * FROM report_subscriptions WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        $subscription = $statement->fetch(PDO::FETCH_ASSOC);
        if ($subscription === false) {
            return ['id' => $id, 'report' => '', 'sent' => 0, 'status' => 'That schedule no longer exists.'];
        }

        $key = (string) $subscription['report_key'];
        if (!ReportData::exists($key)) {
            return self::finish($id, $key, 0, 'Unknown report: ' . $key);
        }

        [$from, $to] = self::period((string) $subscription['frequency']);
        $report = ReportData::run($key, $from, $to);
        $recipients = self::recipients($subscription);

        if ($recipients === []) {
            return self::finish($id, $key, 0, 'No recipient: add an email address or a role.');
        }

        $columns = array_map(static fn (array $c): string => (string) $c['label'], $report['columns']);
        $numeric = array_keys(array_filter($report['columns'], static fn (array $c): bool => in_array($c['type'] ?? '', ['number', 'money', 'decimal', 'percent'], true)));
        $rows = [];
        foreach (array_slice($report['rows'], 0, self::MAX_ROWS) as $row) {
            $line = [];
            foreach ($report['columns'] as $column => $spec) {
                $line[$column] = self::format($row[$column] ?? null, (string) ($spec['type'] ?? 'text'));
            }
            $rows[] = $line;
        }

        $period = $from === $to ? date('d M Y', strtotime($from)) : date('d M Y', strtotime($from)) . ' to ' . date('d M Y', strtotime($to));
        $truncated = count($report['rows']) > self::MAX_ROWS;
        $sent = 0;

        foreach ($recipients as $email => $name) {
            $result = Mailer::send([
                'key' => sprintf('report-%d-%s-%s-%s', $id, $to, md5($email), $subscription['frequency']),
                'category' => 'notification',
                'to' => $email,
                'to_name' => $name,
                'subject' => sprintf('%s: %s', $report['label'], $period),
                'heading' => $report['label'],
                'lines' => array_filter([
                    sprintf('Your %s report for %s.', strtolower((string) $subscription['frequency']), $period),
                    $report['description'] ?? '',
                    $rows === [] ? 'There was no activity in this period.' : '',
                    $truncated ? sprintf('The first %d of %d rows are shown; open the report for the rest.', self::MAX_ROWS, count($report['rows'])) : '',
                ]),
                'items' => $rows === [] ? [] : ['columns' => $columns, 'rows' => $rows, 'numeric' => $numeric],
                'action' => ['label' => 'Open the full report', 'path' => 'reports/view/' . $key . '?from=' . $from . '&to=' . $to],
                'entity_type' => 'report_emails',
                'entity_id' => (string) $id,
            ]);
            if ($result['queued']) {
                $sent++;
            }
        }

        return self::finish($id, $key, $sent, sprintf('%d email(s) for %s queued%s.', $sent, $period, Mailer::enabled() ? '' : ' (email is switched off, so they wait in the outbox)'));
    }

    /** Is this subscription due today? */
    public static function isDue(array $row): bool
    {
        $last = $row['last_sent_at'] ?? null;
        if ($last === null || $last === '') {
            return true;
        }

        $lastTime = strtotime((string) $last);

        return match ((string) $row['frequency']) {
            'weekly' => $lastTime <= strtotime('-7 days', strtotime(date('Y-m-d 23:59:59'))),
            'monthly' => date('Y-m', $lastTime) < date('Y-m'),
            default => date('Y-m-d', $lastTime) < date('Y-m-d'),
        };
    }

    /** @return array{0: string, 1: string} */
    public static function period(string $frequency): array
    {
        return match ($frequency) {
            'weekly' => [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-1 day'))],
            'monthly' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
            default => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
        };
    }

    /** @return array<string, string> email => name */
    private static function recipients(array $subscription): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', (string) ($subscription['recipients'] ?? '')) ?: [] as $email) {
            $email = trim($email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $out[strtolower($email)] = $email;
            }
        }

        $teams = [
            'to_finance' => 'finance', 'to_management' => 'management', 'to_fleet' => 'fleet_manager',
            'to_logistics' => 'logistics_manager', 'to_warehouse' => 'warehouse_manager', 'to_admin' => 'super_admin',
        ];
        $roles = [];
        foreach ($teams as $column => $roleKey) {
            if (!empty($subscription[$column])) {
                $roles[] = $roleKey;
            }
        }
        if ($roles !== []) {
            $placeholders = implode(',', array_fill(0, count($roles), '?'));
            $statement = Database::connection()->prepare(
                "SELECT u.email, u.full_name FROM users u INNER JOIN roles r ON r.id = u.role_id
                  WHERE r.role_key IN ({$placeholders}) AND u.status = 'active' AND u.deleted_at IS NULL AND u.notify_by_email = 1"
            );
            $statement->execute(array_values($roles));
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $user) {
                $out[strtolower((string) $user['email'])] = (string) $user['full_name'];
            }
        }

        return $out;
    }

    private static function format(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => number_format((float) $value, 0),
            'decimal' => number_format((float) $value, 2),
            'number' => number_format((float) $value),
            'percent' => number_format((float) $value, 1) . '%',
            'badge', 'label' => ucwords(str_replace('_', ' ', (string) $value)),
            default => (string) $value,
        };
    }

    /** @return array{id: int, report: string, sent: int, status: string} */
    private static function finish(int $id, string $key, int $sent, string $status): array
    {
        // Only a run that queued something counts as sent. A failed run keeps the
        // old stamp, so tomorrow's cron tries again instead of waiting a period.
        if ($sent > 0) {
            Database::connection()
                ->prepare('UPDATE report_subscriptions SET last_sent_at = ?, last_status = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s'), mb_substr($status, 0, 255), $id]);
        } else {
            Database::connection()
                ->prepare('UPDATE report_subscriptions SET last_status = ? WHERE id = ?')
                ->execute([mb_substr($status, 0, 255), $id]);
        }

        AuditLog::record('report.emailed', 'report_emails', (string) $id, null, ['report' => $key, 'sent' => $sent]);

        return ['id' => $id, 'report' => $key, 'sent' => $sent, 'status' => $status];
    }
}
