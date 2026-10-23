<?php

declare(strict_types=1);

namespace Models;

/**
 * Where the rules of the ERI specification plug into the generic forms.
 *
 * The generic engine (LogisticsData) validates, fills in, saves and deletes any
 * module. Each area of the specification keeps its own rules in its own class
 * and lists the modules it answers for here, instead of growing that engine.
 *
 * An area class may define any of:
 *   rules(string $key, array $input, ?int $id): list<string>        errors
 *   derive(string $key, array $columns, ?int $id): array             columns
 *   saved(string $key, int $id, array $columns, array $before): void
 *   deleting(string $key, array $record): void                        throws to refuse
 */
final class Hooks
{
    /** module key => class that holds its rules */
    private const AREAS = [
        'vehicles' => FleetForms::class,
        'trailers' => FleetForms::class,
        'drivers' => FleetForms::class,
        'driver_documents' => FleetForms::class,
        'vehicle_documents' => FleetForms::class,
        'document_types' => FleetForms::class,
        'driver_momo_changes' => FleetForms::class,
        'tyre_logs' => ServiceLogs::class,
        'crane_logs' => ServiceLogs::class,
        'shortage_rules' => Shortages::class,
        'rates' => CustomerBilling::class,
    ];

    private static function area(string $key, string $method): ?string
    {
        $class = self::AREAS[$key] ?? null;

        return $class !== null && method_exists($class, $method) ? $class : null;
    }

    /** @return list<string> */
    public static function rules(string $key, array $input, ?int $id): array
    {
        $class = self::area($key, 'rules');

        return $class === null ? [] : $class::rules($key, $input, $id);
    }

    public static function derive(string $key, array $columns, ?int $id): array
    {
        $class = self::area($key, 'derive');

        return $class === null ? $columns : $class::derive($key, $columns, $id);
    }

    public static function saved(string $key, int $id, array $columns, array $before): void
    {
        $class = self::area($key, 'saved');
        if ($class !== null) {
            $class::saved($key, $id, $columns, $before);
        }
    }

    public static function deleting(string $key, array $record): void
    {
        $class = self::area($key, 'deleting');
        if ($class !== null) {
            $class::deleting($key, $record);
        }
    }

    /** Does saving this module need the row as it was before? */
    public static function wantsBefore(string $key): bool
    {
        return isset(self::AREAS[$key]);
    }
}
