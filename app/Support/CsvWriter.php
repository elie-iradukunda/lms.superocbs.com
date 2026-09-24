<?php

declare(strict_types=1);

namespace Support;

/**
 * The same report document as a CSV, for anyone who wants the figures in a
 * different tool rather than a formatted statement.
 *
 * The title block is kept as the first few lines so an exported file still says
 * which company, which report and which period it is, which a bare grid of
 * numbers does not.
 */
final class CsvWriter
{
    public static function send(array $doc): never
    {
        $name = Report::filename($doc, 'csv');

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Cache-Control: max-age=0');
        }

        $out = fopen('php://output', 'wb');

        // Excel opens a UTF-8 CSV as Windows-1252 unless it sees a BOM.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [$doc['company']]);
        if (($doc['company_details'] ?? '') !== '') {
            fputcsv($out, [$doc['company_details']]);
        }
        fputcsv($out, [$doc['title']]);
        if (($doc['period'] ?? '') !== '') {
            fputcsv($out, [$doc['period']]);
        }
        fputcsv($out, []);
        fputcsv($out, array_column($doc['columns'], 'label'));

        foreach ($doc['rows'] as $row) {
            if ($row['type'] === 'blank') {
                fputcsv($out, []);
                continue;
            }

            $line = [];
            foreach ($doc['columns'] as $i => $column) {
                $value = $row['cells'][$i] ?? null;

                if ($value === null || $value === '') {
                    $line[] = '';
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    // Plain, unformatted, so a spreadsheet reads it as a number.
                    $line[] = number_format((float) $value, 2, '.', '');
                    continue;
                }

                // The indent is what tells a reader this line sits under the one
                // above, so it is kept rather than flattened away.
                $line[] = ($i === 0 ? str_repeat('    ', $row['indent']) : '') . (string) $value;
            }
            fputcsv($out, $line);
        }

        fputcsv($out, []);
        fputcsv($out, [Report::stamp($doc)]);

        fclose($out);
        exit;
    }
}
