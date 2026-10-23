<?php

declare(strict_types=1);

namespace Models;

use Support\FormPdf;
use Support\Mailer;
use Support\Words;

/**
 * A request printed the way the paper used to look (section 8): the company
 * header, the form's title, number and date, the lines, the total in figures
 * and in words, and the four blocks Prepared by / Verified by / Authorized by
 * (with the company stamp) / Paid by, each with name, date and signature.
 */
final class RequestPdf
{
    private const MARGIN = 36.0;
    private const INK = [0.12, 0.15, 0.19];
    private const MUTED = [0.42, 0.46, 0.52];
    private const RULE = [0.78, 0.81, 0.85];
    private const HEAD_FILL = [0.93, 0.95, 0.98];

    public static function render(int $requestId): ?string
    {
        $request = RequestForms::find($requestId, false);
        if ($request === null) {
            return null;
        }
        $lines = RequestForms::lines($requestId);
        $events = RequestForms::events($requestId);
        $company = Company::header((int) $request['company_id']);

        $pdf = new FormPdf(RequestForms::label($request));
        $top = self::header($pdf, $request, $company);
        $top = self::facts($pdf, $request, $top);

        $top = $request['form_code'] === 'F1'
            ? self::tripMoneyTable($pdf, $request, $lines, $top)
            : self::linesTable($pdf, $request, $lines, $top);

        $top = self::totals($pdf, $request, $lines, $top);

        if (trim((string) ($request['non_standard_reason'] ?? '')) !== '') {
            $pdf->text(self::MARGIN, $top, 'Why it differs from the rate card:', 8, true, self::INK);
            $top += 11 + $pdf->paragraph(self::MARGIN, $top + 11, FormPdf::WIDTH - 2 * self::MARGIN, (string) $request['non_standard_reason'], 8) + 6;
        }
        if (trim((string) ($request['notes'] ?? '')) !== '') {
            $pdf->text(self::MARGIN, $top, 'Notes:', 8, true, self::INK);
            $top += 11 + $pdf->paragraph(self::MARGIN, $top + 11, FormPdf::WIDTH - 2 * self::MARGIN, (string) $request['notes'], 8) + 6;
        }

        if ($top > FormPdf::HEIGHT - 190) {
            $pdf->addPage();
            $top = self::MARGIN;
        }
        self::signatures($pdf, $request, $events, max($top + 10, FormPdf::HEIGHT - 175));

        return $pdf->output();
    }

    /** Saves the PDF under the uploads folder for an email, and says where. @return array{filename: string, path: string}|null */
    public static function store(int $requestId): ?array
    {
        $bytes = self::render($requestId);
        $request = RequestForms::find($requestId, false);
        if ($bytes === null || $request === null) {
            return null;
        }
        $directory = Mailer::uploadsRoot() . '/requests';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        $name = preg_replace('/[^A-Za-z0-9\-]+/', '-', RequestForms::label($request)) . '-v' . (int) $request['version'] . '.pdf';
        file_put_contents($directory . '/' . $name, $bytes);

        return ['filename' => $name, 'path' => 'requests/' . $name];
    }

    // ----------------------------------------------------------------- parts

    private static function header(FormPdf $pdf, array $request, array $company): float
    {
        $x = self::MARGIN;
        $logo = self::upload($company['logo']);
        if ($logo !== null) {
            $x += $pdf->logo($logo, self::MARGIN, self::MARGIN, 70, 48) + 10;
        }
        $pdf->text($x, self::MARGIN + 2, strtoupper($company['name']), 13, true, self::INK);
        $line = self::MARGIN + 18;
        foreach (array_filter([$company['address'], trim($company['phone'] . ($company['email'] !== '' ? '   ' . $company['email'] : '')), $company['tin'] !== '' ? 'TIN ' . $company['tin'] : '']) as $detail) {
            $pdf->text($x, $line, $detail, 8, false, self::MUTED);
            $line += 11;
        }

        $top = max($line, self::MARGIN + 52) + 8;
        $pdf->line(self::MARGIN, $top, FormPdf::WIDTH - self::MARGIN, $top, 1.2, [0.12, 0.31, 0.55]);
        $top += 12;
        $title = (string) $request['title_on_pdf'];
        $number = (string) ($request['request_no'] ?: 'DRAFT #' . $request['id']);
        $pdf->text(self::MARGIN, $top, $title, 12, true, self::INK);
        $pdf->text(self::MARGIN, $top + 16, $number, 9, true, [0.12, 0.31, 0.55]);
        $date = (string) ($request['prepared_at'] ?: $request['created_at']);
        $pdf->text(FormPdf::WIDTH - self::MARGIN - 200, $top, 'Date: ' . date('d/m/Y', (int) strtotime($date)), 9, false, self::INK, 'right', 200);
        if ((int) $request['version'] > 1) {
            $pdf->text(FormPdf::WIDTH - self::MARGIN - 200, $top + 13, 'Version ' . (int) $request['version'], 8, false, self::MUTED, 'right', 200);
        }

        return $top + 34;
    }

    private static function facts(FormPdf $pdf, array $request, float $top): float
    {
        $facts = array_filter([
            'Route' => trim(($request['route_code'] ?? '') . ' ' . ($request['route_name'] ?? '')),
            'Trip' => (string) ($request['trip_code'] ?? ''),
            'Truck' => (string) ($request['plate_number'] ?? ''),
            'Driver' => (string) ($request['driver_name'] ?? ''),
            'Supplier' => (string) ($request['supplier_name'] ?? ''),
            'Customer' => (string) ($request['customer_name'] ?? ''),
            'To' => (string) ($request['to_company'] ?? ''),
            'Period' => $request['period_from'] ? date('d/m/Y', (int) strtotime((string) $request['period_from'])) . ' – ' . date('d/m/Y', (int) strtotime((string) ($request['period_to'] ?: $request['period_from']))) : '',
            'Needed on' => $request['needed_on'] ? date('d/m/Y', (int) strtotime((string) $request['needed_on'])) : '',
            'Requested by' => (string) ($request['prepared_name'] ?? ''),
            'Subject' => (string) $request['title'],
        ], static fn (string $value): bool => trim($value) !== '');

        $column = 0;
        $width = (FormPdf::WIDTH - 2 * self::MARGIN) / 2;
        foreach ($facts as $label => $value) {
            $x = self::MARGIN + $column * $width;
            $pdf->text($x, $top, $label . ':', 8, true, self::MUTED);
            $pdf->text($x + 58, $top, mb_strimwidth($value, 0, 60, '…'), 8, false, self::INK);
            $column++;
            if ($column === 2) {
                $column = 0;
                $top += 12;
            }
        }

        return $top + ($column === 0 ? 6 : 18);
    }

    /**
     * F1 as the 8-truck sheet: one row per truck, with fuel, road toll,
     * mileage, the total and where the money goes.
     */
    private static function tripMoneyTable(FormPdf $pdf, array $request, array $lines, float $top): float
    {
        $groups = [];
        foreach ($lines as $line) {
            $key = (int) ($line['group_no'] ?? 0) ?: (int) $line['line_no'];
            $groups[$key] ??= ['line' => $line, 'fuel' => 0.0, 'road_toll' => 0.0, 'mileage' => 0.0, 'other' => 0.0];
            match ((string) $line['item_code']) {
                'fuel' => $groups[$key]['fuel'] += (float) $line['quantity'],
                'road_toll' => $groups[$key]['road_toll'] += (float) $line['amount'],
                'mileage' => $groups[$key]['mileage'] += (float) $line['amount'],
                default => $groups[$key]['other'] += (float) $line['amount'],
            };
            if ($line['payee_name'] !== null && $line['payee_name'] !== '') {
                $groups[$key]['line'] = $line + ['payee_name' => $line['payee_name']];
            }
        }

        $columns = [
            ['#', 16, 'left'], ['Truck', 52, 'left'], ['Trailer', 48, 'left'], ['Driver', 74, 'left'], ['Licence', 56, 'left'],
            ['ID / Passport', 60, 'left'], ['Fuel (L)', 34, 'right'], ['Road toll', 46, 'right'], ['Mileage', 44, 'right'], ['Total', 50, 'right'], ['Paid to', 43, 'left'],
        ];
        $top = self::tableHead($pdf, $columns, $top);
        $n = 0;
        foreach ($groups as $group) {
            $line = $group['line'];
            $total = $group['road_toll'] + $group['mileage'] + $group['other'];
            $paidTo = $line['payment_method'] === 'cash' ? 'Cash' : (string) ($line['momo_number'] ?? '');
            $top = self::row($pdf, $columns, [
                (string) ++$n, (string) ($line['plate_number'] ?? ''), (string) ($line['trailer_number'] ?? ''), (string) ($line['driver_name'] ?? $line['payee_name'] ?? ''),
                (string) ($line['license_number'] ?? ''), (string) ($line['passport_number'] ?: $line['national_id'] ?? ''),
                self::qty($group['fuel']), self::money($group['road_toll']), self::money($group['mileage']), self::money($total), $paidTo,
            ], $top);
        }

        return $top + 6;
    }

    private static function linesTable(FormPdf $pdf, array $request, array $lines, float $top): float
    {
        $money = (int) $request['is_money'] === 1;
        $columns = $money
            ? [['#', 16, 'left'], ['Truck', 56, 'left'], ['Description', 170, 'left'], ['Qty', 40, 'right'], ['Unit', 34, 'left'], ['Unit price', 60, 'right'], ['Amount', 66, 'right'], ['Paid to', 81, 'left']]
            : [['#', 16, 'left'], ['Truck', 70, 'left'], ['Description', 260, 'left'], ['Qty', 60, 'right'], ['Unit', 60, 'left'], ['Remarks', 57, 'left']];
        $top = self::tableHead($pdf, $columns, $top);
        foreach ($lines as $index => $line) {
            $extra = is_array($line['extra']) ? implode(', ', array_map(static fn ($k, $v): string => $k . ': ' . $v, array_keys($line['extra']), $line['extra'])) : '';
            $cells = $money
                ? [(string) ($index + 1), (string) ($line['plate_number'] ?? ''), (string) $line['description'] . ($extra !== '' ? ' (' . $extra . ')' : ''), self::qty((float) $line['quantity']), (string) ($line['unit'] ?? ''), self::money((float) $line['unit_price']), self::money((float) $line['amount']), (string) ($line['payee_name'] ?? '')]
                : [(string) ($index + 1), (string) ($line['plate_number'] ?? ''), (string) $line['description'], self::qty((float) $line['quantity']), (string) ($line['unit'] ?? ''), $extra];
            $top = self::row($pdf, $columns, $cells, $top);
        }

        return $top + 6;
    }

    private static function totals(FormPdf $pdf, array $request, array $lines, float $top): float
    {
        // paragraph() returns the height it drew, not the new cursor, so each
        // total advances $top by that height rather than overwriting it — the
        // bug that printed the fuel total back up at the top of the page.
        $right = FormPdf::WIDTH - self::MARGIN;
        $wrap = FormPdf::WIDTH - 2 * self::MARGIN - 46;
        $top += 2;
        if ((int) $request['is_money'] === 1 && (float) $request['total_amount'] > 0) {
            $pdf->rect($right - 250, $top, 250, 18, self::HEAD_FILL, self::RULE);
            $pdf->text($right - 244, $top + 5, 'TOTAL', 10, true, self::INK);
            $pdf->text($right - 170, $top + 5, Currency::format((float) $request['total_amount'], (string) $request['currency']), 11, true, self::INK, 'right', 164);
            $top += 24;
            $pdf->text(self::MARGIN, $top, 'In words:', 8, true, self::MUTED);
            $top += $pdf->paragraph(self::MARGIN + 46, $top, $wrap, Words::amount((float) $request['total_amount'], (string) $request['currency']) . ' only.', 8.5, true) + 10;
        }
        $litres = (float) ($request['total_quantity'] ?? 0);
        if ($litres > 0) {
            $pdf->text($right - 244, $top, 'TOTAL FUEL', 10, true, self::INK);
            $pdf->text($right - 170, $top, self::qty($litres) . ' L', 10, true, self::INK, 'right', 164);
            $top += 15;
            $pdf->text(self::MARGIN, $top, 'In words:', 8, true, self::MUTED);
            $top += $pdf->paragraph(self::MARGIN + 46, $top, $wrap, ucfirst(Words::number((int) round($litres))) . ' litres.', 8.5, true) + 8;
        }

        return $top + 6;
    }

    /** Prepared by / Verified by / Authorized by (stamp) / Paid by, each with name, date and signature. */
    private static function signatures(FormPdf $pdf, array $request, array $events, float $top): void
    {
        $version = (int) $request['version'];
        $latest = [];
        foreach ($events as $event) {
            if ((int) $event['version'] !== $version) {
                continue;
            }
            $step = $event['step'] === 'resent' ? 'prepared' : ($event['step'] === 'confirmed' ? 'authorized' : $event['step']);
            if (in_array($step, ['prepared', 'verified', 'authorized', 'paid'], true)) {
                $latest[$step] = $event;
            }
        }

        $blocks = [
            'prepared' => 'Prepared by',
            'verified' => 'Verified by',
            'authorized' => $request['chain'] === 'om_confirm' ? 'Confirmed by' : 'Authorized by',
            'paid' => 'Paid by',
        ];
        if ((int) $request['goes_to_payment'] !== 1) {
            unset($blocks['paid']);
        }
        if (!in_array($request['chain'], ['full'], true)) {
            unset($blocks['verified']);
        }

        $width = (FormPdf::WIDTH - 2 * self::MARGIN) / count($blocks);
        $x = self::MARGIN;
        foreach ($blocks as $step => $label) {
            $event = $latest[$step] ?? null;
            $pdf->rect($x + 2, $top, $width - 4, 130, null, self::RULE, 0.6);
            $pdf->text($x + 8, $top + 8, $label, 8, true, self::INK);
            if ($event !== null) {
                $name = (string) $event['full_name'];
                if (!empty($event['on_behalf_name'])) {
                    $name .= ' for ' . $event['on_behalf_name'];
                }
                $pdf->text($x + 8, $top + 22, mb_strimwidth($name, 0, 34, '…'), 8, false, self::INK);
                $pdf->text($x + 8, $top + 34, date('d/m/Y H:i', (int) strtotime((string) $event['created_at'])), 7.5, false, self::MUTED);
                $signature = self::upload($event['signature_file'] ? 'signatures/' . basename((string) $event['signature_file']) : null);
                if ($signature !== null) {
                    $pdf->image($signature, $x + 8, $top + 46, $width - 20, 36, true);
                }
                $stamp = self::upload($event['stamp_file'] ?? null);
                if ($stamp !== null) {
                    $pdf->image($stamp, $x + 8, $top + 82, $width - 20, 44, true);
                }
            } else {
                $pdf->text($x + 8, $top + 22, 'Name:', 7.5, false, self::MUTED);
                $pdf->text($x + 8, $top + 40, 'Date:', 7.5, false, self::MUTED);
                $pdf->text($x + 8, $top + 58, 'Signature:', 7.5, false, self::MUTED);
            }
            $x += $width;
        }

        $pdf->line(self::MARGIN, $top + 136, FormPdf::WIDTH - self::MARGIN, $top + 136, 0.6, self::RULE);
        $pdf->text(self::MARGIN, $top + 143, sprintf('%s — generated by LMS on %s. Every step is in the request history.', RequestForms::label($request), date('d/m/Y H:i')), 7, false, self::MUTED);
    }

    // --------------------------------------------------------------- helpers

    private static function tableHead(FormPdf $pdf, array $columns, float $top): float
    {
        $x = self::MARGIN;
        $width = array_sum(array_column($columns, 1));
        $pdf->rect(self::MARGIN, $top, $width, 16, self::HEAD_FILL, self::RULE);
        foreach ($columns as [$label, $w, $align]) {
            $pdf->text($x + 3, $top + 4, $label, 7, true, self::INK, $align, $w - 6);
            $x += $w;
        }

        return $top + 16;
    }

    private static function row(FormPdf $pdf, array $columns, array $cells, float $top): float
    {
        if ($top > FormPdf::HEIGHT - 230) {
            $pdf->addPage();
            $top = self::tableHead($pdf, $columns, self::MARGIN);
        }
        $x = self::MARGIN;
        $height = 14.0;
        $wrapped = [];
        foreach ($columns as $index => [, $w]) {
            $wrapped[$index] = FormPdf::wrap((string) ($cells[$index] ?? ''), $w - 6, 7);
            $height = max($height, 4 + 9 * count($wrapped[$index]));
        }
        foreach ($columns as $index => [, $w, $align]) {
            $lineTop = $top + 3;
            foreach ($wrapped[$index] as $text) {
                $pdf->text($x + 3, $lineTop, $text, 7, false, self::INK, $align, $w - 6);
                $lineTop += 9;
            }
            $x += $w;
        }
        $pdf->line(self::MARGIN, $top + $height, self::MARGIN + array_sum(array_column($columns, 1)), $top + $height, 0.4, self::RULE);

        return $top + $height;
    }

    private static function upload(?string $relative): ?string
    {
        $relative = trim((string) $relative);
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        $path = Mailer::uploadsRoot() . '/' . ltrim($relative, '/');

        return is_file($path) ? $path : null;
    }

    private static function money(float $amount): string
    {
        return $amount == 0.0 ? '' : number_format($amount, abs($amount - round($amount)) > 0.001 ? 2 : 0);
    }

    private static function qty(float $quantity): string
    {
        return $quantity == 0.0 ? '' : rtrim(rtrim(number_format($quantity, 2), '0'), '.');
    }
}
