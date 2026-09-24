<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\LogisticsData;
use Models\Permission;
use Models\ReportData;
use Models\Schema;

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
            $report = ReportData::run($key, $from, $to);
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

        $report = ReportData::run($key, $_GET['from'] ?? null, $_GET['to'] ?? null);

        AuditLog::record('report.exported', 'reports', $key, null, ['from' => $report['from'], 'to' => $report['to']]);

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

        if ($report['totals'] !== []) {
            fputcsv($output, []);
            foreach ($report['totals'] as $label => $value) {
                fputcsv($output, ['Total ' . str_replace('_', ' ', (string) $label), is_float($value) ? number_format($value, 2, '.', '') : $value]);
            }
        }

        fclose($output);
        exit;
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
