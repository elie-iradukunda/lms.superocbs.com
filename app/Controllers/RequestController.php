<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\Company;
use Models\RequestFormConfig;
use Models\RequestForms;
use Models\RequestPdf;

/**
 * The request forms F1-F15 and their chain (sections 8 and 9): the list, one
 * form to fill any of them, the request page with its history and the buttons
 * of whoever signs next, and the printed PDF.
 */
final class RequestController
{
    public function index(): void
    {
        $db = Database::connection();
        $where = ['r.deleted_at IS NULL'];
        $parameters = [];
        if (($company = Company::condition('form_requests', 'r.company_id')) !== null) {
            $where[] = '(' . $company . ' OR r.to_company_id IN (' . implode(',', array_map('intval', \company_scope() ?? [0])) . '))';
        }
        $form = (string) ($_GET['form'] ?? '');
        if ($form !== '') {
            $where[] = 'r.form_code = ?';
            $parameters[] = $form;
        }
        $status = (string) ($_GET['status'] ?? '');
        if ($status !== '' && isset(RequestForms::STATUS[$status])) {
            $where[] = 'r.status = ?';
            $parameters[] = $status;
        }
        $currency = (string) ($_GET['currency'] ?? '');
        if ($currency !== '') {
            $where[] = 'r.currency = ?';
            $parameters[] = $currency;
        }
        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(r.request_no LIKE ? OR r.title LIKE ?)';
            $parameters[] = '%' . $search . '%';
            $parameters[] = '%' . $search . '%';
        }
        // A driver and a sister-company supervisor see the requests they raised.
        if (in_array(\current_role(), ['driver', 'supplier', 'customer'], true)) {
            $where[] = '(r.created_by = ? OR r.prepared_by = ?)';
            $parameters[] = \current_user_id();
            $parameters[] = \current_user_id();
        }

        $statement = $db->prepare(
            'SELECT r.id, r.form_code, r.request_no, r.title, r.status, r.currency, r.total_amount, r.total_quantity, r.created_at, r.prepared_at,
                    r.waiting_since, ft.chain, ft.goes_to_payment, ft.is_money, co.short_name AS company, pu.full_name AS prepared_name
               FROM form_requests r
               INNER JOIN form_types ft ON ft.form_code = r.form_code
               LEFT JOIN companies co ON co.id = r.company_id
               LEFT JOIN users pu ON pu.id = r.prepared_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY r.id DESC LIMIT 300'
        );
        $statement->execute($parameters);

        \view('requests/index', [
            'title' => 'Requests',
            'requests' => $statement->fetchAll(\PDO::FETCH_ASSOC),
            'inbox' => RequestForms::inbox(),
            'forms' => RequestForms::formOptions(),
            'startable' => RequestFormConfig::startable(),
        ]);
    }

    public function create(): void
    {
        $code = strtoupper((string) ($_GET['form'] ?? ''));
        $type = RequestForms::formType($code);
        if ($type === null || !RequestForms::mayRaise($type)) {
            Flash::error('Choose a form you may raise.');
            $this->redirect(\url('form_requests'));
        }
        $config = RequestFormConfig::for($code);
        if ($config['created_by_flow'] !== null && $code !== 'F1') {
            Flash::info($type['form_name'] . ' is created from ' . $config['created_by_flow'] . '.');
            $this->redirect(\url('form_requests'));
        }
        if ($code === 'F1') {
            $this->redirect(\url('trip_planner'));
        }

        $defaults = ['form_code' => $code, 'currency' => Company::baseCurrency(), 'title' => '', 'payload' => []];
        foreach (['trip_id', 'vehicle_id', 'driver_id', 'supplier_id', 'customer_id', 'route_id', 'parent_request_id'] as $key) {
            if (!empty($_GET[$key])) {
                $defaults[$key] = (int) $_GET[$key];
            }
        }
        $this->form(null, $defaults, [], []);
    }

    public function store(): void
    {
        $code = strtoupper((string) ($_POST['form_code'] ?? ''));
        $type = RequestForms::formType($code);
        if ($type === null || !RequestForms::mayRaise($type)) {
            Flash::error('You may not raise that form.');
            $this->redirect(\url('form_requests'));
        }
        [$header, $lines] = $this->input($code);

        try {
            $id = RequestForms::create($header, $lines);
            $this->attachUploads($id);
            if (isset($_POST['send'])) {
                RequestForms::send($id);
                Flash::success(sprintf('%s sent for %s.', RequestForms::label(RequestForms::find($id)), strtolower(RequestForms::STEP_LABELS[RequestForms::pendingStep(RequestForms::find($id)) ?? 'verify'] ?? 'approval')));
            } else {
                Flash::success('Saved as a draft. Send it when it is complete.');
            }
            $this->redirect(\url(['form_requests', $id]));
        } catch (\Throwable $exception) {
            if (isset($id)) {
                Flash::error($exception->getMessage());
                $this->redirect(\url(['form_requests', $id, 'edit']));
            }
            $this->form(null, $header + ['form_code' => $code], $lines, [$exception->getMessage()]);
        }
    }

    public function show(array $params): void
    {
        $request = $this->load($params);
        \view('requests/show', [
            'title' => RequestForms::label($request),
            'request' => $request,
            'lines' => RequestForms::lines((int) $request['id']),
            'events' => RequestForms::events((int) $request['id']),
            'step' => RequestForms::pendingStep($request),
            'refusal' => ($step = RequestForms::pendingStep($request)) !== null && $step !== 'pay' ? RequestForms::refusal($request, $step) : null,
            'problems' => in_array($request['status'], ['draft', 'returned'], true) ? RequestForms::problems($request, RequestForms::lines((int) $request['id'])) : [],
            'payments' => $this->payments((int) $request['id']),
            'entries' => $this->entries((int) $request['id']),
        ]);
    }

    public function edit(array $params): void
    {
        $request = $this->load($params);
        if (!in_array($request['status'], ['draft', 'returned'], true)) {
            Flash::error('Once sent a request is locked. It can be changed only if it comes back.');
            $this->redirect(\url(['form_requests', $request['id']]));
        }
        $this->form((int) $request['id'], $request, RequestForms::lines((int) $request['id']), []);
    }

    public function update(array $params): void
    {
        $request = $this->load($params);
        [$header, $lines] = $this->input((string) $request['form_code']);
        try {
            RequestForms::update((int) $request['id'], $header, $lines);
            $this->attachUploads((int) $request['id']);
            if (isset($_POST['send'])) {
                RequestForms::send((int) $request['id']);
                Flash::success(RequestForms::label(RequestForms::find((int) $request['id'])) . ' sent.');
            } else {
                Flash::success('Changes saved.');
            }
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['form_requests', $request['id'], 'edit']));
        }
        $this->redirect(\url(['form_requests', $request['id']]));
    }

    /** One of the chain buttons. */
    public function act(array $params): void
    {
        $request = $this->load($params);
        $action = (string) ($params['action'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        try {
            $message = match ($action) {
                'send' => RequestForms::label(RequestForms::send((int) $request['id'])) . ' sent.',
                'verify' => RequestForms::label(RequestForms::verify((int) $request['id'], $comment)) . ' verified. The books are posted and it is with the MD.',
                'authorize' => RequestForms::label(RequestForms::authorize((int) $request['id'], $comment)) . ' authorized and stamped.',
                'confirm' => RequestForms::label(RequestForms::confirm((int) $request['id'], $comment)) . ' confirmed.',
                'send-back' => RequestForms::label(RequestForms::sendBack((int) $request['id'], $reason)) . ' went back to the preparer with your reason.',
                'cancel' => (static function () use ($request, $reason): string {
                    RequestForms::cancel((int) $request['id'], $reason);

                    return RequestForms::label($request) . ' cancelled.';
                })(),
                default => throw new \RuntimeException('Unknown action.'),
            };
            Flash::success($message);
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }

        $back = (string) ($_POST['back'] ?? '');
        $this->redirect($back === 'approvals' ? \url('approvals') : \url(['form_requests', $request['id']]));
    }

    public function pdf(array $params): void
    {
        $request = $this->load($params);
        $bytes = RequestPdf::render((int) $request['id']);
        if ($bytes === null) {
            Flash::error('The PDF could not be made.');
            $this->redirect(\url(['form_requests', $request['id']]));
        }
        \Models\AuditLog::record('request.printed', 'form_requests', RequestForms::label($request));
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9\-]+/', '-', RequestForms::label($request)) . '.pdf"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    // --------------------------------------------------------------- helpers

    private function form(?int $id, array $request, array $lines, array $errors): void
    {
        $code = (string) $request['form_code'];
        \view('requests/form', [
            'title' => ($id === null ? 'New ' : 'Edit ') . $code,
            'id' => $id,
            'request' => $request,
            'type' => RequestForms::formType($code),
            'config' => RequestFormConfig::for($code),
            'lines' => $lines,
            'lists' => RequestFormConfig::lists(),
            'errors' => $errors,
        ]);
    }

    /**
     * The posted form as a header and lines, in the shape RequestForms wants.
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function input(string $code): array
    {
        $config = RequestFormConfig::for($code);
        $post = $_POST;
        $header = [
            'form_code' => $code,
            'title' => trim((string) ($post['title'] ?? '')),
            'currency' => (string) ($post['currency'] ?? ''),
            'notes' => (string) ($post['notes'] ?? ''),
            'non_standard_reason' => (string) ($post['non_standard_reason'] ?? ''),
        ];
        foreach (['route_id', 'trip_id', 'vehicle_id', 'driver_id', 'supplier_id', 'customer_id', 'to_company_id', 'parent_request_id', 'period_from', 'period_to', 'needed_on'] as $key) {
            if (array_key_exists($key, $post)) {
                $header[$key] = $post[$key];
            }
        }
        $payload = [];
        foreach (['odometer', 'problem', 'support_type', 'location', 'invoice_no', 'invoice_date', 'shipment_ref'] as $key) {
            if (trim((string) ($post[$key] ?? '')) !== '') {
                $payload[$key] = trim((string) $post[$key]);
            }
        }
        $header['payload'] = $payload;

        // Who is paid when the form has one payee.
        $payee = [];
        if ($config['payee'] === 'header') {
            $payee = [
                'payee_type' => (string) ($post['payee_type'] ?? 'other'),
                'payee_id' => $post['payee_id'] ?? null,
                'payee_name' => trim((string) ($post['payee_name'] ?? '')),
                'payment_method' => (string) ($post['payment_method'] ?? 'momo'),
                'momo_number' => trim((string) ($post['momo_number'] ?? '')),
                'momo_name' => trim((string) ($post['momo_name'] ?? '')),
            ];
            if ($config['payee_from'] === 'driver' && !empty($post['driver_id'])) {
                $driver = \Models\Fleet::driver((int) $post['driver_id']);
                if ($driver !== null) {
                    $payee = ['payee_type' => 'driver', 'payee_id' => (int) $driver['id'], 'payee_name' => (string) $driver['full_name'],
                        'payment_method' => $driver['payment_method'] === 'cash' ? 'cash' : 'momo', 'momo_number' => $driver['momo_number'], 'momo_name' => $driver['momo_name']];
                }
            }
            if ($payee['payee_type'] === 'supplier' && !empty($payee['payee_id']) && $payee['payee_name'] === '') {
                $payee['payee_name'] = (string) (\Models\RequestFormConfig::lists()['suppliers'][(int) $payee['payee_id']]['label'] ?? '');
            }
        } elseif ($config['payee'] === 'supplier' && !empty($post['supplier_id'])) {
            $supplier = RequestFormConfig::lists()['suppliers'][(int) $post['supplier_id']] ?? null;
            $payee = ['payee_type' => 'supplier', 'payee_id' => (int) $post['supplier_id'], 'payee_name' => (string) ($supplier['label'] ?? ''),
                'payment_method' => (string) ($post['payment_method'] ?? 'bank'), 'momo_number' => trim((string) ($post['momo_number'] ?? '')), 'momo_name' => ''];
        }

        $lines = [];
        foreach ((array) ($post['lines'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $extra = [];
            foreach (['date', 'position', 'container_no', 'operation', 'size', 'rate_id'] as $key) {
                if (trim((string) ($row[$key] ?? '')) !== '') {
                    $extra[$key] = trim((string) $row[$key]);
                }
            }
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '' && $code === 'F7' && ($extra['operation'] ?? '') !== '') {
                $description = sprintf('Crane %s %s %s', $extra['operation'], $extra['size'] ?? '', $extra['container_no'] ?? '');
            }
            if ($description === '' && $code === 'F6') {
                $description = 'Tyre change' . (isset($extra['position']) ? ' (' . (RequestFormConfig::POSITIONS[$extra['position']] ?? $extra['position']) . ')' : '');
            }
            if ($description === '' && !empty($row['item_id'])) {
                $description = (string) (RequestFormConfig::lists()['items'][(int) $row['item_id']]['label'] ?? '');
            }
            $line = [
                'item_code' => (string) ($row['item_code'] ?? $config['item_default']),
                'item_id' => $row['item_id'] ?? null,
                'trip_id' => $row['trip_id'] ?? ($header['trip_id'] ?? null),
                'vehicle_id' => $row['vehicle_id'] ?? ($header['vehicle_id'] ?? null),
                'trailer_id' => $row['trailer_id'] ?? null,
                'driver_id' => $row['driver_id'] ?? ($header['driver_id'] ?? null),
                'description' => $description,
                'quantity' => ($row['quantity'] ?? '') === '' ? 1 : $row['quantity'],
                'unit' => ($row['unit'] ?? '') ?: $config['unit'],
                'unit_price' => $row['unit_price'] ?? 0,
                'standard_quantity' => $row['standard_quantity'] ?? null,
                'standard_amount' => $row['standard_amount'] ?? null,
                'extra' => $extra,
            ];
            if (($row['amount'] ?? '') !== '') {
                $line['amount'] = $row['amount'];
            }
            if ($config['payee'] === 'line') {
                $line += [
                    'payee_type' => (string) ($row['payee_type'] ?? 'driver'),
                    'payee_id' => $row['payee_id'] ?? ($row['driver_id'] ?? null),
                    'payee_name' => trim((string) ($row['payee_name'] ?? '')),
                    'payment_method' => (string) ($row['payment_method'] ?? 'momo'),
                    'momo_number' => trim((string) ($row['momo_number'] ?? '')),
                    'momo_name' => trim((string) ($row['momo_name'] ?? '')),
                ];
            } else {
                $line += $payee;
            }
            $lines[] = $line;
        }

        return [$header, $lines];
    }

    /** Photos and scans sent with a form (a support request's photos, an invoice scan). */
    private function attachUploads(int $id): void
    {
        $files = [];
        foreach ((array) ($_FILES['attachments']['name'] ?? []) as $index => $name) {
            if ((int) ($_FILES['attachments']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $stored = \Models\Uploads::store([
                'name' => $name,
                'tmp_name' => $_FILES['attachments']['tmp_name'][$index],
                'size' => $_FILES['attachments']['size'][$index],
                'error' => UPLOAD_ERR_OK,
            ], 'form_requests');
            if ($stored !== null) {
                $files[] = $stored;
            }
        }
        if ($files === []) {
            return;
        }
        $request = RequestForms::find($id);
        $payload = $request['payload'] ?? [];
        $payload['attachments'] = array_values(array_merge($payload['attachments'] ?? [], $files));
        Database::connection()->prepare('UPDATE form_requests SET payload = ? WHERE id = ?')->execute([json_encode($payload), $id]);
    }

    private function payments(int $requestId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT bl.*, b.batch_no, b.mtn_batch_no, b.method FROM payment_batch_lines bl INNER JOIN payment_batches b ON b.id = bl.batch_id WHERE bl.request_id = ? ORDER BY bl.id'
        );
        $statement->execute([$requestId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function entries(int $requestId): array
    {
        if (!\can_view('journal') && !\can_view('books')) {
            return [];
        }
        $statement = Database::connection()->prepare(
            "SELECT DISTINCT e.id, e.entry_no, e.entry_date, e.memo, e.status, e.currency,
                    (SELECT SUM(debit) FROM gl_journal_lines x WHERE x.entry_id = e.id) AS amount
               FROM gl_journal_entries e INNER JOIN gl_journal_lines l ON l.entry_id = e.id
              WHERE l.request_id = ? AND e.deleted_at IS NULL ORDER BY e.id"
        );
        $statement->execute([$requestId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function load(array $params): array
    {
        $request = RequestForms::find((int) ($params['id'] ?? 0));
        if ($request === null) {
            Flash::error('That request does not exist, or belongs to another company.');
            $this->redirect(\url('form_requests'));
        }

        return $request;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
