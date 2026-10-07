<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The operational rules that used to be missing entirely: which status a record
 * may move to, what must be true before it moves, and what else changes when it
 * does.
 *
 * Dispatching a trip now sets the vehicle and the driver to "on trip", stamps the
 * dispatch time, pushes the shipments and deliveries into transit, moves the
 * originating request to "assigned" and tells the driver — instead of leaving a
 * person to remember all six edits.
 */
final class Workflow
{
    /** module => action => [from statuses, to status, verb used in the audit trail] */
    private const TRANSITIONS = [
        'requests' => [
            'quote' => ['from' => ['pending'], 'to' => 'quoted'],
            'approve' => ['from' => ['pending', 'quoted'], 'to' => 'approved'],
            'reject' => ['from' => ['pending', 'quoted', 'approved'], 'to' => 'rejected', 'needs_reason' => true],
            // No "assign" transition: a request becomes assigned because a trip
            // was created for it, not because somebody pressed a button and then
            // walked away from the form.
        ],
        'trips' => [
            'dispatch' => ['from' => ['requested', 'approved', 'loading'], 'to' => 'in_transit'],
            'complete' => ['from' => ['loading', 'in_transit'], 'to' => 'delivered'],
            'reject' => ['from' => ['requested', 'approved', 'loading'], 'to' => 'cancelled', 'needs_reason' => true],
        ],
        'deliveries' => [
            'drop' => ['from' => ['loading', 'in_transit'], 'to' => 'at_destination'],
            'complete' => ['from' => ['loading', 'in_transit', 'at_destination'], 'to' => 'delivered'],
            'fail' => ['from' => ['loading', 'in_transit', 'at_destination'], 'to' => 'failed', 'needs_reason' => true],
        ],
        'expenses' => [
            'approve' => ['from' => ['pending'], 'to' => 'approved'],
            'reject' => ['from' => ['pending'], 'to' => 'rejected', 'needs_reason' => true],
        ],
        'procurement' => [
            'approve' => ['from' => ['draft', 'quotation'], 'to' => 'approved'],
            'reject' => ['from' => ['draft', 'quotation', 'approved'], 'to' => 'rejected', 'needs_reason' => true],
            'receive' => ['from' => ['approved'], 'to' => 'received'],
        ],
        'maintenance' => [
            'approve' => ['from' => ['open', 'scheduled', 'in_progress'], 'to' => 'completed'],
            'reject' => ['from' => ['open', 'scheduled', 'in_progress'], 'to' => 'cancelled', 'needs_reason' => true],
        ],
        'invoices' => [
            'approve' => ['from' => ['draft'], 'to' => 'issued'],
            'reject' => ['from' => ['draft', 'issued'], 'to' => 'cancelled', 'needs_reason' => true],
        ],
        'crossings' => [
            'prepare_documents' => ['from' => ['draft'], 'to' => 'documents_pending'],
            'submit_declaration' => ['from' => ['draft', 'documents_pending'], 'to' => 'declaration_submitted'],
            'start_review' => ['from' => ['declaration_submitted'], 'to' => 'under_review'],
            'request_inspection' => ['from' => ['under_review'], 'to' => 'inspection'],
            'pass_inspection' => ['from' => ['inspection'], 'to' => 'duties_pending'],
            'assess_duties' => ['from' => ['under_review'], 'to' => 'duties_pending'],
            'request_payment' => ['from' => ['duties_pending'], 'to' => 'payment_pending'],
            'clear' => ['from' => ['payment_pending'], 'to' => 'cleared'],
            'release' => ['from' => ['cleared'], 'to' => 'released'],
            'reject' => ['from' => ['draft', 'documents_pending', 'declaration_submitted', 'under_review', 'inspection', 'duties_pending', 'payment_pending'], 'to' => 'rejected', 'needs_reason' => true],
        ],
        'cheques' => [
            'issue' => ['from' => ['draft'], 'to' => 'issued'],
            'present' => ['from' => ['issued'], 'to' => 'presented'],
            'void' => ['from' => ['draft', 'issued'], 'to' => 'void', 'needs_reason' => true],
        ],
        'supplier_bills' => [
            'approve' => ['from' => ['draft'], 'to' => 'approved'],
            'reject' => ['from' => ['draft', 'approved'], 'to' => 'cancelled', 'needs_reason' => true],
        ],
        'disbursements' => [
            'write_off' => ['from' => ['pending'], 'to' => 'written_off', 'needs_reason' => true],
        ],
        'support_requests' => [
            'acknowledge' => ['from' => ['open'], 'to' => 'in_progress'],
            'resolve' => ['from' => ['open', 'in_progress'], 'to' => 'resolved'],
            'reject' => ['from' => ['open', 'in_progress'], 'to' => 'rejected', 'needs_reason' => true],
        ],
        'price_offers' => [
            'send' => ['from' => ['draft', 'sent'], 'to' => 'sent'],
            'accept' => ['from' => ['sent'], 'to' => 'accepted'],
            'decline' => ['from' => ['draft', 'sent'], 'to' => 'declined', 'needs_reason' => true],
        ],
        'containers' => [
            'unload' => ['from' => ['out_full'], 'to' => 'unloaded'],
            'return' => ['from' => ['out_full', 'unloaded'], 'to' => 'returned'],
        ],
    ];

    private const STATUS_COLUMN = [
        'requests' => ['transport_requests', 'status'],
        'trips' => ['trips', 'status'],
        'deliveries' => ['deliveries', 'status'],
        'expenses' => ['expenses', 'status'],
        'procurement' => ['purchase_requests', 'status'],
        'maintenance' => ['maintenance_orders', 'status'],
        'invoices' => ['invoices', 'status'],
        'cheques' => ['gl_cheques', 'status'],
        'crossings' => ['border_crossings', 'status'],
        'supplier_bills' => ['supplier_bills', 'status'],
        'disbursements' => ['disbursements', 'status'],
        'containers' => ['containers', 'status'],
        'price_offers' => ['price_offers', 'status'],
        'support_requests' => ['support_requests', 'status'],
    ];

    /** Actions available for a record right now, given its status. */
    public static function availableActions(string $module, array $record): array
    {
        $current = (string) ($record['status'] ?? '');
        $available = [];

        foreach (self::TRANSITIONS[$module] ?? [] as $action => $rule) {
            if (in_array($current, $rule['from'], true)) {
                $available[$action] = $rule;
            }
        }

        // The customer accepted the prices on the offer this request came from:
        // there is nothing left to quote them.
        if ($module === 'requests' && !empty($record['price_offer_id'])) {
            unset($available['quote']);
        }

        foreach (array_keys($available) as $action) {
            if (self::warehouseHandsOver($module, $action, $record)) {
                unset($available[$action]);
            }
        }

        return $available;
    }

    /**
     * A driver closes a delivery he hands over himself. Once the load is off
     * his truck and in a warehouse, the handover (or a failed collection) is
     * the warehouse's to record, not his.
     *
     * @param array<string, mixed> $record
     */
    private static function warehouseHandsOver(string $module, string $action, array $record): bool
    {
        return $module === 'deliveries'
            && in_array($action, ['complete', 'fail'], true)
            && ($record['status'] ?? '') === 'at_destination'
            && \current_role() === 'driver';
    }

    public static function needsReason(string $module, string $action): bool
    {
        return (bool) (self::TRANSITIONS[$module][$action]['needs_reason'] ?? false);
    }

    /**
     * Runs one workflow action.
     *
     * @return array{ok: bool, message: string}
     */
    public static function apply(string $module, int $id, string $action, string $reason = ''): array
    {
        $rule = self::TRANSITIONS[$module][$action] ?? null;
        if ($rule === null) {
            return ['ok' => false, 'message' => 'That action does not exist for this module.'];
        }

        [$table, $statusColumn] = self::STATUS_COLUMN[$module];
        $record = self::record($table, $id);
        if ($record === null) {
            return ['ok' => false, 'message' => 'The record no longer exists.'];
        }

        $current = (string) $record[$statusColumn];
        if (!in_array($current, $rule['from'], true)) {
            return ['ok' => false, 'message' => sprintf('A record that is %s cannot be %sd.', Schema::label($current), $action)];
        }

        $reason = trim($reason);
        if (!empty($rule['needs_reason']) && $reason === '') {
            return ['ok' => false, 'message' => 'A reason is required for this action.'];
        }

        $guard = self::guard($module, $action, $record);
        if ($guard !== null) {
            return ['ok' => false, 'message' => $guard];
        }

        $db = Database::connection();

        // Only open a transaction when nobody else already has one: an action
        // applied from inside a larger operation must join it, not fail on
        // "there is already an active transaction".
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }

        try {
            $update = $db->prepare("UPDATE {$table} SET {$statusColumn} = ? WHERE id = ?");
            $update->execute([$rule['to'], $id]);

            $message = self::effects($module, $action, $rule['to'], $record, $reason);

            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }

            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        AuditLog::record(
            "workflow.{$module}.{$action}",
            $module,
            (string) ($record[self::codeColumn($module)] ?? $id),
            $reason === '' ? null : $reason,
            ['from' => $current, 'to' => $rule['to']]
        );

        if ($module === 'trips') {
            // A leg moving moves the journey it belongs to.
            Journey::syncForTrip($id);
        }

        return ['ok' => true, 'message' => $message];
    }

    // ----------------------------------------------------------------- guards

    /** Returns an error message when the action must not happen, or null when it may. */
    private static function guard(string $module, string $action, array $record): ?string
    {
        if ($module === 'trips' && $action === 'dispatch') {
            return self::guardDispatch($record);
        }

        if ($module === 'trips' && $action === 'complete' && empty($record['driver_id'])) {
            return 'A trip cannot be completed without a driver on it.';
        }

        if (self::warehouseHandsOver($module, $action, $record)) {
            return 'This load is in the warehouse now: the warehouse hands it over to the customer and records it.';
        }

        if ($module === 'procurement' && $action === 'receive' && empty($record['warehouse_id'])) {
            return 'Set the receiving warehouse before marking this request received.';
        }

        if ($module === 'requests' && $action === 'quote' && !empty($record['price_offer_id'])) {
            return 'The customer already accepted the prices on the offer this request came from. Approve the request instead.';
        }

        if ($module === 'requests' && $action === 'approve' && $record['quoted_amount'] === null && !empty($record['price_offer_id'])) {
            return 'This request has no price yet: check the rate card chosen in "Price chosen" and save the request again.';
        }

        if ($module === 'invoices' && $action === 'approve' && (float) $record['total_amount'] <= 0) {
            return 'Add at least one invoice line before issuing this invoice.';
        }

        if ($module === 'cheques') {
            return Cheque::guard($action, $record);
        }

        if ($module === 'crossings') {
            return BorderCrossing::guard($action, $record);
        }

        if ($module === 'maintenance' && $action === 'approve') {
            return Workshop::guardComplete((int) $record['id']);
        }

        if ($module === 'trips' && $action === 'complete') {
            $handover = Journey::guardComplete($record);
            if ($handover !== null) {
                return $handover;
            }
        }

        if ($module === 'support_requests') {
            return SupportRequest::guard($action, $record);
        }

        if ($module === 'price_offers') {
            return PriceOffer::guard($action, $record);
        }

        if ($module === 'supplier_bills' || $module === 'disbursements') {
            return Payables::guard($module, $action, $record);
        }

        return null;
    }

    private static function guardDispatch(array $trip): ?string
    {
        $db = Database::connection();
        $vehicleId = $trip['vehicle_id'] !== null ? (int) $trip['vehicle_id'] : null;
        $driverId = $trip['driver_id'] !== null ? (int) $trip['driver_id'] : null;

        if ($vehicleId === null || $driverId === null) {
            return 'Assign both a vehicle and a driver before dispatching.';
        }

        $vehicle = self::record('vehicles', $vehicleId);
        if ($vehicle === null || $vehicle['deleted_at'] !== null) {
            return 'The assigned vehicle no longer exists.';
        }
        if (in_array($vehicle['status'], ['maintenance', 'inactive'], true)) {
            return sprintf('%s is %s and cannot be dispatched.', $vehicle['plate_number'], Schema::label((string) $vehicle['status']));
        }

        $driver = self::record('drivers', $driverId);
        if ($driver === null || $driver['deleted_at'] !== null) {
            return 'The assigned driver no longer exists.';
        }
        if ($driver['status'] === 'inactive') {
            return sprintf('%s is inactive and cannot be dispatched.', $driver['full_name']);
        }
        if ($driver['license_expiry'] !== null && $driver['license_expiry'] < date('Y-m-d')) {
            return sprintf('The licence for %s expired on %s.', $driver['full_name'], $driver['license_expiry']);
        }

        // Double-booking: the same vehicle or driver already out on another trip.
        $clash = $db->prepare(
            "SELECT reference_code FROM trips
              WHERE id <> ? AND deleted_at IS NULL
                AND status IN ('loading','in_transit')
                AND (vehicle_id = ? OR driver_id = ?)
              LIMIT 1"
        );
        $clash->execute([(int) $trip['id'], $vehicleId, $driverId]);
        $clashing = $clash->fetchColumn();
        if ($clashing !== false) {
            return sprintf('That vehicle or driver is already out on trip %s.', (string) $clashing);
        }

        // Cargo the vehicle is not fit to carry.
        $cargo = $db->prepare(
            'SELECT COALESCE(SUM(weight_kg), 0) AS weight,
                    SUM(CASE WHEN cargo_type IN ("cold_chain","perishable") THEN 1 ELSE 0 END) AS cold,
                    SUM(is_hazardous) AS hazardous
               FROM shipments WHERE trip_id = ? AND deleted_at IS NULL'
        );
        $cargo->execute([(int) $trip['id']]);
        $totals = $cargo->fetch() ?: ['weight' => 0, 'cold' => 0, 'hazardous' => 0];

        if ((int) $totals['cold'] > 0 && (int) $vehicle['has_cooling_unit'] !== 1) {
            return sprintf('%s has no cooling unit and this trip carries cold-chain cargo.', $vehicle['plate_number']);
        }

        // Goods of ours going out on this truck have to actually be in the shed
        // they are leaving. Finding out afterwards means the balance has already
        // gone negative or the load has already gone without them.
        $shortage = StockIssue::guardTrip((int) $trip['id']);
        if ($shortage !== null) {
            return $shortage;
        }

        $documentGate = DocumentPack::dispatchGuard((int) $trip['id']);
        if ($documentGate !== null) {
            return $documentGate;
        }

        $capacity = $vehicle['capacity_kg'] !== null ? (float) $vehicle['capacity_kg'] : null;
        if ($capacity !== null && $capacity > 0 && (float) $totals['weight'] > $capacity) {
            return sprintf(
                'The load is %s kg but %s carries %s kg.',
                number_format((float) $totals['weight'], 0),
                $vehicle['plate_number'],
                number_format($capacity, 0)
            );
        }

        return null;
    }

    // ---------------------------------------------------------------- effects

    /** Applies everything else that must change, and returns the message for the user. */
    private static function effects(string $module, string $action, string $to, array $record, string $reason): string
    {
        $db = Database::connection();
        $id = (int) $record['id'];
        $actor = \current_user_id();
        $code = (string) ($record[self::codeColumn($module)] ?? $id);

        return match (true) {
            $module === 'requests' && $action === 'quote' => self::quoteRequest($id, $code),
            $module === 'requests' && $action === 'approve' => self::approveRequest($db, $id, $code, $actor),
            $module === 'requests' && $action === 'reject' => self::rejectRequest($db, $id, $code, $actor, $reason),
            $module === 'trips' && $action === 'dispatch' => self::dispatchTrip($db, $record, $code),
            $module === 'trips' && $action === 'complete' => self::completeTrip($db, $record, $code),
            $module === 'trips' && $action === 'reject' => self::cancelTrip($db, $record, $code, $reason),

            $module === 'cheques' => Cheque::applied($action, $record, $reason, $actor),
            $module === 'crossings' => BorderCrossing::applied($action, $record, $reason, $actor),
            $module === 'supplier_bills', $module === 'disbursements' => Payables::applied($module, $action, $record, $reason, $actor),
            $module === 'containers' => Containers::applied($action, $record, $reason, $actor),
            $module === 'price_offers' => PriceOffer::applied($action, $record, $reason, $actor),
            $module === 'support_requests' => SupportRequest::applied($action, $record, $reason, $actor),

            $module === 'deliveries' && $action === 'drop' => self::dropDelivery($db, $record, $code),
            $module === 'deliveries' && $action === 'complete' => self::completeDelivery($db, $record, $code, $actor),
            $module === 'deliveries' && $action === 'fail' => self::failDelivery($db, $record, $code, $reason),

            $module === 'expenses' && $action === 'approve' => self::approveExpense($db, $record, $code, $actor),
            $module === 'expenses' && $action === 'reject' => self::rejectExpense($db, $record, $code, $actor, $reason),

            $module === 'procurement' && $action === 'approve' => self::approvePurchase($db, $record, $code, $actor),
            $module === 'procurement' && $action === 'reject' => self::stampRejection($db, 'purchase_requests', $id, $actor, $reason, sprintf('Purchase request %s was rejected.', $code)),
            $module === 'procurement' && $action === 'receive' => self::receivePurchase($db, $record, $code),

            $module === 'maintenance' && $action === 'approve' => self::completeMaintenance($db, $record, $code, $actor),
            $module === 'maintenance' && $action === 'reject' => sprintf('Work order %s was cancelled.', $code),

            $module === 'invoices' && $action === 'approve' => self::issueInvoice($db, $record, $code, $actor),
            $module === 'invoices' && $action === 'reject' => self::cancelInvoice($id, $code),

            default => 'Done.',
        };
    }

    private static function approveRequest(PDO $db, int $id, string $code, ?int $actor): string
    {
        // "Approved" on a quoted request means the customer accepted the price,
        // so the moment is stamped. Leaving it blank would let a request read
        // Approved with nothing recorded about who agreed to what.
        $db->prepare(
            'UPDATE transport_requests
                SET approved_by = ?, approved_at = NOW(), rejection_reason = NULL,
                    accepted_at = COALESCE(accepted_at, (SELECT o.accepted_at FROM price_offers o WHERE o.id = transport_requests.price_offer_id), NOW())
              WHERE id = ?'
        )->execute([$actor, $id]);

        Notifier::toRole('logistics_manager', 'Transport request approved', sprintf('%s is approved and ready to be planned into a trip.', $code), 'requests', 'success', 'requests', $code);

        // The person who asked is almost always the person who accepts, so the
        // name is carried across rather than typed again. It stays editable for
        // the case where somebody senior signed it off instead.
        $db->prepare(
            'UPDATE transport_requests r
               LEFT JOIN customers c ON c.id = r.customer_id
                SET r.accepted_by = COALESCE(NULLIF(r.accepted_by, ""), NULLIF(r.requested_by_contact, ""), NULLIF(c.contact_name, ""))
              WHERE r.id = ?'
        )->execute([$id]);

        return sprintf('Request %s approved. Plan a trip for it next.', $code);
    }

    private static function rejectRequest(PDO $db, int $id, string $code, ?int $actor, string $reason): string
    {
        $stamp = $db->prepare('UPDATE transport_requests SET approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?');
        $stamp->execute([$actor, mb_substr($reason, 0, 255), $id]);

        $requester = $db->prepare('SELECT requester_id FROM transport_requests WHERE id = ?');
        $requester->execute([$id]);
        Notifier::toUser($requester->fetchColumn() ?: null, 'Transport request rejected', sprintf('%s was rejected: %s', $code, $reason), 'requests', 'danger', 'requests', $code);

        return sprintf('Request %s was rejected.', $code);
    }

    private static function dispatchTrip(PDO $db, array $trip, string $code): string
    {
        $id = (int) $trip['id'];

        // The company releases the truck; the driver only drives it off. When he
        // starts the trip himself, the dispatcher is whoever passed its check.
        $dispatcher = \current_user_id();
        if (\current_role() === 'driver') {
            $checker = $db->prepare("SELECT pc.checked_by FROM shipment_pre_dispatch_checks pc INNER JOIN shipments s ON s.id = pc.shipment_id
                                      WHERE s.trip_id = ? AND pc.status = 'ready' AND pc.checked_by IS NOT NULL ORDER BY pc.checked_at DESC, pc.id DESC LIMIT 1");
            $checker->execute([$id]);
            $dispatcher = ($found = $checker->fetchColumn()) ? (int) $found : null;
        }
        $db->prepare('UPDATE trips SET dispatched_at = NOW(), dispatched_by = ?, departure_at = COALESCE(departure_at, NOW()) WHERE id = ?')->execute([$dispatcher, $id]);
        // Distances on the trip count from the clock at the start: the driver's
        // reading when he starts it himself, otherwise the truck's last known one.
        $db->prepare('UPDATE trips t INNER JOIN vehicles v ON v.id = t.vehicle_id SET t.start_mileage = COALESCE(t.start_mileage, v.mileage) WHERE t.id = ?')->execute([$id]);
        $db->prepare("UPDATE vehicles SET status = 'on_trip' WHERE id = ?")->execute([(int) $trip['vehicle_id']]);
        $db->prepare("UPDATE drivers SET status = 'on_trip' WHERE id = ?")->execute([(int) $trip['driver_id']]);
        $db->prepare("UPDATE shipments SET status = 'in_transit' WHERE trip_id = ? AND deleted_at IS NULL AND status IN ('draft','booked','loaded','released')")->execute([$id]);
        $db->prepare("UPDATE deliveries SET status = 'in_transit' WHERE trip_id = ? AND deleted_at IS NULL AND status = 'loading'")->execute([$id]);

        if (!empty($trip['request_id'])) {
            $db->prepare("UPDATE transport_requests SET status = 'assigned', trip_id = ? WHERE id = ? AND status IN ('approved', 'assigned')")->execute([$id, (int) $trip['request_id']]);
        }

        // The driver may start the trip himself as he drives out; then it is the
        // office that needs telling, not him.
        $byDriver = \current_role() === 'driver';
        if ($byDriver) {
            Notifier::toRole('logistics_manager', 'Driver started the trip',
                sprintf('%s: %s left %s at %s, heading for %s.', $code, \current_user_name() ?: 'The driver', $trip['pickup_location'], date('H:i'), $trip['destination']),
                'trips', 'success', 'trips', $code);
        }
        $driverUser = $db->prepare('SELECT user_id FROM drivers WHERE id = ?');
        $driverUser->execute([(int) $trip['driver_id']]);
        $byDriver || Notifier::toUser(
            $driverUser->fetchColumn() ?: null,
            'Trip dispatched to you',
            sprintf('%s from %s to %s is now in transit.', $code, $trip['pickup_location'], $trip['destination']),
            'trips',
            'info',
            'trips',
            $code
        );

        // Goods of ours on board leave the company's stock, through the ordinary
        // ledger, with this trip as the document behind the issue.
        $issued = StockIssue::issueTrip($id, $code);

        // Physically, the shed is now emptier. Each load leaves the depot it was
        // standing in, with this trip named as the reason, so a depot manager can
        // answer "where did yesterday's pallets go" without asking anybody.
        CargoCustody::loaded($id);

        // The sheet the driver works from on the road: the loads behind him, who
        // signs for each one, and the route. A notification he can only read at
        // a screen is no use once the truck has left.
        // A driver who starts the trip himself already has the report from when it became ready.
        $sheet = $byDriver ? false : DriverMail::tripSheet($id);

        // The people waiting for the goods hear it too, not just the driver.
        $told = CustomerMail::dispatched($id);

        // The people who run the trip get the full report, prices included.
        $office = 0;
        try {
            $office = TripReport::sendToOffice($id);
        } catch (\Throwable $exception) {
            error_log('Trip report could not be sent: ' . $exception->getMessage());
        }

        return sprintf(
            '%s dispatched. The vehicle and driver are now marked on trip.%s%s%s%s',
            $code,
            $issued > 0
                ? sprintf(' %d stock line(s) were issued out of the depot.', $issued)
                : '',
            $byDriver
                ? ' You are on your way: press I have arrived at each stop.'
                : ($sheet
                    ? ' The trip sheet was emailed to the driver.'
                    : ' No trip sheet was sent: this driver has no email address on file.'),
            $told > 0 ? sprintf(' %d customer(s) were told their goods are on the way.', $told) : '',
            $office > 0 ? sprintf(' The trip report (PDF and Excel) was sent to %d people in the office.', $office) : ''
        );
    }

    private static function completeTrip(PDO $db, array $trip, string $code): string
    {
        $id = (int) $trip['id'];

        $db->prepare('UPDATE trips SET completed_at = NOW(), arrival_at = COALESCE(arrival_at, NOW()) WHERE id = ?')->execute([$id]);
        if (!empty($trip['vehicle_id'])) {
            $db->prepare("UPDATE vehicles SET status = 'available' WHERE id = ? AND status = 'on_trip'")->execute([(int) $trip['vehicle_id']]);
        }
        if (!empty($trip['driver_id'])) {
            $db->prepare("UPDATE drivers SET status = 'available' WHERE id = ? AND status = 'on_trip'")->execute([(int) $trip['driver_id']]);
        }
        // A load that cleared a border on the way is still aboard until the trip ends.
        $db->prepare("UPDATE shipments SET status = 'delivered' WHERE trip_id = ? AND deleted_at IS NULL AND status IN ('loaded','in_transit','cleared','released')")->execute([$id]);

        $onTime = self::onTimeVerdict($trip);
        Notifier::toRole('logistics_manager', 'Trip completed', sprintf('%s arrived at %s. %s', $code, $trip['destination'], $onTime), 'trips', 'success', 'trips', $code);

        // The report as it stands at the end of the trip: every stop, border,
        // cost and delivery that happened on the way.
        $office = 0;
        try {
            $office = TripReport::sendToOffice($id, 'completed');
        } catch (\Throwable $exception) {
            error_log('Final trip report could not be sent: ' . $exception->getMessage());
        }

        return sprintf('%s completed. Vehicle and driver released. %s%s', $code, $onTime,
            $office > 0 ? sprintf(' The final trip report was sent to %d people in the office.', $office) : '');
    }

    private static function cancelTrip(PDO $db, array $trip, string $code, string $reason): string
    {
        $id = (int) $trip['id'];

        $db->prepare('UPDATE trips SET notes = TRIM(CONCAT(COALESCE(notes, ""), "\nCancelled: ", ?)) WHERE id = ?')->execute([$reason, $id]);
        if (!empty($trip['vehicle_id'])) {
            $db->prepare("UPDATE vehicles SET status = 'available' WHERE id = ? AND status = 'on_trip'")->execute([(int) $trip['vehicle_id']]);
        }
        if (!empty($trip['driver_id'])) {
            $db->prepare("UPDATE drivers SET status = 'available' WHERE id = ? AND status = 'on_trip'")->execute([(int) $trip['driver_id']]);
        }
        $db->prepare("UPDATE shipments SET status = 'cancelled' WHERE trip_id = ? AND deleted_at IS NULL AND status NOT IN ('delivered','returned')")->execute([$id]);
        if (!empty($trip['request_id'])) {
            $db->prepare("UPDATE transport_requests SET status = 'approved', trip_id = NULL WHERE id = ? AND status = 'assigned'")->execute([(int) $trip['request_id']]);
        }

        return sprintf('%s cancelled and its vehicle and driver released.', $code);
    }

    /**
     * The load has reached its own warehouse, and the truck drives on.
     *
     * A trip running Kigali to Rusumo may drop part of its load at Kayonza. That
     * shipment has arrived: it is off the truck, it is at the warehouse it was
     * sold to, and the only thing left is for the customer to come and collect
     * it. The trip carries on with what is still on board.
     *
     * Calling that "delivered" would be a lie to the customer who has not got it
     * yet, and calling it "in transit" would be a lie to the driver who no
     * longer has it. Hence a state of its own.
     */
    /**
     * Work out the price and send it to the customer.
     *
     * A quote that stays inside the office is not a quote. This puts a figure on
     * the request, explains how it was reached, and emails it to the person who
     * asked — which is the whole transaction up to the point of agreement.
     */
    private static function quoteRequest(int $id, string $code): string
    {
        $quote = Rate::quoteRequest($id);

        if ($quote === null) {
            return sprintf(
                'There is no rate card covering that route yet, so %s could not be priced. Add a rate card for those two warehouses and quote it again.',
                $code
            );
        }

        Database::connection()->prepare('UPDATE transport_requests SET quoted_at = COALESCE(quoted_at, NOW()) WHERE id = ?')->execute([$id]);

        CustomerMail::quote($id);

        $quotedIn = (string) (Database::connection()->query('SELECT currency FROM transport_requests WHERE id = ' . (int) $id)->fetchColumn() ?: Currency::base());

        return sprintf('Quoted %s — %s.%s', Currency::format((float) $quote['amount'], $quotedIn), $quote['basis'], CustomerMail::outcome());
    }

    private static function dropDelivery(PDO $db, array $delivery, string $code): string
    {
        $id = (int) $delivery['id'];
        $db->prepare('UPDATE deliveries SET dropped_at = COALESCE(dropped_at, NOW()) WHERE id = ?')->execute([$id]);

        if (!empty($delivery['shipment_id'])) {
            $db->prepare("UPDATE shipments SET status = 'delivered' WHERE id = ?")->execute([(int) $delivery['shipment_id']]);
        }

        $where = self::warehouseName($db, $delivery['destination_warehouse_id'] ?? null) ?? (string) $delivery['destination'];

        // It is in somebody's shed now, and that shed has to show it.
        if (!empty($delivery['shipment_id'])) {
            CargoCustody::arrived(
                (int) $delivery['shipment_id'],
                !empty($delivery['destination_warehouse_id']) ? (int) $delivery['destination_warehouse_id'] : null,
                $id
            );
        }

        Notifier::toRole(
            'logistics_manager',
            'Load at its destination warehouse',
            sprintf('%s is off the truck at %s and is waiting to be collected by %s.', $code, $where, $delivery['recipient_name']),
            'deliveries',
            'info',
            'deliveries',
            $code
        );

        CustomerMail::arrived($id);

        return sprintf(
            '%s is at %s and waiting to be collected. The trip carries on with whatever is still on board.%s',
            $code,
            $where,
            CustomerMail::outcome()
        );
    }

    private static function warehouseName(PDO $db, mixed $warehouseId): ?string
    {
        if (empty($warehouseId)) {
            return null;
        }

        $statement = $db->prepare('SELECT warehouse_name FROM warehouses WHERE id = ?');
        $statement->execute([(int) $warehouseId]);
        $name = $statement->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    private static function completeDelivery(PDO $db, array $delivery, string $code, ?int $actor): string
    {
        $id = (int) $delivery['id'];

        $db->prepare("UPDATE deliveries SET delivered_at = COALESCE(delivered_at, NOW()), delivered_by = ?, failure_reason = 'none', failure_notes = NULL WHERE id = ?")
           ->execute([$actor, $id]);

        if (!empty($delivery['shipment_id'])) {
            $db->prepare("UPDATE shipments SET status = 'delivered' WHERE id = ?")->execute([(int) $delivery['shipment_id']]);
        }

        // Handed over, so it is out of the depot as well as off the books.
        if (!empty($delivery['shipment_id'])) {
            CargoCustody::collected(
                (int) $delivery['shipment_id'],
                !empty($delivery['destination_warehouse_id']) ? (int) $delivery['destination_warehouse_id'] : null,
                $id
            );
        }

        $warning = empty($delivery['proof_file'])
            ? ' Proof of delivery is still missing; upload it on the delivery record.'
            : '';

        Notifier::toRole('logistics_manager', 'Delivery completed', sprintf('%s was delivered to %s.', $code, $delivery['recipient_name']), 'deliveries', 'success', 'deliveries', $code);

        CustomerMail::handedOver($id);

        return sprintf(
            '%s marked delivered.%s%s',
            $code,
            $warning,
            CustomerMail::outcome()
        );
    }

    private static function failDelivery(PDO $db, array $delivery, string $code, string $reason): string
    {
        $id = (int) $delivery['id'];
        $knownReasons = ['recipient_absent', 'address_wrong', 'goods_damaged', 'goods_refused', 'vehicle_breakdown', 'access_denied', 'weather', 'other'];
        $slug = strtolower(str_replace(' ', '_', trim($reason)));
        $code_reason = in_array($slug, $knownReasons, true) ? $slug : 'other';

        $db->prepare('UPDATE deliveries SET failure_reason = ?, failure_notes = ? WHERE id = ?')
           ->execute([$code_reason, mb_substr($reason, 0, 255), $id]);

        Notifier::toRole('logistics_manager', 'Delivery failed', sprintf('%s to %s failed: %s', $code, $delivery['recipient_name'], $reason), 'deliveries', 'danger', 'deliveries', $code);

        return sprintf('%s recorded as failed. Create a new attempt when it is rescheduled.', $code);
    }

    private static function approveExpense(PDO $db, array $expense, string $code, ?int $actor): string
    {
        $db->prepare('UPDATE expenses SET approved_by = ?, approved_at = NOW(), rejection_reason = NULL WHERE id = ?')
           ->execute([$actor, (int) $expense['id']]);

        Posting::tryPost('expense', (int) $expense['id']);

        Notifier::toUser(
            $expense['submitted_by'] !== null ? (int) $expense['submitted_by'] : null,
            'Expense approved',
            sprintf('%s for %s was approved.', $code, Currency::format((float) $expense['amount'], (string) ($expense['currency'] ?? '') ?: Currency::base())),
            'expenses',
            'success',
            'expenses',
            $code
        );

        return sprintf('Expense %s approved for %s.', $code, Currency::format((float) $expense['amount'], (string) ($expense['currency'] ?? '') ?: Currency::base()));
    }

    private static function rejectExpense(PDO $db, array $expense, string $code, ?int $actor, string $reason): string
    {
        $db->prepare('UPDATE expenses SET approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?')
           ->execute([$actor, mb_substr($reason, 0, 255), (int) $expense['id']]);

        Posting::unpost('expense', (int) $expense['id']);

        Notifier::toUser(
            $expense['submitted_by'] !== null ? (int) $expense['submitted_by'] : null,
            'Expense rejected',
            sprintf('%s was rejected: %s', $code, $reason),
            'expenses',
            'danger',
            'expenses',
            $code
        );

        return sprintf('Expense %s rejected.', $code);
    }

    private static function approvePurchase(PDO $db, array $request, string $code, ?int $actor): string
    {
        $db->prepare('UPDATE purchase_requests SET approved_by = ?, approved_at = NOW(), rejection_reason = NULL WHERE id = ?')
           ->execute([$actor, (int) $request['id']]);

        Notifier::toRole('warehouse_manager', 'Purchase request approved', sprintf('%s is approved and can be ordered.', $code), 'procurement', 'success', 'procurement', $code);

        $ordered = self::emailPurchaseOrder($db, $request, $code);

        return sprintf('Purchase request %s approved.%s', $code, $ordered);
    }

    /**
     * Sends the approved request to the supplier as an order.
     *
     * Approval is the moment the company commits to buying, so it is the moment
     * the supplier needs to hear about it. Without this the approval only ever
     * reached the people who already knew, and somebody still had to pick up the
     * phone — which is where the quantity and the price start to drift.
     */
    private static function emailPurchaseOrder(PDO $db, array $request, string $code): string
    {
        if (empty($request['supplier_id'])) {
            return ' No supplier is named on it, so no order was sent.';
        }

        $supplier = $db->prepare('SELECT supplier_name, contact_name, email, payment_terms_days FROM suppliers WHERE id = ? AND deleted_at IS NULL');
        $supplier->execute([(int) $request['supplier_id']]);
        $who = $supplier->fetch();

        if ($who === false || trim((string) $who['email']) === '') {
            return sprintf(' %s has no email address on file, so no order was sent.', $who === false ? 'That supplier' : $who['supplier_name']);
        }

        // Approved, the request is an order: the supplier sees PO-, not PR-.
        $order = Documents::orderNumber($code);

        $lines = $db->prepare(
            'SELECT item_name, quantity, unit_of_measure, unit_price, line_total
               FROM purchase_request_lines WHERE purchase_request_id = ? ORDER BY id'
        );
        $lines->execute([(int) $request['id']]);

        // Priced in the money the request was raised in, not the company's own.
        $money = static fn (float $amount): string => Currency::format($amount, (string) ($request['currency'] ?? ''));

        $rows = [];
        foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $rows[] = [
                'item_name' => (string) $line['item_name'],
                'quantity' => Documents::units((float) $line['quantity'], (string) ($line['unit_of_measure'] ?: 'unit')),
                'unit_price' => $money((float) $line['unit_price']),
                'line_total' => $money((float) $line['line_total']),
            ];
        }

        // A request approved without itemised lines still has its description.
        if ($rows === []) {
            $rows[] = [
                'item_name' => (string) $request['description'],
                'quantity' => '—',
                'unit_price' => '—',
                'line_total' => $money((float) $request['amount']),
            ];
        }

        $warehouse = '';
        if (!empty($request['warehouse_id'])) {
            $place = $db->prepare('SELECT warehouse_name, location FROM warehouses WHERE id = ?');
            $place->execute([(int) $request['warehouse_id']]);
            $row = $place->fetch();
            $warehouse = $row === false ? '' : trim($row['warehouse_name'] . ', ' . $row['location']);
        }

        $pdf = Documents::purchaseOrder((int) $request['id']);
        $required = (string) ($request['expected_date'] ?? '');
        $result = \Support\Mailer::send([
            'key' => 'purchase-order-' . $request['id'],
            'category' => 'invoice',
            'to' => trim((string) $who['email']),
            'to_name' => trim((string) ($who['contact_name'] ?: $who['supplier_name'])),
            'subject' => sprintf('Purchase order %s from %s', $order, \company_name()),
            'heading' => 'Purchase order ' . $order,
            'preheader' => sprintf('Order %s for %s. Please confirm the delivery date.', $order, $money((float) $request['amount'])),
            'lines' => [
                sprintf('Dear %s,', $who['contact_name'] ?: $who['supplier_name']),
                sprintf(
                    'Please supply the items below. Quote %s on your delivery note and invoice.%s',
                    $order,
                    $pdf !== null ? ' The signed order is attached as a PDF.' : ''
                ),
            ],
            'items' => [
                'title' => 'Items ordered',
                'columns' => ['item_name' => 'Item', 'quantity' => 'Quantity', 'unit_price' => 'Unit price', 'line_total' => 'Amount'],
                'numeric' => ['quantity', 'unit_price', 'line_total'],
                'rows' => $rows,
                'totals' => ['Order value' => $money((float) $request['amount'])],
            ],
            'facts' => [
                'Order number' => $order,
                'Order date' => \human_date(date('Y-m-d')),
                'Deliver to' => $warehouse !== '' ? $warehouse : 'To be confirmed',
                // A date already behind us is no date to deliver by.
                'Deliver by' => $required !== '' && $required >= date('Y-m-d') ? \human_date($required) : 'As soon as possible',
                'Payment terms' => sprintf('%d days from delivery', (int) ($who['payment_terms_days'] ?? 30)),
                'Invoice to' => \company_name() . (($tin = trim(Settings::get('company_tin', ''))) !== '' ? ', TIN ' . $tin : ''),
            ],
            'closing' => [
                'Please reply to confirm the order and your delivery date.',
                'Deliver during working hours with a delivery note quoting ' . $order . '.',
            ],
            'attachments' => $pdf !== null ? [$pdf] : [],
            'entity_type' => 'procurement',
            'entity_id' => $code,
        ]);

        return $result['sent']
            ? sprintf(' The order was emailed to %s at %s.', $who['supplier_name'], $who['email'])
            : sprintf(' The order to %s is in the outbox (%s).', $who['email'], $result['reason']);
    }

    /**
     * Confirms to the supplier that their delivery arrived.
     *
     * Until now the order went out and nothing came back, so a supplier had no
     * written acknowledgement that the goods had been accepted — and the first
     * anybody heard of a short delivery was when the invoice was queried. This
     * is the note they invoice against, and the record that the count was done.
     */
    private static function emailGoodsReceived(PDO $db, array $request, string $code): string
    {
        if (empty($request['supplier_id'])) {
            return '';
        }

        $supplier = $db->prepare('SELECT supplier_name, contact_name, email, payment_terms_days FROM suppliers WHERE id = ? AND deleted_at IS NULL');
        $supplier->execute([(int) $request['supplier_id']]);
        $who = $supplier->fetch();

        if ($who === false || trim((string) $who['email']) === '') {
            return ' No receipt note was sent: that supplier has no email address on file.';
        }

        $money = static fn (float $amount): string => Currency::format($amount, (string) ($request['currency'] ?? ''));

        $lines = $db->prepare(
            'SELECT item_name, quantity, unit_of_measure, unit_price, line_total
               FROM purchase_request_lines WHERE purchase_request_id = ? ORDER BY id'
        );
        $lines->execute([(int) $request['id']]);

        $rows = [];
        foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $rows[] = [
                'item_name' => (string) $line['item_name'],
                'quantity' => Documents::units((float) $line['quantity'], (string) ($line['unit_of_measure'] ?: 'unit')),
                'line_total' => $money((float) $line['line_total']),
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'item_name' => (string) $request['description'],
                'quantity' => '—',
                'line_total' => $money((float) $request['amount']),
            ];
        }

        $where = '';
        if (!empty($request['warehouse_id'])) {
            $place = $db->prepare('SELECT warehouse_name, location FROM warehouses WHERE id = ?');
            $place->execute([(int) $request['warehouse_id']]);
            $row = $place->fetch();
            $where = $row === false ? '' : trim($row['warehouse_name'] . ', ' . $row['location']);
        }

        $terms = (int) ($who['payment_terms_days'] ?? 30);
        $order = Documents::orderNumber($code);

        $result = \Support\Mailer::send([
            'key' => 'goods-received-' . $request['id'],
            'category' => 'invoice',
            'to' => trim((string) $who['email']),
            'to_name' => trim((string) ($who['contact_name'] ?: $who['supplier_name'])),
            'subject' => sprintf('Goods received against order %s', $order),
            'heading' => 'Goods received — ' . $order,
            'lines' => [
                sprintf('Dear %s,', $who['contact_name'] ?: $who['supplier_name']),
                sprintf(
                    'We confirm that the goods ordered under %s were delivered and checked in%s. The quantities received are set out below.',
                    $order,
                    $where !== '' ? ' at ' . $where : ''
                ),
            ],
            'items' => [
                'title' => 'Received',
                'columns' => ['item_name' => 'Item', 'quantity' => 'Quantity received', 'line_total' => 'Value'],
                'numeric' => ['quantity', 'line_total'],
                'rows' => $rows,
                'totals' => ['Total received' => $money((float) $request['amount'])],
            ],
            'facts' => [
                'Order number' => $order,
                'Received on' => \human_date(date('Y-m-d')),
                'Received at' => $where !== '' ? $where : 'Our premises',
                'Payment terms' => sprintf('%d days from delivery', $terms),
                'Payment due by' => \human_date(date('Y-m-d', strtotime('+' . $terms . ' days'))),
            ],
            'closing' => [
                sprintf('You may now invoice us for this delivery. Please quote %s on the invoice so that it is matched and paid without delay.', $order),
                'If anything in the table above does not agree with your delivery note, reply to this message and we will check it against the goods before the invoice is processed.',
                'Thank you for supplying us.',
            ],
            'entity_type' => 'procurement',
            'entity_id' => $code,
        ]);

        return $result['sent']
            ? sprintf(' A receipt note was emailed to %s.', $who['supplier_name'])
            : sprintf(' The receipt note to %s is in the outbox (%s).', $who['email'], $result['reason']);
    }
    private static function receivePurchase(PDO $db, array $request, string $code): string
    {
        $db->prepare('UPDATE purchase_requests SET received_at = NOW() WHERE id = ?')->execute([(int) $request['id']]);

        $posted = StockLedger::receivePurchaseRequest((int) $request['id']);
        Posting::tryPost('purchase', (int) $request['id']);

        Notifier::toRole('warehouse_manager', 'Goods received', sprintf('%s was received and %d stock line(s) were posted.', $code, $posted), 'warehouse', 'success', 'procurement', $code);

        // The supplier is owed an answer: their delivery arrived, and this is
        // what they invoice against.
        $told = self::emailGoodsReceived($db, $request, $code);

        return ($posted > 0
            ? sprintf('%s received. %d stock line(s) posted into inventory.', $code, $posted)
            : sprintf('%s received. No line was linked to a stock item, so nothing was posted into inventory.', $code)) . $told;
    }

    private static function completeMaintenance(PDO $db, array $order, string $code, ?int $actor): string
    {
        $id = (int) $order['id'];

        $parts = $db->prepare('SELECT COALESCE(SUM(line_total), 0) FROM maintenance_parts WHERE maintenance_id = ?');
        $parts->execute([$id]);
        $partsTotal = (float) $parts->fetchColumn();

        $db->prepare('UPDATE maintenance_orders SET completed_at = COALESCE(completed_at, NOW()), approved_by = ?, approved_at = NOW(), actual_cost = COALESCE(NULLIF(?, 0), actual_cost, estimated_cost) WHERE id = ?')
           ->execute([$actor, $partsTotal, $id]);

        if (!empty($order['vehicle_id'])) {
            $db->prepare("UPDATE vehicles SET status = 'available' WHERE id = ? AND status = 'maintenance'")->execute([(int) $order['vehicle_id']]);
        }

        // Parts from our own shelves leave the store now, and the truck takes
        // the odometer and next service the mechanic wrote on the card.
        $issued = Workshop::issueStock($id, $code);
        Workshop::updateVehicle($order);

        $final = $db->prepare('SELECT actual_cost FROM maintenance_orders WHERE id = ?');
        $final->execute([$id]);
        $cost = (float) ($final->fetchColumn() ?: 0);

        Posting::tryPost('maintenance', $id);

        $variance = $cost - (float) ($order['estimated_cost'] ?? 0);
        $note = abs($variance) < 0.01
            ? 'Cost matched the estimate.'
            : sprintf('%s %s the estimate.', Settings::money(abs($variance)), $variance > 0 ? 'over' : 'under');

        return sprintf('Work order %s completed at %s. %s%s', $code, Settings::money($cost), $note,
            $issued > 0 ? sprintf(' %d part line%s taken out of stock.', $issued, $issued === 1 ? '' : 's') : '');
    }

    private static function issueInvoice(PDO $db, array $invoice, string $code, ?int $actor): string
    {
        $db->prepare('UPDATE invoices SET issued_by = ?, issue_date = COALESCE(issue_date, CURDATE()) WHERE id = ?')
           ->execute([$actor, (int) $invoice['id']]);

        Posting::tryPost('invoice', (int) $invoice['id']);

        $total = Currency::format((float) $invoice['total_amount'], (string) ($invoice['currency'] ?? '') ?: Currency::base());
        Notifier::toRole('finance', 'Invoice issued', sprintf('%s for %s is issued and due on %s.', $code, $total, \human_date($invoice['due_date'])), 'invoices', 'info', 'invoices', $code);

        $emailed = self::emailInvoice($db, $invoice, $code);

        return sprintf('Invoice %s issued for %s.%s', $code, $total, $emailed);
    }

    /**
     * Sends the invoice to the customer it is addressed to.
     *
     * An invoice nobody receives is not a bill, it is a note to self. The address
     * is the one on the customer record; when it is missing the message says so,
     * rather than the issue quietly succeeding and the money never arriving.
     */
    /** The ways to pay in the invoice's currency, for the bottom of an invoice. */
    private static function paymentFacts(string $currency): array
    {
        $facts = [];

        foreach (PaymentMethod::invoiceInstructions($currency) as $method) {
            $facts['Pay by ' . $method['name']] = $method['details'] !== '' ? $method['details'] : 'Ask us for the details';
        }

        return $facts;
    }

    private static function emailInvoice(PDO $db, array $invoice, string $code): string
    {
        if (empty($invoice['customer_id'])) {
            return ' It is not addressed to a customer, so nothing was emailed.';
        }

        $customer = $db->prepare('SELECT customer_name, contact_name, email, tin_number FROM customers WHERE id = ? AND deleted_at IS NULL');
        $customer->execute([(int) $invoice['customer_id']]);
        $row = $customer->fetch();

        if ($row === false || trim((string) $row['email']) === '') {
            return sprintf(' %s has no email address on file, so nothing was sent.', $row === false ? 'That customer' : $row['customer_name']);
        }

        $lines = $db->prepare('SELECT description, quantity, unit_price, line_total FROM invoice_lines WHERE invoice_id = ? ORDER BY id');
        $lines->execute([(int) $invoice['id']]);

        // The customer is billed in their own currency, so the email says so.
        $currency = (string) ($invoice['currency'] ?? '') ?: Currency::base();
        $money = static fn (float $amount): string => Currency::format($amount, $currency);

        $rows = [];
        foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $rows[] = [
                'description' => (string) $line['description'],
                'quantity' => rtrim(rtrim(number_format((float) $line['quantity'], 2, '.', ''), '0'), '.'),
                'unit_price' => $money((float) $line['unit_price']),
                'line_total' => $money((float) $line['line_total']),
            ];
        }

        $pdf = Documents::invoice((int) $invoice['id']);
        $result = \Support\Mailer::send([
            'key' => 'invoice-issued-' . $invoice['id'],
            'category' => 'invoice',
            'to' => trim((string) $row['email']),
            'to_name' => trim((string) ($row['contact_name'] ?: $row['customer_name'])),
            'subject' => sprintf('Invoice %s from %s', $code, \company_name()),
            'heading' => 'Invoice ' . $code,
            'preheader' => sprintf('Invoice %s for %s, due %s.', $code, $money((float) $invoice['total_amount']), \human_date($invoice['due_date'])),
            'lines' => [
                sprintf('Dear %s,', $row['contact_name'] ?: $row['customer_name']),
                sprintf(
                    'Thank you for your business. Invoice %s is set out below and falls due on %s.%s',
                    $code,
                    \human_date($invoice['due_date']),
                    $pdf !== null ? ' The invoice is attached as a PDF for your records.' : ''
                ),
            ],
            'items' => [
                'title' => 'What this invoice covers',
                'columns' => ['description' => 'Description', 'quantity' => 'Qty', 'unit_price' => 'Unit price', 'line_total' => 'Amount'],
                'numeric' => ['quantity', 'unit_price', 'line_total'],
                'rows' => $rows,
                'totals' => [
                    'Subtotal' => $money((float) $invoice['subtotal']),
                    sprintf('VAT at %s%%', rtrim(rtrim(number_format((float) $invoice['tax_rate'], 2, '.', ''), '0'), '.')) => $money((float) $invoice['tax_amount']),
                    'Total due' => $money((float) $invoice['total_amount']),
                ],
            ],
            'facts' => array_merge(
                [
                    'Invoice number' => $code,
                    'Your order (LPO)' => (string) ($invoice['lpo_number'] ?? ''),
                    'Invoice date' => \human_date($invoice['issue_date'] ?: date('Y-m-d')),
                    'Payment due by' => \human_date($invoice['due_date']),
                    'Your TIN' => (string) ($row['tin_number'] ?? ''),
                    'Our TIN' => trim(Settings::get('company_tin', '')),
                ],
                // A bill that does not say where to send the money is a bill
                // somebody has to ring up about.
                self::paymentFacts($currency)
            ),
            'closing' => [
                'Please quote the invoice number when you pay so we can match it against your account.',
                'If anything on this invoice looks wrong, reply to this message and we will look into it.',
            ],
            'attachments' => $pdf !== null ? [$pdf] : [],
            'entity_type' => 'invoices',
            'entity_id' => $code,
        ]);

        return $result['sent']
            ? sprintf(' It was emailed to %s.', $row['email'])
            : sprintf(' The email to %s is in the outbox (%s).', $row['email'], $result['reason']);
    }

    /** Cancelling an invoice takes its entry back out of the ledger. */
    private static function cancelInvoice(int $id, string $code): string
    {
        Posting::unpost('invoice', $id);

        // Duty paid for the customer is still owed by them: it goes back on the
        // list to be put on the replacement invoice.
        Payables::syncInvoiceDisbursements($id);

        return sprintf('Invoice %s was cancelled and its ledger entry removed.', $code);
    }

    private static function stampRejection(PDO $db, string $table, int $id, ?int $actor, string $reason, string $message): string
    {
        $db->prepare("UPDATE {$table} SET approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?")
           ->execute([$actor, mb_substr($reason, 0, 255), $id]);

        return $message;
    }

    // ---------------------------------------------------------------- helpers

    private static function onTimeVerdict(array $trip): string
    {
        if (empty($trip['planned_arrival_at'])) {
            return 'No planned arrival was set, so on-time performance was not measured.';
        }

        $grace = Settings::int('on_time_grace_minutes', 30);
        $deadline = strtotime((string) $trip['planned_arrival_at']) + ($grace * 60);
        $late = time() - $deadline;

        return $late <= 0
            ? 'Arrived on time.'
            : sprintf('Arrived %d minute(s) after the agreed arrival time.', (int) ceil($late / 60));
    }

    private static function codeColumn(string $module): string
    {
        return Schema::has($module) ? Schema::get($module)['code'] : 'id';
    }

    private static function record(string $table, int $id): ?array
    {
        $allowed = ['transport_requests', 'trips', 'deliveries', 'expenses', 'purchase_requests', 'maintenance_orders', 'invoices', 'vehicles', 'drivers', 'gl_cheques', 'border_crossings', 'supplier_bills', 'disbursements', 'containers', 'price_offers', 'support_requests'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unknown workflow table.');
        }

        $statement = Database::connection()->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
