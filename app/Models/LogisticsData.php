<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Generic, schema-driven persistence for every logistics module.
 *
 * Records are addressed by their primary key, not by their name, so two people
 * called Jean Bosco no longer share a URL and renaming a record no longer breaks
 * its link. Lists are paginated in SQL, a driver only sees their own rows, and a
 * delete is a soft delete that keeps the row for the audit trail.
 */
final class LogisticsData
{
    public const PER_PAGE_CHOICES = [10, 25, 50, 100];

    private static ?PDO $db = null;

    private static function db(): PDO
    {
        return self::$db ??= Database::connection();
    }

    // ------------------------------------------------------------------ read

    /**
     * One page of a module's list.
     *
     * @param array $query  search, filters, sort, page, per_page from the request
     * @param array $context ['role' => string, 'driver_id' => ?int]
     * @return array{rows: list<array>, total: int, page: int, pages: int, per_page: int, search: string, filters: array}
     */
    public static function listing(string $key, array $query = [], array $context = []): array
    {
        $module = Schema::get($key);
        $alias = $module['alias'];

        [$where, $parameters] = self::conditions($module, $query, $context);
        $from = sprintf('%s %s %s', $module['table'], $alias, $module['joins'] ?? '');

        $total = (int) self::scalar("SELECT COUNT(*) FROM {$from} WHERE {$where}", $parameters);

        $perPage = (int) ($query['per_page'] ?? 25);
        $perPage = in_array($perPage, self::PER_PAGE_CHOICES, true) ? $perPage : 25;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($query['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $order = self::order($module, (string) ($query['sort'] ?? ''), (string) ($query['dir'] ?? ''));
        $select = implode(', ', $module['select']);

        $statement = self::db()->prepare(
            "SELECT {$select} FROM {$from} WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}"
        );
        $statement->execute($parameters);

        return [
            'rows' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'search' => (string) ($query['q'] ?? ''),
            'filters' => self::activeFilters($module, $query),
            'sort' => (string) ($query['sort'] ?? ''),
            'dir' => strtolower((string) ($query['dir'] ?? '')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** The raw row, straight from the module's own table, for the form and the detail page. */
    public static function find(string $key, int $id, array $context = []): ?array
    {
        $module = Schema::get($key);
        $alias = $module['alias'];
        $conditions = ["{$alias}.id = ?"];
        $parameters = [$id];

        if (!empty($module['soft_delete'])) {
            $conditions[] = "{$alias}.deleted_at IS NULL";
        }

        $scope = self::scopeCondition($module, $context);
        $joins = $module['joins'] ?? '';
        if ($scope !== null) {
            $conditions[] = $scope[0];
            $parameters[] = $scope[1];
        }

        $statement = self::db()->prepare(sprintf(
            'SELECT %s.* FROM %s %s %s WHERE %s LIMIT 1',
            $alias,
            $module['table'],
            $alias,
            $scope !== null ? $joins : '',
            implode(' AND ', $conditions)
        ));
        $statement->execute($parameters);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** The human reference of a record, used in headings, audit entries and flash messages. */
    public static function code(string $key, array $record): string
    {
        $module = Schema::get($key);

        return (string) ($record[$module['code']] ?? ('#' . ($record['id'] ?? '')));
    }

    /** Field values already resolved for display: relations become names, enums become labels. */
    public static function display(string $key, array $record): array
    {
        $module = Schema::get($key);
        $display = [];

        foreach ($module['fields'] as $name => $field) {
            $value = $record[$name] ?? null;
            $display[$name] = match ($field['type']) {
                'relation' => self::relationLabel($field, $value),
                'select' => (string) ($field['options'][$value] ?? ($value === null ? '' : Schema::label((string) $value))),
                'checkbox' => ((int) $value === 1 ? 'Yes' : 'No'),
                default => $value === null ? '' : (string) $value,
            };
        }

        return $display;
    }

    /** Child rows (trip stops, invoice lines, maintenance parts) for the detail page. */
    public static function lines(string $key, int $parentId): array
    {
        $module = Schema::get($key);
        if (!isset($module['lines'])) {
            return [];
        }

        $lines = $module['lines'];
        $order = $lines['sequence'] ?? 'id';
        $statement = self::db()->prepare("SELECT * FROM {$lines['table']} WHERE {$lines['parent']} = ? ORDER BY {$order}, id");
        $statement->execute([$parentId]);

        return $statement->fetchAll();
    }

    /** Related record blocks shown under a detail page. */
    public static function related(string $key, int $id, string $roleKey): array
    {
        $module = Schema::get($key);
        $blocks = [];

        foreach ($module['related'] as $block) {
            $permission = $block['permission'] ?? $key;
            if (!Permission::allows($roleKey, $permission, 'view')) {
                continue;
            }

            try {
                $statement = self::db()->prepare($block['sql']);
                $statement->execute(['id' => $id]);
                $block['rows'] = $statement->fetchAll();
            } catch (\Throwable) {
                $block['rows'] = [];
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /** Audit entries for one record, newest first, shown as a timeline on the detail page. */
    public static function history(string $key, string $code, int $limit = 20): array
    {
        $statement = self::db()->prepare(
            'SELECT a.action_name, a.reason, a.metadata, a.created_at, COALESCE(u.full_name, "System") AS actor
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE a.entity_type = ? AND a.entity_id = ?
              ORDER BY a.id DESC
              LIMIT ' . max(1, $limit)
        );
        $statement->execute([$key, $code]);

        return $statement->fetchAll();
    }

    /** Options for a relation or select field, for the form and the filter bar. */
    public static function options(string $key, string $fieldName): array
    {
        $field = Schema::get($key)['fields'][$fieldName] ?? null;
        if ($field === null) {
            return [];
        }

        if ($field['type'] === 'select') {
            return $field['options'] ?? [];
        }

        if ($field['type'] !== 'relation') {
            return [];
        }

        return self::relationOptions($field['relation']);
    }

    /** @return array<int|string, string> */
    public static function relationOptions(array $relation): array
    {
        $table = $relation['table'];
        $labelColumn = $relation['label'];
        if (!self::relationAllowed($table, $labelColumn)) {
            return [];
        }

        $where = $relation['where'] ?? '';
        $parameters = [];

        // A drop-down is a list of records, so it obeys the same limits as a
        // list of records. A driver offered every truck in the yard could file
        // his fuel against one he has never driven; `scope` narrows the offer to
        // the rows that role has any business choosing.
        $scope = $relation['scope'][\current_role()] ?? null;
        if ($scope !== null) {
            $driverId = \current_driver_id();
            if ($driverId === null) {
                return [];
            }
            // With emulated prepares off, MySQL will not accept the same named
            // placeholder twice, and a scope that spans two subqueries needs it
            // twice. Each occurrence gets its own name.
            $seq = 0;
            $scope = preg_replace_callback(
                '/:driver_id\b/',
                static function () use (&$seq, &$parameters, $driverId): string {
                    $name = ':driver_id_' . ++$seq;
                    $parameters[$name] = $driverId;
                    return $name;
                },
                $scope
            );

            $where = $where === '' ? $scope : "({$where}) AND ({$scope})";
        }

        $sql = "SELECT id, {$labelColumn} AS label FROM {$table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $sql .= " ORDER BY {$labelColumn}";

        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        $options = [];
        foreach ($statement->fetchAll() as $row) {
            $options[(int) $row['id']] = (string) $row['label'];
        }

        return $options;
    }

    /**
     * Which parent each option of a dependent relation belongs to.
     *
     * The form uses it to hide the options that do not belong to the parent the
     * user has picked, so the two drop-downs cannot disagree.
     *
     * @return array<int, string> option id => parent id, '' when it has none
     */
    public static function relationParents(array $relation): array
    {
        $column = $relation['depends_on']['column'] ?? null;
        if ($column === null) {
            return [];
        }

        $table = $relation['table'];
        if (!self::relationParentAllowed($table, $column)) {
            return [];
        }

        $sql = "SELECT id, {$column} AS parent FROM {$table}";
        if (($relation['where'] ?? '') !== '') {
            $sql .= " WHERE {$relation['where']}";
        }

        $parents = [];
        foreach (self::db()->query($sql)->fetchAll() as $row) {
            $parents[(int) $row['id']] = (string) ($row['parent'] ?? '');
        }

        return $parents;
    }
    /**
     * What each option of a relation can fill in on the rest of the form.
     *
     * The consignee, their phone and the address were settled when the shipment
     * was booked. Asking for them again on the delivery is not a safeguard, it
     * is a second chance to get them wrong — so the answer travels with the
     * option and the browser writes it in.
     *
     * @return array<int, array<string, string>> option id => target field => value
     */
    public static function relationFills(array $relation): array
    {
        $fills = $relation['fills'] ?? [];
        if ($fills === []) {
            return [];
        }

        $table = $relation['table'];
        $columns = self::columnMeta($table);
        $sources = array_values(array_filter($fills, static fn (string $column): bool => isset($columns[$column])));
        if ($sources === []) {
            return [];
        }

        $sql = 'SELECT id, ' . implode(', ', $sources) . " FROM {$table}";
        if (($relation['where'] ?? '') !== '') {
            $sql .= " WHERE {$relation['where']}";
        }

        $out = [];
        foreach (self::db()->query($sql)->fetchAll() as $row) {
            $values = [];
            foreach ($fills as $target => $column) {
                if (isset($columns[$column])) {
                    $values[$target] = (string) ($row[$column] ?? '');
                }
            }
            $out[(int) $row['id']] = $values;
        }

        return $out;
    }
    /**
     * The loads a trip is carrying, with the consignee details they were booked
     * with. A delivery form for a truck carrying one load has nothing to ask.
     *
     * @return list<array<string, mixed>>
     */
    public static function shipmentsOnTrip(int $tripId): array
    {
        $statement = self::db()->prepare(
            'SELECT id, shipment_code, consignee_name, consignee_phone, destination, destination_warehouse_id
               FROM shipments
              WHERE trip_id = ? AND deleted_at IS NULL
              ORDER BY id'
        );
        $statement->execute([$tripId]);

        return $statement->fetchAll();
    }
    /**
     * How full a trip is.
     *
     * A truck shared between customers is only worth sharing if the person
     * booking the next load can see what is left of it. Without this the
     * question "will another five tonnes fit?" is answered by dispatching and
     * being refused.
     *
     * @return array{weight: float, capacity: ?float, loads: int}
     */
    public static function tripLoad(int $tripId): array
    {
        $statement = self::db()->prepare(
            'SELECT COALESCE(SUM(s.weight_kg), 0) AS weight, COUNT(s.id) AS loads, v.capacity_kg
               FROM trips t
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN shipments s ON s.trip_id = t.id AND s.deleted_at IS NULL
              WHERE t.id = ?
              GROUP BY t.id, v.capacity_kg'
        );
        $statement->execute([$tripId]);
        $row = $statement->fetch() ?: [];

        return [
            'weight' => (float) ($row['weight'] ?? 0),
            'capacity' => isset($row['capacity_kg']) && $row['capacity_kg'] !== null ? (float) $row['capacity_kg'] : null,
            'loads' => (int) ($row['loads'] ?? 0),
        ];
    }
    // --------------------------------------------------------------- writing

    /**
     * Server-side validation for one submitted record.
     *
     * @return list<string> messages; an empty list means the record may be saved
     */
    public static function validate(string $key, array $input, array $files = [], ?int $id = null): array
    {
        $module = Schema::get($key);
        $errors = [];

        foreach (Schema::editableFields($key, \current_role()) as $name => $field) {
            $value = trim((string) ($input[$name] ?? ''));
            $label = $field['label'];

            if ($field['type'] === 'checkbox' || $field['type'] === 'file') {
                continue;
            }

            if ($value === '') {
                if (!empty($field['required']) && empty($field['auto'])) {
                    $errors[] = "{$label} is required.";
                }
                continue;
            }

            switch ($field['type']) {
                case 'select':
                    if (!array_key_exists($value, array_change_key_case($field['options'] ?? [], CASE_LOWER)) && !array_key_exists($value, $field['options'] ?? [])) {
                        $errors[] = "{$label} is not one of the allowed values.";
                    }
                    break;

                case 'relation':
                    if (!self::relationExists($field['relation'], $value)) {
                        $errors[] = "{$label} points at a record that does not exist.";
                        break;
                    }
                    $mismatch = self::dependencyError($field, $module, $input, $value);
                    if ($mismatch !== null) {
                        $errors[] = $mismatch;
                    }
                    break;

                case 'number':
                case 'decimal':
                case 'money':
                    $numeric = str_replace(',', '', $value);
                    if (!is_numeric($numeric)) {
                        $errors[] = "{$label} must be a number.";
                        break;
                    }
                    if (isset($field['min']) && (float) $numeric < (float) $field['min']) {
                        $errors[] = "{$label} cannot be below {$field['min']}.";
                    }
                    if (isset($field['max']) && (float) $numeric > (float) $field['max']) {
                        $errors[] = "{$label} cannot be above {$field['max']}.";
                    }
                    if ($field['type'] !== 'number' && (float) $numeric < 0 && !in_array($name, ['temperature_min_c', 'temperature_max_c'], true)) {
                        $errors[] = "{$label} cannot be negative.";
                    }
                    break;

                case 'date':
                case 'datetime':
                    if (strtotime($value) === false) {
                        $errors[] = "{$label} is not a valid date.";
                    }
                    break;

                case 'email':
                    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                        $errors[] = "{$label} is not a valid email address.";
                    }
                    break;
            }
        }

        foreach (self::fileFields($key) as $name => $field) {
            $error = self::validateUpload($files['file_' . $name] ?? null, $field['label']);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        $errors = array_merge($errors, self::businessRules($key, $module, $input, $id));

        return array_values(array_unique($errors));
    }

    /**
     * A relation that hangs off another field has to agree with it.
     *
     * The form only offers the shipments on the chosen trip, but the offer is
     * made in the browser and a post is not obliged to respect it. Filing a
     * delivery against a load the truck is not carrying is exactly the kind of
     * wrong record that is never noticed until someone chases the cargo.
     */
    private static function dependencyError(array $field, array $module, array $input, string $value): ?string
    {
        $depends = $field['relation']['depends_on'] ?? null;
        if ($depends === null) {
            return null;
        }

        $parent = trim((string) ($input[$depends['field']] ?? ''));
        if ($parent === '') {
            return null;
        }

        $table = $field['relation']['table'];
        $column = $depends['column'];
        if (!self::relationParentAllowed($table, $column)) {
            return null;
        }

        $statement = self::db()->prepare("SELECT {$column} FROM {$table} WHERE id = ?");
        $statement->execute([(int) $value]);
        $owner = (string) ($statement->fetchColumn() ?: '');

        if ($owner === $parent) {
            return null;
        }

        $parentLabel = $module['fields'][$depends['field']]['label'] ?? 'the record above';

        return "{$field['label']} does not belong to the {$parentLabel} you chose.";
    }
    /**
     * Every uniqueness rule the table itself declares, checked before the insert
     * and reported against the field the person actually filled in.
     *
     * Reading the indexes rather than listing them by hand means a new unique
     * column is covered the day it is added, and — more importantly — the person
     * is told *which* box to change. A bare "that reference is already used"
     * leaves them guessing which of fourteen fields it meant.
     *
     * @return list<string>
     */
    private static function uniquenessErrors(array $module, array $input, ?int $id): array
    {
        $errors = [];

        foreach (self::uniqueIndexes($module['table']) as $columns) {
            // Only a rule the form can actually satisfy is worth reporting.
            $editable = array_filter($columns, static fn (string $c): bool => isset($module['fields'][$c]));
            if (count($editable) !== count($columns)) {
                continue;
            }

            $values = [];
            foreach ($columns as $column) {
                $field = $module['fields'][$column];
                $raw = trim((string) ($input[$column] ?? ''));

                // A blank auto reference is generated at save time, and a blank
                // optional column is NULL, which never collides.
                if ($raw === '') {
                    continue 2;
                }

                $values[$column] = $field['type'] === 'relation' ? (int) $raw : $raw;
            }

            if (!self::rowExists($module['table'], $values, $id)) {
                continue;
            }

            $described = [];
            foreach ($values as $column => $stored) {
                $field = $module['fields'][$column];
                $shown = $field['type'] === 'relation'
                    ? self::relationLabel($field, $stored)
                    : (string) ($field['options'][$stored] ?? $stored);
                $described[] = sprintf('%s "%s"', $field['label'], $shown);
            }

            $errors[] = count($described) === 1
                ? $described[0] . ' is already used by another record. Change it to something not yet taken.'
                : 'These values are already used together by another record: ' . implode(' and ', $described) . '.';
        }

        return $errors;
    }

    /**
     * Unique indexes on a table, as a list of column lists. The primary key is
     * left out: it is the record's own identity, not a rule a person can break.
     *
     * @var array<string, list<list<string>>>
     */
    private static array $uniqueIndexes = [];

    /** @return list<list<string>> */
    private static function uniqueIndexes(string $table): array
    {
        if (isset(self::$uniqueIndexes[$table])) {
            return self::$uniqueIndexes[$table];
        }

        $statement = self::db()->prepare(
            "SELECT INDEX_NAME, COLUMN_NAME
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND NON_UNIQUE = 0
                AND INDEX_NAME <> 'PRIMARY'
              ORDER BY INDEX_NAME, SEQ_IN_INDEX"
        );
        $statement->execute([$table]);

        $byIndex = [];
        foreach ($statement->fetchAll() as $row) {
            $byIndex[(string) $row['INDEX_NAME']][] = (string) $row['COLUMN_NAME'];
        }

        // MySQL often carries the same column under two index names; one check
        // per set of columns is enough, and two identical messages are noise.
        $seen = [];
        $unique = [];
        foreach ($byIndex as $columns) {
            $signature = implode('|', $columns);
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $unique[] = $columns;
        }

        return self::$uniqueIndexes[$table] = $unique;
    }

    /** Is there already a row carrying these column values, other than this one? */
    private static function rowExists(string $table, array $values, ?int $id): bool
    {
        // Column names come from information_schema, never from the request.
        $conditions = [];
        $parameters = [];
        foreach ($values as $column => $value) {
            $conditions[] = "`{$column}` = ?";
            $parameters[] = $value;
        }

        if ($id !== null) {
            $conditions[] = 'id <> ?';
            $parameters[] = $id;
        }

        $statement = self::db()->prepare(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, implode(' AND ', $conditions))
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    /** Rules that need more than one field, or a look at the database. */
    private static function businessRules(string $key, array $module, array $input, ?int $id): array
    {
        $errors = [];
        $value = static fn (string $name): string => trim((string) ($input[$name] ?? ''));

        $errors = array_merge($errors, self::uniquenessErrors($module, $input, $id));

        if ($key === 'trips') {
            $plannedOut = $value('planned_departure_at');
            $plannedIn = $value('planned_arrival_at');
            if ($plannedOut !== '' && $plannedIn !== '' && strtotime($plannedIn) < strtotime($plannedOut)) {
                $errors[] = 'Planned arrival cannot be before planned departure.';
            }
            $out = $value('departure_at');
            $in = $value('arrival_at');
            if ($out !== '' && $in !== '' && strtotime($in) < strtotime($out)) {
                $errors[] = 'Actual arrival cannot be before actual departure.';
            }
        }

        if ($key === 'shipments') {
            $min = $value('temperature_min_c');
            $max = $value('temperature_max_c');
            if ($min !== '' && $max !== '' && (float) $max < (float) $min) {
                $errors[] = 'Maximum temperature cannot be below the minimum temperature.';
            }
            if ($value('cargo_type') === 'cold_chain' && $min === '' && $max === '') {
                $errors[] = 'Cold-chain cargo needs a temperature range.';
            }
        }

        if ($key === 'fuel') {
            $now = $value('mileage');
            $vehicleId = $value('vehicle_id');
            if ($now !== '' && $vehicleId !== '') {
                $previous = self::lastOdometer((int) $vehicleId, $id);
                if ($previous !== null && (int) $now < $previous) {
                    $errors[] = sprintf('Odometer %s is below the previous reading of %s km for this vehicle.', $now, number_format($previous));
                }
            }
        }

        if ($key === 'invoices') {
            $issue = $value('issue_date');
            $due = $value('due_date');
            if ($issue !== '' && $due !== '' && strtotime($due) < strtotime($issue)) {
                $errors[] = 'The due date cannot be before the issue date.';
            }
        }

        if ($key === 'rates') {
            $fromDate = $value('effective_from');
            $toDate = $value('effective_to');
            if ($fromDate !== '' && $toDate !== '' && strtotime($toDate) < strtotime($fromDate)) {
                $errors[] = 'The rate cannot expire before it becomes effective.';
            }
        }

        if ($key === 'movements' && $value('movement_type') !== '' && $value('item_id') !== '') {
            $quantity = (float) str_replace(',', '', $value('quantity'));
            if (!Schema::movementAdds($value('movement_type'))) {
                $balance = (float) self::scalar('SELECT quantity FROM inventory_items WHERE id = ?', [(int) $value('item_id')]);
                if ($quantity > $balance) {
                    $errors[] = sprintf('Only %s is on hand; this movement would take the balance below zero.', rtrim(rtrim(number_format($balance, 2, '.', ''), '0'), '.'));
                }
            }
        }

        return $errors;
    }

    /**
     * Inserts or updates one record and returns its id.
     * Uploads, auto references and derived columns are handled here.
     */
    public static function save(string $key, ?int $id, array $input, array $files = []): int
    {
        $module = Schema::get($key);
        $columns = [];

        // Renaming a reference-list entry has to follow the records that carry
        // the old name, which means knowing what it was. Only the modules that
        // ask for it pay for the extra read.
        $before = $id !== null && !empty($module['track_changes'])
            ? self::rowBefore($module['table'], $id)
            : [];

        foreach (Schema::editableFields($key, \current_role()) as $name => $field) {
            if ($field['type'] === 'file') {
                $uploaded = self::storeUpload($key, $name, $files, (string) ($input['existing_' . $name] ?? ''));
                $columns[$name] = $uploaded === '' ? null : $uploaded;
                continue;
            }

            if ($field['type'] === 'checkbox') {
                $columns[$name] = isset($input[$name]) && $input[$name] !== '' && $input[$name] !== '0' ? 1 : 0;
                continue;
            }

            $value = trim((string) ($input[$name] ?? ''));

            if ($value === '' && !empty($field['auto'])) {
                $value = Reference::next($field['auto'], $module['table'], $name);
            }

            $columns[$name] = match ($field['type']) {
                'number' => $value === '' ? null : (int) str_replace(',', '', $value),
                'decimal', 'money' => $value === '' ? null : (float) str_replace(',', '', $value),
                'relation' => $value === '' ? null : (int) $value,
                'date' => $value === '' ? null : date('Y-m-d', (int) strtotime($value)),
                'datetime' => $value === '' ? null : date('Y-m-d H:i:s', (int) strtotime($value)),
                default => $value === '' ? null : $value,
            };
        }

        $columns = self::derive($key, $columns, $id);
        $columns = self::respectNotNull($module['table'], $columns);

        // The insert below fills $id in, so whether this was a creation has to
        // be settled here — the audit line at the end runs long after.
        $isNew = $id === null;

        $db = self::db();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }

        try {
            if ($id === null) {
                $names = array_keys($columns);
                $placeholders = implode(', ', array_fill(0, count($names), '?'));
                $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $module['table'], implode(', ', $names), $placeholders);
                $statement = $db->prepare($sql);
                $statement->execute(array_values($columns));
                $id = (int) $db->lastInsertId();
            } else {
                $assignments = implode(', ', array_map(static fn (string $name): string => "{$name} = ?", array_keys($columns)));
                $sql = sprintf('UPDATE %s SET %s WHERE id = ?', $module['table'], $assignments);
                $statement = $db->prepare($sql);
                $statement->execute([...array_values($columns), $id]);
            }

            self::afterSave($key, $id, $columns, $before);

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        $record = self::find($key, $id) ?? $columns;
        AuditLog::record($isNew ? 'record.created' : 'record.updated', $key, self::code($key, $record));

        return $id;
    }

    /**
     * A blank optional field becomes NULL, but some of those columns are NOT NULL
     * with a database default (fuel type, ownership, unit of measure). Writing
     * NULL into them fails, so fall back to the column's own default and drop the
     * column entirely when it has none.
     *
     * @var array<string, array<string, array{nullable: bool, default: ?string}>>
     */
    private static array $columnMeta = [];

    private static function respectNotNull(string $table, array $columns): array
    {
        $meta = self::columnMeta($table);

        foreach ($columns as $name => $value) {
            if ($value !== null || ($meta[$name]['nullable'] ?? true)) {
                continue;
            }

            $default = $meta[$name]['default'] ?? null;
            if ($default === null) {
                unset($columns[$name]);
                continue;
            }

            $columns[$name] = $default;
        }

        return $columns;
    }

    /** @return array<string, array{nullable: bool, default: ?string}> */
    public static function columnMeta(string $table): array
    {
        if (isset(self::$columnMeta[$table])) {
            return self::$columnMeta[$table];
        }

        $statement = self::db()->prepare(
            'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);

        $meta = [];
        foreach ($statement->fetchAll() as $column) {
            $default = $column['COLUMN_DEFAULT'];
            // MariaDB quotes string defaults in information_schema.
            if (is_string($default)) {
                $default = trim($default, "'");
                if (strcasecmp($default, 'NULL') === 0) {
                    $default = null;
                }
            }

            $meta[(string) $column['COLUMN_NAME']] = [
                'nullable' => strcasecmp((string) $column['IS_NULLABLE'], 'YES') === 0,
                'default' => $default,
            ];
        }

        return self::$columnMeta[$table] = $meta;
    }

    /** Values the user does not type but that must still be right. */
    private static function derive(string $key, array $columns, ?int $id): array
    {
        if ($key === 'vehicle_documents' && !empty($columns['expires_on'])) {
            $days = (int) floor((strtotime($columns['expires_on']) - strtotime(date('Y-m-d'))) / 86400);
            if (($columns['status'] ?? '') !== 'cancelled') {
                $columns['status'] = $days < 0 ? 'expired' : ($days <= Settings::int('document_alert_days', 30) ? 'expiring' : 'valid');
            }
        }

        if ($key === 'pre_dispatch_checks' && $value('status') === 'ready') {
            foreach (['cargo_loaded', 'quantity_verified', 'packaging_verified', 'documents_verified', 'vehicle_verified', 'driver_verified', 'route_verified'] as $field) {
                if (($input[$field] ?? '') === '' || ($input[$field] ?? '') === '0') {
                    $errors[] = 'Every final pre-dispatch item must be confirmed before marking this shipment ready.';
                    break;
                }
            }
        }

        if ($key === 'clearance_documents') {
            if (!empty($columns['expires_on']) && strtotime((string) $columns['expires_on']) < strtotime(date('Y-m-d'))) {
                $columns['status'] = 'expired';
            }
            $columns['is_received'] = ($columns['status'] ?? 'pending') === 'valid' ? 1 : 0;
            if ($id === null && \current_user_id() !== null) {
                $columns['uploaded_by'] = \current_user_id();
            }
        }

        if ($key === 'shipment_documents') {
            if (!empty($columns['expiry_date']) && strtotime((string) $columns['expiry_date']) < strtotime(date('Y-m-d'))) {
                $columns['status'] = 'expired';
            }
            if ($id === null && \current_user_id() !== null) {
                $columns['uploaded_by'] = \current_user_id();
            }
        }

        if ($key === 'pre_dispatch_checks') {
            if (($columns['status'] ?? '') === 'ready') {
                $columns['checked_by'] = \current_user_id();
                $columns['checked_at'] = date('Y-m-d H:i:s');
            }
        }

        if ($key === 'fuel' && !empty($columns['vehicle_id'])) {
            $columns['previous_mileage'] = self::lastOdometer((int) $columns['vehicle_id'], $id);
        }

        // A driver's own list is filtered by driver_id, so a fill-up he records
        // against nobody would disappear from it the moment he saved it. The
        // office may still file fuel on someone else's behalf.
        if ($key === 'fuel' && \current_role() === 'driver' && \current_driver_id() !== null) {
            $columns['driver_id'] = \current_driver_id();
        }

        if ($key === 'invoices') {
            $columns['tax_rate'] ??= Settings::float('tax_rate', 18.0);
        }

        if ($key === 'warehouse') {
            if ($id === null) {
                $columns['status'] = self::stockStatus((float) ($columns['quantity'] ?? 0), (float) ($columns['minimum_level'] ?? 0));
            } else {
                // On hand is the ledger's answer, not a field. Editing the item's
                // name or its minimum level must not quietly rewrite the balance
                // that its movements add up to.
                unset($columns['quantity'], $columns['status']);
            }
        }

        if ($key === 'movements' && $id === null) {
            $columns['performed_by'] = \current_user_id();
        }

        // A card written as "500 kg for 500,000" carries its own per-kilogram
        // figure, so the list and any report read one number rather than having
        // to know about the pair.
        if ($key === 'rates') {
            $unit = Rate::unitRate($columns);
            if ($unit > 0 && in_array((string) ($columns['rate_type'] ?? ''), ['per_kg', 'per_m3', 'per_package'], true)) {
                $columns['rate_amount'] = $unit;
            }
        }

        $columns = self::convertMoney($key, $columns);

        if ($key === 'payment_methods') {
            // What payments store is the key, and a key that changes would
            // orphan every payment filed under it. It is made once, from the
            // name, and then left alone.
            if ($id === null) {
                $source = trim((string) ($columns['method_key'] ?? ''));
                $source = $source !== '' ? $source : (string) $columns['method_name'];
                $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($source)) ?? '', '_');
                $columns['method_key'] = $slug !== '' ? substr($slug, 0, 40) : 'method_' . time();
            } else {
                unset($columns['method_key']);
            }
        }

        // Who raised a request, filed a claim or took a payment is a fact about
        // the session, not a choice on a form. It is stamped once, at creation,
        // and an edit never moves it onto somebody else.
        $actor = match ($key) {
            'requests' => 'requester_id',
            'expenses' => 'submitted_by',
            'payments' => 'recorded_by',
            default => null,
        };
        if ($actor !== null) {
            if ($id === null && \current_user_id() !== null) {
                $columns[$actor] = \current_user_id();
            } else {
                unset($columns[$actor]);
            }
        }

        if ($key === 'lookups') {
            // "Shown as" is the exception, not the rule: almost every entry
            // reads on the form exactly as it is stored.
            $columns['label'] = trim((string) ($columns['label'] ?? '')) !== '' ? $columns['label'] : $columns['value'];
            // A blank position puts the entry at the end of its list.
            if (($columns['sort_order'] ?? null) === null) {
                $columns['sort_order'] = self::nextLookupPosition((string) ($columns['list_key'] ?? ''));
            }
        }

        if ($key === 'users' && \current_prvg() !== 1) {
            unset($columns['prvg']);
        }

        if ($key === 'users' && $id === null) {
            // A new account needs a password, and nobody should choose it for
            // someone else. Generate a one-time one, force a change at first
            // sign-in, and hand the plain value back for the administrator to
            // pass on; it is never stored anywhere in plain text.
            self::$lastOneTimePassword = self::oneTimePassword();
            $columns['password_hash'] = password_hash(self::$lastOneTimePassword, PASSWORD_DEFAULT);
            $columns['must_change_password'] = 1;
            $columns['password_changed_at'] = null;
        }

        return $columns;
    }

    /**
     * Settles which money a record is in, and leaves the figure alone.
     *
     * An amount is stored exactly as it was agreed or paid. It is never
     * converted, because each currency keeps its own books: a shilling receipt
     * is read on the shilling page and adds up with other shillings. Converting
     * would put a rate between the receipt and the report, and a rate that moves
     * next month would change what last month said.
     */
    private static function convertMoney(string $key, array $columns): array
    {
        if (!array_key_exists('currency', $columns)) {
            return $columns;
        }

        $columns['currency'] = strtoupper(trim((string) ($columns['currency'] ?? ''))) ?: Currency::base();

        return $columns;
    }

    private static function nextLookupPosition(string $listKey): int
    {
        $last = self::scalar('SELECT MAX(sort_order) FROM lookup_values WHERE list_key = ?', [$listKey]);

        return $last === null || $last === false ? 10 : (int) $last + 10;
    }

    /** Set when a user record is created, so the controller can show it once. */
    private static ?string $lastOneTimePassword = null;

    public static function takeOneTimePassword(): ?string
    {
        $password = self::$lastOneTimePassword;
        self::$lastOneTimePassword = null;

        return $password;
    }

    /**
     * A one-time password that reads as what it is: LMS-7K4M-2QX9.
     *
     * It used to be two words and four digits ("KarongiDepot6877"), which looked
     * like a password somebody had chosen rather than one the system issued for a
     * single sign-in. The prefix says where it came from, and the grouping makes
     * it easy to read down a phone line.
     *
     * The alphabet leaves out 0/O and 1/I, the pairs people mistype, and 8 places
     * from 32 characters is 40 bits — far more than a password that must be
     * replaced at first sign-in needs.
     */
    private static function oneTimePassword(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $block = static function () use ($alphabet): string {
            $out = '';
            for ($i = 0; $i < 4; $i++) {
                $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            return $out;
        };

        return 'LMS-' . $block() . '-' . $block();
    }

    /** Side effects that keep other tables in step with this one. */
    private static function afterSave(string $key, int $id, array $columns, array $before = []): void
    {
        $db = self::db();

        if ($key === 'lookups') {
            $old = (string) ($before['value'] ?? '');
            $new = (string) ($columns['value'] ?? '');
            if ($old !== '' && $old !== $new) {
                // The expenses already filed under "Allowance" move with it when
                // it becomes "Driver allowance", rather than falling out of the list.
                Lookup::rename((string) $columns['list_key'], $old, $new);
            }
            Lookup::flush();
        }

        if ($key === 'vehicles' && !empty($columns['assigned_driver_id'])) {
            // A driver holds one vehicle at a time.
            $release = $db->prepare('UPDATE vehicles SET assigned_driver_id = NULL WHERE assigned_driver_id = ? AND id <> ?');
            $release->execute([(int) $columns['assigned_driver_id'], $id]);
        }

        if ($key === 'trips') {
            // The request is answered the moment a trip exists for it. Moving the
            // trip onto a different request releases the one it left behind, so a
            // request is never held by a trip that no longer names it.
            $previous = (int) ($before['request_id'] ?? 0);
            $current = (int) ($columns['request_id'] ?? 0);

            if ($previous > 0 && $previous !== $current) {
                self::releaseRequest($db, $previous);
            }

            self::claimRequest($db, $current, $id);

            // Swapping the vehicle or the driver on a trip that is already out
            // has to hand the old one back. Otherwise the truck that returned to
            // the yard still reads "On trip" and nothing will dispatch it again.
            self::handOverResource($db, $id, 'vehicles', (int) ($before['vehicle_id'] ?? 0), (int) ($columns['vehicle_id'] ?? 0), (string) ($columns['status'] ?? ''));
            self::handOverResource($db, $id, 'drivers', (int) ($before['driver_id'] ?? 0), (int) ($columns['driver_id'] ?? 0), (string) ($columns['status'] ?? ''));
        }

        if ($key === 'fuel' && !empty($columns['vehicle_id']) && !empty($columns['mileage'])) {
            $bump = $db->prepare('UPDATE vehicles SET mileage = GREATEST(mileage, ?) WHERE id = ?');
            $bump->execute([(int) $columns['mileage'], (int) $columns['vehicle_id']]);
        }

        if ($key === 'maintenance' && ($columns['status'] ?? '') === 'in_progress' && !empty($columns['vehicle_id'])) {
            $db->prepare("UPDATE vehicles SET status = 'maintenance' WHERE id = ? AND status = 'available'")
               ->execute([(int) $columns['vehicle_id']]);
        }

        if ($key === 'warehouse' && $before === [] && array_key_exists('quantity', $columns) && $columns['quantity'] !== null) {
            // A new item's opening stock becomes the first movement, so the
            // balance is always something the ledger can account for.
            StockLedger::openingBalance($id, (float) $columns['quantity'], isset($columns['unit_cost']) ? (float) $columns['unit_cost'] : null);
        }

        if ($key === 'cheques') {
            // The written amount is whatever the lines add up to, so the figure
            // on the leaf and the figure in the books can never disagree.
            Cheque::recalculate($id);
        }

        if ($key === 'shipments') {
            // Price it from the rate card as soon as the weight and the
            // warehouses are known, and keep the figure: a rate renegotiated
            // next month must not change what this customer was told today.
            Rate::quoteShipment($id, $before);

            // A request is answered the moment its cargo has a place on a truck,
            // whether that truck was planned for it or was already going that
            // way with four other customers aboard. The trip cannot record this
            // — it has one request column and a shared truck has many — so the
            // shipment does it.
            // Booking a load names the depot it is standing in, so that depot
            // starts showing it. Everything after this — loading, arriving,
            // being collected — is recorded by the button that causes it.
            CargoCustody::received($id);

            self::claimRequest($db, (int) ($columns['request_id'] ?? 0), (int) ($columns['trip_id'] ?? 0));
            if ((int) ($before['request_id'] ?? 0) > 0 && (int) ($before['request_id'] ?? 0) !== (int) ($columns['request_id'] ?? 0)) {
                self::releaseRequest($db, (int) $before['request_id']);
            }
        }

        if ($key === 'movements') {
            // The movement row is already written; replay the ledger so this row
            // carries its balance and the item follows it.
            StockLedger::rebuild((int) $columns['item_id']);
        }

        if ($key === 'invoices') {
            self::recalculateInvoice($id);
            Posting::tryPost('invoice', $id);
        }

        if ($key === 'fuel') {
            Posting::tryPost('fuel', $id);
        }

        if ($key === 'payments' && !empty($columns['invoice_id'])) {
            // The payment settles its invoice, and both sides reach the ledger.
            self::recalculateInvoice((int) $columns['invoice_id']);
            Posting::tryPost('payment', $id);
            Posting::tryPost('invoice', (int) $columns['invoice_id']);

            // Somebody who has just paid should hear that it arrived, from the
            // company rather than from their own bank statement.
            if ($before === []) {
                Notifier::paymentReceived($id);
            }
        }
    }

    /**
     * The request is Assigned once its load is on a trip, and the trip is
     * written back so the request page links to the truck that is carrying it.
     * Booking a second customer onto the same trip assigns their request too;
     * neither one takes the trip away from the other.
     */
    private static function claimRequest(\PDO $db, int $requestId, int $tripId): void
    {
        if ($requestId <= 0 || $tripId <= 0) {
            return;
        }

        $db->prepare("UPDATE transport_requests SET status = 'assigned', trip_id = ? WHERE id = ? AND status IN ('approved', 'assigned')")
           ->execute([$tripId, $requestId]);
    }

    /**
     * Taking the load off the truck hands the request back — but only when
     * nothing else is still carrying it, since one request can be split across
     * two shipments.
     */
    private static function releaseRequest(\PDO $db, int $requestId): void
    {
        $held = $db->prepare('SELECT COUNT(*) FROM shipments WHERE request_id = ? AND trip_id IS NOT NULL AND deleted_at IS NULL');
        $held->execute([$requestId]);

        if ((int) $held->fetchColumn() > 0) {
            return;
        }

        // A trip planned for this request on its own still holds it.
        $planned = $db->prepare('SELECT COUNT(*) FROM trips WHERE request_id = ? AND deleted_at IS NULL');
        $planned->execute([$requestId]);

        if ((int) $planned->fetchColumn() > 0) {
            return;
        }

        $db->prepare("UPDATE transport_requests SET status = 'approved', trip_id = NULL WHERE id = ? AND status = 'assigned'")
           ->execute([$requestId]);
    }
    /** Replaces the child rows of a record (stops, invoice lines, parts) in one go. */
    public static function saveLines(string $key, int $parentId, array $rows): void
    {
        $module = Schema::get($key);
        if (!isset($module['lines'])) {
            return;
        }

        $lines = $module['lines'];
        $columns = $lines['columns'];
        $db = self::db();

        // Only open a transaction when nobody else already has one, the same way
        // save() does. Without this, saving lines from inside a larger operation
        // fails outright on "there is already an active transaction".
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }

        try {
            $db->prepare("DELETE FROM {$lines['table']} WHERE {$lines['parent']} = ?")->execute([$parentId]);

            $names = array_keys($columns);
            $insertColumns = array_merge([$lines['parent']], $names);
            if (isset($lines['sequence'])) {
                $insertColumns[] = $lines['sequence'];
            }
            $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            $insert = $db->prepare(sprintf('INSERT INTO %s (%s) VALUES (%s)', $lines['table'], implode(', ', $insertColumns), $placeholders));

            $sequence = 0;
            foreach ($rows as $row) {
                $values = [$parentId];
                $blank = true;
                foreach ($names as $name) {
                    $raw = trim((string) ($row[$name] ?? ''));
                    if ($raw !== '') {
                        $blank = false;
                    }
                    $values[] = match ($columns[$name]['type']) {
                        'decimal', 'money' => $raw === '' ? 0 : (float) str_replace(',', '', $raw),
                        'relation' => $raw === '' ? null : (int) $raw,
                        'datetime' => $raw === '' ? null : date('Y-m-d H:i:s', (int) strtotime($raw)),
                        default => $raw === '' ? null : $raw,
                    };
                }

                if ($blank) {
                    continue;
                }

                if (isset($lines['sequence'])) {
                    $values[] = ++$sequence;
                }

                $insert->execute($values);
            }

            if (isset($lines['total_column'])) {
                self::applyLineTotal($module, $lines, $parentId);
            }

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        if ($key === 'invoices') {
            self::recalculateInvoice($parentId);
        }

        if ($key === 'cheques') {
            Cheque::recalculate($parentId);
        }

        if ($key === 'crossings') {
            // Charges decide the total, its conversion, and what is posted, so a
            // cleared crossing reposts as soon as they change.
            BorderCrossing::recalculate($parentId);
            BorderCrossing::post($parentId);
        }

        AuditLog::record('record.lines_updated', $key, (string) $parentId, null, ['lines' => count($rows)]);
    }

    private static function applyLineTotal(array $module, array $lines, int $parentId): void
    {
        // Most line tables carry a generated `line_total` (quantity x price). A
        // cheque line is simply an amount, so the column to add up is named.
        $column = $lines['line_amount'] ?? 'line_total';

        $total = (float) self::scalar(
            "SELECT COALESCE(SUM({$column}), 0) FROM {$lines['table']} WHERE {$lines['parent']} = ?",
            [$parentId]
        );

        $update = self::db()->prepare(sprintf('UPDATE %s SET %s = ? WHERE id = ?', $module['table'], $lines['total_column']));
        $update->execute([$total, $parentId]);
    }

    public static function recalculateInvoice(int $invoiceId): void
    {
        $db = self::db();
        $subtotal = (float) self::scalar('SELECT COALESCE(SUM(line_total), 0) FROM invoice_lines WHERE invoice_id = ?', [$invoiceId]);
        $paid = (float) self::scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND deleted_at IS NULL', [$invoiceId]);

        $invoice = $db->prepare('SELECT tax_rate, status, due_date FROM invoices WHERE id = ?');
        $invoice->execute([$invoiceId]);
        $row = $invoice->fetch();
        if ($row === false) {
            return;
        }

        $taxRate = (float) $row['tax_rate'];
        $tax = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $tax, 2);

        $status = (string) $row['status'];
        if (!in_array($status, ['draft', 'cancelled'], true)) {
            $status = match (true) {
                $paid >= $total && $total > 0 => 'paid',
                $paid > 0 => 'partially_paid',
                $row['due_date'] < date('Y-m-d') => 'overdue',
                default => 'issued',
            };
        }

        $update = $db->prepare('UPDATE invoices SET subtotal = ?, tax_amount = ?, total_amount = ?, amount_paid = ?, status = ? WHERE id = ?');
        $update->execute([$subtotal, $tax, $total, $paid, $status, $invoiceId]);
    }

    /**
     * Soft delete. The row stays in the database with a `deleted_at` stamp so the
     * audit trail and every report that already counted it keep working.
     */
    public static function remove(string $key, int $id, string $reason): void
    {
        $module = Schema::get($key);
        $record = self::find($key, $id);
        if ($record === null) {
            throw new \RuntimeException('That record was already removed.');
        }

        $code = self::code($key, $record);

        if ($key === 'lookups') {
            // Deleting a choice that records still carry would leave those
            // records showing a value no drop-down offers. Retiring it keeps
            // them readable and stops anyone picking it again.
            $inUse = Lookup::usageCount((string) $record['list_key'], (string) $record['value']);
            if ($inUse > 0) {
                throw new \RuntimeException(sprintf(
                    '"%s" is still used by %d record%s. Switch "Offer this choice" off instead — the choice disappears from new forms while those records keep reading correctly.',
                    $record['value'],
                    $inUse,
                    $inUse === 1 ? '' : 's'
                ));
            }
        }

        if (empty($module['soft_delete'])) {
            $itemId = $key === 'movements' ? (int) $record['item_id'] : null;
            self::db()->prepare("DELETE FROM {$module['table']} WHERE id = ?")->execute([$id]);
            if ($itemId !== null) {
                StockLedger::recalculate($itemId);
            }
        } else {
            self::db()->prepare("UPDATE {$module['table']} SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        }

        if (in_array($key, ['invoices', 'payments', 'fuel', 'expenses', 'maintenance', 'procurement'], true)) {
            $source = $key === 'procurement' ? 'purchase' : rtrim($key, 's');
            Posting::unpost($source, $id);
            if ($key === 'payments' && !empty($record['invoice_id'])) {
                self::recalculateInvoice((int) $record['invoice_id']);
            }
        }

        if ($key === 'lookups') {
            Lookup::flush();
        }

        AuditLog::record('record.deleted', $key, $code, $reason);
    }

    /** Flips a record between its two everyday statuses from the list switch. */
    public static function toggleStatus(string $key, int $id, bool $on): string
    {
        $targets = [
            'vehicles' => ['available', 'inactive'],
            'drivers' => ['available', 'inactive'],
            'customers' => ['active', 'inactive'],
            'suppliers' => ['active', 'inactive'],
            'users' => ['active', 'inactive'],
            'rates' => ['active', 'draft'],
            'warehouse' => ['in_stock', 'out_of_stock'],
            'shipments' => ['booked', 'cancelled'],
        ];

        if (!isset($targets[$key])) {
            throw new \RuntimeException('This record cannot be switched from the list; use its workflow buttons.');
        }

        $module = Schema::get($key);
        $record = self::find($key, $id);
        if ($record === null) {
            throw new \RuntimeException('That record no longer exists.');
        }

        $status = $on ? $targets[$key][0] : $targets[$key][1];
        self::db()->prepare("UPDATE {$module['table']} SET status = ? WHERE id = ?")->execute([$status, $id]);

        AuditLog::record('record.status_changed', $key, self::code($key, $record), null, ['status' => $status]);

        return $status;
    }

    // --------------------------------------------------------------- helpers

    /** WHERE clause and bound values for a listing: soft delete, scope, search and filters. */
    private static function conditions(array $module, array $query, array $context): array
    {
        $alias = $module['alias'];
        $conditions = ['1 = 1'];
        $parameters = [];

        if (!empty($module['soft_delete'])) {
            $conditions[] = "{$alias}.deleted_at IS NULL";
        }

        $scope = self::scopeCondition($module, $context);
        if ($scope !== null) {
            $conditions[] = $scope[0];
            $parameters[] = $scope[1];
        }

        $search = trim((string) ($query['q'] ?? ''));
        if ($search !== '' && !empty($module['search'])) {
            $likes = [];
            foreach ($module['search'] as $column) {
                $likes[] = "{$column} LIKE ?";
                $parameters[] = '%' . $search . '%';
            }
            $conditions[] = '(' . implode(' OR ', $likes) . ')';
        }

        foreach ($module['filters'] as $name => $filter) {
            $value = trim((string) ($query[$name] ?? ''));
            if ($value === '' || !array_key_exists($value, $filter['options'])) {
                continue;
            }
            $conditions[] = "{$filter['column']} = ?";
            $parameters[] = $value;
        }

        return [implode(' AND ', $conditions), $parameters];
    }

    /**
     * Row-level scoping. A driver may only see the trips, deliveries and fuel that
     * belong to their own driver profile; before this, every driver could open the
     * whole company's operations.
     *
     * @return array{0: string, 1: int}|null
     */
    private static function scopeCondition(array $module, array $context): ?array
    {
        if (($context['role'] ?? '') !== 'driver' || empty($module['scope']['driver'])) {
            return null;
        }

        $driverId = $context['driver_id'] ?? null;

        // A login with no driver profile gets an impossible id rather than everything.
        return [$module['scope']['driver'] . ' = ?', $driverId === null ? 0 : (int) $driverId];
    }

    private static function order(array $module, string $sort, string $dir): string
    {
        $default = $module['order'];
        if ($sort === '') {
            return $default;
        }

        $sortable = array_keys($module['list']);
        if (!in_array($sort, $sortable, true)) {
            return $default;
        }

        return sprintf('`%s` %s', $sort, strtolower($dir) === 'asc' ? 'ASC' : 'DESC');
    }

    private static function activeFilters(array $module, array $query): array
    {
        $active = [];
        foreach ($module['filters'] as $name => $filter) {
            $value = trim((string) ($query[$name] ?? ''));
            if ($value !== '' && array_key_exists($value, $filter['options'])) {
                $active[$name] = $value;
            }
        }

        return $active;
    }

    /** @return array<string, array> */
    public static function fileFields(string $key): array
    {
        return array_filter(Schema::fields($key), static fn (array $field): bool => $field['type'] === 'file');
    }

    private static function validateUpload(?array $file, string $label): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return "{$label} could not be uploaded.";
        }

        $limit = (int) \config('uploads.max_bytes', 5242880);
        if (($file['size'] ?? 0) > $limit) {
            return sprintf('%s must be %s MB or smaller.', $label, rtrim(rtrim(number_format($limit / 1048576, 1, '.', ''), '0'), '.'));
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx'], true)) {
            return "{$label} must be a PDF, image, Word or Excel file.";
        }

        return null;
    }

    private static function storeUpload(string $key, string $fieldName, array $files, string $existing): string
    {
        $file = $files['file_' . $fieldName] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $existing;
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $directory = rtrim((string) \config('uploads.path', dirname(__DIR__, 2) . '/storage/uploads'), "/\\") . '/' . $key;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The upload folder could not be created.');
        }

        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
            throw new \RuntimeException('The uploaded file could not be saved.');
        }

        return $key . '/' . $filename;
    }

    /** The one column of each table that may be shown as a label. */
    private const LABEL_COLUMNS = [
        'drivers' => 'full_name',
        'vehicles' => 'plate_number',
        'users' => 'full_name',
        'roles' => 'role_name',
        'trips' => 'reference_code',
        'transport_requests' => 'reference_code',
        'shipments' => 'shipment_code',
        'customers' => 'customer_name',
        'warehouses' => 'warehouse_name',
        'suppliers' => 'supplier_name',
        'inventory_items' => 'item_name',
        'invoices' => 'invoice_number',
        'gl_accounts' => 'account_name',
    ];

    private static function relationAllowed(string $table, string $column): bool
    {
        return ($column !== '') && (self::LABEL_COLUMNS[$table] ?? null) === $column;
    }

    /**
     * Which column may be read as a dependent relation's parent.
     *
     * Never displayed, only compared — but it still reaches SQL by name, so it
     * has to be a foreign key on a table the registry already knows about.
     */
    private static function relationParentAllowed(string $table, string $column): bool
    {
        return self::relationAllowed($table, self::LABEL_COLUMNS[$table] ?? '')
            && preg_match('/^[a-z][a-z0-9_]*_id$/', $column) === 1;
    }
    private static function relationExists(array $relation, string $id): bool
    {
        if (!self::relationAllowed($relation['table'], $relation['label'])) {
            return false;
        }

        return (int) self::scalar("SELECT COUNT(*) FROM {$relation['table']} WHERE id = ?", [(int) $id]) > 0;
    }

    /** The name behind a relation id, for the places that show one without offering a choice. */
    public static function relationLabel(array $field, mixed $id): string
    {
        if ($id === null || $id === '') {
            return '';
        }

        $relation = $field['relation'];
        if (!self::relationAllowed($relation['table'], $relation['label'])) {
            return '';
        }

        $value = self::scalar("SELECT {$relation['label']} FROM {$relation['table']} WHERE id = ?", [(int) $id]);

        return $value === false || $value === null ? '' : (string) $value;
    }

    private static function lastOdometer(int $vehicleId, ?int $excludeFuelId): ?int
    {
        $sql = 'SELECT MAX(mileage) FROM fuel_records WHERE vehicle_id = ? AND deleted_at IS NULL';
        $parameters = [$vehicleId];
        if ($excludeFuelId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $excludeFuelId;
        }

        $value = self::scalar($sql, $parameters);

        return $value === null || $value === false ? null : (int) $value;
    }

    private static function stockStatus(float $quantity, float $minimum): string
    {
        return match (true) {
            $quantity <= 0 => 'out_of_stock',
            $quantity <= $minimum => 'reorder',
            default => 'in_stock',
        };
    }

    /**
     * Move "on trip" from the vehicle or driver a trip has left to the one it
     * has taken, but only while the trip is actually out.
     *
     * The one it left is released only when no other running trip still holds
     * it, so reassigning a truck between two live trips never frees it by
     * mistake.
     */
    private static function handOverResource(PDO $db, int $tripId, string $table, int $previous, int $current, string $status): void
    {
        if ($previous === $current) {
            return;
        }

        $running = in_array($status, ['loading', 'in_transit'], true);
        $column = $table === 'vehicles' ? 'vehicle_id' : 'driver_id';

        if ($previous > 0) {
            $held = $db->prepare(
                "SELECT COUNT(*) FROM trips
                  WHERE {$column} = ? AND id <> ? AND deleted_at IS NULL
                    AND status IN ('loading', 'in_transit')"
            );
            $held->execute([$previous, $tripId]);
            if ((int) $held->fetchColumn() === 0) {
                $db->prepare("UPDATE {$table} SET status = 'available' WHERE id = ? AND status = 'on_trip'")->execute([$previous]);
            }
        }

        if ($running && $current > 0) {
            $db->prepare("UPDATE {$table} SET status = 'on_trip' WHERE id = ? AND status = 'available'")->execute([$current]);
        }
    }

    /** The row exactly as it stands before an update, for modules that compare against it. */
    private static function rowBefore(string $table, int $id): array
    {
        $statement = self::db()->prepare("SELECT * FROM {$table} WHERE id = ?");
        $statement->execute([$id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private static function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    /** Recent audit entries for the sidebar panel on a list page. */
    public static function auditLog(int $limit = 12): array
    {
        $statement = self::db()->query(
            'SELECT a.action_name, a.entity_type, a.entity_id, a.reason, a.created_at, COALESCE(u.full_name, "System") AS actor
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              ORDER BY a.id DESC
              LIMIT ' . max(1, $limit)
        );

        return $statement->fetchAll();
    }
}
