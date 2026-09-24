<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Money in more than one country.
 *
 * The company works across Rwanda, Kenya and Tanzania. A driver buys fuel in
 * shillings at Namanga; a customer is billed in francs in Kigali. Both are real
 * amounts, and neither may be quietly treated as the other.
 *
 * So every record that holds money keeps two figures:
 *
 *   amount + currency   what was actually written on the receipt or the invoice
 *   base_amount         the same money in the currency the books are kept in
 *
 * The conversion happens once, when the record is saved, at the rate in force on
 * that record's own date — and the rate used is stored on the row. Nothing
 * recalculates later: a fuel receipt from March is still worth what it was worth
 * in March, however the shilling has moved since. That is the whole point of
 * keeping the rate rather than looking it up again.
 *
 * Every report, and the ledger, read `base_amount` and nothing else.
 */
final class Currency
{
    private static ?array $currencies = null;
    private static array $rates = [];

    /** The currency the books are kept in. Everything converts to this. */
    public static function base(): string
    {
        foreach (self::all() as $code => $currency) {
            if ((int) $currency['is_base'] === 1) {
                return $code;
            }
        }

        return 'RWF';
    }

    /** @return array<string, array> code => row */
    public static function all(): array
    {
        if (self::$currencies !== null) {
            return self::$currencies;
        }

        self::$currencies = [];

        try {
            $sql = 'SELECT * FROM currencies WHERE deleted_at IS NULL ORDER BY sort_order, code';
            foreach (Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                self::$currencies[(string) $row['code']] = $row;
            }
        } catch (\Throwable) {
            // Before the table exists, one currency at par keeps every form usable.
            self::$currencies = ['RWF' => ['code' => 'RWF', 'currency_name' => 'Rwandan franc', 'symbol' => 'FRw', 'decimals' => 0, 'is_base' => 1, 'is_active' => 1]];
        }

        return self::$currencies;
    }

    /** What a form offers: the active currencies, base first. */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $code => $currency) {
            if ((int) $currency['is_active'] !== 1) {
                continue;
            }
            $options[$code] = sprintf('%s — %s', $code, $currency['currency_name']);
        }

        return $options;
    }

    /**
     * The rate in force on a given day: how many units of the base currency one
     * unit of this currency buys.
     *
     * The latest rate dated on or before that day, which is what a bank
     * statement does. A currency with no rate yet returns null rather than
     * guessing — an invented rate is worse in a set of books than a refusal.
     */
    public static function rateOn(string $code, string $date): ?float
    {
        $code = strtoupper(trim($code));
        $date = date('Y-m-d', (int) strtotime($date !== '' ? $date : 'today'));

        if ($code === '' || $code === self::base()) {
            return 1.0;
        }

        $key = $code . '|' . $date;
        if (array_key_exists($key, self::$rates)) {
            return self::$rates[$key];
        }

        try {
            $statement = Database::connection()->prepare(
                'SELECT rate FROM currency_rates
                  WHERE currency_code = ? AND effective_from <= ? AND deleted_at IS NULL
                  ORDER BY effective_from DESC LIMIT 1'
            );
            $statement->execute([$code, $date]);
            $rate = $statement->fetchColumn();
            self::$rates[$key] = $rate === false ? null : (float) $rate;
        } catch (\Throwable) {
            self::$rates[$key] = null;
        }

        return self::$rates[$key];
    }

    /**
     * Convert an amount into the books' currency.
     *
     * @return array{rate: float, base: float, known: bool}
     */
    public static function convert(float $amount, string $code, string $date): array
    {
        $rate = self::rateOn($code, $date);

        // No rate on file: the amount is carried across unconverted and the
        // caller is told, so it can say so rather than silently mis-stating it.
        if ($rate === null) {
            return ['rate' => 1.0, 'base' => round($amount, 2), 'known' => false];
        }

        return ['rate' => $rate, 'base' => round($amount * $rate, 2), 'known' => true];
    }

    /** An amount written the way that currency is written. */
    public static function format(float $amount, string $code): string
    {
        $currency = self::all()[strtoupper($code)] ?? null;
        $decimals = $currency === null ? 0 : (int) $currency['decimals'];

        return sprintf('%s %s', strtoupper($code), number_format($amount, $decimals));
    }

    /**
     * Currencies a rate has never been entered for.
     *
     * Finance sees this on the exchange rates page: an active currency with no
     * rate converts at one, which is almost certainly wrong and should be
     * visible rather than discovered in a report.
     *
     * @return list<string>
     */
    public static function missingRates(): array
    {
        $missing = [];

        foreach (self::all() as $code => $currency) {
            if ((int) $currency['is_active'] !== 1 || (int) $currency['is_base'] === 1) {
                continue;
            }
            if (self::rateOn($code, date('Y-m-d')) === null) {
                $missing[] = $code;
            }
        }

        return $missing;
    }

    public static function flush(): void
    {
        self::$currencies = null;
        self::$rates = [];
        Schema::flush();
    }
}
