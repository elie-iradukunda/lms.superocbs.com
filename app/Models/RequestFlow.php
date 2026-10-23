<?php

declare(strict_types=1);

namespace Models;

use Support\Mailer;

/**
 * What each form sets in motion when it moves (section 8, "Chain" column):
 *
 *   F1 authorized  the trip waits for payment; paid, the driver confirms receipt
 *   F2 authorized  the fuel order goes to the station or ERI Kenya
 *   F3 authorized  a job card opens, the truck goes In garage, the parts go
 *                  to the store on an issue note (F4)
 *   F6/F7 cancelled  the tyre or crane log lines can be paid on another request
 *   F5 authorized  the purchase order goes to the supplier
 *   F8 confirmed   the delivery note is ready for invoicing; a shortage above
 *                  the customer's tolerance raises F9 for the driver
 *   F9 authorized  the deduction goes on the driver's next payroll list
 *   F13 authorized the customer's invoice is made, emailed and in their portal
 *   F11 confirmed  the receiving company's job card and issue note open
 *   F14 confirmed  the trip closes and the truck is Available
 *
 * Each area answers through a small static method on its own class, so the
 * chain itself never needs to know the details.
 */
final class RequestFlow
{
    /** form code => [class, method] per milestone */
    private const HANDLERS = [
        'authorized' => [
            'F1' => [TripLife::class, 'moneyAuthorized'],
            'F2' => [FuelOrders::class, 'orderAuthorized'],
            'F3' => [Garage::class, 'serviceAuthorized'],
            'F5' => [self::class, 'sendToSupplier'],
            'F9' => [Payroll::class, 'deductionAuthorized'],
            'F10' => [TripLife::class, 'supportAuthorized'],
            'F13' => [CustomerBilling::class, 'invoiceAuthorized'],
        ],
        'paid' => [
            'F1' => [TripLife::class, 'moneyPaid'],
        ],
        'confirmed' => [
            'F8' => [TripLife::class, 'deliveryConfirmed'],
            'F11' => [SisterRequests::class, 'confirmed'],
            'F14' => [TripLife::class, 'handoverConfirmed'],
        ],
        'cancelled' => [
            'F1' => [TripLife::class, 'moneyCancelled'],
            'F6' => [ServiceLogs::class, 'released'],
            'F7' => [ServiceLogs::class, 'released'],
        ],
        'returned' => [
            'F14' => [Handover::class, 'returned'],
        ],
    ];

    public static function authorized(array $request): void
    {
        self::dispatch('authorized', $request);
    }

    public static function paid(array $request): void
    {
        self::dispatch('paid', $request);
    }

    public static function confirmed(array $request): void
    {
        self::dispatch('confirmed', $request);
    }

    public static function cancelled(array $request): void
    {
        self::dispatch('cancelled', $request);
    }

    public static function returned(array $request): void
    {
        self::dispatch('returned', $request);
    }

    private static function dispatch(string $milestone, array $request): void
    {
        $handler = self::HANDLERS[$milestone][(string) $request['form_code']] ?? null;
        if ($handler === null || !class_exists($handler[0]) || !method_exists($handler[0], $handler[1])) {
            return;
        }
        try {
            ($handler[0] . '::' . $handler[1])($request);
        } catch (\Throwable $exception) {
            // The signature stands; what follows it is reported, not undone.
            error_log(sprintf('After %s %s: %s', $milestone, RequestForms::label($request), $exception->getMessage()));
            if (PHP_SAPI !== 'cli') {
                \Core\Flash::warning('Signed, but what follows could not be completed: ' . $exception->getMessage());
            }
        }
    }

    /**
     * An authorized order (fuel order, purchase order) goes to the supplier:
     * to their portal login and to their email, with the request attached.
     */
    public static function sendToSupplier(array $request): void
    {
        if (empty($request['supplier_id'])) {
            return;
        }
        $statement = \Core\Database::connection()->prepare('SELECT supplier_name, email FROM suppliers WHERE id = ?');
        $statement->execute([(int) $request['supplier_id']]);
        $supplier = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($supplier === false) {
            return;
        }

        $label = RequestForms::label($request);
        $users = \Core\Database::connection()->prepare("SELECT id FROM users WHERE supplier_id = ? AND status = 'active' AND deleted_at IS NULL");
        $users->execute([(int) $request['supplier_id']]);
        foreach ($users->fetchAll(\PDO::FETCH_COLUMN) as $userId) {
            Notifier::toUser((int) $userId, 'New order from ' . ($request['company_short'] ?: $request['company_name']), $label . ': ' . $request['title'], 'portal', 'info', 'form_requests', (string) $request['id']);
        }

        $email = trim((string) ($supplier['email'] ?? ''));
        if ($email !== '') {
            $pdf = RequestPdf::store((int) $request['id']);
            Mailer::send([
                'key' => 'order-' . $request['id'] . '-v' . $request['version'],
                'category' => 'supplier_order',
                'to' => $email,
                'to_name' => (string) $supplier['supplier_name'],
                'subject' => sprintf('%s from %s', $label, $request['company_name']),
                'heading' => (string) $request['title_on_pdf'],
                'lines' => [
                    sprintf('Dear %s,', $supplier['supplier_name']),
                    sprintf('Please find attached %s, authorized by %s.', $label, $request['authorized_name'] ?? 'the Managing Director'),
                    'Please confirm the quantities delivered against this order.',
                ],
                'attachments' => $pdf === null ? [] : [$pdf],
                'entity_type' => 'form_requests',
                'entity_id' => (string) $request['id'],
            ]);
        }
    }
}
