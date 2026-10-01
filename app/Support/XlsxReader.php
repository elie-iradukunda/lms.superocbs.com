<?php

declare(strict_types=1);

namespace Support;

use ZipArchive;

/**
 * Reads the first worksheet of an .xlsx file, or a .csv, into rows of strings.
 *
 * Like XlsxWriter it needs only ZipArchive: no PhpSpreadsheet, no composer, so
 * it runs on any shared host the system already runs on.
 */
final class XlsxReader
{
    /**
     * @return list<list<string>>
     */
    public static function rows(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return $extension === 'csv' ? self::csv($path) : self::xlsx($path);
    }

    /** @return list<list<string>> */
    private static function csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('The file could not be opened.');
        }

        $rows = [];
        $first = true;
        while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($first && isset($cells[0])) {
                // Excel's "CSV UTF-8" starts with a byte-order mark.
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $first = false;
            }
            $rows[] = array_map(static fn ($cell): string => trim((string) $cell), $cells);
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<list<string>> */
    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('That is not an Excel (.xlsx) file. Save it as .xlsx or .csv and try again.');
        }

        try {
            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                $doc = self::xml($sharedXml);
                foreach ($doc->si as $item) {
                    // A cell with mixed formatting is split into runs; join them back.
                    if (isset($item->t)) {
                        $shared[] = (string) $item->t;
                    } else {
                        $text = '';
                        foreach ($item->r as $run) {
                            $text .= (string) $run->t;
                        }
                        $shared[] = $text;
                    }
                }
            }

            $sheetPath = self::firstSheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw new \RuntimeException('The workbook has no worksheet to read.');
            }

            $sheet = self::xml($sheetXml);
            $rows = [];
            foreach ($sheet->sheetData->row ?? [] as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    $column = self::columnIndex(preg_replace('/\d+/', '', $reference) ?: 'A');
                    $type = (string) $cell['t'];
                    $value = match ($type) {
                        's' => $shared[(int) $cell->v] ?? '',
                        'inlineStr' => (string) ($cell->is->t ?? ''),
                        'b' => ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE',
                        default => (string) $cell->v,
                    };
                    $cells[$column] = trim($value);
                }
                if ($cells === []) {
                    $rows[] = [];
                    continue;
                }
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $index => $value) {
                    $line[$index] = $value;
                }
                $rows[] = $line;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook !== false && $rels !== false) {
            $book = self::xml($workbook);
            $relations = self::xml($rels);
            $first = $book->sheets->sheet[0] ?? null;
            if ($first !== null) {
                $id = (string) ($first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
                foreach ($relations->Relationship as $relation) {
                    if ((string) $relation['Id'] === $id) {
                        $target = ltrim((string) $relation['Target'], '/');
                        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function xml(string $content): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($doc === false) {
            throw new \RuntimeException('The Excel file is damaged and could not be read.');
        }

        return $doc;
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
