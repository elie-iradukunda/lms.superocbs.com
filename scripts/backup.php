<?php

declare(strict_types=1);

/**
 * Daily database backup (ERI specification, section 21).
 *
 * Writes the whole database as one compressed SQL file to the backup folder
 * (setting backup_folder; storage/backups when empty) and removes backups
 * older than backup_keep_days. Uploaded documents live in storage/uploads and
 * are backed up with the files of the site.
 *
 * Run it once a day, from Task Scheduler on Windows or cron elsewhere:
 *
 *   php scripts/backup.php
 *
 * It uses mysqldump when the server has it, and otherwise writes the dump
 * itself, so it also works on hosting without shell access to mysqldump.
 * A copy should also be taken off the server (the hosting's own backup or a
 * download), since a backup on the same disk does not survive losing it.
 */

require __DIR__ . '/../bootstrap.php';

use Core\Database;
use Models\Settings;

$pdo = Database::connection();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

$folder = trim(Settings::get('backup_folder', ''));
if ($folder === '') {
    $folder = __DIR__ . '/../storage/backups';
}
if (!is_dir($folder) && !mkdir($folder, 0750, true) && !is_dir($folder)) {
    fwrite(STDERR, "Cannot create the backup folder {$folder}.\n");
    exit(1);
}
// Keep the folder out of reach of the web.
if (!is_file($folder . '/.htaccess')) {
    file_put_contents($folder . '/.htaccess', "Require all denied\nDeny from all\n");
}

$file = sprintf('%s/%s-%s.sql.gz', rtrim($folder, '/\\'), $database, date('Ymd-His'));
$started = microtime(true);

$written = dumpWithMysqldump($database, $file) || dumpWithPhp($pdo, $database, $file);
if (!$written || !is_file($file) || filesize($file) < 100) {
    @unlink($file);
    fwrite(STDERR, "The backup failed.\n");
    \Models\AuditLog::record('backup.failed', 'system', $database, 'Daily backup failed');
    exit(1);
}

// Keep only the last N days.
$keep = max(1, Settings::int('backup_keep_days', 30));
$removed = 0;
foreach (glob(rtrim($folder, '/\\') . '/' . $database . '-*.sql.gz') ?: [] as $old) {
    if (filemtime($old) < time() - $keep * 86400) {
        $removed += unlink($old) ? 1 : 0;
    }
}

printf(
    "Backed up %s to %s (%s KB in %.1fs). %d old backup(s) removed.\n",
    $database,
    realpath($file) ?: $file,
    number_format(filesize($file) / 1024),
    microtime(true) - $started,
    $removed
);

/** mysqldump, when the server has it and allows running it. */
function dumpWithMysqldump(string $database, string $file): bool
{
    if (!function_exists('proc_open') || in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
        return false;
    }
    $binary = getenv('MYSQLDUMP_PATH') ?: (PHP_OS_FAMILY === 'Windows' && is_file('C:\\xampp\\mysql\\bin\\mysqldump.exe') ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump');

    $command = [
        $binary,
        '--host=' . (getenv('LOGISTICS_DB_HOST') ?: '127.0.0.1'),
        '--port=' . (getenv('LOGISTICS_DB_PORT') ?: '3306'),
        '--user=' . (getenv('LOGISTICS_DB_USER') ?: 'root'),
        '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4',
        $database,
    ];
    $environment = getenv();
    $environment['MYSQL_PWD'] = (string) (getenv('LOGISTICS_DB_PASS') ?: '');

    $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    if (!is_resource($process)) {
        return false;
    }
    $out = gzopen($file, 'wb6');
    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 1 << 20);
        if ($chunk !== false && $chunk !== '') {
            gzwrite($out, $chunk);
        }
    }
    gzclose($out);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        @unlink($file);

        return false;
    }

    return true;
}

/** The same dump written by PHP: tables, rows, views and triggers. */
function dumpWithPhp(PDO $pdo, string $database, string $file): bool
{
    $out = gzopen($file, 'wb6');
    if ($out === false) {
        return false;
    }
    gzwrite($out, sprintf("-- Backup of %s, %s\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n", $database, date('Y-m-d H:i:s')));

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        gzwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

        $rows = $pdo->query('SELECT * FROM `' . $table . '`', PDO::FETCH_ASSOC);
        $batch = [];
        foreach ($rows as $row) {
            $batch[] = '(' . implode(',', array_map(static fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), $row)) . ')';
            if (count($batch) === 200) {
                gzwrite($out, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch !== []) {
            gzwrite($out, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        gzwrite($out, "\n");
    }

    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN) as $view) {
        $create = $pdo->query('SHOW CREATE VIEW `' . $view . '`')->fetch(PDO::FETCH_NUM)[1];
        gzwrite($out, "DROP VIEW IF EXISTS `{$view}`;\n{$create};\n\n");
    }

    foreach ($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $trigger) {
        $create = $pdo->query('SHOW CREATE TRIGGER `' . $trigger['Trigger'] . '`')->fetch(PDO::FETCH_NUM)[2];
        gzwrite($out, "DROP TRIGGER IF EXISTS `{$trigger['Trigger']}`;\nDELIMITER ;;\n{$create};;\nDELIMITER ;\n\n");
    }

    gzwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");

    return gzclose($out);
}
