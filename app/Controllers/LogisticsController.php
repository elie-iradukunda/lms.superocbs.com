<?php

declare(strict_types=1);

namespace Controllers;

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

        $result = Workflow::apply($key, $id, $action, $reason);
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);

        $this->redirect(\url([$key, $id]));
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

        $defaults = match ($key) {
            'vehicles' => ['status' => 'available', 'fuel_type' => 'diesel', 'ownership' => 'owned', 'mileage' => 0],
            'drivers' => ['status' => 'available'],
            'vehicle_documents' => ['document_type' => 'insurance', 'status' => 'valid', 'issued_on' => $today],
            'maintenance' => ['status' => 'open', 'priority' => 'normal', 'maintenance_type' => 'preventive', 'due_date' => $today],
            'requests' => ['status' => 'pending', 'priority' => 'normal', 'required_date' => $today, 'requester_id' => \current_user_id()],
            'trips' => ['status' => 'requested', 'trip_type' => 'delivery'],
            'shipments' => ['status' => 'draft', 'cargo_type' => 'general', 'packages_count' => 1],
            'deliveries' => ['status' => 'loading', 'attempt_number' => 1, 'failure_reason' => 'none'],
            'customers' => ['status' => 'active', 'customer_type' => 'corporate', 'payment_terms_days' => \Models\Settings::int('payment_terms_days', 30)],
            'rates' => ['status' => 'active', 'rate_type' => 'per_kg', 'rate_amount' => 0, 'effective_from' => $today],
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
            default => [],
        };

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
