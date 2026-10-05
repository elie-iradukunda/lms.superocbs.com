<?php

declare(strict_types=1);

namespace Models;

use Support\Report;
use Support\SheetBook;

/**
 * The trip report as an Excel workbook: a sheet per section, figures as real
 * numbers so they can be summed and filtered. Prices only in the office copy.
 */
final class TripReportSheet
{
    public static function build(array $d): ?string
    {
        $brand = Report::brand();
        $book = new SheetBook($brand['rgb'], $brand['tint']);
        $t = $d['trip'];
        $when = static fn ($v): string => ($v ?? '') === '' ? '' : date(date('H:i:s', (int) strtotime((string) $v)) === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i', (int) strtotime((string) $v));
        $label = static fn ($v): string => Schema::label((string) $v);

        // ------------------------------------------------------------- summary
        $rows = [
            [[$d['company']['name'], 'title']],
            [['Trip report ' . $t['reference_code'] . ' · ' . ($d['commercial'] ? 'office copy' : 'driver copy') . ' · generated ' . $d['generated_at'], 'subtitle']],
            [],
            [['The trip', 'section'], ['', 'section']],
        ];
        $pairs = [
            'Trip' => $t['reference_code'], 'Status' => $label($t['status']), 'Type' => $label($t['trip_type']),
            'Customer' => $t['customer_name'], 'Request' => $t['request_code'], 'Customer LPO' => $t['request_lpo'],
            'From' => $t['pickup_location'], 'To' => $t['destination'], 'Cargo' => $t['cargo_summary'],
            'Planned departure' => $when($t['planned_departure_at']), 'Left' => $when($t['departure_at'] ?: $t['dispatched_at']),
            'Planned arrival' => $when($t['planned_arrival_at']), 'Arrived' => $when($t['arrival_at']),
            'Planned by' => $t['planned_by'], 'Dispatched by' => $t['dispatched_by'], 'Notes' => $t['notes'],
        ];
        foreach ($pairs as $k => $v) {
            if (trim((string) $v) !== '') {
                $rows[] = [[$k, 'label'], [(string) $v, 'bold']];
            }
        }
        $rows[] = [];
        $rows[] = [['Truck and driver', 'section'], ['', 'section']];
        foreach ([
            'Truck' => $t['plate_number'], 'Truck type' => trim($t['vehicle_type'] . ' ' . $t['make'] . ' ' . $t['model']),
            'Carries (kg)' => (float) $t['capacity_kg'], 'Load on board (kg)' => (float) $d['totals']['weight'],
            'Driver' => $t['driver_name'], 'Driver phone' => $t['driver_phone'], 'Licence' => trim(($t['license_number'] ?? '') . ' ' . ($t['license_class'] ? '(class ' . $t['license_class'] . ')' : '')),
            'Licence expires' => $t['license_expiry'], 'Emergency contact' => trim(($t['emergency_contact'] ?? '') . ' ' . ($t['emergency_phone'] ?? '')),
        ] as $k => $v) {
            if (is_float($v)) {
                $rows[] = [[$k, 'label'], [$v, 'number']];
            } elseif (trim((string) $v) !== '') {
                $rows[] = [[$k, 'label'], [(string) $v, 'bold']];
            }
        }
        $r = $d['readiness'];
        if ($r !== null) {
            $rows[] = [];
            $rows[] = [['System check', 'section'], ['', 'section']];
            $rows[] = [['Result', 'label'], [$r['ready'] ? 'READY' : 'NOT READY', $r['ready'] ? 'good' : 'bad']];
            foreach ($r['items'] as $item) {
                if ($item['state'] !== 'ok') {
                    $rows[] = [[$item['state'] === 'fail' ? 'Must fix' : 'Warning', $item['state'] === 'fail' ? 'bad' : 'warn'], [$item['label'] . ': ' . $item['detail'], 'wrap']];
                }
            }
        }
        $book->sheet('Summary', $rows, [24, 80]);

        // ------------------------------------------------------------ progress
        $p = $d['progress'];
        $rows = [
            [[sprintf('Progress: %d of %d steps (%d%%)', $p['done'], $p['total'], $p['percent']), 'section'], ['', 'section'], ['', 'section'], ['', 'section'], ['', 'section']],
            [['Reached', 'label'], [$p['reached'] ?: '—', 'bold'], ['Next', 'label'], [$p['current'], 'bold']],
            [],
            [['#', 'head'], ['Step', 'head'], ['Status', 'head'], ['When', 'head'], ['Detail', 'head']],
        ];
        foreach ($p['stages'] as $n => $stage) {
            $rows[] = [$n + 1, [$stage['label'], $stage['state'] === 'now' ? 'bold' : 'text'],
                [['done' => 'Done', 'now' => 'Next', 'todo' => 'To come'][$stage['state']], $stage['state'] === 'done' ? 'good' : ($stage['state'] === 'now' ? 'warn' : 'text')],
                $when($stage['when']), $stage['detail']];
        }
        $book->sheet('Progress', $rows, [5, 46, 12, 18, 40], 4);

        // ------------------------------------------------------------ timeline
        $rows = [[['When', 'head'], ['What', 'head'], ['Detail', 'head']]];
        foreach ($d['timeline'] as $event) {
            $rows[] = [$when($event['when']), [$event['what'], 'bold'], $event['detail']];
        }
        $book->sheet('Timeline', $rows, [18, 36, 70], 1);

        // ---------------------------------------------------------- on the road
        $rows = [[['When', 'head'], ['What', 'head'], ['Detail', 'head'], ['Currency', 'head'], ['Amount', 'head'], ['Status', 'head']]];
        foreach ($d['fuel'] as $f) {
            $rows[] = [$when($f['purchased_at']), 'Fuel', sprintf('%s L at %s', rtrim(rtrim(number_format((float) $f['litres'], 2), '0'), '.'), $f['station_name'] ?: 'a station'),
                (string) ($f['currency'] ?? ''), [round((float) $f['litres'] * (float) $f['unit_price'], 2), 'money'], 'Recorded'];
        }
        foreach ($d['expenses'] as $e) {
            $rows[] = [$when($e['expense_date']), (string) $e['category'], trim((string) ($e['notes'] ?? '')) ?: (string) $e['reference_code'],
                (string) ($e['currency'] ?? ''), [(float) $e['amount'], 'money'], $label($e['status'])];
        }
        $book->sheet('On the road', $rows, [18, 20, 50, 10, 14, 12], 1);

        if (!empty($d['support'])) {
            $rows = [[['Request', 'head'], ['Asked', 'head'], ['Type', 'head'], ['What', 'head'], ['Where', 'head'], ['Urgency', 'head'], ['Currency', 'head'], ['Amount asked', 'head'],
                      ['Sent to', 'head'], ['Status', 'head'], ['Answer', 'head'], ['Amount given', 'head'], ['Sent by', 'head'], ['Reference', 'head']]];
            foreach (array_reverse($d['support']) as $help) {
                $rows[] = [[(string) $help['request_code'], 'bold'], $when($help['created_at']), Schema::SUPPORT_TYPES[$help['support_type']] ?? '', (string) $help['title'], (string) ($help['location'] ?? ''),
                    $label($help['urgency']), (string) ($help['currency'] ?? ''), [(float) $help['amount_requested'], 'money'], (string) ($help['assigned_name'] ?? ''),
                    Schema::SUPPORT_STATUS[$help['status']] ?? $help['status'], $help['status'] === 'rejected' ? 'Refused: ' . ($help['rejection_reason'] ?? '') : (string) ($help['response'] ?? ''),
                    [(float) $help['amount_given'], 'money'], (string) ($help['given_method'] ?? ''), (string) ($help['given_reference'] ?? '')];
            }
            $book->sheet('Support', $rows, [16, 17, 18, 34, 24, 11, 9, 14, 20, 14, 40, 14, 14, 18], 1);
        }

        // --------------------------------------------------------------- route
        $rows = [[['#', 'head'], ['Type', 'head'], ['Place', 'head'], ['Contact', 'head'], ['Phone', 'head'], ['Planned arrival', 'head'], ['Actual arrival', 'head'], ['Left at', 'head'], ['Km on the clock', 'head'], ['Km from the start', 'head'], ['Status', 'head']]];
        foreach ($d['stops'] as $n => $s) {
            $rows[] = [$n + 1, $label($s['stop_type']), (string) $s['location_name'], (string) ($s['contact_name'] ?? ''), (string) ($s['contact_phone'] ?? ''), $when($s['planned_arrival_at']), $when($s['actual_arrival_at']), $when($s['departed_at'] ?? null),
                !empty($s['arrival_mileage']) ? [(int) $s['arrival_mileage'], 'number'] : '',
                !empty($s['arrival_mileage']) && $t['start_mileage'] !== null ? [(int) $s['arrival_mileage'] - (int) $t['start_mileage'], 'number'] : '', $label($s['status'])];
        }
        $book->sheet('Route', $rows, [5, 13, 38, 26, 18, 18, 18, 18, 15, 16, 12], 1);

        // --------------------------------------------------------------- loads
        $head = ['Shipment', 'Customer', 'Status', 'Goods', 'Cargo type', 'Packages', 'Weight (kg)', 'Volume (m3)', 'From', 'Drops at', 'Receiver', 'Receiver phone',
                 'Container', 'Size', 'Seal', 'Trailer', 'Bill of lading', 'Customer LPO', 'Handling'];
        if ($d['commercial']) {
            array_push($head, 'Currency', 'Price', 'Price basis', 'Declared value');
        }
        $rows = [array_map(static fn (string $h): array => [$h, 'head'], $head)];
        foreach ($d['shipments'] as $s) {
            $row = [[(string) $s['shipment_code'], 'bold'], (string) ($s['customer_name'] ?? ''), $label($s['status']), (string) $s['cargo_description'], $label($s['cargo_type']),
                [(float) $s['packages_count'], 'number'], [(float) $s['weight_kg'], 'number'], [(float) $s['volume_m3'], 'money'],
                (string) ($s['from_depot'] ?: $s['origin']), (string) ($s['to_depot'] ?: $s['destination']), (string) ($s['consignee_name'] ?? ''), (string) ($s['consignee_phone'] ?? ''),
                (string) ($s['container_no'] ?? ''), $s['container_size'] ? $label($s['container_size']) : '', (string) ($s['seal_no'] ?? ''), (string) ($s['trailer_plate'] ?? ''),
                (string) ($s['bill_of_lading'] ?? ''), (string) ($s['customer_reference'] ?? ''), (string) ($s['special_instructions'] ?? '')];
            if ($d['commercial']) {
                array_push($row, (string) ($s['currency'] ?? ''), [(float) $s['quoted_amount'], 'money'], (string) ($s['quote_basis'] ?? ''), [(float) $s['declared_value'], 'money']);
            }
            $rows[] = $row;
        }
        $rows[] = [['Total', 'bold'], '', '', '', '', [(float) $d['totals']['packages'], 'number'], [(float) $d['totals']['weight'], 'number'], [(float) $d['totals']['volume'], 'money']];
        $book->sheet('Loads', $rows, array_merge([16, 26, 11, 34, 12, 10, 12, 11, 30, 30, 20, 18, 16, 10, 16, 11, 18, 18, 40], $d['commercial'] ? [9, 13, 34, 15] : []), 1);

        // -------------------------------------------------------------- papers
        $rows = [[['For', 'head'], ['Paper', 'head'], ['Number', 'head'], ['Issued', 'head'], ['Expires', 'head'], ['Status', 'head'], ['Scan attached', 'head']]];
        foreach ($d['papers'] as $p) {
            $expired = ($p['expires'] ?? '') !== '' && $p['expires'] < date('Y-m-d');
            $rows[] = [$p['kind'] . ' · ' . $p['for'], (string) $p['type'], (string) ($p['number'] ?? ''), (string) ($p['issued'] ?? ''), [(string) ($p['expires'] ?: 'No expiry'), $expired ? 'bad' : 'text'],
                [$label($p['status']), $p['status'] === 'valid' ? 'good' : 'bad'], $p['scan'] ? 'Yes' : 'No'];
        }
        $book->sheet('Papers', $rows, [30, 24, 24, 12, 12, 12, 13], 1);

        // ------------------------------------------------------- final check
        $rows = [[['Shipment', 'head'], ['Result', 'head'], ['Cargo loaded', 'head'], ['Quantity verified', 'head'], ['Packaging verified', 'head'], ['Papers verified', 'head'],
                  ['Truck verified', 'head'], ['Driver verified', 'head'], ['Route verified', 'head'], ['Checked by', 'head'], ['Checked at', 'head'], ['Notes', 'head']]];
        foreach ($d['checks'] as $c) {
            $yes = static fn ($v): array => (int) $v === 1 ? ['Yes', 'good'] : ['No', 'bad'];
            $rows[] = [[(string) $c['shipment'], 'bold'], $label($c['status']), $yes($c['cargo_loaded']), $yes($c['quantity_verified']), $yes($c['packaging_verified']),
                $yes($c['documents_verified']), $yes($c['vehicle_verified']), $yes($c['driver_verified']), $yes($c['route_verified']),
                (string) ($c['checked_by_name'] ?? ''), $when($c['checked_at']), (string) ($c['notes'] ?? '')];
        }
        $book->sheet('Final check', $rows, [16, 12, 12, 15, 16, 13, 13, 13, 13, 22, 17, 40], 1);

        // --------------------------------------------- borders and deliveries
        if ($d['crossings'] !== [] || $d['deliveries'] !== []) {
            $rows = [[['Border clearances', 'section']], [['Clearance', 'head'], ['Border', 'head'], ['Direction', 'head'], ['Declaration', 'head'], ['Status', 'head'], ['Released', 'head']]];
            foreach ($d['crossings'] as $x) {
                $rows[] = [(string) $x['reference'], (string) ($x['post_name'] ?? ''), $label($x['direction']), (string) ($x['declaration_no'] ?? ''), $label($x['status']), $when($x['released_at'] ?: $x['cleared_at'])];
            }
            $rows[] = [];
            $rows[] = [['Deliveries', 'section']];
            $rows[] = [['Delivery', 'head'], ['Shipment', 'head'], ['Receiver', 'head'], ['Where', 'head'], ['Status', 'head'], ['Delivered', 'head']];
            foreach ($d['deliveries'] as $dl) {
                $rows[] = [(string) $dl['delivery_code'], (string) ($dl['shipment_code'] ?? ''), trim($dl['recipient_name'] . ' ' . ($dl['recipient_phone'] ?? '')), (string) $dl['destination'], $label($dl['status']), $when($dl['delivered_at'])];
            }
            $book->sheet('Borders and deliveries', $rows, [18, 26, 16, 22, 16, 18]);
        }

        // --------------------------------------------------------------- money
        if ($d['commercial'] && $d['money'] !== null) {
            $m = $d['money'];
            $rows = [[['Item', 'head'], ['Basis', 'head'], ['Currency', 'head'], ['Amount', 'head']]];
            foreach ($m['lines'] as $line) {
                $rows[] = [$line['what'], $line['basis'], $line['currency'], [$line['amount'], 'money']];
            }
            foreach ($m['cost_lines'] as $line) {
                $rows[] = [$line['what'], 'Cost', $line['currency'], [-$line['amount'], 'money']];
            }
            $rows[] = [];
            foreach ($m['margin'] as $currency => $margin) {
                $rows[] = [['Margin so far', 'bold'], 'Revenue less costs, in ' . $currency, $currency, [(float) $margin, 'money']];
            }
            if ($m['recharge'] !== []) {
                $rows[] = [];
                $rows[] = [['Paid for the customer, recharged at cost', 'section']];
                foreach ($m['recharge'] as $line) {
                    $rows[] = [$line['what'], $line['status'], $line['currency'], [$line['amount'], 'money']];
                }
            }
            foreach ($d['invoices'] as $invoice) {
                $rows[] = [];
                $rows[] = [['Invoice ' . $invoice['invoice_number'] . ' · ' . $label($invoice['status']), 'section']];
                $rows[] = ['Total', 'Issued ' . ($invoice['issue_date'] ?? '') . ', due ' . ($invoice['due_date'] ?? ''), (string) $invoice['currency'], [(float) $invoice['total_amount'], 'money']];
                foreach ($invoice['payments'] as $payment) {
                    $rows[] = ['Payment ' . $payment['payment_code'], $when($payment['paid_at']) . ' · ' . $payment['method'], (string) ($payment['currency'] ?: $invoice['currency']), [(float) $payment['amount'], 'money']];
                }
                $rows[] = [['Balance', 'bold'], '', (string) $invoice['currency'], [max(0, (float) $invoice['total_amount'] - (float) $invoice['amount_paid']), 'money']];
            }
            $rows[] = [];
            $rows[] = [['Each currency is kept in its own books; nothing is converted.', 'subtitle']];
            $book->sheet('Money', $rows, [50, 40, 10, 16], 1);
        }

        return $book->output();
    }
}
