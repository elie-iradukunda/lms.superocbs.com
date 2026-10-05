<?php

declare(strict_types=1);

namespace Support;

use ZipArchive;

/**
 * A small Excel (.xlsx) workbook of several sheets, with core PHP's ZipArchive.
 *
 * Each sheet is a list of rows; each cell is a value, or [value, style] where
 * style is one of the names below. Numbers stay numbers, so the workbook can be
 * summed and filtered. Columns get widths; the first row of a table can be
 * frozen. Enough for a report people open, read and work from.
 */
final class SheetBook
{
    /** Style name => index in styles.xml (cellXfs). */
    private const STYLES = ['text' => 0, 'title' => 1, 'subtitle' => 2, 'head' => 3, 'section' => 4, 'label' => 5, 'bold' => 6, 'number' => 7, 'money' => 8, 'wrap' => 9, 'good' => 10, 'warn' => 11, 'bad' => 12];

    /** @var list<array{name: string, rows: list<list<mixed>>, widths: list<float>, freeze: ?int}> */
    private array $sheets = [];

    public function __construct(private string $brandHex = '1F3A5F', private string $tintHex = 'E8EEF5')
    {
    }

    /** @param list<list<mixed>> $rows */
    public function sheet(string $name, array $rows, array $widths = [], ?int $freezeAfterRow = null): void
    {
        $name = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $name) ?? 'Sheet', 0, 31);
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'widths' => $widths, 'freeze' => $freezeAfterRow];
    }

    /** The workbook's bytes, or null when ZipArchive is not available. */
    public function output(): ?string
    {
        if (!class_exists(ZipArchive::class) || $this->sheets === []) {
            return null;
        }
        $temp = tempnam(sys_get_temp_dir(), 'lmsb');
        $zip = new ZipArchive();
        if ($temp === false || $zip->open($temp, ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $count = count($this->sheets);
        $types = '';
        $sheetsXml = '';
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetsXml .= '<sheet name="' . self::esc($this->sheets[$i - 1]['name']) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($count + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $types . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($sheet));
        }
        $zip->close();

        $bytes = (string) file_get_contents($temp);
        @unlink($temp);

        return $bytes;
    }

    private function sheetXml(array $sheet): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        if ($sheet['freeze'] !== null) {
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $sheet['freeze'] . '" topLeftCell="A' . ($sheet['freeze'] + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        } else {
            $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews>';
        }
        if ($sheet['widths'] !== []) {
            $xml .= '<cols>';
            foreach ($sheet['widths'] as $i => $width) {
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $width . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $r => $row) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($row) as $c => $cell) {
                [$value, $style] = is_array($cell) ? [$cell[0] ?? '', $cell[1] ?? 'text'] : [$cell, 'text'];
                if ($value === null || $value === '') {
                    if ($style !== 'text') {
                        $xml .= '<c r="' . self::col($c) . ($r + 1) . '" s="' . (self::STYLES[$style] ?? 0) . '"/>';
                    }
                    continue;
                }
                $ref = self::col($c) . ($r + 1);
                $s = self::STYLES[$style] ?? 0;
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $value . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData><pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/><pageSetup orientation="landscape" fitToWidth="1"/></worksheet>';
    }

    private function styles(): string
    {
        $brand = 'FF' . strtoupper($this->brandHex);
        $tint = 'FF' . strtoupper($this->tintHex);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="8">'
            . '<font><sz val="10"/><name val="Arial"/></font>'
            . '<font><b/><sz val="15"/><color rgb="' . $brand . '"/><name val="Arial"/></font>'
            . '<font><sz val="10"/><color rgb="FF6B7680"/><name val="Arial"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><color rgb="' . $brand . '"/><name val="Arial"/></font>'
            . '<font><b/><sz val="10"/><name val="Arial"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF2F855A"/><name val="Arial"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFC53030"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1C2733"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="' . $tint . '"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF4D6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><bottom style="thin"><color rgb="FFD5DBE1"/></bottom></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="13">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"><alignment vertical="top" wrapText="1"/></xf>'   // text
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/>'                                                 // title
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyFont="1"/>'                                                 // subtitle
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf>' // head
            . '<xf numFmtId="0" fontId="4" fillId="3" borderId="0" applyFont="1" applyFill="1"/>'                                   // section
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" applyFont="1" applyBorder="1"><alignment vertical="top"/></xf>' // label
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="1" applyFont="1" applyBorder="1"><alignment vertical="top" wrapText="1"/></xf>' // bold
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf>' // number
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf>' // money
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"><alignment vertical="top" wrapText="1"/></xf>'   // wrap
            . '<xf numFmtId="0" fontId="6" fillId="0" borderId="1" applyFont="1" applyBorder="1"/>'                                 // good
            . '<xf numFmtId="0" fontId="5" fillId="4" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>'                   // warn
            . '<xf numFmtId="0" fontId="7" fillId="0" borderId="1" applyFont="1" applyBorder="1"/>'                                 // bad
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function col(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26) . $name;
        }

        return $name;
    }

    private static function esc(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
