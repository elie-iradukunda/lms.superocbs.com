<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\Company;
use Models\Garage;
use Models\RequestForms;
use Models\ServiceLogs;
use Models\Store;

/**
 * Garage and store screens (section 12): the job-card board and job card,
 * the store's issue notes, goods received against purchase orders, stock
 * counts, and building the tyre (F6) and crane (F7) payment requests from
 * their logs.
 */
final class GarageController
{
    // -------------------------------------------------------------- garage

    public function board(): void
    {
        \view('garage/board', [
            'title' => 'Garage',
            'open' => Garage::board(),
            'closed' => Garage::board(true),
        ]);
    }

    public function show(array $params): void
    {
        $job = Garage::job((int) ($params['id'] ?? 0));
        if ($job === null) {
            Flash::error('That job card does not exist.');
            $this->redirect(\url('garage'));
        }

        \view('garage/show', [
            'title' => (string) $job['work_order_code'],
            'job' => $job,
            'parts' => Garage::parts((int) $job['id']),
            'notes' => Garage::notes((int) $job['id']),
        ]);
    }

    public function act(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            match ((string) ($params['action'] ?? '')) {
                'start' => Garage::start($id),
                'close' => Garage::close($id, [
                    'work_done' => (string) ($_POST['work_done'] ?? ''),
                    'diagnosis' => (string) ($_POST['diagnosis'] ?? ''),
                    'odometer' => (string) ($_POST['odometer'] ?? ''),
                    'labour_hours' => (string) ($_POST['labour_hours'] ?? ''),
                    'service_done' => !empty($_POST['service_done']),
                ]),
                default => throw new \RuntimeException('Unknown action.'),
            };
            Flash::success(($params['action'] ?? '') === 'close' ? 'Job card closed. The truck is available again.' : 'Work started.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['garage', $id]));
    }

    // ------------------------------------------------------- store issues

    public function issues(): void
    {
        \view('garage/issues', [
            'title' => 'Issue notes',
            'notes' => Garage::issueQueue(),
            'toReceive' => \can_view('goods_receipts') ? Store::toReceive() : [],
        ]);
    }

    public function issue(array $params): void
    {
        $note = RequestForms::find((int) ($params['id'] ?? 0));
        if ($note === null || $note['form_code'] !== 'F4') {
            Flash::error('That issue note does not exist.');
            $this->redirect(\url('store_issues'));
        }

        \view('garage/issue', [
            'title' => RequestForms::label($note),
            'note' => $note,
            'lines' => Garage::noteLines((int) $note['id']),
            'events' => RequestForms::events((int) $note['id']),
        ]);
    }

    public function issueAct(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        try {
            switch ($action) {
                case 'issue':
                    $count = Garage::issue($id, is_array($_POST['qty'] ?? null) ? $_POST['qty'] : []);
                    Flash::success(sprintf('%d line(s) issued. Stock is updated and the cost is on the truck.', $count));
                    break;
                case 'order':
                    $orderId = Garage::orderShortfall($id);
                    if ($orderId === null) {
                        Flash::info('The store holds enough for every line.');
                        break;
                    }
                    Flash::success('A draft purchase order with the missing quantities is ready. Choose the supplier and send it.');
                    $this->redirect(\url(['form_requests', $orderId, 'edit']));
                    // no break: redirect exits
                case 'withdraw':
                    Garage::withdrawRemaining($id, (string) ($_POST['reason'] ?? ''));
                    Flash::success('The rest of the note was withdrawn.');
                    break;
                default:
                    throw new \RuntimeException('Unknown action.');
            }
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['store_issues', $id]));
    }

    // ----------------------------------------------------- goods received

    public function receiveForm(array $params): void
    {
        $order = RequestForms::find((int) ($params['id'] ?? 0));
        if ($order === null || $order['form_code'] !== 'F5') {
            Flash::error('That purchase order does not exist.');
            $this->redirect(\url('store_issues'));
        }

        \view('garage/receive', [
            'title' => 'Receive ' . RequestForms::label($order),
            'order' => $order,
            'lines' => RequestForms::lines((int) $order['id']),
            'receipts' => Store::receipts((int) $order['id']),
        ]);
    }

    public function receive(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            Store::receive($id, is_array($_POST['qty'] ?? null) ? $_POST['qty'] : [], (string) ($_POST['delivery_ref'] ?? ''), (string) ($_POST['notes'] ?? ''));
            Flash::success('Received. Stock is updated; the supplier invoice can now be matched.');
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['goods_receipts', $id]));
    }

    // ------------------------------------------------------- stock counts

    public function counts(): void
    {
        $condition = Company::condition('warehouses', 'company_id');
        \view('garage/counts', [
            'title' => 'Stock counts',
            'counts' => Store::counts(),
            'warehouses' => Database::connection()->query("SELECT id, warehouse_name FROM warehouses WHERE deleted_at IS NULL AND status = 'active'" . ($condition !== null ? ' AND ' . $condition : '') . ' ORDER BY warehouse_name')->fetchAll(\PDO::FETCH_KEY_PAIR),
        ]);
    }

    public function startCount(): void
    {
        try {
            $id = Store::startCount((int) ($_POST['warehouse_id'] ?? 0), (string) ($_POST['notes'] ?? ''));
            $this->redirect(\url(['stock_counts', $id]));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url('stock_counts'));
        }
    }

    public function count(array $params): void
    {
        $count = Store::count((int) ($params['id'] ?? 0));
        if ($count === null) {
            Flash::error('That stock count does not exist.');
            $this->redirect(\url('stock_counts'));
        }
        \view('garage/count', ['title' => (string) $count['count_no'], 'count' => $count]);
    }

    public function countAct(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        try {
            match ($action) {
                'save', 'submit' => \role_can('stock_counts', 'edit') ? Store::saveCount($id, (array) ($_POST['counted'] ?? []), (array) ($_POST['reason'] ?? []), $action === 'submit') : throw new \RuntimeException('You cannot record counts.'),
                'approve' => \role_can('stock_counts', 'approve') ? Store::approveCount($id) : throw new \RuntimeException('You cannot approve counts.'),
                'reject' => \role_can('stock_counts', 'approve') ? Store::rejectCount($id, (string) ($_POST['reason_text'] ?? '')) : throw new \RuntimeException('You cannot approve counts.'),
                default => throw new \RuntimeException('Unknown action.'),
            };
            Flash::success([
                'save' => 'Saved. Finish counting, then submit.',
                'submit' => 'Submitted for approval.',
                'approve' => 'Approved. The stock and the books are adjusted.',
                'reject' => 'Sent back to the counter.',
            ][$action]);
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }
        $this->redirect(\url(['stock_counts', $id]));
    }

    // ------------------------------------------------ tyre and crane logs

    public function logsForm(array $params): void
    {
        $kind = (string) ($params['kind'] ?? 'tyre');
        if (!isset(ServiceLogs::KINDS[$kind])) {
            $this->redirect(\url('tyre_logs'));
        }
        $from = (string) ($_GET['from'] ?? date('Y-m-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));
        $rows = ServiceLogs::unpaid($kind, null, $from, $to);
        $bySupplier = [];
        foreach ($rows as $row) {
            $key = (int) ($row['supplier_id'] ?? 0);
            $bySupplier[$key] ??= ['name' => $row['supplier_name'] ?? 'No supplier on the line', 'rows' => [], 'totals' => []];
            $bySupplier[$key]['rows'][] = $row;
            $amount = $kind === 'tyre' ? (float) $row['quantity'] * (float) $row['unit_price'] : (float) $row['fee'];
            $bySupplier[$key]['totals'][$row['currency']] = ($bySupplier[$key]['totals'][$row['currency']] ?? 0) + $amount;
        }

        \view('garage/logs_pay', [
            'title' => ServiceLogs::KINDS[$kind]['label'] . ' to pay',
            'kind' => $kind,
            'meta' => ServiceLogs::KINDS[$kind],
            'from' => $from,
            'to' => $to,
            'bySupplier' => $bySupplier,
            'tyreLife' => $kind === 'tyre' ? ServiceLogs::tyreLife() : [],
        ]);
    }

    public function logsBuild(array $params): void
    {
        $kind = (string) ($params['kind'] ?? 'tyre');
        try {
            $id = ServiceLogs::buildRequest($kind, (int) ($_POST['supplier_id'] ?? 0), (string) ($_POST['from'] ?? ''), (string) ($_POST['to'] ?? ''));
            Flash::success('The payment request is ready. Check it and send it for verification.');
            $this->redirect(\url(['form_requests', $id]));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['service_logs', $kind], ['from' => (string) ($_POST['from'] ?? ''), 'to' => (string) ($_POST['to'] ?? '')]));
        }
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
