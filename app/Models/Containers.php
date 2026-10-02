<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The shipping line's box, which has to go back.
 *
 * A container leaves the port full on our truck. The line gives a number of
 * free days; after that it charges detention for every day until the box is
 * back, empty, at the depot it names. A box forgotten in a customer's yard in
 * Kigali can cost more than the freight earned. So each one is tracked from
 * gate-out to empty return, the office is warned before the free days run out,
 * and detention the customer caused is recharged to them as a disbursement.
 */
final class Containers
{
    public const STATUS = ['out_full' => 'Out, full', 'unloaded' => 'Unloaded, empty', 'returned' => 'Returned'];

    /** Days out, free days left, detention days and the detention owed so far. */
    public static function position(array $container, ?string $asOf = null): array
    {
        $end = $container['returned_at'] !== null ? (string) $container['returned_at'] : ($asOf ?? date('Y-m-d H:i:s'));
        $days = max(0, (int) floor((strtotime(substr($end, 0, 10)) - strtotime(substr((string) $container['picked_up_at'], 0, 10))) / 86400) + 1);
        $free = (int) $container['free_days'];
        $detentionDays = max(0, $days - $free);

        return [
            'days_out' => $days,
            'free_days_left' => $free - $days,
            'detention_days' => $detentionDays,
            'detention' => round($detentionDays * (float) $container['detention_rate'], 2),
            'free_until' => date('Y-m-d', (int) strtotime(substr((string) $container['picked_up_at'], 0, 10) . ' +' . max(0, $free - 1) . ' days')),
        ];
    }

    public static function applied(string $action, array $record, string $reason, ?int $actor): string
    {
        $db = Database::connection();
        $id = (int) $record['id'];

        if ($action === 'unload') {
            $db->prepare('UPDATE containers SET unloaded_at = COALESCE(unloaded_at, NOW()) WHERE id = ?')->execute([$id]);

            return sprintf('%s is unloaded. Return it empty to %s before the free days end.', $record['container_no'], $record['return_depot'] ?: 'the depot the line named');
        }

        if ($action === 'return') {
            $db->prepare('UPDATE containers SET unloaded_at = COALESCE(unloaded_at, NOW()), returned_at = COALESCE(returned_at, NOW()) WHERE id = ?')->execute([$id]);
            $fresh = self::row($id);
            $position = self::position($fresh);

            return $position['detention_days'] > 0
                ? sprintf('%s is back after %d days: %d day(s) of detention, %s %s. Recharge it to the customer if they caused it.',
                    $record['container_no'], $position['days_out'], $position['detention_days'], $fresh['currency'], number_format($position['detention'], 2))
                : sprintf('%s is back after %d days, inside the free time. No detention.', $record['container_no'], $position['days_out']);
        }

        return 'Done.';
    }

    /**
     * Puts the detention on the customer's list of disbursements, to go on their
     * next invoice at cost.
     */
    public static function recharge(int $id): int
    {
        $container = self::row($id);
        if ($container === null) {
            throw new \RuntimeException('That container is not on file.');
        }
        if (!empty($container['disbursement_id'])) {
            throw new \RuntimeException('The detention on this container has already been recharged.');
        }
        if (empty($container['customer_id'])) {
            throw new \RuntimeException('Name the customer on the container first.');
        }

        $position = self::position($container);
        if ($position['detention'] <= 0) {
            throw new \RuntimeException('There is no detention on this container to recharge.');
        }

        $disbursementId = LogisticsData::save('disbursements', null, [
            'disbursement_code' => '',
            'customer_id' => (string) $container['customer_id'],
            'trip_id' => (string) ($container['trip_id'] ?? ''),
            'shipment_id' => (string) ($container['shipment_id'] ?? ''),
            'supplier_id' => (string) ($container['shipping_line_id'] ?? ''),
            'category' => 'Container detention',
            'description' => sprintf('Detention on %s: %d day(s) beyond %d free days at %s %s a day',
                $container['container_no'], $position['detention_days'], (int) $container['free_days'], $container['currency'], number_format((float) $container['detention_rate'], 2)),
            'amount' => (string) $position['detention'],
            'currency' => (string) $container['currency'],
            'paid_at' => date('Y-m-d'),
            'method' => 'bank_transfer',
            'reference' => (string) ($container['return_interchange_no'] ?? ''),
        ]);

        Database::connection()->prepare('UPDATE containers SET disbursement_id = ? WHERE id = ?')->execute([$disbursementId, $id]);
        AuditLog::record('container.detention_recharged', 'containers', (string) $container['tracking_code'], null, ['amount' => $position['detention']]);

        return $disbursementId;
    }

    /** Warnings before the free days run out, and once detention has started. */
    public static function alerts(callable $raise): void
    {
        try {
            $rows = Database::connection()->query(
                "SELECT * FROM containers WHERE deleted_at IS NULL AND status <> 'returned'"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return;
        }

        $warnDays = Settings::int('container_alert_days', 2);
        foreach ($rows as $container) {
            $position = self::position($container);
            if ($position['detention_days'] > 0) {
                $raise(
                    'container-detention-' . $container['id'],
                    'Container on detention',
                    sprintf('%s has been out %d days, %d past its free time. Detention so far: %s %s. Return it empty to %s.',
                        $container['container_no'], $position['days_out'], $position['detention_days'], $container['currency'],
                        number_format($position['detention'], 2), $container['return_depot'] ?: 'the line\'s depot'),
                    'danger'
                );
            } elseif ($position['free_days_left'] <= $warnDays) {
                $raise(
                    'container-free-' . $container['id'],
                    'Container free time ending',
                    sprintf('%s must be back empty by %s, or detention of %s %s a day starts.',
                        $container['container_no'], $position['free_until'], $container['currency'], number_format((float) $container['detention_rate'], 2)),
                    'warning'
                );
            }
        }
    }

    private static function row(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM containers WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
