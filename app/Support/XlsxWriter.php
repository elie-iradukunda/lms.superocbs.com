<?php

declare(strict_types=1);

namespace Support;

use ZipArchive;

/**
 * An Excel (.xlsx) writer for the accounting books.
 *
 * An xlsx file is a zip of XML parts. This writes the six that matter using
 * only ZipArchive: no PhpSpreadsheet, no composer, nothing to install.
 *
 * Strings go inline rather than through a shared-string table: a little larger
 * on disk, far simpler to get right, and these reports run to thousands of rows
 * rather than millions.
 *
 * TYPOGRAPHY
 * Arial throughout. Title bold 14 centred, report name bold 12, period bold 10,
 * headings bold 9 with a rule under them, body plain 8, totals bold with a rule
 * over the figure, numbers as #,##0.00. No fills and no banding: a ledger of ten
 * thousand lines is read by scanning a column, and a coloured band behind every
 * other row fights that.
 *
 * Figures are written as real numbers, not text, so the finance team can sum and
 * pivot the export instead of retyping it.
 */
final class XlsxWriter
{
    public static function send(array $doc): never
    {
        $temp = tempnam(sys_get_temp_dir(), 'lmsx');
        $zip = new ZipArchive();

        if ($temp === false || $zip->open($temp, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('The Excel file could not be built.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());

        $drawing = self::addLogo($zip, $doc);

        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($doc, $drawing));
        $zip->close();

        $name = Report::filename($doc, 'xlsx');
        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . (string) filesize($temp));
            header('Cache-Control: max-age=0');
        }

        readfile($temp);
        @unlink($temp);
        exit;
    }

    // -------------------------------------------------------------- helpers

    private static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** 0 becomes A, 25 becomes Z, 26 becomes AA. */
    private static function column(int $index): string
    {
        $out = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $out = chr(65 + (($i - 1) % 26)) . $out;
        }

        return $out;
    }

    /**
     * How wide each column has to be for Excel to show what is in it.
     *
     * The width on a column is a relative weight for the PDF, where the page is
     * a fixed size. Excel reads the same number as a character count, so a
     * weight of 8.5 on a money column makes every figure in it print as ######.
     * Each column is measured against its heading and its longest cell instead,
     * and capped so one long description cannot push the rest off the screen.
     *
     * @return list<int>
     */
    private static function widths(array $doc): array
    {
        $widths = [];
        foreach ($doc['columns'] as $i => $column) {
            $widths[$i] = strlen((string) $column['label']) + 2;
        }

        foreach ($doc['rows'] as $row) {
            if ($row['type'] === 'blank') {
                continue;
            }
            foreach ($doc['columns'] as $i => $column) {
                $value = $row['cells'][$i] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $needed = (is_int($value) || is_float($value))
                    ? strlen(number_format((float) $value, 2)) + 2
                    : strlen((string) $value) + 2 + ($row['indent'] * 2);

                if ($needed > $widths[$i]) {
                    $widths[$i] = $needed;
                }
            }
        }

        foreach ($doc['columns'] as $i => $column) {
            if (!empty($column['currency'])) {
                $widths[$i] = max($widths[$i] + 2, 16);
            }
            $widths[$i] = max(9, min(90, $widths[$i]));
        }

        return array_values($widths);
    }

    // ---------------------------------------------------------------- parts

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /**
     * Style slots:
     *   0 body text   1 company   2 title   3 period   4 column heading
     *   5 money       6 bold text 7 bold money        8 subtotal money
     *   9 grand text 10 grand money      11-13 indents 1-3
     *  14 section text 15 section money  16 footer stamp
     */
    private static function styles(): string
    {
        $brand = Report::brand();
        $rgb = $brand['rgb'];

        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $x .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $x .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>';

        $x .= '<fonts count="7">';
        $x .= '<font><sz val="8"/><name val="Arial"/></font>';
        $x .= '<font><b/><sz val="14"/><color rgb="FF' . $rgb . '"/><name val="Arial"/></font>';
        $x .= '<font><b/><sz val="12"/><name val="Arial"/></font>';
        $x .= '<font><b/><sz val="10"/><name val="Arial"/></font>';
        $x .= '<font><b/><sz val="8"/><name val="Arial"/></font>';
        $x .= '<font><b/><sz val="9"/><color rgb="FF' . $rgb . '"/><name val="Arial"/></font>';
        $x .= '<font><i/><sz val="8"/><color rgb="FF666666"/><name val="Arial"/></font>';
        $x .= '</fonts>';

        // No fills: the restraint is the point on a long ledger.
        $x .= '<fills count="2"><fill><patternFill patternType="none"/></fill>';
        $x .= '<fill><patternFill patternType="gray125"/></fill></fills>';

        $x .= '<borders count="4">';
        $x .= '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $x .= '<border><left/><right/><top style="thin"><color rgb="FF000000"/></top><bottom/><diagonal/></border>';
        $x .= '<border><left/><right/><top style="thin"><color rgb="FF000000"/></top>';
        $x .= '<bottom style="double"><color rgb="FF000000"/></bottom><diagonal/></border>';
        $x .= '<border><left/><right/><top/><bottom style="thin"><color rgb="FF' . $rgb . '"/></bottom><diagonal/></border>';
        $x .= '</borders>';

        $x .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $x .= '<cellXfs count="17">';
        $x .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1"/>';
        $x .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';
        $x .= '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>';
        $x .= '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>';
        $x .= '<xf numFmtId="0" fontId="5" fillId="0" borderId="3" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" wrapText="1"/></xf>';
        $x .= '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>';
        $x .= '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>';
        $x .= '<xf numFmtId="164" fontId="4" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>';
        $x .= '<xf numFmtId="164" fontId="4" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>';
        $x .= '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>';
        $x .= '<xf numFmtId="164" fontId="4" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>';
        $x .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment indent="1"/></xf>';
        $x .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment indent="2"/></xf>';
        $x .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment indent="3"/></xf>';
        $x .= '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>';
        $x .= '<xf numFmtId="164" fontId="4" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>';
        $x .= '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left"/></xf>';
        $x .= '</cellXfs>';
        $x .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';
        $x .= '</styleSheet>';

        return $x;
    }

    private static function sheet(array $doc, string $drawing): string
    {
        $columnCount = max(1, count($doc['columns']));

        // A merged title cannot overflow, so the span is widened past the data
        // rather than letting Excel clip the company name on a narrow report.
        $titleSpan = self::column(max($columnCount, 7) - 1);

        $widths = self::widths($doc);
        $cols = '<cols>';
        foreach ($widths as $i => $width) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $r = 0;
        $body = '';
        $merges = [];

        if ($drawing !== '') {
            $r++;
            $body .= '<row r="1" ht="58" customHeight="1"/>';
            $merges[] = 'A1:' . $titleSpan . '1';
        }

        foreach ([[$doc['company'], 1], [$doc['company_details'] ?? '', 3], [$doc['title'], 2], [$doc['period'], 3]] as [$text, $style]) {
            if ($text === '' || $text === null) {
                continue;
            }
            $r++;
            $height = $r === 1 ? ' ht="42" customHeight="1"' : '';
            if ($drawing !== '' && $r === 2) {
                $height = ' ht="24" customHeight="1"';
            }
            $body .= '<row r="' . $r . '"' . $height . '><c r="A' . $r . '" s="' . $style . '" t="inlineStr"><is><t>';
            $body .= self::escape($text) . '</t></is></c></row>';
            $merges[] = 'A' . $r . ':' . $titleSpan . $r;
        }

        $r++;   // the blank row before the headings

        $r++;
        $headerRow = $r;
        $body .= '<row r="' . $r . '" ht="16" customHeight="1">';
        foreach ($doc['columns'] as $i => $column) {
            $body .= '<c r="' . self::column($i) . $r . '" s="4" t="inlineStr"><is><t>';
            $body .= self::escape($column['label']) . '</t></is></c>';
        }
        $body .= '</row>';

        foreach ($doc['rows'] as $row) {
            $r++;
            if ($row['type'] === 'blank') {
                $body .= '<row r="' . $r . '" ht="16" customHeight="1"/>';
                continue;
            }

            $bold = in_array($row['type'], ['section', 'total', 'grand'], true);
            $body .= '<row r="' . $r . '" ht="16" customHeight="1">';

            foreach ($doc['columns'] as $i => $column) {
                $value = $row['cells'][$i] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $ref = self::column($i) . $r;

                if (is_int($value) || is_float($value)) {
                    $style = match (true) {
                        $row['type'] === 'grand' => 10,
                        $row['type'] === 'total' => 8,
                        $row['type'] === 'section' => 15,
                        $bold => 7,
                        default => 5,
                    };
                    $body .= '<c r="' . $ref . '" s="' . $style . '"><v>' . round((float) $value, 2) . '</v></c>';
                    continue;
                }

                $style = match (true) {
                    $i === 0 && !$bold && $row['indent'] > 0 => 10 + min(3, $row['indent']),
                    $row['type'] === 'grand' => 9,
                    $row['type'] === 'section' => 14,
                    $bold => 6,
                    default => 0,
                };
                $body .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t>';
                $body .= self::escape($value) . '</t></is></c>';
            }
            $body .= '</row>';
        }

        $r += 2;
        $body .= '<row r="' . $r . '"><c r="A' . $r . '" s="16" t="inlineStr"><is><t>';
        $body .= self::escape(Report::stamp($doc)) . '</t></is></c></row>';

        $mergeXml = '';
        if ($merges !== []) {
            $mergeXml = '<mergeCells count="' . count($merges) . '"><mergeCell ref="'
                . implode('"/><mergeCell ref="', $merges) . '"/></mergeCells>';
        }

        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $x .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $x .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $x .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        $x .= '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '"';
        $x .= ' activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $x .= '<sheetFormatPr defaultRowHeight="14.5"/>';
        $x .= $cols;
        $x .= '<sheetData>' . $body . '</sheetData>';
        $x .= $mergeXml;
        $x .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
        $x .= '<pageSetup orientation="' . ($columnCount > 5 ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>';
        $x .= $drawing;   // must follow pageSetup, per the schema's element order
        $x .= '</worksheet>';

        return $x;
    }

    /**
     * The logo, centred over the title block. Excel needs the image, a drawing
     * that positions it, and a relationship from the sheet to that drawing, so
     * all three are written or none is.
     */
    private static function addLogo(ZipArchive $zip, array $doc): string
    {
        $brand = Report::brand();
        if ($brand['logo'] === null || !is_file($brand['logo'])) {
            return '';
        }

        $bytes = @file_get_contents($brand['logo']);
        if ($bytes === false || $bytes === '' || strlen($bytes) > 2097152) {
            return '';
        }

        $extension = strtolower(pathinfo($brand['logo'], PATHINFO_EXTENSION));
        $extension = in_array($extension, ['jpg', 'jpeg'], true) ? 'jpeg' : 'png';
        $zip->addFromString('xl/media/logo.' . $extension, $bytes);

        // About 52 points tall, keeping the aspect ratio. An EMU is 1/9525 of a
        // pixel, and a character of Excel's default font is about 7 pixels wide
        // plus 5 of padding per column, which is all the arithmetic below is.
        $heightEmu = 660000;
        $widthEmu = (int) round($heightEmu * max(1, $brand['logow']) / max(1, $brand['logoh']));

        $pixelWidths = [];
        $sheetWidth = 0;
        foreach (self::widths($doc) as $width) {
            $pixels = (int) round($width * 7 + 5);
            $pixelWidths[] = $pixels;
            $sheetWidth += $pixels;
        }

        $logoPixels = max(1, (int) round($widthEmu / 9525));
        $leftPixels = max(0, (int) (($sheetWidth - $logoPixels) / 2));

        $column = 0;
        $offset = 0;
        $accumulated = 0;
        foreach ($pixelWidths as $i => $pixels) {
            if ($accumulated + $pixels > $leftPixels) {
                $column = $i;
                $offset = ($leftPixels - $accumulated) * 9525;
                break;
            }
            $accumulated += $pixels;
            $column = $i + 1;
        }

        $d = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $d .= '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"';
        $d .= ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">';
        $d .= '<xdr:oneCellAnchor>';
        $d .= '<xdr:from><xdr:col>' . $column . '</xdr:col><xdr:colOff>' . $offset . '</xdr:colOff>';
        $d .= '<xdr:row>0</xdr:row><xdr:rowOff>19050</xdr:rowOff></xdr:from>';
        $d .= '<xdr:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/>';
        $d .= '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Logo"/><xdr:cNvPicPr/></xdr:nvPicPr>';
        $d .= '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/>';
        $d .= '<a:stretch><a:fillRect/></a:stretch></xdr:blipFill>';
        $d .= '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>';
        $d .= '<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
        $zip->addFromString('xl/drawings/drawing1.xml', $d);

        $zip->addFromString(
            'xl/drawings/_rels/drawing1.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
            . ' Target="../media/logo.' . $extension . '"/></Relationships>'
        );

        $zip->addFromString(
            'xl/worksheets/_rels/sheet1.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdDr1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing"'
            . ' Target="../drawings/drawing1.xml"/></Relationships>'
        );

        return '<drawing r:id="rIdDr1"/>';
    }
}
