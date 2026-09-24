<?php

declare(strict_types=1);

namespace Support;

/**
 * A PDF writer for the accounting books, using nothing but core PHP.
 *
 * WHY BY HAND
 * This installation has no composer and no TCPDF, and adding one to a XAMPP or
 * shared host is more fragile than these reports are worth. Everything here
 * needs only core PHP; GD is used for a logo and skipped when it is absent.
 *
 * WHAT IT LAYS OUT
 * One thing: a titled, columnar report with headings, indented rows, subtotals
 * and a grand total, which is exactly what a Trial Balance, Profit & Loss,
 * Balance Sheet, Cash Flow statement or General Ledger is. It uses the two PDF
 * base-14 fonts, so nothing is embedded and the file stays small.
 */
final class PdfWriter
{
    /** Helvetica and Helvetica-Bold advance widths, ASCII 32-126, per 1000 units. */
    private const WIDTHS_REGULAR = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    private const WIDTHS_BOLD = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
    ];

    public static function send(array $doc): never
    {
        $columns = $doc['columns'];

        // Landscape once there are more than five columns: the Journal has
        // eight and the General Ledger seven, and neither fits a portrait page.
        $landscape = count($columns) > 5;
        $pageWidth = $landscape ? 842.0 : 595.0;
        $pageHeight = $landscape ? 595.0 : 842.0;

        $left = 34.0;
        $right = 34.0;
        $top = 40.0;
        $bottom = 44.0;
        $usable = $pageWidth - $left - $right;

        // Column widths arrive as relative weights and are shared out here.
        $weights = array_map(static fn (array $c): float => (float) ($c['width'] ?? 18.0), $columns);
        $sum = array_sum($weights) ?: 1.0;
        $widths = array_map(static fn (float $w): float => $usable * $w / $sum, $weights);

        $offsets = [];
        $x = $left;
        foreach ($widths as $w) {
            $offsets[] = $x;
            $x += $w;
        }

        $bodySize = 8.0;
        $lineHeight = 12.5;
        $stamp = Report::stamp($doc, true);

        // Company 16, title 15, period 13, stamp 13, then a clear gap before
        // the column headings.
        $headerHeight = 16 + 15 + 13 + 13 + 28;
        $startY = $pageHeight - $top - $headerHeight;

        $pages = [];
        $current = [];
        $y = $startY;
        foreach ($doc['rows'] as $row) {
            $need = $row['type'] === 'blank' ? $lineHeight * 0.6 : $lineHeight;
            if ($y - $need < $bottom) {
                $pages[] = $current;
                $current = [];
                $y = $startY;
            }
            $current[] = [$row, $y];
            $y -= $need;
        }
        $pages[] = $current;
        $pageCount = count($pages);

        $brand = Report::brand();
        $logo = null;
        $logoWidth = 0.0;
        $logoHeight = 0.0;
        if ($brand['logo'] !== null) {
            $logo = self::logoStream($brand['logo']);
            if ($logo !== null) {
                $logoHeight = 34.0;
                $logoWidth = $logoHeight * max(1, $brand['logow']) / max(1, $brand['logoh']);
            }
        }

        $streams = [];
        foreach ($pages as $index => $rows) {
            $s = self::band(0, $pageHeight - $top + 14, $pageWidth, 3.5, $brand['pdf']);

            if ($logo !== null) {
                $s .= sprintf(
                    "q %.2f 0 0 %.2f %.2f %.2f cm /Logo Do Q\n",
                    $logoWidth,
                    $logoHeight,
                    $left,
                    $pageHeight - $top - 4 - $logoHeight
                );
            }

            $s .= self::centre($doc['company'], $pageHeight - $top - 12, $pageWidth, 11, true, $brand['pdf']);

            // The address, phone and TIN under the name. A statement that
            // leaves the building has to say which company it belongs to, and
            // a trading name on its own does not.
            $details = (string) ($doc['company_details'] ?? '');
            $shift = 0;
            if ($details !== '') {
                $s .= self::centre($details, $pageHeight - $top - 23, $pageWidth, 7.5, false, [0.42, 0.46, 0.5]);
                $shift = 9;
            }

            $s .= self::centre($doc['title'], $pageHeight - $top - 27 - $shift, $pageWidth, 13, true);
            if (($doc['period'] ?? '') !== '') {
                $s .= self::centre((string) $doc['period'], $pageHeight - $top - 40 - $shift, $pageWidth, 9, false);
            }
            $s .= self::text($left, $pageHeight - $top - 53 - $shift, $stamp, 7, false, 0.4);

            $pageLabel = ($index + 1) . '/' . $pageCount;
            $s .= self::text(
                $pageWidth - $right - self::textWidth($pageLabel, 7, false),
                $pageHeight - $top - 53,
                $pageLabel,
                7,
                false,
                0.4
            );

            // Column headings, repeated on every page.
            $headY = $startY + 15;
            foreach ($columns as $i => $column) {
                $align = $column['align'] ?? ($i === 0 ? 'left' : 'right');
                $s .= self::cell(strtoupper((string) $column['label']), $offsets[$i], $widths[$i], $headY, 8.0, true, $align, 0.25, false, true);
            }
            $s .= self::line($left, $headY - 4, $pageWidth - $right, $headY - 4, 0.9, $brand['pdf']);

            foreach ($rows as [$row, $rowY]) {
                if ($row['type'] === 'blank') {
                    continue;
                }

                $bold = in_array($row['type'], ['section', 'total', 'grand'], true);

                if ($row['type'] === 'section') {
                    $s .= self::band($left, $rowY - 3.5, $pageWidth - $right - $left, 12.0, self::hexToPdf($brand['tint']));
                }
                if ($row['type'] === 'total') {
                    $s .= self::line($left, $rowY + 9.5, $pageWidth - $right, $rowY + 9.5, 0.4, 0.55);
                }
                if ($row['type'] === 'grand') {
                    $s .= self::line($left, $rowY + 10.5, $pageWidth - $right, $rowY + 10.5, 0.6, 0.1);
                    $s .= self::line($left, $rowY - 3.5, $pageWidth - $right, $rowY - 3.5, 0.5, 0.1);
                    $s .= self::line($left, $rowY - 5.5, $pageWidth - $right, $rowY - 5.5, 0.5, 0.1);
                }

                foreach ($columns as $i => $column) {
                    $value = $row['cells'][$i] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $align = $column['align'] ?? ($i === 0 ? 'left' : 'right');
                    $numeric = is_int($value) || is_float($value);
                    if ($numeric) {
                        $printed = Report::money($value, !empty($column['currency']));
                        $align = 'right';
                    } else {
                        $printed = (string) $value;
                    }

                    $pad = $i === 0 ? $row['indent'] * 11.0 : 0.0;
                    $s .= self::cell($printed, $offsets[$i] + $pad, $widths[$i] - $pad, $rowY, $bodySize, $bold, $align, 0.1, $numeric);
                }
            }

            $streams[] = $s;
        }

        self::emit($streams, $pageWidth, $pageHeight, Report::filename($doc, 'pdf'), $logo);
    }

    // ------------------------------------------------------------ measuring

    private static function widths(bool $bold): array
    {
        return $bold ? self::WIDTHS_BOLD : self::WIDTHS_REGULAR;
    }

    private static function textWidth(string $text, float $size, bool $bold): float
    {
        $table = self::widths($bold);
        $total = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $code = ord($text[$i]);
            $total += ($code >= 32 && $code <= 126) ? $table[$code - 32] : 500;
        }

        return $total * $size / 1000;
    }

    /** Cut a string to fit a column, with an ellipsis when it does not. */
    private static function fit(string $text, float $maxWidth, float $size, bool $bold): string
    {
        if (self::textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        $dots = self::textWidth('...', $size, $bold);
        $table = self::widths($bold);
        $out = '';
        $width = 0.0;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $code = ord($text[$i]);
            $charWidth = (($code >= 32 && $code <= 126) ? $table[$code - 32] : 500) * $size / 1000;
            if ($width + $charWidth + $dots > $maxWidth) {
                break;
            }
            $out .= $text[$i];
            $width += $charWidth;
        }

        return rtrim($out) . '...';
    }

    private static function escape(string $value): string
    {
        // WinAnsi is close enough for these reports; anything outside it would
        // render as the wrong glyph, so it is stripped rather than shown.
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
        }

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $converted);
    }

    // ------------------------------------------------------------- drawing

    /** @return array{0: float, 1: float, 2: float} */
    private static function rgb(mixed $tone): array
    {
        if (is_array($tone)) {
            return [(float) $tone[0], (float) $tone[1], (float) $tone[2]];
        }

        return [(float) $tone, (float) $tone, (float) $tone];
    }

    private static function hexToPdf(string $hex): array
    {
        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }

    private static function text(float $x, float $y, string $text, float $size, bool $bold = false, mixed $tone = 0.1): string
    {
        [$r, $g, $b] = self::rgb($tone);

        return sprintf(
            "q %.3f %.3f %.3f rg BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET Q\n",
            $r,
            $g,
            $b,
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            $y,
            self::escape($text)
        );
    }

    private static function centre(string $text, float $y, float $pageWidth, float $size, bool $bold, mixed $tone = 0.1): string
    {
        $width = self::textWidth($text, $size, $bold);

        return self::text(($pageWidth - $width) / 2, $y, $text, $size, $bold, $tone);
    }

    private static function band(float $x, float $y, float $w, float $h, mixed $tone): string
    {
        [$r, $g, $b] = self::rgb($tone);

        return sprintf("q %.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f Q\n", $r, $g, $b, $x, $y, $w, $h);
    }

    private static function line(float $x1, float $y1, float $x2, float $y2, float $weight, mixed $tone): string
    {
        [$r, $g, $b] = self::rgb($tone);

        return sprintf("q %.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S Q\n", $r, $g, $b, $weight, $x1, $y1, $x2, $y2);
    }

    /**
     * Draw one cell.
     *
     * A figure is never truncated. "12,00..." is not a smaller version of
     * 12,000.00, it is a different number, and a report that prints it is worse
     * than one that prints nothing. So a right-aligned cell that does not fit is
     * set smaller, down to 5.5pt, and only text is ever shortened.
     */
    private static function cell(
        string $text,
        float $x,
        float $width,
        float $y,
        float $size,
        bool $bold,
        string $align,
        mixed $tone,
        bool $numeric = false,
        bool $isHeading = false
    ): string {
        $available = $width - 5;
        $floor = ($numeric || $align === 'right') ? 5.5 : ($isHeading ? 4.8 : 5.8);

        while ($size > $floor && self::textWidth($text, $size, $bold) > $available) {
            $size -= 0.25;
        }

        if (!$numeric && $align !== 'right') {
            $text = self::fit($text, $available, $size, $bold);
        }

        if ($align === 'right') {
            $x = $x + $width - 3 - self::textWidth($text, $size, $bold);
        } elseif ($align === 'center') {
            $x = $x + ($width - self::textWidth($text, $size, $bold)) / 2;
        } else {
            $x += 2;
        }

        return self::text($x, $y, $text, $size, $bold, $tone);
    }

    /**
     * Read the logo and return it ready to embed.
     *
     * PNGs go through GD to JPEG first: a PDF carries a JPEG verbatim with
     * /DCTDecode, whereas embedding a PNG means unpacking its filters by hand.
     * Without GD the logo is skipped and the report still prints.
     *
     * @return array{data: string, w: int, h: int}|null
     */
    private static function logoStream(string $path): ?array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'jpg' || $extension === 'jpeg') {
            $bytes = @file_get_contents($path);
            $size = @getimagesize($path);
            if ($bytes === false || $size === false) {
                return null;
            }

            return ['data' => $bytes, 'w' => (int) $size[0], 'h' => (int) $size[1]];
        }

        if (!function_exists('imagecreatefrompng')) {
            return null;
        }

        $image = @imagecreatefrompng($path);
        if ($image === false) {
            return null;
        }

        // Flatten onto white: a DCTDecode image carries no transparency.
        $w = imagesx($image);
        $h = imagesy($image);
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);

        ob_start();
        imagejpeg($flat, null, 92);
        $bytes = (string) ob_get_clean();

        imagedestroy($image);
        imagedestroy($flat);

        return $bytes === '' ? null : ['data' => $bytes, 'w' => $w, 'h' => $h];
    }

    /** Assemble the objects, write the cross-reference table, send the file. */
    private static function emit(array $streams, float $pageWidth, float $pageHeight, string $filename, ?array $logo): never
    {
        $count = count($streams);
        $objects = [];

        // 1 catalog, 2 pages, 3 regular font, 4 bold font, 5 logo, then a page
        // object and a content stream for each page.
        $logoId = $logo !== null ? 5 : 0;
        $base = $logo !== null ? 6 : 5;

        $kids = [];
        for ($i = 0; $i < $count; $i++) {
            $kids[] = ($base + $i * 2) . ' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Count ' . $count . ' /Kids [' . implode(' ', $kids) . '] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $xobject = '';
        if ($logo !== null) {
            $objects[$logoId] = 'IMAGE:' . $logo['data'];
            $xobject = '/XObject << /Logo ' . $logoId . ' 0 R >> ';
        }

        foreach ($streams as $i => $stream) {
            $pageObject = $base + $i * 2;
            $contentObject = $pageObject + 1;
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> %s>> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                $xobject,
                $contentObject
            );
            $objects[$contentObject] = 'STREAM:' . $stream;
        }

        ksort($objects);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            if (str_starts_with($body, 'IMAGE:')) {
                $data = substr($body, 6);
                $out .= "{$id} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$logo['w']} "
                    . "/Height {$logo['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 "
                    . '/Filter /DCTDecode /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream\nendobj\n";
            } elseif (str_starts_with($body, 'STREAM:')) {
                $data = substr($body, 7);
                $out .= "{$id} 0 obj\n<< /Length " . strlen($data) . " >>\nstream\n" . $data . "\nendstream\nendobj\n";
            } else {
                $out .= "{$id} 0 obj\n" . $body . "\nendobj\n";
            }
        }

        $maxId = max(array_keys($objects));
        $xrefPosition = strlen($out);
        $out .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $out .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPosition}\n%%EOF";

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($out));
            header('Cache-Control: max-age=0');
        }

        echo $out;
        exit;
    }
}
