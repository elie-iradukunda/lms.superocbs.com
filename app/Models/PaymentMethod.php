<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * How a company takes and makes payments, and which account each one moves.
 *
 * This used to be a list in the code with a second, invisible list in `Posting`
 * mapping each way to pay onto a hard-coded account number. A company could not
 * add its own wallet, could not point a method at its own bank account, and
 * could not see why a receipt had landed where it did.
 *
 * The rows answer three questions at once: what the drop-downs offer, which
 * account the ledger moves, and what the customer is told on an invoice.
 */
final class PaymentMethod
{
    private static ?array $cache = null;

    /** Every method, active or not, keyed by the value stored on a payment. */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];

        try {
            // The ledger account is aliased: `pm.account_name` is the name the
            // bank account is held in, which is not the same thing at all.
            $sql = 'SELECT pm.*, a.account_code, a.account_name AS gl_account_name
                      FROM payment_methods pm
                      LEFT JOIN gl_accounts a ON a.id = pm.gl_account_id
                     WHERE pm.deleted_at IS NULL
                     ORDER BY pm.sort_order, pm.method_name';
            foreach (Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                self::$cache[(string) $row['method_key']] = $row;
            }
        } catch (\Throwable) {
            // Before the table exists the forms fall back to the names the
            // payments table has always accepted, so nothing is unusable.
            foreach (['bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card'] as $key => $name) {
                self::$cache[$key] = ['method_key' => $key, 'method_name' => $name, 'account_code' => null, 'is_active' => 1, 'direction' => 'both', 'show_on_invoice' => 1, 'payment_details' => null];
            }
        }

        return self::$cache;
    }

    /**
     * The choices a form offers.
     *
     * @param string $direction 'in' for money received, 'out' for money paid
     */
    public static function options(string $direction = 'both'): array
    {
        $options = [];

        foreach (self::all() as $key => $row) {
            if ((int) $row['is_active'] !== 1) {
                continue;
            }
            if ($direction !== 'both' && !in_array((string) $row['direction'], [$direction, 'both'], true)) {
                continue;
            }
            $options[$key] = (string) $row['method_name'];
        }

        return $options;
    }

    /** The account code money on this method moves through, for the ledger. */
    public static function accountCode(string $methodKey, string $fallback = '1020'): string
    {
        $code = self::all()[$methodKey]['account_code'] ?? null;

        return $code !== null && $code !== '' ? (string) $code : $fallback;
    }

    public static function name(string $methodKey): string
    {
        return (string) (self::all()[$methodKey]['method_name'] ?? Schema::label($methodKey));
    }

    /**
     * What to print on an invoice so the customer knows where to send the money.
     *
     * @return list<array{name: string, details: string}>
     */
    public static function invoiceInstructions(): array
    {
        $shown = [];

        foreach (self::all() as $row) {
            if ((int) $row['is_active'] !== 1 || (int) $row['show_on_invoice'] !== 1) {
                continue;
            }
            if (!in_array((string) $row['direction'], ['in', 'both'], true)) {
                continue;
            }
            $shown[] = [
                'name' => (string) $row['method_name'],
                'details' => self::describe($row),
            ];
        }

        return $shown;
    }

    /**
     * One readable line of account details, built from the parts.
     *
     * Composing it here rather than storing a sentence means every invoice reads
     * the same, and correcting the account number does not mean retyping the
     * bank's name along with it.
     */
    public static function describe(array $row): string
    {
        $parts = [];

        foreach (['provider_name', 'branch_name'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        $name = trim((string) ($row['account_name'] ?? ''));
        $number = trim((string) ($row['account_number'] ?? ''));
        if ($name !== '' && $number !== '') {
            $parts[] = sprintf('%s — %s', $name, $number);
        } elseif ($name !== '' || $number !== '') {
            $parts[] = $name !== '' ? $name : $number;
        }

        foreach (['swift_code' => 'SWIFT %s', 'phone_number' => 'Tel %s'] as $field => $format) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $parts[] = sprintf($format, $value);
            }
        }

        // Anything the fields do not cover, typed by hand.
        $extra = trim((string) ($row['payment_details'] ?? ''));
        if ($extra !== '') {
            $parts[] = $extra;
        }

        return implode(' · ', $parts);
    }

    /** The details of one method, for a page that shows a single payment. */
    public static function details(string $methodKey): string
    {
        $row = self::all()[$methodKey] ?? null;

        return $row === null ? '' : self::describe($row);
    }

    public static function flush(): void
    {
        self::$cache = null;
        Schema::flush();
    }
}
