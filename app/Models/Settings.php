<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Company-wide settings read from `company_settings` instead of being hard-coded:
 * currency, tax rate, invoice prefix, alert windows and login lockout policy.
 */
final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    private const FALLBACK = [
        'company_name' => 'LMS Logistics',
        'currency_code' => 'RWF',
        'currency_symbol' => 'RWF',
        'tax_rate' => '18',
        'invoice_prefix' => 'INV',
        'payment_terms_days' => '30',
        'licence_alert_days' => '90',
        'document_alert_days' => '30',
        'service_alert_days' => '14',
        'on_time_grace_minutes' => '30',
        'max_login_attempts' => '5',
        'lockout_minutes' => '15',
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $rows = Database::connection()
                ->query('SELECT setting_key, setting_value FROM company_settings')
                ->fetchAll(PDO::FETCH_KEY_PAIR);
            self::$cache = array_map(static fn ($value): string => (string) $value, $rows) + self::FALLBACK;
        } catch (\Throwable) {
            self::$cache = self::FALLBACK;
        }

        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::all()[$key] ?? $default;
        return $value === '' ? $default : $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, (string) $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key, (string) $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function put(string $key, string $value): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE company_settings SET setting_value = ? WHERE setting_key = ?'
        );
        $statement->execute([$value, $key]);
        self::$cache = null;
    }

    /** @return list<array{setting_key: string, setting_value: ?string, setting_label: string, setting_group: string, input_type: string}> */
    public static function editable(): array
    {
        return Database::connection()
            ->query('SELECT setting_key, setting_value, setting_label, setting_group, input_type FROM company_settings ORDER BY setting_group, setting_label')
            ->fetchAll();
    }

    public static function money(float $amount, bool $withDecimals = false): string
    {
        return self::get('currency_symbol', 'RWF') . ' ' . number_format($amount, $withDecimals ? 2 : 0, '.', ',');
    }
}
