<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use Support\Mailer;

/**
 * Writes notifications when something actually happens, and lets a user clear
 * them. Before this existed the bell only ever showed seeded rows and the unread
 * count could never go down.
 */
final class Notifier
{
    /** Notify every user holding a role. */
    public static function toRole(string $roleKey, string $title, string $message, ?string $route = null, string $severity = 'info', ?string $entityType = null, ?string $entityId = null): void
    {
        self::insert(null, $roleKey, $title, $message, $route, $severity, $entityType, $entityId);
    }

    /** Notify one person. */
    public static function toUser(?int $userId, string $title, string $message, ?string $route = null, string $severity = 'info', ?string $entityType = null, ?string $entityId = null): void
    {
        if ($userId === null) {
            return;
        }

        self::insert($userId, null, $title, $message, $route, $severity, $entityType, $entityId);
    }

    /**
     * Tells finance, and the customer, that a payment landed.
     *
     * The receipt goes to the address on the customer record. A customer with no
     * address simply does not get one; the bell entry still records the payment.
     */
    public static function paymentReceived(int $paymentId): void
    {
        $statement = \Core\Database::connection()->prepare(
            'SELECT p.payment_code, p.amount, p.method, p.reference, p.paid_at,
                    i.invoice_number, i.total_amount, i.amount_paid, i.status,
                    c.customer_name, c.contact_name, c.email
               FROM payments p
               INNER JOIN invoices i ON i.id = p.invoice_id
               LEFT JOIN customers c ON c.id = i.customer_id
              WHERE p.id = ?'
        );
        $statement->execute([$paymentId]);
        $row = $statement->fetch();

        if ($row === false) {
            return;
        }

        $outstanding = max(0.0, (float) $row['total_amount'] - (float) $row['amount_paid']);

        self::toRole(
            'finance',
            'Payment received',
            sprintf('%s of %s against %s. %s still outstanding.',
                $row['payment_code'], Settings::money((float) $row['amount']), $row['invoice_number'], Settings::money($outstanding)),
            'payments',
            'success',
            'payments',
            (string) $row['payment_code']
        );

        if (trim((string) $row['email']) === '') {
            return;
        }

        \Support\Mailer::send([
            'key' => 'payment-receipt-' . $paymentId,
            'category' => 'invoice',
            'to' => trim((string) $row['email']),
            'to_name' => trim((string) ($row['contact_name'] ?: $row['customer_name'])),
            'subject' => sprintf('Payment received — %s', $row['invoice_number']),
            'heading' => 'Receipt ' . $row['payment_code'],
            'lines' => [
                sprintf('Dear %s,', $row['contact_name'] ?: $row['customer_name']),
                sprintf(
                    'Thank you. We confirm receipt of your payment against invoice %s. This message serves as your receipt.',
                    $row['invoice_number']
                ),
            ],
            'items' => [
                'title' => 'Payment received',
                'columns' => ['detail' => 'Detail', 'value' => 'Amount'],
                'numeric' => ['value'],
                'rows' => [
                    ['detail' => 'Invoice total', 'value' => Settings::money((float) $row['total_amount'])],
                    ['detail' => 'Paid before this receipt', 'value' => Settings::money(max(0.0, (float) $row['amount_paid'] - (float) $row['amount']))],
                    ['detail' => 'This payment', 'value' => Settings::money((float) $row['amount'])],
                ],
                'totals' => [$outstanding > 0 ? 'Still outstanding' : 'Balance' => Settings::money($outstanding)],
            ],
            'facts' => [
                'Receipt number' => (string) $row['payment_code'],
                'Invoice number' => (string) $row['invoice_number'],
                'Date received' => date('Y-m-d', (int) strtotime((string) $row['paid_at'])),
                'Paid by' => PaymentMethod::name((string) $row['method']),
                'Your reference' => (string) ($row['reference'] ?: '—'),
            ],
            'closing' => [
                $outstanding > 0
                    ? sprintf('%s remains outstanding on this invoice. Please quote the invoice number on your next payment.', Settings::money($outstanding))
                    : 'This invoice is now settled in full. No further action is needed.',
                'If any detail above is not as you expected, reply to this message and we will check it.',
            ],
            'entity_type' => 'payments',
            'entity_id' => (string) $row['payment_code'],
        ]);
    }

    /** @param list<string> $roleKeys */
    public static function toRoles(array $roleKeys, string $title, string $message, ?string $route = null, string $severity = 'info', ?string $entityType = null, ?string $entityId = null): void
    {
        foreach (array_unique($roleKeys) as $roleKey) {
            self::toRole($roleKey, $title, $message, $route, $severity, $entityType, $entityId);
        }
    }

    public static function markRead(int $userId, string $roleKey, string $notificationKey): void
    {
        try {
            $statement = Database::connection()->prepare(
                'UPDATE notifications
                    SET is_read = 1, read_at = NOW()
                  WHERE notification_key = ?
                    AND (user_id = ? OR (user_id IS NULL AND (role_key = ? OR role_key IS NULL)))'
            );
            $statement->execute([$notificationKey, $userId, $roleKey]);
        } catch (\Throwable) {
            // Never break navigation because a read receipt failed.
        }
    }

    public static function markAllRead(int $userId, string $roleKey): int
    {
        try {
            $statement = Database::connection()->prepare(
                'UPDATE notifications
                    SET is_read = 1, read_at = NOW()
                  WHERE is_read = 0
                    AND (user_id = ? OR (user_id IS NULL AND (role_key = ? OR role_key IS NULL)))'
            );
            $statement->execute([$userId, $roleKey]);

            return $statement->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Operational alerts that are true right now rather than stored: licences and
     * vehicle documents about to expire, services due, stock below minimum and
     * invoices past their due date. Run on login so the bell is useful from the
     * first page.
     */
    public static function refreshOperationalAlerts(): void
    {
        try {
            $db = Database::connection();
            $licenceDays = Settings::int('licence_alert_days', 90);
            $documentDays = Settings::int('document_alert_days', 30);
            $serviceDays = Settings::int('service_alert_days', 14);

            // Paperwork that lapses grounds a truck, so it reaches the people who
            // renew it and the people who answer for the fleet standing idle.
            $fleetAndOwners = ['fleet_manager', 'super_admin', 'management'];

            $expiringLicences = $db->prepare(
                'SELECT full_name, license_expiry FROM drivers
                  WHERE deleted_at IS NULL AND license_expiry IS NOT NULL
                    AND license_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)'
            );
            $expiringLicences->execute([$licenceDays]);
            foreach ($expiringLicences->fetchAll() as $driver) {
                self::once(
                    'licence-' . md5($driver['full_name'] . $driver['license_expiry']),
                    $fleetAndOwners,
                    'Driver licence expiring',
                    sprintf('%s has a licence expiring on %s.', $driver['full_name'], $driver['license_expiry']),
                    'drivers',
                    'warning'
                );
            }

            $expiringDocuments = $db->prepare(
                'SELECT vd.document_code, vd.document_type, vd.expires_on, v.plate_number
                   FROM vehicle_documents vd
                   INNER JOIN vehicles v ON v.id = vd.vehicle_id
                  WHERE vd.deleted_at IS NULL AND vd.status <> "cancelled"
                    AND vd.expires_on <= DATE_ADD(CURDATE(), INTERVAL ? DAY)'
            );
            $expiringDocuments->execute([$documentDays]);
            foreach ($expiringDocuments->fetchAll() as $document) {
                self::once(
                    'vehdoc-' . md5((string) $document['document_code'] . $document['expires_on']),
                    $fleetAndOwners,
                    'Vehicle document expiring',
                    sprintf('%s for %s expires on %s.', ucfirst(str_replace('_', ' ', (string) $document['document_type'])), $document['plate_number'], $document['expires_on']),
                    'vehicle_documents',
                    'danger'
                );
            }

            $dueService = $db->prepare(
                'SELECT plate_number, next_service_date FROM vehicles
                  WHERE deleted_at IS NULL AND next_service_date IS NOT NULL
                    AND next_service_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)'
            );
            $dueService->execute([$serviceDays]);
            foreach ($dueService->fetchAll() as $vehicle) {
                self::once(
                    'service-' . md5($vehicle['plate_number'] . $vehicle['next_service_date']),
                    $fleetAndOwners,
                    'Service due',
                    sprintf('%s is due for service on %s.', $vehicle['plate_number'], $vehicle['next_service_date']),
                    'maintenance',
                    'warning'
                );
            }

            foreach ($db->query('SELECT sku, item_name, quantity, minimum_level FROM inventory_items WHERE deleted_at IS NULL AND quantity <= minimum_level')->fetchAll() as $item) {
                self::once(
                    'stock-' . md5((string) $item['sku'] . (string) $item['quantity']),
                    ['warehouse_manager', 'super_admin'],
                    'Stock below minimum',
                    sprintf('%s is at %s against a minimum of %s.', $item['item_name'], rtrim(rtrim((string) $item['quantity'], '0'), '.'), rtrim(rtrim((string) $item['minimum_level'], '0'), '.')),
                    'warehouse',
                    'danger'
                );
            }

            $db->exec("UPDATE invoices SET status = 'overdue' WHERE deleted_at IS NULL AND status IN ('issued','partially_paid') AND due_date < CURDATE()");
            foreach ($db->query("SELECT invoice_number, due_date, (total_amount - amount_paid) AS balance FROM invoices WHERE deleted_at IS NULL AND status = 'overdue'")->fetchAll() as $invoice) {
                self::once(
                    'overdue-' . md5((string) $invoice['invoice_number']),
                    ['finance', 'super_admin', 'management'],
                    'Invoice overdue',
                    sprintf('%s was due on %s with %s outstanding.', $invoice['invoice_number'], $invoice['due_date'], Settings::money((float) $invoice['balance'])),
                    'invoices',
                    'danger'
                );
            }
        } catch (\Throwable) {
            // Alerts are a convenience; a failure here must not block the dashboard.
        }
    }

    /** Inserts only if that key has never been raised, so an alert is not repeated every page load. */
    /**
     * Raises an alert once, for everyone it concerns.
     *
     * A lapsed licence stops a truck and a lapsed insurance certificate is a
     * fine at the roadside, so the person who renews it is not the only person
     * who needs the warning. Each role gets its own row, keyed so the same
     * expiry is never raised at the same person twice.
     *
     * @param string|list<string> $roles
     */
    private static function once(string $key, string|array $roles, string $title, string $message, ?string $route, string $severity): void
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO notifications (notification_key, user_id, role_key, title, message, link_route, severity)
             VALUES (?, NULL, ?, ?, ?, ?, ?)'
        );

        foreach (array_unique((array) $roles) as $index => $roleKey) {
            $roleKey = (string) $roleKey;
            $rowKey = $index === 0 ? $key : $key . '-' . $roleKey;
            $statement->execute([$rowKey, $roleKey, $title, $message, $route, $severity]);

            // Only email an alert the first time it is raised, which is what the
            // INSERT IGNORE above has just told us.
            if ($statement->rowCount() > 0 && Settings::int('email_alerts_to_roles', 1) === 1) {
                self::email($rowKey, null, $roleKey, $title, $message, $route, $severity, 'alert', null, null);
            }
        }
    }

    // ----------------------------------------------------------------- email

    /**
     * Sends the same update by email to whoever the notification was for.
     *
     * The bell tells someone who is already looking at the screen. Email reaches
     * the driver on the road and the accountant who has not signed in today, so
     * every notification goes both ways unless the person has opted out.
     */
    private static function email(
        string $key,
        ?int $userId,
        ?string $roleKey,
        string $title,
        string $message,
        ?string $route,
        string $severity,
        string $category,
        ?string $entityType,
        ?string $entityId
    ): void {
        try {
            if (!Mailer::enabled() || !Mailer::passesThreshold($severity)) {
                return;
            }

            foreach (self::recipients($userId, $roleKey) as $person) {
                Mailer::send([
                    // One message per person per notification, so a role of six
                    // people gets six letters and no duplicates.
                    'key' => mb_substr($key . '-u' . $person['id'], 0, 120),
                    'category' => $category,
                    'to' => $person['email'],
                    'to_name' => $person['full_name'],
                    'user_id' => (int) $person['id'],
                    'subject' => $title,
                    'heading' => $title,
                    'lines' => [$message],
                    'facts' => array_filter([
                        'Reference' => $entityId,
                        'Area' => $entityType === null ? null : ucfirst(str_replace('_', ' ', $entityType)),
                    ]),
                    'action' => $route === null || $route === '' ? null : [
                        'label' => 'Open it in LMS',
                        'path' => $route,
                    ],
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ]);
            }
        } catch (\Throwable) {
            // A notification is never worth failing the user's action for, and
            // an email even less so.
        }
    }

    /**
     * Who should receive this: one person, or everyone holding a role.
     *
     * Inactive and deleted accounts are left out, as is anyone who has turned
     * email off for themselves.
     *
     * @return list<array{id: int, email: string, full_name: string}>
     */
    private static function recipients(?int $userId, ?string $roleKey): array
    {
        if ($userId !== null) {
            $statement = Database::connection()->prepare(
                "SELECT id, email, full_name FROM users
                  WHERE id = ? AND status = 'active' AND deleted_at IS NULL AND notify_by_email = 1"
            );
            $statement->execute([$userId]);

            return $statement->fetchAll();
        }

        if ($roleKey === null || $roleKey === '') {
            return [];
        }

        $statement = Database::connection()->prepare(
            "SELECT u.id, u.email, u.full_name
               FROM users u
               INNER JOIN roles r ON r.id = u.role_id
              WHERE r.role_key = ? AND u.status = 'active' AND u.deleted_at IS NULL AND u.notify_by_email = 1"
        );
        $statement->execute([$roleKey]);

        return $statement->fetchAll();
    }

    private static function insert(?int $userId, ?string $roleKey, string $title, string $message, ?string $route, string $severity, ?string $entityType, ?string $entityId): void
    {
        $key = uniqid('n-', true);

        try {
            $severity = in_array($severity, ['info', 'success', 'warning', 'danger'], true) ? $severity : 'info';
            $statement = Database::connection()->prepare(
                'INSERT INTO notifications (notification_key, user_id, role_key, title, message, link_route, entity_type, entity_id, severity)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $key,
                $userId,
                $roleKey,
                mb_substr($title, 0, 150),
                $message,
                $route,
                $entityType,
                $entityId,
                $severity,
            ]);
        } catch (\Throwable) {
            // A notification is never worth failing the user's action for.
            return;
        }

        self::email($key, $userId, $roleKey, $title, $message, $route, $severity, 'notification', $entityType, $entityId);
    }
}
