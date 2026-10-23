<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Who signs each step of the chain (section 9), read from
 * approval_step_settings: a role, optionally one named person, an optional
 * backup who steps in after a waiting time, and delegations for set dates.
 * Admin keeps the system safe but takes no part in requests or payments.
 */
final class Approver
{
    /** @var array<string, ?array> */
    private static array $cache = [];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /** The setting for a step: the company's own, else the group's. */
    public static function setting(int $companyId, string $step): ?array
    {
        $key = $companyId . '|' . $step;
        if (!array_key_exists($key, self::$cache)) {
            $statement = self::db()->prepare(
                'SELECT * FROM approval_step_settings WHERE step = ? AND is_active = 1 AND (company_id = ? OR company_id IS NULL)
                  ORDER BY company_id IS NULL, id LIMIT 1'
            );
            $statement->execute([$step, $companyId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            self::$cache[$key] = $row === false ? null : $row;
        }

        return self::$cache[$key];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function roleLabel(int $companyId, string $step): string
    {
        $setting = self::setting($companyId, $step);
        if ($setting === null) {
            return 'nobody (set the approvers in Administration)';
        }
        if (!empty($setting['main_user_id'])) {
            $statement = self::db()->prepare('SELECT full_name FROM users WHERE id = ?');
            $statement->execute([(int) $setting['main_user_id']]);

            return (string) ($statement->fetchColumn() ?: \role_label((string) $setting['role_key']));
        }

        return 'the ' . \role_label((string) $setting['role_key']);
    }

    /**
     * May this person sign the step? false when not; null when in their own
     * right; the id of the approver they stand in for (backup or delegate).
     */
    public static function actingFor(int $companyId, string $step, int $userId, string $roleKey, string $waitingSince = ''): int|false|null
    {
        $setting = self::setting($companyId, $step);
        if ($setting === null || $userId <= 0) {
            return false;
        }

        $main = (int) ($setting['main_user_id'] ?? 0);
        $holdsRole = $roleKey === (string) $setting['role_key'] && self::belongsTo($userId, $companyId);

        if ($main > 0 ? $userId === $main : $holdsRole) {
            return null;
        }

        // The backup steps in once the request has waited long enough.
        $backup = (int) ($setting['backup_user_id'] ?? 0);
        $after = $setting['backup_after_hours'];
        if ($backup === $userId && $after !== null && $waitingSince !== ''
            && strtotime($waitingSince) <= time() - (int) $after * 3600) {
            return $main > 0 ? $main : 0;
        }

        // A delegation for today, from someone who signs this step.
        $statement = self::db()->prepare(
            'SELECT d.from_user_id, r.role_key FROM approval_delegations d
               INNER JOIN users u ON u.id = d.from_user_id
               INNER JOIN roles r ON r.id = u.role_id
              WHERE d.to_user_id = ? AND d.cancelled_at IS NULL AND CURDATE() BETWEEN d.starts_on AND d.ends_on'
        );
        $statement->execute([$userId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $delegation) {
            $from = (int) $delegation['from_user_id'];
            if ($main > 0 ? $from === $main : ((string) $delegation['role_key'] === (string) $setting['role_key'] && self::belongsTo($from, $companyId))) {
                return $from;
            }
        }

        return false;
    }

    /** A person signs for their own company, or for any when they see the whole group. */
    private static function belongsTo(int $userId, int $companyId): bool
    {
        $statement = self::db()->prepare(
            "SELECT u.company_id,
                    EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = u.role_id AND rp.permission_key = 'all_companies' AND rp.can_view = 1) AS sees_all
               FROM users u WHERE u.id = ?"
        );
        $statement->execute([$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        return (int) $row['sees_all'] === 1 || $row['company_id'] === null || (int) $row['company_id'] === $companyId;
    }

    /** Tells whoever signs the step (and their delegate today) that it is their turn. */
    public static function notify(int $companyId, string $step, string $title, string $message, int $requestId, bool $toBackup = false): void
    {
        $setting = self::setting($companyId, $step);
        if ($setting === null) {
            return;
        }

        Notifier::forCompany($companyId, static function () use ($setting, $title, $message, $requestId, $toBackup): void {
            $severity = $toBackup ? 'danger' : 'warning';
            if ($toBackup && !empty($setting['backup_user_id'])) {
                Notifier::toUser((int) $setting['backup_user_id'], $title . ' (as backup)', $message, 'form_requests', $severity, 'form_requests', (string) $requestId);

                return;
            }
            $main = (int) ($setting['main_user_id'] ?? 0);
            if ($main > 0) {
                Notifier::toUser($main, $title, $message, 'form_requests', $severity, 'form_requests', (string) $requestId);
                $delegates = [$main];
            } else {
                Notifier::toRole((string) $setting['role_key'], $title, $message, 'form_requests', $severity, 'form_requests', (string) $requestId);
                $statement = Database::connection()->prepare('SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.role_key = ? AND u.status = \'active\' AND u.deleted_at IS NULL');
                $statement->execute([(string) $setting['role_key']]);
                $delegates = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            }
            if ($delegates !== []) {
                $in = implode(',', $delegates);
                foreach (Database::connection()->query("SELECT DISTINCT to_user_id FROM approval_delegations WHERE from_user_id IN ({$in}) AND cancelled_at IS NULL AND CURDATE() BETWEEN starts_on AND ends_on")->fetchAll(PDO::FETCH_COLUMN) as $delegate) {
                    Notifier::toUser((int) $delegate, $title . ' (delegated to you)', $message, 'form_requests', $severity, 'form_requests', (string) $requestId);
                }
            }
        });
    }

    /**
     * Never stuck (section 9): a request waiting longer than the reminder time
     * is raised again, until it is handled; past the backup time, the backup
     * is told too. Run by the clock (scripts/alerts.php).
     */
    public static function sweep(): int
    {
        $count = 0;
        $rows = self::db()->query(
            "SELECT r.*, ft.chain, ft.goes_to_payment, ft.is_money FROM form_requests r INNER JOIN form_types ft ON ft.form_code = r.form_code
              WHERE r.deleted_at IS NULL AND r.status IN ('sent', 'verified', 'authorized', 'partly_paid') AND r.waiting_since IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $request) {
            $step = RequestForms::pendingStep($request);
            if ($step === null) {
                continue;
            }
            $setting = self::setting((int) $request['company_id'], $step);
            if ($setting === null) {
                continue;
            }
            $waited = time() - (int) strtotime((string) $request['waiting_since']);
            $since = (string) ($request['reminded_at'] ?: $request['waiting_since']);
            $sinceReminder = time() - (int) strtotime($since);
            $every = max(1, (int) $setting['reminder_hours']) * 3600;
            if ($sinceReminder < $every || Settings::int('approval_reminder_until_handled', 1) !== 1) {
                continue;
            }

            $hours = (int) floor($waited / 3600);
            $label = RequestForms::label($request);
            $message = sprintf('%s has been waiting %d hour%s for you: %s.', $label, $hours, $hours === 1 ? '' : 's', $request['title']);
            self::notify((int) $request['company_id'], $step, 'Reminder: ' . strtolower(RequestForms::STEP_LABELS[$step]), $message, (int) $request['id']);
            if (!empty($setting['backup_user_id']) && $setting['backup_after_hours'] !== null && $waited >= (int) $setting['backup_after_hours'] * 3600) {
                self::notify((int) $request['company_id'], $step, 'Waiting too long', $message, (int) $request['id'], true);
            }
            self::db()->prepare('UPDATE form_requests SET reminded_at = NOW() WHERE id = ?')->execute([(int) $request['id']]);
            $count++;
        }

        return $count;
    }
}
