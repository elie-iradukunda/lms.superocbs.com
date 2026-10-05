<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\AuditLog;
use Models\LogisticsData;
use Models\Permission;
use Models\Schema;
use Models\Workflow;

/**
 * One controller for every logistics module. What each module contains, which
 * fields it has and how its form is laid out all come from Models\Schema.
 */
final class LogisticsController
{
    public function index(array $params): void
    {
        $key = $this->key($params);
        $module = Schema::get($key);
        $listing = LogisticsData::listing($key, $_GET, \current_context());

        \view('modules/index', [
            'title' => $module['title'],
            'moduleKey' => $key,
            'module' => $module,
            'listing' => $listing,
            'audit' => LogisticsData::auditLog(8),
        ]);
    }

    public function create(array $params): void
    {
        $key = $this->key($params);
        $module = Schema::get($key);

        $this->renderForm($key, $module, null, $this->defaults($key), []);
    }

    public function store(array $params): void
    {
        $key = $this->key($params);
        $module = Schema::get($key);
        $input = $this->input();

        $errors = LogisticsData::validate($key, $input, $_FILES, null);
        if ($errors !== []) {
            $this->renderForm($key, $module, null, $input, $errors);
            return;
        }

        try {
            $id = LogisticsData::save($key, null, $input, $_FILES);
        } catch (\Throwable $exception) {
            $this->renderForm($key, $module, null, $input, [$this->friendly($exception)]);
            return;
        }

        Flash::success(sprintf('%s created.', $module['singular']));

        // A new account gets a one-time password. It is emailed to the person
        // directly; the administrator only sees it when the email could not go,
        // so it is not read out loud unless it has to be.
        $oneTime = LogisticsData::takeOneTimePassword();
        if ($oneTime !== null) {
            $address = trim((string) ($input['email'] ?? ''));
            $name = trim((string) ($input['full_name'] ?? $address));

            $result = \Support\Mailer::send([
                'key' => 'welcome-' . $id,
                'category' => 'new_account',
                'to' => $address,
                'to_name' => $name,
                'user_id' => $id,
                'subject' => 'Your LMS account is ready',
                'heading' => 'Welcome to ' . \company_name(),
                'lines' => [
                    sprintf('Hello %s,', $name),
                    'An account has been created for you on the LMS logistics system. Sign in with the details below.',
                    'You will be asked to choose your own password the first time you sign in, and this one stops working straight away.',
                ],
                'facts' => ['Sign in with' => $address, 'One-time password' => $oneTime],
                'action' => ['label' => 'Sign in to LMS', 'url' => \Support\Mailer::link('')],
                'entity_type' => 'users',
                'entity_id' => (string) $id,
            ]);

            if ($result['sent']) {
                Flash::info(sprintf('A one-time password was emailed to %s. They must change it at first sign-in.', $address));
            } else {
                Flash::warning(sprintf(
                    'One-time password for %s: %s — the email could not be sent, so give it to them directly. They must change it at first sign-in, and it will not be shown again.',
                    $address === '' ? 'the new account' : $address,
                    $oneTime
                ));
            }
        }

        $this->redirect(\url([$key, $id]));
    }

    public function details(array $params): void
    {
        $key = $this->key($params);
        $module = Schema::get($key);
        $id = (int) $params['id'];

        $record = LogisticsData::find($key, $id, \current_context());
        if ($record === null) {
            Flash::error('That record does not exist, or it is not visible to your role.');
            $this->redirect(\url($key));
        }

        $code = LogisticsData::code($key, $record);

        \view('modules/details', [
            'title' => $code,
            'moduleKey' => $key,
            'module' => $module,
            'record' => $record,
            'display' => LogisticsData::display($key, $record),
            'code' => $code,
            'lines' => LogisticsData::lines($key, $id),
            'related' => LogisticsData::related($key, $id, \current_role()),
            'history' => LogisticsData::history($key, $code),
            'actions' => Workflow::availableActions($key, $record),
        ]);
    }

    public function edit(array $params): void
    {
        $key = $this->key($params);
        $module = Schema::get($key);
        $id = (int) $params['id'];

        $record = LogisticsData::find($key, $id, \current_context());
        if ($record === null) {
            Flash::error('That record does not exist, or it is not visible to your role.');
            $this->redirect(\url($key));
        }

        $this->renderForm($key, $module, $id, $record, []);
    }

    public function update(array $params): void
    {
        $key = $this->key($params);
        $module = Schema::get($key);
        $id = (int) $params['id'];

        if (LogisticsData::find($key, $id, \current_context()) === null) {
            Flash::error('That record does not exist, or it is not visible to your role.');
            $this->redirect(\url($key));
        }

        $input = $this->input();
        $errors = LogisticsData::validate($key, $input, $_FILES, $id);
        if ($errors !== []) {
            $this->renderForm($key, $module, $id, $input + ['id' => $id], $errors);
            return;
        }

        try {
            LogisticsData::save($key, $id, $input, $_FILES);
        } catch (\Throwable $exception) {
            $this->renderForm($key, $module, $id, $input + ['id' => $id], [$this->friendly($exception)]);
            return;
        }

        Flash::success(sprintf('%s updated.', $module['singular']));
        $this->redirect(\url([$key, $id]));
    }

    /** Saves the child rows of a record: trip stops, invoice lines, maintenance parts. */
    public function lines(array $params): void
    {
        $key = $this->key($params);
        $id = (int) $params['id'];

        if (LogisticsData::find($key, $id, \current_context()) === null) {
            Flash::error('That record does not exist.');
            $this->redirect(\url($key));
        }

        $rows = $_POST['lines'] ?? [];
        try {
            LogisticsData::saveLines($key, $id, is_array($rows) ? $rows : []);
            Flash::success(Schema::get($key)['lines']['title'] . ' saved.');
        } catch (\Throwable $exception) {
            Flash::error($this->friendly($exception));
        }

        $this->redirect(\url([$key, $id]));
    }

    public function toggle(array $params): void
    {
        $key = $this->key($params);
        $id = (int) $params['id'];

        try {
            $status = LogisticsData::toggleStatus($key, $id, ($_POST['status'] ?? '0') === '1');
            Flash::success('Status changed to ' . Schema::label($status) . '.');
        } catch (\Throwable $exception) {
            Flash::error($this->friendly($exception));
        }

        $this->redirect($this->backTo($key));
    }

    public function delete(array $params): void
    {
        $key = $this->key($params);
        $id = (int) $params['id'];
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            Flash::error('A deletion reason is required before a record can be removed.');
            $this->redirect($this->backTo($key));
        }

        try {
            LogisticsData::remove($key, $id, $reason);
            Flash::success(sprintf('%s removed. It stays in the audit trail.', Schema::get($key)['singular']));
        } catch (\Throwable $exception) {
            Flash::error($this->friendly($exception));
        }

        $this->redirect(\url($key));
    }

    /** Approve, reject, dispatch, complete, receive: one workflow transition with its side effects. */
    public function action(array $params): void
    {
        $key = $this->key($params);
        $id = (int) $params['id'];
        $action = (string) $params['action'];
        $reason = trim((string) ($_POST['reason'] ?? ''));

        // An action that asks for a few fields first (the answer when a support
        // request is resolved) saves them in the same press. Only fields this
        // role may write are taken.
        $spec = Schema::get($key)['actions'][$action] ?? [];
        if (!empty($spec['form']) && is_array($_POST['f'] ?? null)) {
            $problem = $this->saveActionFields($key, $id, (array) $spec['form'], $_POST['f']);
            if ($problem !== null) {
                Flash::error($problem);
                $this->redirect(\url([$key, $id]));
            }
        }

        $result = Workflow::apply($key, $id, $action, $reason);
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);

        $this->redirect(\url([$key, $id]));
    }

    /** @return string|null what is wrong with what was typed, or null once it is saved */
    private function saveActionFields(string $key, int $id, array $names, array $posted): ?string
    {
        if (LogisticsData::find($key, $id, \current_context()) === null) {
            return 'That record no longer exists.';
        }
        $editable = Schema::editableFields($key, \current_role());
        $sets = [];
        $values = [];
        foreach ($names as $name) {
            if (!isset($editable[$name]) || !array_key_exists($name, $posted)) {
                continue;
            }
            $field = $editable[$name];
            $raw = trim((string) $posted[$name]);
            if ($raw === '') {
                $value = null;
            } elseif (in_array($field['type'], ['money', 'decimal', 'number'], true)) {
                $number = str_replace([',', ' '], '', $raw);
                if (!is_numeric($number) || (float) $number < 0) {
                    return sprintf('"%s" must be a number.', $field['label']);
                }
                $value = $field['type'] === 'number' ? (int) $number : round((float) $number, 2);
            } elseif ($field['type'] === 'select') {
                if (!array_key_exists($raw, $field['options'] ?? [])) {
                    return sprintf('Choose "%s" from the list.', $field['label']);
                }
                $value = $raw;
            } else {
                $value = mb_substr($raw, 0, 2000);
            }
            $sets[] = $name . ' = ?';
            $values[] = $value;
        }
        if ($sets !== []) {
            $values[] = $id;
            Database::connection()->prepare('UPDATE ' . Schema::get($key)['table'] . ' SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
            AuditLog::record('record.updated', $key, (string) $id, null, ['fields' => array_map(static fn (string $s): string => strtok($s, ' '), $sets)]);
        }

        return null;
    }

    /** CSV of exactly what the current filters show, not of the whole table. */
    public function export(array $params): void
    {
        $key = $this->key($params);
        $module = Schema::get($key);

        $query = $_GET + ['per_page' => 100, 'page' => 1];
        $filename = sprintf('%s-%s.csv', $key, date('Y-m-d'));

        AuditLog::record('record.exported', $key, null, null, ['filters' => array_intersect_key($_GET, $module['filters'])]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'wb');
        fputcsv($output, array_column($module['list'], 'label'));

        $page = 1;
        do {
            $query['page'] = $page;
            $listing = LogisticsData::listing($key, $query, \current_context());
            foreach ($listing['rows'] as $row) {
                $line = [];
                foreach ($module['list'] as $column => $spec) {
                    $line[] = $this->csvValue($row[$column] ?? '', $spec['type'] ?? 'text');
                }
                fputcsv($output, $line);
            }
            $page++;
        } while ($page <= $listing['pages'] && $page <= 200);

        fclose($output);
        exit;
    }

    // --------------------------------------------------------------- helpers

    private function renderForm(string $key, array $module, ?int $id, array $record, array $errors): void
    {
        \view('modules/form', [
            'title' => $id === null ? $module['button'] : 'Edit ' . $module['singular'],
            'moduleKey' => $key,
            // Fields that belong to another role are shown, so the driver can
            // read the address he is delivering to, but locked, so he cannot
            // change what he was asked to do.
            'module' => Schema::forRole($key, \current_role()),
            'record' => $record,
            'recordId' => $id,
            'errors' => $errors,
            'formAction' => $id === null ? \url($key) : \url([$key, $id]),
        ]);
    }

    /** Sensible starting values so a new record opens half filled in, not blank. */
    private function defaults(string $key): array
    {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        // "Plan trip" on an approved request opens this form carrying the
        // request with it, so the route and the customer are not retyped.
        if ($key === 'trips' && ($_GET['request_id'] ?? '') !== '') {
            $request = LogisticsData::find('requests', (int) $_GET['request_id'], \current_context());
            if ($request !== null && in_array((string) $request['status'], ['approved', 'assigned'], true)) {
                return [
                    'status' => 'requested',
                    'trip_type' => 'delivery',
                    'request_id' => $request['id'],
                    'customer_id' => $request['customer_id'],
                    'pickup_location' => $request['pickup_location'],
                    'destination' => $request['destination'],
                    'cargo_summary' => $request['cargo_description'],
                    'planned_arrival_at' => $request['required_date'] ? $request['required_date'] . ' 12:00:00' : null,
                ];
            }
        }

        // A request is answered either by a trip of its own or by a place on a
        // trip that is already going that way. The second is the common case —
        // one truck, several customers — and it is the shipment, not the trip,
        // that carries the customer. So "Load on a planned trip" opens this form
        // with everything the request settled, leaving one thing to choose: which
        // trip. Coming the other way, from a trip page, the trip is the given and
        // the request is what is chosen.
        if ($key === 'shipments' && ($_GET['request_id'] ?? '') !== '') {
            $request = LogisticsData::find('requests', (int) $_GET['request_id'], \current_context());
            if ($request !== null && in_array((string) $request['status'], ['approved', 'assigned'], true)) {
                return [
                    'status' => 'draft',
                    'cargo_type' => 'general',
                    'request_id' => $request['id'],
                    'customer_id' => $request['customer_id'],
                    'consignee_name' => $request['requested_by_contact'],
                    'origin' => $request['pickup_location'],
                    'origin_warehouse_id' => $request['origin_warehouse_id'],
                    'destination' => $request['destination'],
                    'destination_warehouse_id' => $request['destination_warehouse_id'],
                    'cargo_description' => $request['cargo_description'],
                    'weight_kg' => $request['weight_kg'],
                    'packages_count' => $request['packages_count'] ?: 1,
                    'currency' => $request['currency'],
                    // The customer's order number follows the cargo onto the
                    // delivery note and the invoice.
                    'customer_reference' => $request['lpo_number'] ?? null,
                    // An order against our own stock carries the item with it,
                    // so the depot loses the right thing when the truck leaves.
                    'stock_item_id' => $request['stock_item_id'],
                    'stock_quantity' => $request['stock_quantity'],
                    // The trip is deliberately left empty: choosing it is the
                    // whole point of arriving here.
                    'trip_id' => (int) ($request['trip_id'] ?? 0) ?: null,
                ];
            }
        }

        if ($key === 'shipments' && ($_GET['trip_id'] ?? '') !== '') {
            $trip = LogisticsData::find('trips', (int) $_GET['trip_id'], \current_context());
            if ($trip !== null && !in_array((string) $trip['status'], ['delivered', 'cancelled'], true)) {
                return [
                    'status' => 'draft',
                    'cargo_type' => 'general',
                    'packages_count' => 1,
                    'trip_id' => $trip['id'],
                    'origin' => $trip['pickup_location'],
                    'destination' => $trip['destination'],
                ];
            }
        }

        // "Record delivery" from a trip page opens this form already pointing at
        // that trip — and at the state the trip is actually in, so a delivery
        // added to a truck that has already left is not filed as still loading.
        if ($key === 'deliveries' && ($_GET['trip_id'] ?? '') !== '') {
            $trip = LogisticsData::find('trips', (int) $_GET['trip_id'], \current_context());
            if ($trip !== null && !in_array((string) $trip['status'], ['delivered', 'cancelled'], true)) {
                $defaults = [
                    'trip_id' => $trip['id'],
                    'destination' => $trip['destination'],
                    'planned_at' => $trip['planned_arrival_at'],
                    'status' => (string) $trip['status'] === 'in_transit' ? 'in_transit' : 'loading',
                    'attempt_number' => 1,
                    'failure_reason' => 'none',
                ];

                // A truck carrying one load leaves nothing to choose, so choose
                // it — and with it comes the consignee, named once when the load
                // was booked rather than typed again here. With several loads
                // the choice is real, so it is left to the user and the browser
                // fills the rest in behind them.
                $onBoard = LogisticsData::shipmentsOnTrip((int) $trip['id']);

                if (count($onBoard) === 1) {
                    $shipment = $onBoard[0];
                    $defaults['shipment_id'] = $shipment['id'];
                    $defaults['recipient_name'] = $shipment['consignee_name'];
                    $defaults['recipient_phone'] = $shipment['consignee_phone'];
                    $defaults['destination'] = $shipment['destination'] ?: $trip['destination'];
                    $defaults['destination_warehouse_id'] = $shipment['destination_warehouse_id'];
                }

                return $defaults;
            }
        }

        if (in_array($key, ['clearance_documents', 'clearance_inspections', 'clearance_payments', 'clearance_releases'], true) && ($_GET['crossing_id'] ?? '') !== '') {
            $clearance = LogisticsData::find('crossings', (int) $_GET['crossing_id'], current_context());
            if ($clearance !== null) {
                return match ($key) {
                    'clearance_documents' => ['crossing_id' => $clearance['id'], 'status' => 'pending'],
                    'clearance_inspections' => ['crossing_id' => $clearance['id'], 'result' => 'pending'],
                    'clearance_payments' => ['crossing_id' => $clearance['id'], 'currency' => $clearance['currency'], 'status' => 'pending', 'payment_date' => $today],
                    'clearance_releases' => ['crossing_id' => $clearance['id'], 'release_date' => $today, 'released_by' => current_user_id()],
                };
            }
        }

        $defaults = match ($key) {
            'vehicles' => ['status' => 'available', 'fuel_type' => 'diesel', 'ownership' => 'owned', 'mileage' => 0],
            'drivers' => ['status' => 'available'],
            'vehicle_documents' => ['document_type' => 'insurance', 'status' => 'valid', 'issued_on' => $today],
            'support_requests' => ['urgency' => 'normal', 'support_type' => 'money', 'currency' => \Models\Currency::base()],
            'maintenance' => ['status' => 'open', 'priority' => 'normal', 'maintenance_type' => 'preventive', 'due_date' => $today],
            'requests' => ['status' => 'pending', 'priority' => 'normal', 'required_date' => $today, 'requester_id' => \current_user_id()],
            'trips' => ['status' => 'requested', 'trip_type' => 'delivery'],
            'shipments' => ['status' => 'draft', 'cargo_type' => 'general', 'packages_count' => 1],
            'crossings' => ['status' => 'draft', 'direction' => 'import', 'currency' => 'RWF'],
            'clearance_documents' => ['status' => 'pending'],
            'clearance_inspections' => ['result' => 'pending'],
            'clearance_payments' => ['status' => 'pending', 'currency' => 'RWF', 'payment_date' => $today],
            'clearance_releases' => ['release_date' => $today, 'released_by' => current_user_id()],
            'deliveries' => ['status' => 'loading', 'attempt_number' => 1, 'failure_reason' => 'none'],
            'customers' => ['status' => 'active', 'customer_type' => 'corporate', 'payment_terms_days' => \Models\Settings::int('payment_terms_days', 30)],
            'rates' => ['status' => 'active', 'service_type' => 'full_truck', 'rate_type' => 'per_trip', 'rate_amount' => 0, 'effective_from' => $today],
            'invoices' => ['status' => 'draft', 'issue_date' => $today, 'due_date' => date('Y-m-d', strtotime('+' . \Models\Settings::int('payment_terms_days', 30) . ' days')), 'tax_rate' => \Models\Settings::get('tax_rate', '18')],
            'fuel' => ['fuel_type' => 'diesel', 'is_full_tank' => 1, 'purchased_at' => $now],
            'expenses' => ['status' => 'pending', 'expense_date' => $today, 'payment_method' => 'cash', 'submitted_by' => \current_user_id()],
            'warehouse' => ['status' => 'in_stock', 'unit_of_measure' => 'Unit', 'quantity' => 0, 'minimum_level' => 0],
            'movements' => ['movement_type' => 'stock_in', 'moved_at' => $now, 'reference_type' => 'adjustment'],
            'procurement' => ['status' => 'draft', 'requested_by' => \current_user_id()],
            'suppliers' => ['status' => 'active', 'payment_terms_days' => 30],
            'payments' => ['method' => 'bank_transfer', 'paid_at' => $today, 'recorded_by' => \current_user_id()],
            'users' => ['status' => 'active', 'prvg' => 2, 'must_change_password' => 1],
            'reports' => ['format_label' => 'CSV', 'period_label' => 'Monthly', 'owner_name' => \current_user_name(), 'action_label' => 'View'],
            // Nobody adds a choice to a list in order to withhold it.
            'lookups' => ['is_active' => 1],
            'warehouses' => ['status' => 'active'],
            'report_emails' => ['is_active' => 1, 'frequency' => 'weekly'],
            'supplier_bills' => ['status' => 'draft', 'bill_date' => $today, 'currency' => \Models\Currency::base(), 'tax_rate' => 0],
            'supplier_payments' => ['method' => 'bank_transfer', 'paid_at' => $now, 'recorded_by' => \current_user_id()],
            'storage_lots' => ['received_at' => $now, 'unit' => 'Bags', 'free_days' => 7, 'charge_basis' => 'per_unit_day', 'daily_rate' => 0, 'currency' => \Models\Currency::base(), 'status' => 'in_store'],
            'storage_releases' => ['released_at' => $now, 'authorised_by' => \current_user_id()],
            'containers' => ['picked_up_at' => $now, 'free_days' => 14, 'currency' => 'USD', 'container_size' => '20ft', 'status' => 'out_full'],
            'bank_statements' => ['status' => 'open', 'currency' => \Models\Currency::base(), 'period_from' => date('Y-m-01', strtotime('first day of last month')), 'period_to' => date('Y-m-t', strtotime('last day of last month')), 'opening_balance' => 0],
            'disbursements' => ['status' => 'pending', 'paid_at' => $today, 'method' => 'bank_transfer', 'currency' => \Models\Currency::base(), 'category' => 'Customs duty'],
            default => [],
        };

        // "Record disbursement" / "Record supplier bill" from a trip page.
        if (in_array($key, ['disbursements', 'supplier_bills'], true) && ($_GET['trip_id'] ?? '') !== '') {
            $trip = LogisticsData::find('trips', (int) $_GET['trip_id'], \current_context());
            if ($trip !== null) {
                $defaults['trip_id'] = $trip['id'];
                if ($key === 'disbursements' && !empty($trip['customer_id'])) {
                    $defaults['customer_id'] = $trip['customer_id'];
                    $currency = \Core\Database::connection()->prepare('SELECT currency FROM customers WHERE id = ?');
                    $currency->execute([(int) $trip['customer_id']]);
                    $defaults['currency'] = $currency->fetchColumn() ?: $defaults['currency'];
                }
            }
        }

        // "Keep in storage" / "Track container" from a shipment: the cargo, the
        // customer and the box are already on file.
        if (in_array($key, ['storage_lots', 'containers'], true) && ($_GET['shipment_id'] ?? '') !== '') {
            $shipment = LogisticsData::find('shipments', (int) $_GET['shipment_id'], \current_context());
            if ($shipment !== null) {
                $defaults['shipment_id'] = $shipment['id'];
                $defaults['customer_id'] = $shipment['customer_id'];
                if ($key === 'storage_lots') {
                    $defaults += [
                        'warehouse_id' => $shipment['destination_warehouse_id'] ?? null,
                        'description' => $shipment['cargo_description'],
                        'quantity_received' => $shipment['packages_count'],
                        'weight_kg' => $shipment['weight_kg'],
                        'volume_m3' => $shipment['volume_m3'] ?? null,
                    ];
                } else {
                    $defaults += [
                        'container_no' => $shipment['container_no'] ?? null,
                        'container_size' => $shipment['container_size'] ?? null,
                        'bill_of_lading' => $shipment['bill_of_lading'] ?? null,
                        'trip_id' => $shipment['trip_id'],
                    ];
                }
            }
        }

        // "Release goods" from a lot: what is left is what normally goes.
        if ($key === 'storage_releases' && ($_GET['lot_id'] ?? '') !== '') {
            $lot = \Models\Storage::lot((int) $_GET['lot_id']);
            if ($lot !== null) {
                $defaults['lot_id'] = $lot['id'];
                $defaults['quantity'] = \Models\Storage::onHand((int) $lot['id']);
            }
        }

        // "Raise transport request" on an accepted price offer: the customer and
        // the route agreed come with it.
        if ($key === 'requests' && ($_GET['price_offer_id'] ?? '') !== '') {
            $fromOffer = \Models\PriceOffer::requestDefaults((int) $_GET['price_offer_id']);
            if ($fromOffer !== null) {
                $defaults = $fromOffer + $defaults;
            }
        }

        if ($key === 'price_offers') {
            $defaults += ['status' => 'draft', 'valid_until' => date('Y-m-d', strtotime('+30 days')), 'message' => 'As you requested, please find below our rates for the transport of your goods.'];
        }

        // "Add next leg" on a journey: the next truck starts where the last ends.
        if ($key === 'trips' && ($_GET['journey_id'] ?? '') !== '') {
            $leg = \Models\Journey::nextLegDefaults((int) $_GET['journey_id']);
            if ($leg !== null) {
                $defaults = $leg + $defaults;
            }
        }

        // "Pay this bill" from a bill page: the balance is what is normally paid.
        if ($key === 'supplier_payments' && ($_GET['bill_id'] ?? '') !== '') {
            $bill = LogisticsData::find('supplier_bills', (int) $_GET['bill_id'], \current_context());
            if ($bill !== null) {
                $defaults['bill_id'] = $bill['id'];
                $defaults['amount'] = max(0, round((float) $bill['total_amount'] - (float) $bill['amount_paid'], 2));
            }
        }

        // Any other link that carries a record along ("Record garage bill" from
        // a work order) pre-selects it. Only relation fields are read, and only
        // as numbers; saving still checks the record exists.
        foreach (\Models\Schema::fields($key) as $name => $field) {
            if ($field['type'] === 'relation' && !isset($defaults[$name]) && ctype_digit((string) ($_GET[$name] ?? ''))) {
                $defaults[$name] = (int) $_GET[$name];
            }
            // A choice, such as the type of paper to add, only when it is one of
            // the options. Asked for by name, it replaces the usual default.
            if ($field['type'] === 'select' && isset($_GET[$name]) && is_string($_GET[$name]) && array_key_exists($_GET[$name], $field['options'] ?? [])) {
                $defaults[$name] = (string) $_GET[$name];
            }
        }

        // "Record expense" / "Record fuel" from a trip: the trip and its truck are
        // already known, so the driver only types what is on the receipt.
        if (in_array($key, ['expenses', 'fuel'], true) && isset($_GET['trip_id'])) {
            $trip = LogisticsData::find('trips', (int) $_GET['trip_id'], \current_context());
            if ($trip !== null) {
                $defaults['trip_id'] = $trip['id'];
                $defaults['vehicle_id'] = $trip['vehicle_id'] ?? null;
            }
        }

        return $defaults;
    }

    /** Form fields arrive under `f[...]` so they cannot collide with `_token` or `page`. */
    private function input(): array
    {
        $input = $_POST['f'] ?? [];
        if (!is_array($input)) {
            return [];
        }

        foreach ($_POST as $name => $value) {
            if (str_starts_with($name, 'existing_') && is_string($value)) {
                $input[$name] = $value;
            }
        }

        return $input;
    }

    private function key(array $params): string
    {
        $key = (string) ($params['module'] ?? '');
        if (!Schema::has($key)) {
            throw new \InvalidArgumentException('Unknown module.');
        }

        return $key;
    }

    /** Back to the list, keeping the filters the user had applied. */
    private function backTo(string $key): string
    {
        $module = Schema::get($key);
        $keep = array_intersect_key($_GET, array_flip(array_merge(['q', 'page', 'per_page', 'sort', 'dir'], array_keys($module['filters']))));

        return \url($key, $keep);
    }

    private function csvValue(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'badge', 'label' => Schema::label((string) $value),
            'money' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }

    /** Turns a database error into something an operations user can act on. */
    private function friendly(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        // Validation catches duplicates first and names the field. This is the
        // backstop for the rest: it at least repeats the value the database
        // objected to, so the person is not left guessing which box was wrong.
        if (str_contains($message, '1062')) {
            return preg_match("/Duplicate entry '(.*)' for key/", $message, $matches) === 1
                ? sprintf('The value "%s" is already used by another record. Change it to something not yet taken.', $matches[1])
                : 'One of these values is already used by another record. Change it to something not yet taken.';
        }
        if (str_contains($message, '1451') || str_contains($message, '1452')) {
            return 'This record is linked to other logistics data, so it cannot be saved that way.';
        }
        if (str_contains($message, '1406')) {
            return 'One of the values is too long for its field.';
        }

        return $message;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
