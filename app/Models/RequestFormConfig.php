<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The fields of each request form (section 8, "Main fields"), so one screen
 * can fill any of them: what goes at the top of the form, what each line
 * carries, and who is paid.
 *
 *   header  fields of the whole form (supplier, period, truck...)
 *   columns columns of each line
 *   payee   'line' (each line its own payee), 'header' (one payee for the form),
 *           'supplier' (the supplier chosen), or 'none'
 *   created_by_flow  the form is made by another screen (F1 by New trip...)
 */
final class RequestFormConfig
{
    public static function for(string $code): array
    {
        $forms = [
            // Trip money: what a trip is given for fuel, road toll and mileage,
            // each line paid to the driver or the station.
            'F1' => [
                'created_by_flow' => 'New trip',
                'header' => ['route_id', 'needed_on', 'non_standard_reason', 'notes'],
                'columns' => ['vehicle_id', 'trailer_id', 'driver_id', 'item_code', 'description', 'quantity', 'unit', 'amount', 'payee'],
                'labels' => ['item_code' => 'Cost', 'description' => 'Details', 'amount' => 'Amount'],
                'items' => ['fuel', 'road_toll', 'mileage', 'other'],
                'payee' => 'line',
            ],
            // Fuel order (the paper "Fuel request"): one supplier and route at the
            // top, then a line per truck — truck, trailer, driver, litres.
            'F2' => [
                'header' => ['supplier_id', 'route_id', 'needed_on', 'non_standard_reason', 'notes'],
                'columns' => ['vehicle_id', 'trailer_id', 'driver_id', 'quantity'],
                'labels' => ['quantity' => 'Fuel (L)'],
                'items' => ['fuel'],
                'item_default' => 'fuel',
                'unit' => 'L',
                'payee' => 'none',
                'supplier_label' => 'Station or sister company',
            ],
            'F3' => [
                'header' => ['vehicle_id', 'odometer', 'problem', 'needed_on', 'notes'],
                'columns' => ['item_code', 'item_id', 'description', 'quantity', 'unit', 'unit_price', 'amount'],
                'items' => ['part', 'labour', 'service', 'tyre', 'other'],
                'item_default' => 'part',
                'payee' => 'none',
            ],
            // Purchase order: what to buy from a supplier — a line per item with
            // its price, and the truck it is for.
            'F5' => [
                'header' => ['supplier_id', 'vehicle_id', 'needed_on', 'notes'],
                'columns' => ['item_id', 'description', 'quantity', 'unit', 'unit_price', 'amount', 'vehicle_id'],
                'labels' => ['item_id' => 'Item', 'unit_price' => 'Unit price', 'amount' => 'Amount', 'vehicle_id' => 'For truck'],
                'items' => ['part', 'tyre', 'fuel', 'other'],
                'item_default' => 'part',
                'payee' => 'none',
                'supplier_label' => 'Supplier',
            ],
            // Tyre change payment (the paper): a line per truck — truck, driver,
            // date, tyres, unit price, total — paid to one receiver.
            'F6' => [
                'header' => ['period_from', 'period_to', 'payee', 'notes'],
                'columns' => ['date', 'vehicle_id', 'driver_id', 'quantity', 'unit_price', 'amount'],
                'labels' => ['quantity' => 'Tyres', 'unit_price' => 'Unit price', 'amount' => 'Total'],
                'items' => ['tyre'],
                'item_default' => 'tyre',
                'unit' => 'tyre',
                'rate_type' => 'tyre',
                'payee' => 'header',
                'payee_label' => 'Tyre fitter (receiver)',
            ],
            // Crane payment (the paper): a line per lift — date, truck, container,
            // what was done and why, size, crane fee — paid to one operator.
            'F7' => [
                'header' => ['period_from', 'period_to', 'payee', 'notes'],
                'columns' => ['date', 'vehicle_id', 'container_no', 'operation', 'description', 'size', 'amount'],
                'labels' => ['description' => 'Work done & reason', 'amount' => 'Crane fee'],
                'items' => ['crane'],
                'item_default' => 'crane',
                'unit' => 'lift',
                'rate_type' => 'crane',
                'payee' => 'header',
                'payee_label' => 'Crane operator',
            ],
            // Shortage deduction: cargo that arrived short, and what it costs the
            // driver — a line per item short.
            'F9' => [
                'header' => ['driver_id', 'trip_id', 'customer_id', 'notes'],
                'columns' => ['description', 'quantity', 'unit', 'unit_price', 'amount'],
                'labels' => ['description' => 'What was short', 'unit_price' => 'Unit value', 'amount' => 'To deduct'],
                'items' => ['shortage'],
                'item_default' => 'shortage',
                'payee' => 'none',
            ],
            // Driver support: money a driver on the road needs now — what it is
            // for and how much, paid to the driver.
            'F10' => [
                'header' => ['driver_id', 'trip_id', 'support_type', 'location', 'notes'],
                'columns' => ['description', 'amount'],
                'labels' => ['description' => 'What is needed', 'amount' => 'Amount'],
                'items' => ['support'],
                'item_default' => 'support',
                'payee' => 'header',
                'payee_from' => 'driver',
                'payee_label' => 'Paid to',
            ],
            // Sister-company request: ask another ERI company for a truck, parts
            // or a service — a line per thing asked for.
            'F11' => [
                'header' => ['to_company_id', 'needed_on', 'notes'],
                'columns' => ['vehicle_id', 'trailer_id', 'item_code', 'description', 'quantity', 'unit'],
                'labels' => ['item_code' => 'Kind', 'description' => 'Details'],
                'items' => ['tyre', 'service', 'part', 'labour', 'other'],
                'item_default' => 'service',
                'payee' => 'none',
            ],
            // Supplier invoice (the paper invoice): what the supplier billed —
            // description, quantity, rate, amount, and the truck it was for.
            'F12' => [
                'header' => ['supplier_id', 'parent_request_id', 'invoice_no', 'invoice_date', 'notes'],
                'columns' => ['item_code', 'description', 'quantity', 'unit', 'unit_price', 'amount', 'vehicle_id'],
                'labels' => ['item_code' => 'Kind', 'unit_price' => 'Rate', 'amount' => 'Amount', 'vehicle_id' => 'For truck'],
                'items' => ['part', 'fuel', 'service', 'labour', 'tyre', 'crane', 'other'],
                'item_default' => 'other',
                'payee' => 'supplier',
                'supplier_label' => 'Supplier',
            ],
            // Payment request (other): anything else that must be paid — duty,
            // shipping, a service — a line per charge, paid to one payee.
            'F15' => [
                'header' => ['payee', 'customer_id', 'shipment_ref', 'needed_on', 'notes'],
                'columns' => ['item_code', 'description', 'vehicle_id', 'quantity', 'unit_price', 'amount'],
                'labels' => ['item_code' => 'Kind', 'vehicle_id' => 'For truck', 'unit_price' => 'Unit price', 'amount' => 'Amount'],
                'items' => ['duty', 'shipping', 'service', 'other'],
                'item_default' => 'other',
                'payee' => 'header',
                'payee_label' => 'Paid to',
            ],
        ];

        return ($forms[$code] ?? [
            'created_by_flow' => match ($code) {
                'F4' => 'the service request (store issue)',
                'F8' => 'the driver app (delivery note)',
                'F13' => 'Invoices',
                'F14' => 'the driver app (vehicle handover)',
                default => 'its own screen',
            },
            'header' => ['notes'],
            'columns' => ['description', 'quantity', 'unit'],
            'items' => ['other'],
            'payee' => 'none',
        ]) + ['created_by_flow' => null, 'item_default' => 'other', 'unit' => null, 'rate_type' => null, 'supplier_label' => 'Supplier', 'payee_label' => 'Paid to', 'payee_from' => null, 'labels' => []];
    }

    /** The forms a person can start from the Requests page. @return array<string, string> */
    public static function startable(): array
    {
        $options = [];
        foreach (RequestForms::formOptions(true) as $code => $label) {
            if (self::for($code)['created_by_flow'] === null) {
                $options[$code] = $label;
            }
        }

        return $options;
    }

    /** Everything the drop-downs of the form need, for the current company. */
    public static function lists(): array
    {
        $db = Database::connection();
        $company = static fn (string $table, string $column = 'company_id'): string => ($c = Company::condition($table, $column)) !== null ? " AND {$c}" : '';

        $options = static function (string $sql) use ($db): array {
            $rows = [];
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[(int) $row['id']] = $row;
            }

            return $rows;
        };

        return [
            'vehicles' => $options("SELECT id, plate_number AS label, trailer_id, assigned_driver_id FROM vehicles WHERE deleted_at IS NULL AND status <> 'inactive'" . $company('vehicles') . ' ORDER BY plate_number'),
            'trailers' => $options("SELECT id, trailer_number AS label FROM trailers WHERE deleted_at IS NULL AND status <> 'inactive'" . $company('trailers') . ' ORDER BY trailer_number'),
            'drivers' => $options("SELECT id, full_name AS label, payment_method, momo_number, momo_name FROM drivers WHERE deleted_at IS NULL AND status <> 'left'" . $company('drivers') . ' ORDER BY full_name'),
            'suppliers' => $options("SELECT id, supplier_name AS label, phone FROM suppliers WHERE deleted_at IS NULL AND status = 'active'" . $company('suppliers') . ' ORDER BY supplier_name'),
            'customers' => $options("SELECT id, customer_name AS label FROM customers WHERE deleted_at IS NULL AND status <> 'inactive'" . $company('customers') . ' ORDER BY customer_name'),
            'routes' => $options("SELECT id, CONCAT(route_code, ' — ', route_name) AS label FROM routes WHERE deleted_at IS NULL AND is_active = 1" . $company('routes') . ' ORDER BY route_code'),
            'trips' => $options("SELECT id, CONCAT(reference_code, ' ', COALESCE((SELECT plate_number FROM vehicles v WHERE v.id = trips.vehicle_id), '')) AS label, vehicle_id, driver_id, trailer_id FROM trips WHERE deleted_at IS NULL AND status NOT IN ('cancelled')" . $company('trips') . ' ORDER BY id DESC LIMIT 300'),
            'items' => $options("SELECT id, CONCAT(item_name, ' (', sku, ')') AS label, unit_of_measure AS unit, unit_cost FROM inventory_items WHERE deleted_at IS NULL" . $company('inventory_items') . ' ORDER BY item_name'),
            'rates' => $options("SELECT id, CONCAT(description, ' — ', currency, ' ', FORMAT(amount, 0)) AS label, rate_type, amount, currency, variant FROM service_rates WHERE deleted_at IS NULL AND is_active = 1 AND valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE())" . $company('service_rates') . ' ORDER BY rate_type, description'),
            'orders' => $options("SELECT id, CONCAT(form_code, ' ', COALESCE(request_no, id), ' — ', title) AS label, supplier_id FROM form_requests WHERE deleted_at IS NULL AND form_code IN ('F2', 'F5') AND status IN ('authorized', 'closed', 'paid')" . $company('form_requests') . ' ORDER BY id DESC LIMIT 200'),
            'companies' => array_map(static fn (string $name): array => ['label' => $name], array_diff_key(Company::options(), [\current_company_id() => true])),
        ];
    }

    public const SUPPORT_TYPES = ['money' => 'Money needed', 'breakdown' => 'Breakdown', 'accident' => 'Accident', 'border_delay' => 'Border delay', 'medical' => 'Medical', 'other' => 'Other'];
    public const POSITIONS = ['front_left' => 'Front left', 'front_right' => 'Front right', 'drive_left_outer' => 'Drive left (outer)', 'drive_left_inner' => 'Drive left (inner)', 'drive_right_outer' => 'Drive right (outer)', 'drive_right_inner' => 'Drive right (inner)', 'trailer' => 'Trailer', 'spare' => 'Spare'];
}
