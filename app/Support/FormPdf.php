<?php

declare(strict_types=1);

namespace Support;

/**
 * A fillable PDF form, in core PHP.
 *
 * Text, lines, boxes and a logo are printed and cannot be changed; fields are
 * real PDF form fields (AcroForm) that anyone can type into with Adobe Reader,
 * Chrome, Edge, Firefox or a phone, then save and send back. Each field carries
 * its own appearance, so even a viewer that cannot edit forms shows what was
 * filled in, and NeedAppearances asks the others to redraw it as people type.
 *
 * Coordinates are in points from the top-left corner of an A4 page, which is
 * how a layout is thought about; they are turned upside down for the PDF.
 */
final class FormPdf
{
    public const WIDTH = 595.0;
    public const HEIGHT = 842.0;

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

    /** The shade behind a field, so the reader sees at once what is theirs to fill. */
    private const FIELD_FILL = [0.91, 0.945, 1.0];
    private const FIELD_BORDER = [0.58, 0.66, 0.80];

    /** @var list<array{stream: string, fields: list<array>}> */
    private array $pages = [];
    private int $current = -1;
    /** @var array<string, array{data: string, w: int, h: int, name: string}> images drawn, each embedded once */
    private array $images = [];
    private array $names = [];

    public function __construct(private string $title = 'Form')
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        $this->pages[] = ['stream' => '', 'fields' => []];
        $this->current = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function usePage(int $index): void
    {
        $this->current = max(0, min($index, count($this->pages) - 1));
    }

    // ------------------------------------------------------------ measuring

    public static function textWidth(string $text, float $size, bool $bold = false): float
    {
        $table = $bold ? self::WIDTHS_BOLD : self::WIDTHS_REGULAR;
        $encoded = self::encode($text);
        $total = 0;
        $length = strlen($encoded);
        for ($i = 0; $i < $length; $i++) {
            $code = ord($encoded[$i]);
            $total += ($code >= 32 && $code <= 126) ? $table[$code - 32] : 556;
        }

        return $total * $size / 1000;
    }

    /** @return list<string> the text broken into lines no wider than $width */
    public static function wrap(string $text, float $width, float $size, bool $bold = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [''] as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/', trim($paragraph)) ?: [] as $word) {
                if ($word === '') {
                    continue;
                }
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($line !== '' && self::textWidth($candidate, $size, $bold) > $width) {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $candidate;
                }
            }
            $lines[] = $line;
        }

        return $lines;
    }

    // ------------------------------------------------------------- drawing

    /** One line of text; $top is the top of the line, $align 'left', 'right' or 'center' within $width. */
    public function text(float $x, float $top, string $text, float $size = 9.0, bool $bold = false, array $colour = [0.12, 0.15, 0.19], string $align = 'left', float $width = 0.0): void
    {
        if ($text === '') {
            return;
        }
        if ($align !== 'left' && $width > 0) {
            $measured = self::textWidth($text, $size, $bold);
            $x += $align === 'right' ? $width - $measured : ($width - $measured) / 2;
        }
        $this->pages[$this->current]['stream'] .= sprintf(
            "q %.3f %.3f %.3f rg BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET Q\n",
            $colour[0], $colour[1], $colour[2], $bold ? 'F2' : 'F1', $size, $x, self::HEIGHT - $top - $size * 0.8, self::escape($text)
        );
    }

    /** Wrapped text. Returns the height it took. */
    public function paragraph(float $x, float $top, float $width, string $text, float $size = 8.5, bool $bold = false, array $colour = [0.2, 0.24, 0.29], float $leading = 1.35): float
    {
        $lines = self::wrap($text, $width, $size, $bold);
        foreach ($lines as $i => $line) {
            $this->text($x, $top + $i * $size * $leading, $line, $size, $bold, $colour);
        }

        return count($lines) * $size * $leading;
    }

    public function rect(float $x, float $top, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lineWidth = 0.6): void
    {
        $y = self::HEIGHT - $top - $h;
        $ops = 'q ';
        if ($fill !== null) {
            $ops .= sprintf('%.3f %.3f %.3f rg ', ...$fill);
        }
        if ($stroke !== null) {
            $ops .= sprintf('%.3f %.3f %.3f RG %.2f w ', $stroke[0], $stroke[1], $stroke[2], $lineWidth);
        }
        $paint = $fill !== null && $stroke !== null ? 'B' : ($fill !== null ? 'f' : 'S');
        $this->pages[$this->current]['stream'] .= $ops . sprintf("%.2f %.2f %.2f %.2f re %s Q\n", $x, $y, $w, $h, $paint);
    }

    public function line(float $x1, float $top1, float $x2, float $top2, float $width = 0.6, array $colour = [0.75, 0.78, 0.82]): void
    {
        $this->pages[$this->current]['stream'] .= sprintf(
            "q %.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S Q\n",
            $colour[0], $colour[1], $colour[2], $width, $x1, self::HEIGHT - $top1, $x2, self::HEIGHT - $top2
        );
    }

    /** The company logo, a PNG or JPEG file, drawn at most $maxW by $maxH. Returns the width used. */
    public function logo(string $path, float $x, float $top, float $maxW, float $maxH): float
    {
        return $this->image($path, $x, $top, $maxW, $maxH);
    }

    /**
     * Any PNG or JPEG (the logo, a signature), drawn at most $maxW by $maxH,
     * left-aligned, or centred in the box when $centre is set. Each file is
     * embedded once however often it is drawn. Returns the width used.
     */
    public function image(string $path, float $x, float $top, float $maxW, float $maxH, bool $centre = false): float
    {
        $key = md5($path);
        if (!isset($this->images[$key])) {
            $loaded = self::loadImage($path);
            if ($loaded === null) {
                return 0.0;
            }
            $this->images[$key] = $loaded + ['name' => 'Im' . (count($this->images) + 1)];
        }
        $image = $this->images[$key];
        $scale = min($maxW / $image['w'], $maxH / $image['h']);
        $w = $image['w'] * $scale;
        $h = $image['h'] * $scale;
        if ($centre) {
            $x += ($maxW - $w) / 2;
            $top += ($maxH - $h) / 2;
        }
        $this->pages[$this->current]['stream'] .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $w, $h, $x, self::HEIGHT - $top - $h, $image['name']);

        return $w;
    }

    /**
     * A box the reader types into. $value is what it starts with (a detail we
     * already know, for them to check); a multi-line field wraps its text.
     */
    public function field(string $name, float $x, float $top, float $w, float $h, string $value = '', bool $multiline = false, float $size = 8.5): void
    {
        $name = preg_replace('/[^A-Za-z0-9_.-]/', '_', $name) ?: 'field';
        $base = $name;
        for ($n = 2; isset($this->names[$name]); $n++) {
            $name = $base . '_' . $n;
        }
        $this->names[$name] = true;

        $this->pages[$this->current]['fields'][] = [
            'name' => $name, 'x' => $x, 'y' => self::HEIGHT - $top - $h, 'w' => $w, 'h' => $h,
            'value' => $value, 'multiline' => $multiline, 'size' => $size,
        ];
    }

    // ------------------------------------------------------------- the file

    public function output(): string
    {
        $objects = [];
        $next = 1;
        $reserve = static function () use (&$next): int {
            return $next++;
        };

        $catalogId = $reserve();
        $pagesId = $reserve();
        $fontRegular = $reserve();
        $fontBold = $reserve();
        $imageIds = [];
        foreach ($this->images as $key => $image) {
            $imageIds[$key] = $reserve();
        }
        $infoId = $reserve();

        $objects[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $xobjects = '';
        foreach ($this->images as $key => $image) {
            $objects[$imageIds[$key]] = ['image' => $image];
            $xobjects .= ' /' . $image['name'] . ' ' . $imageIds[$key] . ' 0 R';
        }
        $objects[$infoId] = sprintf('<< /Title (%s) /Producer (%s) /CreationDate (D:%s) >>', self::escape($this->title), self::escape(\vendor_name() . ' LMS'), date('YmdHis'));

        $kids = [];
        $allFields = [];
        $total = count($this->pages);

        foreach ($this->pages as $page) {
            $pageId = $reserve();
            $contentId = $reserve();
            $kids[] = $pageId . ' 0 R';
            $objects[$contentId] = ['stream' => $page['stream']];

            $annots = [];
            foreach ($page['fields'] as $field) {
                $fieldId = $reserve();
                $apId = $reserve();
                $annots[] = $fieldId . ' 0 R';
                $allFields[] = $fieldId . ' 0 R';

                $objects[$apId] = ['stream' => self::appearance($field), 'dict' => sprintf(
                    '/Type /XObject /Subtype /Form /BBox [0 0 %.2f %.2f] /Resources << /Font << /Helv %d 0 R >> >>',
                    $field['w'], $field['h'], $fontRegular
                )];
                $objects[$fieldId] = vsprintf(
                    '<< /Type /Annot /Subtype /Widget /FT /Tx /T (%s) /Rect [%.2f %.2f %.2f %.2f] /F 4 /P %d 0 R'
                    . ' /DA (/Helv %.1f Tf 0.06 0.12 0.35 rg) /MK << /BG [%.3f %.3f %.3f] /BC [%.3f %.3f %.3f] >> /BS << /W 0.6 /S /S >>'
                    . '%s /V (%s) /AP << /N %d 0 R >> >>',
                    array_merge(
                        [$field['name'], $field['x'], $field['y'], $field['x'] + $field['w'], $field['y'] + $field['h'], $pageId, $field['size']],
                        self::FIELD_FILL,
                        self::FIELD_BORDER,
                        [$field['multiline'] ? ' /Ff 4096' : '', self::escape($field['value'], true), $apId]
                    )
                );
            }

            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >>%s >> /Contents %d 0 R%s >>',
                $pagesId, self::WIDTH, self::HEIGHT, $fontRegular, $fontBold,
                $xobjects !== '' ? ' /XObject <<' . $xobjects . ' >>' : '', $contentId,
                $annots !== [] ? ' /Annots [' . implode(' ', $annots) . ']' : ''
            );
        }

        $objects[$pagesId] = '<< /Type /Pages /Count ' . $total . ' /Kids [' . implode(' ', $kids) . '] >>';
        $objects[$catalogId] = sprintf(
            '<< /Type /Catalog /Pages %d 0 R /AcroForm << /Fields [%s] /NeedAppearances true /DA (/Helv 0 Tf 0 g) /DR << /Font << /Helv %d 0 R >> >> >> >>',
            $pagesId, implode(' ', $allFields), $fontRegular
        );

        ksort($objects);
        $out = "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            if (is_array($body) && isset($body['image'])) {
                $image = $body['image'];
                $out .= "{$id} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$image['w']} /Height {$image['h']} /ColorSpace /DeviceRGB"
                    . ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($image['data']) . " >>\nstream\n" . $image['data'] . "\nendstream\nendobj\n";
            } elseif (is_array($body)) {
                $out .= "{$id} 0 obj\n<< " . ($body['dict'] ?? '') . ' /Length ' . strlen($body['stream']) . " >>\nstream\n" . $body['stream'] . "\nendstream\nendobj\n";
            } else {
                $out .= "{$id} 0 obj\n{$body}\nendobj\n";
            }
        }

        $size = max(array_keys($objects)) + 1;
        $xref = strlen($out);
        $out .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        return $out . "trailer\n<< /Size {$size} /Root {$catalogId} 0 R /Info {$infoId} 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    // ------------------------------------------------------------ internals

    /** What a field looks like before anyone touches it: the shade, the border and any starting text. */
    private static function appearance(array $field): string
    {
        $w = $field['w'];
        $h = $field['h'];
        $size = $field['size'];
        $ops = sprintf('%.3f %.3f %.3f rg 0 0 %.2f %.2f re f ', ...array_merge(self::FIELD_FILL, [$w, $h]));
        $ops .= sprintf('%.3f %.3f %.3f RG 0.6 w 0.3 0.3 %.2f %.2f re S ', ...array_merge(self::FIELD_BORDER, [$w - 0.6, $h - 0.6]));
        $ops .= '/Tx BMC ';
        if ($field['value'] !== '') {
            $lines = $field['multiline'] ? self::wrap($field['value'], $w - 4, $size) : [$field['value']];
            $ops .= sprintf('q BT /Helv %.1f Tf 0.06 0.12 0.35 rg ', $size);
            $first = $field['multiline'] ? $h - 2 - $size : ($h - $size * 0.7) / 2;
            foreach ($lines as $i => $line) {
                $ops .= sprintf('1 0 0 1 2 %.2f Tm (%s) Tj ', $first - $i * $size * 1.15, self::escape($line));
            }
            $ops .= 'ET Q ';
        }

        return $ops . 'EMC';
    }

    private static function encode(string $value): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

        return $converted === false ? (preg_replace('/[^\x20-\x7E]/', '', $value) ?? '') : $converted;
    }

    private static function escape(string $value, bool $keepBreaks = false): string
    {
        $converted = self::encode($value);

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', $keepBreaks ? '\\r' : ' '], $converted);
    }

    /** @return array{data: string, w: int, h: int}|null */
    private static function loadImage(string $path): ?array
    {
        $size = @getimagesize($path);
        if ($size === false) {
            return null;
        }
        if (($size[2] ?? 0) === IMAGETYPE_JPEG) {
            $bytes = @file_get_contents($path);

            return $bytes === false ? null : ['data' => $bytes, 'w' => (int) $size[0], 'h' => (int) $size[1]];
        }
        if (($size[2] ?? 0) !== IMAGETYPE_PNG || !function_exists('imagecreatefrompng')) {
            return null;
        }
        $image = @imagecreatefrompng($path);
        if ($image === false) {
            return null;
        }
        // A DCTDecode image has no transparency, so the logo is laid on white.
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
}
