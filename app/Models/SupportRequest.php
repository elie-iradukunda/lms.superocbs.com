<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * A driver asking the company for help during a trip.
 *
 * Money for a burst tyre, a breakdown, a border or police problem, papers, a
 * health matter: the driver writes what he needs from his phone and chooses
 * who should handle it. That person, and the logistics managers, hear about
 * it on the bell and by email; whoever answers records what was done (the
 * money sent and how), and the driver hears back. Every request belongs to
 * its trip, so it shows on the trip report.
 */
final class SupportRequest
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** The trip's requests, newest first, with who answered. */
    public static function forTrip(int $tripId): array
    {
        $statement = self::db()->prepare(
            'SELECT sr.*, a.full_name AS assigned_name, rb.full_name AS responded_name
               FROM support_requests sr
               LEFT JOIN users a ON a.id = sr.assigned_to
               LEFT JOIN users rb ON rb.id = sr.responded_by
              WHERE sr.trip_id = ? AND sr.deleted_at IS NULL
              ORDER BY sr.id DESC'
        );
        try {
            $statement->execute([$tripId]);
        } catch (\Throwable) {
            return []; // before the table exists
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Just sent: the person chosen and the logistics managers are told, on the bell and by email. */
    public static function announce(int $id): void
    {
        $request = self::row($id);
        if ($request === null) {
            return;
        }
        $what = sprintf('%s · %s%s%s', $request['request_code'], Schema::SUPPORT_TYPES[$request['support_type']] ?? 'Support', ' — ' . $request['title'],
            (float) $request['amount_requested'] > 0 ? sprintf(' (%s)', Currency::format((float) $request['amount_requested'], (string) ($request['currency'] ?: Currency::base()))) : '');
        $message = sprintf('%s on %s asks: %s%s', $request['driver_name'] ?: 'The driver', $request['trip_code'], $what,
            trim((string) $request['location']) !== '' ? '. At ' . $request['location'] . '.' : '.');
        $severity = $request['urgency'] === 'normal' ? 'warning' : 'danger';
        $title = ($request['urgency'] === 'emergency' ? 'EMERGENCY: ' : ($request['urgency'] === 'urgent' ? 'Urgent: ' : '')) . 'Driver needs support';

        Notifier::toUser($request['assigned_to'] !== null ? (int) $request['assigned_to'] : null, $title, $message, 'support_requests', $severity, 'support_requests', (string) $id);
        Notifier::toRole('logistics_manager', $title, $message, 'support_requests', $severity, 'support_requests', (string) $id);
    }

    public static function guard(string $action, array $request): ?string
    {
        if ($action === 'resolve') {
            if (trim((string) ($request['response'] ?? '')) === '') {
                return 'Write the answer to the driver first (Edit, "Answer to the driver"), then press Resolved.';
            }
            if ($request['support_type'] === 'money' && (float) ($request['amount_given'] ?? 0) <= 0) {
                return 'This is a money request: record the amount given and how it was sent (Edit) before marking it resolved.';
            }
        }

        return null;
    }

    public static function applied(string $action, array $request, string $reason, ?int $actor): string
    {
        $id = (int) $request['id'];
        $db = self::db();
        $db->prepare('UPDATE support_requests SET responded_by = COALESCE(responded_by, ?), responded_at = COALESCE(responded_at, NOW()) WHERE id = ?')->execute([$actor, $id]);

        $full = self::row($id) ?? $request;
        $driverUser = $full['driver_user_id'] !== null ? (int) $full['driver_user_id'] : null;
        $who = (string) ($full['responded_name'] ?? 'The office');

        if ($action === 'acknowledge') {
            Notifier::toUser($driverUser, 'Your request is being handled', sprintf('%s: %s has seen "%s" and is handling it.', $full['request_code'], $who, $full['title']), 'my-trips', 'info', 'support_requests', (string) $id);

            return sprintf('%s marked as being handled. The driver was told.', $full['request_code']);
        }

        if ($action === 'resolve') {
            $db->prepare('UPDATE support_requests SET resolved_at = NOW() WHERE id = ?')->execute([$id]);
            $given = (float) $full['amount_given'] > 0
                ? sprintf(' %s sent%s%s.', Currency::format((float) $full['amount_given'], (string) ($full['currency'] ?: Currency::base())),
                    $full['given_method'] ? ' by ' . $full['given_method'] : '', $full['given_reference'] ? ' (ref. ' . $full['given_reference'] . ')' : '')
                : '';
            Notifier::toUser($driverUser, 'Your request is resolved', sprintf('%s: %s%s', $full['request_code'], trim((string) $full['response']), $given), 'my-trips', 'success', 'support_requests', (string) $id);

            return sprintf('%s resolved. The driver was told.', $full['request_code']);
        }

        if ($action === 'reject') {
            $db->prepare('UPDATE support_requests SET rejection_reason = ? WHERE id = ?')->execute([mb_substr($reason, 0, 255), $id]);
            Notifier::toUser($driverUser, 'Your request was refused', sprintf('%s: %s', $full['request_code'], $reason), 'my-trips', 'danger', 'support_requests', (string) $id);

            return sprintf('%s refused. The driver was told why.', $full['request_code']);
        }

        return 'Done.';
    }

    private static function row(int $id): ?array
    {
        $statement = self::db()->prepare(
            'SELECT sr.*, t.reference_code AS trip_code, d.full_name AS driver_name, d.user_id AS driver_user_id, rb.full_name AS responded_name
               FROM support_requests sr
               LEFT JOIN trips t ON t.id = sr.trip_id
               LEFT JOIN drivers d ON d.id = sr.driver_id
               LEFT JOIN users rb ON rb.id = sr.responded_by
              WHERE sr.id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
