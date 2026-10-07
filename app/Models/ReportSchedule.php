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
 * The email carries the headline figures, per currency, so it reads on a phone;
 * the full report travels with it as a PDF and an Excel workbook, the same
 * files the Export buttons produce.
 */
final class ReportSchedule
{
    public const FREQUENCIES = ['daily' => 'Daily (yesterday)', 'weekly' => 'Weekly (last 7 days)', 'monthly' => 'Monthly (last month)'];


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

        $period = $from === $to ? \human_date($from) : \human_date($from) . ' to ' . \human_date($to);
        $count = count($report['rows']);
        $summary = self::summary($report);
        $files = $count > 0 ? self::files($report, $id) : [];
        $sent = 0;

        foreach ($recipients as $email => $name) {
            $result = Mailer::send([
                'key' => sprintf('report-%d-%s-%s-%s', $id, $to, md5($email), $subscription['frequency']),
                'category' => 'notification',
                'to' => $email,
                'to_name' => $name,
                'subject' => sprintf('%s: %s', $report['label'], $period),
                'heading' => $report['label'],
                'preheader' => $count === 0 ? 'No activity in this period.' : sprintf('%d line%s for %s.', $count, $count === 1 ? '' : 's', $period),
                'lines' => array_filter([
                    sprintf('Dear %s,', $name),
                    sprintf('Your %s report for %s.', strtolower((string) $subscription['frequency']), $period),
                    $report['description'] ?? '',
                    $count === 0
                        ? 'There was no activity in this period.'
                        : sprintf('It has %d line%s. The full report is attached as a PDF and an Excel workbook.', $count, $count === 1 ? '' : 's'),
                ]),
                'items' => $summary === [] ? [] : [
                    'title' => 'Totals',
                    'columns' => ['detail' => 'Detail', 'value' => 'Value'],
                    'numeric' => [],
                    'rows' => $summary,
                ],
                'attachments' => $files,
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

    /**
     * The report's totals, one line per currency: money is never added across
     * currencies.
     *
     * @return list<array{detail: string, value: string}>
     */
    private static function summary(array $report): array
    {
        $lines = [];
        foreach ($report['money_totals'] ?? [] as $currency => $totals) {
            foreach ($totals as $column => $amount) {
                $label = (string) ($report['columns'][$column]['label'] ?? $column);
                $lines[] = ['detail' => $label . ' (' . $currency . ')', 'value' => Currency::format((float) $amount, (string) $currency)];
            }
        }
        foreach ($report['totals'] ?? [] as $column => $value) {
            $spec = $report['columns'][$column] ?? null;
            if ($spec === null || ($spec['type'] ?? '') === 'money' || !is_numeric($value)) {
                continue;
            }
            $lines[] = ['detail' => (string) $spec['label'], 'value' => self::format($value, (string) ($spec['type'] ?? 'number'))];
        }

        return $lines;
    }

    /**
     * The PDF and the workbook, written under uploads so the outbox can send
     * them again on a retry.
     *
     * @return list<array{filename: string, path: string}>
     */
    private static function files(array $report, int $id): array
    {
        $doc = \Controllers\ReportController::document($report);
        $folder = 'reports/' . date('Y-m');
        $directory = Mailer::uploadsRoot() . '/' . $folder;
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (['pdf' => static fn (): string => \Support\PdfWriter::build($doc), 'xlsx' => static fn (): string => \Support\XlsxWriter::build($doc)] as $extension => $build) {
            try {
                $name = \Support\Report::filename($doc, $extension);
                $stored = $id . '-' . date('His') . '-' . $name;
                if (@file_put_contents($directory . '/' . $stored, $build()) !== false) {
                    $files[] = ['filename' => $name, 'path' => $folder . '/' . $stored];
                }
            } catch (\Throwable) {
                // The email still goes with the totals and the link.
            }
        }

        return $files;
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
            'money' => number_format((float) $value, 2),
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
