<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Goods a customer keeps in our warehouse.
 *
 * A lot is what came in on one goods received note: 2,000 bags of cement in
 * bay C4. It goes out a part at a time, each part against a gate pass naming
 * who collected it and on which truck. What is left is received less released,
 * never a number somebody typed.
 *
 * Storage is free for the first few days, then charged per day on what is still
 * there: per bag, per pallet position, per cubic metre or per tonne. A lot
 * remembers the last day it was billed to, so billing twice never charges the
 * same day twice.
 */
final class Storage
{
    public const STATUS = ['in_store' => 'In store', 'partly_released' => 'Partly released', 'released' => 'All released'];
    public const BASIS = [
        'per_unit_day' => 'Per unit per day',
        'per_lot_day' => 'Per lot per day',
        'per_m3_day' => 'Per m3 per day',
        'per_tonne_day' => 'Per tonne per day',
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function lot(int $id): ?array
    {
        return self::row('SELECT * FROM storage_lots WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    /** What is still in the bay. */
    public static function onHand(int $lotId, ?int $exceptRelease = null): float
    {
        $lot = self::lot($lotId);
        if ($lot === null) {
            return 0.0;
        }

        $released = (float) self::scalar(
            'SELECT COALESCE(SUM(quantity), 0) FROM storage_releases WHERE lot_id = ? AND deleted_at IS NULL AND id <> ?',
            [$lotId, (int) $exceptRelease]
        );

        return round((float) $lot['quantity_received'] - $released, 2);
    }

    /** Released total and status follow the gate passes. */
    public static function recalculate(int $lotId): void
    {
        $lot = self::lot($lotId);
        if ($lot === null) {
            return;
        }

        $released = round((float) self::scalar('SELECT COALESCE(SUM(quantity), 0) FROM storage_releases WHERE lot_id = ? AND deleted_at IS NULL', [$lotId]), 2);
        $status = match (true) {
            $released <= 0 => 'in_store',
            $released + 0.004 >= (float) $lot['quantity_received'] => 'released',
            default => 'partly_released',
        };

        self::db()->prepare('UPDATE storage_lots SET quantity_released = ?, status = ? WHERE id = ?')->execute([$released, $status, $lotId]);

        // A lot that came off one of our trucks leaves the depot's cargo
        // register when the last of it is collected.
        if ($status === 'released' && $lot['status'] !== 'released' && !empty($lot['shipment_id'])) {
            CargoCustody::collected((int) $lot['shipment_id'], (int) $lot['warehouse_id']);
        }
    }

    /**
     * The storage charge on one lot for the days from $from to $to inclusive,
     * after its free days, on what was in the bay each day.
     *
     * @return array{days: int, units: float, amount: float, from: ?string, to: ?string}
     */
    public static function charge(array $lot, string $from, string $to): array
    {
        $chargeFrom = date('Y-m-d', (int) strtotime(substr((string) $lot['received_at'], 0, 10) . ' +' . (int) $lot['free_days'] . ' days'));
        $start = max($from, $chargeFrom);
        if ($start > $to) {
            return ['days' => 0, 'units' => 0.0, 'amount' => 0.0, 'from' => null, 'to' => null];
        }

        $releases = self::rows(
            'SELECT DATE(released_at) AS day, SUM(quantity) AS quantity FROM storage_releases
              WHERE lot_id = ? AND deleted_at IS NULL GROUP BY DATE(released_at) ORDER BY day',
            [(int) $lot['id']]
        );

        $received = (float) $lot['quantity_received'];
        $rate = (float) $lot['daily_rate'];
        $days = 0;
        $units = 0.0;
        $first = null;
        $last = null;

        for ($day = $start; $day <= $to; $day = date('Y-m-d', (int) strtotime($day . ' +1 day'))) {
            // Goods released on a day were in the bay that morning, so the day
            // of release still counts.
            $releasedBefore = 0.0;
            foreach ($releases as $release) {
                if ($release['day'] < $day) {
                    $releasedBefore += (float) $release['quantity'];
                }
            }
            $held = $received - $releasedBefore;
            if ($held <= 0.004) {
                break;
            }

            $share = $received > 0 ? $held / $received : 0.0;
            $units += match ((string) $lot['charge_basis']) {
                'per_lot_day' => 1.0,
                'per_m3_day' => (float) $lot['volume_m3'] * $share,
                'per_tonne_day' => (float) $lot['weight_kg'] / 1000 * $share,
                default => $held,
            };
            $days++;
            $first ??= $day;
            $last = $day;
        }

        return ['days' => $days, 'units' => round($units, 3), 'amount' => round($units * $rate, 2), 'from' => $first, 'to' => $last];
    }

    /** The charge not yet billed on a lot, up to today. */
    public static function unbilled(array $lot, ?string $until = null): array
    {
        $from = $lot['billed_until'] !== null
            ? date('Y-m-d', (int) strtotime((string) $lot['billed_until'] . ' +1 day'))
            : substr((string) $lot['received_at'], 0, 10);

        return self::charge($lot, $from, $until ?? date('Y-m-d'));
    }

    /**
     * Raises draft storage invoices for a customer up to a date: one per
     * currency, one line per lot, and moves each lot's billed-until date on.
     *
     * @return array{ok: bool, message: string, invoices: list<int>}
     */
    public static function bill(int $customerId, ?string $until = null): array
    {
        $until ??= date('Y-m-d');
        $customer = self::row('SELECT id, customer_name, payment_terms_days FROM customers WHERE id = ? AND deleted_at IS NULL', [$customerId]);
        if ($customer === null) {
            return ['ok' => false, 'message' => 'That customer no longer exists.', 'invoices' => []];
        }

        $lots = self::rows(
            'SELECT l.*, w.warehouse_name FROM storage_lots l INNER JOIN warehouses w ON w.id = l.warehouse_id
              WHERE l.customer_id = ? AND l.deleted_at IS NULL AND l.daily_rate > 0
                AND (l.billed_until IS NULL OR l.billed_until < ?)
              ORDER BY l.currency, l.received_at',
            [$customerId, $until]
        );

        $byCurrency = [];
        foreach ($lots as $lot) {
            $charge = self::unbilled($lot, $until);
            if ($charge['amount'] <= 0) {
                continue;
            }
            $byCurrency[(string) $lot['currency']][] = [$lot, $charge];
        }

        if ($byCurrency === []) {
            return ['ok' => false, 'message' => sprintf('Nothing to bill for %s up to %s: every lot is still within its free days, already billed, or has no daily rate.', $customer['customer_name'], $until), 'invoices' => []];
        }

        $terms = (int) ($customer['payment_terms_days'] ?: Settings::int('payment_terms_days', 30));
        $invoices = [];
        $db = self::db();
        $db->beginTransaction();
        try {
            foreach ($byCurrency as $currency => $items) {
                $invoiceId = LogisticsData::save('invoices', null, [
                    'invoice_number' => '', 'customer_id' => (string) $customerId, 'status' => 'draft', 'currency' => $currency,
                    'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+' . $terms . ' days')),
                    'tax_rate' => (string) Settings::float('tax_rate', 18.0),
                    'notes' => 'Warehouse storage up to ' . $until . '.',
                ]);

                $lines = [];
                foreach ($items as [$lot, $charge]) {
                    $basis = match ((string) $lot['charge_basis']) {
                        'per_lot_day' => 'lot-days',
                        'per_m3_day' => 'm3-days',
                        'per_tonne_day' => 'tonne-days',
                        default => strtolower((string) $lot['unit']) . '-days',
                    };
                    $lines[] = [
                        'description' => mb_substr(sprintf('Storage %s, %s at %s, %s to %s (%s %s)',
                            $lot['lot_code'], $lot['description'], $lot['warehouse_name'], $charge['from'], $charge['to'],
                            rtrim(rtrim(number_format($charge['units'], 3, '.', ','), '0'), '.'), $basis), 0, 255),
                        'quantity' => (string) $charge['units'],
                        'unit_price' => (string) $lot['daily_rate'],
                    ];
                    $db->prepare('UPDATE storage_lots SET billed_until = ? WHERE id = ?')->execute([$charge['to'], (int) $lot['id']]);
                }
                LogisticsData::saveLines('invoices', $invoiceId, $lines);
                $invoices[] = $invoiceId;
            }
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('storage.billed', 'storage_lots', (string) $customer['customer_name'], null, ['until' => $until, 'invoices' => $invoices]);
        Notifier::toRole('finance', 'Storage invoice ready to issue',
            sprintf('Storage for %s up to %s is on %d draft invoice(s). Check and issue.', $customer['customer_name'], $until, count($invoices)),
            'invoices', 'info', 'invoices', (string) $invoices[0]);

        return ['ok' => true, 'message' => sprintf('%d draft storage invoice%s raised for %s up to %s.', count($invoices), count($invoices) === 1 ? '' : 's', $customer['customer_name'], $until), 'invoices' => $invoices];
    }

    private static function row(string $sql, array $parameters): ?array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function rows(string $sql, array $parameters): array
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function scalar(string $sql, array $parameters): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
