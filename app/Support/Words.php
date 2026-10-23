<?php

declare(strict_types=1);

namespace Support;

/**
 * Amounts written out in words, as every form carries its total twice:
 * 8,080,000 RWF -> "Eight million eighty thousand Rwandan francs".
 */
final class Words
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    private const SCALES = ['', 'thousand', 'million', 'billion', 'trillion'];

    /** Currency names and their hundredth. */
    private const CURRENCIES = [
        'RWF' => ['Rwandan francs', null],
        'TZS' => ['Tanzanian shillings', null],
        'UGX' => ['Ugandan shillings', null],
        'KES' => ['Kenyan shillings', 'cents'],
        'USD' => ['US dollars', 'cents'],
        'EUR' => ['euros', 'cents'],
    ];

    public static function number(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }
        if ($number < 0) {
            return 'minus ' . self::number(-$number);
        }

        $parts = [];
        $scale = 0;
        while ($number > 0) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $parts[] = trim(self::hundreds($chunk) . ' ' . self::SCALES[$scale]);
            }
            $number = intdiv($number, 1000);
            $scale++;
        }

        return implode(' ', array_reverse($parts));
    }

    private static function hundreds(int $number): string
    {
        $words = [];
        if ($number >= 100) {
            $words[] = self::ONES[intdiv($number, 100)] . ' hundred';
            $number %= 100;
        }
        if ($number >= 20) {
            $words[] = self::TENS[intdiv($number, 10)] . ($number % 10 > 0 ? '-' . self::ONES[$number % 10] : '');
        } elseif ($number > 0) {
            $words[] = self::ONES[$number];
        }

        return implode(' ', $words);
    }

    public static function amount(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        [$name, $minor] = self::CURRENCIES[$currency] ?? [$currency, 'cents'];
        $whole = (int) floor(abs($amount) + 0.0000001);
        $cents = (int) round((abs($amount) - $whole) * 100);

        $text = self::number($whole) . ' ' . $name;
        if ($cents > 0 && $minor !== null) {
            $text .= ' and ' . self::number($cents) . ' ' . $minor;
        }

        return ucfirst($text);
    }
}
