<?php

declare(strict_types=1);

/**
 * Does the code agree with the migrations, without a database?
 *
 * scripts/doctor.php answers the same question against a running MySQL: it reads
 * the live columns and compares them with what the module registry asks for.
 * That is the right check on a deployed machine, but it needs the database to be
 * up and migrated first. This script answers the structural half of it with
 * nothing running at all — it reads database/schema.sql and every migration the
 * way scripts/migrate.php applies them, works out which tables and columns that
 * leaves, and compares that with Models\Schema. It is the check to run in CI, or
 * on a laptop with no MySQL, before a drift between a model and its migration is
 * discovered by a user as an "unknown column".
 *
 * It parses plain CREATE TABLE / ALTER TABLE / DROP TABLE / RENAME TABLE, which
 * is all the migrations use. It changes nothing.
 *
 * Usage:
 *   php scripts/schema_check.php
 *   php scripts/schema_check.php --dump            list every parsed table
 *   php scripts/schema_check.php --dump=table_name columns of one table
 *
 * Exit code is 0 when the code and the migrations agree, 1 when they do not.
 */

require __DIR__ . '/../bootstrap.php';

/** Strip -- line comments and / * * / block comments, leaving string literals alone. */
$stripComments = static function (string $sql): string {
    $out = '';
    $len = strlen($sql);
    $i = 0;
    $quote = null; // "'", '"' or '`' while inside a quoted token
    while ($i < $len) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($quote !== null) {
            $out .= $ch;
            // Backslash escape inside '...' / "..." (not inside `...`).
            if ($ch === '\\' && $quote !== '`' && $next !== '') {
                $out .= $next;
                $i += 2;
                continue;
            }
            if ($ch === $quote) {
                // A doubled quote is an escaped quote, still inside the token.
                if ($next === $quote) {
                    $out .= $next;
                    $i += 2;
                    continue;
                }
                $quote = null;
            }
            $i++;
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $out .= $ch;
            $i++;
            continue;
        }
        if ($ch === '-' && $next === '-') {
            // Line comment to end of line.
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;
            continue;
        }
        if ($ch === '#') {
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;
            continue;
        }
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 2;
            continue;
        }

        $out .= $ch;
        $i++;
    }

    return $out;
};

/** Split into statements on a top-level semicolon (outside quotes and parentheses). */
$splitStatements = static function (string $sql): array {
    $statements = [];
    $buffer = '';
    $len = strlen($sql);
    $depth = 0;
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($quote !== null) {
            $buffer .= $ch;
            if ($ch === '\\' && $quote !== '`' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($ch === $quote) {
                if ($next === $quote) {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $quote = null;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buffer .= $ch;
            continue;
        }
        if ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            $depth = max(0, $depth - 1);
        }
        if ($ch === ';' && $depth === 0) {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
};

$unquote = static fn (string $name): string => trim(trim($name), '`"');

/** The identifier directly after a keyword such as TABLE, e.g. `t` or db.`t`. */
$readIdentifier = static function (string $s, int $from) use ($unquote): array {
    $n = strlen($s);
    $i = $from;
    while ($i < $n && ctype_space($s[$i])) {
        $i++;
    }
    $token = '';
    if ($i < $n && $s[$i] === '`') {
        $i++;
        while ($i < $n && $s[$i] !== '`') {
            $token .= $s[$i];
            $i++;
        }
        $i++; // closing backtick
    } else {
        while ($i < $n && (ctype_alnum($s[$i]) || $s[$i] === '_' || $s[$i] === '$' || $s[$i] === '.')) {
            $token .= $s[$i];
            $i++;
        }
    }
    // A db-qualified name keeps only the table part for our purposes.
    if (str_contains($token, '.')) {
        $parts = explode('.', $token);
        $token = end($parts);
    }

    return [$unquote($token), $i];
};

// Column-list items whose first word is a constraint, not a column.
$constraintWords = ['primary', 'unique', 'key', 'index', 'fulltext', 'spatial', 'foreign', 'constraint', 'check'];

/**
 * Column names declared inside a CREATE TABLE body (the text between the first
 * '(' and its matching ')').
 */
$columnsFromBody = static function (string $body) use ($constraintWords, $unquote): array {
    // Split the body on top-level commas.
    $items = [];
    $buffer = '';
    $depth = 0;
    $quote = null;
    $len = strlen($body);
    for ($i = 0; $i < $len; $i++) {
        $ch = $body[$i];
        if ($quote !== null) {
            $buffer .= $ch;
            if ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buffer .= $ch;
            continue;
        }
        if ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            $depth--;
        }
        if ($ch === ',' && $depth === 0) {
            $items[] = trim($buffer);
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    if (trim($buffer) !== '') {
        $items[] = trim($buffer);
    }

    $columns = [];
    foreach ($items as $item) {
        if ($item === '') {
            continue;
        }
        // First token: a backticked name or a bare word.
        if ($item[0] === '`') {
            $end = strpos($item, '`', 1);
            if ($end === false) {
                continue;
            }
            $columns[] = $unquote(substr($item, 0, $end + 1));
            continue;
        }
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_$]*)/', $item, $m)) {
            continue;
        }
        if (in_array(strtolower($m[1]), $constraintWords, true)) {
            continue;
        }
        $columns[] = $m[1];
    }

    return $columns;
};

/** @var array<string, array<string, bool>> table => column => true */
$tables = [];

$applyStatement = static function (string $stmt) use (
    &$tables, $readIdentifier, $columnsFromBody, $unquote
): void {
    // Normalise leading whitespace; keep the body intact.
    $head = ltrim($stmt);
    $upper = strtoupper($head);

    if (str_starts_with($upper, 'CREATE TABLE')) {
        $afterTable = stripos($head, 'TABLE') + 5;
        // Skip optional IF NOT EXISTS.
        $rest = substr($head, $afterTable);
        if (preg_match('/^\s*IF\s+NOT\s+EXISTS\s/i', $rest)) {
            $afterTable += stripos($rest, 'EXISTS') + 6;
        }
        [$table, $pos] = $readIdentifier($head, $afterTable);
        if ($table === '') {
            return;
        }
        // CREATE TABLE x LIKE y  — copy y's columns.
        if (preg_match('/\bLIKE\b/i', substr($head, $pos), $m, PREG_OFFSET_CAPTURE)) {
            [$source] = $readIdentifier($head, $pos + $m[0][1] + 4);
            $tables[$table] = $tables[$source] ?? [];
            return;
        }
        $open = strpos($head, '(', $pos);
        if ($open === false) {
            $tables[$table] ??= [];
            return;
        }
        // Find the matching close paren for the column list.
        $depth = 0;
        $close = $open;
        $len = strlen($head);
        $quote = null;
        for ($i = $open; $i < $len; $i++) {
            $ch = $head[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $close = $i;
                    break;
                }
            }
        }
        $body = substr($head, $open + 1, $close - $open - 1);
        $tables[$table] ??= [];
        foreach ($columnsFromBody($body) as $col) {
            $tables[$table][$col] = true;
        }
        return;
    }

    if (str_starts_with($upper, 'ALTER TABLE')) {
        $afterTable = stripos($head, 'TABLE') + 5;
        [$table, $pos] = $readIdentifier($head, $afterTable);
        if ($table === '') {
            return;
        }
        $tables[$table] ??= [];
        // The operations are comma-separated at top level after the table name.
        $opsText = substr($head, $pos);
        // Reuse the same top-level comma splitter as the column body.
        $ops = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $len = strlen($opsText);
        for ($i = 0; $i < $len; $i++) {
            $ch = $opsText[$i];
            if ($quote !== null) {
                $buffer .= $ch;
                if ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            }
            if ($ch === ',' && $depth === 0) {
                $ops[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') {
            $ops[] = trim($buffer);
        }

        foreach ($ops as $op) {
            $opUpper = strtoupper($op);
            if (preg_match('/^ADD\s+(COLUMN\s+)?(IF\s+NOT\s+EXISTS\s+)?/i', $op, $m)) {
                $after = strlen($m[0]);
                // Skip ADD of a key/constraint, not a column.
                $firstWord = strtoupper(trim(substr($op, 3)));
                if (preg_match('/^(COLUMN\b|IF\b|`|[A-Za-z_])/', trim(substr($op, 3)))
                    && !preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|FULLTEXT|SPATIAL|FOREIGN|CONSTRAINT|CHECK)\b/i', trim(substr($op, 3)))) {
                    [$col] = $readIdentifier($op, $after);
                    if ($col !== '') {
                        $tables[$table][$col] = true;
                    }
                }
                continue;
            }
            if (preg_match('/^DROP\s+(COLUMN\s+)?(IF\s+EXISTS\s+)?/i', $op, $m)
                && !preg_match('/^DROP\s+(PRIMARY|KEY|INDEX|FOREIGN|CONSTRAINT|CHECK)\b/i', $op)) {
                [$col] = $readIdentifier($op, strlen($m[0]));
                if ($col !== '') {
                    unset($tables[$table][$col]);
                }
                continue;
            }
            if (preg_match('/^CHANGE\s+(COLUMN\s+)?/i', $op, $m)) {
                [$old, $p2] = $readIdentifier($op, strlen($m[0]));
                [$new] = $readIdentifier($op, $p2);
                if ($old !== '') {
                    unset($tables[$table][$old]);
                }
                if ($new !== '') {
                    $tables[$table][$new] = true;
                }
                continue;
            }
            if (preg_match('/^RENAME\s+COLUMN\s+/i', $op, $m)) {
                [$old, $p2] = $readIdentifier($op, strlen($m[0]));
                // skip TO
                $p3 = stripos($op, 'TO', $p2);
                [$new] = $readIdentifier($op, $p3 === false ? $p2 : $p3 + 2);
                if ($old !== '') {
                    unset($tables[$table][$old]);
                }
                if ($new !== '') {
                    $tables[$table][$new] = true;
                }
                continue;
            }
            if (preg_match('/^RENAME\s+(TO\s+|AS\s+)?/i', $op, $m) && !preg_match('/^RENAME\s+COLUMN/i', $op)) {
                [$newTable] = $readIdentifier($op, strlen($m[0]));
                if ($newTable !== '' && isset($tables[$table])) {
                    $tables[$newTable] = $tables[$table];
                    unset($tables[$table]);
                    $table = $newTable;
                }
                continue;
            }
            // MODIFY, ENGINE=, CONVERT TO, ADD INDEX etc. change no column set.
        }
        return;
    }

    if (str_starts_with($upper, 'DROP TABLE')) {
        $rest = preg_replace('/^DROP\s+TABLE\s+(IF\s+EXISTS\s+)?/i', '', $head);
        foreach (explode(',', (string) $rest) as $name) {
            $t = $unquote(trim($name));
            unset($tables[$t]);
        }
        return;
    }

    if (str_starts_with($upper, 'RENAME TABLE')) {
        // RENAME TABLE a TO b [, c TO d]
        $rest = preg_replace('/^RENAME\s+TABLE\s+/i', '', $head);
        foreach (explode(',', (string) $rest) as $pair) {
            if (!preg_match('/^\s*(.+?)\s+TO\s+(.+?)\s*$/i', $pair, $m)) {
                continue;
            }
            $from = $unquote(trim($m[1]));
            $to = $unquote(trim($m[2]));
            if (isset($tables[$from])) {
                $tables[$to] = $tables[$from];
                unset($tables[$from]);
            }
        }
        return;
    }

    // CREATE INDEX, INSERT, UPDATE, CREATE VIEW, SET, USE, CREATE DATABASE: ignored.
};

// ---------------------------------------------------- read schema + migrations
$files = [__DIR__ . '/../database/schema.sql'];
$migrations = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
sort($migrations);
$files = array_merge($files, $migrations);

$parseErrors = [];
foreach ($files as $file) {
    $sql = (string) file_get_contents($file);
    $clean = $stripComments($sql);
    foreach ($splitStatements($clean) as $stmt) {
        try {
            $applyStatement($stmt);
        } catch (Throwable $e) {
            $parseErrors[] = basename($file) . ': ' . $e->getMessage();
        }
    }
}

// ------------------------------------------------------------------- dump
$dumpArg = null;
foreach ($argv ?? [] as $arg) {
    if ($arg === '--dump') {
        $dumpArg = '*';
    } elseif (str_starts_with($arg, '--dump=')) {
        $dumpArg = substr($arg, 7);
    }
}
if ($dumpArg !== null) {
    ksort($tables);
    if ($dumpArg === '*') {
        foreach ($tables as $name => $cols) {
            printf("%-40s %d column(s)\n", $name, count($cols));
        }
        echo "\n" . count($tables) . " table(s) parsed from schema.sql + migrations.\n";
    } elseif (isset($tables[$dumpArg])) {
        echo "{$dumpArg}:\n";
        foreach (array_keys($tables[$dumpArg]) as $col) {
            echo "  {$col}\n";
        }
    } else {
        fwrite(STDERR, "No such table parsed: {$dumpArg}\n");
        exit(1);
    }
    exit(0);
}

// ---------------------------------------------------------------- compare
$ok = static fn (string $line): string => "  \u{2713} {$line}";
$bad = static fn (string $line): string => "  \u{2717} {$line}";

echo "LMS schema check (no database required)\n";
echo str_repeat('-', 64) . "\n\n";
echo 'Read ' . count($files) . " SQL file(s): schema.sql + " . count($migrations) . " migration(s)\n";
echo 'They declare ' . count($tables) . " table(s).\n\n";

$problems = [];

echo "Tables and columns the modules read\n";
$missingTables = [];
$missingColumns = [];

foreach (Models\Schema::all() as $module) {
    $table = $module['table'];
    if (!isset($tables[$table])) {
        $missingTables[] = sprintf('%s (needed by the %s page)', $table, $module['title']);
        continue;
    }
    foreach (array_keys($module['fields']) as $field) {
        if (!isset($tables[$table][$field])) {
            $missingColumns[] = sprintf('%s.%s (the "%s" field on %s)', $table, $field, $module['fields'][$field]['label'] ?? $field, $module['title']);
        }
    }
    if (isset($module['lines']['table']) && !isset($tables[$module['lines']['table']])) {
        $missingTables[] = sprintf('%s (the line items under %s)', $module['lines']['table'], $module['title']);
    }
}

// The same system tables scripts/doctor.php insists on.
foreach (['migrations', 'roles', 'users', 'permissions', 'role_permissions', 'company_settings',
          'notifications', 'audit_logs', 'login_attempts', 'password_resets', 'email_outbox',
          'gl_accounts', 'gl_journal_entries', 'gl_journal_lines', 'gl_fiscal_periods',
          'payment_methods', 'lookup_values'] as $table) {
    // `migrations` is created by the migrate script itself, not by a SQL file.
    if ($table === 'migrations') {
        continue;
    }
    if (!isset($tables[$table])) {
        $missingTables[] = $table;
    }
}

if ($missingTables === [] && $missingColumns === []) {
    echo $ok('every module table and field maps to a column the migrations create') . "\n";
} else {
    foreach ($missingTables as $line) {
        echo $bad('missing table ' . $line) . "\n";
    }
    foreach ($missingColumns as $line) {
        echo $bad('missing column ' . $line) . "\n";
    }
    $problems[] = 'A model names a table or column no migration creates. Add a migration, or fix the field name in app/Models/Schema.php (or SchemaEri.php).';
}

if ($parseErrors !== []) {
    echo "\nNotes while reading the SQL:\n";
    foreach (array_unique($parseErrors) as $note) {
        echo "  - {$note}\n";
    }
}

echo "\n" . str_repeat('-', 64) . "\n";
if ($problems === []) {
    echo "The code and the migrations agree.\n";
    exit(0);
}

echo count(array_unique($problems)) . " thing(s) to fix:\n";
foreach (array_unique($problems) as $problem) {
    echo "  - {$problem}\n";
}
exit(1);
