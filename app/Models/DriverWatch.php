<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Drivers leaving without notice (section 6): a driver on a trip whose phone
 * has not been heard from for the set hours (24 by default), or a truck parked
 * with no handover for the set hours, is reported to the OM and HR once.
 */
final class DriverWatch
{
    public static function sweep(): int
    {
        $db = Database::connection();
        $count = 0;

        $hours = max(1, Settings::int('driver_unreachable_hours', 24));
        $rows = $db->query(
            "SELECT d.id, d.full_name, d.phone, d.company_id, d.last_seen_at, t.reference_code, v.plate_number
               FROM drivers d
               INNER JOIN trips t ON t.driver_id = d.id AND t.deleted_at IS NULL AND t.status IN ('on_road', 'at_turnaround', 'loading', 'offloading', 'returning', 'arrived')
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
              WHERE d.deleted_at IS NULL AND (d.last_seen_at IS NULL OR d.last_seen_at < DATE_SUB(NOW(), INTERVAL {$hours} HOUR))"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $since = $row['last_seen_at'] !== null ? date('Y-m-d-H', (int) strtotime((string) $row['last_seen_at'])) : 'never';
            Notifier::forCompany($row['company_id'] !== null ? (int) $row['company_id'] : null, static fn () => Notifier::raiseOnce(
                'unreachable-' . $row['id'] . '-' . $since,
                ['logistics_manager'],
                'Driver unreachable',
                sprintf('%s (%s, %s) has not been heard from %s. Call %s.', $row['full_name'], $row['reference_code'], $row['plate_number'] ?? '', $row['last_seen_at'] !== null ? 'since ' . human_date((string) $row['last_seen_at'], true) : 'in the app yet', $row['phone'] ?: 'the driver'),
                'drivers',
                'danger',
                'drivers',
                (string) $row['id']
            ));
            $count++;
        }

        // A truck back at base with no handover after the set hours.
        $parked = max(1, Settings::int('parked_without_handover_hours', 12));
        $rows = $db->query(
            "SELECT t.id, t.reference_code, t.company_id, t.arrival_at, v.plate_number, d.full_name
               FROM trips t LEFT JOIN vehicles v ON v.id = t.vehicle_id LEFT JOIN drivers d ON d.id = t.driver_id
              WHERE t.deleted_at IS NULL AND t.status = 'arrived' AND t.arrival_at < DATE_SUB(NOW(), INTERVAL {$parked} HOUR)
                AND NOT EXISTS (SELECT 1 FROM vehicle_handovers h WHERE h.trip_id = t.id AND h.status IN ('submitted', 'confirmed') AND h.deleted_at IS NULL)"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            Notifier::forCompany($row['company_id'] !== null ? (int) $row['company_id'] : null, static fn () => Notifier::raiseOnce(
                'nohandover-' . $row['id'],
                ['logistics_manager'],
                'Truck parked with no handover',
                sprintf('%s (%s) arrived %s and %s has not handed it over.', $row['plate_number'], $row['reference_code'], human_date((string) $row['arrival_at'], true), $row['full_name'] ?: 'the driver'),
                'trips',
                'warning',
                'trips',
                (string) $row['id']
            ));
            $count++;
        }

        return $count;
    }
}
