<?php

declare(strict_types=1);

namespace Models;

/**
 * The single description of every logistics module: its table, its list query,
 * and every field with a label, type, width, help text and validation rule.
 *
 * Forms, detail pages, list tables, validation and persistence are all generated
 * from here, so a field is added in one place instead of six.
 *
 * Field spec keys:
 *   label     human label
 *   type      text|textarea|email|tel|number|decimal|money|date|datetime|select|relation|file|checkbox
 *   required  true when the field may not be blank
 *   width     bootstrap columns out of 12 (default 6)
 *   help      one line shown under the input
 *   options   value => label map for `select`
 *   relation  ['table','label','where','empty'] for `relation`
 *   auto      reference prefix; the code is generated when the user leaves it blank
 *   suffix    unit shown inside the input group (kg, L, km, %)
 *   readonly  set by the system, never typed by hand
 *   readonly_note  the word on the badge next to a readonly label (default "calculated")
 */
final class Schema
{
    public const VEHICLE_STATUS = ['available' => 'Available', 'on_trip' => 'On trip', 'maintenance' => 'Maintenance', 'inactive' => 'Inactive'];
    public const DRIVER_STATUS = ['available' => 'Available', 'on_trip' => 'On trip', 'off_duty' => 'Off duty', 'inactive' => 'Inactive'];
    public const TRIP_STATUS = ['requested' => 'Requested', 'approved' => 'Approved', 'loading' => 'Loading', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    public const REQUEST_STATUS = ['pending' => 'Pending', 'quoted' => 'Quoted', 'approved' => 'Approved', 'assigned' => 'Assigned', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
    public const DELIVERY_STATUS = ['loading' => 'Loading', 'in_transit' => 'In transit', 'at_destination' => 'At destination', 'delivered' => 'Delivered', 'failed' => 'Failed'];
    public const SHIPMENT_STATUS = ['draft' => 'Draft', 'booked' => 'Booked', 'loaded' => 'Loaded', 'cleared' => 'Cleared', 'released' => 'Released', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'returned' => 'Returned', 'cancelled' => 'Cancelled'];
    public const STOCK_STATUS = ['in_stock' => 'In stock', 'reorder' => 'Reorder', 'out_of_stock' => 'Out of stock'];
    public const PURCHASE_STATUS = ['draft' => 'Draft', 'quotation' => 'Quotation', 'approved' => 'Approved', 'received' => 'Received', 'rejected' => 'Rejected'];
    public const MAINTENANCE_STATUS = ['open' => 'Open', 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    public const APPROVAL_STATUS = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    public const INVOICE_STATUS = ['draft' => 'Draft', 'issued' => 'Issued', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'];
    public const PRIORITY = ['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'];
    public const SUPPORT_TYPES = ['money' => 'Money', 'breakdown' => 'Truck breakdown', 'accident' => 'Accident', 'police_border' => 'Police or border problem', 'documents' => 'Papers problem', 'health' => 'Health', 'other' => 'Other'];
    public const SUPPORT_URGENCY = ['normal' => 'Normal', 'urgent' => 'Urgent', 'emergency' => 'Emergency'];
    public const SUPPORT_STATUS = ['open' => 'Sent', 'in_progress' => 'Being handled', 'resolved' => 'Resolved', 'rejected' => 'Refused'];
    public const PRIVILEGES = [1 => 'Privileged - may switch roles', 2 => 'Standard'];
    public const ACCOUNT_TYPES = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'cost_of_sales' => 'Cost of sales', 'expense' => 'Expense'];

    /** Badge colour for a status value, so one status reads the same on every screen. */
    public const TONES = [
        'available' => 'success', 'active' => 'success', 'in_stock' => 'success', 'valid' => 'success',
        'completed' => 'success', 'delivered' => 'success', 'approved' => 'success', 'received' => 'success', 'cleared' => 'success', 'released' => 'success', 'passed' => 'success',
        'paid' => 'success', 'issued' => 'info', 'booked' => 'info', 'assigned' => 'info', 'scheduled' => 'info',
        'on_trip' => 'primary', 'in_transit' => 'primary', 'loading' => 'primary', 'loaded' => 'primary',
        'in_progress' => 'primary', 'quotation' => 'primary', 'partially_paid' => 'primary',
        'pending' => 'warning', 'requested' => 'warning', 'reorder' => 'warning', 'open' => 'warning', 'documents_pending' => 'warning', 'duties_pending' => 'warning', 'payment_pending' => 'warning',
        'draft' => 'secondary', 'off_duty' => 'secondary', 'on_hold' => 'warning', 'expiring' => 'warning', 'declaration_submitted' => 'info', 'under_review' => 'primary', 'inspection' => 'warning',
        'maintenance' => 'warning', 'overdue' => 'danger', 'failed' => 'danger', 'rejected' => 'danger',
        'cancelled' => 'danger', 'out_of_stock' => 'danger', 'expired' => 'danger', 'returned' => 'danger',
        'inactive' => 'secondary', 'locked' => 'danger',
        'invoiced' => 'info', 'recovered' => 'success', 'written_off' => 'secondary', 'reconciled' => 'success',
        'urgent' => 'danger', 'high' => 'warning', 'normal' => 'info', 'low' => 'secondary',
    ];

    /** @var array<string, array>|null */
    private static ?array $modules = null;

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function get(string $key): array
    {
        $module = self::all()[$key] ?? null;
        if ($module === null) {
            throw new \InvalidArgumentException("Unknown module: {$key}");
        }

        return $module;
    }

    /** Drop the built registry so a freshly edited reference list is read again. */
    public static function flush(): void
    {
        self::$modules = null;
    }

    /** @return array<string, array> */
    public static function all(): array
    {
        if (self::$modules !== null) {
            return self::$modules;
        }

        self::$modules = self::fleet() + self::transport() + self::documentPack() + self::clearance() + self::commercial() + self::finance() + self::warehouse() + self::administration();

        foreach (self::$modules as $key => $module) {
            self::$modules[$key]['key'] = $key;
            self::$modules[$key]['permission'] ??= $key;
            self::$modules[$key]['pk'] ??= 'id';
            self::$modules[$key]['soft_delete'] ??= true;
            self::$modules[$key]['filters'] ??= [];
            self::$modules[$key]['related'] ??= [];
            self::$modules[$key]['actions'] ??= [];
            self::$modules[$key]['links'] ??= [];
        }

        return self::$modules;
    }

    public static function tone(?string $value): string
    {
        return self::TONES[strtolower((string) $value)] ?? 'secondary';
    }

    public static function label(?string $value): string
    {
        return $value === null || $value === '' ? '' : ucfirst(str_replace('_', ' ', $value));
    }

    /** Every field of a module, flattened out of its sections. */
    public static function fields(string $key): array
    {
        return self::get($key)['fields'];
    }

    /** Fields the user actually types, i.e. everything the workflow does not own. */
    /**
     * The fields a role may actually write.
     *
     * `readonly` fields are calculated by the system and nobody types them.
     * `locked_for` is narrower: the field belongs to someone else's job. A driver
     * opens the delivery he is carrying and fills in the proof, the signature and
     * the time — but the address it was supposed to go to is not his to change,
     * so it is shown to him and refused from him.
     */
    public static function editableFields(string $key, ?string $role = null): array
    {
        return array_filter(self::fields($key), static function (array $field) use ($role): bool {
            if (!empty($field['readonly'])) {
                return false;
            }

            return $role === null || !in_array($role, $field['locked_for'] ?? [], true);
        });
    }

    /** The module as one role sees it: fields that are not theirs come back locked. */
    public static function forRole(string $key, string $role): array
    {
        $module = self::get($key);

        foreach ($module['fields'] as $name => $field) {
            if (in_array($role, $field['locked_for'] ?? [], true)) {
                $module['fields'][$name]['readonly'] = true;
                $module['fields'][$name]['readonly_note'] = $field['locked_note'] ?? 'set by the office';
            }
        }

        return $module;
    }

    // ----------------------------------------------------------------- fleet

    private static function fleet(): array
    {
        return [
            'vehicles' => [
                'title' => 'Vehicle fleet',
                'singular' => 'Vehicle',
                'kicker' => 'Fleet registry',
                'icon' => 'truck',
                'description' => 'Every vehicle with its capacity, ownership, compliance and availability.',
                'button' => 'Register vehicle',
                'table' => 'vehicles',
                'alias' => 'v',
                'code' => 'plate_number',
                'order' => 'v.plate_number',
                'joins' => 'LEFT JOIN drivers d ON d.id = v.assigned_driver_id',
                'select' => [
                    'v.id', 'v.plate_number', 'v.vehicle_type', "CONCAT_WS(' ', v.make, v.model) AS make_model",
                    'v.status', 'v.mileage', 'v.next_service_date', 'v.capacity_kg', 'v.has_cooling_unit',
                    'd.full_name AS assigned_driver',
                ],
                'search' => ['v.plate_number', 'v.vehicle_type', 'v.make', 'v.model', 'v.chassis_number'],
                'list' => [
                    'plate_number' => ['label' => 'Plate', 'type' => 'code'],
                    'vehicle_type' => ['label' => 'Type'],
                    'make_model' => ['label' => 'Make / model'],
                    'assigned_driver' => ['label' => 'Driver', 'empty' => 'Unassigned'],
                    'capacity_kg' => ['label' => 'Capacity', 'type' => 'decimal', 'suffix' => 'kg'],
                    'mileage' => ['label' => 'Odometer', 'type' => 'number', 'suffix' => 'km'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                    'next_service_date' => ['label' => 'Next service', 'type' => 'date'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'v.status', 'options' => self::VEHICLE_STATUS]],
                'sections' => [
                    ['title' => 'Identification', 'icon' => 'hash', 'hint' => 'How the vehicle is recognised on the road and in the registry.', 'fields' => ['plate_number', 'vehicle_type', 'make', 'model', 'manufacture_year', 'chassis_number']],
                    ['title' => 'Capacity and type', 'icon' => 'box', 'hint' => 'Used to check a vehicle can actually carry a shipment.', 'fields' => ['capacity_kg', 'capacity_m3', 'fuel_type', 'has_cooling_unit', 'ownership', 'acquired_on']],
                    ['title' => 'Assignment and status', 'icon' => 'user-check', 'hint' => 'Dispatch keeps these in step automatically when a trip starts or ends.', 'fields' => ['assigned_driver_id', 'status', 'mileage', 'next_service_date']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'plate_number' => ['label' => 'Plate number', 'type' => 'text', 'required' => true, 'width' => 4, 'placeholder' => 'RAC 482D', 'help' => 'Must be unique across the fleet.'],
                    'vehicle_type' => ['label' => 'Vehicle type', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => Lookup::options('vehicle_type')],
                    'make' => ['label' => 'Make', 'type' => 'text', 'width' => 4, 'placeholder' => 'Toyota'],
                    'model' => ['label' => 'Model', 'type' => 'text', 'width' => 4, 'placeholder' => 'Dyna'],
                    'manufacture_year' => ['label' => 'Year of manufacture', 'type' => 'number', 'width' => 4, 'min' => 1970, 'max' => 2100],
                    'chassis_number' => ['label' => 'Chassis number', 'type' => 'text', 'width' => 4, 'help' => 'Used for insurance and registration records.'],
                    'capacity_kg' => ['label' => 'Payload capacity', 'type' => 'decimal', 'width' => 4, 'suffix' => 'kg', 'help' => 'Dispatch warns when a shipment is heavier than this.'],
                    'capacity_m3' => ['label' => 'Volume capacity', 'type' => 'decimal', 'width' => 4, 'suffix' => 'm3'],
                    'fuel_type' => ['label' => 'Fuel type', 'type' => 'select', 'width' => 4, 'options' => ['diesel' => 'Diesel', 'petrol' => 'Petrol', 'electric' => 'Electric', 'hybrid' => 'Hybrid', 'cng' => 'CNG']],
                    'has_cooling_unit' => ['label' => 'Cold chain unit fitted', 'type' => 'checkbox', 'width' => 4, 'help' => 'Required before a cold-chain shipment can be loaded.'],
                    'ownership' => ['label' => 'Ownership', 'type' => 'select', 'width' => 4, 'options' => ['owned' => 'Owned', 'leased' => 'Leased', 'rented' => 'Rented', 'subcontracted' => 'Subcontracted']],
                    'acquired_on' => ['label' => 'Acquired on', 'type' => 'date', 'width' => 4],
                    'assigned_driver_id' => ['label' => 'Assigned driver', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'drivers', 'label' => 'full_name', 'where' => "status <> 'inactive'"], 'help' => 'A driver can hold only one vehicle at a time.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::VEHICLE_STATUS],
                    'mileage' => ['label' => 'Odometer reading', 'type' => 'number', 'width' => 4, 'suffix' => 'km', 'help' => 'Fuel entries update this automatically.'],
                    'next_service_date' => ['label' => 'Next service due', 'type' => 'date', 'width' => 4],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'related' => [
                    ['title' => 'Compliance documents', 'icon' => 'shield', 'permission' => 'vehicle_documents', 'module' => 'vehicle_documents',
                     'sql' => 'SELECT id, document_code, document_type, document_number, expires_on, status FROM vehicle_documents WHERE vehicle_id = :id AND deleted_at IS NULL ORDER BY expires_on',
                     'columns' => ['document_code' => 'Reference', 'document_type' => 'Type', 'document_number' => 'Number', 'expires_on' => 'Expires', 'status' => 'Status'],
                     'empty' => 'No insurance, inspection or registration record captured yet.'],
                    ['title' => 'Open maintenance', 'icon' => 'tool', 'permission' => 'maintenance', 'module' => 'maintenance',
                     'sql' => "SELECT id, work_order_code, service_name, priority, status, due_date FROM maintenance_orders WHERE vehicle_id = :id AND deleted_at IS NULL AND status <> 'completed' ORDER BY due_date",
                     'columns' => ['work_order_code' => 'Work order', 'service_name' => 'Service', 'priority' => 'Priority', 'status' => 'Status', 'due_date' => 'Due'],
                     'empty' => 'No open work orders.'],
                    ['title' => 'Recent fuel', 'icon' => 'droplet', 'permission' => 'fuel', 'module' => 'fuel',
                     'sql' => 'SELECT id, reference_code, station_name, litres, unit_price, purchased_at FROM fuel_records WHERE vehicle_id = :id AND deleted_at IS NULL ORDER BY purchased_at DESC LIMIT 8',
                     'columns' => ['reference_code' => 'Reference', 'station_name' => 'Station', 'litres' => 'Litres', 'unit_price' => 'Unit price', 'purchased_at' => 'Purchased'],
                     'empty' => 'No fuel purchases recorded.'],
                    ['title' => 'Recent trips', 'icon' => 'navigation', 'permission' => 'trips', 'module' => 'trips',
                     'sql' => 'SELECT id, reference_code, pickup_location, destination, status, departure_at FROM trips WHERE vehicle_id = :id AND deleted_at IS NULL ORDER BY id DESC LIMIT 8',
                     'columns' => ['reference_code' => 'Trip', 'pickup_location' => 'From', 'destination' => 'To', 'status' => 'Status', 'departure_at' => 'Departed'],
                     'empty' => 'This vehicle has not run a trip yet.'],
                ],
            ],

            'drivers' => [
                'title' => 'Drivers',
                'singular' => 'Driver',
                'kicker' => 'People and compliance',
                'icon' => 'user',
                'description' => 'Driver records, licence validity, emergency contacts and vehicle assignment.',
                'button' => 'Add driver',
                'table' => 'drivers',
                'alias' => 'd',
                'code' => 'full_name',
                'order' => 'd.full_name',
                'joins' => 'LEFT JOIN vehicles v ON v.assigned_driver_id = d.id LEFT JOIN users u ON u.id = d.user_id',
                'select' => ['d.id', 'd.full_name', 'd.phone', 'd.license_number', 'd.license_class', 'd.license_expiry', 'd.status', 'v.plate_number AS assigned_vehicle', 'u.email AS login_email'],
                'search' => ['d.full_name', 'd.phone', 'd.license_number', 'd.national_id'],
                'list' => [
                    'full_name' => ['label' => 'Driver', 'type' => 'code'],
                    'phone' => ['label' => 'Phone'],
                    'license_number' => ['label' => 'Licence'],
                    'license_class' => ['label' => 'Class'],
                    'license_expiry' => ['label' => 'Licence expiry', 'type' => 'expiry'],
                    'assigned_vehicle' => ['label' => 'Vehicle', 'empty' => 'Unassigned'],
                    'login_email' => ['label' => 'Login', 'empty' => 'No login'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'd.status', 'options' => self::DRIVER_STATUS]],
                'sections' => [
                    ['title' => 'Personal details', 'icon' => 'user', 'fields' => ['full_name', 'national_id', 'date_of_birth', 'phone', 'address']],
                    ['title' => 'Licence', 'icon' => 'credit-card', 'hint' => 'The dashboard raises an alert before the licence expires.', 'fields' => ['license_number', 'license_class', 'license_expiry']],
                    ['title' => 'Employment and assignment', 'icon' => 'briefcase', 'fields' => ['hired_on', 'user_id', 'status']],
                    ['title' => 'Emergency contact', 'icon' => 'phone-call', 'fields' => ['emergency_contact', 'emergency_phone']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'full_name' => ['label' => 'Full name', 'type' => 'text', 'required' => true, 'width' => 6],
                    'national_id' => ['label' => 'National ID', 'type' => 'text', 'width' => 6],
                    'date_of_birth' => ['label' => 'Date of birth', 'type' => 'date', 'width' => 4],
                    'phone' => ['label' => 'Phone', 'type' => 'tel', 'width' => 4, 'placeholder' => '+250 788 000 000'],
                    'address' => ['label' => 'Address', 'type' => 'text', 'width' => 4],
                    'license_number' => ['label' => 'Licence number', 'type' => 'text', 'required' => true, 'width' => 4, 'help' => 'Must be unique.'],
                    'license_class' => ['label' => 'Licence class', 'type' => 'select', 'width' => 4, 'options' => Lookup::options('licence_class')],
                    'license_expiry' => ['label' => 'Licence expiry', 'type' => 'date', 'required' => true, 'width' => 4],
                    'hired_on' => ['label' => 'Hired on', 'type' => 'date', 'width' => 4],
                    'user_id' => ['label' => 'Linked login account', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => "status = 'active' AND deleted_at IS NULL"], 'help' => 'Links this driver to a login so the driver dashboard shows their own trips.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::DRIVER_STATUS],
                    'emergency_contact' => ['label' => 'Emergency contact name', 'type' => 'text', 'width' => 6],
                    'emergency_phone' => ['label' => 'Emergency phone', 'type' => 'tel', 'width' => 6],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'related' => [
                    ['title' => 'Driver papers', 'icon' => 'file-text', 'permission' => 'driver_documents', 'module' => 'driver_documents',
                     'sql' => 'SELECT id, document_type, document_number, expiry_date, status FROM driver_documents WHERE driver_id = :id ORDER BY document_type',
                     'columns' => ['document_type' => 'Document', 'document_number' => 'Number', 'expiry_date' => 'Expires', 'status' => 'Status'],
                     'empty' => 'No passport, licence or permit on file yet.'],
                    ['title' => 'Recent trips', 'icon' => 'navigation', 'permission' => 'trips', 'module' => 'trips',
                     'sql' => 'SELECT id, reference_code, pickup_location, destination, status, departure_at FROM trips WHERE driver_id = :id AND deleted_at IS NULL ORDER BY id DESC LIMIT 10',
                     'columns' => ['reference_code' => 'Trip', 'pickup_location' => 'From', 'destination' => 'To', 'status' => 'Status', 'departure_at' => 'Departed'],
                     'empty' => 'No trips assigned yet.'],
                    ['title' => 'Fuel drawn', 'icon' => 'droplet', 'permission' => 'fuel', 'module' => 'fuel',
                     'sql' => 'SELECT id, reference_code, station_name, litres, purchased_at FROM fuel_records WHERE driver_id = :id AND deleted_at IS NULL ORDER BY purchased_at DESC LIMIT 8',
                     'columns' => ['reference_code' => 'Reference', 'station_name' => 'Station', 'litres' => 'Litres', 'purchased_at' => 'Purchased'],
                     'empty' => 'No fuel drawn by this driver.'],
                ],
            ],

            // The driver's own papers: what he shows at a border with his face on it.
            // They appear on his dashboard and open from his phone.
            'driver_documents' => [
                'title' => 'Driver papers', 'singular' => 'Driver paper', 'kicker' => 'Driver compliance', 'icon' => 'file-text',
                'description' => 'Passport, driving licence, national ID and cross-border permits for each driver, with a scan and an expiry date.',
                'button' => 'Add driver paper', 'table' => 'driver_documents', 'alias' => 'dd', 'code' => 'document_type', 'order' => 'dd.id DESC', 'soft_delete' => false,
                'joins' => 'INNER JOIN drivers d ON d.id = dd.driver_id',
                'scope' => ['driver' => 'dd.driver_id'],
                'select' => ['dd.id', 'd.full_name AS driver', 'dd.document_type', 'dd.document_number', 'dd.expiry_date', 'dd.status', 'dd.document_file'],
                'search' => ['d.full_name', 'dd.document_number'],
                'list' => [
                    'driver' => ['label' => 'Driver', 'type' => 'code'],
                    'document_type' => ['label' => 'Document', 'type' => 'label'],
                    'document_number' => ['label' => 'Number', 'empty' => '-'],
                    'expiry_date' => ['label' => 'Expires', 'type' => 'expiry'],
                    'document_file' => ['label' => 'Scan', 'type' => 'file'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'document_type' => ['label' => 'Document', 'column' => 'dd.document_type', 'options' => ['passport' => 'Passport', 'driving_licence' => 'Driving licence', 'driver_id' => 'National ID', 'permit' => 'Permit', 'other' => 'Other']],
                    'status' => ['label' => 'Status', 'column' => 'dd.status', 'options' => ['pending' => 'Pending', 'valid' => 'Valid', 'rejected' => 'Rejected', 'expired' => 'Expired']],
                ],
                'sections' => [
                    ['title' => 'Document', 'icon' => 'file-text', 'hint' => 'One of each kind per driver: adding a second passport replaces nothing, so edit the existing one instead.', 'fields' => ['driver_id', 'document_type', 'document_number', 'issue_date', 'expiry_date', 'status']],
                    ['title' => 'Scan', 'icon' => 'paperclip', 'hint' => 'The driver opens this from his dashboard at the border.', 'fields' => ['document_file']],
                ],
                'fields' => [
                    'driver_id' => ['label' => 'Driver', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'drivers', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'document_type' => ['label' => 'Document', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['passport' => 'Passport', 'driving_licence' => 'Driving licence', 'driver_id' => 'National ID', 'permit' => 'Cross-border permit', 'other' => 'Other']],
                    'document_number' => ['label' => 'Number', 'type' => 'text', 'width' => 4],
                    'issue_date' => ['label' => 'Issued on', 'type' => 'date', 'width' => 4],
                    'expiry_date' => ['label' => 'Expires on', 'type' => 'date', 'width' => 4],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['pending' => 'Pending', 'valid' => 'Valid', 'rejected' => 'Rejected', 'expired' => 'Expired']],
                    'document_file' => ['label' => 'Scan (PDF or photo)', 'type' => 'file', 'width' => 6],
                ],
            ],

            'vehicle_documents' => [
                'title' => 'Vehicle documents',
                'singular' => 'Document',
                'kicker' => 'Compliance control',
                'icon' => 'shield',
                'description' => 'Insurance, inspection, registration and road licence records with expiry alerts.',
                'button' => 'Add document',
                'table' => 'vehicle_documents',
                'alias' => 'vd',
                'code' => 'document_code',
                'order' => 'vd.expires_on',
                'joins' => 'LEFT JOIN vehicles v ON v.id = vd.vehicle_id',
                'select' => ['vd.id', 'vd.document_code', 'v.plate_number AS vehicle', 'vd.document_type', 'vd.document_number', 'vd.provider_name', 'vd.expires_on', 'vd.cost', 'vd.status', 'vd.document_file'],
                'search' => ['vd.document_code', 'vd.document_number', 'vd.provider_name', 'v.plate_number'],
                'list' => [
                    'document_code' => ['label' => 'Reference', 'type' => 'code'],
                    'vehicle' => ['label' => 'Vehicle'],
                    'document_type' => ['label' => 'Type', 'type' => 'label'],
                    'document_number' => ['label' => 'Number'],
                    'provider_name' => ['label' => 'Provider'],
                    'expires_on' => ['label' => 'Expires', 'type' => 'expiry'],
                    'cost' => ['label' => 'Cost', 'type' => 'money'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'vd.status', 'options' => ['valid' => 'Valid', 'expiring' => 'Expiring', 'expired' => 'Expired', 'cancelled' => 'Cancelled']],
                    'document_type' => ['label' => 'Type', 'column' => 'vd.document_type', 'options' => ['insurance' => 'Insurance', 'inspection' => 'Inspection', 'registration' => 'Registration', 'road_license' => 'Road licence', 'permit' => 'Permit', 'other' => 'Other']],
                ],
                'sections' => [
                    ['title' => 'Document', 'icon' => 'file', 'fields' => ['document_code', 'vehicle_id', 'document_type', 'document_number']],
                    ['title' => 'Validity', 'icon' => 'calendar', 'hint' => 'Status is recalculated from the expiry date every time the record is saved.', 'fields' => ['provider_name', 'issued_on', 'expires_on', 'cost', 'status']],
                    ['title' => 'Attachment', 'icon' => 'paperclip', 'fields' => ['document_file', 'notes']],
                ],
                'fields' => [
                    'document_code' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'DOC', 'help' => 'Leave blank to generate one.'],
                    'vehicle_id' => ['label' => 'Vehicle', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'vehicles', 'label' => 'plate_number', 'where' => 'deleted_at IS NULL']],
                    'document_type' => ['label' => 'Document type', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['insurance' => 'Insurance', 'inspection' => 'Inspection', 'registration' => 'Registration', 'road_license' => 'Road licence', 'permit' => 'Permit', 'other' => 'Other']],
                    'document_number' => ['label' => 'Document number', 'type' => 'text', 'width' => 4],
                    'provider_name' => ['label' => 'Issuer / insurer', 'type' => 'text', 'width' => 4, 'placeholder' => 'SONARWA'],
                    'issued_on' => ['label' => 'Issued on', 'type' => 'date', 'width' => 4],
                    'expires_on' => ['label' => 'Expires on', 'type' => 'date', 'required' => true, 'width' => 4, 'help' => 'Drives the compliance alert on the fleet dashboard.'],
                    'cost' => ['label' => 'Cost', 'type' => 'money', 'width' => 4],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 4, 'options' => ['valid' => 'Valid', 'expiring' => 'Expiring', 'expired' => 'Expired', 'cancelled' => 'Cancelled']],
                    'document_file' => ['label' => 'Scanned document', 'type' => 'file', 'width' => 6, 'help' => 'PDF or image, up to 5 MB.'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
            ],

            'maintenance' => [
                'title' => 'Maintenance',
                'singular' => 'Work order',
                'kicker' => 'Fleet health',
                'icon' => 'tool',
                'description' => 'Preventive and corrective work orders with estimated versus actual cost and parts used.',
                'button' => 'Create work order',
                'table' => 'maintenance_orders',
                'alias' => 'm',
                'code' => 'work_order_code',
                'order' => 'm.id DESC',
                'joins' => 'LEFT JOIN vehicles v ON v.id = m.vehicle_id LEFT JOIN users a ON a.id = m.approved_by',
                'select' => ['m.id', 'm.work_order_code', 'v.plate_number AS vehicle', 'm.service_name', 'm.maintenance_type', 'm.provider_name', 'm.priority', 'm.estimated_cost', 'm.actual_cost', 'm.status', 'm.due_date', 'm.completed_at', 'a.full_name AS approver'],
                'search' => ['m.work_order_code', 'm.service_name', 'm.provider_name', 'v.plate_number'],
                'list' => [
                    'work_order_code' => ['label' => 'Work order', 'type' => 'code'],
                    'vehicle' => ['label' => 'Vehicle'],
                    'service_name' => ['label' => 'Service'],
                    'maintenance_type' => ['label' => 'Type', 'type' => 'label'],
                    'priority' => ['label' => 'Priority', 'type' => 'badge'],
                    'estimated_cost' => ['label' => 'Estimated', 'type' => 'money'],
                    'actual_cost' => ['label' => 'Actual', 'type' => 'money'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                    'due_date' => ['label' => 'Due', 'type' => 'expiry'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'm.status', 'options' => self::MAINTENANCE_STATUS],
                    'priority' => ['label' => 'Priority', 'column' => 'm.priority', 'options' => self::PRIORITY],
                ],
                'sections' => [
                    ['title' => 'Work order', 'icon' => 'clipboard', 'fields' => ['work_order_code', 'vehicle_id', 'service_name', 'maintenance_type', 'priority']],
                    ['title' => 'Job card', 'icon' => 'tool', 'hint' => 'What the driver reported, what the mechanic found, and what was done.', 'fields' => ['workshop', 'mechanic_id', 'reported_by_driver_id', 'complaint', 'diagnosis', 'work_done', 'labour_hours']],
                    ['title' => 'Provider and cost', 'icon' => 'dollar-sign', 'hint' => 'Actual cost is recalculated from the parts and labour lines when they exist. Parts picked from stock are priced from the stock card and leave the store when the cost is approved.', 'fields' => ['provider_name', 'supplier_id', 'estimated_cost', 'actual_cost', 'odometer_reading']],
                    ['title' => 'Schedule', 'icon' => 'calendar', 'hint' => 'The next service written here is set on the vehicle when the job is approved.', 'fields' => ['status', 'due_date', 'started_at', 'completed_at', 'next_service_km', 'next_service_date']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'work_order_code' => ['label' => 'Work order reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'MNT'],
                    'vehicle_id' => ['label' => 'Vehicle', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'vehicles', 'label' => 'plate_number', 'where' => 'deleted_at IS NULL']],
                    'service_name' => ['label' => 'Service', 'type' => 'text', 'required' => true, 'width' => 4, 'placeholder' => 'Cold-chain unit inspection'],
                    'maintenance_type' => ['label' => 'Maintenance type', 'type' => 'select', 'width' => 4, 'options' => ['preventive' => 'Preventive', 'corrective' => 'Corrective', 'inspection' => 'Inspection', 'tyre' => 'Tyre', 'bodywork' => 'Bodywork', 'emergency' => 'Emergency']],
                    'priority' => ['label' => 'Priority', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::PRIORITY],
                    'workshop' => ['label' => 'Done where', 'type' => 'select', 'width' => 3, 'options' => ['in_house' => 'Our own workshop', 'external' => 'Outside garage']],
                    'mechanic_id' => ['label' => 'Mechanic', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => "status = 'active' AND deleted_at IS NULL"]],
                    'reported_by_driver_id' => ['label' => 'Reported by driver', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'drivers', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'labour_hours' => ['label' => 'Labour hours', 'type' => 'decimal', 'width' => 3, 'suffix' => 'h'],
                    'complaint' => ['label' => 'Complaint (what the driver reported)', 'type' => 'textarea', 'width' => 4, 'placeholder' => 'Brakes pulling left at speed'],
                    'diagnosis' => ['label' => 'Diagnosis (what was found)', 'type' => 'textarea', 'width' => 4],
                    'work_done' => ['label' => 'Work done', 'type' => 'textarea', 'width' => 4],
                    'supplier_id' => ['label' => 'Garage on file', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'suppliers', 'label' => 'supplier_name', 'where' => 'deleted_at IS NULL'], 'help' => 'For an outside garage: its bill is then recorded under Supplier bills and linked to this work order.'],
                    'next_service_km' => ['label' => 'Next service at', 'type' => 'number', 'width' => 3, 'suffix' => 'km'],
                    'next_service_date' => ['label' => 'Next service on', 'type' => 'date', 'width' => 3],
                    'provider_name' => ['label' => 'Service provider', 'type' => 'text', 'width' => 4],
                    'estimated_cost' => ['label' => 'Estimated cost', 'type' => 'money', 'required' => true, 'width' => 4],
                    'actual_cost' => ['label' => 'Actual cost', 'type' => 'money', 'width' => 4, 'help' => 'Filled in when the job is completed.'],
                    'odometer_reading' => ['label' => 'Odometer at service', 'type' => 'number', 'width' => 4, 'suffix' => 'km'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::MAINTENANCE_STATUS],
                    'due_date' => ['label' => 'Due date', 'type' => 'date', 'required' => true, 'width' => 4],
                    'started_at' => ['label' => 'Started at', 'type' => 'datetime', 'width' => 4],
                    'completed_at' => ['label' => 'Completed at', 'type' => 'datetime', 'width' => 4],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'maintenance_parts',
                    'parent' => 'maintenance_id',
                    'title' => 'Parts and labour',
                    'total_column' => 'actual_cost',
                    'hint' => 'Pick "From stock" for a part off our own shelves: leave the cost at 0 and it is priced from the stock card.',
                    'columns' => [
                        'line_type' => ['label' => 'Type', 'type' => 'select', 'default' => 'part', 'options' => ['part' => 'Part', 'labour' => 'Labour', 'service' => 'Service', 'consumable' => 'Consumable']],
                        'item_id' => ['label' => 'From stock', 'type' => 'relation', 'relation' => ['table' => 'inventory_items', 'label' => 'item_name', 'where' => 'deleted_at IS NULL']],
                        'part_name' => ['label' => 'Description', 'type' => 'text', 'required' => true],
                        'part_number' => ['label' => 'Part number', 'type' => 'text'],
                        'quantity' => ['label' => 'Qty', 'type' => 'decimal'],
                        'unit_cost' => ['label' => 'Unit cost', 'type' => 'money'],
                    ],
                ],
                'actions' => ['approve' => ['label' => 'Approve cost', 'to' => 'completed'], 'reject' => ['label' => 'Cancel order', 'to' => 'cancelled']],
                'links' => [
                    ['label' => 'Record garage bill', 'icon' => 'inbox', 'tone' => 'outline-primary',
                     'permission' => 'supplier_bills', 'ability' => 'create',
                     'route' => ['supplier_bills', 'create'], 'carry' => ['maintenance_id' => 'id', 'supplier_id' => 'supplier_id'],
                     'hint' => 'The outside garage\'s invoice for this job, so it is paid against the work order.'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------- transport

    private static function transport(): array
    {
        return [
            'requests' => [
                'title' => 'Transport requests',
                'singular' => 'Request',
                'kicker' => 'Demand and approvals',
                'icon' => 'clipboard',
                'description' => 'Transport demand from the business, reviewed and approved before a trip is planned.',
                'button' => 'New request',
                'table' => 'transport_requests',
                'alias' => 'r',
                'code' => 'reference_code',
                'order' => 'r.id DESC',
                'joins' => 'LEFT JOIN users u ON u.id = r.requester_id LEFT JOIN customers c ON c.id = r.customer_id LEFT JOIN trips t ON t.id = r.trip_id LEFT JOIN users ap ON ap.id = r.approved_by',
                'select' => ['r.id', 'r.reference_code', 'r.requested_by_contact', 'u.full_name AS requester', 'c.customer_name AS customer', 'r.pickup_location', 'r.destination', 'r.required_date', 'r.priority', 'r.status', 'r.weight_kg', 't.reference_code AS trip', 'ap.full_name AS approver'],
                'search' => ['r.reference_code', 'r.pickup_location', 'r.destination', 'r.cargo_description', 'c.customer_name'],
                'list' => [
                    'reference_code' => ['label' => 'Request', 'type' => 'code'],
                    'customer' => ['label' => 'Customer', 'empty' => 'Internal'],
                    'requested_by_contact' => ['label' => 'Asked for by', 'empty' => 'Not named'],
                    'requester' => ['label' => 'Logged by'],
                    'pickup_location' => ['label' => 'From'],
                    'destination' => ['label' => 'To'],
                    'required_date' => ['label' => 'Required', 'type' => 'expiry'],
                    'priority' => ['label' => 'Priority', 'type' => 'badge'],
                    'trip' => ['label' => 'Trip', 'empty' => 'Not assigned'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'r.status', 'options' => self::REQUEST_STATUS],
                    'priority' => ['label' => 'Priority', 'column' => 'r.priority', 'options' => self::PRIORITY],
                ],
                'sections' => [
                    ['title' => 'Request', 'icon' => 'clipboard', 'fields' => ['reference_code', 'customer_id', 'price_offer_id', 'requested_by_contact', 'requester_id', 'priority']],
                    ['title' => 'Customer order (LPO)', 'icon' => 'file', 'hint' => 'The customer\'s purchase order. Its number is carried onto the shipment, the delivery note and the invoice, so nobody types it again.', 'fields' => ['lpo_number', 'lpo_file']],
                    ['title' => 'Route and date', 'icon' => 'map-pin', 'fields' => ['origin_warehouse_id', 'destination_warehouse_id', 'pickup_location', 'destination', 'required_date']],
                    ['title' => 'The quotation', 'icon' => 'tag', 'hint' => 'Worked out from the rate card. For a request from an accepted price offer, it is filled in on save.', 'fields' => ['rate_card_id', 'quoted_amount', 'currency', 'quote_basis', 'accepted_by', 'accepted_at']],
                    ['title' => 'Cargo', 'icon' => 'package', 'hint' => 'Used to pick a vehicle with enough capacity.', 'fields' => ['cargo_description', 'weight_kg', 'packages_count', 'stock_item_id', 'stock_quantity']],
                    ['title' => 'Decision', 'icon' => 'check-circle', 'hint' => 'Use the Approve and Reject buttons instead of editing the status by hand.', 'fields' => ['status', 'notes']],
                ],
                'fields' => [
                    'reference_code' => ['label' => 'Request reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'REQ'],
                    'requester_id' => ['label' => 'Logged by', 'type' => 'relation', 'width' => 3, 'readonly' => true, 'readonly_note' => 'you', 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL'], 'help' => 'Recorded, not chosen.'],
                    'price_offer_id' => ['label' => 'From price offer', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'price_offers', 'label' => 'offer_code', 'where' => "deleted_at IS NULL AND status = 'accepted'"]],
                    'requested_by_contact' => ['label' => 'Asked for by', 'type' => 'text', 'width' => 3, 'placeholder' => 'Peter Kamau', 'help' => 'Who at the customer asked.'],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL'], 'help' => 'Leave blank for an internal movement.'],
                    'priority' => ['label' => 'Priority', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::PRIORITY],
                    'lpo_number' => ['label' => 'LPO / customer order number', 'type' => 'text', 'width' => 6, 'placeholder' => 'LPO-2026-0412'],
                    'lpo_file' => ['label' => 'LPO document', 'type' => 'file', 'width' => 6, 'help' => 'Scan or PDF of the customer\'s order.'],
                    'pickup_location' => ['label' => 'Pickup location', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => 'Dar es Salaam'],
                    'origin_warehouse_id' => ['label' => 'Collect from depot', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"], ],
                    'destination' => ['label' => 'Destination', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => 'Kigali'],
                    'destination_warehouse_id' => ['label' => 'Deliver to depot', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"], ],
                    'required_date' => ['label' => 'Required date', 'type' => 'date', 'required' => true, 'width' => 4],
                    'cargo_description' => ['label' => 'Cargo description', 'type' => 'text', 'width' => 6, 'placeholder' => 'Fortified maize flour, 40 sacks'],
                    'stock_item_id' => ['label' => 'Item from our stock', 'type' => 'relation', 'width' => 5, 'relation' => ['table' => 'inventory_items', 'label' => 'item_name', 'where' => 'deleted_at IS NULL', 'depends_on' => ['field' => 'origin_warehouse_id', 'column' => 'warehouse_id'], 'empty' => 'Not from our stock'], 'help' => 'Only when the customer is ordering goods we hold.'],
                    'stock_quantity' => ['label' => 'Quantity wanted', 'type' => 'decimal', 'width' => 3],
                    'weight_kg' => ['label' => 'Estimated weight', 'type' => 'decimal', 'width' => 3, 'suffix' => 'kg'],
                    'packages_count' => ['label' => 'Packages', 'type' => 'number', 'width' => 3],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::REQUEST_STATUS],
                    'rate_card_id' => ['label' => 'Price chosen (rate card)', 'type' => 'relation', 'width' => 12, 'relation' => ['table' => 'rate_cards', 'label' => 'rate_code', 'where' => "deleted_at IS NULL AND status = 'active'"]],
                    'quoted_amount' => ['label' => 'Quoted price', 'type' => 'money', 'width' => 3, 'readonly' => true, 'readonly_note' => 'from the rate card'],
                    'currency' => ['label' => 'Quoted in', 'type' => 'select', 'width' => 2, 'options' => Currency::options()],
                    'quote_basis' => ['label' => 'How it was worked out', 'type' => 'text', 'width' => 7, 'readonly' => true, 'readonly_note' => 'the working'],
                    'accepted_by' => ['label' => 'Accepted by', 'type' => 'text', 'width' => 4, 'placeholder' => 'Who at the customer agreed it'],
                    'accepted_at' => ['label' => 'Accepted on', 'type' => 'datetime', 'width' => 4],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'related' => [
                    ['title' => 'Shipments on this request', 'icon' => 'package', 'permission' => 'shipments', 'module' => 'shipments',
                     'sql' => 'SELECT id, shipment_code, cargo_description, packages_count, weight_kg, status FROM shipments WHERE request_id = :id AND deleted_at IS NULL ORDER BY id',
                     'columns' => ['shipment_code' => 'Shipment', 'cargo_description' => 'Cargo', 'packages_count' => 'Packages', 'weight_kg' => 'Weight', 'status' => 'Status'],
                     'empty' => 'No shipment booked against this request yet.'],
                ],
                'actions' => [
                    'quote' => ['label' => 'Quote the customer', 'to' => 'quoted', 'from' => ['pending'], 'tone' => 'primary'],
                    'approve' => ['label' => 'Customer accepted', 'to' => 'approved', 'from' => ['pending', 'quoted'], 'tone' => 'success',
                                  // From an accepted offer the customer has agreed already: this is our own approval.
                                  'label_when_set' => ['price_offer_id' => 'Approve request']],
                    'reject' => ['label' => 'Reject', 'to' => 'rejected', 'from' => ['pending', 'quoted'], 'tone' => 'danger', 'reason' => true],
                ],
                // Not a status change: it opens the trip form with this request
                // already in it. The request turns Assigned when that trip is saved.
                'links' => [
                    ['label' => 'Load on a planned trip', 'icon' => 'package', 'tone' => 'primary',
                     'permission' => 'shipments', 'ability' => 'create', 'when' => ['status' => ['approved', 'assigned']],
                     'route' => ['shipments', 'create'], 'carry' => ['request_id' => 'id'],
                     'hint' => 'Books this load onto a trip that is already going that way. One trip carries several customers.'],
                    ['label' => 'Plan a new trip', 'icon' => 'navigation', 'tone' => 'outline-primary',
                     'permission' => 'trips', 'ability' => 'create', 'when' => ['status' => ['approved']],
                     'route' => ['trips', 'create'], 'carry' => ['request_id' => 'id'],
                     'hint' => 'Only when nothing already planned covers this route.'],
                ],
            ],

            'trips' => [
                'title' => 'Trips',
                'singular' => 'Trip',
                'kicker' => 'Dispatch and execution',
                'icon' => 'navigation',
                'description' => 'Planned and running trips with their route, stops, vehicle, driver and on-time result.',
                'button' => 'Create trip',
                'table' => 'trips',
                'alias' => 't',
                'code' => 'reference_code',
                'order' => 't.id DESC',
                // Moving a trip to another request has to release the old one.
                'track_changes' => true,
                'joins' => 'LEFT JOIN vehicles v ON v.id = t.vehicle_id LEFT JOIN drivers d ON d.id = t.driver_id LEFT JOIN customers c ON c.id = t.customer_id LEFT JOIN transport_requests rq ON rq.id = t.request_id',
                'select' => ['t.id', 't.reference_code', 'rq.reference_code AS request', 'c.customer_name AS customer', 't.trip_type', 't.pickup_location', 't.destination', 't.planned_arrival_at', 't.departure_at', 't.arrival_at', 'v.plate_number AS vehicle', 'd.full_name AS driver', 't.status'],
                'search' => ['t.reference_code', 't.pickup_location', 't.destination', 'v.plate_number', 'd.full_name'],
                'scope' => ['driver' => 't.driver_id'],
                'list' => [
                    'reference_code' => ['label' => 'Trip', 'type' => 'code'],
                    'customer' => ['label' => 'Customer', 'empty' => 'Internal'],
                    'pickup_location' => ['label' => 'From'],
                    'destination' => ['label' => 'To'],
                    'vehicle' => ['label' => 'Vehicle', 'empty' => 'Unassigned'],
                    'driver' => ['label' => 'Driver', 'empty' => 'Unassigned'],
                    'planned_arrival_at' => ['label' => 'Planned arrival', 'type' => 'datetime'],
                    'arrival_at' => ['label' => 'Actual arrival', 'type' => 'datetime'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 't.status', 'options' => self::TRIP_STATUS],
                    'trip_type' => ['label' => 'Type', 'column' => 't.trip_type', 'options' => ['delivery' => 'Delivery', 'collection' => 'Collection', 'transfer' => 'Transfer', 'return' => 'Return', 'shuttle' => 'Shuttle']],
                ],
                'sections' => [
                    ['title' => 'Trip', 'icon' => 'navigation', 'fields' => ['reference_code', 'request_id', 'customer_id', 'trip_type']],
                    ['title' => 'Route', 'icon' => 'map-pin', 'hint' => 'Add intermediate drops under Stops on the trip page. When this truck covers only part of the way, link it to the journey as one of its legs.', 'fields' => ['pickup_location', 'destination', 'cargo_summary', 'journey_id', 'leg_no']],
                    ['title' => 'Plan', 'icon' => 'calendar', 'hint' => 'Planned arrival is what on-time performance is measured against.', 'fields' => ['planned_departure_at', 'planned_arrival_at']],
                    ['title' => 'Resources', 'icon' => 'truck', 'hint' => 'Dispatch blocks a vehicle or driver already committed to an overlapping trip.', 'fields' => ['vehicle_id', 'driver_id', 'status']],
                    ['title' => 'Actual execution', 'icon' => 'clock', 'fields' => ['departure_at', 'arrival_at']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'reference_code' => ['label' => 'Trip reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'TRP', 'locked_for' => ['driver']],
                    'request_id' => ['label' => 'Fulfils request', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'transport_requests', 'label' => 'reference_code', 'where' => "status IN ('approved','assigned') AND deleted_at IS NULL"], 'help' => 'Only for a truck hired by one customer. A scheduled run is left blank and customers come aboard as shipments.', 'locked_for' => ['driver']],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL'], 'locked_for' => ['driver']],
                    'trip_type' => ['label' => 'Trip type', 'type' => 'select', 'width' => 4, 'options' => ['delivery' => 'Delivery', 'collection' => 'Collection', 'transfer' => 'Transfer', 'return' => 'Return', 'shuttle' => 'Shuttle'], 'locked_for' => ['driver']],
                    'pickup_location' => ['label' => 'Pickup location', 'type' => 'text', 'required' => true, 'width' => 6, 'locked_for' => ['driver']],
                    'destination' => ['label' => 'Final destination', 'type' => 'text', 'required' => true, 'width' => 6, 'locked_for' => ['driver']],
                    'cargo_summary' => ['label' => 'Cargo summary', 'type' => 'text', 'width' => 12, 'placeholder' => '40 sacks maize flour, 12 cold-chain crates', 'locked_for' => ['driver']],
                    'journey_id' => ['label' => 'Leg of journey', 'type' => 'relation', 'width' => 8, 'relation' => ['table' => 'journeys', 'label' => 'journey_code', 'where' => "deleted_at IS NULL AND status <> 'cancelled'"], 'help' => 'Only when the move takes more than one truck, e.g. Dar es Salaam to Rusumo, then Rusumo to Kigali.', 'locked_for' => ['driver']],
                    'leg_no' => ['label' => 'Leg number', 'type' => 'number', 'width' => 4, 'help' => '1 for the first truck, 2 for the next…', 'locked_for' => ['driver']],
                    'planned_departure_at' => ['label' => 'Planned departure', 'type' => 'datetime', 'width' => 6, 'locked_for' => ['driver']],
                    'planned_arrival_at' => ['label' => 'Planned arrival', 'type' => 'datetime', 'width' => 6, 'help' => 'Leave blank if the trip has no committed arrival time.', 'locked_for' => ['driver']],
                    'vehicle_id' => ['label' => 'Vehicle', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'vehicles', 'label' => 'plate_number', 'where' => "status <> 'inactive' AND deleted_at IS NULL"], 'locked_for' => ['driver']],
                    'driver_id' => ['label' => 'Driver', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'drivers', 'label' => 'full_name', 'where' => "status <> 'inactive' AND deleted_at IS NULL"], 'locked_for' => ['driver']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::TRIP_STATUS, 'locked_for' => ['driver']],
                    'departure_at' => ['label' => 'Actual departure', 'type' => 'datetime', 'width' => 6],
                    'arrival_at' => ['label' => 'Actual arrival', 'type' => 'datetime', 'width' => 6],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'trip_stops',
                    'parent' => 'trip_id',
                    'title' => 'Stops on this route',
                    'hint' => 'Where the truck stops between the two ends: depots it loads or drops at, borders it clears, towns it rests in. Optional, and a straight run needs none.',
                    'sequence' => 'stop_sequence',
                    'columns' => [
                        'stop_type' => ['label' => 'Type', 'type' => 'select', 'options' => ['pickup' => 'Pickup', 'dropoff' => 'Drop-off', 'waypoint' => 'Waypoint', 'checkpoint' => 'Checkpoint']],
                        'location_name' => ['label' => 'Location', 'type' => 'text', 'required' => true],
                        'contact_name' => ['label' => 'Contact', 'type' => 'text'],
                        'contact_phone' => ['label' => 'Phone', 'type' => 'tel'],
                        'planned_arrival_at' => ['label' => 'Planned arrival', 'type' => 'datetime'],
                        'actual_arrival_at' => ['label' => 'Actual arrival', 'type' => 'datetime'],
                        'arrival_mileage' => ['label' => 'Km on the clock', 'type' => 'number'],
                        'departed_at' => ['label' => 'Left at', 'type' => 'datetime'],
                        'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'arrived' => 'Arrived', 'completed' => 'Left', 'skipped' => 'Skipped', 'failed' => 'Failed']],
                    ],
                ],
                'related' => [
                    ['title' => 'Shipments on board', 'icon' => 'package', 'permission' => 'shipments', 'module' => 'shipments',
                     'sql' => 'SELECT id, shipment_code, cargo_description, cargo_type, packages_count, weight_kg, status FROM shipments WHERE trip_id = :id AND deleted_at IS NULL ORDER BY id',
                     'columns' => ['shipment_code' => 'Shipment', 'cargo_description' => 'Cargo', 'cargo_type' => 'Type', 'packages_count' => 'Packages', 'weight_kg' => 'Weight', 'status' => 'Status'],
                     'empty' => 'No shipment loaded on this trip.'],
                    ['title' => 'Deliveries', 'icon' => 'check-square', 'permission' => 'deliveries', 'module' => 'deliveries',
                     'sql' => 'SELECT id, delivery_code, recipient_name, destination, status, delivered_at FROM deliveries WHERE trip_id = :id AND deleted_at IS NULL ORDER BY id',
                     'columns' => ['delivery_code' => 'Delivery', 'recipient_name' => 'Recipient', 'destination' => 'Destination', 'status' => 'Status', 'delivered_at' => 'Delivered'],
                     'empty' => 'No delivery recorded yet.'],
                    ['title' => 'Trip costs', 'icon' => 'credit-card', 'permission' => 'expenses', 'module' => 'expenses',
                     'sql' => 'SELECT id, reference_code, category, amount, status, expense_date FROM expenses WHERE trip_id = :id AND deleted_at IS NULL ORDER BY expense_date DESC',
                     'columns' => ['reference_code' => 'Reference', 'category' => 'Category', 'amount' => 'Amount', 'status' => 'Status', 'expense_date' => 'Date'],
                     'empty' => 'No cost booked against this trip.'],
                    ['title' => 'Fuel on this trip', 'icon' => 'droplet', 'permission' => 'fuel', 'module' => 'fuel',
                     'sql' => 'SELECT id, reference_code, station_name, litres, unit_price, purchased_at FROM fuel_records WHERE trip_id = :id AND deleted_at IS NULL ORDER BY purchased_at',
                     'columns' => ['reference_code' => 'Reference', 'station_name' => 'Station', 'litres' => 'Litres', 'unit_price' => 'Unit price', 'purchased_at' => 'Purchased'],
                     'empty' => 'No fuel booked against this trip.'],
                ],
                'actions' => [
                    'dispatch' => ['label' => 'Dispatch trip', 'to' => 'in_transit', 'from' => ['requested', 'approved', 'loading'], 'tone' => 'primary'],
                    'complete' => ['label' => 'Mark delivered', 'to' => 'delivered', 'from' => ['loading', 'in_transit'], 'tone' => 'success'],
                    'reject' => ['label' => 'Cancel trip', 'to' => 'cancelled', 'from' => ['requested', 'approved', 'loading'], 'tone' => 'danger', 'reason' => true],
                ],
                'links' => [
                    ['label' => 'Trip report', 'icon' => 'file-text', 'tone' => 'outline-secondary',
                     'permission' => 'trips', 'ability' => 'view', 'new_tab' => true,
                     'route' => ['trips', ':id', 'report'],
                     'hint' => 'Everything about this trip on one document: print it, or download the PDF or the Excel workbook.'],
                    ['label' => 'Load a shipment', 'icon' => 'package', 'tone' => 'outline-primary',
                     'permission' => 'shipments', 'ability' => 'create',
                     'when' => ['status' => ['requested', 'approved', 'loading']],
                     'route' => ['shipments', 'create'], 'carry' => ['trip_id' => 'id'],
                     'hint' => 'Puts another load on this truck. Each customer gets their own shipment.'],
                    ['label' => 'Record delivery', 'icon' => 'check-square', 'tone' => 'outline-primary',
                     'permission' => 'deliveries', 'ability' => 'create',
                     'when' => ['status' => ['requested', 'approved', 'loading', 'in_transit']],
                     'route' => ['deliveries', 'create'], 'carry' => ['trip_id' => 'id'],
                     'hint' => 'Opens a delivery for this trip, already set to the state the trip is in.'],
                    ['label' => 'Create invoice', 'icon' => 'file-text', 'tone' => 'success', 'method' => 'post',
                     'permission' => 'invoices', 'ability' => 'create',
                     'when' => ['status' => ['loading', 'in_transit', 'delivered']],
                     'route' => ['trips', ':id', 'invoice'],
                     'hint' => 'Raises a draft invoice for each customer on this trip, with a line per shipment at the quoted price. Nothing is retyped.'],
                    ['label' => 'Hand cargo to next leg', 'icon' => 'shuffle', 'tone' => 'warning', 'method' => 'post',
                     'permission' => 'trips', 'ability' => 'edit',
                     'when' => ['status' => ['loading', 'in_transit']], 'when_set' => ['journey_id'],
                     'route' => ['trips', ':id', 'handover'],
                     'hint' => 'Only for a leg of a journey: moves the loads aboard onto the next leg\'s truck at this leg\'s destination, then this leg can be completed.'],
                    ['label' => 'Record disbursement', 'icon' => 'corner-up-right', 'tone' => 'outline-primary',
                     'permission' => 'disbursements', 'ability' => 'create',
                     'route' => ['disbursements', 'create'], 'carry' => ['trip_id' => 'id', 'customer_id' => 'customer_id'],
                     'hint' => 'Duty, port charges or an agent fee paid for the customer on this trip, to be recharged at cost on their invoice.'],
                    ['label' => 'Record supplier bill', 'icon' => 'inbox', 'tone' => 'outline-primary',
                     'permission' => 'supplier_bills', 'ability' => 'create',
                     'route' => ['supplier_bills', 'create'], 'carry' => ['trip_id' => 'id'],
                     'hint' => 'A subcontractor, agent or garage invoice for this trip, so its cost shows on the trip and in what we owe.'],
                ],
            ],

            'shipments' => [
                'title' => 'Shipments',
                'singular' => 'Shipment',
                'kicker' => 'Cargo and consignment',
                'icon' => 'package',
                'description' => 'What is actually being moved: cargo, weight, packages, temperature range and consignee.',
                'button' => 'Book shipment',
                'table' => 'shipments',
                'alias' => 's',
                // Requote only when the route, the weight or the customer moves.
                'track_changes' => true,
                'code' => 'shipment_code',
                'order' => 's.id DESC',
                'joins' => 'LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN trips t ON t.id = s.trip_id LEFT JOIN transport_requests r ON r.id = s.request_id',
                'select' => ['s.id', 's.shipment_code', 'c.customer_name AS customer', 's.consignee_name', 's.origin', 's.destination', 's.cargo_type', 's.cargo_description', 's.packages_count', 's.weight_kg', 's.is_hazardous', 't.reference_code AS trip', 'r.reference_code AS request', 's.status'],
                'search' => ['s.shipment_code', 's.consignee_name', 's.cargo_description', 's.origin', 's.destination', 'c.customer_name'],
                'list' => [
                    'shipment_code' => ['label' => 'Shipment', 'type' => 'code'],
                    'customer' => ['label' => 'Customer', 'empty' => 'Internal'],
                    'consignee_name' => ['label' => 'Consignee'],
                    'destination' => ['label' => 'To'],
                    'cargo_type' => ['label' => 'Cargo type', 'type' => 'label'],
                    'packages_count' => ['label' => 'Packages', 'type' => 'number'],
                    'weight_kg' => ['label' => 'Weight', 'type' => 'decimal', 'suffix' => 'kg'],
                    'trip' => ['label' => 'Trip', 'empty' => 'Unplanned'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 's.status', 'options' => self::SHIPMENT_STATUS],
                    'cargo_type' => ['label' => 'Cargo type', 'column' => 's.cargo_type', 'options' => ['general' => 'General', 'cold_chain' => 'Cold chain', 'perishable' => 'Perishable', 'fragile' => 'Fragile', 'hazardous' => 'Hazardous', 'bulk' => 'Bulk', 'liquid' => 'Liquid']],
                ],
                'sections' => [
                    ['title' => 'Shipment', 'icon' => 'package', 'fields' => ['shipment_code', 'customer_id', 'request_id', 'trip_id']],
                    ['title' => 'Consignee and route', 'icon' => 'map-pin', 'hint' => 'A load that drops part way along finishes there; the truck carries on.', 'fields' => ['consignee_name', 'consignee_phone', 'origin', 'origin_warehouse_id', 'destination', 'destination_warehouse_id']],
                    ['title' => 'Out of our own stock', 'icon' => 'archive', 'hint' => 'Only when the goods are ours. Dispatching the truck takes them off the depot balance.', 'fields' => ['stock_item_id', 'stock_quantity', 'stock_issued_at']],
                    ['title' => 'Cargo', 'icon' => 'box', 'hint' => 'Cold chain and hazardous cargo change which vehicle may carry it.', 'fields' => ['cargo_type', 'cargo_description', 'packages_count', 'weight_kg', 'volume_m3', 'declared_value', 'currency']],
                    ['title' => 'Container and references', 'icon' => 'hash', 'hint' => 'A container is the big steel box the goods travel in from the ship to the customer; it belongs to the shipping line and must be returned empty. These numbers are how the port, customs and the customer find and check this load. Printed on the delivery note and used at every border. Goods loaded loose on the truck (no box): leave the container fields blank and choose "Loose / break bulk".', 'fields' => ['container_no', 'container_size', 'seal_no', 'trailer_plate', 'bill_of_lading', 'customer_reference']],
                    ['title' => 'Handling conditions', 'icon' => 'thermometer', 'hint' => 'Leave the temperature range blank for ambient cargo.', 'fields' => ['temperature_min_c', 'temperature_max_c', 'is_hazardous', 'status']],
                    ['title' => 'Instructions', 'icon' => 'file-text', 'fields' => ['special_instructions']],
                ],
                'fields' => [
                    'shipment_code' => ['label' => 'Shipment reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'SHP'],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL', 'fills' => ['consignee_phone' => 'phone', 'currency' => 'currency']]],
                    'request_id' => ['label' => 'Source request', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'transport_requests', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL', 'fills' => ['customer_id' => 'customer_id', 'consignee_name' => 'requested_by_contact', 'origin' => 'pickup_location', 'destination' => 'destination', 'origin_warehouse_id' => 'origin_warehouse_id', 'destination_warehouse_id' => 'destination_warehouse_id', 'cargo_description' => 'cargo_description', 'weight_kg' => 'weight_kg', 'packages_count' => 'packages_count', 'currency' => 'currency', 'stock_item_id' => 'stock_item_id', 'stock_quantity' => 'stock_quantity', 'customer_reference' => 'lpo_number']]],
                    'trip_id' => ['label' => 'Loaded on trip', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => "status NOT IN ('delivered','cancelled') AND deleted_at IS NULL"], 'help' => 'Pick a trip already going that way. Saving this marks the request Assigned.'],
                    'consignee_name' => ['label' => 'Consignee', 'type' => 'text', 'required' => true, 'width' => 4, 'help' => 'Who receives the goods at the destination.'],
                    'consignee_phone' => ['label' => 'Consignee phone', 'type' => 'tel', 'width' => 4],
                    'origin' => ['label' => 'Origin', 'type' => 'text', 'required' => true, 'width' => 3],
                    'origin_warehouse_id' => ['label' => 'Loaded at warehouse', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"], ],
                    'destination' => ['label' => 'Destination', 'type' => 'text', 'required' => true, 'width' => 3],
                    'destination_warehouse_id' => ['label' => 'Drops at warehouse', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"], 'help' => 'Where this load leaves the truck.'],
                    'stock_item_id' => ['label' => 'Item from our stock', 'type' => 'relation', 'width' => 5, 'relation' => ['table' => 'inventory_items', 'label' => 'item_name', 'where' => 'deleted_at IS NULL', 'depends_on' => ['field' => 'origin_warehouse_id', 'column' => 'warehouse_id'], 'empty' => 'Not from our stock'], 'help' => 'Leave blank when the customer brought the goods themselves.'],
                    'stock_quantity' => ['label' => 'Quantity issued', 'type' => 'decimal', 'width' => 3, 'help' => 'In the item\'s own unit: 8 tyres, 200 litres.'],
                    'stock_issued_at' => ['label' => 'Taken off the depot', 'type' => 'datetime', 'width' => 4, 'readonly' => true, 'readonly_note' => 'when the truck left'],
                    'cargo_type' => ['label' => 'Cargo type', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['general' => 'General', 'cold_chain' => 'Cold chain', 'perishable' => 'Perishable', 'fragile' => 'Fragile', 'hazardous' => 'Hazardous', 'bulk' => 'Bulk', 'liquid' => 'Liquid']],
                    'cargo_description' => ['label' => 'Cargo description', 'type' => 'text', 'required' => true, 'width' => 8, 'placeholder' => 'Fortified maize flour, 40 sacks of 25 kg'],
                    'packages_count' => ['label' => 'Packages', 'type' => 'number', 'width' => 3, 'min' => 1],
                    'weight_kg' => ['label' => 'Gross weight', 'type' => 'decimal', 'width' => 3, 'suffix' => 'kg'],
                    'volume_m3' => ['label' => 'Volume', 'type' => 'decimal', 'width' => 3, 'suffix' => 'm3'],
                    'declared_value' => ['label' => 'Declared value', 'type' => 'money', 'width' => 3, 'help' => 'What the goods are worth, not what we charge to carry them. Used for insurance and at customs.'],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 3, 'options' => Currency::options(), ],
                    'container_no' => ['label' => 'Container number', 'type' => 'text', 'width' => 4, 'placeholder' => 'MSKU 123456-7', 'help' => 'Painted on the doors of the box: 4 letters (the owner) and 7 digits. Unique in the world.'],
                    'container_size' => ['label' => 'Container size', 'type' => 'select', 'width' => 4, 'options' => ['20ft' => '20 ft', '40ft' => '40 ft', '40ft_hc' => '40 ft high cube', '45ft' => '45 ft', 'loose' => 'Loose / break bulk'], 'help' => 'The length of the box. A 40 ft holds about twice a 20 ft. Loose: goods on the truck with no box.'],
                    'seal_no' => ['label' => 'Seal number', 'type' => 'text', 'width' => 4, 'help' => 'The numbered lock on the doors. A broken or different seal means the box was opened on the way.'],
                    'trailer_plate' => ['label' => 'Trailer plate', 'type' => 'text', 'width' => 4, 'placeholder' => 'RL 1234', 'help' => 'The plate of the trailer the box sits on; not the truck\'s plate.'],
                    'bill_of_lading' => ['label' => 'Bill of lading', 'type' => 'text', 'width' => 4, 'placeholder' => 'MEDUK0448812', 'help' => 'The shipping line\'s receipt for the goods at sea. The port releases the box only against it.'],
                    'customer_reference' => ['label' => 'Customer LPO / reference', 'type' => 'text', 'width' => 4, 'help' => 'Filled in from the request. Printed on the delivery note and the invoice.'],
                    'temperature_min_c' => ['label' => 'Minimum temperature', 'type' => 'decimal', 'width' => 3, 'suffix' => 'C'],
                    'temperature_max_c' => ['label' => 'Maximum temperature', 'type' => 'decimal', 'width' => 3, 'suffix' => 'C'],
                    'is_hazardous' => ['label' => 'Hazardous goods', 'type' => 'checkbox', 'width' => 3],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => self::SHIPMENT_STATUS],
                    'special_instructions' => ['label' => 'Special instructions', 'type' => 'textarea', 'width' => 12, 'placeholder' => 'Keep between 2 and 8 degrees. Do not stack more than 3 crates high.'],
                ],
                'related' => [
                    ['title' => 'Deliveries', 'icon' => 'check-square', 'permission' => 'deliveries', 'module' => 'deliveries',
                     'sql' => 'SELECT id, delivery_code, recipient_name, status, attempt_number, delivered_at FROM deliveries WHERE shipment_id = :id AND deleted_at IS NULL ORDER BY id',
                     'columns' => ['delivery_code' => 'Delivery', 'recipient_name' => 'Recipient', 'status' => 'Status', 'attempt_number' => 'Attempt', 'delivered_at' => 'Delivered'],
                     'empty' => 'No delivery attempt recorded.'],
                    // Where this load has physically been, in order. The answer
                    // to "the customer says it never reached the depot".
                    ['title' => 'Depots it has been through', 'icon' => 'home', 'permission' => 'warehouses', 'module' => 'warehouses',
                     'sql' => 'SELECT wc.id, wc.movement_code, w.warehouse_name, wc.direction, wc.reason, wc.weight_kg, t.reference_code AS trip, wc.moved_at, u.full_name AS handled_by
                                 FROM warehouse_cargo wc
                                 INNER JOIN warehouses w ON w.id = wc.warehouse_id
                                 LEFT JOIN trips t ON t.id = wc.trip_id
                                 LEFT JOIN users u ON u.id = wc.performed_by
                                WHERE wc.shipment_id = :id
                                ORDER BY wc.moved_at, wc.id',
                     'columns' => ['warehouse_name' => 'Depot', 'direction' => 'In or out', 'reason' => 'Why', 'weight_kg' => 'Weight', 'trip' => 'Trip', 'moved_at' => 'When', 'handled_by' => 'Handled by'],
                     'empty' => 'This load has not been recorded at any depot.'],
                ],
                'links' => [
                    ['label' => 'Keep in storage', 'icon' => 'archive', 'tone' => 'outline-primary',
                     'permission' => 'storage_lots', 'ability' => 'create',
                     'route' => ['storage_lots', 'create'], 'carry' => ['shipment_id' => 'id'],
                     'hint' => 'The customer is leaving this cargo with us: opens a goods received note filled from the shipment.'],
                    ['label' => 'Track container', 'icon' => 'box', 'tone' => 'outline-primary',
                     'permission' => 'containers', 'ability' => 'create',
                     'route' => ['containers', 'create'], 'carry' => ['shipment_id' => 'id'],
                     'hint' => 'Starts the free-days clock on the shipping line\'s container, so it goes back before detention.'],
                ],
            ],

            'support_requests' => [
                'title' => 'Driver support',
                'singular' => 'Support request',
                'kicker' => 'Help on the road',
                'icon' => 'life-buoy',
                'description' => 'What a driver asks the company for during a trip: money, help with a breakdown, a border or police problem. Each request belongs to its trip.',
                'button' => 'Ask for support',
                'table' => 'support_requests',
                'alias' => 'sr',
                'code' => 'request_code',
                'order' => "FIELD(sr.status, 'open', 'in_progress', 'resolved', 'rejected'), FIELD(sr.urgency, 'emergency', 'urgent', 'normal'), sr.id DESC",
                'joins' => 'LEFT JOIN trips t ON t.id = sr.trip_id LEFT JOIN drivers d ON d.id = sr.driver_id LEFT JOIN users a ON a.id = sr.assigned_to',
                'select' => ['sr.id', 'sr.request_code', 't.reference_code AS trip', 'd.full_name AS driver', 'sr.support_type', 'sr.title', 'sr.urgency', 'sr.amount_requested', 'sr.currency', 'a.full_name AS assigned', 'sr.status', 'sr.created_at'],
                'search' => ['sr.request_code', 'sr.title', 'sr.details', 't.reference_code', 'd.full_name'],
                // A driver sees the requests of his own trips only.
                'scope' => ['driver' => 't.driver_id'],
                'list' => [
                    'request_code' => ['label' => 'Request', 'type' => 'code'],
                    'trip' => ['label' => 'Trip'],
                    'driver' => ['label' => 'Driver'],
                    'support_type' => ['label' => 'Type', 'type' => 'label'],
                    'title' => ['label' => 'What is needed'],
                    'urgency' => ['label' => 'Urgency', 'type' => 'badge'],
                    'amount_requested' => ['label' => 'Amount asked', 'type' => 'money', 'empty' => '—'],
                    'assigned' => ['label' => 'Sent to', 'empty' => 'Office'],
                    'created_at' => ['label' => 'Asked', 'type' => 'datetime'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'sr.status', 'options' => self::SUPPORT_STATUS],
                    'support_type' => ['label' => 'Type', 'column' => 'sr.support_type', 'options' => self::SUPPORT_TYPES],
                    'urgency' => ['label' => 'Urgency', 'column' => 'sr.urgency', 'options' => self::SUPPORT_URGENCY],
                ],
                'sections' => [
                    ['title' => 'What the driver needs', 'icon' => 'life-buoy', 'fields' => ['request_code', 'trip_id', 'support_type', 'urgency', 'title', 'details', 'location', 'amount_requested', 'currency', 'attachment']],
                    ['title' => 'Who handles it', 'icon' => 'user-check', 'fields' => ['assigned_to', 'driver_id', 'requested_by']],
                    ['title' => 'The answer', 'icon' => 'message-circle', 'hide_on_create' => true, 'fields' => ['status', 'response', 'amount_given', 'given_method', 'given_reference', 'responded_by', 'responded_at']],
                ],
                'fields' => [
                    'request_code' => ['label' => 'Request number', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'SUP'],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => "deleted_at IS NULL AND status IN ('requested','approved','loading','in_transit','delivered')", 'scope' => ['driver' => 'driver_id = :driver_id']]],
                    'support_type' => ['label' => 'Type of support', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::SUPPORT_TYPES],
                    'urgency' => ['label' => 'How urgent', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::SUPPORT_URGENCY],
                    'title' => ['label' => 'What do you need?', 'type' => 'text', 'required' => true, 'width' => 8, 'placeholder' => 'Money for a new tyre at Busia'],
                    'details' => ['label' => 'What happened', 'type' => 'textarea', 'width' => 12, 'placeholder' => 'The rear left tyre burst 20 km after Busia. The garage asks USD 180 for a new one.'],
                    'location' => ['label' => 'Where you are', 'type' => 'text', 'width' => 6, 'placeholder' => 'Busia, 20 km towards Kampala'],
                    'amount_requested' => ['label' => 'Amount needed', 'type' => 'money', 'width' => 3],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 3, 'options' => Currency::options()],
                    'attachment' => ['label' => 'Photo or document', 'type' => 'file', 'width' => 6],
                    'assigned_to' => ['label' => 'Send to', 'type' => 'relation', 'required' => true, 'width' => 4,
                                      'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => "deleted_at IS NULL AND status = 'active' AND role_id NOT IN (SELECT id FROM roles WHERE role_key = 'driver')"]],
                    'driver_id' => ['label' => 'Driver', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'from the trip', 'relation' => ['table' => 'drivers', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'requested_by' => ['label' => 'Asked by', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'you', 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 3, 'options' => self::SUPPORT_STATUS, 'readonly' => true, 'readonly_note' => 'set by the buttons'],
                    'response' => ['label' => 'Answer to the driver', 'type' => 'textarea', 'width' => 12, 'locked_for' => ['driver'], 'placeholder' => 'USD 180 sent by MoMo. Keep the garage receipt.'],
                    'amount_given' => ['label' => 'Amount given', 'type' => 'money', 'width' => 3, 'locked_for' => ['driver']],
                    'given_method' => ['label' => 'Sent by', 'type' => 'select', 'width' => 3, 'locked_for' => ['driver'], 'options' => ['MoMo' => 'Mobile money', 'Bank transfer' => 'Bank transfer', 'Cash' => 'Cash', 'Agent' => 'Through an agent', 'Fuel card' => 'Fuel card']],
                    'given_reference' => ['label' => 'Reference', 'type' => 'text', 'width' => 3, 'locked_for' => ['driver'], 'placeholder' => 'MoMo transaction ID'],
                    'responded_by' => ['label' => 'Answered by', 'type' => 'relation', 'width' => 3, 'readonly' => true, 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'responded_at' => ['label' => 'Answered at', 'type' => 'datetime', 'width' => 3, 'readonly' => true],
                ],
                'actions' => [
                    'acknowledge' => ['label' => 'Seen, handling it', 'to' => 'in_progress', 'from' => ['open'], 'tone' => 'primary'],
                    // Resolving asks for the answer in a small window and saves it in the same press.
                    'resolve' => ['label' => 'Resolved', 'to' => 'resolved', 'from' => ['open', 'in_progress'], 'tone' => 'success',
                                    'form' => ['response', 'amount_given', 'given_method', 'given_reference'], 'form_title' => 'Answer the driver', 'form_submit' => 'Save and resolve'],
                    'reject' => ['label' => 'Refuse', 'to' => 'rejected', 'from' => ['open', 'in_progress'], 'tone' => 'danger', 'reason' => true],
                ],
                'links' => [
                    ['label' => 'Open the trip', 'icon' => 'navigation', 'tone' => 'outline-secondary', 'permission' => 'trips', 'ability' => 'view',
                     'route' => ['trips', ':trip_id']],
                ],
            ],

            'journeys' => [
                'title' => 'Journeys',
                'singular' => 'Journey',
                'kicker' => 'Moves that take more than one truck',
                'icon' => 'git-commit',
                'description' => 'One customer move made of several legs, each an ordinary trip with its own truck and driver. Cargo is handed from leg to leg, the customer is billed once, and the profit is read across all legs.',
                'button' => 'Plan a journey',
                'table' => 'journeys',
                'alias' => 'j',
                'code' => 'journey_code',
                'order' => 'j.id DESC',
                'joins' => 'LEFT JOIN customers c ON c.id = j.customer_id',
                'select' => ['j.id', 'j.journey_code', 'c.customer_name AS customer', 'j.origin', 'j.route_via', 'j.final_destination', 'j.planned_start', 'j.planned_end', 'j.status',
                             '(SELECT COUNT(*) FROM trips t WHERE t.journey_id = j.id AND t.deleted_at IS NULL) AS legs'],
                'search' => ['j.journey_code', 'j.origin', 'j.final_destination', 'c.customer_name'],
                'list' => [
                    'journey_code' => ['label' => 'Journey', 'type' => 'code'],
                    'customer' => ['label' => 'Customer', 'empty' => '—'],
                    'origin' => ['label' => 'From'],
                    'route_via' => ['label' => 'Via', 'empty' => '—'],
                    'final_destination' => ['label' => 'To'],
                    'legs' => ['label' => 'Legs', 'type' => 'number'],
                    'planned_start' => ['label' => 'Starts', 'type' => 'date'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'j.status', 'options' => Journey::STATUS]],
                'sections' => [
                    ['title' => 'Journey', 'icon' => 'git-commit', 'hint' => 'Then add each leg from this page: "Add next leg" opens a trip starting where the last one ends.', 'fields' => ['journey_code', 'customer_id', 'request_id', 'origin', 'route_via', 'final_destination', 'planned_start', 'planned_end', 'status', 'notes']],
                ],
                'fields' => [
                    'journey_code' => ['label' => 'Journey reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'JRY'],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL']],
                    'request_id' => ['label' => 'Fulfils request', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'transport_requests', 'label' => 'reference_code', 'where' => "status IN ('approved','assigned') AND deleted_at IS NULL"]],
                    'origin' => ['label' => 'Starts at', 'type' => 'text', 'required' => true, 'width' => 4, 'placeholder' => 'Dar es Salaam port'],
                    'route_via' => ['label' => 'Via (handover points)', 'type' => 'text', 'width' => 4, 'placeholder' => 'Rusumo'],
                    'final_destination' => ['label' => 'Ends at', 'type' => 'text', 'required' => true, 'width' => 4, 'placeholder' => 'Kigali, ITEC warehouse'],
                    'planned_start' => ['label' => 'Planned start', 'type' => 'date', 'width' => 4],
                    'planned_end' => ['label' => 'Planned arrival', 'type' => 'date', 'width' => 4],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 4, 'options' => Journey::STATUS, 'readonly' => true, 'readonly_note' => 'follows the legs'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'links' => [
                    ['label' => 'Add next leg', 'icon' => 'plus', 'tone' => 'primary',
                     'permission' => 'trips', 'ability' => 'create',
                     'when' => ['status' => ['planned', 'in_progress']],
                     'route' => ['trips', 'create'], 'carry' => ['journey_id' => 'id'],
                     'hint' => 'Opens a trip for the next truck, starting where the last leg ends.'],
                    ['label' => 'Create invoice', 'icon' => 'file-text', 'tone' => 'success', 'method' => 'post',
                     'permission' => 'invoices', 'ability' => 'create',
                     'when' => ['status' => ['in_progress', 'completed']],
                     'route' => ['journeys', ':id', 'invoice'],
                     'hint' => 'One draft invoice per customer for the whole journey, with the duty paid on any leg added at cost.'],
                ],
                'related' => [
                    ['title' => 'Legs', 'icon' => 'navigation', 'permission' => 'trips', 'module' => 'trips',
                     'sql' => 'SELECT t.id, t.leg_no, t.reference_code, t.pickup_location, t.destination, v.plate_number, d.full_name AS driver, t.status
                                 FROM trips t LEFT JOIN vehicles v ON v.id = t.vehicle_id LEFT JOIN drivers d ON d.id = t.driver_id
                                WHERE t.journey_id = :id AND t.deleted_at IS NULL ORDER BY COALESCE(t.leg_no, 99), t.id',
                     'columns' => ['leg_no' => 'Leg', 'reference_code' => 'Trip', 'pickup_location' => 'From', 'destination' => 'To', 'plate_number' => 'Truck', 'driver' => 'Driver', 'status' => 'Status'],
                     'empty' => 'No legs yet. Press "Add next leg".'],
                    ['title' => 'Cargo and where it is', 'icon' => 'package', 'permission' => 'shipments', 'module' => 'shipments',
                     'sql' => 'SELECT s.id, s.shipment_code, c.customer_name, s.cargo_description, t.reference_code AS on_trip, t.leg_no, s.status
                                 FROM shipments s INNER JOIN trips t ON t.id = s.trip_id LEFT JOIN customers c ON c.id = s.customer_id
                                WHERE t.journey_id = :id AND s.deleted_at IS NULL ORDER BY s.id',
                     'columns' => ['shipment_code' => 'Shipment', 'customer_name' => 'Customer', 'cargo_description' => 'Goods', 'on_trip' => 'Now on', 'leg_no' => 'Leg', 'status' => 'Status'],
                     'empty' => 'No cargo is booked on any leg yet.'],
                    ['title' => 'Money across all legs', 'icon' => 'dollar-sign', 'permission' => 'invoices',
                     'sql' => "SELECT j.id,
                                      (SELECT COALESCE(SUM(i.subtotal - COALESCE((SELECT SUM(l.line_total) FROM invoice_lines l WHERE l.invoice_id = i.id AND l.line_kind = 'disbursement'), 0)), 0)
                                         FROM invoices i WHERE i.deleted_at IS NULL AND i.status <> 'cancelled' AND (i.journey_id = j.id OR i.trip_id IN (SELECT id FROM trips WHERE journey_id = j.id))) AS revenue,
                                      (SELECT COALESCE(SUM(f.litres * f.unit_price), 0) FROM fuel_records f WHERE f.deleted_at IS NULL AND f.trip_id IN (SELECT id FROM trips WHERE journey_id = j.id)) AS fuel,
                                      (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.deleted_at IS NULL AND e.status <> 'rejected' AND e.trip_id IN (SELECT id FROM trips WHERE journey_id = j.id)) AS expenses,
                                      (SELECT COALESCE(SUM(b.subtotal), 0) FROM supplier_bills b WHERE b.deleted_at IS NULL AND b.status <> 'cancelled' AND b.trip_id IN (SELECT id FROM trips WHERE journey_id = j.id)) AS bills
                                 FROM journeys j WHERE j.id = :id",
                     'columns' => ['revenue' => 'Revenue (net of VAT and disbursements)', 'fuel' => 'Fuel', 'expenses' => 'Trip expenses', 'bills' => 'Supplier bills'],
                     'empty' => 'Nothing yet.'],
                ],
            ],

            'containers' => [
                'title' => 'Containers',
                'singular' => 'Container',
                'kicker' => 'Empty return and detention',
                'icon' => 'box',
                'description' => 'Every shipping-line container we move, from leaving the port full to coming back empty. Free days count from gate-out; after them the line charges detention per day.',
                'button' => 'Track a container',
                'table' => 'containers',
                'alias' => 'ct',
                'code' => 'tracking_code',
                'order' => "FIELD(ct.status, 'out_full', 'unloaded', 'returned'), ct.picked_up_at",
                'joins' => 'LEFT JOIN suppliers sl ON sl.id = ct.shipping_line_id LEFT JOIN customers c ON c.id = ct.customer_id LEFT JOIN trips t ON t.id = ct.trip_id',
                'select' => ['ct.id', 'ct.tracking_code', 'ct.container_no', 'ct.container_size', 'sl.supplier_name AS line', 'c.customer_name AS customer', 't.reference_code AS trip',
                             'ct.picked_up_at', 'ct.free_days', 'ct.returned_at', 'ct.currency', 'ct.status',
                             'DATEDIFF(COALESCE(DATE(ct.returned_at), CURDATE()), DATE(ct.picked_up_at)) + 1 AS days_out',
                             // free_days is unsigned: subtracting from it directly fails while a box is still inside its free time.
                             'GREATEST(0, DATEDIFF(COALESCE(DATE(ct.returned_at), CURDATE()), DATE(ct.picked_up_at)) + 1 - CAST(ct.free_days AS SIGNED)) AS detention_days',
                             'GREATEST(0, DATEDIFF(COALESCE(DATE(ct.returned_at), CURDATE()), DATE(ct.picked_up_at)) + 1 - CAST(ct.free_days AS SIGNED)) * ct.detention_rate AS detention_due',
                             'DATE_ADD(DATE(ct.picked_up_at), INTERVAL GREATEST(CAST(ct.free_days AS SIGNED), 1) - 1 DAY) AS free_until'],
                'search' => ['ct.container_no', 'ct.tracking_code', 'ct.bill_of_lading', 'c.customer_name', 'sl.supplier_name'],
                'list' => [
                    'container_no' => ['label' => 'Container', 'type' => 'code'],
                    'container_size' => ['label' => 'Size', 'empty' => '—'],
                    'line' => ['label' => 'Shipping line', 'empty' => '—'],
                    'customer' => ['label' => 'Customer', 'empty' => '—'],
                    'picked_up_at' => ['label' => 'Out of port', 'type' => 'date'],
                    'free_until' => ['label' => 'Free until', 'type' => 'expiry'],
                    'days_out' => ['label' => 'Days out', 'type' => 'number'],
                    'detention_days' => ['label' => 'Detention days', 'type' => 'number'],
                    'detention_due' => ['label' => 'Detention', 'type' => 'money'],
                    'currency' => ['label' => 'Cur.'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'ct.status', 'options' => Containers::STATUS],
                ],
                'sections' => [
                    ['title' => 'The container', 'icon' => 'box', 'fields' => ['tracking_code', 'container_no', 'container_size', 'shipping_line_id', 'bill_of_lading', 'customer_id', 'shipment_id', 'trip_id']],
                    ['title' => 'Free days and detention', 'icon' => 'clock', 'hint' => 'From the shipping line\'s release order. Days count from the day it left the port, that day included.', 'fields' => ['picked_up_at', 'picked_up_from', 'free_days', 'detention_rate', 'currency', 'deposit_amount']],
                    ['title' => 'Back empty', 'icon' => 'corner-down-left', 'hint' => 'Fill the return date from the depot\'s interchange receipt. The status follows the dates.', 'fields' => ['unloaded_at', 'returned_at', 'return_depot', 'return_interchange_no', 'deposit_refunded_at', 'status', 'interchange_file', 'notes']],
                ],
                'fields' => [
                    'tracking_code' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 3, 'auto' => 'CTR'],
                    'container_no' => ['label' => 'Container number', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => 'MSKU 123456-7'],
                    'container_size' => ['label' => 'Size', 'type' => 'select', 'width' => 2, 'options' => ['20ft' => '20 ft', '40ft' => '40 ft', '40ft HC' => '40 ft high cube', '20ft reefer' => '20 ft reefer', '40ft reefer' => '40 ft reefer']],
                    'shipping_line_id' => ['label' => 'Shipping line', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'suppliers', 'label' => 'supplier_name', 'where' => "deleted_at IS NULL AND (category = 'Shipping line' OR id IN (SELECT shipping_line_id FROM containers WHERE shipping_line_id IS NOT NULL))"], 'help' => 'Add Maersk, MSC, CMA CGM… as suppliers in the Shipping line category.'],
                    'bill_of_lading' => ['label' => 'Bill of lading', 'type' => 'text', 'width' => 3],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL']],
                    'shipment_id' => ['label' => 'Shipment', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'shipments', 'label' => 'shipment_code', 'where' => 'deleted_at IS NULL', 'fills' => ['container_no' => 'container_no', 'container_size' => 'container_size', 'bill_of_lading' => 'bill_of_lading', 'customer_id' => 'customer_id', 'trip_id' => 'trip_id', 'picked_up_from' => 'origin']], 'help' => 'Choosing it fills in the container, bill of lading, customer and trip.'],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL']],
                    'picked_up_at' => ['label' => 'Left the port (gate-out full)', 'type' => 'datetime', 'required' => true, 'width' => 4],
                    'picked_up_from' => ['label' => 'From', 'type' => 'text', 'width' => 4, 'placeholder' => 'Dar es Salaam port, TICTS'],
                    'free_days' => ['label' => 'Free days', 'type' => 'number', 'required' => true, 'width' => 2],
                    'detention_rate' => ['label' => 'Detention per day', 'type' => 'money', 'width' => 3],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 2, 'options' => Currency::options()],
                    'deposit_amount' => ['label' => 'Deposit paid', 'type' => 'money', 'width' => 3, 'help' => 'Container guarantee paid to the line, if any.'],
                    'unloaded_at' => ['label' => 'Unloaded at customer', 'type' => 'datetime', 'width' => 3],
                    'returned_at' => ['label' => 'Returned empty', 'type' => 'datetime', 'width' => 3],
                    'return_depot' => ['label' => 'Return depot', 'type' => 'text', 'width' => 3, 'placeholder' => 'MSC empty depot, Kigali'],
                    'return_interchange_no' => ['label' => 'Interchange (EIR) number', 'type' => 'text', 'width' => 3],
                    'deposit_refunded_at' => ['label' => 'Deposit refunded on', 'type' => 'date', 'width' => 3],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 3, 'options' => Containers::STATUS, 'readonly' => true, 'readonly_note' => 'follows the dates'],
                    'interchange_file' => ['label' => 'Interchange receipt (scan)', 'type' => 'file', 'width' => 6],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'actions' => [
                    'unload' => ['label' => 'Unloaded', 'to' => 'unloaded', 'from' => ['out_full'], 'tone' => 'primary'],
                    'return' => ['label' => 'Returned empty', 'to' => 'returned', 'from' => ['out_full', 'unloaded'], 'tone' => 'success'],
                ],
                'links' => [
                    ['label' => 'Recharge detention to customer', 'icon' => 'corner-up-right', 'tone' => 'outline-danger', 'method' => 'post',
                     'permission' => 'disbursements', 'ability' => 'create',
                     'route' => ['containers', ':id', 'recharge'],
                     'hint' => 'Puts the detention so far on the customer\'s disbursements, to go on their next invoice at cost.'],
                ],
            ],

            'deliveries' => [
                'title' => 'Deliveries',
                'singular' => 'Delivery',
                'kicker' => 'Proof of delivery',
                'icon' => 'check-square',
                'description' => 'Delivery attempts, recipients, proof files, signatures and failure reasons.',
                'button' => 'Record delivery',
                'table' => 'deliveries',
                'alias' => 'dl',
                'code' => 'delivery_code',
                'order' => 'dl.id DESC',
                'joins' => 'LEFT JOIN trips t ON t.id = dl.trip_id LEFT JOIN shipments s ON s.id = dl.shipment_id',
                'select' => ['dl.id', 'dl.delivery_code', 't.reference_code AS trip', 's.shipment_code AS shipment', 'dl.recipient_name', 'dl.recipient_phone', 'dl.destination', 'dl.status', 'dl.attempt_number', 'dl.failure_reason', 'dl.proof_file', 'dl.recipient_signature', 'dl.planned_at', 'dl.delivered_at'],
                'search' => ['dl.delivery_code', 'dl.recipient_name', 'dl.destination', 't.reference_code'],
                'scope' => ['driver' => 't.driver_id'],
                'list' => [
                    'delivery_code' => ['label' => 'Delivery', 'type' => 'code'],
                    'trip' => ['label' => 'Trip', 'empty' => 'Standalone'],
                    'recipient_name' => ['label' => 'Recipient'],
                    'destination' => ['label' => 'Destination'],
                    'attempt_number' => ['label' => 'Attempt', 'type' => 'number'],
                    'proof_file' => ['label' => 'Proof', 'type' => 'file'],
                    'recipient_signature' => ['label' => 'Signature', 'type' => 'file'],
                    'delivered_at' => ['label' => 'Delivered', 'type' => 'datetime'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'dl.status', 'options' => self::DELIVERY_STATUS],
                    'failure_reason' => ['label' => 'Failure reason', 'column' => 'dl.failure_reason', 'options' => self::failureReasons()],
                ],
                'sections' => [
                    ['title' => 'Delivery', 'icon' => 'check-square', 'fields' => ['delivery_code', 'trip_id', 'shipment_id', 'attempt_number']],
                    ['title' => 'Recipient', 'icon' => 'user', 'fields' => ['recipient_name', 'recipient_phone', 'destination', 'destination_warehouse_id']],
                    ['title' => 'Timing and status', 'icon' => 'clock', 'fields' => ['planned_at', 'status', 'delivered_at']],
                    ['title' => 'Proof of delivery', 'icon' => 'camera', 'hint' => 'Who actually took the goods, with their ID, and the signed delivery note. Uploads are private.', 'fields' => ['collected_by', 'collected_id_no', 'proof_file', 'recipient_signature']],
                    ['title' => 'Exception', 'icon' => 'alert-triangle', 'hint' => 'Fill this in only when the delivery failed, then reschedule it.', 'fields' => ['failure_reason', 'failure_notes', 'rescheduled_at']],
                ],
                'fields' => [
                    'delivery_code' => ['label' => 'Delivery reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'DEL', 'locked_for' => ['driver']],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL'], 'locked_for' => ['driver']],
                    'shipment_id' => ['label' => 'Shipment', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'shipments', 'label' => 'shipment_code', 'where' => 'deleted_at IS NULL', 'depends_on' => ['field' => 'trip_id', 'column' => 'trip_id'], 'fills' => ['recipient_name' => 'consignee_name', 'recipient_phone' => 'consignee_phone', 'destination' => 'destination', 'destination_warehouse_id' => 'destination_warehouse_id']], 'locked_for' => ['driver'], 'help' => 'Only the loads on the trip above. Choosing one fills in the recipient below.'],
                    'attempt_number' => ['label' => 'Attempt number', 'type' => 'number', 'width' => 4, 'min' => 1, 'help' => 'Increase when you re-deliver after a failure.', 'locked_for' => ['driver']],
                    'recipient_name' => ['label' => 'Recipient name', 'type' => 'text', 'required' => true, 'width' => 4, 'locked_for' => ['driver']],
                    'recipient_phone' => ['label' => 'Recipient phone', 'type' => 'tel', 'width' => 4, 'locked_for' => ['driver']],
                    'destination' => ['label' => 'Delivery address', 'type' => 'text', 'required' => true, 'width' => 4, 'locked_for' => ['driver']],
                    'destination_warehouse_id' => ['label' => 'Collected from warehouse', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"], 'help' => 'When the customer collects rather than being delivered to.', 'locked_for' => ['driver']],
                    'planned_at' => ['label' => 'Planned for', 'type' => 'datetime', 'width' => 4, 'locked_for' => ['driver']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::DELIVERY_STATUS, 'locked_for' => ['driver']],
                    'delivered_at' => ['label' => 'Delivered at', 'type' => 'datetime', 'width' => 4],
                    'collected_by' => ['label' => 'Received / collected by', 'type' => 'text', 'width' => 6, 'placeholder' => 'Jean Claude Mugabo', 'help' => 'The person who signed. Printed on the delivery note.'],
                    'collected_id_no' => ['label' => 'Their ID number', 'type' => 'text', 'width' => 6, 'placeholder' => 'National ID or passport'],
                    'proof_file' => ['label' => 'Proof of delivery', 'type' => 'file', 'width' => 6, 'help' => 'Signed delivery note, photo or PDF.'],
                    'recipient_signature' => ['label' => 'Recipient signature', 'type' => 'file', 'width' => 6],
                    'failure_reason' => ['label' => 'Failure reason', 'type' => 'select', 'width' => 4, 'options' => self::failureReasons()],
                    'failure_notes' => ['label' => 'Failure notes', 'type' => 'text', 'width' => 4],
                    'rescheduled_at' => ['label' => 'Rescheduled for', 'type' => 'datetime', 'width' => 4],
                ],
                'actions' => [
                    'drop' => ['label' => 'Dropped at warehouse', 'to' => 'at_destination', 'from' => ['loading', 'in_transit'], 'tone' => 'primary'],
                    'complete' => ['label' => 'Collected / delivered', 'to' => 'delivered', 'from' => ['loading', 'in_transit', 'at_destination'], 'tone' => 'success'],
                    'fail' => ['label' => 'Record failure', 'to' => 'failed', 'from' => ['loading', 'in_transit', 'at_destination'], 'tone' => 'danger', 'reason' => true],
                ],
                'links' => [
                    ['label' => 'Delivery note', 'icon' => 'printer', 'tone' => 'outline-primary', 'new_tab' => true,
                     'permission' => 'deliveries', 'ability' => 'view',
                     'route' => ['deliveries', ':id', 'note'],
                     'hint' => 'Opens the delivery note to print or save as PDF, for the receiver to sign.'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------- document packs

    private static function documentPack(): array
    {
        $shipment = ['table' => 'shipments', 'label' => 'shipment_code', 'where' => 'deleted_at IS NULL'];
        return [
            'shipment_documents' => [
                'title' => 'Shipment documents', 'singular' => 'Shipment document', 'kicker' => 'Pre-dispatch', 'icon' => 'file-text', 'description' => 'Documents the driver carries for a shipment.', 'button' => 'Add document', 'table' => 'shipment_documents', 'alias' => 'sd', 'code' => 'document_type', 'order' => 'sd.id DESC', 'soft_delete' => false,
                'joins' => 'INNER JOIN shipments s ON s.id = sd.shipment_id', 'select' => ['sd.id', 'sd.document_type', 'sd.document_number', 's.shipment_code AS shipment', 'sd.expiry_date', 'sd.status'], 'search' => ['sd.document_type', 'sd.document_number', 's.shipment_code'],
                'list' => ['document_type' => ['label' => 'Document', 'type' => 'code'], 'document_number' => ['label' => 'Number'], 'shipment' => ['label' => 'Shipment'], 'expiry_date' => ['label' => 'Expires', 'type' => 'date'], 'status' => ['label' => 'Status', 'type' => 'badge']], 'filters' => ['status' => ['label' => 'Status', 'column' => 'sd.status', 'options' => ['pending' => 'Pending', 'valid' => 'Valid', 'rejected' => 'Rejected', 'expired' => 'Expired']]],
                'sections' => [['title' => 'Document', 'icon' => 'file-text', 'fields' => ['shipment_id', 'document_type', 'document_number', 'issue_date', 'expiry_date', 'status']], ['title' => 'File', 'icon' => 'paperclip', 'fields' => ['document_file', 'notes']]],
                'fields' => ['shipment_id' => ['label' => 'Shipment', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => $shipment], 'document_type' => ['label' => 'Document type', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::packDocumentTypes()], 'document_number' => ['label' => 'Number', 'type' => 'text', 'width' => 4], 'issue_date' => ['label' => 'Issue date', 'type' => 'date', 'width' => 3], 'expiry_date' => ['label' => 'Expiry date', 'type' => 'date', 'width' => 3], 'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => ['pending' => 'Pending', 'valid' => 'Valid', 'rejected' => 'Rejected', 'expired' => 'Expired']], 'document_file' => ['label' => 'File', 'type' => 'file', 'width' => 3], 'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12]],
            ],
            'pre_dispatch_checks' => [
                'title' => 'Pre-dispatch checks', 'singular' => 'Pre-dispatch check', 'kicker' => 'Dispatch readiness', 'icon' => 'check-square', 'description' => 'Final cargo, document, truck, driver and route check before dispatch.', 'button' => 'Run check', 'table' => 'shipment_pre_dispatch_checks', 'alias' => 'pc', 'code' => 'id', 'order' => 'pc.id DESC', 'soft_delete' => false,
                'joins' => 'INNER JOIN shipments s ON s.id = pc.shipment_id', 'select' => ['pc.id', 's.shipment_code AS shipment', 'pc.cargo_loaded', 'pc.documents_verified', 'pc.vehicle_verified', 'pc.driver_verified', 'pc.route_verified', 'pc.status', 'pc.checked_at'], 'search' => ['s.shipment_code'], 'list' => ['shipment' => ['label' => 'Shipment', 'type' => 'code'], 'cargo_loaded' => ['label' => 'Loaded', 'type' => 'yesno'], 'documents_verified' => ['label' => 'Documents', 'type' => 'yesno'], 'vehicle_verified' => ['label' => 'Truck', 'type' => 'yesno'], 'driver_verified' => ['label' => 'Driver', 'type' => 'yesno'], 'route_verified' => ['label' => 'Route', 'type' => 'yesno'], 'status' => ['label' => 'Status', 'type' => 'badge']], 'filters' => ['status' => ['label' => 'Status', 'column' => 'pc.status', 'options' => ['pending' => 'Pending', 'ready' => 'Ready for dispatch', 'not_ready' => 'Not ready']]],
                'sections' => [['title' => 'Final check', 'icon' => 'check-square', 'fields' => ['shipment_id', 'cargo_loaded', 'quantity_verified', 'packaging_verified', 'documents_verified', 'vehicle_verified', 'driver_verified', 'route_verified', 'status']], ['title' => 'Record', 'icon' => 'file-text', 'fields' => ['checked_by', 'checked_at', 'notes']]],
                'fields' => ['shipment_id' => ['label' => 'Shipment', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => $shipment], 'cargo_loaded' => ['label' => 'Cargo loaded', 'type' => 'checkbox', 'width' => 3], 'quantity_verified' => ['label' => 'Quantity verified', 'type' => 'checkbox', 'width' => 3], 'packaging_verified' => ['label' => 'Packaging verified', 'type' => 'checkbox', 'width' => 3], 'documents_verified' => ['label' => 'Documents verified', 'type' => 'checkbox', 'width' => 3], 'vehicle_verified' => ['label' => 'Truck verified', 'type' => 'checkbox', 'width' => 3], 'driver_verified' => ['label' => 'Driver verified', 'type' => 'checkbox', 'width' => 3], 'route_verified' => ['label' => 'Route verified', 'type' => 'checkbox', 'width' => 3], 'status' => ['label' => 'Readiness', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => ['pending' => 'Pending', 'ready' => 'Ready for dispatch', 'not_ready' => 'Not ready']], 'checked_by' => ['label' => 'Checked by', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']], 'checked_at' => ['label' => 'Checked at', 'type' => 'datetime', 'width' => 4], 'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12]],
            ],
        ];
    }

    // ------------------------------------------------------ clearance children

    private static function clearance(): array
    {
        $crossingRelation = ['table' => 'border_crossings', 'label' => 'reference', 'where' => 'deleted_at IS NULL'];

        return [
            'clearance_documents' => [
                // A corrected number is carried onto the clearance, so the old one is remembered.
                'track_changes' => true,
                'title' => 'Clearance documents', 'singular' => 'Clearance document', 'kicker' => 'Shipment clearance', 'icon' => 'file-text',
                'description' => 'The supporting papers for each customs clearance, including their validity and attachment.', 'button' => 'Add document',
                'table' => 'border_documents', 'alias' => 'cd', 'code' => 'document_type', 'order' => 'cd.id DESC', 'soft_delete' => false,
                'joins' => 'INNER JOIN border_crossings bc ON bc.id = cd.crossing_id',
                'select' => ['cd.id', 'cd.document_type', 'cd.document_no', 'bc.reference AS clearance', 'cd.issued_on', 'cd.expires_on', 'cd.status'],
                'search' => ['cd.document_type', 'cd.document_no', 'bc.reference'],
                'list' => ['document_type' => ['label' => 'Document', 'type' => 'code'], 'document_no' => ['label' => 'Number', 'empty' => '-'], 'clearance' => ['label' => 'Clearance'], 'issued_on' => ['label' => 'Issued', 'type' => 'date'], 'expires_on' => ['label' => 'Expires', 'type' => 'date'], 'status' => ['label' => 'Status', 'type' => 'badge']],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'cd.status', 'options' => ['pending' => 'Pending', 'valid' => 'Valid', 'rejected' => 'Rejected', 'expired' => 'Expired']]],
                'sections' => [['title' => 'Document', 'icon' => 'file-text', 'fields' => ['crossing_id', 'document_type', 'document_no', 'issued_on', 'expires_on', 'status']], ['title' => 'Attachment and notes', 'icon' => 'paperclip', 'fields' => ['document_file', 'notes']]],
                'fields' => [
                    'crossing_id' => ['label' => 'Clearance', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => $crossingRelation],
                    'document_type' => ['label' => 'Document type', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => Lookup::options('border_document')],
                    'document_no' => ['label' => 'Document number', 'type' => 'text', 'width' => 4],
                    'issued_on' => ['label' => 'Issue date', 'type' => 'date', 'width' => 3], 'expires_on' => ['label' => 'Expiry date', 'type' => 'date', 'width' => 3],
                    'status' => ['label' => 'Document status', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => ['pending' => 'Pending', 'valid' => 'Valid', 'rejected' => 'Rejected', 'expired' => 'Expired']],
                    'document_file' => ['label' => 'Document file', 'type' => 'file', 'width' => 3], 'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
            ],
            'clearance_inspections' => [
                'title' => 'Clearance inspections', 'singular' => 'Inspection', 'kicker' => 'Shipment clearance', 'icon' => 'search', 'description' => 'Customs inspection appointments and outcomes.', 'button' => 'Add inspection',
                'table' => 'clearance_inspections', 'alias' => 'ci', 'code' => 'inspection_type', 'order' => 'ci.scheduled_at DESC, ci.id DESC', 'soft_delete' => false,
                'joins' => 'INNER JOIN border_crossings bc ON bc.id = ci.crossing_id', 'select' => ['ci.id', 'ci.inspection_type', 'bc.reference AS clearance', 'ci.scheduled_at', 'ci.location', 'ci.inspector', 'ci.result'],
                'search' => ['ci.inspection_type', 'ci.location', 'ci.inspector', 'bc.reference'],
                'list' => ['inspection_type' => ['label' => 'Inspection', 'type' => 'code'], 'clearance' => ['label' => 'Clearance'], 'scheduled_at' => ['label' => 'Scheduled', 'type' => 'datetime'], 'location' => ['label' => 'Location'], 'inspector' => ['label' => 'Inspector'], 'result' => ['label' => 'Result', 'type' => 'badge']],
                'filters' => ['result' => ['label' => 'Result', 'column' => 'ci.result', 'options' => ['pending' => 'Pending', 'passed' => 'Passed', 'failed' => 'Failed']]],
                'sections' => [['title' => 'Inspection', 'icon' => 'search', 'fields' => ['crossing_id', 'inspection_type', 'scheduled_at', 'location', 'inspector', 'result']], ['title' => 'Remarks', 'icon' => 'file-text', 'fields' => ['remarks']]],
                'fields' => ['crossing_id' => ['label' => 'Clearance', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => $crossingRelation], 'inspection_type' => ['label' => 'Inspection type', 'type' => 'text', 'required' => true, 'width' => 4], 'scheduled_at' => ['label' => 'Scheduled date', 'type' => 'datetime', 'width' => 4], 'location' => ['label' => 'Location', 'type' => 'text', 'width' => 4], 'inspector' => ['label' => 'Inspector', 'type' => 'text', 'width' => 4], 'result' => ['label' => 'Result', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['pending' => 'Pending', 'passed' => 'Passed', 'failed' => 'Failed']], 'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'width' => 12]],
            ],
            'clearance_payments' => [
                'title' => 'Clearance payments', 'singular' => 'Clearance payment', 'kicker' => 'Shipment clearance', 'icon' => 'credit-card', 'description' => 'Payments made for customs duty, taxes and clearance fees.', 'button' => 'Add payment',
                'table' => 'clearance_payments', 'alias' => 'cp', 'code' => 'reference', 'order' => 'cp.payment_date DESC, cp.id DESC', 'soft_delete' => false,
                'joins' => 'INNER JOIN border_crossings bc ON bc.id = cp.crossing_id', 'select' => ['cp.id', 'cp.reference', 'bc.reference AS clearance', 'cp.amount', 'cp.currency', 'cp.payment_method', 'cp.payment_date', 'cp.status'],
                'search' => ['cp.reference', 'cp.payment_method', 'bc.reference'], 'list' => ['reference' => ['label' => 'Reference', 'type' => 'code'], 'clearance' => ['label' => 'Clearance'], 'amount' => ['label' => 'Amount', 'type' => 'money'], 'payment_method' => ['label' => 'Method', 'type' => 'label'], 'payment_date' => ['label' => 'Date', 'type' => 'date'], 'status' => ['label' => 'Status', 'type' => 'badge']],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'cp.status', 'options' => ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed']]],
                'sections' => [['title' => 'Payment', 'icon' => 'credit-card', 'fields' => ['crossing_id', 'amount', 'currency', 'payment_method', 'reference', 'payment_date', 'status']], ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']]],
                'fields' => ['crossing_id' => ['label' => 'Clearance', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => $crossingRelation], 'amount' => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'width' => 3], 'currency' => ['label' => 'Currency', 'type' => 'select', 'required' => true, 'width' => 2, 'options' => Currency::options()], 'payment_method' => ['label' => 'Payment method', 'type' => 'select', 'width' => 3, 'options' => PaymentMethod::options('out')], 'reference' => ['label' => 'Payment reference', 'type' => 'text', 'width' => 4], 'payment_date' => ['label' => 'Payment date', 'type' => 'date', 'width' => 3], 'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed']], 'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12]],
            ],
            'clearance_releases' => [
                'title' => 'Clearance releases', 'singular' => 'Release', 'kicker' => 'Shipment clearance', 'icon' => 'check-circle', 'description' => 'The formal customs release issued after clearance.', 'button' => 'Add release',
                'table' => 'clearance_releases', 'alias' => 'cr', 'code' => 'release_number', 'order' => 'cr.release_date DESC, cr.id DESC', 'soft_delete' => false,
                'joins' => 'INNER JOIN border_crossings bc ON bc.id = cr.crossing_id LEFT JOIN users u ON u.id = cr.released_by', 'select' => ['cr.id', 'cr.release_number', 'bc.reference AS clearance', 'cr.release_date', 'u.full_name AS released_by'],
                'search' => ['cr.release_number', 'bc.reference', 'u.full_name'], 'list' => ['release_number' => ['label' => 'Release number', 'type' => 'code'], 'clearance' => ['label' => 'Clearance'], 'release_date' => ['label' => 'Release date', 'type' => 'date'], 'released_by' => ['label' => 'Released by']],
                'sections' => [['title' => 'Release', 'icon' => 'check-circle', 'fields' => ['crossing_id', 'release_number', 'release_date', 'released_by']], ['title' => 'Remarks', 'icon' => 'file-text', 'fields' => ['remarks']]],
                'fields' => ['crossing_id' => ['label' => 'Clearance', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => $crossingRelation], 'release_number' => ['label' => 'Release number', 'type' => 'text', 'required' => true, 'width' => 4], 'release_date' => ['label' => 'Release date', 'type' => 'date', 'required' => true, 'width' => 4], 'released_by' => ['label' => 'Released by', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']], 'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'width' => 12]],
            ],
        ];
    }

    // ------------------------------------------------------------ commercial

    private static function commercial(): array
    {
        return [
            'border_posts' => [
                'title' => 'Border posts',
                'singular' => 'Border post',
                'kicker' => 'Cross-border',
                'icon' => 'flag',
                'description' => 'The crossings this company actually uses, with how long each one normally takes. The typical hours are for planning a run, and for seeing at a glance when a crossing is going badly.',
                'button' => 'Add border post',
                'table' => 'border_posts',
                'alias' => 'bp',
                'code' => 'post_name',
                'order' => 'bp.post_name',
                'select' => ['bp.id', 'bp.post_name', 'bp.post_code', 'bp.country_a', 'bp.country_b', 'bp.is_one_stop', 'bp.typical_hours', 'bp.contact_phone', 'bp.is_active'],
                'search' => ['bp.post_name', 'bp.post_code', 'bp.country_a', 'bp.country_b'],
                'list' => [
                    'post_name' => ['label' => 'Post', 'type' => 'code'],
                    'post_code' => ['label' => 'Code', 'empty' => '—'],
                    'country_a' => ['label' => 'From'],
                    'country_b' => ['label' => 'To'],
                    'is_one_stop' => ['label' => 'One stop', 'type' => 'yesno'],
                    'typical_hours' => ['label' => 'Normally takes', 'type' => 'decimal', 'suffix' => 'h'],
                    'is_active' => ['label' => 'In use', 'type' => 'yesno'],
                ],
                'filters' => ['is_active' => ['label' => 'In use', 'column' => 'bp.is_active', 'options' => [1 => 'Yes', 0 => 'No']]],
                'sections' => [
                    ['title' => 'The post', 'icon' => 'flag', 'hint' => 'A one-stop post puts both countries under one roof.', 'fields' => ['post_name', 'post_code', 'country_a', 'country_b', 'is_one_stop', 'typical_hours']],
                    ['title' => 'Who to call', 'icon' => 'phone', 'fields' => ['contact_name', 'contact_phone', 'is_active']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'post_name' => ['label' => 'Post name', 'type' => 'text', 'required' => true, 'width' => 4, 'placeholder' => 'Gatuna / Katuna'],
                    'post_code' => ['label' => 'Customs code', 'type' => 'text', 'width' => 2],
                    'country_a' => ['label' => 'Country this side', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => 'Rwanda'],
                    'country_b' => ['label' => 'Country the other side', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => 'Uganda'],
                    'is_one_stop' => ['label' => 'One-stop post', 'type' => 'checkbox', 'width' => 4],
                    'typical_hours' => ['label' => 'Normally takes', 'type' => 'decimal', 'width' => 4, 'suffix' => 'h', ],
                    'contact_name' => ['label' => 'Contact', 'type' => 'text', 'width' => 4],
                    'contact_phone' => ['label' => 'Phone', 'type' => 'tel', 'width' => 4],
                    'is_active' => ['label' => 'Still used', 'type' => 'checkbox', 'width' => 4],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12, 'placeholder' => 'Closes at 18:00. Weighbridge on the Ugandan side only.'],
                ],
            ],

            'crossings' => [
                'title' => 'Customs clearance',
                'singular' => 'Clearance record',
                'kicker' => 'Shipment clearance',
                'icon' => 'flag',
                'description' => 'A truck at a border post: which papers it is waiting on, what was paid to release it, and how long it stood there. The four times are the point of the record — the gaps between them are where a cross-border run loses its money.',
                'button' => 'Start clearance',
                'table' => 'border_crossings',
                'alias' => 'bc',
                'code' => 'reference',
                'order' => 'bc.arrived_at DESC, bc.id DESC',
                'track_changes' => true,
                'joins' => 'INNER JOIN border_posts bp ON bp.id = bc.border_post_id LEFT JOIN trips t ON t.id = bc.trip_id LEFT JOIN vehicles v ON v.id = bc.vehicle_id LEFT JOIN drivers d ON d.id = bc.driver_id LEFT JOIN suppliers s ON s.id = bc.clearing_agent_id',
                'select' => ['bc.id', 'bc.reference', 'bp.post_name', 't.reference_code AS trip', 'v.plate_number AS vehicle', 'd.full_name AS driver', 'bc.direction', 'bc.declaration_no', 'bc.arrived_at', 'bc.departed_at', 'bc.charges_total', 'bc.currency', 'bc.status'],
                'search' => ['bc.reference', 'bc.declaration_no', 'bc.transit_bond_no', 'bp.post_name', 'v.plate_number'],
                'scope' => ['driver' => 'bc.driver_id'],
                'list' => [
                    'reference' => ['label' => 'Reference', 'type' => 'code'],
                    'post_name' => ['label' => 'Post'],
                    'vehicle' => ['label' => 'Vehicle', 'empty' => '—'],
                    'direction' => ['label' => 'Direction', 'type' => 'label'],
                    'declaration_no' => ['label' => 'Declaration', 'empty' => 'Not lodged'],
                    'arrived_at' => ['label' => 'Arrived', 'type' => 'datetime'],
                    'departed_at' => ['label' => 'Left', 'type' => 'datetime', 'empty' => 'Still there'],
                    'charges_total' => ['label' => 'Charges', 'type' => 'money'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'bc.status', 'options' => self::clearanceStatuses()],
                    'border_post_id' => ['label' => 'Post', 'column' => 'bc.border_post_id', 'options' => []],
                    'direction' => ['label' => 'Direction', 'column' => 'bc.direction', 'options' => ['export' => 'Export', 'import' => 'Import', 'transit' => 'Transit']],
                ],
                'sections' => [
                    ['title' => 'The clearance', 'icon' => 'flag', 'hint' => 'Each clearance belongs directly to the shipment customs is processing.', 'fields' => ['reference', 'border_post_id', 'customs_country', 'direction', 'trip_id', 'shipment_id']],
                    ['title' => 'Truck and agent', 'icon' => 'truck', 'fields' => ['vehicle_id', 'driver_id', 'clearing_agent_id']],
                    ['title' => 'Customs references', 'icon' => 'hash', 'fields' => ['declaration_no', 'transit_bond_no', 'seal_no', 'weighbridge_kg']],
                    ['title' => 'The clock', 'icon' => 'clock', 'hint' => 'The workflow stamps these milestones as clearance advances.', 'fields' => ['arrived_at', 'submitted_at', 'lodged_at', 'approved_at', 'cleared_at', 'released_at', 'departed_at']],
                    ['title' => 'What it cost', 'icon' => 'dollar-sign', 'hint' => 'Add each charge under Border charges below.', 'fields' => ['charges_total', 'currency']],
                    ['title' => 'Held or noted', 'icon' => 'alert-triangle', 'fields' => ['status', 'hold_reason', 'notes']],
                ],
                'fields' => [
                    'reference' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 3, 'auto' => 'BDR'],
                    'border_post_id' => ['label' => 'Border post', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'border_posts', 'label' => 'post_name', 'where' => 'deleted_at IS NULL AND is_active = 1']],
                    'direction' => ['label' => 'Direction', 'type' => 'select', 'required' => true, 'width' => 2, 'options' => ['export' => 'Export', 'import' => 'Import', 'transit' => 'Transit']],
                    'trip_id' => ['label' => 'On trip', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL'], ],
                    'shipment_id' => ['label' => 'Shipment', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'shipments', 'label' => 'shipment_code', 'where' => 'deleted_at IS NULL', 'fills' => ['trip_id' => 'trip_id', 'seal_no' => 'seal_no']], 'help' => 'The shipment customs is processing.'],
                    'customs_country' => ['label' => 'Customs country', 'type' => 'text', 'width' => 3, 'placeholder' => 'Rwanda'],
                    'vehicle_id' => ['label' => 'Vehicle', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'vehicles', 'label' => 'plate_number', 'where' => 'deleted_at IS NULL']],
                    'driver_id' => ['label' => 'Driver', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'drivers', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'clearing_agent_id' => ['label' => 'Clearing agent', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'suppliers', 'label' => 'supplier_name', 'where' => "deleted_at IS NULL AND status = 'active' AND (category = 'Clearing agent' OR id IN (SELECT clearing_agent_id FROM border_crossings WHERE clearing_agent_id IS NOT NULL))"], ],
                    'declaration_no' => ['label' => 'Declaration number', 'type' => 'text', 'width' => 3],
                    'transit_bond_no' => ['label' => 'T1 / bond number', 'type' => 'text', 'width' => 3],
                    'seal_no' => ['label' => 'Seal number', 'type' => 'text', 'width' => 3],
                    'weighbridge_kg' => ['label' => 'Weighbridge', 'type' => 'decimal', 'width' => 3, 'suffix' => 'kg'],
                    'arrived_at' => ['label' => 'Arrived at the post', 'type' => 'datetime', 'width' => 3],
                    'lodged_at' => ['label' => 'Declaration lodged', 'type' => 'datetime', 'width' => 3],
                    'submitted_at' => ['label' => 'Submitted', 'type' => 'datetime', 'width' => 3, 'readonly' => true, 'readonly_note' => 'from workflow'],
                    'approved_at' => ['label' => 'Approved for assessment', 'type' => 'datetime', 'width' => 3, 'readonly' => true, 'readonly_note' => 'from workflow'],
                    'cleared_at' => ['label' => 'Released by customs', 'type' => 'datetime', 'width' => 3],
                    'released_at' => ['label' => 'Formal release', 'type' => 'datetime', 'width' => 3, 'readonly' => true, 'readonly_note' => 'from workflow'],
                    'departed_at' => ['label' => 'Left the post', 'type' => 'datetime', 'width' => 3],
                    'charges_total' => ['label' => 'Charges', 'type' => 'money', 'width' => 3, 'readonly' => true, 'readonly_note' => 'added from the lines', 'help' => 'Added up from the charge lines.'],
                    'currency' => ['label' => 'Paid in', 'type' => 'select', 'width' => 3, 'options' => Currency::options(), ],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::clearanceStatuses(), 'readonly' => true, 'readonly_note' => 'set by the workflow'],
                    'hold_reason' => ['label' => 'Held because', 'type' => 'text', 'width' => 8, 'readonly' => true, 'readonly_note' => 'from the hold'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'border_charges',
                    'parent' => 'crossing_id',
                    'title' => 'Border charges',
                    'total_column' => 'charges_total',
                    'line_amount' => 'amount',
                    'columns' => [
                        'charge_type' => ['label' => 'Charge', 'type' => 'select', 'required' => true, 'options' => Lookup::options('border_charge')],
                        'description' => ['label' => 'Description', 'type' => 'text'],
                        'receipt_no' => ['label' => 'Receipt', 'type' => 'text'],
                        'tax_rate' => ['label' => 'Rate %', 'type' => 'decimal'],
                        'taxable_amount' => ['label' => 'Taxable amount', 'type' => 'money'],
                        'amount' => ['label' => 'Amount', 'type' => 'money'],
                        'paid_for' => ['label' => 'Paid for', 'type' => 'select', 'default' => 'us', 'options' => ['us' => 'Our cost', 'customer' => 'Customer (recharge)']],
                        'status' => ['label' => 'Payment', 'type' => 'select', 'options' => ['pending' => 'Pending', 'paid' => 'Paid', 'waived' => 'Waived']],
                    ],
                ],
                'links' => [
                    ['label' => 'Email the clearing agent', 'route' => ['crossings', ':id', 'agent-email'], 'permission' => 'crossings', 'ability' => 'edit', 'tone' => 'primary', 'icon' => 'mail',
                     'hint' => 'The clearing instruction with the load details and the papers attached, sent from here. The reply comes to your email.'],
                    ['label' => 'Add document', 'route' => ['clearance_documents', 'create'], 'permission' => 'clearance_documents', 'ability' => 'create', 'tone' => 'secondary', 'icon' => 'file-text', 'carry' => ['crossing_id' => 'id']],
                    ['label' => 'Add inspection', 'route' => ['clearance_inspections', 'create'], 'permission' => 'clearance_inspections', 'ability' => 'create', 'tone' => 'secondary', 'icon' => 'search', 'carry' => ['crossing_id' => 'id']],
                    ['label' => 'Add payment', 'route' => ['clearance_payments', 'create'], 'permission' => 'clearance_payments', 'ability' => 'create', 'tone' => 'secondary', 'icon' => 'credit-card', 'carry' => ['crossing_id' => 'id']],
                    ['label' => 'Create release', 'route' => ['clearance_releases', 'create'], 'permission' => 'clearance_releases', 'ability' => 'create', 'tone' => 'secondary', 'icon' => 'check-circle', 'carry' => ['crossing_id' => 'id']],
                ],
                'actions' => [
                    'prepare_documents' => ['label' => 'Collect documents', 'to' => 'documents_pending', 'from' => ['draft'], 'tone' => 'primary'],
                    'submit_declaration' => ['label' => 'Submit declaration', 'to' => 'declaration_submitted', 'from' => ['draft', 'documents_pending'], 'tone' => 'primary'],
                    'start_review' => ['label' => 'Start review', 'to' => 'under_review', 'from' => ['declaration_submitted'], 'tone' => 'primary'],
                    'request_inspection' => ['label' => 'Request inspection', 'to' => 'inspection', 'from' => ['under_review'], 'tone' => 'warning'],
                    'pass_inspection' => ['label' => 'Inspection passed', 'to' => 'duties_pending', 'from' => ['inspection'], 'tone' => 'success'],
                    'assess_duties' => ['label' => 'Assess duties', 'to' => 'duties_pending', 'from' => ['under_review'], 'tone' => 'primary'],
                    'request_payment' => ['label' => 'Request payment', 'to' => 'payment_pending', 'from' => ['duties_pending'], 'tone' => 'warning'],
                    'clear' => ['label' => 'Mark cleared', 'to' => 'cleared', 'from' => ['payment_pending'], 'tone' => 'success'],
                    'release' => ['label' => 'Mark released', 'to' => 'released', 'from' => ['cleared'], 'tone' => 'success'],
                    'reject' => ['label' => 'Reject clearance', 'to' => 'rejected', 'from' => ['draft', 'documents_pending', 'declaration_submitted', 'under_review', 'inspection', 'duties_pending', 'payment_pending'], 'tone' => 'danger', 'reason' => true],
                ],
                'related' => [
                    ['title' => 'Clearance documents', 'icon' => 'file-text', 'permission' => 'clearance_documents', 'module' => 'clearance_documents',
                     'sql' => 'SELECT id, document_type, document_no, issued_on, expires_on, status FROM border_documents WHERE crossing_id = :id ORDER BY status, id',
                     'columns' => ['document_type' => 'Document', 'document_no' => 'Number', 'issued_on' => 'Issued', 'expires_on' => 'Expires', 'status' => 'Status'], 'empty' => 'No clearance documents have been listed yet.'],
                    ['title' => 'Inspections', 'icon' => 'search', 'permission' => 'clearance_inspections', 'module' => 'clearance_inspections',
                     'sql' => 'SELECT id, inspection_type, scheduled_at, location, inspector, result FROM clearance_inspections WHERE crossing_id = :id ORDER BY scheduled_at DESC, id DESC',
                     'columns' => ['inspection_type' => 'Inspection', 'scheduled_at' => 'Scheduled', 'location' => 'Location', 'inspector' => 'Inspector', 'result' => 'Result'], 'empty' => 'No inspection has been recorded.'],
                    ['title' => 'Payments', 'icon' => 'credit-card', 'permission' => 'clearance_payments', 'module' => 'clearance_payments',
                     'sql' => 'SELECT id, reference, amount, currency, payment_date, status FROM clearance_payments WHERE crossing_id = :id ORDER BY payment_date DESC, id DESC',
                     'columns' => ['reference' => 'Reference', 'amount' => 'Amount', 'currency' => 'Currency', 'payment_date' => 'Date', 'status' => 'Status'], 'empty' => 'No clearance payment has been recorded.'],
                    ['title' => 'Emails to the clearing agent', 'icon' => 'mail', 'permission' => 'crossings',
                     'sql' => "SELECT id, DATE_FORMAT(created_at, '%d %b %Y %H:%i') AS sent, to_email, subject, JSON_LENGTH(COALESCE(attachments, '[]')) AS files, status FROM email_outbox WHERE entity_type = 'border_crossings' AND entity_id = (SELECT reference FROM border_crossings WHERE id = :id) ORDER BY id DESC",
                     'columns' => ['sent' => 'Sent', 'to_email' => 'To', 'subject' => 'Subject', 'files' => 'Documents', 'status' => 'Status'], 'empty' => 'Nothing emailed to the agent yet.'],
                    ['title' => 'Formal release', 'icon' => 'check-circle', 'permission' => 'clearance_releases', 'module' => 'clearance_releases',
                     'sql' => 'SELECT id, release_number, release_date, remarks FROM clearance_releases WHERE crossing_id = :id',
                     'columns' => ['release_number' => 'Release number', 'release_date' => 'Release date', 'remarks' => 'Remarks'], 'empty' => 'No formal release has been recorded.'],
                ],
            ],

            'customers' => [
                'title' => 'Customers',
                'singular' => 'Customer',
                'kicker' => 'Commercial register',
                'icon' => 'briefcase',
                'description' => 'The organisations the company moves goods for, with billing terms and credit limits.',
                'button' => 'Add customer',
                'table' => 'customers',
                'alias' => 'c',
                'code' => 'customer_code',
                'order' => 'c.customer_name',
                'joins' => '',
                'select' => ['c.id', 'c.customer_code', 'c.customer_name', 'c.customer_type', 'c.contact_name', 'c.phone', 'c.district', 'c.payment_terms_days', 'c.credit_limit', 'c.currency', 'c.status'],
                'search' => ['c.customer_code', 'c.customer_name', 'c.contact_name', 'c.phone', 'c.tin_number'],
                'list' => [
                    'customer_code' => ['label' => 'Code', 'type' => 'code'],
                    'customer_name' => ['label' => 'Customer'],
                    'customer_type' => ['label' => 'Type', 'type' => 'label'],
                    'contact_name' => ['label' => 'Contact'],
                    'phone' => ['label' => 'Phone'],
                    'district' => ['label' => 'District'],
                    'payment_terms_days' => ['label' => 'Terms', 'type' => 'number', 'suffix' => 'days'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'c.status', 'options' => ['active' => 'Active', 'on_hold' => 'On hold', 'inactive' => 'Inactive']],
                    'customer_type' => ['label' => 'Type', 'column' => 'c.customer_type', 'options' => ['corporate' => 'Corporate', 'government' => 'Government', 'ngo' => 'NGO', 'individual' => 'Individual']],
                ],
                'sections' => [
                    ['title' => 'Identity', 'icon' => 'briefcase', 'fields' => ['customer_code', 'customer_name', 'customer_type', 'tin_number']],
                    ['title' => 'Contact', 'icon' => 'phone', 'fields' => ['contact_name', 'phone', 'email', 'address', 'district']],
                    ['title' => 'Commercial terms', 'icon' => 'dollar-sign', 'hint' => 'Payment terms set the due date on every invoice raised for this customer.', 'fields' => ['payment_terms_days', 'credit_limit', 'currency', 'status']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'customer_code' => ['label' => 'Customer code', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'CUS'],
                    'customer_name' => ['label' => 'Customer name', 'type' => 'text', 'required' => true, 'width' => 5],
                    'customer_type' => ['label' => 'Type', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => ['corporate' => 'Corporate', 'government' => 'Government', 'ngo' => 'NGO', 'individual' => 'Individual']],
                    'tin_number' => ['label' => 'TIN number', 'type' => 'text', 'width' => 4],
                    'contact_name' => ['label' => 'Contact person', 'type' => 'text', 'width' => 4],
                    'phone' => ['label' => 'Phone', 'type' => 'tel', 'width' => 4],
                    'email' => ['label' => 'Email', 'type' => 'email', 'width' => 4],
                    'address' => ['label' => 'Address', 'type' => 'text', 'width' => 5],
                    'district' => ['label' => 'District', 'type' => 'text', 'width' => 3],
                    'payment_terms_days' => ['label' => 'Payment terms', 'type' => 'number', 'width' => 4, 'suffix' => 'days'],
                    'credit_limit' => ['label' => 'Credit limit', 'type' => 'money', 'width' => 3],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 2, 'options' => Currency::options(), 'help' => 'What this customer is billed in.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['active' => 'Active', 'on_hold' => 'On hold', 'inactive' => 'Inactive']],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'related' => [
                    ['title' => 'Rate cards', 'icon' => 'tag', 'permission' => 'rates', 'module' => 'rates',
                     'sql' => 'SELECT id, rate_code, origin, destination, rate_type, rate_amount, status FROM rate_cards WHERE customer_id = :id AND deleted_at IS NULL ORDER BY origin',
                     'columns' => ['rate_code' => 'Rate', 'origin' => 'From', 'destination' => 'To', 'rate_type' => 'Basis', 'rate_amount' => 'Amount', 'status' => 'Status'],
                     'empty' => 'No agreed rate for this customer yet.'],
                    ['title' => 'Invoices', 'icon' => 'file-text', 'permission' => 'invoices', 'module' => 'invoices',
                     'sql' => 'SELECT id, invoice_number, issue_date, due_date, total_amount, amount_paid, status FROM invoices WHERE customer_id = :id AND deleted_at IS NULL ORDER BY issue_date DESC LIMIT 10',
                     'columns' => ['invoice_number' => 'Invoice', 'issue_date' => 'Issued', 'due_date' => 'Due', 'total_amount' => 'Total', 'amount_paid' => 'Paid', 'status' => 'Status'],
                     'empty' => 'No invoice raised yet.'],
                    ['title' => 'Recent trips', 'icon' => 'navigation', 'permission' => 'trips', 'module' => 'trips',
                     'sql' => 'SELECT id, reference_code, pickup_location, destination, status, departure_at FROM trips WHERE customer_id = :id AND deleted_at IS NULL ORDER BY id DESC LIMIT 10',
                     'columns' => ['reference_code' => 'Trip', 'pickup_location' => 'From', 'destination' => 'To', 'status' => 'Status', 'departure_at' => 'Departed'],
                     'empty' => 'No trip run for this customer yet.'],
                ],
            ],

            'price_offers' => [
                'title' => 'Price offers',
                'singular' => 'Price offer',
                'kicker' => 'Prices sent to customers',
                'icon' => 'send',
                'description' => 'The rate cards picked for one customer and emailed to them.',
                'button' => 'New price offer',
                'table' => 'price_offers',
                'alias' => 'po',
                'code' => 'offer_code',
                'order' => 'po.id DESC',
                'joins' => 'LEFT JOIN customers c ON c.id = po.customer_id',
                'select' => ['po.id', 'po.offer_code', 'c.customer_name AS customer', 'po.sent_at', 'po.valid_until', 'po.accepted_at', 'po.status',
                             '(SELECT COUNT(*) FROM price_offer_lines l WHERE l.offer_id = po.id) AS routes'],
                'search' => ['po.offer_code', 'c.customer_name'],
                'list' => [
                    'offer_code' => ['label' => 'Offer', 'type' => 'code'],
                    'customer' => ['label' => 'Customer'],
                    'routes' => ['label' => 'Routes', 'type' => 'number'],
                    'sent_at' => ['label' => 'Sent', 'type' => 'datetime', 'empty' => 'Not yet'],
                    'valid_until' => ['label' => 'Valid until', 'type' => 'expiry', 'empty' => '—'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'po.status', 'options' => PriceOffer::STATUS]],
                'sections' => [
                    ['title' => 'The offer', 'icon' => 'send', 'fields' => ['offer_code', 'customer_id', 'valid_until', 'message']],
                    ['title' => 'Answer', 'icon' => 'check-circle', 'fields' => ['status', 'sent_at', 'accepted_by', 'accepted_at', 'lpo_sent_at', 'notes']],
                ],
                'fields' => [
                    'offer_code' => ['label' => 'Offer number', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'OFR'],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => "deleted_at IS NULL AND status = 'active'"]],
                    'valid_until' => ['label' => 'Valid until', 'type' => 'date', 'width' => 4],
                    'message' => ['label' => 'Message to the customer', 'type' => 'textarea', 'width' => 12, 'placeholder' => 'As you requested, please find below our rates for the transport of your goods.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 3, 'options' => PriceOffer::STATUS, 'readonly' => true, 'readonly_note' => 'set by the buttons'],
                    'sent_at' => ['label' => 'Sent at', 'type' => 'datetime', 'width' => 3, 'readonly' => true],
                    'accepted_by' => ['label' => 'Accepted by', 'type' => 'text', 'width' => 3, 'placeholder' => 'Diane Uwase'],
                    'accepted_at' => ['label' => 'Accepted at', 'type' => 'datetime', 'width' => 3, 'readonly' => true],
                    'lpo_sent_at' => ['label' => 'LPO form sent', 'type' => 'datetime', 'width' => 3, 'readonly' => true],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'price_offer_lines',
                    'parent' => 'offer_id',
                    'title' => 'Rates offered',
                    'columns' => [
                        'rate_card_id' => ['label' => 'Rate card', 'type' => 'relation', 'required' => true, 'relation' => ['table' => 'rate_cards', 'label' => 'rate_code', 'where' => "deleted_at IS NULL AND status = 'active' AND (effective_to IS NULL OR effective_to >= CURDATE())"]],
                        'note' => ['label' => 'Note for the customer', 'type' => 'text'],
                    ],
                ],
                'actions' => [
                    'send' => ['label' => 'Send to customer', 'to' => 'sent', 'from' => ['draft', 'sent'], 'tone' => 'primary', 'page' => 'email'],
                    'accept' => ['label' => 'Customer accepted', 'to' => 'accepted', 'from' => ['sent'], 'tone' => 'success'],
                    'decline' => ['label' => 'Customer declined', 'to' => 'declined', 'from' => ['draft', 'sent'], 'tone' => 'danger', 'reason' => true],
                ],
                'links' => [
                    ['label' => 'Send LPO form', 'icon' => 'mail', 'tone' => 'primary',
                     'permission' => 'price_offers', 'ability' => 'approve',
                     'when' => ['status' => ['accepted']],
                     'route' => ['price_offers', ':id', 'lpo-email']],
                    ['label' => 'LPO form (PDF)', 'icon' => 'file-text', 'tone' => 'outline-secondary',
                     'permission' => 'price_offers', 'ability' => 'view',
                     'when' => ['status' => ['accepted']], 'new_tab' => true,
                     'route' => ['price_offers', ':id', 'lpo']],
                    ['label' => 'Raise transport request', 'icon' => 'clipboard', 'tone' => 'success',
                     'permission' => 'requests', 'ability' => 'create',
                     'when' => ['status' => ['accepted']],
                     'route' => ['requests', 'create'], 'carry' => ['price_offer_id' => 'id']],
                ],
                'related' => [
                    ['title' => 'Requests raised from this offer', 'icon' => 'clipboard', 'permission' => 'requests', 'module' => 'requests',
                     'sql' => 'SELECT id, reference_code, pickup_location, destination, status FROM transport_requests WHERE price_offer_id = :id AND deleted_at IS NULL ORDER BY id',
                     'columns' => ['reference_code' => 'Request', 'pickup_location' => 'From', 'destination' => 'To', 'status' => 'Status'],
                     'empty' => 'No request yet.'],
                ],
            ],

            'rates' => [
                'title' => 'Rate cards',
                'singular' => 'Rate card',
                'kicker' => 'Pricing',
                'icon' => 'tag',
                'description' => 'The company\'s prices, one card per route. Leave Customer empty for a price everyone gets; name a customer only for a special agreed price. Send cards to a customer from Price offers.',
                'list_links' => [
                    ['label' => 'Send prices to a customer', 'icon' => 'send', 'route' => ['price_offers', 'create'], 'permission' => 'price_offers', 'ability' => 'create'],
                ],
                'button' => 'Add rate',
                'table' => 'rate_cards',
                'alias' => 'rc',
                'code' => 'rate_code',
                'order' => 'rc.id DESC',
                'joins' => 'LEFT JOIN customers c ON c.id = rc.customer_id',
                'select' => ['rc.id', 'rc.rate_code', 'rc.service_type', 'c.customer_name AS customer', 'rc.origin', 'rc.destination', 'rc.vehicle_type', 'rc.rate_type', 'rc.rate_amount', 'rc.minimum_charge', 'rc.currency', 'rc.effective_from', 'rc.effective_to', 'rc.status'],
                'search' => ['rc.rate_code', 'rc.origin', 'rc.destination', 'c.customer_name'],
                'list' => [
                    'rate_code' => ['label' => 'Rate', 'type' => 'code'],
                    'service_type' => ['label' => 'Service', 'type' => 'label', 'map' => ['full_truck' => 'Full truck', 'shared' => 'Shared truck']],
                    'customer' => ['label' => 'Customer', 'empty' => 'All customers'],
                    'origin' => ['label' => 'From'],
                    'destination' => ['label' => 'To'],
                    'vehicle_type' => ['label' => 'Vehicle type', 'empty' => 'Any'],
                    'rate_type' => ['label' => 'Basis', 'type' => 'label'],
                    'rate_amount' => ['label' => 'Amount', 'type' => 'money'],
                    'minimum_charge' => ['label' => 'Minimum', 'type' => 'money', 'empty' => '—'],
                    'effective_to' => ['label' => 'Valid to', 'type' => 'expiry'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'rc.status', 'options' => ['active' => 'Active', 'draft' => 'Draft', 'expired' => 'Expired']], 'service_type' => ['label' => 'Service', 'column' => 'rc.service_type', 'options' => ['full_truck' => 'Full truck', 'shared' => 'Shared truck']]],
                'sections' => [
                    ['title' => 'Rate', 'icon' => 'tag', 'fields' => ['rate_code', 'service_type', 'customer_id', 'status']],
                    ['title' => 'Route', 'icon' => 'map-pin', 'hint' => 'Name the warehouses, not the places. A stop part way along is its own card.', 'fields' => ['origin_warehouse_id', 'destination_warehouse_id', 'origin', 'destination', 'vehicle_type']],
                    ['title' => 'A full load (only for a price by weight; leave empty otherwise)', 'icon' => 'package', 'hint' => 'The price as you agreed it: a full truck holds this much and costs this. Everything below follows from it.', 'fields' => ['full_load_kg', 'full_load_price', 'currency']],
                    ['title' => 'Pricing', 'icon' => 'dollar-sign', 'hint' => 'By weight divides the full load above. Per trip charges the same however full the truck is.', 'fields' => ['rate_type', 'rate_amount', 'minimum_charge']],
                    ['title' => 'Validity', 'icon' => 'calendar', 'fields' => ['effective_from', 'effective_to', 'notes']],
                ],
                'fields' => [
                    'rate_code' => ['label' => 'Rate reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'RATE'],
                    'service_type' => ['label' => 'Type of service', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['full_truck' => 'Full truck (one customer hires the whole truck)', 'shared' => 'Shared truck (several customers, each pays for their load)']],
                    'customer_id' => ['label' => 'Only for customer (leave empty for all)', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL'], 'help' => 'Blank means this is the default rate for the route.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['active' => 'Active', 'draft' => 'Draft', 'expired' => 'Expired']],
                    'origin' => ['label' => 'Origin', 'type' => 'text', 'required' => true, 'width' => 4, ],
                    'origin_warehouse_id' => ['label' => 'Leaves warehouse', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"], ],
                    'destination' => ['label' => 'Destination', 'type' => 'text', 'required' => true, 'width' => 4],
                    'destination_warehouse_id' => ['label' => 'Arrives at warehouse', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"], ],
                    'vehicle_type' => ['label' => 'Vehicle type', 'type' => 'select', 'width' => 4, 'options' => Lookup::options('vehicle_type'), 'help' => 'Leave blank when the rate applies to any vehicle.'],
                    'full_load_kg' => ['label' => 'A full load is', 'type' => 'decimal', 'width' => 4, 'suffix' => 'kg'],
                    'full_load_price' => ['label' => 'A full load costs', 'type' => 'money', 'width' => 4],
                    'rate_type' => ['label' => 'Charging basis', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['per_trip' => 'Per trip', 'per_kg' => 'Per kilogram', 'per_m3' => 'Per cubic metre', 'per_package' => 'Per package', 'per_day' => 'Per day']],
                    'rate_amount' => ['label' => 'Rate amount', 'type' => 'money', 'width' => 4, 'help' => 'Leave at zero when a full load is filled in above.'],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 4, 'options' => Currency::options(), 'help' => 'Applies to every figure on this card.'],
                    'minimum_charge' => ['label' => 'Minimum charge', 'type' => 'money', 'width' => 4],
                    'effective_from' => ['label' => 'Effective from', 'type' => 'date', 'required' => true, 'width' => 4],
                    'effective_to' => ['label' => 'Effective to', 'type' => 'date', 'width' => 4],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
            ],

            'payments' => [
                'title' => 'Payments received',
                'singular' => 'Payment',
                'kicker' => 'Collections',
                'icon' => 'credit-card',
                'description' => 'Money received against an invoice. Recording one settles the invoice and posts to the bank account it arrived in.',
                'button' => 'Record payment',
                'table' => 'payments',
                'alias' => 'pm',
                'code' => 'payment_code',
                'order' => 'pm.paid_at DESC, pm.id DESC',
                'joins' => 'INNER JOIN invoices i ON i.id = pm.invoice_id LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN users u ON u.id = pm.recorded_by',
                'select' => ['pm.id', 'pm.payment_code', 'i.invoice_number AS invoice', 'c.customer_name AS customer', 'pm.currency', 'pm.amount', 'pm.method', 'pm.reference', 'pm.paid_at', 'u.full_name AS recorded_by_name'],
                'search' => ['pm.payment_code', 'pm.reference', 'i.invoice_number', 'c.customer_name'],
                'list' => [
                    'payment_code' => ['label' => 'Payment', 'type' => 'code'],
                    'invoice' => ['label' => 'Invoice'],
                    'customer' => ['label' => 'Customer'],
                    'currency' => ['label' => 'Cur.'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'method' => ['label' => 'Method', 'type' => 'label'],
                    'reference' => ['label' => 'Bank reference', 'empty' => 'None'],
                    'paid_at' => ['label' => 'Received', 'type' => 'datetime'],
                    'recorded_by_name' => ['label' => 'Recorded by', 'empty' => 'System'],
                ],
                'filters' => ['method' => ['label' => 'Method', 'column' => 'pm.method', 'options' => PaymentMethod::options('in')]],
                'sections' => [
                    ['title' => 'Payment', 'icon' => 'credit-card', 'hint' => 'The method decides which bank or cash account the ledger debits.', 'fields' => ['payment_code', 'invoice_id', 'amount', 'method']],
                    ['title' => 'Evidence', 'icon' => 'hash', 'fields' => ['reference', 'paid_at', 'recorded_by']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'payment_code' => ['label' => 'Payment reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'PAY'],
                    'invoice_id' => ['label' => 'Against invoice', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'invoices', 'label' => 'invoice_number', 'where' => "deleted_at IS NULL AND status NOT IN ('draft','cancelled')"]],
                    'amount' => ['label' => 'Amount received', 'type' => 'money', 'required' => true, 'width' => 4, 'help' => 'In the invoice\'s own currency (a USD invoice is paid in USD). It cannot be more than the balance still owed.'],
                    'method' => ['label' => 'Method', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => PaymentMethod::options('in'), 'help' => 'Set these up under Accounting, Payment methods. Each one posts to its own account.'],
                    'reference' => ['label' => 'Bank or till reference', 'type' => 'text', 'width' => 4, 'placeholder' => 'BK-778120'],
                    'paid_at' => ['label' => 'Received at', 'type' => 'datetime', 'required' => true, 'width' => 4],
                    'recorded_by' => ['label' => 'Recorded by', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'you', 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL'], 'help' => 'Whoever is signed in when the payment is entered. It is recorded, not chosen.'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'related' => [
                    self::moneyMovement('payments', "SELECT p.id, CONCAT('Customer: ', c.customer_name) AS from_side, " . self::methodAccountSql('pm') . " AS to_side,
                                   CONCAT(p.currency, ' ', FORMAT(p.amount, 2)) AS amount_text, CONCAT(a.account_code, ' ', a.account_name) AS ledger, p.reference
                              FROM payments p
                              INNER JOIN invoices i ON i.id = p.invoice_id
                              LEFT JOIN customers c ON c.id = i.customer_id
                              LEFT JOIN payment_methods pm ON pm.method_key = p.method
                              LEFT JOIN gl_accounts a ON a.id = pm.gl_account_id
                             WHERE p.id = :id"),
                ],
            ],

            'invoices' => [
                'title' => 'Invoices',
                'singular' => 'Invoice',
                'kicker' => 'Revenue',
                'icon' => 'file-text',
                'description' => 'Customer invoices and payments; the revenue side that makes trip profitability real.',
                'button' => 'Raise invoice',
                'table' => 'invoices',
                'alias' => 'i',
                'code' => 'invoice_number',
                'order' => 'i.id DESC',
                'joins' => 'LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN trips t ON t.id = i.trip_id',
                'select' => ['i.id', 'i.invoice_number', 'c.customer_name AS customer', 't.reference_code AS trip', 'i.issue_date', 'i.due_date', 'i.currency', 'i.subtotal', 'i.tax_amount', 'i.total_amount', 'i.amount_paid', '(i.total_amount - i.amount_paid) AS balance', 'i.status'],
                'search' => ['i.invoice_number', 'c.customer_name', 't.reference_code', 'i.lpo_number'],
                'list' => [
                    'invoice_number' => ['label' => 'Invoice', 'type' => 'code'],
                    'customer' => ['label' => 'Customer'],
                    'trip' => ['label' => 'Trip', 'empty' => 'Not linked'],
                    'issue_date' => ['label' => 'Issued', 'type' => 'date'],
                    'due_date' => ['label' => 'Due', 'type' => 'expiry'],
                    'currency' => ['label' => 'Cur.'],
                    'total_amount' => ['label' => 'Total', 'type' => 'money'],
                    'amount_paid' => ['label' => 'Paid', 'type' => 'money'],
                    'balance' => ['label' => 'Balance', 'type' => 'money'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'i.status', 'options' => self::INVOICE_STATUS]],
                'sections' => [
                    ['title' => 'Invoice', 'icon' => 'file-text', 'fields' => ['invoice_number', 'customer_id', 'trip_id', 'status', 'currency', 'lpo_number']],
                    ['title' => 'Dates', 'icon' => 'calendar', 'hint' => 'The due date defaults to the customer payment terms.', 'fields' => ['issue_date', 'due_date']],
                    ['title' => 'Amounts', 'icon' => 'dollar-sign', 'hint' => 'Totals are recalculated from the invoice lines whenever they change.', 'fields' => ['subtotal', 'tax_rate', 'tax_amount', 'total_amount', 'amount_paid']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'invoice_number' => ['label' => 'Invoice number', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'INV'],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL', 'fills' => ['currency' => 'currency']]],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL'], 'help' => 'Linking a trip is what makes it show a profit or a loss.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::INVOICE_STATUS],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 4, 'options' => Currency::options(), 'help' => 'Taken from the customer. Payments against this invoice are recorded in the same currency.'],
                    'lpo_number' => ['label' => 'Customer LPO number', 'type' => 'text', 'width' => 4, 'help' => 'Printed on the invoice so the customer can match it to their order.'],
                    'issue_date' => ['label' => 'Issue date', 'type' => 'date', 'required' => true, 'width' => 4],
                    'due_date' => ['label' => 'Due date', 'type' => 'date', 'required' => true, 'width' => 4],
                    'subtotal' => ['label' => 'Subtotal', 'type' => 'money', 'width' => 3, 'readonly' => true, 'readonly_note' => 'added from the lines'],
                    'tax_rate' => ['label' => 'VAT rate', 'type' => 'decimal', 'width' => 3, 'suffix' => '%'],
                    'tax_amount' => ['label' => 'VAT amount', 'type' => 'money', 'width' => 3, 'readonly' => true, 'readonly_note' => 'subtotal x the rate'],
                    'total_amount' => ['label' => 'Total', 'type' => 'money', 'width' => 3, 'readonly' => true, 'readonly_note' => 'subtotal plus VAT'],
                    'amount_paid' => ['label' => 'Amount paid', 'type' => 'money', 'width' => 3, 'readonly' => true, 'readonly_note' => 'added from the payments'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'invoice_lines',
                    'parent' => 'invoice_id',
                    'title' => 'Invoice lines',
                    'total_column' => 'subtotal',
                    'hint' => 'A disbursement line is money paid for the customer, recharged at cost: no VAT is charged on it and it is not counted as revenue.',
                    'columns' => [
                        'description' => ['label' => 'Description', 'type' => 'text', 'required' => true],
                        'quantity' => ['label' => 'Qty', 'type' => 'decimal'],
                        'unit_price' => ['label' => 'Unit price', 'type' => 'money'],
                        'line_kind' => ['label' => 'Kind', 'type' => 'select', 'default' => 'service', 'options' => ['service' => 'Service (VAT)', 'disbursement' => 'Disbursement (at cost)']],
                        'disbursement_id' => ['label' => 'Disbursement', 'type' => 'relation', 'relation' => ['table' => 'disbursements', 'label' => 'disbursement_code', 'where' => "deleted_at IS NULL AND status IN ('pending','invoiced','recovered')"]],
                    ],
                ],
                'links' => [
                    ['label' => 'Add disbursements', 'icon' => 'corner-up-right', 'tone' => 'outline-primary', 'method' => 'post',
                     'permission' => 'invoices', 'ability' => 'edit',
                     'when' => ['status' => ['draft']],
                     'route' => ['invoices', ':id', 'disbursements'],
                     'hint' => 'Adds every duty, port charge or fee paid for this customer that has not been recharged yet, at cost and without VAT.'],
                    ['label' => 'Print invoice', 'icon' => 'printer', 'tone' => 'outline-secondary',
                     'permission' => 'invoices', 'ability' => 'view', 'new_tab' => true,
                     'route' => ['invoices', ':id', 'print']],
                ],
                'related' => [
                    ['title' => 'Payments received', 'icon' => 'credit-card', 'permission' => 'invoices',
                     'sql' => 'SELECT id, payment_code, amount, method, reference, paid_at FROM payments WHERE invoice_id = :id AND deleted_at IS NULL ORDER BY paid_at DESC',
                     'columns' => ['payment_code' => 'Payment', 'amount' => 'Amount', 'method' => 'Method', 'reference' => 'Reference', 'paid_at' => 'Received'],
                     'empty' => 'No payment received against this invoice.'],
                ],
                'actions' => [
                    'approve' => ['label' => 'Issue invoice', 'to' => 'issued', 'from' => ['draft'], 'tone' => 'primary'],
                    'reject' => ['label' => 'Cancel invoice', 'to' => 'cancelled', 'from' => ['draft', 'issued'], 'tone' => 'danger', 'reason' => true],
                ],
            ],
        ];
    }

    // --------------------------------------------------------------- finance

    private static function finance(): array
    {
        return [
            'fuel' => [
                'title' => 'Fuel management',
                'singular' => 'Fuel record',
                'kicker' => 'Consumption control',
                'icon' => 'droplet',
                'description' => 'Fuel purchases with odometer readings, so consumption per 100 km can be measured.',
                'button' => 'Record fuel',
                'table' => 'fuel_records',
                'alias' => 'f',
                'code' => 'reference_code',
                'order' => 'f.purchased_at DESC',
                'joins' => 'LEFT JOIN vehicles v ON v.id = f.vehicle_id LEFT JOIN drivers dr ON dr.id = f.driver_id LEFT JOIN trips t ON t.id = f.trip_id',
                'select' => ['f.id', 'f.reference_code', 'v.plate_number AS vehicle', 'dr.full_name AS driver', 't.reference_code AS trip', 'f.station_name', 'f.litres', 'f.unit_price', '(f.litres * f.unit_price) AS total_cost', 'f.currency', 'f.mileage', 'f.previous_mileage', 'f.purchased_at', 'f.receipt_file'],
                'search' => ['f.reference_code', 'f.station_name', 'v.plate_number'],
                'scope' => ['driver' => 'f.driver_id'],
                'list' => [
                    'reference_code' => ['label' => 'Reference', 'type' => 'code'],
                    'vehicle' => ['label' => 'Vehicle'],
                    'driver' => ['label' => 'Driver', 'empty' => 'Not recorded'],
                    'station_name' => ['label' => 'Station'],
                    'litres' => ['label' => 'Litres', 'type' => 'decimal', 'suffix' => 'L'],
                    'unit_price' => ['label' => 'Unit price', 'type' => 'money'],
                    'total_cost' => ['label' => 'Total', 'type' => 'money'],
                    'mileage' => ['label' => 'Odometer', 'type' => 'number', 'suffix' => 'km'],
                    'purchased_at' => ['label' => 'Purchased', 'type' => 'datetime'],
                    'receipt_file' => ['label' => 'Receipt', 'type' => 'file'],
                ],
                'filters' => ['fuel_type' => ['label' => 'Fuel type', 'column' => 'f.fuel_type', 'options' => ['diesel' => 'Diesel', 'petrol' => 'Petrol', 'electric' => 'Electric', 'cng' => 'CNG']]],
                'sections' => [
                    ['title' => 'Purchase', 'icon' => 'droplet', 'fields' => ['reference_code', 'vehicle_id', 'station_name', 'fuel_type', 'purchased_at']],
                    ['title' => 'Quantity and price', 'icon' => 'dollar-sign', 'hint' => 'Record the price exactly as on the receipt, in the currency it was paid in.', 'fields' => ['litres', 'unit_price', 'currency', 'is_full_tank']],
                    ['title' => 'Odometer', 'icon' => 'activity', 'hint' => 'The number on the dashboard when the tank was filled.', 'fields' => ['previous_mileage', 'mileage']],
                    ['title' => 'Attribution', 'icon' => 'user', 'fields' => ['driver_id', 'trip_id']],
                    ['title' => 'Receipt', 'icon' => 'paperclip', 'fields' => ['receipt_file', 'notes']],
                ],
                'fields' => [
                    'reference_code' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'FUE'],
                    'vehicle_id' => ['label' => 'Vehicle', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'vehicles', 'label' => 'plate_number', 'where' => 'deleted_at IS NULL', 'scope' => ['driver' => "id IN (SELECT vehicle_id FROM trips WHERE driver_id = :driver_id AND vehicle_id IS NOT NULL AND deleted_at IS NULL UNION SELECT id FROM vehicles WHERE assigned_driver_id = :driver_id)"]]],
                    'station_name' => ['label' => 'Station', 'type' => 'text', 'required' => true, 'width' => 4, 'placeholder' => 'SP Remera'],
                    'fuel_type' => ['label' => 'Fuel type', 'type' => 'select', 'width' => 4, 'options' => ['diesel' => 'Diesel', 'petrol' => 'Petrol', 'electric' => 'Electric', 'cng' => 'CNG']],
                    'purchased_at' => ['label' => 'Purchased at', 'type' => 'datetime', 'required' => true, 'width' => 4],
                    'litres' => ['label' => 'Litres', 'type' => 'decimal', 'required' => true, 'width' => 4, 'suffix' => 'L'],
                    'unit_price' => ['label' => 'Price per litre', 'type' => 'money', 'required' => true, 'width' => 4],
                    'currency' => ['label' => 'Paid in', 'type' => 'select', 'width' => 4, 'options' => Currency::options(), 'help' => 'TZS at Nyakanazi, KES at Malaba, RWF at home.'],
                    'is_full_tank' => ['label' => 'Tank filled to full', 'type' => 'checkbox', 'width' => 4, 'help' => 'Consumption is only accurate between two full-tank fill-ups.'],
                    'previous_mileage' => ['label' => 'Previous odometer', 'type' => 'number', 'width' => 4, 'suffix' => 'km', 'readonly' => true, 'readonly_note' => 'last reading on file', 'help' => 'The highest reading already recorded for this vehicle. You cannot type it.'],
                    'mileage' => ['label' => 'Odometer now', 'type' => 'number', 'required' => true, 'width' => 4, 'suffix' => 'km', 'help' => 'Read it off the dashboard at the pump. It cannot be lower than the previous reading.'],
                    'driver_id' => ['label' => 'Driver', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'drivers', 'label' => 'full_name', 'where' => 'deleted_at IS NULL'], 'locked_for' => ['driver'], 'locked_note' => 'you', 'help' => 'Who bought the fuel. A driver recording their own fill-up is filled in automatically.'],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL', 'scope' => ['driver' => "driver_id = :driver_id"]]],
                    'receipt_file' => ['label' => 'Receipt', 'type' => 'file', 'width' => 6],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
            ],

            'expenses' => [
                'title' => 'Logistics expenses',
                'singular' => 'Expense',
                'kicker' => 'Cost control',
                'icon' => 'credit-card',
                'description' => 'Trip and fleet costs routed through a real approval, with the approver on record.',
                'button' => 'Submit expense',
                'table' => 'expenses',
                'alias' => 'e',
                'code' => 'reference_code',
                'order' => 'e.id DESC',
                'joins' => 'LEFT JOIN vehicles v ON v.id = e.vehicle_id LEFT JOIN trips t ON t.id = e.trip_id LEFT JOIN users u ON u.id = e.submitted_by LEFT JOIN users ap ON ap.id = e.approved_by',
                'select' => ['e.id', 'e.reference_code', 'e.category', 'v.plate_number AS vehicle', 't.reference_code AS trip', 'e.amount', 'e.currency', 'u.full_name AS submitted_by', 'ap.full_name AS approver', 'e.status', 'e.expense_date', 'e.payment_method', 'e.receipt_file'],
                'search' => ['e.reference_code', 'e.category', 'e.notes', 'v.plate_number', 't.reference_code'],
                // A driver files the costs of his own trips and sees only those.
                'scope' => ['driver' => 't.driver_id'],
                'list' => [
                    'reference_code' => ['label' => 'Reference', 'type' => 'code'],
                    'category' => ['label' => 'Category'],
                    'vehicle' => ['label' => 'Vehicle', 'empty' => 'Not linked'],
                    'trip' => ['label' => 'Trip', 'empty' => 'Not linked'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'submitted_by' => ['label' => 'Submitted by'],
                    'approver' => ['label' => 'Approved by', 'empty' => 'Awaiting'],
                    'expense_date' => ['label' => 'Date', 'type' => 'date'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'e.status', 'options' => self::APPROVAL_STATUS],
                    'category' => ['label' => 'Category', 'column' => 'e.category', 'options' => Lookup::options('expense_category')],
                ],
                'sections' => [
                    ['title' => 'Expense', 'icon' => 'credit-card', 'fields' => ['reference_code', 'category', 'amount', 'currency', 'expense_date']],
                    ['title' => 'Attribution', 'icon' => 'link', 'hint' => 'Linking a trip is what lets the cost per trip report work.', 'fields' => ['vehicle_id', 'trip_id', 'submitted_by']],
                    ['title' => 'Approval', 'icon' => 'check-circle', 'hint' => 'Use the Approve and Reject buttons; the approver and time are recorded automatically.', 'fields' => ['status', 'payment_method']],
                    ['title' => 'Evidence', 'icon' => 'paperclip', 'fields' => ['receipt_file', 'notes']],
                ],
                'fields' => [
                    'reference_code' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'EXP'],
                    'category' => ['label' => 'Category', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => Lookup::options('expense_category')],
                    'amount' => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'width' => 4],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 2, 'options' => Currency::options(), 'help' => 'What the paper actually says.'],
                    'expense_date' => ['label' => 'Expense date', 'type' => 'date', 'required' => true, 'width' => 4],
                    'vehicle_id' => ['label' => 'Vehicle', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'vehicles', 'label' => 'plate_number', 'where' => 'deleted_at IS NULL']],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL', 'scope' => ['driver' => 'driver_id = :driver_id']]],
                    'submitted_by' => ['label' => 'Submitted by', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'you', 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL'], 'help' => 'Whoever is signed in when the claim is filed. It is recorded, not chosen.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::APPROVAL_STATUS, 'locked_for' => ['driver'], 'locked_note' => 'Finance decides'],
                    'payment_method' => ['label' => 'Payment method', 'type' => 'select', 'width' => 4, 'options' => PaymentMethod::options('out'), 'help' => 'What the claim was settled from. It decides which account the ledger credits.', 'locked_for' => ['driver'], 'locked_note' => 'Finance decides'],
                    'receipt_file' => ['label' => 'Receipt', 'type' => 'file', 'width' => 6],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'actions' => [
                    'approve' => ['label' => 'Approve expense', 'to' => 'approved', 'from' => ['pending'], 'tone' => 'success'],
                    'reject' => ['label' => 'Reject', 'to' => 'rejected', 'from' => ['pending'], 'tone' => 'danger', 'reason' => true],
                ],
                'related' => [
                    self::moneyMovement('expenses', "SELECT e.id, " . self::methodAccountSql('pm') . " AS from_side,
                                   CONCAT(e.category, COALESCE(CONCAT(', ', v.plate_number), ''), COALESCE(CONCAT(', trip ', t.reference_code), '')) AS to_side,
                                   CONCAT(e.currency, ' ', FORMAT(e.amount, 2)) AS amount_text, CONCAT(a.account_code, ' ', a.account_name) AS ledger, e.reference_code AS reference
                              FROM expenses e
                              LEFT JOIN vehicles v ON v.id = e.vehicle_id
                              LEFT JOIN trips t ON t.id = e.trip_id
                              LEFT JOIN payment_methods pm ON pm.method_key = e.payment_method
                              LEFT JOIN gl_accounts a ON a.id = pm.gl_account_id
                             WHERE e.id = :id"),
                ],
            ],

            'supplier_bills' => [
                'title' => 'Supplier bills',
                'singular' => 'Supplier bill',
                'kicker' => 'Accounts payable',
                'icon' => 'inbox',
                'description' => 'Invoices we receive: subcontracted trucks, clearing agents, garages, port and fuel suppliers. Approving a bill makes it money we owe; paying it clears it.',
                'button' => 'Record bill',
                'table' => 'supplier_bills',
                'alias' => 'sb',
                'code' => 'bill_no',
                'order' => 'sb.bill_date DESC, sb.id DESC',
                'track_changes' => true,
                'joins' => 'LEFT JOIN suppliers s ON s.id = sb.supplier_id LEFT JOIN trips t ON t.id = sb.trip_id',
                'select' => ['sb.id', 'sb.bill_no', 's.supplier_name AS supplier', 'sb.supplier_invoice_no', 't.reference_code AS trip', 'sb.bill_date', 'sb.due_date', 'sb.currency', 'sb.total_amount', 'sb.amount_paid', '(sb.total_amount - sb.amount_paid) AS balance', 'sb.status'],
                'search' => ['sb.bill_no', 'sb.supplier_invoice_no', 's.supplier_name', 't.reference_code'],
                'list' => [
                    'bill_no' => ['label' => 'Bill', 'type' => 'code'],
                    'supplier' => ['label' => 'Supplier'],
                    'supplier_invoice_no' => ['label' => 'Their invoice', 'empty' => 'None'],
                    'trip' => ['label' => 'Trip', 'empty' => 'Not linked'],
                    'bill_date' => ['label' => 'Dated', 'type' => 'date'],
                    'due_date' => ['label' => 'Due', 'type' => 'expiry'],
                    'currency' => ['label' => 'Cur.'],
                    'total_amount' => ['label' => 'Total', 'type' => 'money'],
                    'balance' => ['label' => 'Still owed', 'type' => 'money'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'sb.status', 'options' => Payables::BILL_STATUS],
                ],
                'list_links' => [
                    ['label' => 'What we owe (aging)', 'icon' => 'clock', 'route' => ['reports', 'view', 'ap_aging'], 'permission' => 'reports'],
                ],
                'sections' => [
                    ['title' => 'The bill', 'icon' => 'inbox', 'fields' => ['bill_no', 'supplier_id', 'supplier_invoice_no', 'bill_date', 'due_date', 'currency']],
                    ['title' => 'What it is for', 'icon' => 'link', 'hint' => 'Link the trip so the cost shows on its profit. Link the goods receipt, work order or border crossing when that is what is being billed — it is then already in the books, and the bill only proves the amount.', 'fields' => ['trip_id', 'purchase_request_id', 'maintenance_id', 'crossing_id']],
                    ['title' => 'Amounts', 'icon' => 'dollar-sign', 'hint' => 'Add the lines below after saving. Totals follow the lines.', 'fields' => ['tax_rate', 'subtotal', 'tax_amount', 'total_amount', 'amount_paid', 'status']],
                    ['title' => 'Paper', 'icon' => 'paperclip', 'fields' => ['bill_file', 'notes']],
                ],
                'fields' => [
                    'bill_no' => ['label' => 'Our reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'BILL'],
                    'supplier_id' => ['label' => 'Supplier', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'suppliers', 'label' => 'supplier_name', 'where' => 'deleted_at IS NULL']],
                    'supplier_invoice_no' => ['label' => 'Their invoice number', 'type' => 'text', 'width' => 4, 'placeholder' => 'INV-20419'],
                    'bill_date' => ['label' => 'Bill date', 'type' => 'date', 'required' => true, 'width' => 4],
                    'due_date' => ['label' => 'Due date', 'type' => 'date', 'width' => 4, 'help' => 'Blank follows the supplier\'s payment terms.'],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 4, 'options' => Currency::options(), 'help' => 'As written on the bill. It is paid in the same currency.'],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL']],
                    'purchase_request_id' => ['label' => 'Goods receipt', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'purchase_requests', 'label' => 'request_code', 'where' => 'deleted_at IS NULL']],
                    'maintenance_id' => ['label' => 'Work order', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'maintenance_orders', 'label' => 'work_order_code', 'where' => 'deleted_at IS NULL']],
                    'crossing_id' => ['label' => 'Border crossing', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'border_crossings', 'label' => 'reference', 'where' => 'deleted_at IS NULL']],
                    'tax_rate' => ['label' => 'VAT rate', 'type' => 'decimal', 'width' => 2, 'suffix' => '%', 'help' => '0 when the supplier charged none.'],
                    'subtotal' => ['label' => 'Subtotal', 'type' => 'money', 'width' => 2, 'readonly' => true, 'readonly_note' => 'from the lines'],
                    'tax_amount' => ['label' => 'VAT', 'type' => 'money', 'width' => 2, 'readonly' => true],
                    'total_amount' => ['label' => 'Total', 'type' => 'money', 'width' => 2, 'readonly' => true],
                    'amount_paid' => ['label' => 'Paid', 'type' => 'money', 'width' => 2, 'readonly' => true, 'readonly_note' => 'from payments'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 2, 'options' => Payables::BILL_STATUS, 'readonly' => true, 'readonly_note' => 'set by the buttons'],
                    'bill_file' => ['label' => 'Scan of the bill', 'type' => 'file', 'width' => 6],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'supplier_bill_lines',
                    'parent' => 'bill_id',
                    'title' => 'Bill lines',
                    'total_column' => 'subtotal',
                    'columns' => [
                        'account_id' => ['label' => 'Charge to account', 'type' => 'relation', 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_header = 0 AND is_active = 1']],
                        'description' => ['label' => 'Description', 'type' => 'text', 'required' => true],
                        'quantity' => ['label' => 'Qty', 'type' => 'decimal'],
                        'unit_price' => ['label' => 'Unit price', 'type' => 'money'],
                    ],
                ],
                'links' => [
                    ['label' => 'Pay this bill', 'icon' => 'send', 'tone' => 'success',
                     'permission' => 'supplier_payments', 'ability' => 'create',
                     'when' => ['status' => ['approved', 'partially_paid']],
                     'route' => ['supplier_payments', 'create'], 'carry' => ['bill_id' => 'id'],
                     'hint' => 'Records money paid to the supplier against this bill. The balance is filled in.'],
                ],
                'related' => [
                    ['title' => 'Payments made', 'icon' => 'send', 'permission' => 'supplier_payments',
                     'sql' => 'SELECT id, payment_code, amount, method, reference, paid_at FROM supplier_payments WHERE bill_id = :id AND deleted_at IS NULL ORDER BY paid_at DESC',
                     'columns' => ['payment_code' => 'Payment', 'amount' => 'Amount', 'method' => 'Method', 'reference' => 'Reference', 'paid_at' => 'Paid'],
                     'empty' => 'Nothing paid on this bill yet.'],
                    ['title' => 'Ledger entry', 'icon' => 'book', 'permission' => 'journal',
                     'sql' => 'SELECT e.entry_no, a.account_code, a.account_name, l.debit, l.credit
                                 FROM gl_journal_entries e
                                 INNER JOIN gl_journal_lines l ON l.entry_id = e.id
                                 INNER JOIN gl_accounts a ON a.id = l.account_id
                                WHERE e.source_type = "supplier_bill" AND e.source_id = :id AND e.deleted_at IS NULL
                                ORDER BY l.line_no',
                     'columns' => ['entry_no' => 'Entry', 'account_code' => 'Code', 'account_name' => 'Account', 'debit' => 'Debit', 'credit' => 'Credit'],
                     'empty' => 'Nothing is posted until the bill is approved — or it is linked to a document already in the books.'],
                ],
                'actions' => [
                    'approve' => ['label' => 'Approve bill', 'to' => 'approved', 'from' => ['draft'], 'tone' => 'success'],
                    'reject' => ['label' => 'Cancel bill', 'to' => 'cancelled', 'from' => ['draft', 'approved'], 'tone' => 'danger', 'reason' => true],
                ],
            ],

            'supplier_payments' => [
                'title' => 'Supplier payments',
                'singular' => 'Supplier payment',
                'kicker' => 'Money out',
                'icon' => 'send',
                'description' => 'Money paid to a supplier against an approved bill. It clears what we owe and takes the money off the bank, till or wallet it left from.',
                'button' => 'Pay a supplier',
                'table' => 'supplier_payments',
                'alias' => 'sp',
                'code' => 'payment_code',
                'order' => 'sp.paid_at DESC, sp.id DESC',
                'track_changes' => true,
                'joins' => 'INNER JOIN supplier_bills b ON b.id = sp.bill_id LEFT JOIN suppliers s ON s.id = b.supplier_id LEFT JOIN users u ON u.id = sp.recorded_by',
                'select' => ['sp.id', 'sp.payment_code', 'b.bill_no AS bill', 's.supplier_name AS supplier', 'sp.currency', 'sp.amount', 'sp.method', 'sp.reference', 'sp.paid_at', 'u.full_name AS recorded_by_name'],
                'search' => ['sp.payment_code', 'sp.reference', 'b.bill_no', 's.supplier_name'],
                'list' => [
                    'payment_code' => ['label' => 'Payment', 'type' => 'code'],
                    'bill' => ['label' => 'Bill'],
                    'supplier' => ['label' => 'Supplier'],
                    'currency' => ['label' => 'Cur.'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'method' => ['label' => 'Method', 'type' => 'label'],
                    'reference' => ['label' => 'Reference', 'empty' => 'None'],
                    'paid_at' => ['label' => 'Paid', 'type' => 'datetime'],
                    'recorded_by_name' => ['label' => 'Recorded by', 'empty' => 'System'],
                ],
                'filters' => ['method' => ['label' => 'Method', 'column' => 'sp.method', 'options' => PaymentMethod::options('out')]],
                'sections' => [
                    ['title' => 'Payment', 'icon' => 'send', 'hint' => 'Only approved bills can be paid. The currency is the bill\'s.', 'fields' => ['payment_code', 'bill_id', 'amount', 'method']],
                    ['title' => 'Evidence', 'icon' => 'hash', 'fields' => ['reference', 'paid_at', 'recorded_by', 'notes']],
                ],
                'fields' => [
                    'payment_code' => ['label' => 'Payment reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'SPAY'],
                    'bill_id' => ['label' => 'Against bill', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'supplier_bills', 'label' => 'bill_no', 'where' => "deleted_at IS NULL AND status IN ('approved','partially_paid','paid')"]],
                    'amount' => ['label' => 'Amount paid', 'type' => 'money', 'required' => true, 'width' => 4, 'help' => 'It cannot be more than the balance on the bill.'],
                    'method' => ['label' => 'Paid by', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => PaymentMethod::options('out'), 'help' => 'Decides which bank, till or wallet the ledger credits.'],
                    'reference' => ['label' => 'Bank or cheque reference', 'type' => 'text', 'width' => 4],
                    'paid_at' => ['label' => 'Paid at', 'type' => 'datetime', 'required' => true, 'width' => 4],
                    'recorded_by' => ['label' => 'Recorded by', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'you', 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'related' => [
                    self::moneyMovement('supplier_payments', "SELECT sp.id, " . self::methodAccountSql('pm') . " AS from_side, CONCAT('Supplier: ', s.supplier_name, ' (bill ', b.bill_no, ')') AS to_side,
                                   CONCAT(sp.currency, ' ', FORMAT(sp.amount, 2)) AS amount_text, CONCAT(a.account_code, ' ', a.account_name) AS ledger, sp.reference
                              FROM supplier_payments sp
                              INNER JOIN supplier_bills b ON b.id = sp.bill_id
                              LEFT JOIN suppliers s ON s.id = b.supplier_id
                              LEFT JOIN payment_methods pm ON pm.method_key = sp.method
                              LEFT JOIN gl_accounts a ON a.id = pm.gl_account_id
                             WHERE sp.id = :id"),
                ],
            ],

            'disbursements' => [
                'title' => 'Disbursements',
                'singular' => 'Disbursement',
                'kicker' => 'Paid for customers',
                'icon' => 'corner-up-right',
                'description' => 'Duty, port and storage charges, agent fees: money we pay on a customer\'s behalf. Each one waits here until it is recharged on their invoice at cost, with no VAT, so none is ever forgotten.',
                'button' => 'Record disbursement',
                'table' => 'disbursements',
                'alias' => 'd',
                'code' => 'disbursement_code',
                'order' => 'd.paid_at DESC, d.id DESC',
                'joins' => 'LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN trips t ON t.id = d.trip_id LEFT JOIN invoices i ON i.id = d.invoice_id',
                'select' => ['d.id', 'd.disbursement_code', 'c.customer_name AS customer', 't.reference_code AS trip', 'd.category', 'd.description', 'd.currency', 'd.amount', 'd.paid_at', 'i.invoice_number AS invoice', 'd.status', 'd.receipt_file'],
                'search' => ['d.disbursement_code', 'd.description', 'd.reference', 'c.customer_name', 't.reference_code'],
                'list' => [
                    'disbursement_code' => ['label' => 'Reference', 'type' => 'code'],
                    'customer' => ['label' => 'Customer'],
                    'trip' => ['label' => 'Trip', 'empty' => 'Not linked'],
                    'category' => ['label' => 'What'],
                    'currency' => ['label' => 'Cur.'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'paid_at' => ['label' => 'Paid', 'type' => 'date'],
                    'invoice' => ['label' => 'Invoice', 'empty' => 'Not yet'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                    'receipt_file' => ['label' => 'Receipt', 'type' => 'file'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'd.status', 'options' => Payables::DISBURSEMENT_STATUS],
                ],
                'sections' => [
                    ['title' => 'Paid for', 'icon' => 'user', 'fields' => ['disbursement_code', 'customer_id', 'trip_id', 'shipment_id']],
                    ['title' => 'What was paid', 'icon' => 'dollar-sign', 'hint' => 'Exactly as on the receipt, in the currency it was paid in.', 'fields' => ['category', 'description', 'amount', 'currency', 'paid_at']],
                    ['title' => 'Paid from', 'icon' => 'send', 'fields' => ['method', 'supplier_id', 'reference']],
                    ['title' => 'Recovery', 'icon' => 'repeat', 'hint' => 'Use "Add disbursements" on a draft invoice. The status follows the invoice.', 'fields' => ['status', 'invoice_id', 'receipt_file', 'notes']],
                ],
                'fields' => [
                    'disbursement_code' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 3, 'auto' => 'DSB'],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'required' => true, 'width' => 3, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL', 'fills' => ['currency' => 'currency']]],
                    'trip_id' => ['label' => 'Trip', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL']],
                    'shipment_id' => ['label' => 'Shipment', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'shipments', 'label' => 'shipment_code', 'where' => 'deleted_at IS NULL']],
                    'category' => ['label' => 'What it was', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['Customs duty' => 'Customs duty', 'Port charges' => 'Port charges', 'Storage' => 'Storage', 'Agency fee' => 'Agency fee', 'Container deposit' => 'Container deposit', 'Container detention' => 'Container detention', 'Inspection' => 'Inspection', 'Other' => 'Other']],
                    'description' => ['label' => 'Description', 'type' => 'text', 'required' => true, 'width' => 8, 'placeholder' => 'Import duty on 600 bags cement, entry C 4471'],
                    'amount' => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'width' => 4],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 4, 'options' => Currency::options(), 'help' => 'It is recharged in the same currency, so put it on an invoice in that currency.'],
                    'paid_at' => ['label' => 'Paid on', 'type' => 'date', 'required' => true, 'width' => 4],
                    'method' => ['label' => 'Paid by', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => PaymentMethod::options('out')],
                    'supplier_id' => ['label' => 'Paid to', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'suppliers', 'label' => 'supplier_name', 'where' => 'deleted_at IS NULL'], 'help' => 'The revenue authority, port or agent, if on file.'],
                    'reference' => ['label' => 'Receipt or bank reference', 'type' => 'text', 'width' => 4],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 4, 'options' => Payables::DISBURSEMENT_STATUS, 'readonly' => true, 'readonly_note' => 'follows the invoice'],
                    'invoice_id' => ['label' => 'Recharged on', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'follows the invoice', 'relation' => ['table' => 'invoices', 'label' => 'invoice_number', 'where' => 'deleted_at IS NULL']],
                    'receipt_file' => ['label' => 'Receipt', 'type' => 'file', 'width' => 4],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'actions' => [
                    'write_off' => ['label' => 'Write off', 'to' => 'written_off', 'from' => ['pending'], 'tone' => 'danger', 'reason' => true],
                ],
                'related' => [
                    self::moneyMovement('disbursements', "SELECT d.id, " . self::methodAccountSql('pm') . " AS from_side,
                                   CONCAT(COALESCE(s.supplier_name, 'Paid out'), ', for ', c.customer_name) AS to_side,
                                   CONCAT(d.currency, ' ', FORMAT(d.amount, 2)) AS amount_text, CONCAT(a.account_code, ' ', a.account_name) AS ledger, d.reference
                              FROM disbursements d
                              LEFT JOIN suppliers s ON s.id = d.supplier_id
                              LEFT JOIN customers c ON c.id = d.customer_id
                              LEFT JOIN payment_methods pm ON pm.method_key = d.method
                              LEFT JOIN gl_accounts a ON a.id = pm.gl_account_id
                             WHERE d.id = :id"),
                ],
            ],

            'bank_statements' => [
                'title' => 'Bank reconciliation',
                'singular' => 'Bank statement',
                'kicker' => 'Bank and books agree',
                'icon' => 'check-square',
                'description' => 'One bank statement per account per month. Import the bank\'s export, match each line to what the books recorded, post the charges the bank alone knew about, and close it when nothing is left unexplained.',
                'button' => 'New statement',
                'table' => 'bank_statements',
                'alias' => 'bs',
                'code' => 'statement_code',
                'order' => 'bs.period_to DESC, bs.id DESC',
                'joins' => 'LEFT JOIN gl_accounts a ON a.id = bs.account_id LEFT JOIN payment_methods bpm ON bpm.id = bs.payment_method_id',
                'select' => ['bs.id', 'bs.statement_code', "COALESCE(CONCAT_WS(' · ', bpm.provider_name, IF(bpm.account_number IS NULL OR bpm.account_number = '', NULL, CONCAT('No. ', bpm.account_number))), a.account_name) AS account", 'bs.currency', 'bs.period_from', 'bs.period_to', 'bs.closing_balance', 'bs.status',
                             '(SELECT COUNT(*) FROM bank_statement_lines l WHERE l.statement_id = bs.id) AS line_count',
                             '(SELECT COUNT(*) FROM bank_statement_lines l WHERE l.statement_id = bs.id AND l.matched_entry_id IS NULL) AS unmatched'],
                'search' => ['bs.statement_code', 'a.account_name'],
                'list' => [
                    'statement_code' => ['label' => 'Statement', 'type' => 'code'],
                    'account' => ['label' => 'Bank account'],
                    'currency' => ['label' => 'Cur.'],
                    'period_from' => ['label' => 'From', 'type' => 'date'],
                    'period_to' => ['label' => 'To', 'type' => 'date'],
                    'closing_balance' => ['label' => 'Closing balance', 'type' => 'money'],
                    'line_count' => ['label' => 'Lines', 'type' => 'number'],
                    'unmatched' => ['label' => 'Unmatched', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'bs.status', 'options' => ['open' => 'Open', 'reconciled' => 'Reconciled']]],
                'sections' => [
                    ['title' => 'Statement', 'icon' => 'file-text', 'hint' => 'Choose the bank account the statement is for (set up under Payment methods, with its bank, number and currency), then copy the figures from the top of the statement.', 'fields' => ['statement_code', 'payment_method_id', 'account_id', 'currency', 'period_from', 'period_to', 'opening_balance', 'closing_balance']],
                    ['title' => 'Paper', 'icon' => 'paperclip', 'fields' => ['statement_file', 'status', 'notes']],
                ],
                'fields' => [
                    'statement_code' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'BST'],
                    'payment_method_id' => ['label' => 'Bank account', 'type' => 'relation', 'required' => true, 'width' => 4,
                        'relation' => ['table' => 'payment_methods', 'label' => 'method_name', 'where' => 'deleted_at IS NULL AND gl_account_id IN (SELECT id FROM gl_accounts WHERE is_bank = 1 AND deleted_at IS NULL)'],
                        'help' => 'The account as set up under Payment methods. Its ledger account and currency come with it.'],
                    'account_id' => ['label' => 'Ledger account', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'from the bank account', 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_bank = 1']],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 4, 'options' => Currency::options(), 'help' => 'Taken from the bank account when it has one. Only entries in this currency are matched.'],
                    'period_from' => ['label' => 'From', 'type' => 'date', 'required' => true, 'width' => 3],
                    'period_to' => ['label' => 'To', 'type' => 'date', 'required' => true, 'width' => 3],
                    'opening_balance' => ['label' => 'Opening balance', 'type' => 'money', 'width' => 3],
                    'closing_balance' => ['label' => 'Closing balance', 'type' => 'money', 'required' => true, 'width' => 3],
                    'statement_file' => ['label' => 'Statement (PDF or scan)', 'type' => 'file', 'width' => 6],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 3, 'options' => ['open' => 'Open', 'reconciled' => 'Reconciled'], 'readonly' => true, 'readonly_note' => 'set on the reconcile page'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'bank_statement_lines',
                    'parent' => 'statement_id',
                    'title' => 'Statement lines',
                    'hint' => 'Typed by hand when there is no file to import. Money in positive, money out negative. Saving here clears every match on this statement, so import or type all lines before matching.',
                    'columns' => [
                        'line_date' => ['label' => 'Date', 'type' => 'date', 'required' => true],
                        'description' => ['label' => 'Description', 'type' => 'text'],
                        'reference' => ['label' => 'Reference', 'type' => 'text'],
                        'amount' => ['label' => 'Amount', 'type' => 'money'],
                    ],
                ],
                'related' => [
                    ['title' => 'The bank account', 'icon' => 'credit-card', 'permission' => 'bank_statements',
                     'sql' => "SELECT bs.id, pm.method_name, pm.provider_name, pm.branch_name, pm.account_name AS holder, pm.account_number, pm.swift_code, COALESCE(pm.currency, bs.currency) AS currency, CONCAT(a.account_code, ' ', a.account_name) AS ledger
                                 FROM bank_statements bs
                                 LEFT JOIN payment_methods pm ON pm.id = bs.payment_method_id
                                 LEFT JOIN gl_accounts a ON a.id = bs.account_id
                                WHERE bs.id = :id",
                     'columns' => ['provider_name' => 'Bank', 'branch_name' => 'Branch', 'account_number' => 'Account number', 'holder' => 'In the name of', 'swift_code' => 'SWIFT', 'currency' => 'Currency', 'ledger' => 'Ledger account', 'method_name' => 'Payment method'],
                     'empty' => 'Choose the bank account.'],
                ],
                'links' => [
                    ['label' => 'Reconcile', 'icon' => 'check-square', 'tone' => 'primary',
                     'permission' => 'bank_statements', 'ability' => 'view',
                     'route' => ['bank_statements', ':id', 'reconcile'],
                     'hint' => 'Import the bank\'s file, match lines to the books, and close the month.'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------- warehouse

    private static function warehouse(): array
    {
        return [
            'warehouses' => [
                'title' => 'Warehouses',
                'singular' => 'Warehouse',
                'kicker' => 'Storage sites',
                'icon' => 'home',
                'description' => 'The stores and depots stock is held in. Every stock item belongs to one of these, so at least one must exist before anything can be received.',
                'button' => 'Add warehouse',
                'table' => 'warehouses',
                'alias' => 'wh',
                'code' => 'warehouse_name',
                'order' => 'wh.warehouse_name',
                'joins' => 'LEFT JOIN users mu ON mu.id = wh.manager_id',
                'select' => ['wh.id', 'wh.warehouse_code', 'wh.warehouse_name', 'wh.location', 'mu.full_name AS manager', 'wh.phone', 'wh.capacity_m3', 'wh.is_cold_chain', 'wh.status'],
                'search' => ['wh.warehouse_name', 'wh.warehouse_code', 'wh.location', 'wh.phone'],
                'list' => [
                    'warehouse_code' => ['label' => 'Code', 'type' => 'code', 'empty' => 'No code'],
                    'warehouse_name' => ['label' => 'Warehouse'],
                    'location' => ['label' => 'Location'],
                    'manager' => ['label' => 'Manager', 'empty' => 'Unassigned'],
                    'phone' => ['label' => 'Phone'],
                    'capacity_m3' => ['label' => 'Capacity', 'type' => 'decimal', 'suffix' => 'm3'],
                    'is_cold_chain' => ['label' => 'Cold chain', 'type' => 'yesno'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'wh.status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]],
                'sections' => [
                    ['title' => 'Warehouse', 'icon' => 'home', 'fields' => ['warehouse_code', 'warehouse_name', 'location', 'status']],
                    ['title' => 'Contact and capacity', 'icon' => 'phone', 'hint' => 'A cold-chain store is where chilled cargo may be held between trips.', 'fields' => ['manager_id', 'phone', 'capacity_m3', 'is_cold_chain']],
                ],
                'fields' => [
                    'warehouse_code' => ['label' => 'Warehouse code', 'type' => 'text', 'width' => 4, 'auto' => 'WH'],
                    'warehouse_name' => ['label' => 'Warehouse name', 'type' => 'text', 'required' => true, 'width' => 5, 'placeholder' => 'Kigali Central Warehouse'],
                    'location' => ['label' => 'Location', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => 'Gikondo, Kigali'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
                    'manager_id' => ['label' => 'Manager', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => "status = 'active' AND deleted_at IS NULL"]],
                    'phone' => ['label' => 'Phone', 'type' => 'tel', 'width' => 4],
                    'capacity_m3' => ['label' => 'Storage capacity', 'type' => 'decimal', 'width' => 4, 'suffix' => 'm3'],
                    'is_cold_chain' => ['label' => 'Cold chain storage', 'type' => 'checkbox', 'width' => 4],
                ],
                'related' => [
                    // Two different things sit in a depot and are never added
                    // together: what the company owns, and what a customer has
                    // left with us. They get a table each.
                    ['title' => 'Customer cargo in the shed', 'icon' => 'box', 'permission' => 'shipments', 'module' => 'shipments',
                     'sql' => "SELECT s.id, s.shipment_code, c.customer_name, s.cargo_description,
                                      SUM(CASE WHEN wc.direction = 'in' THEN wc.packages ELSE -wc.packages END) AS packages,
                                      SUM(CASE WHEN wc.direction = 'in' THEN wc.weight_kg ELSE -wc.weight_kg END) AS weight_kg,
                                      MAX(wc.moved_at) AS since
                                 FROM warehouse_cargo wc
                                 INNER JOIN shipments s ON s.id = wc.shipment_id
                                 LEFT JOIN customers c ON c.id = s.customer_id
                                WHERE wc.warehouse_id = :id AND s.deleted_at IS NULL
                                GROUP BY s.id, s.shipment_code, c.customer_name, s.cargo_description
                               HAVING SUM(CASE WHEN wc.direction = 'in' THEN wc.weight_kg ELSE -wc.weight_kg END) > 0
                                ORDER BY since DESC",
                     'columns' => ['shipment_code' => 'Consignment', 'customer_name' => 'Customer', 'cargo_description' => 'Goods', 'packages' => 'Packages', 'weight_kg' => 'Weight', 'since' => 'Here since'],
                     'empty' => 'No customer cargo is standing here.'],
                    ['title' => 'Cargo movements', 'icon' => 'repeat', 'permission' => 'shipments',
                     'sql' => 'SELECT wc.id, wc.movement_code, s.shipment_code, wc.direction, wc.reason, wc.weight_kg, t.reference_code AS trip, wc.moved_at
                                 FROM warehouse_cargo wc
                                 INNER JOIN shipments s ON s.id = wc.shipment_id
                                 LEFT JOIN trips t ON t.id = wc.trip_id
                                WHERE wc.warehouse_id = :id
                                ORDER BY wc.moved_at DESC, wc.id DESC
                                LIMIT 25',
                     'columns' => ['movement_code' => 'Movement', 'shipment_code' => 'Consignment', 'direction' => 'In or out', 'reason' => 'Why', 'weight_kg' => 'Weight', 'trip' => 'Trip', 'moved_at' => 'When'],
                     'empty' => 'Nothing has moved through here yet.'],
                    ['title' => 'Our own stock held here', 'icon' => 'package', 'permission' => 'warehouse', 'module' => 'warehouse',
                     'sql' => 'SELECT id, sku, item_name, quantity, minimum_level, status FROM inventory_items WHERE warehouse_id = :id AND deleted_at IS NULL ORDER BY item_name LIMIT 20',
                     'columns' => ['sku' => 'SKU', 'item_name' => 'Item', 'quantity' => 'On hand', 'minimum_level' => 'Minimum', 'status' => 'Status'],
                     'empty' => 'No stock is held here yet.'],
                ],
            ],

            'storage_lots' => [
                'title' => 'Customer storage',
                'singular' => 'Storage lot',
                'kicker' => 'Goods we keep for customers',
                'icon' => 'archive',
                'description' => 'Goods a customer leaves in our warehouse. Each lot is one goods received note; it goes out a part at a time against gate passes, and storage is charged per day after the free days.',
                'button' => 'Receive goods (GRN)',
                'table' => 'storage_lots',
                'alias' => 'sl',
                'code' => 'lot_code',
                'order' => 'sl.received_at DESC, sl.id DESC',
                'joins' => 'LEFT JOIN customers c ON c.id = sl.customer_id LEFT JOIN warehouses w ON w.id = sl.warehouse_id',
                'select' => ['sl.id', 'sl.lot_code', 'c.customer_name AS customer', 'w.warehouse_name AS warehouse', 'sl.description', 'sl.unit', 'sl.bay_location',
                             'sl.quantity_received', '(sl.quantity_received - sl.quantity_released) AS on_hand', 'sl.received_at',
                             'DATEDIFF(CURDATE(), DATE(sl.received_at)) + 1 AS days_in_store', '(CAST(sl.free_days AS SIGNED) - DATEDIFF(CURDATE(), DATE(sl.received_at)) - 1) AS free_days_left',
                             'sl.billed_until', 'sl.status'],
                'search' => ['sl.lot_code', 'sl.description', 'sl.bay_location', 'c.customer_name'],
                'list' => [
                    'lot_code' => ['label' => 'Lot / GRN', 'type' => 'code'],
                    'customer' => ['label' => 'Customer'],
                    'warehouse' => ['label' => 'Warehouse'],
                    'description' => ['label' => 'Goods'],
                    'bay_location' => ['label' => 'Bay', 'empty' => '—'],
                    'quantity_received' => ['label' => 'Received', 'type' => 'decimal'],
                    'on_hand' => ['label' => 'On hand', 'type' => 'decimal'],
                    'unit' => ['label' => 'Unit'],
                    'days_in_store' => ['label' => 'Days in', 'type' => 'number'],
                    'billed_until' => ['label' => 'Billed to', 'type' => 'date', 'empty' => 'Not yet'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'sl.status', 'options' => Storage::STATUS],
                ],
                'sections' => [
                    ['title' => 'Goods received', 'icon' => 'archive', 'hint' => 'Count what came off the truck. The lot code is the GRN number the driver signs.', 'fields' => ['lot_code', 'customer_id', 'warehouse_id', 'shipment_id', 'description', 'quantity_received', 'unit', 'weight_kg', 'volume_m3', 'bay_location']],
                    ['title' => 'Delivered by', 'icon' => 'truck', 'fields' => ['received_at', 'received_from', 'vehicle_plate', 'condition_note']],
                    ['title' => 'Storage charge', 'icon' => 'dollar-sign', 'hint' => 'Free for the first days, then the rate per day on what is still here. "Bill storage" on the lot raises the invoice.', 'fields' => ['free_days', 'charge_basis', 'daily_rate', 'currency', 'billed_until']],
                    ['title' => 'Paper', 'icon' => 'paperclip', 'fields' => ['status', 'quantity_released', 'grn_file', 'notes']],
                ],
                'fields' => [
                    'lot_code' => ['label' => 'Lot / GRN number', 'type' => 'text', 'required' => true, 'width' => 3, 'auto' => 'GRN'],
                    'customer_id' => ['label' => 'Customer', 'type' => 'relation', 'required' => true, 'width' => 3, 'relation' => ['table' => 'customers', 'label' => 'customer_name', 'where' => 'deleted_at IS NULL', 'fills' => ['currency' => 'currency']]],
                    'warehouse_id' => ['label' => 'Warehouse', 'type' => 'relation', 'required' => true, 'width' => 3, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => "deleted_at IS NULL AND status = 'active'"]],
                    'shipment_id' => ['label' => 'From shipment', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'shipments', 'label' => 'shipment_code', 'where' => 'deleted_at IS NULL'], 'help' => 'When it came in on one of our trucks.'],
                    'description' => ['label' => 'Goods', 'type' => 'text', 'required' => true, 'width' => 6, 'placeholder' => 'Cement 50 kg bags, CIMERWA'],
                    'quantity_received' => ['label' => 'Quantity received', 'type' => 'decimal', 'required' => true, 'width' => 2],
                    'unit' => ['label' => 'Unit', 'type' => 'select', 'required' => true, 'width' => 2, 'options' => ['Bags' => 'Bags', 'Cartons' => 'Cartons', 'Pallets' => 'Pallets', 'Pieces' => 'Pieces', 'Bundles' => 'Bundles', 'Drums' => 'Drums', 'Rolls' => 'Rolls', 'Tonnes' => 'Tonnes', 'Units' => 'Units']],
                    'weight_kg' => ['label' => 'Weight', 'type' => 'decimal', 'width' => 2, 'suffix' => 'kg'],
                    'volume_m3' => ['label' => 'Volume', 'type' => 'decimal', 'width' => 2, 'suffix' => 'm3'],
                    'bay_location' => ['label' => 'Bay / location', 'type' => 'text', 'width' => 2, 'placeholder' => 'C4'],
                    'received_at' => ['label' => 'Received at', 'type' => 'datetime', 'required' => true, 'width' => 3],
                    'received_from' => ['label' => 'Delivered by (driver or person)', 'type' => 'text', 'width' => 3],
                    'vehicle_plate' => ['label' => 'Truck plate', 'type' => 'text', 'width' => 3],
                    'condition_note' => ['label' => 'Condition on arrival', 'type' => 'text', 'width' => 3, 'placeholder' => 'Good; 3 bags torn'],
                    'free_days' => ['label' => 'Free days', 'type' => 'number', 'width' => 2],
                    'charge_basis' => ['label' => 'Charged', 'type' => 'select', 'width' => 3, 'options' => Storage::BASIS],
                    'daily_rate' => ['label' => 'Rate per day', 'type' => 'money', 'width' => 3],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 2, 'options' => Currency::options()],
                    'billed_until' => ['label' => 'Billed up to', 'type' => 'date', 'width' => 2, 'readonly' => true, 'readonly_note' => 'moved by billing'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'width' => 3, 'options' => Storage::STATUS, 'readonly' => true, 'readonly_note' => 'follows the gate passes'],
                    'quantity_released' => ['label' => 'Released so far', 'type' => 'decimal', 'width' => 3, 'readonly' => true, 'readonly_note' => 'from the gate passes'],
                    'grn_file' => ['label' => 'Signed GRN (scan)', 'type' => 'file', 'width' => 6],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'links' => [
                    ['label' => 'Print GRN', 'icon' => 'printer', 'tone' => 'outline-primary', 'new_tab' => true,
                     'permission' => 'storage_lots', 'ability' => 'view',
                     'route' => ['storage_lots', ':id', 'grn'],
                     'hint' => 'The goods received note for the driver and the storekeeper to sign.'],
                    ['label' => 'Release goods (gate pass)', 'icon' => 'log-out', 'tone' => 'primary',
                     'permission' => 'storage_releases', 'ability' => 'create',
                     'when' => ['status' => ['in_store', 'partly_released']],
                     'route' => ['storage_releases', 'create'], 'carry' => ['lot_id' => 'id'],
                     'hint' => 'Lets part or all of this lot out, to a named person on a named truck.'],
                    ['label' => 'Bill storage to date', 'icon' => 'file-text', 'tone' => 'success', 'method' => 'post',
                     'permission' => 'invoices', 'ability' => 'create',
                     'route' => ['storage_lots', ':id', 'bill'],
                     'hint' => 'Raises a draft invoice for every lot this customer has with us, for the days not billed yet, after the free days.'],
                ],
                'related' => [
                    ['title' => 'Gate passes', 'icon' => 'log-out', 'permission' => 'storage_releases', 'module' => 'storage_releases',
                     'sql' => 'SELECT id, gate_pass_no, quantity, released_at, collected_by, vehicle_plate FROM storage_releases WHERE lot_id = :id AND deleted_at IS NULL ORDER BY released_at',
                     'columns' => ['gate_pass_no' => 'Gate pass', 'quantity' => 'Quantity', 'released_at' => 'Released', 'collected_by' => 'Collected by', 'vehicle_plate' => 'Truck'],
                     'empty' => 'Nothing has left this lot yet.'],
                ],
            ],

            'storage_releases' => [
                'title' => 'Gate passes',
                'singular' => 'Gate pass',
                'kicker' => 'Goods leaving storage',
                'icon' => 'log-out',
                'description' => 'Every release of a customer\'s goods from storage: how much, to whom, on which truck, on whose authority. The gate checks the printed pass.',
                'button' => 'New gate pass',
                'table' => 'storage_releases',
                'alias' => 'sr',
                'code' => 'gate_pass_no',
                'order' => 'sr.released_at DESC, sr.id DESC',
                'track_changes' => true,
                'joins' => 'INNER JOIN storage_lots sl ON sl.id = sr.lot_id LEFT JOIN customers c ON c.id = sl.customer_id LEFT JOIN users u ON u.id = sr.authorised_by',
                'select' => ['sr.id', 'sr.gate_pass_no', 'sl.lot_code AS lot', 'c.customer_name AS customer', 'sl.description AS goods', 'sr.quantity', 'sl.unit', 'sr.released_at', 'sr.collected_by', 'sr.vehicle_plate', 'u.full_name AS authorised'],
                'search' => ['sr.gate_pass_no', 'sl.lot_code', 'sr.collected_by', 'sr.vehicle_plate', 'c.customer_name'],
                'list' => [
                    'gate_pass_no' => ['label' => 'Gate pass', 'type' => 'code'],
                    'lot' => ['label' => 'Lot'],
                    'customer' => ['label' => 'Customer'],
                    'goods' => ['label' => 'Goods'],
                    'quantity' => ['label' => 'Quantity', 'type' => 'decimal'],
                    'unit' => ['label' => 'Unit'],
                    'released_at' => ['label' => 'Released', 'type' => 'datetime'],
                    'collected_by' => ['label' => 'Collected by'],
                    'vehicle_plate' => ['label' => 'Truck', 'empty' => '—'],
                ],
                'sections' => [
                    ['title' => 'What leaves', 'icon' => 'log-out', 'hint' => 'It cannot be more than is still in the lot.', 'fields' => ['gate_pass_no', 'lot_id', 'quantity', 'released_at', 'customer_order_ref']],
                    ['title' => 'Who takes it', 'icon' => 'user', 'fields' => ['collected_by', 'collected_id_no', 'vehicle_plate', 'trip_id', 'authorised_by', 'notes']],
                ],
                'fields' => [
                    'gate_pass_no' => ['label' => 'Gate pass number', 'type' => 'text', 'required' => true, 'width' => 3, 'auto' => 'GP'],
                    'lot_id' => ['label' => 'From lot', 'type' => 'relation', 'required' => true, 'width' => 3, 'relation' => ['table' => 'storage_lots', 'label' => 'lot_code', 'where' => 'deleted_at IS NULL']],
                    'quantity' => ['label' => 'Quantity', 'type' => 'decimal', 'required' => true, 'width' => 2],
                    'released_at' => ['label' => 'Released at', 'type' => 'datetime', 'required' => true, 'width' => 2],
                    'customer_order_ref' => ['label' => 'Customer release order', 'type' => 'text', 'width' => 2, 'help' => 'The customer\'s written instruction to release.'],
                    'collected_by' => ['label' => 'Collected by', 'type' => 'text', 'required' => true, 'width' => 4],
                    'collected_id_no' => ['label' => 'Their ID number', 'type' => 'text', 'width' => 3],
                    'vehicle_plate' => ['label' => 'Truck plate', 'type' => 'text', 'width' => 2],
                    'trip_id' => ['label' => 'On our trip', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'trips', 'label' => 'reference_code', 'where' => 'deleted_at IS NULL']],
                    'authorised_by' => ['label' => 'Authorised by', 'type' => 'relation', 'width' => 4, 'readonly' => true, 'readonly_note' => 'you', 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 8],
                ],
                'links' => [
                    ['label' => 'Print gate pass', 'icon' => 'printer', 'tone' => 'primary', 'new_tab' => true,
                     'permission' => 'storage_releases', 'ability' => 'view',
                     'route' => ['storage_releases', ':id', 'pass'],
                     'hint' => 'For the gate and for the person collecting to sign.'],
                ],
            ],

            'warehouse' => [
                'title' => 'Warehouse and inventory',
                'singular' => 'Stock item',
                'kicker' => 'Stock control',
                'icon' => 'package',
                'description' => 'Stock held per warehouse. The quantity is the running balance of the stock ledger.',
                'button' => 'Add item',
                'table' => 'inventory_items',
                'alias' => 'it',
                // afterSave needs to tell a new item from an edited one.
                'track_changes' => true,
                'code' => 'sku',
                'order' => 'it.item_name',
                'joins' => 'INNER JOIN warehouses w ON w.id = it.warehouse_id',
                'select' => ['it.id', 'it.sku', 'it.item_name', 'it.category', 'w.warehouse_name AS warehouse', 'it.quantity', 'it.unit_of_measure', 'it.minimum_level', 'it.unit_cost', '(it.quantity * it.unit_cost) AS stock_value', 'it.expiry_date', 'it.status'],
                'search' => ['it.sku', 'it.item_name', 'it.category', 'it.batch_number', 'w.warehouse_name'],
                'list' => [
                    'sku' => ['label' => 'SKU', 'type' => 'code'],
                    'item_name' => ['label' => 'Item'],
                    'category' => ['label' => 'Category', 'empty' => 'Uncategorised'],
                    'warehouse' => ['label' => 'Warehouse'],
                    'quantity' => ['label' => 'On hand', 'type' => 'decimal'],
                    'minimum_level' => ['label' => 'Minimum', 'type' => 'decimal'],
                    'stock_value' => ['label' => 'Value', 'type' => 'money'],
                    'expiry_date' => ['label' => 'Expires', 'type' => 'expiry'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'it.status', 'options' => self::STOCK_STATUS]],
                'sections' => [
                    ['title' => 'Item', 'icon' => 'package', 'fields' => ['sku', 'item_name', 'category', 'warehouse_id']],
                    ['title' => 'Stock levels', 'icon' => 'bar-chart', 'hint' => 'Record stock in and stock out as movements; this balance follows them.', 'fields' => ['quantity', 'unit_of_measure', 'minimum_level', 'reorder_quantity', 'status']],
                    ['title' => 'Valuation and batch', 'icon' => 'dollar-sign', 'fields' => ['unit_cost', 'batch_number', 'expiry_date', 'storage_temperature']],
                ],
                'fields' => [
                    'sku' => ['label' => 'SKU', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'SKU', 'help' => 'Unique stock keeping unit.'],
                    'item_name' => ['label' => 'Item name', 'type' => 'text', 'required' => true, 'width' => 5],
                    'category' => ['label' => 'Category', 'type' => 'select', 'width' => 3, 'options' => Lookup::options('item_category')],
                    'warehouse_id' => ['label' => 'Warehouse', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => 'deleted_at IS NULL']],
                    'quantity' => ['label' => 'Quantity on hand', 'type' => 'decimal', 'required' => true, 'width' => 4],
                    'unit_of_measure' => ['label' => 'Unit of measure', 'type' => 'select', 'width' => 4, 'options' => Lookup::options('unit_of_measure')],
                    'minimum_level' => ['label' => 'Minimum level', 'type' => 'decimal', 'required' => true, 'width' => 4, 'help' => 'Below this the item is flagged for reorder.'],
                    'reorder_quantity' => ['label' => 'Reorder quantity', 'type' => 'decimal', 'width' => 4],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::STOCK_STATUS],
                    'unit_cost' => ['label' => 'Unit cost', 'type' => 'money', 'required' => true, 'width' => 4],
                    'batch_number' => ['label' => 'Batch number', 'type' => 'text', 'width' => 4],
                    'expiry_date' => ['label' => 'Expiry date', 'type' => 'date', 'width' => 4],
                    'storage_temperature' => ['label' => 'Storage temperature', 'type' => 'text', 'width' => 4, 'placeholder' => '2 to 8 C'],
                ],
                'related' => [
                    ['title' => 'Stock movements', 'icon' => 'repeat', 'permission' => 'movements', 'module' => 'movements',
                     'sql' => 'SELECT id, movement_code, movement_type, quantity, balance_after, reference_code, moved_at FROM stock_movements WHERE item_id = :id ORDER BY moved_at DESC, id DESC LIMIT 15',
                     'columns' => ['movement_code' => 'Movement', 'movement_type' => 'Type', 'quantity' => 'Quantity', 'balance_after' => 'Balance', 'reference_code' => 'Reference', 'moved_at' => 'When'],
                     'empty' => 'No movement recorded; the opening balance was set when the item was created.'],
                ],
            ],

            'movements' => [
                'title' => 'Stock movements',
                'singular' => 'Movement',
                'kicker' => 'Stock ledger',
                'icon' => 'repeat',
                'description' => 'Every stock in, stock out, transfer, damage and adjustment, with who did it and when.',
                'button' => 'Record movement',
                'table' => 'stock_movements',
                'alias' => 'sm',
                'code' => 'movement_code',
                'order' => 'sm.moved_at DESC, sm.id DESC',
                'soft_delete' => false,
                'joins' => 'INNER JOIN inventory_items it ON it.id = sm.item_id INNER JOIN warehouses w ON w.id = sm.warehouse_id LEFT JOIN users u ON u.id = sm.performed_by',
                'select' => ['sm.id', 'sm.movement_code', 'it.sku AS item_sku', 'it.item_name AS item', 'w.warehouse_name AS warehouse', 'sm.movement_type', 'sm.quantity', 'sm.balance_after', 'sm.reference_code', 'u.full_name AS performed_by', 'sm.moved_at'],
                'search' => ['sm.movement_code', 'sm.reference_code', 'it.item_name', 'it.sku'],
                'list' => [
                    'movement_code' => ['label' => 'Movement', 'type' => 'code'],
                    'item' => ['label' => 'Item'],
                    'warehouse' => ['label' => 'Warehouse'],
                    'movement_type' => ['label' => 'Type', 'type' => 'badge'],
                    'quantity' => ['label' => 'Quantity', 'type' => 'decimal'],
                    'balance_after' => ['label' => 'Balance after', 'type' => 'decimal'],
                    'reference_code' => ['label' => 'Reference', 'empty' => 'Manual'],
                    'performed_by' => ['label' => 'By'],
                    'moved_at' => ['label' => 'When', 'type' => 'datetime'],
                ],
                'filters' => ['movement_type' => ['label' => 'Type', 'column' => 'sm.movement_type', 'options' => self::movementTypes()]],
                'sections' => [
                    ['title' => 'Movement', 'icon' => 'repeat', 'hint' => 'Stock in and returns add to the balance; stock out, damage and transfer out subtract from it.', 'fields' => ['movement_code', 'item_id', 'warehouse_id', 'movement_type']],
                    ['title' => 'Quantity', 'icon' => 'hash', 'fields' => ['quantity', 'unit_cost', 'moved_at']],
                    ['title' => 'Source document', 'icon' => 'link', 'fields' => ['reference_type', 'reference_code', 'notes']],
                ],
                'fields' => [
                    'movement_code' => ['label' => 'Movement reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'MOV'],
                    'item_id' => ['label' => 'Item', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'inventory_items', 'label' => 'item_name', 'where' => 'deleted_at IS NULL']],
                    'warehouse_id' => ['label' => 'Warehouse', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => 'deleted_at IS NULL']],
                    'movement_type' => ['label' => 'Movement type', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::movementTypes()],
                    'quantity' => ['label' => 'Quantity', 'type' => 'decimal', 'required' => true, 'width' => 4, 'help' => 'Always a positive number; the type decides the direction.'],
                    'unit_cost' => ['label' => 'Unit cost', 'type' => 'money', 'width' => 4],
                    'moved_at' => ['label' => 'Moved at', 'type' => 'datetime', 'required' => true, 'width' => 4],
                    'reference_type' => ['label' => 'Source document type', 'type' => 'select', 'width' => 4, 'options' => ['purchase' => 'Purchase request', 'delivery' => 'Delivery', 'trip' => 'Trip', 'adjustment' => 'Manual adjustment', 'transfer' => 'Warehouse transfer']],
                    'reference_code' => ['label' => 'Source document reference', 'type' => 'text', 'width' => 4, 'placeholder' => 'PR-KFF-001'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
            ],

            'procurement' => [
                'title' => 'Procurement',
                'singular' => 'Purchase request',
                'kicker' => 'Purchasing workflow',
                'icon' => 'shopping-cart',
                'description' => 'Purchase requests with itemised lines, approval and goods received into stock.',
                'button' => 'New purchase request',
                'table' => 'purchase_requests',
                'alias' => 'p',
                'code' => 'request_code',
                'order' => 'p.id DESC',
                'joins' => 'LEFT JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN users u ON u.id = p.requested_by LEFT JOIN users ap ON ap.id = p.approved_by LEFT JOIN warehouses w ON w.id = p.warehouse_id',
                'select' => ['p.id', 'p.request_code', 'p.description', 's.supplier_name AS supplier', 'w.warehouse_name AS warehouse', 'p.category', 'p.amount', 'u.full_name AS requested_by', 'ap.full_name AS approver', 'p.expected_date', 'p.status'],
                'search' => ['p.request_code', 'p.description', 's.supplier_name'],
                'list' => [
                    'request_code' => ['label' => 'Request', 'type' => 'code'],
                    'description' => ['label' => 'Description'],
                    'supplier' => ['label' => 'Supplier', 'empty' => 'Not selected'],
                    'warehouse' => ['label' => 'Deliver to', 'empty' => 'Not set'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'requested_by' => ['label' => 'Requested by'],
                    'approver' => ['label' => 'Approved by', 'empty' => 'Awaiting'],
                    'expected_date' => ['label' => 'Expected', 'type' => 'expiry'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'p.status', 'options' => self::PURCHASE_STATUS]],
                'sections' => [
                    ['title' => 'Request', 'icon' => 'shopping-cart', 'fields' => ['request_code', 'description', 'category', 'requested_by']],
                    ['title' => 'Supplier and delivery', 'icon' => 'truck', 'fields' => ['supplier_id', 'warehouse_id', 'expected_date']],
                    ['title' => 'Value and approval', 'icon' => 'check-circle', 'hint' => 'Marking it received posts the line quantities into stock.', 'fields' => ['amount', 'currency', 'status', 'received_at']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'request_code' => ['label' => 'Request reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'PR'],
                    'description' => ['label' => 'Description', 'type' => 'textarea', 'required' => true, 'width' => 8],
                    'category' => ['label' => 'Category', 'type' => 'select', 'width' => 4, 'options' => Lookup::options('supplier_category')],
                    'requested_by' => ['label' => 'Requested by', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'users', 'label' => 'full_name', 'where' => 'deleted_at IS NULL']],
                    'supplier_id' => ['label' => 'Supplier', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'suppliers', 'label' => 'supplier_name', 'where' => "status = 'active' AND deleted_at IS NULL"]],
                    'warehouse_id' => ['label' => 'Deliver to warehouse', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'warehouses', 'label' => 'warehouse_name', 'where' => 'deleted_at IS NULL'], 'help' => 'Where received goods will be booked into stock.'],
                    'expected_date' => ['label' => 'Expected date', 'type' => 'date', 'width' => 4],
                    'amount' => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'width' => 4],
                    'currency' => ['label' => 'Currency', 'type' => 'select', 'width' => 2, 'options' => Currency::options(), 'help' => 'What the paper actually says.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::PURCHASE_STATUS],
                    'received_at' => ['label' => 'Received at', 'type' => 'datetime', 'width' => 4],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'lines' => [
                    'table' => 'purchase_request_lines',
                    'parent' => 'purchase_request_id',
                    'title' => 'Requested items',
                    'total_column' => 'amount',
                    'columns' => [
                        'item_name' => ['label' => 'Item', 'type' => 'text', 'required' => true],
                        'quantity' => ['label' => 'Qty', 'type' => 'decimal'],
                        'unit_of_measure' => ['label' => 'Unit', 'type' => 'text'],
                        'unit_price' => ['label' => 'Unit price', 'type' => 'money'],
                        'item_id' => ['label' => 'Stock item', 'type' => 'relation', 'relation' => ['table' => 'inventory_items', 'label' => 'item_name', 'where' => 'deleted_at IS NULL']],
                    ],
                ],
                'actions' => [
                    'approve' => ['label' => 'Approve request', 'to' => 'approved', 'from' => ['draft', 'quotation'], 'tone' => 'success'],
                    'reject' => ['label' => 'Reject', 'to' => 'rejected', 'from' => ['draft', 'quotation', 'approved'], 'tone' => 'danger', 'reason' => true],
                    'receive' => ['label' => 'Mark received', 'to' => 'received', 'from' => ['approved'], 'tone' => 'primary'],
                ],
            ],

            'suppliers' => [
                'title' => 'Suppliers',
                'singular' => 'Supplier',
                'kicker' => 'Vendor register',
                'icon' => 'users',
                'description' => 'Approved vendors with contact details, payment terms and performance rating.',
                'button' => 'Add supplier',
                'table' => 'suppliers',
                'alias' => 'sp',
                'code' => 'supplier_name',
                'order' => 'sp.supplier_name',
                'joins' => '',
                'select' => ['sp.id', 'sp.supplier_code', 'sp.supplier_name', 'sp.category', 'sp.contact_name', 'sp.phone', 'sp.email', 'sp.payment_terms_days', 'sp.rating', 'sp.status'],
                'search' => ['sp.supplier_name', 'sp.contact_name', 'sp.phone', 'sp.email', 'sp.tin_number'],
                'list' => [
                    'supplier_code' => ['label' => 'Code', 'type' => 'code', 'empty' => 'No code'],
                    'supplier_name' => ['label' => 'Supplier'],
                    'category' => ['label' => 'Category', 'empty' => 'General'],
                    'contact_name' => ['label' => 'Contact'],
                    'phone' => ['label' => 'Phone'],
                    'payment_terms_days' => ['label' => 'Terms', 'type' => 'number', 'suffix' => 'days'],
                    'rating' => ['label' => 'Rating', 'type' => 'rating'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'sp.status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]],
                'sections' => [
                    ['title' => 'Supplier', 'icon' => 'users', 'fields' => ['supplier_code', 'supplier_name', 'category', 'tin_number']],
                    ['title' => 'Contact', 'icon' => 'phone', 'fields' => ['contact_name', 'phone', 'email', 'address']],
                    ['title' => 'Terms', 'icon' => 'dollar-sign', 'fields' => ['payment_terms_days', 'rating', 'status']],
                ],
                'fields' => [
                    'supplier_code' => ['label' => 'Supplier code', 'type' => 'text', 'width' => 4, 'auto' => 'SUP'],
                    'supplier_name' => ['label' => 'Supplier name', 'type' => 'text', 'required' => true, 'width' => 5],
                    'category' => ['label' => 'Category', 'type' => 'select', 'width' => 3, 'options' => Lookup::options('procurement_category')],
                    'tin_number' => ['label' => 'TIN number', 'type' => 'text', 'width' => 4],
                    'contact_name' => ['label' => 'Contact person', 'type' => 'text', 'width' => 4],
                    'phone' => ['label' => 'Phone', 'type' => 'tel', 'width' => 4],
                    'email' => ['label' => 'Email', 'type' => 'email', 'width' => 4],
                    'address' => ['label' => 'Address', 'type' => 'text', 'width' => 8],
                    'payment_terms_days' => ['label' => 'Payment terms', 'type' => 'number', 'width' => 4, 'suffix' => 'days'],
                    'rating' => ['label' => 'Rating', 'type' => 'select', 'width' => 4, 'options' => [5 => '5 - Excellent', 4 => '4 - Good', 3 => '3 - Acceptable', 2 => '2 - Weak', 1 => '1 - Poor']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
                ],
                'related' => [
                    ['title' => 'Purchase requests', 'icon' => 'shopping-cart', 'permission' => 'procurement', 'module' => 'procurement',
                     'sql' => 'SELECT id, request_code, description, amount, status FROM purchase_requests WHERE supplier_id = :id AND deleted_at IS NULL ORDER BY id DESC LIMIT 10',
                     'columns' => ['request_code' => 'Request', 'description' => 'Description', 'amount' => 'Amount', 'status' => 'Status'],
                     'empty' => 'No purchase request placed with this supplier.'],
                ],
            ],
        ];
    }

    // -------------------------------------------------------- administration

    private static function administration(): array
    {
        return [
            'users' => [
                'title' => 'Users and permissions',
                'singular' => 'User',
                'kicker' => 'Access control',
                'icon' => 'users',
                'description' => 'System accounts, their role, privilege and login status.',
                'button' => 'Add user',
                'table' => 'users',
                'alias' => 'u',
                'code' => 'email',
                'order' => 'u.full_name',
                'joins' => 'INNER JOIN roles r ON r.id = u.role_id',
                'select' => ['u.id', 'u.full_name', 'u.email', 'r.role_name AS role', 'u.department', 'u.job_title', 'u.phone', 'u.last_login_at', 'u.status', 'u.prvg', 'u.must_change_password'],
                'search' => ['u.full_name', 'u.email', 'u.department', 'u.phone'],
                'list' => [
                    'full_name' => ['label' => 'User', 'type' => 'code'],
                    'email' => ['label' => 'Email'],
                    'role' => ['label' => 'Role'],
                    'department' => ['label' => 'Department', 'empty' => 'Not set'],
                    'job_title' => ['label' => 'Job title', 'empty' => 'Not set'],
                    'phone' => ['label' => 'Phone'],
                    'last_login_at' => ['label' => 'Last login', 'type' => 'datetime', 'empty' => 'Never'],
                    'prvg' => ['label' => 'Privilege', 'type' => 'privilege'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'u.status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'locked' => 'Locked']]],
                'sections' => [
                    ['title' => 'Person', 'icon' => 'user', 'fields' => ['full_name', 'email', 'phone']],
                    ['title' => 'Position', 'icon' => 'briefcase', 'fields' => ['role_id', 'department', 'job_title']],
                    ['title' => 'Account', 'icon' => 'lock', 'hint' => 'A new account is created with a one-time password that must be changed at first login.', 'fields' => ['status', 'prvg', 'must_change_password']],
                ],
                'fields' => [
                    'full_name' => ['label' => 'Full name', 'type' => 'text', 'required' => true, 'width' => 4],
                    'email' => ['label' => 'Email', 'type' => 'email', 'required' => true, 'width' => 4, 'help' => 'This is the login name and must be unique.'],
                    'phone' => ['label' => 'Phone', 'type' => 'tel', 'width' => 4],
                    'role_id' => ['label' => 'Role', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'roles', 'label' => 'role_name', 'empty' => null]],
                    'department' => ['label' => 'Department', 'type' => 'select', 'width' => 4, 'options' => Lookup::options('department')],
                    'job_title' => ['label' => 'Job title', 'type' => 'text', 'width' => 4],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'locked' => 'Locked']],
                    'prvg' => ['label' => 'Privilege', 'type' => 'select', 'width' => 4, 'options' => self::PRIVILEGES, 'help' => 'Only a privileged account may change this or switch roles.'],
                    'must_change_password' => ['label' => 'Force password change at next login', 'type' => 'checkbox', 'width' => 4],
                ],
            ],

            'lookups' => [
                'title' => 'Reference lists',
                'singular' => 'List entry',
                'kicker' => 'Configuration',
                'icon' => 'list',
                'description' => 'The choices the drop-downs offer: vehicle types, expense categories, units of measure, departments and the rest. Add what your company actually hauls, stores and spends on, and retire what it does not. Renaming an entry also renames it on every record that carries it.',
                'button' => 'Add list entry',
                'table' => 'lookup_values',
                'alias' => 'lv',
                'code' => 'value',
                'order' => 'lv.list_key, lv.sort_order, lv.value',
                'select' => ['lv.id', 'lv.list_key', 'lv.value', 'lv.label', 'lv.sort_order', 'lv.is_active', 'lv.notes'],
                'search' => ['lv.value', 'lv.label', 'lv.notes'],
                // Renaming has to follow the records, so the row is read before it is written.
                'track_changes' => true,
                'list' => [
                    'list_key' => ['label' => 'List', 'map' => self::lookupLists()],
                    'value' => ['label' => 'Name', 'type' => 'code'],
                    'label' => ['label' => 'Shown as'],
                    'sort_order' => ['label' => 'Order', 'type' => 'number'],
                    'notes' => ['label' => 'Notes'],
                    'is_active' => ['label' => 'In use', 'type' => 'yesno'],
                ],
                'filters' => [
                    'list_key' => ['label' => 'List', 'column' => 'lv.list_key', 'options' => self::lookupLists()],
                    'is_active' => ['label' => 'In use', 'column' => 'lv.is_active', 'options' => [1 => 'Yes', 0 => 'No']],
                ],
                'sections' => [
                    ['title' => 'The choice', 'icon' => 'list', 'fields' => ['list_key', 'value', 'sort_order']],
                    ['title' => 'How it behaves', 'icon' => 'settings', 'hint' => 'Retiring a choice keeps the records that already use it readable.', 'fields' => ['label', 'is_active', 'notes']],
                ],
                'fields' => [
                    'list_key' => ['label' => 'Which list', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::lookupLists(), 'help' => 'The drop-down this choice will appear in.'],
                    'value' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 5, 'placeholder' => 'Cement bulker', 'help' => 'Exactly as it should read on the form.'],
                    'sort_order' => ['label' => 'Position', 'type' => 'number', 'width' => 3, 'help' => 'Lower numbers come first.'],
                    'label' => ['label' => 'Shown as', 'type' => 'text', 'width' => 4, 'help' => 'Only if it should read differently. Usually blank.'],
                    'is_active' => ['label' => 'Offer this choice', 'type' => 'checkbox', 'width' => 4, 'help' => 'Off retires it.'],
                    'notes' => ['label' => 'Notes', 'type' => 'text', 'width' => 4, ],
                ],
            ],

            'cheque_books' => [
                'title' => 'Cheque books',
                'singular' => 'Cheque book',
                'kicker' => 'Money out',
                'icon' => 'book',
                'description' => 'The numbered books the bank issued, so the system can offer the next leaf instead of asking anyone to remember it — and so a missing number is visible.',
                'button' => 'Add cheque book',
                'table' => 'gl_cheque_books',
                'alias' => 'cb',
                'code' => 'id',
                'order' => 'cb.status, cb.first_no',
                'joins' => 'LEFT JOIN gl_accounts ga ON ga.id = cb.bank_account_id',
                'select' => ['cb.id', 'ga.account_name AS bank', 'cb.prefix', 'cb.first_no', 'cb.last_no', 'cb.next_no', 'cb.status', 'cb.notes'],
                'search' => ['ga.account_name', 'cb.notes', 'cb.prefix'],
                'list' => [
                    'bank' => ['label' => 'Drawn on', 'type' => 'code'],
                    'prefix' => ['label' => 'Prefix', 'empty' => '—'],
                    'first_no' => ['label' => 'From', 'type' => 'number'],
                    'last_no' => ['label' => 'To', 'type' => 'number'],
                    'next_no' => ['label' => 'Next leaf', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => ['status' => ['label' => 'Status', 'column' => 'cb.status', 'options' => ['active' => 'Active', 'finished' => 'Finished']]],
                'sections' => [
                    ['title' => 'The book', 'icon' => 'book', 'hint' => 'Next leaf only moves forward: a spoiled cheque is spent, not returned.', 'fields' => ['bank_account_id', 'prefix', 'first_no', 'last_no', 'next_no', 'status']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'bank_account_id' => ['label' => 'Drawn on', 'type' => 'relation', 'required' => true, 'width' => 6, 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_bank = 1 AND is_active = 1'], ],
                    'prefix' => ['label' => 'Prefix', 'type' => 'text', 'width' => 3, 'placeholder' => 'BK', ],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => ['active' => 'Active', 'finished' => 'Finished']],
                    'first_no' => ['label' => 'First number', 'type' => 'number', 'required' => true, 'width' => 4],
                    'last_no' => ['label' => 'Last number', 'type' => 'number', 'required' => true, 'width' => 4],
                    'next_no' => ['label' => 'Next unused number', 'type' => 'number', 'required' => true, 'width' => 4, 'help' => 'Set it to the first number on a new book.'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
            ],

            'cheques' => [
                'title' => 'Cheques',
                'singular' => 'Cheque',
                'kicker' => 'Money out',
                'icon' => 'credit-card',
                'description' => 'Payments made by cheque: which account it is drawn on, which leaf, who it is payable to and what the money is for. Issuing one takes it off the bank and posts it to the ledger; the bank taking it is recorded separately, which is what makes a reconciliation possible.',
                'button' => 'Write a cheque',
                'table' => 'gl_cheques',
                'alias' => 'ch',
                'code' => 'reference',
                'order' => 'ch.cheque_date DESC, ch.id DESC',
                'track_changes' => true,
                'joins' => 'LEFT JOIN gl_accounts ga ON ga.id = ch.bank_account_id LEFT JOIN suppliers s ON s.id = ch.supplier_id',
                'select' => ['ch.id', 'ch.reference', 'ch.cheque_no', 'ga.account_name AS bank', 'ch.payee_name', 'ch.cheque_date', 'ch.amount', 'ch.status', 'ch.presented_on'],
                'search' => ['ch.reference', 'ch.cheque_no', 'ch.payee_name', 'ch.memo'],
                'list' => [
                    'reference' => ['label' => 'Reference', 'type' => 'code'],
                    'cheque_no' => ['label' => 'Leaf', 'empty' => 'Not written'],
                    'bank' => ['label' => 'Drawn on'],
                    'payee_name' => ['label' => 'Payable to'],
                    'cheque_date' => ['label' => 'Dated', 'type' => 'date'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'presented_on' => ['label' => 'Taken by bank', 'type' => 'date', 'empty' => 'Not yet'],
                    'status' => ['label' => 'Status', 'type' => 'badge'],
                ],
                'filters' => [
                    'status' => ['label' => 'Status', 'column' => 'ch.status', 'options' => ['draft' => 'Draft', 'issued' => 'Issued', 'presented' => 'Presented', 'void' => 'Void']],
                    'bank_account_id' => ['label' => 'Bank account', 'column' => 'ch.bank_account_id', 'options' => []],
                ],
                'sections' => [
                    ['title' => 'The cheque', 'icon' => 'credit-card', 'hint' => 'The leaf number comes from the open cheque book. It can be written once.', 'fields' => ['reference', 'bank_account_id', 'cheque_book_id', 'cheque_no', 'cheque_date', 'amount']],
                    ['title' => 'Payable to', 'icon' => 'user', 'fields' => ['payee_name', 'supplier_id', 'payee_address']],
                    ['title' => 'What it is for', 'icon' => 'file-text', 'hint' => 'Add the accounts to charge under the lines below, after saving.', 'fields' => ['memo', 'notes']],
                    ['title' => 'Bank and cancellation', 'icon' => 'check-square', 'fields' => ['status', 'presented_on', 'void_reason']],
                ],
                'fields' => [
                    'reference' => ['label' => 'Reference', 'type' => 'text', 'required' => true, 'width' => 4, 'auto' => 'CHQ'],
                    'bank_account_id' => ['label' => 'Drawn on', 'type' => 'relation', 'required' => true, 'width' => 4, 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_bank = 1 AND is_active = 1'], ],
                    'cheque_book_id' => ['label' => 'From book', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'gl_cheque_books', 'label' => 'id', 'where' => "status = 'active' AND deleted_at IS NULL"], ],
                    'cheque_no' => ['label' => 'Leaf number', 'type' => 'text', 'width' => 4, 'help' => 'Blank takes the next number from the book.'],
                    'cheque_date' => ['label' => 'Dated', 'type' => 'date', 'required' => true, 'width' => 4],
                    'amount' => ['label' => 'Amount', 'type' => 'money', 'width' => 4, 'readonly' => true, 'readonly_note' => 'added from the lines', 'help' => 'Added up from the lines below.'],
                    'payee_name' => ['label' => 'Payable to', 'type' => 'text', 'required' => true, 'width' => 5, 'placeholder' => 'Kigali Auto Care Ltd'],
                    'supplier_id' => ['label' => 'Supplier on file', 'type' => 'relation', 'width' => 3, 'relation' => ['table' => 'suppliers', 'label' => 'supplier_name', 'where' => 'deleted_at IS NULL']],
                    'payee_address' => ['label' => 'Address', 'type' => 'text', 'width' => 4],
                    'memo' => ['label' => 'Memo on the leaf', 'type' => 'text', 'width' => 12, 'placeholder' => 'Tyres, order PR-2026-0001'],
                    'notes' => ['label' => 'Internal note', 'type' => 'textarea', 'width' => 12],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['draft' => 'Draft', 'issued' => 'Issued', 'presented' => 'Presented', 'void' => 'Void'], 'readonly' => true, 'readonly_note' => 'set by the buttons above'],
                    'presented_on' => ['label' => 'Taken by the bank on', 'type' => 'date', 'width' => 4, 'help' => 'Filled in from the bank statement, or by the Mark presented button.'],
                    'void_reason' => ['label' => 'Cancelled because', 'type' => 'text', 'width' => 4, 'readonly' => true, 'readonly_note' => 'from the cancellation'],
                ],
                'lines' => [
                    'table' => 'gl_cheque_lines',
                    'parent' => 'cheque_id',
                    'title' => 'What the money is for',
                    'total_column' => 'amount',
                    // A cheque line is an amount, not a quantity times a price.
                    'line_amount' => 'amount',
                    'columns' => [
                        'account_id' => ['label' => 'Account', 'type' => 'relation', 'required' => true, 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_header = 0 AND is_active = 1']],
                        'description' => ['label' => 'Description', 'type' => 'text'],
                        'amount' => ['label' => 'Amount', 'type' => 'money'],
                    ],
                ],
                'actions' => [
                    'issue' => ['label' => 'Issue cheque', 'to' => 'issued', 'from' => ['draft'], 'tone' => 'primary'],
                    'present' => ['label' => 'Mark presented', 'to' => 'presented', 'from' => ['issued'], 'tone' => 'success'],
                    'void' => ['label' => 'Void cheque', 'to' => 'void', 'from' => ['draft', 'issued'], 'tone' => 'danger', 'reason' => true],
                ],
                'related' => [
                    ['title' => 'Ledger entry', 'icon' => 'book', 'permission' => 'journal',
                     'sql' => 'SELECT e.entry_no, e.entry_date, e.memo, a.account_code, a.account_name, l.debit, l.credit
                                 FROM gl_journal_entries e
                                 INNER JOIN gl_journal_lines l ON l.entry_id = e.id
                                 INNER JOIN gl_accounts a ON a.id = l.account_id
                                WHERE e.source_type = "cheque" AND e.source_id = :id AND e.deleted_at IS NULL
                                ORDER BY l.line_no',
                     'columns' => ['entry_no' => 'Entry', 'account_code' => 'Code', 'account_name' => 'Account', 'debit' => 'Debit', 'credit' => 'Credit'],
                     'empty' => 'Nothing is posted until the cheque is issued.'],
                ],
            ],

            'budgets' => [
                'title' => 'Budget',
                'singular' => 'Budget line',
                'kicker' => 'Planning',
                'icon' => 'target',
                'description' => 'What each account is expected to earn or cost. Actual figures are never typed here: they are read from the ledger, so Budget versus actual cannot drift from the books.',
                'button' => 'Add budget line',
                'table' => 'gl_budgets',
                'alias' => 'bg',
                'code' => 'id',
                'order' => 'bg.fiscal_year DESC, bg.month, ga.account_code',
                'joins' => 'INNER JOIN gl_accounts ga ON ga.id = bg.account_id',
                'select' => ['bg.id', 'bg.fiscal_year', 'ga.account_code', 'ga.account_name AS account', 'bg.month', 'bg.amount', 'bg.notes'],
                'search' => ['ga.account_code', 'ga.account_name', 'bg.notes'],
                'list' => [
                    'fiscal_year' => ['label' => 'Year', 'type' => 'code'],
                    'account_code' => ['label' => 'Code'],
                    'account' => ['label' => 'Account'],
                    'month' => ['label' => 'Period', 'map' => self::budgetMonths()],
                    'amount' => ['label' => 'Budgeted', 'type' => 'money'],
                    'notes' => ['label' => 'Notes', 'empty' => '—'],
                ],
                'filters' => [
                    'fiscal_year' => ['label' => 'Year', 'column' => 'bg.fiscal_year', 'options' => []],
                    'month' => ['label' => 'Period', 'column' => 'bg.month', 'options' => self::budgetMonths()],
                ],
                'sections' => [
                    ['title' => 'The line', 'icon' => 'target', 'hint' => 'One figure per account per period.', 'fields' => ['fiscal_year', 'account_id', 'month', 'amount']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['notes']],
                ],
                'fields' => [
                    'fiscal_year' => ['label' => 'Financial year', 'type' => 'number', 'required' => true, 'width' => 3],
                    'account_id' => ['label' => 'Account', 'type' => 'relation', 'required' => true, 'width' => 5, 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_header = 0 AND is_active = 1']],
                    'month' => ['label' => 'Period', 'type' => 'select', 'required' => true, 'width' => 2, 'options' => self::budgetMonths()],
                    'amount' => ['label' => 'Budgeted amount', 'type' => 'money', 'required' => true, 'width' => 2],
                    'notes' => ['label' => 'Notes', 'type' => 'text', 'width' => 12, 'placeholder' => 'Assumes two extra trucks from July'],
                ],
                'actions' => [],
            ],

            'currencies' => [
                'title' => 'Currencies',
                'singular' => 'Currency',
                'kicker' => 'Cross-border',
                'icon' => 'globe',
                'description' => 'The money this company handles. Money is recorded exactly as it was paid, in its own currency, and never converted: each currency keeps its own books. The base currency is the one pages open in first.',
                'button' => 'Add currency',
                'table' => 'currencies',
                'alias' => 'cu',
                'code' => 'code',
                'order' => 'cu.sort_order, cu.code',
                'select' => ['cu.id', 'cu.code', 'cu.currency_name', 'cu.symbol', 'cu.country', 'cu.decimals', 'cu.is_base', 'cu.is_active', 'cu.sort_order'],
                'search' => ['cu.code', 'cu.currency_name', 'cu.country'],
                'list' => [
                    'code' => ['label' => 'Code', 'type' => 'code'],
                    'currency_name' => ['label' => 'Currency'],
                    'symbol' => ['label' => 'Symbol', 'empty' => '—'],
                    'country' => ['label' => 'Used in', 'empty' => '—'],
                    'decimals' => ['label' => 'Decimals', 'type' => 'number'],
                    'is_base' => ['label' => 'Base', 'type' => 'yesno'],
                    'is_active' => ['label' => 'In use', 'type' => 'yesno'],
                ],
                'filters' => ['is_active' => ['label' => 'In use', 'column' => 'cu.is_active', 'options' => [1 => 'Yes', 0 => 'No']]],
                'sections' => [
                    ['title' => 'The currency', 'icon' => 'globe', 'hint' => 'The three letters the banks use. Every record stores this, so it does not change.', 'fields' => ['code', 'currency_name', 'symbol', 'country']],
                    ['title' => 'How it behaves', 'icon' => 'settings', 'hint' => 'Exactly one currency is the base.', 'fields' => ['decimals', 'sort_order', 'is_base', 'is_active']],
                ],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => 'KES', ],
                    'currency_name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 5, 'placeholder' => 'Kenyan shilling'],
                    'symbol' => ['label' => 'Symbol', 'type' => 'text', 'width' => 2, 'placeholder' => 'KSh'],
                    'country' => ['label' => 'Used in', 'type' => 'text', 'width' => 2, 'placeholder' => 'Kenya'],
                    'decimals' => ['label' => 'Decimal places', 'type' => 'number', 'width' => 3, ],
                    'sort_order' => ['label' => 'Position', 'type' => 'number', 'width' => 3],
                    'is_base' => ['label' => 'The books are kept in this', 'type' => 'checkbox', 'width' => 3],
                    'is_active' => ['label' => 'Offer this currency', 'type' => 'checkbox', 'width' => 3],
                ],
            ],

            'payment_methods' => [
                'title' => 'Payment methods',
                'singular' => 'Payment method',
                'kicker' => 'Money in and out',
                'icon' => 'credit-card',
                'description' => 'Every account the company pays from and is paid into, with all its details: bank, branch, account number, the name it is held in, SWIFT and currency. Make one method per real account — "Bank of Kigali – RWF", "Bank of Kigali – USD", "MTN MoMo pay code". What you set here is what the payment forms offer, which account the ledger moves, what a customer is told on an invoice, and what every payment shows as where the money went.',
                'button' => 'Add payment method',
                'table' => 'payment_methods',
                'alias' => 'pm',
                'code' => 'method_name',
                'order' => 'pm.sort_order, pm.method_name',
                'joins' => 'LEFT JOIN gl_accounts ga ON ga.id = pm.gl_account_id',
                'select' => ['pm.id', 'pm.method_key', 'pm.method_name', 'ga.account_name AS account', 'ga.account_code', 'pm.provider_name', 'pm.branch_name', 'pm.account_name AS holder', 'pm.account_number', 'pm.currency', 'pm.direction', 'pm.show_on_invoice', 'pm.is_active', 'pm.sort_order',
                    "CASE WHEN ga.is_bank = 1 THEN (SELECT COALESCE(SUM(jl.debit - jl.credit), 0) FROM gl_journal_lines jl INNER JOIN gl_journal_entries je ON je.id = jl.entry_id WHERE jl.account_id = pm.gl_account_id AND je.status = 'posted' AND je.deleted_at IS NULL AND je.currency = COALESCE(pm.currency, '" . Currency::base() . "')) END AS balance"],
                'search' => ['pm.method_name', 'pm.provider_name', 'pm.account_number', 'pm.account_name', 'ga.account_name'],
                'list' => [
                    'method_name' => ['label' => 'Method', 'type' => 'code'],
                    'provider_name' => ['label' => 'Bank or network', 'empty' => 'Not filled in'],
                    'account_number' => ['label' => 'Account number', 'empty' => '—'],
                    'holder' => ['label' => 'In the name of', 'empty' => '—'],
                    'currency' => ['label' => 'Cur.', 'empty' => '—'],
                    'balance' => ['label' => 'Balance now', 'type' => 'money'],
                    'account_code' => ['label' => 'Ledger code'],
                    'account' => ['label' => 'Posts to', 'empty' => 'Not mapped'],
                    'direction' => ['label' => 'Used for', 'type' => 'label'],
                    'show_on_invoice' => ['label' => 'On invoices', 'type' => 'yesno'],
                    'is_active' => ['label' => 'In use', 'type' => 'yesno'],
                ],
                'filters' => [
                    'direction' => ['label' => 'Used for', 'column' => 'pm.direction', 'options' => ['in' => 'Money received', 'out' => 'Money paid', 'both' => 'Both']],
                    'is_active' => ['label' => 'In use', 'column' => 'pm.is_active', 'options' => [1 => 'Yes', 0 => 'No']],
                ],
                'sections' => [
                    ['title' => 'The method', 'icon' => 'credit-card', 'hint' => 'The name is what everyone sees. The key is what payments store and never changes.', 'fields' => ['method_name', 'direction']],
                    ['title' => 'Where the money goes', 'icon' => 'book', 'hint' => 'The account this money moves through in the ledger.', 'fields' => ['gl_account_id']],
                    ['title' => 'Account details', 'icon' => 'hash', 'hint' => 'The real account behind this method, exactly as on the bank\'s papers. These details are shown on invoices, on every payment as where the money went, and on the bank statements.', 'fields' => ['provider_name', 'branch_name', 'account_name', 'account_number', 'currency', 'swift_code', 'phone_number']],
                    ['title' => 'Opening balance', 'icon' => 'flag', 'fields' => ['opening_balance', 'opening_date']],
                    ['title' => 'On the invoice', 'icon' => 'file-text', 'fields' => ['payment_details', 'instructions', 'show_on_invoice', 'is_active']],
                ],
                'fields' => [
                    'method_name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 4, 'placeholder' => 'Bank of Kigali transfer'],
                    'direction' => ['label' => 'Used for', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['both' => 'Money in and out', 'in' => 'Money received only', 'out' => 'Money paid only']],
                    'gl_account_id' => ['label' => 'Posts to account', 'type' => 'relation', 'width' => 12, 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_header = 0 AND is_active = 1'], ],
                    'provider_name' => ['label' => 'Bank or network', 'type' => 'text', 'width' => 4, 'placeholder' => 'Bank of Kigali', 'help' => 'For mobile money, the network: MTN Rwanda, Airtel Rwanda.'],
                    'branch_name' => ['label' => 'Branch', 'type' => 'text', 'width' => 4, 'placeholder' => 'Nyarugenge Branch'],
                    'account_name' => ['label' => 'Account held in the name of', 'type' => 'text', 'width' => 4, 'placeholder' => 'The name on the bank account'],
                    'account_number' => ['label' => 'Account number or pay code', 'type' => 'text', 'width' => 4, 'placeholder' => '000401234567890', 'help' => 'For mobile money, the merchant or pay code.'],
                    'currency' => ['label' => 'Currency of the account', 'type' => 'select', 'width' => 4, 'options' => Currency::options(), 'help' => 'An RWF account and a USD account are two methods.'],
                    'opening_balance' => ['label' => 'Opening balance', 'type' => 'money', 'width' => 4],
                    'opening_date' => ['label' => 'As at', 'type' => 'date', 'width' => 4],
                    'swift_code' => ['label' => 'SWIFT / BIC', 'type' => 'text', 'width' => 4, 'placeholder' => 'BKIGRWRW', 'help' => 'Only needed for payments from outside the country.'],
                    'phone_number' => ['label' => 'Phone number', 'type' => 'tel', 'width' => 4, 'placeholder' => '+250 788 000 001', 'help' => 'The number a mobile money account is registered to.'],
                    'payment_details' => ['label' => 'Anything else', 'type' => 'text', 'width' => 12, 'placeholder' => 'Quote the invoice number as the reference', 'help' => 'Only if the fields above do not cover it.'],
                    'instructions' => ['label' => 'Internal note', 'type' => 'text', 'width' => 12, 'help' => 'Only for your own staff — never shown to a customer.'],
                    'show_on_invoice' => ['label' => 'Show on invoices', 'type' => 'checkbox', 'width' => 6],
                    'is_active' => ['label' => 'Offer this method', 'type' => 'checkbox', 'width' => 6, 'help' => 'Turn it off to retire it. Payments already recorded under it are untouched.'],
                ],
                'related' => [
                    ['title' => 'Balance', 'icon' => 'dollar-sign', 'permission' => 'payment_methods',
                     'sql' => "SELECT je.currency,
                                    FORMAT(COALESCE(SUM(CASE WHEN je.source_type = 'method_opening' THEN jl.debit - jl.credit END), 0), 2) AS opening,
                                    FORMAT(COALESCE(SUM(CASE WHEN je.source_type <> 'method_opening' THEN jl.debit END), 0), 2) AS money_in,
                                    FORMAT(COALESCE(SUM(CASE WHEN je.source_type <> 'method_opening' THEN jl.credit END), 0), 2) AS money_out,
                                    FORMAT(COALESCE(SUM(jl.debit - jl.credit), 0), 2) AS balance
                               FROM payment_methods pm
                               INNER JOIN gl_journal_lines jl ON jl.account_id = pm.gl_account_id
                               INNER JOIN gl_journal_entries je ON je.id = jl.entry_id AND je.status = 'posted' AND je.deleted_at IS NULL
                              INNER JOIN gl_accounts ga ON ga.id = pm.gl_account_id AND ga.is_bank = 1
                              WHERE pm.id = :id AND (pm.currency IS NULL OR je.currency = pm.currency)
                              GROUP BY je.currency
                              ORDER BY je.currency",
                     'columns' => ['currency' => 'Cur.', 'opening' => 'Opening balance', 'money_in' => 'Money in', 'money_out' => 'Money out', 'balance' => 'Balance now'],
                     'empty' => 'Nothing has moved through this account yet.'],
                    ['title' => 'Money through this account', 'icon' => 'repeat', 'permission' => 'payment_methods',
                     'sql' => "SELECT x.happened_on, x.direction, x.reference, x.counterparty, x.amount, x.currency
                                 FROM (SELECT method_key FROM payment_methods WHERE id = :id) k
                                 INNER JOIN (
                                     SELECT p.method, DATE(p.paid_at) AS happened_on, 'In' AS direction, p.payment_code AS reference, c.customer_name AS counterparty, p.amount, p.currency
                                       FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id LEFT JOIN customers c ON c.id = i.customer_id WHERE p.deleted_at IS NULL
                                     UNION ALL
                                     SELECT sp.method, DATE(sp.paid_at), 'Out', sp.payment_code, s.supplier_name, sp.amount, sp.currency
                                       FROM supplier_payments sp INNER JOIN supplier_bills b ON b.id = sp.bill_id LEFT JOIN suppliers s ON s.id = b.supplier_id WHERE sp.deleted_at IS NULL
                                     UNION ALL
                                     SELECT d.method, d.paid_at, 'Out', d.disbursement_code, COALESCE(s.supplier_name, CONCAT(d.category, ' for ', c.customer_name)), d.amount, d.currency
                                       FROM disbursements d LEFT JOIN suppliers s ON s.id = d.supplier_id LEFT JOIN customers c ON c.id = d.customer_id WHERE d.deleted_at IS NULL
                                     UNION ALL
                                     SELECT e.payment_method, e.expense_date, 'Out', e.reference_code, e.category, e.amount, e.currency
                                       FROM expenses e WHERE e.deleted_at IS NULL AND e.status = 'approved'
                                 ) x ON x.method = k.method_key
                                ORDER BY x.happened_on DESC, x.reference DESC
                                LIMIT 25",
                     'columns' => ['happened_on' => 'Date', 'direction' => 'In / out', 'reference' => 'Reference', 'counterparty' => 'From / to', 'amount' => 'Amount', 'currency' => 'Cur.'],
                     'empty' => 'No money has gone through this method yet.'],
                ],
            ],

            'accounts' => [
                'title' => 'Chart of accounts',
                'singular' => 'Account',
                'kicker' => 'Accounting',
                'icon' => 'list',
                'description' => 'Every account the books are kept in, and which statement each one belongs on.',
                'button' => 'Add account',
                'list_links' => [
                    ['label' => 'Import from Excel', 'icon' => 'upload', 'route' => ['accounts', 'import'], 'permission' => 'accounts', 'ability' => 'create'],
                ],
                'table' => 'gl_accounts',
                'alias' => 'ga',
                'code' => 'account_code',
                'order' => 'ga.section_order, ga.account_code',
                'joins' => 'LEFT JOIN gl_accounts gp ON gp.id = ga.parent_id',
                'select' => ['ga.id', 'ga.account_code', 'ga.account_name', 'ga.account_type', 'ga.normal_balance', 'ga.report_section', 'ga.cash_flow_class', 'ga.is_bank', 'ga.is_header', 'ga.is_active', 'gp.account_name AS parent'],
                'search' => ['ga.account_code', 'ga.account_name', 'ga.report_section', 'ga.description'],
                'list' => [
                    'account_code' => ['label' => 'Code', 'type' => 'code'],
                    'account_name' => ['label' => 'Account'],
                    'account_type' => ['label' => 'Type', 'type' => 'label'],
                    'report_section' => ['label' => 'Statement section'],
                    'normal_balance' => ['label' => 'Normal side', 'type' => 'label'],
                    'parent' => ['label' => 'Under', 'empty' => 'Top level'],
                    'cash_flow_class' => ['label' => 'Cash flow', 'type' => 'label'],
                ],
                'filters' => [
                    'account_type' => ['label' => 'Type', 'column' => 'ga.account_type', 'options' => self::ACCOUNT_TYPES],
                    'report_section' => ['label' => 'Section', 'column' => 'ga.report_section', 'options' => self::statementSections()],
                ],
                'sections' => [
                    ['title' => 'Account', 'icon' => 'hash', 'hint' => 'The code decides where the account sorts; the type decides which statement it lands on.', 'fields' => ['account_code', 'account_name', 'account_type', 'normal_balance']],
                    ['title' => 'Placement', 'icon' => 'layers', 'hint' => 'The section and its order are what group the Trial Balance and the Balance Sheet.', 'fields' => ['report_section', 'section_order', 'parent_id', 'depth']],
                    ['title' => 'Behaviour', 'icon' => 'settings', 'hint' => 'A bank account is what the cash flow statement measures movement in.', 'fields' => ['is_header', 'is_contra', 'is_bank', 'cash_flow_class', 'is_active']],
                    ['title' => 'Notes', 'icon' => 'file-text', 'fields' => ['description']],
                ],
                'fields' => [
                    'account_code' => ['label' => 'Account code', 'type' => 'text', 'required' => true, 'width' => 3, 'placeholder' => '6600', 'help' => 'Must be unique. Assets 1000s, liabilities 2000s, equity 3000s, income 4000s, costs 5000s, expenses 6000s.'],
                    'account_name' => ['label' => 'Account name', 'type' => 'text', 'required' => true, 'width' => 5],
                    'account_type' => ['label' => 'Type', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::ACCOUNT_TYPES],
                    'normal_balance' => ['label' => 'Normal balance', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ['debit' => 'Debit', 'credit' => 'Credit'], 'help' => 'Assets and expenses are debit; income, liabilities and equity are credit.'],
                    'report_section' => ['label' => 'Statement section', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => self::statementSections()],
                    'section_order' => ['label' => 'Section order', 'type' => 'number', 'width' => 4, 'help' => 'Lower numbers print first.'],
                    'parent_id' => ['label' => 'Sits under', 'type' => 'relation', 'width' => 4, 'relation' => ['table' => 'gl_accounts', 'label' => 'account_name', 'where' => 'deleted_at IS NULL AND is_header = 1']],
                    'depth' => ['label' => 'Indent level', 'type' => 'number', 'width' => 4, 'min' => 0, 'max' => 3],
                    'is_header' => ['label' => 'Heading only, nothing posts to it', 'type' => 'checkbox', 'width' => 3],
                    'is_contra' => ['label' => 'Contra account', 'type' => 'checkbox', 'width' => 3, 'help' => 'Such as accumulated depreciation, which reduces the asset above it.'],
                    'is_bank' => ['label' => 'Bank or cash account', 'type' => 'checkbox', 'width' => 3],
                    'cash_flow_class' => ['label' => 'Cash flow activity', 'type' => 'select', 'width' => 3, 'options' => ['operating' => 'Operating', 'investing' => 'Investing', 'financing' => 'Financing', 'cash' => 'Cash itself', 'none' => 'Not classified']],
                    'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'width' => 3],
                    'description' => ['label' => 'What it is for', 'type' => 'textarea', 'width' => 12],
                ],
                'related' => [
                    ['title' => 'Recent movements', 'icon' => 'repeat', 'permission' => 'journal',
                     'sql' => "SELECT e.id, e.entry_no, e.entry_date, e.memo, l.debit, l.credit
                                 FROM gl_journal_lines l
                                 INNER JOIN gl_journal_entries e ON e.id = l.entry_id
                                WHERE l.account_id = :id AND e.status = 'posted' AND e.deleted_at IS NULL
                                ORDER BY e.entry_date DESC, e.id DESC LIMIT 12",
                     'columns' => ['entry_no' => 'Entry', 'entry_date' => 'Date', 'memo' => 'Memo', 'debit' => 'Debit', 'credit' => 'Credit'],
                     'empty' => 'Nothing has been posted to this account yet.'],
                ],
            ],

            'reports' => [
                'title' => 'Reports',
                'singular' => 'Report',
                'kicker' => 'Report catalogue',
                'icon' => 'bar-chart-2',
                'description' => 'The catalogue of saved reports. Open one to run it against live data.',
                'button' => 'Add report',
                'table' => 'reports',
                'alias' => 'rp',
                'code' => 'report_name',
                'order' => 'rp.id',
                'joins' => '',
                'select' => ['rp.id', 'rp.report_key', 'rp.report_name', 'rp.description', 'rp.period_label', 'rp.owner_name', 'rp.last_generated_at', 'rp.format_label'],
                'search' => ['rp.report_name', 'rp.description', 'rp.owner_name'],
                'list' => [
                    'report_name' => ['label' => 'Report', 'type' => 'code'],
                    'description' => ['label' => 'What it answers'],
                    'period_label' => ['label' => 'Period'],
                    'owner_name' => ['label' => 'Owner'],
                    'last_generated_at' => ['label' => 'Last run', 'type' => 'datetime', 'empty' => 'Never'],
                    'format_label' => ['label' => 'Format'],
                ],
                'sections' => [
                    ['title' => 'Report', 'icon' => 'bar-chart-2', 'fields' => ['report_name', 'report_key', 'description']],
                    ['title' => 'Ownership', 'icon' => 'user', 'fields' => ['period_label', 'owner_name', 'format_label', 'action_label']],
                ],
                'fields' => [
                    'report_name' => ['label' => 'Report name', 'type' => 'text', 'required' => true, 'width' => 6],
                    'report_key' => ['label' => 'Live query', 'type' => 'select', 'width' => 6, 'options' => ReportData::CATALOGUE_LABELS, 'help' => 'Pick the query this catalogue entry runs.'],
                    'description' => ['label' => 'What it answers', 'type' => 'text', 'width' => 12],
                    'period_label' => ['label' => 'Period', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => Lookup::options('report_period')],
                    'owner_name' => ['label' => 'Owner', 'type' => 'text', 'required' => true, 'width' => 3],
                    'format_label' => ['label' => 'Format', 'type' => 'select', 'required' => true, 'width' => 3, 'options' => ['CSV' => 'CSV', 'PDF' => 'PDF', 'XLSX' => 'XLSX']],
                    'action_label' => ['label' => 'Action label', 'type' => 'text', 'width' => 3],
                ],
            ],

            // Reports that arrive by email: scripts/reports.php sends what is due
            // every morning, and "Send now" sends one straight away.
            'report_emails' => [
                'title' => 'Report emails',
                'singular' => 'Report email',
                'kicker' => 'Scheduled reports',
                'icon' => 'mail',
                'description' => 'Reports sent automatically by email every day, week or month, to the people and teams you choose.',
                'button' => 'Schedule a report',
                'table' => 'report_subscriptions',
                'alias' => 'rs',
                'code' => 'report_key',
                'order' => 'rs.id DESC',
                'joins' => '',
                'select' => ['rs.id', 'rs.report_key', 'rs.frequency', 'rs.recipients', 'rs.to_finance', 'rs.to_management', 'rs.to_fleet', 'rs.to_logistics', 'rs.is_active', 'rs.last_sent_at', 'rs.last_status'],
                'search' => ['rs.report_key', 'rs.recipients'],
                'list' => [
                    'report_key' => ['label' => 'Report', 'type' => 'label'],
                    'frequency' => ['label' => 'How often', 'type' => 'label'],
                    'recipients' => ['label' => 'Also to', 'empty' => 'Teams only'],
                    'is_active' => ['label' => 'On', 'type' => 'yesno'],
                    'last_sent_at' => ['label' => 'Last sent', 'type' => 'datetime', 'empty' => 'Never'],
                    'last_status' => ['label' => 'Last result', 'empty' => '—'],
                ],
                'filters' => ['frequency' => ['label' => 'How often', 'column' => 'rs.frequency', 'options' => ReportSchedule::FREQUENCIES]],
                'sections' => [
                    ['title' => 'Which report, how often', 'icon' => 'bar-chart-2', 'hint' => 'Daily covers yesterday, weekly the last seven days, monthly the previous calendar month. Sent every morning by the scheduled job.', 'fields' => ['report_key', 'frequency', 'is_active']],
                    ['title' => 'Who receives it', 'icon' => 'users', 'hint' => 'Tick whole teams (everyone active in that role who accepts email), and/or type extra addresses such as the managing director or an auditor.', 'fields' => ['to_finance', 'to_management', 'to_fleet', 'to_logistics', 'to_warehouse', 'to_admin', 'recipients']],
                    ['title' => 'Last run', 'icon' => 'clock', 'fields' => ['last_sent_at', 'last_status', 'notes']],
                ],
                'fields' => [
                    'report_key' => ['label' => 'Report', 'type' => 'select', 'required' => true, 'width' => 5, 'options' => ReportData::CATALOGUE_LABELS],
                    'frequency' => ['label' => 'How often', 'type' => 'select', 'required' => true, 'width' => 4, 'options' => ReportSchedule::FREQUENCIES],
                    'is_active' => ['label' => 'Switched on', 'type' => 'checkbox', 'width' => 3],
                    'to_finance' => ['label' => 'Finance team', 'type' => 'checkbox', 'width' => 4],
                    'to_management' => ['label' => 'Management', 'type' => 'checkbox', 'width' => 4],
                    'to_fleet' => ['label' => 'Fleet managers', 'type' => 'checkbox', 'width' => 4],
                    'to_logistics' => ['label' => 'Logistics managers', 'type' => 'checkbox', 'width' => 4],
                    'to_warehouse' => ['label' => 'Warehouse managers', 'type' => 'checkbox', 'width' => 4],
                    'to_admin' => ['label' => 'System administrators', 'type' => 'checkbox', 'width' => 4],
                    'recipients' => ['label' => 'Extra email addresses', 'type' => 'textarea', 'width' => 12, 'placeholder' => 'md@company.com, auditor@firm.rw', 'help' => 'Separate several addresses with commas.'],
                    'last_sent_at' => ['label' => 'Last sent', 'type' => 'datetime', 'width' => 4, 'readonly' => true, 'readonly_note' => 'by the system'],
                    'last_status' => ['label' => 'Last result', 'type' => 'text', 'width' => 8, 'readonly' => true, 'readonly_note' => 'by the system'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'width' => 12],
                ],
                'links' => [
                    ['label' => 'Send now', 'icon' => 'send', 'tone' => 'primary', 'method' => 'post',
                     'permission' => 'report_emails', 'ability' => 'edit',
                     'route' => ['report_emails', ':id', 'send'],
                     'hint' => 'Sends this report for its latest period straight away, without waiting for the morning run.'],
                ],
            ],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Where an account lands on the balance sheet or the income statement.
     *
     * This one stays in code because the books group by it: `Books` knows that
     * 'Other income' belongs below the operating result, and a section it has
     * never heard of would have nowhere to go.
     */
    /**
     * The periods a budget line can cover.
     *
     * Zero is the whole year, which is how most companies budget; the months are
     * there for the ones that split it, and Budget versus actual adds them up
     * either way.
     */
    /**
     * SQL for "the account behind a payment method", as a person reads it:
     * Bank transfer — Bank of Kigali · Nyarugenge · No. 00040-123 · ITEC Cargo Ltd · RWF.
     */
    private static function methodAccountSql(string $alias): string
    {
        return "CONCAT_WS(' — ', {$alias}.method_name, NULLIF(CONCAT_WS(' · ', NULLIF({$alias}.provider_name, ''), NULLIF({$alias}.branch_name, ''),"
            . " IF({$alias}.account_number IS NULL OR {$alias}.account_number = '', NULL, CONCAT('No. ', {$alias}.account_number)),"
            . " NULLIF({$alias}.account_name, ''), NULLIF({$alias}.currency, '')), ''))";
    }

    /** One "where the money went" block for a record that names a payment method. */
    private static function moneyMovement(string $permission, string $sql, string $empty = 'The payment method of this record is not set up yet.'): array
    {
        return ['title' => 'Where the money went', 'icon' => 'shuffle', 'permission' => $permission,
            'sql' => $sql,
            'columns' => ['from_side' => 'From', 'to_side' => 'To', 'amount_text' => 'Amount', 'ledger' => 'In the books', 'reference' => 'Reference'],
            'empty' => $empty];
    }

    private static function budgetMonths(): array
    {
        $months = [0 => 'Whole year'];
        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = date('F', (int) mktime(0, 0, 0, $month, 1));
        }

        return $months;
    }

    /** The reference lists a company may edit, named in `Models\Lookup`. */
    private static function lookupLists(): array
    {
        return Lookup::lists();
    }

    /** The ordered customs declaration lifecycle shared by list filters and forms. */
    private static function clearanceStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'documents_pending' => 'Documents pending',
            'declaration_submitted' => 'Declaration submitted',
            'under_review' => 'Under review',
            'inspection' => 'Inspection',
            'duties_pending' => 'Duties pending',
            'payment_pending' => 'Payment pending',
            'cleared' => 'Cleared',
            'released' => 'Released',
            'rejected' => 'Rejected',
        ];
    }

    private static function packDocumentTypes(): array
    {
        return array_combine($types = ['Commercial invoice', 'Packing list', 'Transport document', 'Certificate of origin', 'Customs declaration', 'Import/Export permit', 'Insurance', 'Other'], $types);
    }

    private static function statementSections(): array
    {
        $sections = ['Cash and bank', 'Accounts receivable', 'Other current assets', 'Fixed assets', 'Accounts payable', 'Other current liabilities', 'Long term liabilities', 'Equity', 'Income', 'Cost of sales', 'Operating expenses', 'Other income', 'Other expenses'];

        return array_combine($sections, $sections);
    }

    private static function failureReasons(): array
    {
        return [
            'none' => 'No failure',
            'recipient_absent' => 'Recipient absent',
            'address_wrong' => 'Address wrong',
            'goods_damaged' => 'Goods damaged',
            'goods_refused' => 'Goods refused',
            'vehicle_breakdown' => 'Vehicle breakdown',
            'access_denied' => 'Access denied',
            'weather' => 'Weather',
            'other' => 'Other',
        ];
    }

    private static function movementTypes(): array
    {
        return [
            'stock_in' => 'Stock in',
            'stock_out' => 'Stock out',
            'transfer_in' => 'Transfer in',
            'transfer_out' => 'Transfer out',
            'adjustment' => 'Adjustment',
            'damage' => 'Damage',
            'return' => 'Return',
        ];
    }

    /** Movement types that increase the balance; everything else decreases it. */
    public static function movementAdds(string $type): bool
    {
        return in_array($type, ['stock_in', 'transfer_in', 'return', 'adjustment'], true);
    }
}
