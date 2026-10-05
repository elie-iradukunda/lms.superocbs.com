<?php

declare(strict_types=1);

namespace Models;

use Support\FormPdf;
use Support\Report;

/**
 * The trip report as a PDF: A4, the company's logo and colour, summary tiles
 * at the top, then each section in order, flowing over as many pages as the
 * trip needs, with the table headings repeated on a new page and the trip
 * number and page count at the foot of every page.
 */
final class TripReportPdf
{
    private const L = 36.0;
    private const R = 559.0;
    private const BOTTOM = 788.0;
    private const INK = [0.12, 0.15, 0.19];
    private const GREY = [0.42, 0.47, 0.53];
    private const RULE = [0.82, 0.85, 0.88];
    private const RED = [0.72, 0.16, 0.16];
    private const AMBER = [0.66, 0.42, 0.02];
    private const GREEN = [0.18, 0.49, 0.30];

    private FormPdf $pdf;
    private float $y = 0.0;
    private array $accent;
    private array $tint;
    private string $code;

    private function __construct(private array $data)
    {
        $brand = Report::brand();
        $this->accent = $brand['pdf'];
        $this->tint = [hexdec(substr($brand['tint'], 0, 2)) / 255, hexdec(substr($brand['tint'], 2, 2)) / 255, hexdec(substr($brand['tint'], 4, 2)) / 255];
        $this->code = (string) $data['trip']['reference_code'];
        $this->pdf = new FormPdf('Trip report ' . $this->code);
    }

    public static function build(array $data): string
    {
        $report = new self($data);
        $report->write();

        return $report->pdf->output();
    }

    private function write(): void
    {
        $d = $this->data;
        $t = $d['trip'];
        $width = self::R - self::L;

        $this->header();
        $this->tiles();
        $this->progress();

        // ------------------------------------------------------------ the trip
        $this->section('The trip');
        $this->pairs([
            'Trip' => $t['reference_code'],
            'Status' => Schema::label((string) $t['status']),
            'Type' => Schema::label((string) $t['trip_type']),
            'Customer' => $t['customer_name'] ?: (count($d['shipments']) > 1 ? 'Several (see loads)' : '—'),
            'Request' => $t['request_code'],
            'Customer LPO' => $t['request_lpo'] ?: implode(', ', array_filter(array_column($d['shipments'], 'customer_reference'))),
            'Journey' => $t['journey_code'] ? $t['journey_code'] . ($t['leg_no'] ? ', leg ' . $t['leg_no'] : '') : '',
            'Planned by' => $t['planned_by'],
            'Planned departure' => self::when($t['planned_departure_at']),
            'Left' => self::when($t['departure_at'] ?: $t['dispatched_at']),
            'Planned arrival' => self::when($t['planned_arrival_at']),
            'Arrived' => self::when($t['arrival_at']),
            'Dispatched by' => $t['dispatched_by'],
            'Cargo' => $t['cargo_summary'],
        ]);
        if (trim((string) $t['notes']) !== '') {
            $this->note('Instructions from the office: ' . trim((string) $t['notes']));
        }

        // ---------------------------------------------------- truck and driver
        $this->section('Truck and driver');
        $half = ($width - 16) / 2;
        $top = $this->y;
        $leftEnd = $this->pairsAt(self::L, $half, [
            'Truck' => $t['plate_number'],
            'Type' => trim($t['vehicle_type'] . ' · ' . trim($t['make'] . ' ' . $t['model']), ' ·'),
            'Carries' => (float) $t['capacity_kg'] > 0 ? number_format((float) $t['capacity_kg']) . ' kg' . ((float) $t['capacity_m3'] > 0 ? ' · ' . self::num((float) $t['capacity_m3']) . ' m³' : '') : '',
            'Cooling unit' => $t['plate_number'] ? ((int) $t['has_cooling_unit'] === 1 ? 'Yes' : 'No') : '',
            'Mileage at start' => $t['mileage'] !== null ? number_format((int) $t['mileage']) . ' km' : '',
            'Ownership' => $t['ownership'] ? Schema::label((string) $t['ownership']) : '',
        ], $top);
        $rightEnd = $this->pairsAt(self::L + $half + 16, $half, [
            'Driver' => $t['driver_name'],
            'Phone' => $t['driver_phone'],
            'Licence' => trim(($t['license_number'] ?? '') . ($t['license_class'] ? ' (class ' . $t['license_class'] . ')' : '')),
            'Licence expires' => $t['license_expiry'],
            'Emergency contact' => trim(($t['emergency_contact'] ?? '') . ' ' . ($t['emergency_phone'] ?? '')),
        ], $top);
        $this->y = max($leftEnd, $rightEnd) + 6;

        // --------------------------------------------------------------- route
        $this->section('Route and stops');
        if ($d['stops'] === []) {
            $this->note(sprintf('Straight run: %s to %s, no stops recorded.', $t['pickup_location'], $t['destination']));
        } else {
            $rows = [];
            foreach ($d['stops'] as $n => $stop) {
                $rows[] = [(string) ($n + 1), Schema::label((string) $stop['stop_type']), (string) $stop['location_name'],
                    trim(($stop['contact_name'] ?? '') . "\n" . ($stop['contact_phone'] ?? '')), self::when($stop['planned_arrival_at']),
                    (self::when($stop['actual_arrival_at']) ?: '—') . (!empty($stop['arrival_mileage'])
                        ? "\n" . number_format((int) $stop['arrival_mileage']) . ' km' . ($t['start_mileage'] !== null ? ' (+' . number_format((int) $stop['arrival_mileage'] - (int) $t['start_mileage']) . ')' : '') : ''),
                    Schema::label((string) $stop['status'])];
            }
            $this->table([['#', 18], ['Type', 58], ['Place', 140], ['Contact', 105], ['Planned', 70], ['Actual', 70], ['Status', 62]], $rows);
        }

        // ---------------------------------------------------------------- loads
        $this->section(sprintf('Loads on board  (%d · %s kg · %s packages)', $d['totals']['loads'], number_format((float) $d['totals']['weight']), number_format((float) $d['totals']['packages'])));
        if ($d['shipments'] === []) {
            $this->note('No load on this trip yet.');
        }
        foreach ($d['shipments'] as $s) {
            $this->ensure(70);
            $this->pdf->text(self::L, $this->y, $s['shipment_code'] . '  ·  ' . ($s['customer_name'] ?: 'Internal'), 9.5, true, self::INK);
            $this->pdf->text(self::L, $this->y, Schema::label((string) $s['status']), 8, true, $this->accent, 'right', $width);
            $this->y += 15;
            $this->pairs([
                'Goods' => $s['cargo_description'],
                'Cargo type' => Schema::label((string) $s['cargo_type']) . ((int) $s['is_hazardous'] === 1 ? ' · HAZARDOUS' : ''),
                'Packages' => $s['packages_count'] !== null ? number_format((float) $s['packages_count']) : '',
                'Gross weight' => $s['weight_kg'] !== null ? number_format((float) $s['weight_kg']) . ' kg' : '',
                'Volume' => (float) $s['volume_m3'] > 0 ? self::num((float) $s['volume_m3']) . ' m³' : '',
                'Declared value' => (float) $s['declared_value'] > 0 ? Currency::format((float) $s['declared_value'], (string) ($s['currency'] ?: Currency::base())) : '',
                'From' => $s['from_depot'] ?: $s['origin'],
                'Drops at' => $s['to_depot'] ?: $s['destination'],
                'Receiver' => trim(($s['consignee_name'] ?? '') . ' ' . ($s['consignee_phone'] ?? '')),
                'Container' => trim(($s['container_no'] ?? '') . ($s['container_size'] ? ' (' . Schema::label((string) $s['container_size']) . ')' : '')),
                'Seal' => $s['seal_no'],
                'Trailer' => $s['trailer_plate'],
                'Bill of lading' => $s['bill_of_lading'],
                'Customer LPO' => $s['customer_reference'],
                'Temperature' => $s['temperature_min_c'] !== null ? self::num((float) $s['temperature_min_c']) . ' to ' . self::num((float) $s['temperature_max_c']) . ' °C' : '',
                'Request' => $s['request_code'],
            ]);
            if (trim((string) $s['special_instructions']) !== '') {
                $this->note('Handling: ' . trim((string) $s['special_instructions']));
            }
            $this->y += 4;
        }

        // --------------------------------------------------------------- papers
        $this->section('Papers carried');
        if ($d['papers'] === []) {
            $this->note('No papers recorded.');
        } else {
            $rows = [];
            foreach ($d['papers'] as $p) {
                $rows[] = [$p['kind'], (string) $p['type'], (string) ($p['number'] ?: '—'), (string) ($p['issued'] ?: '—'), (string) ($p['expires'] ?: 'No expiry'), Schema::label((string) $p['status']), $p['scan'] ? 'Yes' : 'No'];
            }
            $this->table([['For', 52], ['Paper', 110], ['Number', 115], ['Issued', 62], ['Expires', 64], ['Status', 70], ['Scan', 50]], $rows);
        }

        // --------------------------------------------------- the final check
        $this->section('Final check before leaving');
        if ($d['checks'] === []) {
            $this->note('No dispatch check recorded.');
        }
        foreach ($d['checks'] as $c) {
            $yes = static fn ($v): string => (int) $v === 1 ? 'Yes' : 'No';
            $this->pairs([
                'Shipment' => $c['shipment'],
                'Result' => Schema::label((string) $c['status']),
                'Cargo loaded' => $yes($c['cargo_loaded']), 'Quantity verified' => $yes($c['quantity_verified']),
                'Packaging verified' => $yes($c['packaging_verified']), 'Papers verified' => $yes($c['documents_verified']) . ' (by the system)',
                'Truck verified' => $yes($c['vehicle_verified']) . ' (by the system)', 'Driver verified' => $yes($c['driver_verified']) . ' (by the system)',
                'Route verified' => $yes($c['route_verified']) . ' (by the system)', 'Checked by' => trim(($c['checked_by_name'] ?? '') . ' ' . self::when($c['checked_at'])),
            ]);
            if (trim((string) $c['notes']) !== '') {
                $this->note('Notes: ' . trim((string) $c['notes']));
            }
        }
        $r = $d['readiness'];
        if ($r !== null) {
            $open = array_filter($r['items'], static fn (array $i): bool => $i['state'] !== 'ok');
            $passed = count($r['items']) - count($open);
            $this->ensure(20);
            $this->pdf->text(self::L, $this->y, sprintf('System check now: %s · %d of %d points in order', $r['ready'] ? 'READY' : 'NOT READY', $passed, count($r['items'])), 8.5, true, $r['ready'] ? self::GREEN : self::RED);
            $this->y += 14;
            foreach ($open as $item) {
                $this->ensure(14);
                $colour = $item['state'] === 'fail' ? self::RED : self::AMBER;
                $this->pdf->text(self::L, $this->y, $item['state'] === 'fail' ? 'Must fix' : 'Warning', 7.6, true, $colour);
                $this->y += $this->pdf->paragraph(self::L + 52, $this->y, $width - 52, $item['label'] . ': ' . $item['detail'], 7.8, false, self::INK) + 2;
            }
        }

        // ------------------------------------------------- borders, deliveries
        $this->section('Border clearances');
        if ($d['crossings'] === []) {
            $this->note('None recorded yet. Each border clearance is added here as the truck crosses.');
        } else {
            $rows = [];
            foreach ($d['crossings'] as $x) {
                $rows[] = [(string) $x['reference'], (string) ($x['post_name'] ?: '—'), trim(Schema::label((string) $x['direction']) . ' ' . ($x['customs_country'] ?? '')),
                    (string) ($x['declaration_no'] ?: '—'), Schema::label((string) $x['status']), self::when($x['released_at'] ?: $x['cleared_at']) ?: '—',
                    $d['commercial'] && (float) $x['charges_total'] > 0 ? Currency::format((float) $x['charges_total'], (string) ($x['currency'] ?: Currency::base())) : '—'];
            }
            $this->table([['Clearance', 78], ['Border', 90], ['Direction', 70], ['Declaration', 85], ['Status', 70], ['Released', 70], ['Charges', 60]], $rows);
        }

        $this->section('Deliveries');
        if ($d['deliveries'] === []) {
            $this->note('Not delivered yet.');
        } else {
            $rows = [];
            foreach ($d['deliveries'] as $dl) {
                $rows[] = [(string) $dl['delivery_code'], (string) ($dl['shipment_code'] ?? '—'), trim($dl['recipient_name'] . "\n" . ($dl['recipient_phone'] ?? '')),
                    (string) $dl['destination'], self::when($dl['planned_at']) ?: '—', self::when($dl['delivered_at']) ?: '—', Schema::label((string) $dl['status'])];
            }
            $this->table([['Delivery', 70], ['Shipment', 70], ['Receiver', 95], ['Where', 110], ['Planned', 60], ['Delivered', 60], ['Status', 58]], $rows);
        }

        // ---------------------------------------------------------- on the road
        $this->section('On the road: fuel and expenses');
        if ($d['fuel'] === [] && $d['expenses'] === []) {
            $this->note('Nothing recorded yet. Fuel and expenses the driver records on the trip are added here.');
        } else {
            $rows = [];
            foreach ($d['fuel'] as $f) {
                $rows[] = [self::when($f['purchased_at']), 'Fuel', sprintf('%s L at %s%s', self::num((float) $f['litres']), $f['station_name'] ?: 'a station', $f['mileage'] ? ' · ' . number_format((int) $f['mileage']) . ' km' : ''),
                    Currency::format(round((float) $f['litres'] * (float) $f['unit_price'], 2), (string) ($f['currency'] ?: Currency::base())), 'Recorded'];
            }
            foreach ($d['expenses'] as $e) {
                $rows[] = [self::when($e['expense_date']), (string) $e['category'], trim((string) ($e['notes'] ?? '')) ?: (string) $e['reference_code'],
                    Currency::format((float) $e['amount'], (string) ($e['currency'] ?: Currency::base())), Schema::label((string) $e['status'])];
            }
            usort($rows, static fn (array $a, array $b): int => strcmp((string) strtotime($a[0]), (string) strtotime($b[0])));
            $this->table([['When', 82], ['What', 90], ['Detail', 186], ['Amount', 90, 'right'], ['Status', 75]], $rows);
        }

        // ---------------------------------------------------- support asked
        if (!empty($d['support'])) {
            $this->section('Support asked for on the road');
            $rows = [];
            foreach (array_reverse($d['support']) as $help) {
                $currency = (string) ($help['currency'] ?: Currency::base());
                $asked = (float) $help['amount_requested'] > 0 ? Currency::format((float) $help['amount_requested'], $currency) : '—';
                $answer = $help['status'] === 'rejected' ? 'Refused: ' . ($help['rejection_reason'] ?? '') : trim((string) $help['response']);
                if ((float) $help['amount_given'] > 0) {
                    $answer = trim($answer . ' · ' . Currency::format((float) $help['amount_given'], $currency) . ($help['given_method'] ? ' by ' . $help['given_method'] : '') . ($help['given_reference'] ? ' (' . $help['given_reference'] . ')' : ''), ' ·');
                }
                $rows[] = [$help['request_code'] . "\n" . self::when($help['created_at']), (Schema::SUPPORT_TYPES[$help['support_type']] ?? '') . "\n" . $help['title'] . ($help['location'] ? "\nAt " . $help['location'] : ''),
                    $asked, (string) ($help['assigned_name'] ?? 'Office'), Schema::SUPPORT_STATUS[$help['status']] ?? $help['status'], $answer ?: '—'];
            }
            $this->table([['Request', 82], ['What', 140], ['Asked', 62, 'right'], ['Sent to', 70], ['Status', 55], ['Answer', 114]], $rows);
        }

        // --------------------------------------------- container and storage
        if ($d['containers'] !== [] || $d['storage'] !== []) {
            $this->section('Container and storage');
            if ($d['containers'] !== []) {
                $rows = [];
                foreach ($d['containers'] as $c) {
                    $rows[] = [(string) $c['container_no'], Schema::label((string) $c['container_size']), self::when($c['picked_up_at']) ?: '—', self::when($c['unloaded_at']) ?: '—',
                        self::when($c['returned_at']) ?: '—', trim(Schema::label((string) $c['status']) . ' · ' . (int) $c['free_days'] . ' free days')];
                }
                $this->table([['Container', 90], ['Size', 55], ['Picked up', 80], ['Unloaded', 80], ['Returned', 80], ['Status', 138]], $rows);
            }
            if ($d['storage'] !== []) {
                $rows = [];
                foreach ($d['storage'] as $lot) {
                    $rows[] = [(string) $lot['lot_code'], (string) ($lot['warehouse_name'] ?? ''), (string) $lot['description'],
                        self::num((float) $lot['quantity_received']) . ' ' . $lot['unit'] . ' in, ' . self::num((float) $lot['quantity_released']) . ' out', self::when($lot['received_at']), Schema::label((string) $lot['status'])];
                }
                $this->table([['Lot', 70], ['Warehouse', 95], ['Goods', 130], ['Quantity', 95], ['Received', 70], ['Status', 63]], $rows);
            }
        }

        // ------------------------------------------------------------- timeline
        $this->section('Timeline: what happened, in order');
        if ($d['timeline'] === []) {
            $this->note('Nothing recorded yet.');
        } else {
            $rows = [];
            foreach ($d['timeline'] as $event) {
                $rows[] = [self::when($event['when']), $event['what'], $event['detail']];
            }
            $this->table([['When', 90], ['What', 170], ['Detail', 263]], $rows);
        }

        // ---------------------------------------------------------------- money
        if ($d['commercial'] && $d['money'] !== null && $d['invoices'] !== []) {
            $this->section('Invoice and payments');
            $rows = [];
            foreach ($d['invoices'] as $invoice) {
                $currency = (string) ($invoice['currency'] ?: Currency::base());
                $rows[] = [(string) $invoice['invoice_number'], Schema::label((string) $invoice['status']), (string) ($invoice['issue_date'] ?? ''), (string) ($invoice['due_date'] ?? ''),
                    Currency::format((float) $invoice['total_amount'], $currency), Currency::format((float) $invoice['amount_paid'], $currency),
                    Currency::format(max(0, (float) $invoice['total_amount'] - (float) $invoice['amount_paid']), $currency)];
                foreach ($invoice['payments'] as $payment) {
                    $rows[] = ['  ' . $payment['payment_code'], 'Payment', self::when($payment['paid_at']), (string) $payment['method'], '', Currency::format((float) $payment['amount'], (string) ($payment['currency'] ?: $currency)), (string) ($payment['reference'] ?? '')];
                }
            }
            $this->table([['Invoice', 80], ['Status', 60], ['Issued', 70], ['Due', 70], ['Total', 82, 'right'], ['Paid', 82, 'right'], ['Balance', 79, 'right']], $rows);
        }

        if ($d['commercial'] && $d['money'] !== null) {
            $m = $d['money'];
            $this->section('Money (office copy only)');
            $rows = [];
            foreach ($m['lines'] as $line) {
                $rows[] = [$line['what'], $line['basis'], Currency::format($line['amount'], $line['currency'])];
            }
            foreach ($m['cost_lines'] as $line) {
                $rows[] = [$line['what'], 'Cost', '- ' . Currency::format($line['amount'], $line['currency'])];
            }
            if ($rows !== []) {
                $this->table([['Item', 250], ['Basis', 158], ['Amount', 115, 'right']], $rows);
            }
            $this->ensure(18 * max(1, count($m['margin'])) + 10);
            foreach ($m['margin'] as $currency => $margin) {
                $this->pdf->text(self::L, $this->y, sprintf('Revenue %s  ·  costs so far %s', Currency::format((float) ($m['revenue'][$currency] ?? 0), $currency), Currency::format((float) ($m['costs'][$currency] ?? 0), $currency)), 8.5, false, self::GREY);
                $this->pdf->text(self::L, $this->y, 'Margin so far ' . Currency::format((float) $margin, $currency), 9.5, true, $margin >= 0 ? self::GREEN : self::RED, 'right', $width);
                $this->y += 16;
            }
            if ($m['recharge'] !== []) {
                $rows = [];
                foreach ($m['recharge'] as $line) {
                    $rows[] = [$line['what'], $line['status'], Currency::format($line['amount'], $line['currency'])];
                }
                $this->note('Paid for the customer, to be recharged at cost on the invoice:');
                $this->table([['Disbursement', 250], ['Status', 158], ['Amount', 115, 'right']], $rows);
            }
            $this->note('Amounts are shown in the currency they were charged or paid in; each currency is kept in its own books, nothing is converted.');
        }

        // ----------------------------------------------------------- signatures
        $this->ensure(96);
        $this->y += 8;
        $boxW = ($width - 24) / 3;
        $i = 0;
        $signed = $d['signatures'] ?? [];
        $boxes = [
            'Dispatched by' => [(string) ($t['dispatched_by'] ?? ''), $signed['dispatched'] ?? null],
            'Driver' => [(string) ($t['driver_name'] ?? ''), $signed['driver'] ?? null],
            'Received at destination' => [(string) ($signed['received_name'] ?? ''), $signed['received'] ?? null],
        ];
        foreach ($boxes as $n => [$who, $signature]) {
            $x = self::L + $i * ($boxW + 12);
            $i++;
            $this->pdf->rect($x, $this->y, $boxW, 78, null, self::RULE);
            $this->pdf->text($x + 8, $this->y + 8, strtoupper($n), 7.6, true, $this->accent);
            $this->pdf->text($x + 8, $this->y + 21, $who, 9, true, self::INK);
            if ($signature !== null) {
                $this->pdf->image($signature, $x + 8, $this->y + 32, $boxW - 16, 29, true);
            }
            $this->pdf->line($x + 8, $this->y + 62, $x + $boxW - 8, $this->y + 62, 0.6, self::GREY);
            $this->pdf->text($x + 8, $this->y + 66, 'Signature and date', 7, false, self::GREY);
        }
        $this->y += 86;

        $this->footers();
    }

    // ------------------------------------------------------------- building

    private function header(): void
    {
        $d = $this->data;
        $t = $d['trip'];
        $width = self::R - self::L;
        $brand = Report::brand();
        $logoWidth = $brand['logo'] ? $this->pdf->logo((string) $brand['logo'], self::L, 32, 110, 42) : 0.0;
        $x = self::L + ($logoWidth > 0 ? $logoWidth + 10 : 0);
        $this->pdf->text($x, 34, $d['company']['name'], 13.5, true, self::INK);
        if ($d['company']['details'] !== '') {
            $this->pdf->paragraph($x, 52, 270 - ($x - self::L), $d['company']['details'], 7.6, false, self::GREY);
        }
        $this->pdf->text(self::L, 32, 'TRIP REPORT', 17, true, $this->accent, 'right', $width);
        $this->pdf->text(self::L, 54, $t['reference_code'] . '  ·  ' . strtoupper(Schema::label((string) $t['status'])), 10, true, self::INK, 'right', $width);
        $this->pdf->text(self::L, 69, ($d['commercial'] ? 'Office copy' : 'Driver copy') . '  ·  generated ' . date('j M Y, H:i', (int) strtotime((string) $d['generated_at'])), 7.6, false, self::GREY, 'right', $width);
        $this->pdf->line(self::L, 86, self::R, 86, 1.4, $this->accent);
        $this->y = 96;
    }

    private function tiles(): void
    {
        $d = $this->data;
        $t = $d['trip'];
        $capacity = (float) $t['capacity_kg'];
        $tiles = [
            'Route' => $t['pickup_location'] . ' to ' . $t['destination'],
            'Truck' => (string) ($t['plate_number'] ?: '—'),
            'Driver' => (string) ($t['driver_name'] ?: '—'),
            'Load' => number_format((float) $d['totals']['weight']) . ' kg' . ($capacity > 0 ? ' of ' . number_format($capacity) . ' (' . (int) round((float) $d['totals']['weight'] / $capacity * 100) . '%)' : ''),
            'Leaves' => self::when($t['departure_at'] ?: $t['planned_departure_at']) ?: '—',
            'Arrives' => self::when($t['arrival_at'] ?: $t['planned_arrival_at']) ?: '—',
            'Distance' => ($d['distance']['start_km'] ?? null) !== null ? number_format((int) $d['distance']['driven']) . ' km' . (($d['distance']['end_km'] ?? null) !== null ? ' in all' : ' so far') . ' (from ' . number_format((int) $d['distance']['start_km']) . ' km)' : 'Not started',
        ];
        $count = 3;
        $gap = 8.0;
        $w = (self::R - self::L - ($count - 1) * $gap) / $count;
        $i = 0;
        foreach ($tiles as $label => $value) {
            $x = self::L + ($i % $count) * ($w + $gap);
            $top = $this->y + intdiv($i, $count) * 46;
            $this->pdf->rect($x, $top, $w, 40, $this->tint);
            $this->pdf->text($x + 8, $top + 6, strtoupper($label), 6.8, true, self::GREY);
            $lines = FormPdf::wrap($value, $w - 16, 8.8, true);
            foreach (array_slice($lines, 0, 2) as $k => $line) {
                $this->pdf->text($x + 8, $top + 17 + $k * 10.5, $line, 8.8, true, self::INK);
            }
            $i++;
        }
        $this->y += (int) ceil(count($tiles) / $count) * 46 + 4;
    }

    /** Where the trip has got to: a bar, then every step with its date. */
    private function progress(): void
    {
        $p = $this->data['progress'];
        $width = self::R - self::L;
        $this->section(sprintf('Progress  ·  %d of %d steps  ·  %d%%', $p['done'], $p['total'], $p['percent']));
        $this->pdf->rect(self::L, $this->y, $width, 9, [0.91, 0.93, 0.95]);
        if ($p['percent'] > 0) {
            $this->pdf->rect(self::L, $this->y, $width * $p['percent'] / 100, 9, $this->accent);
        }
        $this->y += 15;
        $this->pdf->text(self::L, $this->y, 'Reached: ' . ($p['reached'] !== '' ? $p['reached'] : '—'), 8.8, true, self::INK);
        $this->pdf->text(self::L, $this->y, $p['current'] === 'All done' ? 'All done' : 'Next: ' . $p['current'], 8.8, true, $this->accent, 'right', $width);
        $this->y += 16;
        $rows = [];
        foreach ($p['stages'] as $n => $stage) {
            $rows[] = [(string) ($n + 1), $stage['label'], ['done' => 'Done', 'now' => 'Next', 'todo' => 'To come'][$stage['state']], self::when($stage['when']) ?: '—', $stage['detail']];
        }
        $this->table([['#', 20], ['Step', 215], ['Status', 55], ['When', 85], ['Detail', 148]], $rows);
    }

    private function section(string $title): void
    {
        $this->ensure(44);
        $this->y += 6;
        $this->pdf->rect(self::L, $this->y, self::R - self::L, 17, $this->tint);
        $this->pdf->text(self::L + 7, $this->y + 4.5, strtoupper($title), 8.2, true, $this->accent);
        $this->y += 24;
    }

    /** Label and value pairs in two columns, skipping the empty ones. */
    private function pairs(array $pairs): void
    {
        $pairs = array_filter($pairs, static fn ($v): bool => trim((string) $v) !== '');
        $width = self::R - self::L;
        $col = ($width - 16) / 2;
        $items = array_values(array_map(null, array_keys($pairs), array_values($pairs)));
        for ($i = 0; $i < count($items); $i += 2) {
            $heights = [];
            foreach ([0, 1] as $k) {
                if (!isset($items[$i + $k])) {
                    continue;
                }
                $heights[] = count(FormPdf::wrap((string) $items[$i + $k][1], $col - 92, 8.4)) * 11;
            }
            $rowHeight = max($heights) + 4;
            $this->ensure($rowHeight);
            foreach ([0, 1] as $k) {
                if (!isset($items[$i + $k])) {
                    continue;
                }
                [$label, $value] = $items[$i + $k];
                $x = self::L + $k * ($col + 16);
                $this->pdf->text($x, $this->y, (string) $label, 7.8, false, self::GREY);
                $this->pdf->paragraph($x + 92, $this->y, $col - 92, (string) $value, 8.4, true, self::INK, 1.3);
            }
            $this->y += $rowHeight;
            $this->pdf->line(self::L, $this->y - 2, self::R, $this->y - 2, 0.3, [0.92, 0.93, 0.95]);
        }
        $this->y += 2;
    }

    /** Pairs in one column at a given place; returns where they end. */
    private function pairsAt(float $x, float $width, array $pairs, float $top): float
    {
        $y = $top;
        foreach ($pairs as $label => $value) {
            if (trim((string) $value) === '') {
                continue;
            }
            $this->pdf->text($x, $y, (string) $label, 7.8, false, self::GREY);
            $y += $this->pdf->paragraph($x + 92, $y, $width - 92, (string) $value, 8.4, true, self::INK, 1.3) + 4;
        }

        return $y;
    }

    /** A table with wrapping cells; the heading row repeats on every new page. */
    private function table(array $columns, array $rows): void
    {
        $head = function () use ($columns): void {
            $x = self::L;
            $this->pdf->rect(self::L, $this->y, self::R - self::L, 15, [0.11, 0.15, 0.20]);
            foreach ($columns as $column) {
                $this->pdf->text($x + 4, $this->y + 4, strtoupper((string) $column[0]), 6.8, true, [1, 1, 1], ($column[2] ?? 'left') === 'right' ? 'right' : 'left', $column[1] - 8);
                $x += $column[1];
            }
            $this->y += 17;
        };
        $this->ensure(36);
        $head();
        foreach ($rows as $row) {
            $wrapped = [];
            foreach ($columns as $k => $column) {
                $lines = [];
                foreach (preg_split('/\R/', (string) ($row[$k] ?? '')) ?: [''] as $part) {
                    $lines = array_merge($lines, FormPdf::wrap($part, $column[1] - 8, 7.8));
                }
                $wrapped[$k] = array_values(array_filter($lines, static fn (string $l): bool => $l !== '')) ?: [''];
            }
            $height = max(array_map('count', $wrapped)) * 10 + 6;
            if ($this->y + $height > self::BOTTOM) {
                $this->newPage();
                $head();
            }
            $x = self::L;
            foreach ($columns as $k => $column) {
                foreach ($wrapped[$k] as $n => $line) {
                    $this->pdf->text($x + 4, $this->y + 2 + $n * 10, $line, 7.8, $k === 0, self::INK, ($column[2] ?? 'left') === 'right' ? 'right' : 'left', $column[1] - 8);
                }
                $x += $column[1];
            }
            $this->y += $height;
            $this->pdf->line(self::L, $this->y - 2, self::R, $this->y - 2, 0.4, self::RULE);
        }
        $this->y += 4;
    }

    private function note(string $text): void
    {
        $lines = FormPdf::wrap($text, self::R - self::L, 7.8);
        $this->ensure(count($lines) * 10.5 + 4);
        $this->y += $this->pdf->paragraph(self::L, $this->y, self::R - self::L, $text, 7.8, false, self::GREY) + 4;
    }

    private function ensure(float $height): void
    {
        if ($this->y + $height > self::BOTTOM) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        $this->pdf->addPage();
        $this->pdf->text(self::L, 30, $this->data['company']['name'], 9.5, true, self::INK);
        $this->pdf->text(self::L, 31, 'TRIP REPORT ' . $this->code . ' (continued)', 8.5, true, $this->accent, 'right', self::R - self::L);
        $this->pdf->line(self::L, 46, self::R, 46, 1.0, $this->accent);
        $this->y = 56;
    }

    private function footers(): void
    {
        $total = $this->pdf->pageCount();
        for ($page = 0; $page < $total; $page++) {
            $this->pdf->usePage($page);
            $this->pdf->line(self::L, 806, self::R, 806, 0.5, self::RULE);
            $this->pdf->text(self::L, 811, sprintf('%s  ·  Trip report %s  ·  %s', $this->data['company']['name'], $this->code, $this->data['commercial'] ? 'office copy' : 'driver copy'), 7, false, self::GREY);
            $this->pdf->text(self::L, 811, sprintf('Page %d of %d', $page + 1, $total), 7, false, self::GREY, 'right', self::R - self::L);
        }
    }

    private static function when(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if ($value === '') {
            return '';
        }
        // A date with no time of day (an expense) is shown as the date alone.
        $stamp = (int) strtotime($value);

        return date('H:i:s', $stamp) === '00:00:00' ? date('j M Y', $stamp) : date('j M Y, H:i', $stamp);
    }

    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
