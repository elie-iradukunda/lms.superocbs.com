<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The lists a company decides for itself.
 *
 * Vehicle types, expense categories, units of measure and the rest used to be
 * PHP arrays, so a company that hauled cement in a "Cement bulker" had to wait
 * for a developer. They are rows in `lookup_values` now, and the Reference lists
 * page adds, renames, reorders and retires them.
 *
 * The defaults below are the starting point a new database is seeded with, and
 * the fallback used when the table cannot be read (during the first migration,
 * for instance). They are not a limit: nothing here stops a list growing.
 *
 * Two kinds of list deliberately stayed in code:
 *
 *  - Statuses, priorities and cargo types, because the system reasons about
 *    them. Adding "Half delivered" to a status list would not teach dispatch
 *    what to do with it, so those remain database enums.
 *  - Statement sections and export formats, because the accounting books group
 *    by the first and only three writers exist for the second.
 */
final class Lookup
{
    /**
     * key => [label, description, targets, defaults]
     *
     * `targets` are the table.column pairs that store a value from the list.
     * Renaming an entry rewrites every one of them, so a rename never orphans a
     * record.
     */
    public const LISTS = [
        'vehicle_type' => [
            'label' => 'Vehicle types',
            'description' => 'The shapes of vehicle in the fleet. Used when registering a vehicle and when a rate card is priced for one kind of vehicle.',
            'targets' => [['vehicles', 'vehicle_type'], ['rate_cards', 'vehicle_type']],
            'defaults' => ['Delivery truck', 'Box truck', 'Refrigerated truck', 'Pickup', 'Van', 'Tanker', 'Trailer', 'Motorcycle'],
        ],
        'licence_class' => [
            'label' => 'Driving licence classes',
            'description' => 'The licence classes issued in your country. A driver holds one of these.',
            'targets' => [['drivers', 'license_class']],
            'defaults' => ['A', 'B', 'C', 'D', 'E', 'B/C', 'C/D'],
        ],
        'expense_category' => [
            'label' => 'Expense categories',
            'description' => 'How a logistics expense is classified when it is claimed.',
            'targets' => [['expenses', 'category']],
            'defaults' => ['Fuel', 'Toll', 'Repair', 'Allowance', 'Parking', 'Insurance', 'Loading', 'Permit'],
        ],
        'item_category' => [
            'label' => 'Stock categories',
            'description' => 'How stock items are grouped in the warehouse.',
            'targets' => [['inventory_items', 'category']],
            'defaults' => ['Food', 'Packaging', 'Spare parts', 'Consumables', 'Cold chain', 'Equipment', 'Stationery'],
        ],
        'unit_of_measure' => [
            'label' => 'Units of measure',
            'description' => 'What a stock quantity is counted in.',
            'targets' => [['inventory_items', 'unit_of_measure']],
            'defaults' => ['Unit', 'Kg', 'Litre', 'Box', 'Sack', 'Crate', 'Pallet', 'Carton'],
        ],
        'supplier_category' => [
            'label' => 'Supplier categories',
            'description' => 'What a supplier mainly provides.',
            'targets' => [['suppliers', 'category']],
            'defaults' => ['Spare parts', 'Fuel', 'Packaging', 'Cold chain', 'Equipment', 'Services', 'Consumables'],
        ],
        'procurement_category' => [
            'label' => 'Procurement categories',
            'description' => 'What a purchase request is for.',
            'targets' => [['purchase_requests', 'category']],
            'defaults' => ['Spare parts', 'Fuel', 'Packaging', 'Cold chain', 'Equipment', 'Services', 'Food'],
        ],
        'department' => [
            'label' => 'Departments',
            'description' => 'The parts of the company a staff login belongs to.',
            'targets' => [['users', 'department']],
            'defaults' => ['Operations', 'Fleet', 'Warehouse', 'Finance', 'Management', 'Administration'],
        ],
        'border_charge' => [
            'label' => 'Border charge types',
            'description' => 'What a truck pays to get through a crossing: duty, the clearing agent, the weighbridge, an escort.',
            'targets' => [['border_charges', 'charge_type']],
            'defaults' => ['Customs duty', 'Import VAT', 'Withholding tax', 'Clearing agent fee', 'Transit bond', 'Weighbridge', 'Escort fee', 'Parking and storage', 'Road toll', 'Other border charge'],
        ],
        'border_document' => [
            'label' => 'Border documents',
            'description' => 'The papers a crossing can be held on. Which ones apply depends on the corridor and the cargo.',
            'targets' => [['border_documents', 'document_type']],
            'defaults' => ['Customs declaration', 'T1 transit bond', 'Commercial invoice', 'Packing list', 'Certificate of origin', 'Bill of lading', 'Weighbridge ticket', 'COMESA yellow card', 'Road transit permit', 'Phytosanitary certificate', 'Driver passport'],
        ],
        'report_period' => [
            'label' => 'Report periods',
            'description' => 'How often a saved report covers its figures.',
            'targets' => [['reports', 'period_label']],
            'defaults' => ['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly'],
        ],
    ];

    /** list_key => [value => label], loaded once per request. */
    private static ?array $cache = null;

    /** The choices a form offers for one list: active entries, in the order set on the page. */
    public static function options(string $key): array
    {
        return self::table()[$key] ?? self::fallback($key);
    }

    /** list_key => human label, for the Reference lists page itself. */
    public static function lists(): array
    {
        return array_map(static fn (array $list): string => $list['label'], self::LISTS);
    }

    public static function describe(string $key): string
    {
        return self::LISTS[$key]['description'] ?? '';
    }

    /**
     * Every list, read in one query so building the module registry costs one
     * round trip rather than one per list.
     */
    private static function table(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];

        try {
            $sql = 'SELECT list_key, value, label FROM lookup_values
                    WHERE deleted_at IS NULL AND is_active = 1
                    ORDER BY list_key, sort_order, label';
            foreach (Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                self::$cache[$row['list_key']][$row['value']] = $row['label'] !== '' && $row['label'] !== null
                    ? (string) $row['label']
                    : (string) $row['value'];
            }
        } catch (\Throwable) {
            // Before the table exists — the first migration, say — the defaults
            // stand in, so forms are never blank.
            self::$cache = [];
            foreach (array_keys(self::LISTS) as $key) {
                self::$cache[$key] = self::fallback($key);
            }
        }

        return self::$cache;
    }

    private static function fallback(string $key): array
    {
        $defaults = self::LISTS[$key]['defaults'] ?? [];

        return $defaults === [] ? [] : array_combine($defaults, $defaults);
    }

    /** Forget the cached lists after the page has changed one. */
    public static function flush(): void
    {
        self::$cache = null;
        Schema::flush();
    }

    /**
     * Follow a renamed entry into the records that carry it.
     *
     * A company that decides "Allowance" should read "Driver allowance" expects
     * the expenses already filed under it to move with the name, not to fall out
     * of the list. Returns how many records were rewritten.
     */
    public static function rename(string $listKey, string $old, string $new): int
    {
        if ($old === '' || $new === '' || $old === $new) {
            return 0;
        }

        $touched = 0;
        $db = Database::connection();

        foreach (self::LISTS[$listKey]['targets'] ?? [] as [$table, $column]) {
            if (!self::targetExists($table, $column)) {
                continue;
            }
            $statement = $db->prepare("UPDATE {$table} SET {$column} = ? WHERE {$column} = ?");
            $statement->execute([$new, $old]);
            $touched += $statement->rowCount();
        }

        return $touched;
    }

    /** How many records currently carry one entry — shown before it is retired. */
    public static function usageCount(string $listKey, string $value): int
    {
        $total = 0;
        $db = Database::connection();

        foreach (self::LISTS[$listKey]['targets'] ?? [] as [$table, $column]) {
            if (!self::targetExists($table, $column)) {
                continue;
            }
            $statement = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
            $statement->execute([$value]);
            $total += (int) $statement->fetchColumn();
        }

        return $total;
    }

    /**
     * Only the table.column pairs named above are ever interpolated into SQL,
     * and they are checked against the live database before use.
     */
    private static function targetExists(string $table, string $column): bool
    {
        static $known = [];
        $signature = $table . '.' . $column;

        if (!isset($known[$signature])) {
            $statement = Database::connection()->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $statement->execute([$table, $column]);
            $known[$signature] = (int) $statement->fetchColumn() > 0;
        }

        return $known[$signature];
    }

    /**
     * Put the starting lists in place. Safe to run again: an entry a company has
     * renamed or retired is left exactly as they left it, and only genuinely
     * missing keys are added.
     */
    public static function seed(): int
    {
        $db = Database::connection();
        $insert = $db->prepare(
            'INSERT IGNORE INTO lookup_values (list_key, value, label, sort_order, is_active)
             VALUES (?, ?, ?, ?, 1)'
        );

        $added = 0;
        foreach (self::LISTS as $key => $list) {
            $order = 10;
            foreach ($list['defaults'] as $value) {
                $insert->execute([$key, $value, $value, $order]);
                $added += $insert->rowCount();
                $order += 10;
            }
        }

        self::$cache = null;

        return $added;
    }
}
