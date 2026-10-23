<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Maintenance, garage and stores (ERI specification, section 12):
 *
 *   F3 service request -> chain -> job card + F4 stores issue note
 *   -> the storekeeper issues (stock goes down, each part's value charged to
 *      the truck) -> the mechanic records work, parts and odometer and closes
 *      the job card -> the truck is Available, next service set 7,000 km on.
 *
 * Parts not in stock become a draft purchase order (F5). A sister company's
 * request (F11) opens the job card and issue note directly (section 17).
 */
final class Garage
{
    /** Request lines that are things the store hands out. */
    private const STOCK_ITEMS = ['part', 'tyre', 'other'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    // ------------------------------------------------------------ job cards

    /** F3 authorized: the job card opens and the parts go to the store. */
    public static function serviceAuthorized(array $request): void
    {
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : (json_decode((string) ($request['payload'] ?? ''), true) ?: []);
        self::openJob((int) $request['company_id'], [
            'request_id' => (int) $request['id'],
            'vehicle_id' => $request['vehicle_id'] ? (int) $request['vehicle_id'] : null,
            'title' => (string) $request['title'],
            'complaint' => (string) ($payload['problem'] ?? $request['notes'] ?? ''),
            'odometer' => isset($payload['odometer']) && $payload['odometer'] !== '' ? (int) $payload['odometer'] : null,
            'currency' => (string) $request['currency'],
            'estimated' => (float) $request['total_amount'],
            'lines' => RequestForms::lines((int) $request['id']),
        ]);
    }

    /**
     * Opens a job card, puts the truck In garage and sends the parts that come
     * from the store as an issue note (F4). Parts the store does not carry go
     * on a draft purchase order (F5) for the OM to complete.
     *
     * @param array{request_id?:int|null, vehicle_id?:int|null, trailer_id?:int|null, owner_company_id?:int|null, title:string, complaint?:string, odometer?:int|null, currency?:string, estimated?:float, lines:list<array<string, mixed>>} $job
     */
    public static function openJob(int $companyId, array $job): int
    {
        $db = self::db();
        $vehicleId = $job['vehicle_id'] ?? null;
        $vehicle = $vehicleId ? Fleet::vehicle((int) $vehicleId) : null;
        $lines = $job['lines'];
        $isService = array_filter($lines, static fn (array $l): bool => ($l['item_code'] ?? '') === 'service') !== [];
        $isTyres = $lines !== [] && array_filter($lines, static fn (array $l): bool => ($l['item_code'] ?? '') !== 'tyre') === [];

        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }
        try {
            $code = Numbering::next($companyId, 'job_card', Settings::get('job_card_number_format', 'JC-{YEAR}-{SEQ4}'), 'year');
            $db->prepare(
                "INSERT INTO maintenance_orders
                    (company_id, work_order_code, vehicle_id, request_id, trailer_id, owner_company_id, flow, service_name, maintenance_type, workshop,
                     complaint, priority, estimated_cost, odometer_reading, status, started_at, due_date)
                 VALUES (?, ?, ?, ?, ?, ?, 'eri', ?, ?, 'in_house', ?, 'normal', ?, ?, 'open', NOW(), CURDATE())"
            )->execute([
                $companyId, $code, $vehicleId, $job['request_id'] ?? null, $job['trailer_id'] ?? ($vehicle['trailer_id'] ?? null),
                $job['owner_company_id'] ?? null, mb_substr($job['title'] ?: 'Service', 0, 150),
                $isService ? 'preventive' : ($isTyres ? 'tyre' : 'corrective'),
                trim((string) ($job['complaint'] ?? '')) ?: null,
                ($job['estimated'] ?? 0) > 0 ? round((float) $job['estimated'], 2) : null,
                $job['odometer'] ?? null,
            ]);
            $jobId = (int) $db->lastInsertId();

            if (!empty($job['request_id'])) {
                $db->prepare('UPDATE form_requests SET job_card_id = ? WHERE id = ?')->execute([$jobId, (int) $job['request_id']]);
            }

            // The truck goes In garage, unless it is out on a trip (a
            // breakdown on the road is repaired where it stands).
            if ($vehicle !== null && in_array($vehicle['status'], ['available', 'assigned', 'off_road'], true)) {
                Fleet::setVehicleStatus((int) $vehicleId, 'in_garage');
            }

            // From the store: an issue note. Not carried: a draft order.
            $fromStore = [];
            $toBuy = [];
            $byName = $db->prepare("SELECT id FROM inventory_items WHERE deleted_at IS NULL AND company_id = ? AND LOWER(item_name) = LOWER(?) LIMIT 1");
            foreach ($lines as $line) {
                if (!in_array((string) ($line['item_code'] ?? ''), self::STOCK_ITEMS, true) || (float) ($line['quantity'] ?? 0) <= 0) {
                    continue;
                }
                $itemId = $line['item_id'] ?? null;
                // A sister-company line names no store item id; match it by name
                // in the receiving store so the storekeeper can issue it.
                if (empty($itemId) && trim((string) $line['description']) !== '') {
                    $byName->execute([$companyId, trim((string) $line['description'])]);
                    $itemId = $byName->fetchColumn() ?: null;
                }
                $row = [
                    'item_code' => (string) $line['item_code'], 'item_id' => $itemId,
                    'description' => (string) $line['description'], 'quantity' => (float) $line['quantity'], 'unit' => $line['unit'] ?? null,
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'vehicle_id' => $line['vehicle_id'] ?? $vehicleId, 'trailer_id' => $line['trailer_id'] ?? null,
                ];
                if (!empty($itemId)) {
                    $fromStore[] = $row;
                } else {
                    $toBuy[] = $row;
                }
            }

            $plate = $vehicle['plate_number'] ?? 'truck';
            $noteId = null;
            if ($fromStore !== []) {
                $noteId = RequestForms::create([
                    'form_code' => 'F4', 'company_id' => $companyId, 'title' => sprintf('Parts for %s — %s', $plate, $code),
                    'vehicle_id' => $vehicleId, 'job_card_id' => $jobId, 'parent_request_id' => $job['request_id'] ?? null,
                ], $fromStore);
                RequestForms::send($noteId);
            }
            $orderId = null;
            if ($toBuy !== []) {
                $orderId = RequestForms::create([
                    'form_code' => 'F5', 'company_id' => $companyId, 'title' => sprintf('Parts to buy for %s — %s', $plate, $code),
                    'vehicle_id' => $vehicleId, 'job_card_id' => $jobId, 'parent_request_id' => $job['request_id'] ?? null,
                    'currency' => $job['currency'] ?? null,
                    'notes' => 'Not in the store. Choose the supplier and agreed prices, then send.',
                ], $toBuy);
            }

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('garage.job_opened', 'maintenance', $code, (string) $job['title']);
        Notifier::forCompany($companyId, static function () use ($code, $plate, $jobId, $noteId, $orderId, $job): void {
            Notifier::toRole('warehouse_manager', 'Job card opened', sprintf('%s for %s: %s', $code, $plate, $job['title']), 'garage', 'info', 'maintenance', (string) $jobId);
            if ($noteId !== null) {
                Notifier::toRole('warehouse_manager', 'Parts to issue', sprintf('Issue note for %s (%s) is waiting in the store.', $plate, $code), 'store_issues', 'warning', 'form_requests', (string) $noteId);
            }
            if ($orderId !== null) {
                Notifier::toRole('logistics_manager', 'Parts to buy', sprintf('Some parts for %s (%s) are not in the store. A draft purchase order is ready to complete.', $plate, $code), 'form_requests', 'warning', 'form_requests', (string) $orderId);
            }
        });

        return $jobId;
    }

    /** @return array<string, mixed>|null */
    public static function job(int $id): ?array
    {
        $condition = Company::condition('maintenance_orders', 'm.company_id');
        $statement = self::db()->prepare(
            'SELECT m.*, v.plate_number, v.mileage, v.status AS vehicle_status, v.company_id AS vehicle_company_id, tr.trailer_number,
                    r.request_no, r.form_code AS request_form, u.full_name AS closed_name, mu.full_name AS mechanic_name, co.short_name AS owner_company
               FROM maintenance_orders m
               LEFT JOIN vehicles v ON v.id = m.vehicle_id
               LEFT JOIN trailers tr ON tr.id = m.trailer_id
               LEFT JOIN form_requests r ON r.id = m.request_id
               LEFT JOIN users u ON u.id = m.closed_by
               LEFT JOIN users mu ON mu.id = m.mechanic_id
               LEFT JOIN companies co ON co.id = m.owner_company_id
              WHERE m.id = ? AND m.deleted_at IS NULL' . ($condition !== null ? ' AND ' . $condition : '')
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> parts and labour on the job card */
    public static function parts(int $jobId): array
    {
        $statement = self::db()->prepare('SELECT * FROM maintenance_parts WHERE maintenance_id = ? ORDER BY id');
        $statement->execute([$jobId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> the issue notes and orders of a job */
    public static function notes(int $jobId): array
    {
        $statement = self::db()->prepare("SELECT * FROM form_requests WHERE job_card_id = ? AND form_code IN ('F4','F5','F3','F11') AND deleted_at IS NULL ORDER BY id");
        $statement->execute([$jobId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Open job cards (or recent closed ones), for the garage board.
     *
     * @return list<array<string, mixed>>
     */
    public static function board(bool $closed = false): array
    {
        $condition = Company::condition('maintenance_orders', 'm.company_id');

        return self::db()->query(
            "SELECT m.*, v.plate_number, co.short_name AS owner_company,
                    (SELECT COUNT(*) FROM form_requests r WHERE r.job_card_id = m.id AND r.form_code = 'F4' AND r.status = 'sent') AS notes_open
               FROM maintenance_orders m LEFT JOIN vehicles v ON v.id = m.vehicle_id LEFT JOIN companies co ON co.id = m.owner_company_id
              WHERE m.deleted_at IS NULL AND m.status " . ($closed ? "IN ('completed','cancelled') AND m.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" : "IN ('open','scheduled','in_progress')")
            . ($condition !== null ? ' AND ' . $condition : '') . ' ORDER BY m.id DESC LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The mechanic starts work. */
    public static function start(int $jobId): void
    {
        $job = self::job($jobId) ?? throw new \RuntimeException('That job card does not exist.');
        if ($job['status'] !== 'open') {
            throw new \RuntimeException('Only an open job card can be started.');
        }
        self::db()->prepare("UPDATE maintenance_orders SET status = 'in_progress', started_at = NOW(), mechanic_id = COALESCE(mechanic_id, ?) WHERE id = ?")
            ->execute([\current_user_id(), $jobId]);
        AuditLog::record('garage.job_started', 'maintenance', (string) $job['work_order_code']);
    }

    /**
     * The mechanic records the work, the parts used and the odometer and closes
     * the job. The truck is Available again and, after a service, the next one
     * is set an interval later.
     *
     * @param array{work_done:string, odometer?:int|string|null, diagnosis?:string, labour_hours?:float|string|null, service_done?:bool} $data
     */
    public static function close(int $jobId, array $data): void
    {
        $job = self::job($jobId) ?? throw new \RuntimeException('That job card does not exist.');
        if (!in_array($job['status'], ['open', 'scheduled', 'in_progress'], true)) {
            throw new \RuntimeException('This job card is already closed.');
        }
        $workDone = trim((string) ($data['work_done'] ?? ''));
        if ($workDone === '') {
            throw new \RuntimeException('Write the work done before closing the job card.');
        }
        $odometer = (int) ($data['odometer'] ?? 0);
        $ownTruck = $job['vehicle_id'] !== null && (int) $job['vehicle_company_id'] === (int) $job['company_id'];
        if ($job['vehicle_id'] !== null && $odometer <= 0) {
            throw new \RuntimeException('Record the odometer reading.');
        }
        if ($ownTruck && $odometer > 0 && $odometer < (int) $job['mileage']) {
            throw new \RuntimeException(sprintf('The odometer (%s km) is below what the truck already shows (%s km).', number_format($odometer), number_format((int) $job['mileage'])));
        }
        $open = (int) self::db()->query("SELECT COUNT(*) FROM form_requests WHERE job_card_id = {$jobId} AND form_code = 'F4' AND status = 'sent'")->fetchColumn();
        if ($open > 0) {
            throw new \RuntimeException('Parts on the issue note are still waiting in the store. Have them issued, or withdraw what is not needed, before closing.');
        }

        $db = self::db();
        $db->prepare(
            "UPDATE maintenance_orders SET status = 'completed', work_done = ?, diagnosis = COALESCE(?, diagnosis), labour_hours = COALESCE(?, labour_hours),
                    odometer_reading = ?, completed_at = NOW(), closed_by = ?, actual_cost = (SELECT COALESCE(SUM(line_total), 0) FROM maintenance_parts WHERE maintenance_id = ?)
              WHERE id = ?"
        )->execute([
            $workDone, trim((string) ($data['diagnosis'] ?? '')) ?: null,
            isset($data['labour_hours']) && $data['labour_hours'] !== '' ? (float) $data['labour_hours'] : null,
            $odometer > 0 ? $odometer : null, \current_user_id(), $jobId, $jobId,
        ]);

        if ($job['vehicle_id'] !== null) {
            $vehicleId = (int) $job['vehicle_id'];
            if (!empty($data['service_done']) || $job['maintenance_type'] === 'preventive') {
                Fleet::recordService($vehicleId, $odometer);
            } elseif ($odometer > 0) {
                $db->prepare('UPDATE vehicles SET mileage = GREATEST(COALESCE(mileage, 0), ?) WHERE id = ?')->execute([$odometer, $vehicleId]);
            }
            $stillOpen = (int) $db->query("SELECT COUNT(*) FROM maintenance_orders WHERE vehicle_id = {$vehicleId} AND id <> {$jobId} AND deleted_at IS NULL AND status IN ('open','scheduled','in_progress')")->fetchColumn();
            if ($stillOpen === 0 && (Fleet::vehicle($vehicleId)['status'] ?? '') === 'in_garage') {
                Fleet::setVehicleStatus($vehicleId, 'available');
            }
        }

        if (!empty($job['request_id'])) {
            $db->prepare("UPDATE form_requests SET status = 'closed' WHERE id = ? AND status IN ('authorized','closed')")->execute([(int) $job['request_id']]);
        }

        AuditLog::record('garage.job_closed', 'maintenance', (string) $job['work_order_code'], $workDone);
        Notifier::forCompany((int) $job['company_id'], static fn () => Notifier::toRole(
            'logistics_manager',
            'Job card closed',
            sprintf('%s on %s is done: %s', $job['work_order_code'], $job['plate_number'] ?? 'the truck', $workDone),
            'garage',
            'success',
            'maintenance',
            (string) $jobId
        ));
        if ($job['owner_company_id'] !== null && class_exists(SisterRequests::class) && method_exists(SisterRequests::class, 'jobClosed')) {
            SisterRequests::jobClosed($jobId);
        }
    }

    // ---------------------------------------------------------- issue notes

    /** @return list<array<string, mixed>> issue notes waiting in the store */
    public static function issueQueue(): array
    {
        $condition = Company::condition('form_requests', 'r.company_id');

        return self::db()->query(
            "SELECT r.*, v.plate_number, m.work_order_code FROM form_requests r
               LEFT JOIN vehicles v ON v.id = r.vehicle_id LEFT JOIN maintenance_orders m ON m.id = r.job_card_id
              WHERE r.form_code = 'F4' AND r.status = 'sent' AND r.deleted_at IS NULL" . ($condition !== null ? ' AND ' . $condition : '') . '
              ORDER BY r.id'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The lines of an issue note with what is left to issue and what the
     * store holds.
     *
     * @return list<array<string, mixed>>
     */
    public static function noteLines(int $noteId): array
    {
        $rows = RequestForms::lines($noteId);
        $item = self::db()->prepare('SELECT i.id, i.item_name, i.sku, i.quantity AS on_hand, i.unit_cost, i.unit_of_measure, w.warehouse_name FROM inventory_items i LEFT JOIN warehouses w ON w.id = i.warehouse_id WHERE i.id = ?');
        foreach ($rows as &$row) {
            $row['remaining'] = max(0.0, round((float) $row['quantity'] - (float) $row['done_quantity'], 2));
            $row['stock'] = null;
            if (!empty($row['item_id'])) {
                $item->execute([(int) $row['item_id']]);
                $row['stock'] = $item->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        return $rows;
    }

    /**
     * The storekeeper issues the parts. Stock goes down, each part's value is
     * charged to the truck (Dr repairs or tyres, Cr stock) and goes onto the
     * job card. A note issued in full is closed.
     *
     * @param array<int, float|string> $quantities line id => quantity issued now
     * @return int the number of lines issued
     */
    public static function issue(int $noteId, array $quantities): int
    {
        $note = RequestForms::find($noteId) ?? throw new \RuntimeException('That issue note does not exist.');
        if ($note['form_code'] !== 'F4' || $note['status'] !== 'sent') {
            throw new \RuntimeException('This issue note is not waiting in the store.');
        }
        $lines = [];
        foreach (self::noteLines($noteId) as $line) {
            $lines[(int) $line['id']] = $line;
        }

        $db = self::db();
        $companyId = (int) $note['company_id'];
        $jobId = $note['job_card_id'] ? (int) $note['job_card_id'] : null;
        $code = (string) $note['request_no'];
        $issued = 0;
        $ledger = [];
        $fitted = [];

        $db->beginTransaction();
        try {
            foreach ($quantities as $lineId => $quantity) {
                $quantity = round((float) str_replace(',', '', (string) $quantity), 2);
                $line = $lines[(int) $lineId] ?? null;
                if ($line === null || $quantity <= 0) {
                    continue;
                }
                if ($line['stock'] === null) {
                    throw new \RuntimeException(sprintf('"%s" is not linked to a store item.', $line['description']));
                }
                if ($quantity > $line['remaining'] + 0.004) {
                    throw new \RuntimeException(sprintf('Only %s of "%s" is left to issue on this note.', self::number($line['remaining']), $line['description']));
                }
                $itemId = (int) $line['item_id'];
                $unitCost = round((float) $line['stock']['unit_cost'], 2);
                $movement = StockLedger::record($itemId, 'stock_out', $quantity, $unitCost, 'issue_note', $code, null, sprintf('Issued for %s %s', $note['plate_number'] ?? '', $note['title']));

                $db->prepare('UPDATE form_request_lines SET done_quantity = done_quantity + ? WHERE id = ?')->execute([$quantity, (int) $lineId]);
                if ($jobId !== null) {
                    $db->prepare(
                        "INSERT INTO maintenance_parts (maintenance_id, line_type, item_id, request_line_id, movement_code, issued_at, part_name, part_number, quantity, unit_cost)
                         VALUES (?, 'part', ?, ?, ?, NOW(), ?, ?, ?, ?)"
                    )->execute([$jobId, $itemId, (int) $lineId, $movement, mb_substr((string) $line['stock']['item_name'], 0, 180), $line['stock']['sku'], $quantity, $unitCost]);
                }

                $value = round($quantity * $unitCost, 2);
                if ($value > 0) {
                    $mapping = (string) $line['item_code'] === 'tyre' ? 'tyre_issued' : 'part_issued';
                    [$debit, $credit] = RequestPosting::accountsFor($companyId, $mapping, null);
                    $dims = ['vehicle_id' => $line['vehicle_id'] ?: $note['vehicle_id'], 'request_id' => $note['parent_request_id'] ?: $noteId];
                    $ledger[] = ['account' => $debit, 'debit' => $value, 'description' => sprintf('%s × %s', self::number($quantity), $line['stock']['item_name'])] + $dims;
                    $ledger[] = ['account' => $credit, 'credit' => $value, 'description' => 'Store issue ' . $code] + $dims;
                }
                $fitted[] = [$itemId, (int) ($line['vehicle_id'] ?: $note['vehicle_id']), (string) $line['stock']['item_name']];
                $issued++;
            }
            if ($issued === 0) {
                throw new \RuntimeException('Enter the quantity issued on at least one line.');
            }

            if ($ledger !== []) {
                Ledger::post(date('Y-m-d'), sprintf('Store issue %s — %s', $code, $note['title']), $ledger, 'store_issue', $noteId, $code, null, Company::baseCurrency($companyId), $companyId);
            }
            if ($jobId !== null) {
                $db->prepare('UPDATE maintenance_orders SET actual_cost = (SELECT COALESCE(SUM(line_total), 0) FROM maintenance_parts WHERE maintenance_id = ?), stock_issued_at = COALESCE(stock_issued_at, NOW()) WHERE id = ?')
                    ->execute([$jobId, $jobId]);
            }
            $left = (float) $db->query("SELECT COALESCE(SUM(quantity - done_quantity), 0) FROM form_request_lines WHERE request_id = {$noteId}")->fetchColumn();
            if ($left <= 0.004) {
                $db->prepare("UPDATE form_requests SET status = 'closed', authorized_by = ?, authorized_at = NOW(), waiting_since = NULL WHERE id = ?")->execute([\current_user_id(), $noteId]);
            }
            RequestForms::event($noteId, (int) $note['version'], $left <= 0.004 ? 'issued' : 'partly_issued', sprintf('%d line(s) issued', $issued), true);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        AuditLog::record('store.issued', 'form_requests', RequestForms::label($note), sprintf('%d line(s)', $issued));
        foreach ($fitted as [$itemId, $vehicleId, $name]) {
            self::warnIfRepeated($companyId, $itemId, $vehicleId, $name, $jobId);
        }
        Notifier::forCompany($companyId, static fn () => Notifier::toRole('warehouse_manager', 'Parts issued', sprintf('The store issued %d line(s) for %s (%s).', $issued, $note['plate_number'] ?? 'the truck', $code), 'garage', 'success', 'maintenance', (string) ($jobId ?? '')));

        return $issued;
    }

    /**
     * Parts the store cannot cover go on a draft purchase order (F5), with
     * the missing quantity.
     */
    public static function orderShortfall(int $noteId): ?int
    {
        $note = RequestForms::find($noteId) ?? throw new \RuntimeException('That issue note does not exist.');
        $lines = [];
        foreach (self::noteLines($noteId) as $line) {
            $onHand = (float) ($line['stock']['on_hand'] ?? 0);
            $missing = round($line['remaining'] - $onHand, 2);
            if ($missing > 0) {
                $lines[] = [
                    'item_code' => $line['item_code'], 'item_id' => $line['item_id'], 'description' => $line['description'],
                    'quantity' => $missing, 'unit' => $line['unit'], 'unit_price' => (float) ($line['stock']['unit_cost'] ?? 0), 'vehicle_id' => $line['vehicle_id'],
                ];
            }
        }
        if ($lines === []) {
            return null;
        }

        return RequestForms::create([
            'form_code' => 'F5', 'company_id' => (int) $note['company_id'], 'title' => 'Stock to buy for ' . RequestForms::label($note),
            'vehicle_id' => $note['vehicle_id'], 'job_card_id' => $note['job_card_id'], 'parent_request_id' => $noteId,
            'notes' => 'The store does not hold enough. Choose the supplier and agreed prices, then send.',
        ], $lines);
    }

    /** What is not needed any more is withdrawn from the note, which then closes. */
    public static function withdrawRemaining(int $noteId, string $reason): void
    {
        $note = RequestForms::find($noteId) ?? throw new \RuntimeException('That issue note does not exist.');
        if ($note['form_code'] !== 'F4' || $note['status'] !== 'sent') {
            throw new \RuntimeException('This issue note is not open.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Say why the rest is not needed.');
        }
        $db = self::db();
        $issuedAny = (float) $db->query("SELECT COALESCE(SUM(done_quantity), 0) FROM form_request_lines WHERE request_id = {$noteId}")->fetchColumn() > 0;
        $db->prepare('UPDATE form_requests SET status = ?, returned_reason = ?, waiting_since = NULL WHERE id = ?')->execute([$issuedAny ? 'closed' : 'cancelled', trim($reason), $noteId]);
        RequestForms::event($noteId, (int) $note['version'], 'withdrawn', trim($reason));
        AuditLog::record('store.withdrawn', 'form_requests', RequestForms::label($note), trim($reason));
    }

    /** "A part fitted to the same truck too often" (section 12, Alerts). */
    private static function warnIfRepeated(int $companyId, int $itemId, int $vehicleId, string $name, ?int $jobId): void
    {
        $days = Settings::int('part_repeat_days', 30);
        if ($days <= 0 || $vehicleId <= 0) {
            return;
        }
        $statement = self::db()->prepare(
            'SELECT m.work_order_code, p.issued_at FROM maintenance_parts p INNER JOIN maintenance_orders m ON m.id = p.maintenance_id
              WHERE p.item_id = ? AND m.vehicle_id = ? AND p.issued_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND m.id <> ?
              ORDER BY p.issued_at DESC LIMIT 1'
        );
        $statement->execute([$itemId, $vehicleId, $days, $jobId ?? 0]);
        $before = $statement->fetch(PDO::FETCH_ASSOC);
        if ($before === false) {
            return;
        }
        $plate = (string) (Fleet::vehicle($vehicleId)['plate_number'] ?? '');
        Notifier::forCompany($companyId, static fn () => Notifier::toRoles(
            ['logistics_manager', 'warehouse_manager'],
            'Same part fitted again',
            sprintf('%s was fitted to %s again; the last time was %s (%s).', $name, $plate, \human_date((string) $before['issued_at']), $before['work_order_code']),
            'garage',
            'warning',
            'vehicles',
            (string) $vehicleId
        ));
    }

    // --------------------------------------------------- service at 7,000 km

    /**
     * A truck that has reached its service gets a draft service request (F3)
     * for the OM to complete and send, once. Run by the clock.
     */
    public static function raiseDueServices(): int
    {
        if (Settings::int('service_request_auto', 1) !== 1) {
            return 0;
        }
        $raised = 0;
        foreach (self::db()->query("SELECT * FROM vehicles WHERE deleted_at IS NULL AND status NOT IN ('inactive')")->fetchAll(PDO::FETCH_ASSOC) as $vehicle) {
            $state = Fleet::serviceState($vehicle);
            if ($state['state'] !== 'due') {
                continue;
            }
            $id = (int) $vehicle['id'];
            $open = (int) self::db()->query(
                "SELECT (SELECT COUNT(*) FROM form_requests WHERE vehicle_id = {$id} AND form_code = 'F3' AND deleted_at IS NULL AND status NOT IN ('closed','cancelled'))
                      + (SELECT COUNT(*) FROM maintenance_orders WHERE vehicle_id = {$id} AND deleted_at IS NULL AND status IN ('open','scheduled','in_progress'))"
            )->fetchColumn();
            if ($open > 0) {
                continue;
            }
            $companyId = (int) $vehicle['company_id'];
            $requestId = RequestForms::create([
                'form_code' => 'F3', 'company_id' => $companyId, 'vehicle_id' => $id,
                'title' => sprintf('%s km service — %s', number_format(Fleet::serviceInterval($vehicle)), $vehicle['plate_number']),
                'payload' => ['odometer' => (string) (int) $vehicle['mileage'], 'problem' => sprintf('Service due at %s km (now %s km).', number_format($state['next_km']), number_format((int) $vehicle['mileage']))],
                'notes' => 'Raised by the system. Add the parts and labour, then send.',
            ], [['item_code' => 'service', 'description' => 'Service at ' . number_format((int) $vehicle['mileage']) . ' km', 'quantity' => 1, 'amount' => 0]]);
            Notifier::forCompany($companyId, static fn () => Notifier::toRole('logistics_manager', 'Service due', sprintf('%s has reached its service. A draft service request is ready.', $vehicle['plate_number']), 'form_requests', 'warning', 'form_requests', (string) $requestId));
            $raised++;
        }

        return $raised;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }
}
