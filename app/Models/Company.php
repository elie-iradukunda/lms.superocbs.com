<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The companies of the group (specification, section 3).
 *
 * Every operational record belongs to one company. A person works inside the
 * company they belong to; someone allowed to see all companies (the head of
 * the business, Admin) can switch to another one or look at the whole group.
 *
 * A table takes part simply by having a `company_id` column. Lists and
 * drop-downs filter on it, and a new record is stamped with the company the
 * person is working in. A row written with no company (by older code, or a
 * supplier shared by the group) is read as belonging to the default company,
 * unless the table is one whose rows may be shared by the whole group.
 */
final class Company
{
    /** Tables whose rows may belong to nobody in particular: shared by the group. */
    public const SHARED_TABLES = ['customers', 'suppliers', 'payment_methods', 'gl_accounts', 'routes', 'document_types', 'form_types', 'gps_providers', 'geofences', 'border_posts'];

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @var array<string, bool> */
    private static array $hasColumn = [];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /** @return array<int, array<string, mixed>> id => company row, active ones in their order */
    public static function all(bool $activeOnly = true): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $rows = self::db()->query('SELECT * FROM companies WHERE deleted_at IS NULL ORDER BY sort_order, company_name')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    self::$cache[(int) $row['id']] = $row;
                }
            } catch (\Throwable) {
                self::$cache = [];
            }
        }

        return $activeOnly
            ? array_filter(self::$cache, static fn (array $row): bool => (int) $row['is_active'] === 1)
            : self::$cache;
    }

    public static function find(?int $id): ?array
    {
        return $id === null ? null : (self::all(false)[$id] ?? null);
    }

    /** @return array<int, string> id => name, for drop-downs */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $id => $row) {
            $options[$id] = (string) ($row['short_name'] ?: $row['company_name']);
        }

        return $options;
    }

    public static function name(?int $id): string
    {
        $row = self::find($id);

        return $row === null ? '' : (string) ($row['short_name'] ?: $row['company_name']);
    }

    /** The company a record goes into when nothing else says which. */
    public static function defaultId(): int
    {
        $configured = Settings::int('default_company_id', 0);
        if ($configured > 0 && self::find($configured) !== null) {
            return $configured;
        }

        $first = array_key_first(self::all());

        return $first === null ? 1 : (int) $first;
    }

    /** The currency a company keeps its books in. */
    public static function baseCurrency(?int $id = null): string
    {
        $row = self::find($id ?? \current_company_id());
        $code = strtoupper(trim((string) ($row['base_currency'] ?? '')));

        return $code !== '' ? $code : Currency::base();
    }

    /** Does this table carry a company? Read once per table from the database itself. */
    public static function tableHasCompany(string $table): bool
    {
        if (!array_key_exists($table, self::$hasColumn)) {
            try {
                $statement = self::db()->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'company_id'"
                );
                $statement->execute([$table]);
                self::$hasColumn[$table] = (int) $statement->fetchColumn() > 0;
            } catch (\Throwable) {
                self::$hasColumn[$table] = false;
            }
        }

        return self::$hasColumn[$table];
    }

    /**
     * The WHERE fragment that keeps a query inside the companies the person may
     * see, or null when they are looking at the whole group.
     *
     * The ids are written into the SQL as integers rather than bound, so the
     * fragment can join a query that uses named placeholders as well as one
     * that uses question marks.
     */
    public static function condition(string $table, string $column, ?array $scope = null): ?string
    {
        $scope ??= \company_scope();
        if ($scope === null || !self::tableHasCompany($table)) {
            return null;
        }

        $ids = implode(', ', array_map('intval', $scope === [] ? [0] : $scope));
        if (in_array($table, self::SHARED_TABLES, true)) {
            return "({$column} IS NULL OR {$column} IN ({$ids}))";
        }

        // A row written without a company belongs to the default company.
        return 'COALESCE(' . $column . ', ' . self::defaultId() . ") IN ({$ids})";
    }

    /** May the person open this record of this table? */
    public static function owns(string $table, array $record): bool
    {
        $scope = \company_scope();
        if ($scope === null || !self::tableHasCompany($table) || !array_key_exists('company_id', $record)) {
            return true;
        }
        if ($record['company_id'] === null) {
            return in_array($table, self::SHARED_TABLES, true) || in_array(self::defaultId(), $scope, true);
        }

        return in_array((int) $record['company_id'], $scope, true);
    }

    /** The details printed at the top of every form for this company. */
    public static function header(?int $id = null): array
    {
        $row = self::find($id ?? \current_company_id()) ?? [];

        return [
            'name' => (string) ($row['company_name'] ?? \company_name()),
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'tin' => (string) ($row['tin_number'] ?? ''),
            'logo' => (string) ($row['logo_file'] ?? ''),
            'stamp' => (string) ($row['stamp_file'] ?? ''),
            'currency' => self::baseCurrency(isset($row['id']) ? (int) $row['id'] : null),
        ];
    }

    public static function flush(): void
    {
        self::$cache = null;
        self::$hasColumn = [];
    }
}
