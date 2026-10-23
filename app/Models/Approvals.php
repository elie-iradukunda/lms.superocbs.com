<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Approvals by department, in order.
 *
 * A record that needs signing (a transport request, a trip about to leave, a
 * shipping paper, an expense, a purchase, a supplier bill, an invoice) gets a
 * round of steps from `approval_policies`: Operations first, then Finance, then
 * Management from a certain amount, and so on. Each step belongs to a role.
 * The record does not move until every step is signed; each person is told
 * when it is their turn, reminded half way through the time they were given,
 * and their manager is told when that time runs out.
 *
 * The workflow button stays the same one people already press ("Approve",
 * "Verify"): pressing it signs the current step, and the last signature is
 * what actually moves the record. A rejection at any step ends the round.
 *
 * Money is never compared across currencies: a threshold belongs to one
 * currency and only applies to records in that currency.
 */
final class Approvals
{
    /**
     * What each module is called in the tables, which column is its amount,
     * its currency, and the person who raised it.
     */
    private const SUBJECTS = [
        'requests' => ['table' => 'transport_requests', 'amount' => 'quoted_amount', 'currency' => 'currency', 'requester' => 'requester_id'],
        'shipment_documents' => ['table' => 'shipment_documents', 'amount' => null, 'currency' => null, 'requester' => 'uploaded_by'],
        'clearance_documents' => ['table' => 'border_documents', 'amount' => null, 'currency' => null, 'requester' => 'uploaded_by'],
        'expenses' => ['table' => 'expenses', 'amount' => 'amount', 'currency' => 'currency', 'requester' => 'submitted_by'],
        'procurement' => ['table' => 'purchase_requests', 'amount' => 'amount', 'currency' => 'currency', 'requester' => 'requested_by'],
        'supplier_bills' => ['table' => 'supplier_bills', 'amount' => 'total_amount', 'currency' => 'currency', 'requester' => null],
        'invoices' => ['table' => 'invoices', 'amount' => 'total_amount', 'currency' => 'currency', 'requester' => null],
    ];

    /** The action each module is signed through. */
    public const ACTIONS = [
        'requests' => 'approve', 'shipment_documents' => 'verify', 'clearance_documents' => 'verify',
        'expenses' => 'approve', 'procurement' => 'approve', 'supplier_bills' => 'approve', 'invoices' => 'approve',
    ];

    public const CONDITIONS = ['always' => 'Always', 'cross_border' => 'Only when the route crosses a border'];

    /** @var array<string, list<array<string, mixed>>> */
    private static array $policyCache = [];

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function supports(string $module): bool
    {
        return isset(self::SUBJECTS[$module]);
    }

    /**
     * module => label, for the rules form. Written out rather than read from
     * Schema, because Schema asks for this while it is being built.
     *
     * @return array<string, string>
     */
    public static function moduleOptions(): array
    {
        return [
            'requests' => 'Transport requests', 'shipment_documents' => 'Shipment papers',
            'clearance_documents' => 'Customs papers', 'expenses' => 'Trip expenses', 'procurement' => 'Purchase requests',
            'supplier_bills' => 'Supplier bills', 'invoices' => 'Invoices',
        ];
    }

    /** Is there at least one active rule for this module and action? */
    public static function has(string $module, string $action): bool
    {
        return self::policies($module, $action) !== [];
    }

    /** @return list<array<string, mixed>> */
    private static function policies(string $module, string $action): array
    {
        $key = $module . '|' . $action;
        if (!array_key_exists($key, self::$policyCache)) {
            try {
                $statement = self::db()->prepare('SELECT * FROM approval_policies WHERE module = ? AND action = ? AND is_active = 1 ORDER BY step_no, id');
                $statement->execute([$module, $action]);
                self::$policyCache[$key] = $statement->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                // Before the migration: no rules, everything works as it did.
                self::$policyCache[$key] = [];
            }
        }

        return self::$policyCache[$key];
    }

    public static function flush(): void
    {
        self::$policyCache = [];
    }

    /** The record as the approvals see it. */
    private static function load(string $module, int $id): ?array
    {
        if (!self::supports($module)) {
            return null;
        }
        $statement = self::db()->prepare(sprintf('SELECT * FROM `%s` WHERE id = ? LIMIT 1', self::SUBJECTS[$module]['table']));
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array{0: ?float, 1: ?string} amount and currency */
    private static function money(string $module, array $record): array
    {
        $subject = self::SUBJECTS[$module];
        $amount = $subject['amount'] !== null && isset($record[$subject['amount']]) && $record[$subject['amount']] !== null ? round((float) $record[$subject['amount']], 2) : null;
        $currency = $subject['currency'] !== null ? strtoupper(trim((string) ($record[$subject['currency']] ?? ''))) : '';

        return [$amount, $currency !== '' ? $currency : null];
    }

    private static function requester(string $module, array $record): ?int
    {
        $column = self::SUBJECTS[$module]['requester'];
        $value = $column !== null ? ($record[$column] ?? null) : null;

        return $value !== null && (int) $value > 0 ? (int) $value : null;
    }

    private static function code(string $module, array $record): string
    {
        $column = Schema::has($module) ? Schema::get($module)['code'] : 'id';
        $code = trim((string) ($record[$column] ?? ''));

        if ($module === 'shipment_documents' || $module === 'clearance_documents') {
            // "Packing list" says nothing on its own: name what it belongs to.
            $owner = $module === 'shipment_documents'
                ? self::scalar('SELECT shipment_code FROM shipments WHERE id = ?', [(int) ($record['shipment_id'] ?? 0)])
                : self::scalar('SELECT reference FROM border_crossings WHERE id = ?', [(int) ($record['crossing_id'] ?? 0)]);
            $code = trim($code . ($owner ? ' · ' . $owner : ''));
        }

        return mb_substr($code !== '' ? $code : '#' . ($record['id'] ?? ''), 0, 64);
    }

    /**
     * The steps this record needs right now, in order.
     *
     * @return list<array{step_no: int, step_label: string, approver_role: string, sla_hours: int}>
     */
    public static function requiredSteps(string $module, string $action, array $record): array
    {
        [$amount, $currency] = self::money($module, $record);
        $steps = [];

        foreach (self::policies($module, $action) as $rule) {
            $number = (int) $rule['step_no'];
            if (isset($steps[$number])) {
                continue;
            }
            if (!self::conditionHolds((string) $rule['applies_when'], $module, $record)) {
                continue;
            }
            if ($rule['min_amount'] !== null) {
                $ruleCurrency = strtoupper(trim((string) $rule['currency']));
                if ($amount === null || $currency === null || $ruleCurrency === '' || $ruleCurrency !== $currency || $amount < (float) $rule['min_amount']) {
                    continue;
                }
            }
            $steps[$number] = [
                'step_no' => $number,
                'step_label' => (string) $rule['step_label'],
                'approver_role' => (string) $rule['approver_role'],
                'sla_hours' => max(1, (int) $rule['sla_hours']),
            ];
        }
        ksort($steps);

        return array_values($steps);
    }

    private static function conditionHolds(string $condition, string $module, array $record): bool
    {
        if ($condition !== 'cross_border') {
            return true;
        }

        return match ($module) {
            'trips' => Clearance::tripCrossesBorder((int) $record['id']),
            'shipment_documents' => Clearance::shipmentCrossesBorder((int) ($record['shipment_id'] ?? 0)),
            default => true,
        };
    }

    // ---------------------------------------------------------------- rounds

    /** @return list<array<string, mixed>> the latest round's steps, in order */
    public static function latestRound(string $module, int $id, string $action): array
    {
        $round = (int) self::scalar('SELECT COALESCE(MAX(round_no), 0) FROM approval_steps WHERE module = ? AND record_id = ? AND action = ?', [$module, $id, $action]);
        if ($round === 0) {
            return [];
        }
        $statement = self::db()->prepare('SELECT * FROM approval_steps WHERE module = ? AND record_id = ? AND action = ? AND round_no = ? ORDER BY step_no, id');
        $statement->execute([$module, $id, $action, $round]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** active (someone still has to sign), approved (all signed), closed (rejected or cancelled), none */
    private static function state(array $steps): string
    {
        if ($steps === []) {
            return 'none';
        }
        $statuses = array_column($steps, 'status');
        if (array_intersect($statuses, ['pending', 'waiting']) !== []) {
            return 'active';
        }
        if (array_intersect($statuses, ['rejected', 'cancelled']) !== []) {
            return 'closed';
        }

        return 'approved';
    }

    private static function isOpenStatus(string $module, string $action, array $record): bool
    {
        $from = Workflow::fromStatuses($module, $action);

        return $from !== [] && in_array((string) ($record['status'] ?? ''), $from, true);
    }

    /**
     * Makes sure a record waiting for signatures has its round, with its first
     * step in front of the right people. Safe to call as often as you like.
     */
    /**
     * @param ?int $raisedBy who raised the record, when it was just created and
     *                       the record itself does not say (an invoice, a bill)
     */
    public static function open(string $module, int $id, ?string $action = null, ?int $raisedBy = null): void
    {
        $action ??= self::ACTIONS[$module] ?? 'approve';
        if (!self::supports($module) || !self::has($module, $action)) {
            return;
        }
        $record = self::load($module, $id);
        if ($record === null || !empty($record['deleted_at']) || !self::isOpenStatus($module, $action, $record)) {
            return;
        }

        $steps = self::latestRound($module, $id, $action);
        $state = self::state($steps);
        if ($state === 'active') {
            self::sync($module, $record, $action, $steps);
            return;
        }
        if ($state === 'approved' && self::sameAsRound($module, $record, $action, $steps)) {
            // Signed by everyone; only the move itself is left.
            return;
        }

        self::startRound($module, $record, $action, $steps === [] ? 1 : (int) $steps[0]['round_no'] + 1, false, $raisedBy ?? ($steps !== [] && $steps[0]['requested_by'] !== null ? (int) $steps[0]['requested_by'] : null));
    }

    /** Still the same record the round was for: same amount, same currency, same steps. */
    private static function sameAsRound(string $module, array $record, string $action, array $steps): bool
    {
        [$amount, $currency] = self::money($module, $record);
        $first = $steps[0] ?? [];
        $roundAmount = isset($first['amount']) && $first['amount'] !== null ? round((float) $first['amount'], 2) : null;
        $roundCurrency = ($first['currency'] ?? null) !== null && $first['currency'] !== '' ? (string) $first['currency'] : null;
        if ($roundAmount !== $amount || $roundCurrency !== $currency) {
            return false;
        }

        $wanted = array_map(static fn (array $s): string => $s['step_no'] . ':' . $s['approver_role'], self::requiredSteps($module, $action, $record));
        $have = array_map(static fn (array $s): string => $s['step_no'] . ':' . $s['approver_role'], $steps);

        return $wanted === $have;
    }

    /**
     * The amount, the currency or the steps changed while signatures were being
     * collected: what was signed was a different record, so it starts again.
     */
    private static function sync(string $module, array $record, string $action, array $steps): void
    {
        if (self::sameAsRound($module, $record, $action, $steps)) {
            return;
        }
        $signed = count(array_filter($steps, static fn (array $s): bool => $s['status'] === 'approved'));
        self::db()->prepare("UPDATE approval_steps SET status = 'cancelled', comment = COALESCE(comment, 'Changed while waiting: approval restarted') WHERE module = ? AND record_id = ? AND action = ? AND round_no = ? AND status IN ('pending','waiting')")
            ->execute([$module, (int) $record['id'], $action, (int) $steps[0]['round_no']]);
        self::startRound($module, $record, $action, (int) $steps[0]['round_no'] + 1, $signed > 0, $steps[0]['requested_by'] !== null ? (int) $steps[0]['requested_by'] : null);
    }

    private static function startRound(string $module, array $record, string $action, int $round, bool $restarted = false, ?int $raisedBy = null): void
    {
        $required = self::requiredSteps($module, $action, $record);
        if ($required === []) {
            return;
        }
        [$amount, $currency] = self::money($module, $record);
        $code = self::code($module, $record);
        $requester = self::requester($module, $record) ?? $raisedBy;
        $insert = self::db()->prepare(
            'INSERT INTO approval_steps (module, action, record_id, record_code, round_no, step_no, step_label, approver_role, status, amount, currency, requested_by, sla_hours, activated_at, due_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($required as $index => $step) {
            $first = $index === 0;
            $insert->execute([
                $module, $action, (int) $record['id'], $code, $round, $step['step_no'], $step['step_label'], $step['approver_role'],
                $first ? 'pending' : 'waiting', $amount, $currency, $requester, $step['sla_hours'],
                $first ? date('Y-m-d H:i:s') : null,
                $first ? date('Y-m-d H:i:s', time() + $step['sla_hours'] * 3600) : null,
            ]);
        }

        self::tellApprover($module, (int) $record['id'], $code, $required[0], 1, count($required), $amount, $currency, $restarted);
    }

    // --------------------------------------------------------------- signing

    /**
     * Called by the workflow before the action moves the record.
     *
     * @return array{ok: bool, message: string}|null null when the record may move now
     */
    public static function gate(string $module, string $action, array $record, string $comment = ''): ?array
    {
        if (!self::supports($module) || (self::ACTIONS[$module] ?? '') !== $action || !self::has($module, $action)) {
            return null;
        }
        $id = (int) $record['id'];
        self::open($module, $id, $action);
        $steps = self::latestRound($module, $id, $action);
        $state = self::state($steps);
        if ($state === 'none' || $state === 'approved') {
            return null;
        }
        if ($state === 'closed') {
            return ['ok' => false, 'message' => 'The last approval round was closed. Save the record again to start a new one.'];
        }

        $pending = null;
        foreach ($steps as $step) {
            if ($step['status'] === 'pending') {
                $pending = $step;
                break;
            }
        }
        if ($pending === null) {
            return null;
        }

        $total = count($steps);
        $position = (int) array_search($pending['id'], array_column($steps, 'id'), true) + 1;
        $role = \current_role();
        $actor = \current_user_id();

        if ($role !== $pending['approver_role'] && $role !== 'super_admin') {
            return ['ok' => false, 'message' => sprintf(
                'This is waiting for step %d of %d, %s, which is signed by %s. You will be told when it reaches you.',
                $position, $total, $pending['step_label'], \role_label((string) $pending['approver_role'])
            )];
        }

        if ($role !== 'super_admin' && Settings::int('approval_separation', 1) === 1) {
            // A paper is checked by whoever handles it, often the person who
            // scanned it; what matters there is that two steps are signed by
            // two different people, which the second check below keeps.
            $papers = in_array($module, ['shipment_documents', 'clearance_documents'], true);
            if (!$papers && $actor !== null && (int) $pending['requested_by'] === $actor) {
                return ['ok' => false, 'message' => 'You raised this record, so somebody else in your department has to approve it.'];
            }
            foreach ($steps as $step) {
                if ($step['status'] === 'approved' && $actor !== null && (int) $step['decided_by'] === $actor) {
                    return ['ok' => false, 'message' => sprintf('You already signed "%s" on this record. Another person signs "%s".', $step['step_label'], $pending['step_label'])];
                }
            }
        }

        self::db()->prepare("UPDATE approval_steps SET status = 'approved', decided_by = ?, decided_at = NOW(), comment = ? WHERE id = ?")
            ->execute([$actor, $comment !== '' ? mb_substr($comment, 0, 255) : null, (int) $pending['id']]);

        $next = null;
        foreach ($steps as $step) {
            if ($step['status'] === 'waiting') {
                $next = $step;
                break;
            }
        }

        AuditLog::record('approval.signed', $module, (string) $pending['record_code'], $comment !== '' ? $comment : null, ['step' => $pending['step_label'], 'round' => (int) $pending['round_no']]);

        if ($next === null) {
            // Everybody has signed: the record moves now.
            return null;
        }

        $due = date('Y-m-d H:i:s', time() + max(1, (int) $next['sla_hours']) * 3600);
        self::db()->prepare("UPDATE approval_steps SET status = 'pending', activated_at = NOW(), due_at = ? WHERE id = ?")->execute([$due, (int) $next['id']]);
        [$amount, $currency] = [$next['amount'] !== null ? (float) $next['amount'] : null, $next['currency'] ?: null];
        self::tellApprover($module, $id, (string) $next['record_code'], $next, $position + 1, $total, $amount, $currency);

        return ['ok' => true, 'message' => sprintf(
            'Step %d of %d (%s) signed. It now waits for %s by %s, who have been told.',
            $position, $total, $pending['step_label'], $next['step_label'], \role_label((string) $next['approver_role'])
        )];
    }

    /**
     * After any workflow action on a record: the signed action closes its round
     * with good news; anything else (a rejection, a cancellation) ends it.
     */
    public static function afterTransition(string $module, string $action, array $record, string $reason = ''): void
    {
        if (!self::supports($module)) {
            return;
        }
        $signedAction = self::ACTIONS[$module] ?? 'approve';
        $id = (int) $record['id'];
        $steps = self::latestRound($module, $id, $signedAction);
        if ($steps === []) {
            return;
        }
        $code = (string) $steps[0]['record_code'];
        $requester = $steps[0]['requested_by'] !== null ? (int) $steps[0]['requested_by'] : null;

        if ($action === $signedAction) {
            if (self::state($steps) === 'approved' && $requester !== null && $requester !== \current_user_id()) {
                Notifier::toUser($requester, 'Approved: ' . $code, sprintf('%s has every signature it needs (%s).', $code, implode(', ', array_column($steps, 'step_label'))), $module, 'success', $module, (string) $id);
            }
            return;
        }

        if (self::state($steps) !== 'active') {
            return;
        }
        $isRejection = Workflow::needsReason($module, $action);
        self::db()->prepare("UPDATE approval_steps SET status = ?, decided_by = ?, decided_at = NOW(), comment = ? WHERE module = ? AND record_id = ? AND action = ? AND round_no = ? AND status = 'pending'")
            ->execute([$isRejection ? 'rejected' : 'cancelled', \current_user_id(), $reason !== '' ? mb_substr($reason, 0, 255) : null, $module, $id, $signedAction, (int) $steps[0]['round_no']]);
        self::db()->prepare("UPDATE approval_steps SET status = 'cancelled' WHERE module = ? AND record_id = ? AND action = ? AND round_no = ? AND status = 'waiting'")
            ->execute([$module, $id, $signedAction, (int) $steps[0]['round_no']]);

        if ($isRejection && $requester !== null && $requester !== \current_user_id()) {
            Notifier::toUser($requester, 'Sent back: ' . $code, sprintf('%s was not approved by %s: %s', $code, \current_user_name(), $reason !== '' ? $reason : 'no reason given'), $module, 'danger', $module, (string) $id);
        }
    }

    /**
     * A form may not set a status that only the signatures can give.
     *
     * @return list<string>
     */
    public static function formErrors(string $module, array $input, ?int $id): array
    {
        $action = self::ACTIONS[$module] ?? null;
        if ($action === null || !self::has($module, $action) || !array_key_exists('status', $input)) {
            return [];
        }
        $target = Workflow::targetStatus($module, $action);
        $wanted = (string) $input['status'];
        $current = $id !== null ? (string) (self::load($module, $id)['status'] ?? '') : '';
        if ($target === null || $wanted !== $target || $wanted === $current) {
            return [];
        }

        $label = Schema::label($target);

        return [sprintf('"%s" is given by the approvals, not typed: save the record, then use the %s button. Each department is asked in turn.', $label, ucfirst($action))];
    }

    // ------------------------------------------------------------ the inbox

    /** @return list<array<string, mixed>> steps waiting for this role, most urgent first */
    public static function inbox(string $role, int $limit = 200): array
    {
        try {
            $sql = "SELECT * FROM approval_steps WHERE status = 'pending'" . ($role === 'super_admin' ? '' : ' AND approver_role = ?') . ' ORDER BY due_at IS NULL, due_at, id LIMIT ' . max(1, $limit);
            $statement = self::db()->prepare($sql);
            $statement->execute($role === 'super_admin' ? [] : [$role]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(array_map([self::class, 'decorate'], $rows), static fn (?array $row): bool => $row !== null));
    }

    public static function countFor(string $role): int
    {
        try {
            if ($role === 'super_admin') {
                return (int) self::scalar("SELECT COUNT(*) FROM approval_steps WHERE status = 'pending'");
            }

            return (int) self::scalar("SELECT COUNT(*) FROM approval_steps WHERE status = 'pending' AND approver_role = ?", [$role]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> what this person raised that is still waiting */
    public static function raisedBy(int $userId, int $limit = 50): array
    {
        try {
            $statement = self::db()->prepare("SELECT * FROM approval_steps WHERE status = 'pending' AND requested_by = ? ORDER BY activated_at DESC LIMIT " . max(1, $limit));
            $statement->execute([$userId]);

            return array_values(array_filter(array_map([self::class, 'decorate'], $statement->fetchAll(PDO::FETCH_ASSOC))));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> what this person signed or sent back lately */
    public static function decidedBy(int $userId, int $limit = 30): array
    {
        try {
            $statement = self::db()->prepare("SELECT * FROM approval_steps WHERE decided_by = ? AND status IN ('approved','rejected') ORDER BY decided_at DESC LIMIT " . max(1, $limit));
            $statement->execute([$userId]);

            return array_values(array_filter(array_map([self::class, 'decorate'], $statement->fetchAll(PDO::FETCH_ASSOC))));
        } catch (\Throwable) {
            return [];
        }
    }

    /** One step, with what a person needs to decide from the list. */
    private static function decorate(array $step): ?array
    {
        $module = (string) $step['module'];
        if (!Schema::has($module)) {
            return null;
        }
        $total = (int) self::scalar('SELECT COUNT(*) FROM approval_steps WHERE module = ? AND record_id = ? AND action = ? AND round_no = ?', [$module, (int) $step['record_id'], $step['action'], (int) $step['round_no']]);
        $position = (int) self::scalar('SELECT COUNT(*) FROM approval_steps WHERE module = ? AND record_id = ? AND action = ? AND round_no = ? AND step_no <= ?', [$module, (int) $step['record_id'], $step['action'], (int) $step['round_no'], (int) $step['step_no']]);
        $requester = $step['requested_by'] !== null ? (string) self::scalar('SELECT full_name FROM users WHERE id = ?', [(int) $step['requested_by']]) : '';
        $decider = $step['decided_by'] !== null ? (string) self::scalar('SELECT full_name FROM users WHERE id = ?', [(int) $step['decided_by']]) : '';
        $due = $step['due_at'] !== null ? strtotime((string) $step['due_at']) : null;

        return $step + [
            'module_title' => Schema::get($module)['singular'] ?? Schema::get($module)['title'],
            'url' => \url([$module, (int) $step['record_id']]),
            'action_url' => \url([$module, (int) $step['record_id'], 'action', (string) $step['action']]),
            'reject_url' => Workflow::hasAction($module, 'reject') ? \url([$module, (int) $step['record_id'], 'action', 'reject']) : null,
            'position' => $position,
            'total' => $total,
            'requester_name' => $requester,
            'decider_name' => $decider,
            'role_name' => \role_label((string) $step['approver_role']),
            'overdue' => $due !== null && $due < time() && $step['status'] === 'pending',
            'money' => $step['amount'] !== null && $step['currency'] ? Currency::format((float) $step['amount'], (string) $step['currency']) : '',
        ];
    }

    /** @return list<array{round: int, steps: list<array<string, mixed>>}> every round on a record, newest first */
    public static function timeline(string $module, int $id): array
    {
        if (!self::supports($module)) {
            return [];
        }
        try {
            self::open($module, $id);
            $statement = self::db()->prepare('SELECT s.*, u.full_name AS decider_name FROM approval_steps s LEFT JOIN users u ON u.id = s.decided_by WHERE s.module = ? AND s.record_id = ? ORDER BY s.round_no DESC, s.step_no, s.id');
            $statement->execute([$module, $id]);
            $rounds = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row['role_name'] = \role_label((string) $row['approver_role']);
                $rounds[(int) $row['round_no']]['round'] = (int) $row['round_no'];
                $rounds[(int) $row['round_no']]['steps'][] = $row;
            }

            return array_values($rounds);
        } catch (\Throwable) {
            return [];
        }
    }

    // ------------------------------------------------------------- the clock

    /**
     * Reminders, escalations, and rounds for records that reached their
     * waiting status by some other road (raised from a trip, imported).
     *
     * @return array{opened: int, reminded: int, escalated: int}
     */
    public static function sweep(): array
    {
        $result = ['opened' => 0, 'reminded' => 0, 'escalated' => 0];
        try {
            $db = self::db();

            foreach (self::ACTIONS as $module => $action) {
                if (!self::has($module, $action)) {
                    continue;
                }
                $from = Workflow::fromStatuses($module, $action);
                if ($from === []) {
                    continue;
                }
                $table = self::SUBJECTS[$module]['table'];
                $soft = in_array('deleted_at', array_keys(LogisticsData::columnMeta($table)), true) ? ' AND t.deleted_at IS NULL' : '';
                $marks = implode(',', array_fill(0, count($from), '?'));
                $statement = $db->prepare(
                    "SELECT t.id FROM `{$table}` t
                      WHERE t.status IN ({$marks}){$soft}
                        AND NOT EXISTS (SELECT 1 FROM approval_steps s WHERE s.module = ? AND s.record_id = t.id AND s.action = ? AND s.status IN ('pending','waiting'))
                      ORDER BY t.id DESC LIMIT 100"
                );
                $statement->execute([...$from, $module, $action]);
                foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $recordId) {
                    $before = (int) self::scalar("SELECT COUNT(*) FROM approval_steps WHERE module = ? AND record_id = ? AND action = ?", [$module, (int) $recordId, $action]);
                    self::open($module, (int) $recordId, $action);
                    if ((int) self::scalar("SELECT COUNT(*) FROM approval_steps WHERE module = ? AND record_id = ? AND action = ?", [$module, (int) $recordId, $action]) > $before) {
                        $result['opened']++;
                    }
                }
            }

            // Half the time gone: a reminder to the same people.
            $half = $db->query("SELECT * FROM approval_steps WHERE status = 'pending' AND reminded_at IS NULL AND due_at IS NOT NULL AND activated_at IS NOT NULL
                                  AND NOW() >= DATE_ADD(activated_at, INTERVAL ROUND(TIMESTAMPDIFF(SECOND, activated_at, due_at) / 2) SECOND)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($half as $step) {
                Notifier::toRole((string) $step['approver_role'], 'Reminder: approval waiting', sprintf(
                    '%s %s is still waiting for "%s". Please decide by %s.',
                    self::moduleWord((string) $step['module']), $step['record_code'], $step['step_label'], \human_date((string) $step['due_at'], true)
                ), (string) $step['module'], 'warning', (string) $step['module'], (string) $step['record_id']);
                $db->prepare('UPDATE approval_steps SET reminded_at = NOW() WHERE id = ?')->execute([(int) $step['id']]);
                $result['reminded']++;
            }

            // Out of time: the people above are told, once.
            $late = $db->query("SELECT * FROM approval_steps WHERE status = 'pending' AND escalated_at IS NULL AND due_at IS NOT NULL AND due_at < NOW()")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($late as $step) {
                $above = array_values(array_diff(['management', 'super_admin'], [(string) $step['approver_role']]));
                Notifier::toRoles($above, 'Approval overdue', sprintf(
                    '%s %s has waited since %s for "%s" by %s. It was due %s.',
                    self::moduleWord((string) $step['module']), $step['record_code'], \human_date((string) $step['activated_at'], true), $step['step_label'],
                    \role_label((string) $step['approver_role']), \human_date((string) $step['due_at'], true)
                ), (string) $step['module'], 'danger', (string) $step['module'], (string) $step['record_id']);
                $db->prepare('UPDATE approval_steps SET escalated_at = NOW() WHERE id = ?')->execute([(int) $step['id']]);
                $result['escalated']++;
            }
        } catch (\Throwable $exception) {
            error_log('Approval sweep: ' . $exception->getMessage());
        }

        return $result;
    }

    // ------------------------------------------------------------- helpers

    private static function tellApprover(string $module, int $id, string $code, array $step, int $position, int $total, ?float $amount, ?string $currency, bool $restarted = false): void
    {
        $facts = [sprintf('Step %d of %d: %s', $position, $total, $step['step_label'])];
        if ($amount !== null && $currency !== null) {
            $facts[] = Currency::format($amount, $currency);
        }
        $facts[] = 'Decide within ' . (int) $step['sla_hours'] . ' hours';
        if ($restarted) {
            array_unshift($facts, 'It changed after it was signed, so approval started again');
        }
        Notifier::toRole(
            (string) $step['approver_role'],
            'Approval needed: ' . self::moduleWord($module) . ' ' . $code,
            implode(' · ', $facts),
            $module,
            'warning',
            $module,
            (string) $id
        );
    }

    private static function moduleWord(string $module): string
    {
        return Schema::has($module) ? (string) (Schema::get($module)['singular'] ?? $module) : Schema::label($module);
    }

    private static function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }
}
