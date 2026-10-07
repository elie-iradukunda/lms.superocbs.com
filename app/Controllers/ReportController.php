<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\LogisticsData;
use Models\Permission;
use Models\ReportData;
use Models\Schema;
use Support\Report;

/**
 * Runs the eight operational reports against live data. The `reports` table is
 * now only a catalogue of saved report definitions; the numbers come from here.
 */
final class ReportController
{
    public function index(): void
    {
        $role = \current_role();
        $available = ReportData::availableFor($role);

        \view('reports/index', [
            'title' => 'Reports',
            'available' => $available,
            'catalogue' => LogisticsData::listing('reports', ['per_page' => 50], \current_context())['rows'],
            'canEditCatalogue' => Permission::allows($role, 'reports', 'edit'),
        ]);
    }

    public function show(array $params): void
    {
        $key = (string) ($params['key'] ?? '');
        $this->guard($key);

        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;

        try {
            $report = ReportData::run($key, $from, $to, $_GET['currency'] ?? null);
        } catch (\Throwable $exception) {
            Flash::error('That report could not be run: ' . $exception->getMessage());
            header('Location: ' . \url('reports'));
            exit;
        }

        \view('reports/show', [
            'title' => $report['label'],
            'report' => $report,
            'available' => ReportData::availableFor(\current_role()),
        ]);
    }

    public function export(array $params): void
    {
        $key = (string) ($params['key'] ?? '');
        $this->guard($key);

        $report = ReportData::run($key, $_GET['from'] ?? null, $_GET['to'] ?? null, $_GET['currency'] ?? null);

        $format = strtolower((string) ($_GET['format'] ?? 'csv'));
        AuditLog::record('report.exported', 'reports', $key, null, ['from' => $report['from'], 'to' => $report['to'], 'format' => $format]);

        // PDF and Excel are drawn by the same writers as the accounting books,
        // so every report carries the company header, logo and seal styling.
        if (in_array($format, ['pdf', 'xlsx'], true)) {
            Report::download(self::document($report), $format);
        }

        $filename = sprintf('%s-%s-to-%s.csv', str_replace('_', '-', $key), $report['from'], $report['to']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'wb');
        fputcsv($output, [$report['label']]);
        fputcsv($output, ['Period', $report['from'] . ' to ' . $report['to']]);
        fputcsv($output, []);
        fputcsv($output, array_column($report['columns'], 'label'));

        foreach ($report['rows'] as $row) {
            $line = [];
            foreach ($report['columns'] as $column => $spec) {
                $value = $row[$column] ?? '';
                $line[] = match ($spec['type']) {
                    'badge', 'label' => Schema::label((string) $value),
                    'money' => $value === '' || $value === null ? '' : number_format((float) $value, 2, '.', ''),
                    'percent' => $value === null || $value === '' ? '' : $value . '%',
                    default => (string) $value,
                };
            }
            fputcsv($output, $line);
        }

        // One total line per currency, each amount under its own column.
        foreach ($report['money_totals'] ?? [] as $code => $sums) {
            $line = [];
            foreach ($report['columns'] as $column => $spec) {
                $line[] = match (true) {
                    $column === array_key_first($report['columns']) => 'TOTAL ' . $code,
                    $column === 'currency' => $code,
                    array_key_exists($column, $sums) => number_format((float) $sums[$column], 2, '.', ''),
                    default => '',
                };
            }
            fputcsv($output, $line);
        }

        if ($report['totals'] !== []) {
            fputcsv($output, []);
            foreach ($report['totals'] as $label => $value) {
                fputcsv($output, ['Total ' . str_replace('_', ' ', (string) $label), is_float($value) ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') : $value]);
            }
        }

        fclose($output);
        exit;
    }

    /** A report as an export document: one row per line, a TOTAL line, then any other totals. */
    public static function document(array $report): array
    {
        $day = static fn (string $date): string => date('d M Y', (int) strtotime($date));
        $period = $report['key'] === 'ar_aging' || $report['key'] === 'ap_aging'
            ? 'As at ' . $day($report['to'])
            : $day($report['from']) . ' to ' . $day($report['to']);
        if (($report['currency'] ?? '') !== '') {
            $period .= ' · ' . $report['currency'] . ' only';
        }

        $widths = ['money' => 16, 'number' => 10, 'decimal' => 12, 'percent' => 10, 'code' => 16, 'badge' => 13, 'label' => 13, 'datetime' => 17];
        $columns = [];
        foreach ($report['columns'] as $key => $spec) {
            $type = (string) $spec['type'];
            $numeric = in_array($type, ['money', 'number', 'decimal', 'percent'], true);
            // Quantities kept as decimals but always whole (16 drums, 3 tarpaulins) print as counts.
            $whole = $type === 'decimal' && array_reduce($report['rows'], static fn (bool $all, array $row): bool => $all && fmod((float) ($row[$key] ?? 0), 1.0) === 0.0, true);
            $columns[] = ['label' => (string) $spec['label'], 'align' => $numeric ? 'right' : 'left', 'width' => $key === 'currency' ? 7 : ($widths[$type] ?? 24), 'count' => $type === 'number' || $whole];
        }

        $doc = Report::make($report['label'], $period, $columns, 'Operational report');

        $cell = static function (string $type, mixed $value): mixed {
            if ($value === null || $value === '') {
                return null;
            }

            return match ($type) {
                'money', 'decimal' => (float) $value,
                'number' => (int) round((float) $value),
                'percent' => rtrim(rtrim(number_format((float) $value, 1), '0'), '.') . '%',
                'badge', 'label' => Schema::label((string) $value),
                'datetime' => date('d M Y H:i', (int) strtotime((string) $value)),
                default => (string) $value,
            };
        };

        foreach ($report['rows'] as $row) {
            $cells = [];
            foreach ($report['columns'] as $column => $spec) {
                $cells[] = $cell((string) $spec['type'], $row[$column] ?? null);
            }
            Report::row($doc, $cells);
        }

        if ($report['rows'] === []) {
            Report::row($doc, ['Nothing in this period.']);
        }

        $totals = $report['totals'] ?? [];
        $keys = array_keys($report['columns']);

        // Money: a TOTAL line for each currency, never one line across them.
        foreach ($report['money_totals'] ?? [] as $code => $sums) {
            $line = array_fill(0, count($keys), null);
            foreach ($keys as $index => $column) {
                if ($index === 0) {
                    $line[$index] = 'TOTAL ' . $code;
                } elseif ($column === 'currency') {
                    $line[$index] = $code;
                } elseif (array_key_exists($column, $sums)) {
                    $line[$index] = (float) $sums[$column];
                }
            }
            Report::grand($doc, $line);
        }

        // Counts sit under their own column when the report has no money; with
        // money they go to the summary, below the currency totals.
        $grand = array_fill(0, count($keys), null);
        $placed = false;
        foreach (isset($report['columns']['currency']) ? [] : $totals as $name => $value) {
            $index = array_search($name, $keys, true);
            if ($index !== false && $index > 0) {
                $grand[$index] = $cell((string) $report['columns'][$name]['type'], $value);
                unset($totals[$name]);
                $placed = true;
            }
        }
        if ($placed) {
            $grand[0] = 'TOTAL';
            Report::grand($doc, $grand);
        }

        if ($totals !== []) {
            Report::blank($doc);
            Report::section($doc, 'Summary');
            foreach ($totals as $name => $value) {
                $line = array_fill(0, count($keys), null);
                $line[0] = ucfirst(str_replace('_', ' ', (string) $name));
                $line[count($keys) - 1] = is_float($value) ? (float) $value : number_format((float) $value);
                Report::row($doc, $line, 1);
            }
        }

        return $doc;
    }

    private function guard(string $key): void
    {
        if (!ReportData::exists($key)) {
            Flash::error('That report does not exist.');
            header('Location: ' . \url('reports'));
            exit;
        }

        $role = \current_role();
        if (!Permission::allows($role, ReportData::permission($key), 'view') && !Permission::allows($role, 'reports', 'view')) {
            Flash::error('That report is not available for the ' . \role_label() . ' role.');
            header('Location: ' . \url('reports'));
            exit;
        }
    }
}
