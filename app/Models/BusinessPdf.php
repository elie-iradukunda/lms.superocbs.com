<?php

declare(strict_types=1);

namespace Models;

use Support\FormPdf;
use Support\Report;

/**
 * The PDF that travels with a business email: invoice, receipt, quotation,
 * purchase order, delivery note.
 *
 * A customer's accountant files PDFs, not email bodies. Every document is drawn
 * in one house style, the same as the LPO form: the company's logo and details,
 * the document's name and number, who it is for, a table of lines, the totals,
 * the payment details, and a signature with the seal.
 *
 * $doc keys:
 *   title, number, meta [label => value], to_label, to [lines], right_label, right [lines],
 *   columns [[label, weight, align]], rows [[cells]], totals [label => value] (last one bold),
 *   blocks [[title, [lines]]], sign [name, role, signature path|null], seal (bool), file (name)
 */
final class BusinessPdf
{
    private const L = 36.0;
    private const R = 559.0;
    private const BOTTOM = 790.0;
    private const INK = [0.12, 0.15, 0.19];
    private const GREY = [0.42, 0.47, 0.53];
    private const RULE = [0.84, 0.87, 0.90];

    /** Writes the PDF under uploads and returns what Mailer needs to attach it. */
    public static function store(string $folder, array $doc): ?array
    {
        $directory = \Support\Mailer::uploadsRoot() . '/' . trim($folder, '/');
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $doc['file']) ?: 'document.pdf';
        if (@file_put_contents($directory . '/' . $filename, self::render($doc)) === false) {
            return null;
        }

        return ['filename' => $filename, 'path' => trim($folder, '/') . '/' . $filename];
    }

    public static function render(array $doc): string
    {
        $brand = Report::brand();
        $accent = $brand['pdf'];
        $tint = [hexdec(substr($brand['tint'], 0, 2)) / 255, hexdec(substr($brand['tint'], 2, 2)) / 255, hexdec(substr($brand['tint'], 4, 2)) / 255];
        $company = \company_name();
        $L = self::L;
        $width = self::R - self::L;

        $pdf = new FormPdf($doc['title'] . ' ' . $doc['number'] . ' - ' . $company);

        // ------------------------------------------------------------ header
        $logoWidth = $brand['logo'] ? $pdf->logo((string) $brand['logo'], $L, 34, 110, 44) : 0.0;
        $textX = $L + ($logoWidth > 0 ? $logoWidth + 10 : 0);
        $pdf->text($textX, 36, $company, 14, true, self::INK);
        $top = 56.0;
        foreach (array_filter([
            trim(Settings::get('company_address', '')),
            implode('  ·  ', array_filter([trim(Settings::get('company_phone', '')), trim(Settings::get('company_email', ''))])),
            ($tin = trim(Settings::get('company_tin', ''))) !== '' ? 'TIN ' . $tin : '',
        ]) as $detail) {
            $pdf->text($textX, $top, $detail, 8, false, self::GREY);
            $top += 11;
        }

        $pdf->text($L, 34, strtoupper((string) $doc['title']), 18, true, $accent, 'right', $width);
        $pdf->text($L, 57, (string) $doc['number'], 11, true, self::INK, 'right', $width);

        // The document's own dates and numbers, in a box on the right.
        $meta = array_filter($doc['meta'] ?? [], static fn ($v): bool => $v !== null && $v !== '');
        $boxX = 360.0;
        $boxTop = 76.0;
        if ($meta !== []) {
            $pdf->rect($boxX, $boxTop, self::R - $boxX, count($meta) * 15 + 8, null, self::RULE);
            $i = 0;
            foreach ($meta as $label => $value) {
                $y = $boxTop + 6 + $i * 15;
                $pdf->text($boxX + 8, $y, (string) $label, 8, false, self::GREY);
                $pdf->text($boxX + 8, $y, (string) $value, 8.5, true, self::INK, 'right', self::R - $boxX - 16);
                $i++;
            }
        }

        // Who it is for, and anything said on the right of it.
        $partyTop = max($top + 12, 96.0);
        $partyBottom = $partyTop;
        if (!empty($doc['to'])) {
            $pdf->text($L, $partyTop, strtoupper((string) ($doc['to_label'] ?? 'To')), 7.5, true, $accent);
            $y = $partyTop + 13;
            foreach ($doc['to'] as $n => $line) {
                $y += $pdf->paragraph($L, $y, 300, (string) $line, $n === 0 ? 10 : 8.5, $n === 0, $n === 0 ? self::INK : [0.25, 0.29, 0.34]);
            }
            $partyBottom = max($partyBottom, $y);
        }
        $y = max($partyBottom, $boxTop + count($meta) * 15 + 8) + 14;
        if (!empty($doc['right'])) {
            $pdf->text($boxX, $y - 4, strtoupper((string) ($doc['right_label'] ?? '')), 7.5, true, $accent);
            $ry = $y + 9;
            foreach ($doc['right'] as $line) {
                $ry += $pdf->paragraph($boxX, $ry, self::R - $boxX, (string) $line, 8.5, false, [0.25, 0.29, 0.34]);
            }
            $y = max($y, $ry + 6);
        }
        $pdf->line($L, $y, self::R, $y, 1.4, $accent);
        $y += 14;

        // ------------------------------------------------------------ the lines
        $columns = $doc['columns'] ?? [];
        $weights = array_map(static fn (array $c): float => (float) $c[1], $columns);
        $sum = array_sum($weights) ?: 1.0;
        $xs = [];
        $x = $L;
        foreach ($columns as $i => $column) {
            $w = $width * $weights[$i] / $sum;
            $xs[] = [$x, $w, $column[2] ?? 'left'];
            $x += $w;
        }
        $head = function (float $top) use ($pdf, $columns, $xs, $L, $width, $tint): float {
            $pdf->rect($L, $top, $width, 18, $tint);
            foreach ($columns as $i => $column) {
                [$cx, $cw, $align] = $xs[$i];
                $pdf->text($cx + 5, $top + 5.5, strtoupper((string) $column[0]), 7.5, true, self::INK, $align, $cw - 10);
            }

            return $top + 24;
        };

        if ($columns !== []) {
            $y = $head($y);
            foreach ($doc['rows'] ?? [] as $row) {
                $wrapped = [];
                $height = 0;
                foreach ($columns as $i => $column) {
                    $wrapped[$i] = FormPdf::wrap((string) ($row[$i] ?? ''), $xs[$i][1] - 10, 8.8);
                    $height = max($height, count($wrapped[$i]) * 11.5);
                }
                if ($y + $height > self::BOTTOM - 60) {
                    $pdf->addPage();
                    $y = $head(40.0);
                }
                foreach ($columns as $i => $column) {
                    [$cx, $cw, $align] = $xs[$i];
                    foreach ($wrapped[$i] as $n => $line) {
                        $pdf->text($cx + 5, $y + $n * 11.5, $line, 8.8, false, self::INK, $align, $cw - 10);
                    }
                }
                $y += $height + 5;
                $pdf->line($L, $y - 2, self::R, $y - 2, 0.5, self::RULE);
                $y += 4;
            }
        }

        // ------------------------------------------------------------ totals
        $totals = $doc['totals'] ?? [];
        if ($totals !== []) {
            if ($y + count($totals) * 17 > self::BOTTOM - 40) {
                $pdf->addPage();
                $y = 40.0;
            }
            $labelX = 330.0;
            $last = array_key_last($totals);
            foreach ($totals as $label => $value) {
                $bold = $label === $last;
                if ($bold) {
                    $pdf->rect($labelX, $y - 4, self::R - $labelX, 20, $tint);
                }
                $pdf->text($labelX + 8, $y + 1, (string) $label, $bold ? 10 : 9, $bold, $bold ? self::INK : self::GREY);
                $pdf->text($labelX, $y + 1, (string) $value, $bold ? 10.5 : 9, true, self::INK, 'right', self::R - $labelX - 8);
                $y += $bold ? 24 : 16;
            }
            $y += 6;
        }

        // ------------------------------------------------------------ blocks of text
        foreach ($doc['blocks'] ?? [] as [$title, $lines]) {
            $lines = array_values(array_filter((array) $lines, static fn ($l): bool => trim((string) $l) !== ''));
            if ($lines === []) {
                continue;
            }
            $need = 22 + count($lines) * 13;
            if ($y + $need > self::BOTTOM - 20) {
                $pdf->addPage();
                $y = 40.0;
            }
            $pdf->text($L, $y, strtoupper((string) $title), 7.5, true, $accent);
            $y += 13;
            foreach ($lines as $line) {
                $y += $pdf->paragraph($L, $y, $width, (string) $line, 8.6, false, [0.22, 0.26, 0.31]) + 2;
            }
            $y += 8;
        }

        // ------------------------------------------------------------ signature and seal
        if (!empty($doc['sign']) || !empty($doc['seal'])) {
            if ($y + 96 > self::BOTTOM) {
                $pdf->addPage();
                $y = 40.0;
            }
            $y += 6;
            $sign = $doc['sign'] ?? [];
            if (!empty($sign['signature']) && is_file((string) $sign['signature'])) {
                $pdf->image((string) $sign['signature'], $L, $y, 140, 40);
            }
            $pdf->line($L, $y + 44, $L + 190, $y + 44, 0.6, self::GREY);
            $pdf->text($L, $y + 49, (string) ($sign['name'] ?? ''), 8.8, true, self::INK);
            $pdf->text($L, $y + 61, trim((string) ($sign['role'] ?? '') . ', ' . $company, ', '), 8, false, self::GREY);
            $seal = !empty($doc['seal']) ? CompanyImage::path(CompanyImage::SEAL) : null;
            if ($seal !== null) {
                $pdf->image($seal, 270, $y - 6, 86, 86, true);
            }
        }

        // ------------------------------------------------------------ footer on every page
        $pages = $pdf->pageCount();
        for ($p = 0; $p < $pages; $p++) {
            $pdf->usePage($p);
            $pdf->line($L, 806, self::R, 806, 0.5, self::RULE);
            $pdf->text($L, 811, $company . '  ·  ' . $doc['title'] . ' ' . $doc['number'], 7.2, false, self::GREY);
            $pdf->text($L, 811, sprintf('Page %d of %d', $p + 1, $pages), 7.2, false, self::GREY, 'right', $width);
        }

        return $pdf->output();
    }
}
