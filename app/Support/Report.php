<?php

declare(strict_types=1);

namespace Support;

use Models\Settings;

/**
 * The shape every accounting book is built in, and the one place its branding,
 * money formatting and file naming are decided.
 *
 * A book describes itself once and three renderers read that description, so
 * the screen, the PDF and the Excel file can never drift apart:
 *
 *   $doc = Report::make('Trial Balance', 'As of 30 Sep 2026', [
 *       ['label' => 'ACCOUNT', 'align' => 'left',  'width' => 46],
 *       ['label' => 'DEBIT',   'align' => 'right', 'width' => 18, 'currency' => true],
 *       ['label' => 'CREDIT',  'align' => 'right', 'width' => 18, 'currency' => true],
 *   ]);
 *   Report::section($doc, 'Assets');
 *   Report::row($doc, ['Bank - main account', 12000000.0, null], 1);
 *   Report::grand($doc, ['TOTAL', 104500000.0, 104500000.0]);
 *
 * Row types follow the way an accountant reads a statement:
 *   section  a heading with no figures        ("Income", "Cost of sales")
 *   row      an ordinary account line
 *   total    a subtotal, ruled above
 *   grand    the closing figure, double ruled
 *   blank    a spacer
 *
 * A cell is a string, a number, or null for empty. Numbers are formatted as
 * money by the renderer, so the same figure prints identically everywhere.
 */
final class Report
{
    /** @param list<array{label: string, align?: string, width?: float, currency?: bool}> $columns */
    public static function make(string $title, string $period, array $columns, string $basis = null): array
    {
        return [
            'company' => self::company(),
            // A printed book leaves the building: it goes to an accountant, a
            // bank or a tax office, and a page with only a trading name on it
            // does not say which company it belongs to. The details come from
            // Settings, so whoever is running this system puts their own there.
            'company_details' => self::companyDetails(),
            'title' => $title,
            'period' => $period,
            'basis' => $basis ?? Settings::get('accounting_basis', 'Accrual Basis'),
            'columns' => $columns,
            'rows' => [],
        ];
    }

    public static function row(array &$doc, array $cells, int $indent = 0, string $type = 'row', ?string $link = null): void
    {
        $doc['rows'][] = ['type' => $type, 'indent' => $indent, 'cells' => $cells, 'link' => $link];
    }

    public static function section(array &$doc, string $label, int $indent = 0): void
    {
        self::row($doc, [$label], $indent, 'section');
    }

    public static function total(array &$doc, array $cells, int $indent = 0): void
    {
        self::row($doc, $cells, $indent, 'total');
    }

    public static function grand(array &$doc, array $cells, int $indent = 0): void
    {
        self::row($doc, $cells, $indent, 'grand');
    }

    public static function blank(array &$doc): void
    {
        self::row($doc, [], 0, 'blank');
    }

    /** Money the way a printed statement sets it: no symbol, two decimals, minus in front. */
    public static function money(mixed $value, bool $withSymbol = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = number_format(abs((float) $value), 2);
        $text = ($withSymbol ? Settings::get('currency_symbol', 'RWF') . ' ' : '') . $number;

        return (float) $value < 0 ? '-' . $text : $text;
    }

    /**
     * The line under the company name on an exported page.
     *
     * Address, TIN and contact, in the order somebody reading a statement looks
     * for them, and only the parts that have been filled in. Every one of these
     * is a Settings field: nothing here names a company.
     */
    public static function companyDetails(): string
    {
        try {
            $parts = array_filter([
                trim((string) Settings::get('company_address', '')),
                trim((string) Settings::get('company_phone', '')),
                trim((string) Settings::get('company_email', '')),
                ($tin = trim((string) Settings::get('company_tin', ''))) !== '' ? 'TIN ' . $tin : '',
            ], static fn (string $part): bool => $part !== '');

            return implode('  ·  ', $parts);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function company(): string
    {
        try {
            return strtoupper(Settings::get('company_name', 'LMS LOGISTICS'));
        } catch (\Throwable) {
            return 'LMS LOGISTICS';
        }
    }

    /**
     * Colour and logo for the exported files.
     *
     * The logo is looked for rather than required: when it is genuinely absent
     * the exports print the company name instead of failing, because a missing
     * picture must never cost anyone their report.
     *
     * @return array{rgb: string, tint: string, pdf: array{0: float, 1: float, 2: float}, logo: ?string, logow: int, logoh: int}
     */
    public static function brand(): array
    {
        static $brand = null;
        if ($brand !== null) {
            return $brand;
        }

        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', Settings::get('report_brand_color', 'AD7D00')) ?: 'AD7D00');
        if (strlen($hex) !== 6) {
            $hex = 'AD7D00';
        }

        $brand = [
            'rgb' => $hex,
            'tint' => self::tint($hex),
            'pdf' => [
                hexdec(substr($hex, 0, 2)) / 255,
                hexdec(substr($hex, 2, 2)) / 255,
                hexdec(substr($hex, 4, 2)) / 255,
            ],
            'logo' => null,
            'logow' => 0,
            'logoh' => 0,
        ];

        // PDF and Excel both need real raster bytes, so the SVG the interface
        // uses is no good here; a PNG or JPG beside it is.
        $root = dirname(__DIR__, 2) . '/public/assets/img/';
        $configured = trim(Settings::get('company_logo', ''));
        $candidates = [];
        if ($configured !== '' && !str_contains($configured, '..')) {
            $candidates[] = $root . ltrim($configured, '/\\');
        }
        $candidates[] = $root . 'report-logo.png';
        $candidates[] = $root . 'report-logo.jpg';

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            $size = @getimagesize($path);
            if ($size !== false) {
                $brand['logo'] = $path;
                $brand['logow'] = (int) $size[0];
                $brand['logoh'] = (int) $size[1];
            }
            break;
        }

        return $brand;
    }

    /** A pale version of the brand colour, for the band behind a section heading. */
    private static function tint(string $hex): string
    {
        $out = '';
        for ($i = 0; $i < 6; $i += 2) {
            $channel = (int) hexdec(substr($hex, $i, 2));
            $out .= str_pad(strtoupper(dechex((int) round($channel + (255 - $channel) * 0.88))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /** The line at the foot of every export: the basis it was prepared on, and when. */
    public static function stamp(array $doc, bool $wide = false): string
    {
        $basis = trim((string) ($doc['basis'] ?? ''));
        $gap = $basis === '' ? ' ' : ($wide ? '  ' : ' ');

        return $basis . $gap . date('l, j F Y h:i A');
    }

    /** "Trial Balance" becomes "LMS_LOGISTICS_Trial_Balance.pdf". */
    public static function filename(array $doc, string $extension): string
    {
        $base = preg_replace('/[^A-Za-z0-9]+/', '_', $doc['company'] . '_' . $doc['title']) ?? 'report';

        return trim($base, '_') . '.' . $extension;
    }

    /**
     * Streams the book in whichever format was asked for and stops.
     * Anything already buffered is dropped first: one stray notice corrupts a
     * binary download.
     */
    public static function download(array $doc, string $format): never
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        match (strtolower($format)) {
            'pdf' => PdfWriter::send($doc),
            'xlsx', 'excel' => XlsxWriter::send($doc),
            default => CsvWriter::send($doc),
        };
    }

    /** Totals a column of a finished document, for a summary line on screen. */
    public static function columnTotal(array $doc, int $column): float
    {
        $total = 0.0;
        foreach ($doc['rows'] as $row) {
            if ($row['type'] !== 'row') {
                continue;
            }
            $value = $row['cells'][$column] ?? null;
            if (is_int($value) || is_float($value)) {
                $total += (float) $value;
            }
        }

        return $total;
    }
}
