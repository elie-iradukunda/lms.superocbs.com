<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = \config('db');

        // A port only appears in the DSN when one is configured, so a default
        // MySQL on 3306 connects exactly as it always did, and a database on a
        // managed host with its own port needs no code change.
        $dsn = sprintf('mysql:host=%s', $config['host']);
        if (!empty($config['port']) && (string) $config['port'] !== '3306') {
            $dsn .= ';port=' . (int) $config['port'];
        }
        $dsn .= sprintf(';dbname=%s;charset=%s', $config['name'], $config['charset']);

        try {
            self::$connection = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Logistics database connection failed.', 0, $exception);
        }

        return self::$connection;
    }
}
