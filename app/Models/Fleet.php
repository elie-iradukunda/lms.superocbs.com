<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The fleet rules of sections 5 and 6, in one place.
 *
 * A truck, trailer or driver may go on a trip only when every document its
 * type requires is uploaded and in date for the whole expected trip, a truck is
 * not due for service (unless the OM records why it will be serviced on
 * return), and the load is within the maximum. Which documents are required is
 * read from `document_types`, which Admin keeps.
 */
final class Fleet
{
    /** The truck statuses that replace the Excel status list (section 5). */
    public const VEHICLE_STATUS = [
        'available' => 'Available (parked, empty)',
        'assigned' => 'Assigned',
        'loading' => 'Loading',
        'on_road' => 'Loaded, on the road',
        'offloading' => 'Offloading',
        'returning' => 'Empty, returning',
        'in_garage' => 'In garage',
        'off_road' => 'Off road',
        'inactive' => 'Inactive',
    ];

    /** Who sets each status, shown next to it. */
    public const VEHICLE_STATUS_SOURCE = [
        'available' => 'Trip closed, or garage job closed',
        'assigned' => 'OM puts it on a trip',
        'loading' => 'Driver or OM at the loading point',
        'on_road' => 'Driver confirms departure; GPS confirms movement',
        'offloading' => 'Driver at the customer',
        'returning' => 'Delivery note done',
        'in_garage' => 'Job card opened',
        'off_road' => 'OM, with a reason',
        'inactive' => 'Sold or retired',
    ];

    /** The statuses in which a truck is busy with a trip. */
    public const ON_TRIP = ['assigned', 'loading', 'on_road', 'offloading', 'returning'];

    public const TRAILER_STATUS = ['available' => 'Available', 'in_use' => 'In use', 'in_garage' => 'In garage', 'off_road' => 'Off road', 'inactive' => 'Inactive'];

    /** Employment status as HR speaks of it (section 6). */
    public const DRIVER_STATUS = ['active' => 'Active', 'on_trip' => 'On trip', 'on_leave' => 'On leave', 'suspended' => 'Suspended', 'left' => 'Left'];

    public const FLEETS = ['tanker' => 'Tanker', 'container' => 'Container truck', 'other' => 'Other'];

    public const CONTAINER_SIZES = ['1x20' => '1 × 20 ft', '1x40' => '1 × 40 ft', '2x20' => '2 × 20 ft'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    // ------------------------------------------------------------ documents

    /**
     * The document types a vehicle, trailer or driver of this fleet needs.
     *
     * @return list<array<string, mixed>>
     */
    public static function documentTypes(string $appliesTo, ?string $fleet = null, ?bool $crossBorder = null, bool $tripOnly = false): array
    {
        $sql = "SELECT * FROM document_types WHERE deleted_at IS NULL AND is_active = 1 AND applies_to = ?";
        $parameters = [$appliesTo];
        if ($fleet !== null && $fleet !== '' && $fleet !== 'all' && $fleet !== 'other') {
            $sql .= " AND fleet IN ('all', ?)";
            $parameters[] = $fleet;
        } elseif ($fleet !== null) {
            $sql .= " AND fleet = 'all'";
        }
        if ($crossBorder === false) {
            $sql .= ' AND cross_border_only = 0';
        }
        if ($tripOnly) {
            $sql .= ' AND required_for_trip = 1';
        }
        $sql .= ' ORDER BY sort_order, type_name';

        try {
            $statement = self::db()->prepare($sql);
            $statement->execute($parameters);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, string> code => name, for a drop-down */
    public static function documentTypeOptions(string $appliesTo): array
    {
        $options = [];
        foreach (self::documentTypes($appliesTo) as $type) {
            $options[(string) $type['type_code']] = (string) $type['type_name'];
        }

        return $options;
    }

    /**
     * The newest paper of each type an owner holds.
     *
     * @return array<string, array<string, mixed>> type code => paper
     */
    public static function papers(string $ownerType, int $ownerId): array
    {
        if ($ownerType === 'driver') {
            $statement = self::db()->prepare(
                "SELECT id, document_type, document_number, issue_date AS issued_on, expiry_date AS expires_on, document_file, status
                   FROM driver_documents
                  WHERE driver_id = ? AND deleted_at IS NULL AND status <> 'rejected'
                  ORDER BY COALESCE(expiry_date, '9999-12-31') DESC, id DESC"
            );
        } else {
            $column = $ownerType === 'trailer' ? 'trailer_id' : 'vehicle_id';
            $statement = self::db()->prepare(
                "SELECT id, document_type, document_number, issued_on, expires_on, document_file, status
                   FROM vehicle_documents
                  WHERE {$column} = ? AND owner_type = ? AND deleted_at IS NULL AND status <> 'cancelled'
                  ORDER BY expires_on DESC, id DESC"
            );
            $statement->execute([$ownerId, $ownerType]);

            return self::newestByType($statement->fetchAll(PDO::FETCH_ASSOC));
        }
        $statement->execute([$ownerId]);

        return self::newestByType($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function newestByType(array $rows): array
    {
        $papers = [];
        foreach ($rows as $row) {
            $papers[(string) $row['document_type']] ??= $row;
        }

        return $papers;
    }

    /**
     * What stops this owner from going on a trip that ends on $untilDate.
     *
     * @return list<string> one sentence per problem; empty when everything is in order
     */
    public static function documentProblems(string $ownerType, int $ownerId, string $label, ?string $fleet, string $untilDate, bool $crossBorder): array
    {
        $problems = [];
        $papers = self::papers($ownerType, $ownerId);
        foreach (self::documentTypes($ownerType, $fleet, $crossBorder, true) as $type) {
            $code = (string) $type['type_code'];
            $name = (string) $type['type_name'];
            $paper = $papers[$code] ?? null;
            if ($paper === null) {
                $problems[] = sprintf('%s: %s is missing.', $label, $name);
                continue;
            }
            // A document on file with a number and an in-date expiry satisfies
            // "must be in date to go on a trip". Whether its scan is attached is
            // a records matter, chased on the document page, not a reason to hold
            // the truck at the gate.
            $expires = (string) ($paper['expires_on'] ?? '');
            if ($expires === '') {
                continue;
            }
            if ($expires < date('Y-m-d')) {
                $problems[] = sprintf('%s: %s expired on %s.', $label, $name, human_date($expires));
            } elseif ($expires < $untilDate) {
                $problems[] = sprintf('%s: %s expires on %s, before the trip is expected back (%s).', $label, $name, human_date($expires), human_date($untilDate));
            }
        }

        return $problems;
    }

    /** A paper's status from its expiry date and the warning days of its type. */
    public static function paperStatus(?string $expires, string $current = 'valid'): string
    {
        if (in_array($current, ['cancelled', 'rejected', 'pending'], true)) {
            return $current;
        }
        if ($expires === null || $expires === '') {
            return 'valid';
        }
        $days = days_until($expires);
        $warn = max(self::alertDays());

        return match (true) {
            $days === null => 'valid',
            $days < 0 => 'expired',
            $days <= $warn => 'expiring',
            default => 'valid',
        };
    }

    /** @return list<int> the days before expiry people are warned, largest first */
    public static function alertDays(?string $list = null): array
    {
        $list ??= Settings::get('document_alert_days_list', '30,14,3');
        $days = array_values(array_filter(array_map('intval', explode(',', $list)), static fn (int $d): bool => $d > 0));
        rsort($days);

        return $days === [] ? [30, 14, 3] : $days;
    }

    // -------------------------------------------------------------- service

    public static function serviceInterval(array $vehicle): int
    {
        $own = (int) ($vehicle['service_interval_km'] ?? 0);

        return $own > 0 ? $own : max(500, Settings::int('service_interval_km', 7000));
    }

    /** The odometer reading the next service falls at. */
    public static function nextServiceKm(array $vehicle): int
    {
        $set = (int) ($vehicle['next_service_km'] ?? 0);
        if ($set > 0) {
            return $set;
        }

        return (int) ($vehicle['last_service_km'] ?? 0) + self::serviceInterval($vehicle);
    }

    /**
     * Where a truck stands against its service.
     *
     * @return array{state: string, next_km: int, remaining_km: int, label: string}
     */
    public static function serviceState(array $vehicle): array
    {
        $next = self::nextServiceKm($vehicle);
        $remaining = $next - (int) ($vehicle['mileage'] ?? 0);
        $warning = max(0, Settings::int('service_warning_km', 500));

        $state = match (true) {
            $remaining <= 0 => 'due',
            $remaining <= $warning => 'warning',
            default => 'ok',
        };

        $label = match ($state) {
            'due' => sprintf('Service due: %s km over (due at %s km)', number_format(abs($remaining)), number_format($next)),
            'warning' => sprintf('Service in %s km (at %s km)', number_format($remaining), number_format($next)),
            default => sprintf('Next service at %s km', number_format($next)),
        };

        return ['state' => $state, 'next_km' => $next, 'remaining_km' => $remaining, 'label' => $label];
    }

    /** After a service: the next one is one interval later (section 12, step 6). */
    public static function recordService(int $vehicleId, int $odometer): void
    {
        $vehicle = self::vehicle($vehicleId);
        if ($vehicle === null) {
            return;
        }
        $odometer = max($odometer, (int) $vehicle['mileage']);
        self::db()->prepare(
            'UPDATE vehicles SET last_service_km = ?, next_service_km = ?, mileage = GREATEST(mileage, ?), service_first_on_return = 0 WHERE id = ?'
        )->execute([$odometer, $odometer + self::serviceInterval($vehicle), $odometer, $vehicleId]);
    }

    // ------------------------------------------------------------ the record

    public static function vehicle(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM vehicles WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function trailer(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM trailers WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function driver(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM drivers WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function setVehicleStatus(int $vehicleId, string $status, ?string $reason = null): void
    {
        if (!array_key_exists($status, self::VEHICLE_STATUS)) {
            throw new \InvalidArgumentException('Unknown truck status: ' . $status);
        }
        self::db()->prepare('UPDATE vehicles SET status = ?, off_road_reason = ? WHERE id = ?')
            ->execute([$status, $status === 'off_road' ? $reason : null, $vehicleId]);
    }

    /**
     * The OM's decision on a new MoMo number: approved, the driver is paid on
     * it from now on; rejected, nothing changes. The driver is told either way.
     */
    public static function momoDecided(string $action, array $change, string $reason, ?int $actor): string
    {
        $db = self::db();
        $db->prepare('UPDATE driver_momo_changes SET decided_by = ?, decided_at = NOW(), reason = ? WHERE id = ?')
            ->execute([$actor, $reason !== '' ? $reason : null, (int) $change['id']]);

        $driver = self::driver((int) $change['driver_id']);
        if ($driver === null) {
            return 'Done.';
        }

        if ($action === 'approve') {
            $db->prepare('UPDATE drivers SET momo_number = ?, momo_name = ?, phone = COALESCE(NULLIF(?, \'\'), phone), payment_method = \'momo\' WHERE id = ?')
                ->execute([$change['new_number'], $change['new_name'], (string) ($change['new_phone'] ?? ''), (int) $driver['id']]);
            if (!empty($change['new_phone']) && !empty($driver['user_id'])) {
                $db->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$change['new_phone'], (int) $driver['user_id']]);
            }
            Notifier::toUser($driver['user_id'] !== null ? (int) $driver['user_id'] : null, 'MoMo number approved', sprintf('You will now be paid on %s (%s).', $change['new_number'], $change['new_name']), 'my-trips', 'success');

            return sprintf('%s will now be paid on %s.', $driver['full_name'], $change['new_number']);
        }

        Notifier::toUser($driver['user_id'] !== null ? (int) $driver['user_id'] : null, 'MoMo number not changed', sprintf('Your request to be paid on %s was refused: %s', $change['new_number'], $reason), 'my-trips', 'warning');

        return sprintf('The change for %s was rejected; payments stay on %s.', $driver['full_name'], $driver['momo_number'] ?: 'cash');
    }

    // --------------------------------------------------------------- alerts

    /**
     * Documents expiring at 30, 14 and 3 days, and services at the warning and
     * due marks, each raised once per mark (section 5 and 6 alerts).
     */
    public static function alerts(): int
    {
        $raised = 0;
        $marks = self::alertDays();
        $markFor = static function (int $days) use ($marks): ?int {
            if ($days < 0) {
                return 0;
            }
            $hit = null;
            foreach ($marks as $mark) {
                if ($days <= $mark) {
                    $hit = $mark;
                }
            }

            return $hit;
        };

        try {
            $rows = self::db()->query(
                "SELECT d.id, d.owner_type, d.document_type, d.expires_on, d.company_id,
                        COALESCE(v.plate_number, t.trailer_number) AS owner, dt.type_name, dt.alert_days
                   FROM vehicle_documents d
                   LEFT JOIN vehicles v ON v.id = d.vehicle_id
                   LEFT JOIN trailers t ON t.id = d.trailer_id
                   LEFT JOIN document_types dt ON dt.applies_to = d.owner_type AND dt.type_code = d.document_type
                  WHERE d.deleted_at IS NULL AND d.status <> 'cancelled' AND d.expires_on <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $days = (int) days_until((string) $row['expires_on']);
                $mark = self::markFor($days, (string) ($row['alert_days'] ?? ''), $markFor);
                if ($mark === null) {
                    continue;
                }
                $name = (string) ($row['type_name'] ?: Schema::label((string) $row['document_type']));
                Notifier::forCompany($row['company_id'] !== null ? (int) $row['company_id'] : null, static fn () => Notifier::raiseOnce(
                    sprintf('doc-%s-%d-%d', $row['owner_type'], $row['id'], $mark),
                    ['logistics_manager'],
                    $days < 0 ? 'Document expired' : 'Document expiring',
                    $days < 0
                        ? sprintf('%s of %s expired on %s. The truck cannot go on a trip.', $name, $row['owner'], human_date((string) $row['expires_on']))
                        : sprintf('%s of %s expires in %d day%s (%s).', $name, $row['owner'], $days, $days === 1 ? '' : 's', human_date((string) $row['expires_on'])),
                    'vehicle_documents',
                    $days <= 3 ? 'danger' : 'warning',
                    'vehicle_documents',
                    (string) $row['id']
                ));
                $raised++;
            }

            $rows = self::db()->query(
                "SELECT dd.id, dd.document_type, dd.expiry_date, dd.driver_id, d.full_name, d.user_id, d.company_id, dt.type_name, dt.alert_days
                   FROM driver_documents dd
                   INNER JOIN drivers d ON d.id = dd.driver_id AND d.deleted_at IS NULL AND d.status <> 'left'
                   LEFT JOIN document_types dt ON dt.applies_to = 'driver' AND dt.type_code = dd.document_type
                  WHERE dd.deleted_at IS NULL AND dd.status <> 'rejected' AND dd.expiry_date IS NOT NULL
                    AND dd.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $days = (int) days_until((string) $row['expiry_date']);
                $mark = self::markFor($days, (string) ($row['alert_days'] ?? ''), $markFor);
                if ($mark === null) {
                    continue;
                }
                $name = (string) ($row['type_name'] ?: Schema::label((string) $row['document_type']));
                $message = $days < 0
                    ? sprintf('%s of %s expired on %s.', $name, $row['full_name'], human_date((string) $row['expiry_date']))
                    : sprintf('%s of %s expires in %d day%s (%s).', $name, $row['full_name'], $days, $days === 1 ? '' : 's', human_date((string) $row['expiry_date']));
                // OM, HR and the driver (section 6).
                Notifier::forCompany($row['company_id'] !== null ? (int) $row['company_id'] : null, static function () use ($row, $mark, $days, $message): void {
                    Notifier::raiseOnce(sprintf('ddoc-%d-%d', $row['id'], $mark), ['logistics_manager'], $days < 0 ? 'Driver document expired' : 'Driver document expiring', $message, 'driver_documents', $days <= 3 ? 'danger' : 'warning', 'driver_documents', (string) $row['id']);
                    if (!empty($row['user_id'])) {
                        Notifier::toUserOnce(sprintf('ddoc-u-%d-%d', $row['id'], $mark), (int) $row['user_id'], 'Your document is expiring', $message, 'my-trips', $days <= 3 ? 'danger' : 'warning');
                    }
                });
                $raised++;
            }

            // Service at the warning mark and at the due mark (OM and garage).
            foreach (self::db()->query("SELECT * FROM vehicles WHERE deleted_at IS NULL AND status <> 'inactive'")->fetchAll(PDO::FETCH_ASSOC) as $vehicle) {
                $service = self::serviceState($vehicle);
                if ($service['state'] === 'ok') {
                    continue;
                }
                Notifier::forCompany($vehicle['company_id'] !== null ? (int) $vehicle['company_id'] : null, static fn () => Notifier::raiseOnce(
                    sprintf('service-%d-%d-%s', $vehicle['id'], $service['next_km'], $service['state']),
                    ['logistics_manager', 'warehouse_manager'],
                    $service['state'] === 'due' ? 'Service due' : 'Service soon',
                    sprintf('%s: %s.', $vehicle['plate_number'], $service['label']),
                    'vehicles',
                    $service['state'] === 'due' ? 'danger' : 'warning',
                    'vehicles',
                    (string) $vehicle['id']
                ));
                $raised++;
            }
        } catch (\Throwable $exception) {
            error_log('Fleet alerts failed: ' . $exception->getMessage());
        }

        return $raised;
    }

    /** The warning mark a number of days falls in, by the type's own days when it has them. */
    private static function markFor(int $days, string $typeDays, callable $default): ?int
    {
        if ($typeDays === '') {
            return $default($days);
        }
        if ($days < 0) {
            return 0;
        }
        $hit = null;
        foreach (self::alertDays($typeDays) as $mark) {
            if ($days <= $mark) {
                $hit = $mark;
            }
        }

        return $hit;
    }
}
