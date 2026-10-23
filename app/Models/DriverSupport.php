<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Driver support (section 16): breakdown, accident, sickness, border delay,
 * money needed. It reaches the OM at once; the OM approves or rejects it with
 * a reason and forwards it to whoever should act (Finance for money, the
 * garage for a breakdown, management for an accident, HR for sickness). A
 * money request becomes an F10 that follows the full chain and lands on the
 * trip's cost. Urgent requests left unhandled go to a backup.
 */
final class DriverSupport
{
    /** The specification's types, on the columns the table already has. */
    public const TYPES = ['money' => 'Money needed', 'breakdown' => 'Breakdown', 'accident' => 'Accident', 'police_border' => 'Border delay / police', 'health' => 'Sickness / medical', 'documents' => 'Papers problem', 'other' => 'Other'];

    /** Where each kind of request usually goes. */
    public const FORWARD_TO = ['finance' => 'Finance (money)', 'warehouse_manager' => 'Store & garage (breakdown, parts)', 'management' => 'Managing Director (accident)', 'logistics_manager' => 'Operations (OM)'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function find(int $id): ?array
    {
        $statement = self::db()->prepare(
            'SELECT sr.*, d.full_name AS driver_name, d.user_id AS driver_user_id, t.reference_code AS trip_code, v.plate_number
               FROM support_requests sr
               LEFT JOIN drivers d ON d.id = sr.driver_id
               LEFT JOIN trips t ON t.id = sr.trip_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
              WHERE sr.id = ? AND sr.deleted_at IS NULL'
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * The driver sends it from the app.
     *
     * @param array<string, mixed> $data type, urgency, details, location, lat, lng, amount, currency
     * @param list<string> $photos stored files
     */
    public static function submit(int $driverId, ?int $tripId, array $data, array $photos = []): int
    {
        $driver = Fleet::driver($driverId) ?? throw new \RuntimeException('Unknown driver.');
        $type = (string) ($data['type'] ?? '');
        if (!array_key_exists($type, self::TYPES)) {
            throw new \RuntimeException('Choose what the problem is.');
        }
        $details = trim((string) ($data['details'] ?? ''));
        if ($details === '') {
            throw new \RuntimeException('Say what happened and what you need.');
        }
        $amount = (float) str_replace(',', '', (string) ($data['amount'] ?? '0'));
        if ($type === 'money' && $amount <= 0) {
            throw new \RuntimeException('Say how much money you need.');
        }
        $urgency = in_array($data['urgency'] ?? '', ['normal', 'urgent', 'emergency'], true) ? (string) $data['urgency'] : ($type === 'accident' ? 'emergency' : 'urgent');
        $companyId = (int) ($driver['company_id'] ?: \current_company_id());
        $code = Numbering::next($companyId, 'support', 'SUP-{YEAR}-{SEQ4}', 'year');

        self::db()->prepare(
            "INSERT INTO support_requests (company_id, request_code, trip_id, driver_id, requested_by, support_type, urgency, title, details, location, latitude, longitude,
                amount_requested, currency, photos, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')"
        )->execute([
            $companyId, $code, $tripId, $driverId, \current_user_id(), $type, $urgency, mb_substr(self::TYPES[$type] . ': ' . $details, 0, 150), $details,
            trim((string) ($data['location'] ?? '')) ?: null,
            ($data['lat'] ?? '') !== '' ? (float) $data['lat'] : null, ($data['lng'] ?? '') !== '' ? (float) $data['lng'] : null,
            $amount > 0 ? $amount : null, $amount > 0 ? strtoupper((string) ($data['currency'] ?? '') ?: Company::baseCurrency($companyId)) : null,
            $photos !== [] ? json_encode(array_values($photos)) : null,
        ]);
        $id = (int) self::db()->lastInsertId();

        if ($tripId !== null) {
            TripLife::event($tripId, 'support_requested', 'driver', ['place' => $data['location'] ?? null, 'lat' => $data['lat'] ?? '', 'lng' => $data['lng'] ?? ''], self::TYPES[$type] . ': ' . $details);
        }
        $request = self::find($id);
        $message = sprintf('%s%s (%s): %s%s%s', $request['driver_name'], $request['trip_code'] ? ' on ' . $request['trip_code'] : '', $request['plate_number'] ?? '', $details,
            $amount > 0 ? ' — needs ' . Currency::format($amount, (string) $request['currency']) : '', $request['location'] ? ' — at ' . $request['location'] : '');
        Notifier::forCompany($companyId, static fn () => Notifier::toRole('logistics_manager', ($urgency === 'emergency' ? 'EMERGENCY: ' : ($urgency === 'urgent' ? 'Urgent: ' : '')) . self::TYPES[$type], $message, 'support_requests', $urgency === 'normal' ? 'warning' : 'danger', 'support_requests', (string) $id));
        AuditLog::record('support.sent', 'support_requests', $code);

        return $id;
    }

    /**
     * The OM approves (and forwards) or rejects. Money approved becomes an F10,
     * sent into the chain with the driver as payee.
     */
    public static function decide(int $id, bool $approve, string $reason, ?string $forwardRole = null, ?int $assignee = null): string
    {
        $request = self::find($id) ?? throw new \RuntimeException('That support request does not exist.');
        if ($request['status'] !== 'open') {
            throw new \RuntimeException('This request has already been decided.');
        }
        $db = self::db();
        if (!$approve) {
            if (trim($reason) === '') {
                throw new \RuntimeException('Give the reason: the driver is told.');
            }
            $db->prepare("UPDATE support_requests SET status = 'rejected', rejection_reason = ?, decided_by = ?, decided_at = NOW() WHERE id = ?")->execute([trim($reason), \current_user_id(), $id]);
            Notifier::toUser($request['driver_user_id'] !== null ? (int) $request['driver_user_id'] : null, 'Support request refused', $request['request_code'] . ': ' . trim($reason), 'my-trips', 'warning');
            AuditLog::record('support.rejected', 'support_requests', (string) $request['request_code'], trim($reason));

            return 'Refused; the driver was told why.';
        }

        $forwardRole = $forwardRole !== null && array_key_exists($forwardRole, self::FORWARD_TO) ? $forwardRole : ($request['support_type'] === 'money' ? 'finance' : 'logistics_manager');
        $requestId = null;
        if ((float) ($request['amount_requested'] ?? 0) > 0) {
            $driver = Fleet::driver((int) $request['driver_id']);
            $requestId = RequestForms::create([
                'form_code' => 'F10',
                'company_id' => (int) $request['company_id'],
                'title' => sprintf('Driver support %s — %s', $request['request_code'], self::TYPES[$request['support_type']] ?? 'Support'),
                'currency' => (string) $request['currency'],
                'trip_id' => $request['trip_id'],
                'driver_id' => $request['driver_id'],
                'payload' => ['support_type' => $request['support_type'], 'location' => $request['location'], 'support_request_id' => $id],
                'notes' => (string) $request['details'],
            ], [[
                'item_code' => 'support', 'description' => (string) $request['details'], 'quantity' => 1, 'amount' => (float) $request['amount_requested'],
                'trip_id' => $request['trip_id'], 'driver_id' => $request['driver_id'],
                'payee_type' => 'driver', 'payee_id' => (int) $request['driver_id'], 'payee_name' => (string) ($driver['full_name'] ?? $request['driver_name']),
                'payment_method' => ($driver['payment_method'] ?? 'momo') === 'cash' ? 'cash' : 'momo', 'momo_number' => $driver['momo_number'] ?? null, 'momo_name' => $driver['momo_name'] ?? null,
            ]]);
            RequestForms::send($requestId);
        }
        $db->prepare("UPDATE support_requests SET status = 'in_progress', decided_by = ?, decided_at = NOW(), forwarded_role = ?, assigned_to = ?, request_id = ?, response = ? WHERE id = ?")
            ->execute([\current_user_id(), $forwardRole, $assignee, $requestId, trim($reason) ?: null, $id]);

        $message = sprintf('%s (%s, %s): %s%s', $request['request_code'], $request['driver_name'], $request['plate_number'] ?? '', $request['details'], trim($reason) !== '' ? ' — OM: ' . trim($reason) : '');
        Notifier::forCompany((int) $request['company_id'], static function () use ($assignee, $forwardRole, $message, $id): void {
            if ($assignee !== null) {
                Notifier::toUser($assignee, 'Driver support forwarded to you', $message, 'support_requests', 'warning', 'support_requests', (string) $id);
            } else {
                Notifier::toRole($forwardRole, 'Driver support forwarded', $message, 'support_requests', 'warning', 'support_requests', (string) $id);
            }
        });
        Notifier::toUser($request['driver_user_id'] !== null ? (int) $request['driver_user_id'] : null, 'Support request approved', sprintf('%s is being handled by %s.', $request['request_code'], self::FORWARD_TO[$forwardRole]), 'my-trips', 'success');
        AuditLog::record('support.forwarded', 'support_requests', (string) $request['request_code'], $forwardRole);

        return $requestId !== null ? 'Approved and forwarded; the money went to Finance as an F10.' : 'Approved and forwarded.';
    }

    /** Whoever it was forwarded to marks it resolved; the driver is told. */
    public static function resolve(int $id, string $note): void
    {
        $request = self::find($id) ?? throw new \RuntimeException('That support request does not exist.');
        if ($request['status'] !== 'in_progress') {
            throw new \RuntimeException('Only a request being handled can be resolved.');
        }
        if (trim($note) === '') {
            throw new \RuntimeException('Say what was done.');
        }
        self::db()->prepare("UPDATE support_requests SET status = 'resolved', response = ?, responded_by = ?, responded_at = NOW(), resolved_at = NOW() WHERE id = ?")
            ->execute([trim($note), \current_user_id(), $id]);
        Notifier::toUser($request['driver_user_id'] !== null ? (int) $request['driver_user_id'] : null, 'Support resolved', $request['request_code'] . ': ' . trim($note), 'my-trips', 'success');
        Notifier::forCompany((int) $request['company_id'], static fn () => Notifier::bellToRole('logistics_manager', 'Support resolved', $request['request_code'] . ': ' . trim($note), 'support_requests', 'success', 'support_requests', (string) $id));
        if (!empty($request['trip_id'])) {
            TripLife::event((int) $request['trip_id'], 'support_resolved', 'om', null, trim($note));
        }
        AuditLog::record('support.resolved', 'support_requests', (string) $request['request_code'], trim($note));
    }

    /** Urgent requests still open after the set time go to the backup (section 16). */
    public static function escalate(): int
    {
        $minutes = max(5, Settings::int('support_backup_after_minutes', 60));
        $role = Settings::get('support_backup_role', 'management');
        $rows = self::db()->query(
            "SELECT id FROM support_requests WHERE deleted_at IS NULL AND status = 'open' AND urgency IN ('urgent', 'emergency')
                AND escalated_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $id) {
            $request = self::find((int) $id);
            Notifier::forCompany((int) $request['company_id'], static fn () => Notifier::toRole($role, 'Urgent support not handled', sprintf('%s from %s has waited more than %d minutes: %s', $request['request_code'], $request['driver_name'], $minutes, $request['details']), 'support_requests', 'danger', 'support_requests', (string) $id));
            self::db()->prepare('UPDATE support_requests SET escalated_at = NOW() WHERE id = ?')->execute([(int) $id]);
        }

        return count($rows);
    }

    /** @return list<array<string, mixed>> the driver's requests, newest first */
    public static function forDriver(int $driverId, int $limit = 20): array
    {
        $statement = self::db()->prepare('SELECT * FROM support_requests WHERE driver_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT ' . max(1, $limit));
        $statement->execute([$driverId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
