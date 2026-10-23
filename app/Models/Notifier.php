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
    /** The company the next notifications are about, when it is not the signed-in person's. */
    private static ?int $company = null;

    /**
     * Run $work with notifications addressed to one company's people: the
     * company the record belongs to, which is not always the one the person
     * acting works in (a sister-company request, a clock job with nobody signed in).
     */
    public static function forCompany(?int $companyId, callable $work): mixed
    {
        $previous = self::$company;
        self::$company = $companyId;
        try {
            return $work();
        } finally {
            self::$company = $previous;
        }
    }

    private static function companyId(): ?int
    {
        if (self::$company !== null) {
            return self::$company;
        }

        return \is_logged_in() ? \current_company_id() : null;
    }

    /** Notify one person once for a given key (a reminder that must not repeat). */
    public static function toUserOnce(string $key, ?int $userId, string $title, string $message, ?string $route = null, string $severity = 'info', ?string $entityType = null, ?string $entityId = null): void
    {
        if ($userId === null) {
            return;
        }
        try {
            $statement = Database::connection()->prepare(
                'INSERT IGNORE INTO notifications (notification_key, company_id, user_id, role_key, title, message, link_route, entity_type, entity_id, severity)
                 VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([mb_substr($key, 0, 80), self::companyId(), $userId, mb_substr($title, 0, 150), $message, $route, $entityType, $entityId, in_array($severity, ['info', 'success', 'warning', 'danger'], true) ? $severity : 'info']);
            if ($statement->rowCount() > 0) {
                self::email($key, $userId, null, $title, $message, $route, $severity, 'alert', $entityType, $entityId);
                self::whatsapp($key, $userId, null, $title, $message, $route, $entityType, $entityId);
            }
        } catch (\Throwable) {
            // A reminder is never worth failing anything for.
        }
    }

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
                    i.invoice_number, i.total_amount, i.amount_paid, i.status, i.currency,
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
        // Receipts are written in the invoice's own currency, not the base one.
        $money = static fn (float $amount): string => Currency::format($amount, (string) ($row['currency'] ?? '') ?: Currency::base());

        self::toRole(
            'finance',
            sprintf('Payment received: %s from %s', $money((float) $row['amount']), $row['customer_name'] ?: 'a customer'),
            sprintf('%s of %s against %s. %s still outstanding.',
                $row['payment_code'], $money((float) $row['amount']), $row['invoice_number'], $money($outstanding)),
            'payments',
            'success',
            'payments',
            (string) $row['payment_code']
        );

        if (trim((string) $row['email']) === '') {
            return;
        }

        $pdf = Documents::receipt($paymentId);
        \Support\Mailer::send([
            'key' => 'payment-receipt-' . $paymentId,
            'category' => 'invoice',
            'to' => trim((string) $row['email']),
            'to_name' => trim((string) ($row['contact_name'] ?: $row['customer_name'])),
            'subject' => sprintf('Receipt %s: payment of %s received', $row['payment_code'], $money((float) $row['amount'])),
            'heading' => 'Receipt ' . $row['payment_code'],
            'tone' => 'success',
            'preheader' => sprintf('We received %s against invoice %s. Thank you.', $money((float) $row['amount']), $row['invoice_number']),
            'lines' => [
                sprintf('Dear %s,', $row['contact_name'] ?: $row['customer_name']),
                sprintf(
                    'Thank you. We confirm receipt of your payment against invoice %s.%s',
                    $row['invoice_number'],
                    $pdf !== null ? ' Your receipt is attached as a PDF.' : ' This message serves as your receipt.'
                ),
            ],
            'items' => [
                'title' => 'Payment received',
                'columns' => ['detail' => 'Detail', 'value' => 'Amount'],
                'numeric' => ['value'],
                'rows' => [
                    ['detail' => 'Invoice total', 'value' => $money((float) $row['total_amount'])],
                    ['detail' => 'Paid before this receipt', 'value' => $money(max(0.0, (float) $row['amount_paid'] - (float) $row['amount']))],
                    ['detail' => 'This payment', 'value' => $money((float) $row['amount'])],
                ],
                'totals' => [$outstanding > 0 ? 'Still outstanding' : 'Balance' => $money($outstanding)],
            ],
            'facts' => [
                'Receipt number' => (string) $row['payment_code'],
                'Invoice number' => (string) $row['invoice_number'],
                'Date received' => \human_date((string) $row['paid_at']),
                'Paid by' => PaymentMethod::name((string) $row['method']),
                'Your reference' => (string) ($row['reference'] ?: '—'),
            ],
            'closing' => [
                $outstanding > 0
                    ? sprintf('%s remains outstanding on this invoice. Please quote the invoice number on your next payment.', $money($outstanding))
                    : 'This invoice is now settled in full. No further action is needed.',
                'If any detail above is not as you expected, reply to this message and we will check it.',
            ],
            'attachments' => $pdf !== null ? [$pdf] : [],
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

    /**
     * Reads one update for this person. A personal update is marked on its own
     * row; an update for a whole role is marked read for this person only.
     */
    public static function markRead(int $userId, string $roleKey, string $notificationKey): void
    {
        try {
            $db = Database::connection();
            $db->prepare(
                'UPDATE notifications
                    SET is_read = 1, read_at = NOW()
                  WHERE notification_key = ? AND user_id = ?'
            )->execute([$notificationKey, $userId]);

            $db->prepare(
                'INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
                 SELECT id, ?, NOW() FROM notifications
                  WHERE notification_key = ? AND user_id IS NULL AND (role_key = ? OR role_key IS NULL)'
            )->execute([$userId, $notificationKey, $roleKey]);
        } catch (\Throwable) {
            // Never break navigation because a read receipt failed.
        }
    }

    public static function markAllRead(int $userId, string $roleKey): int
    {
        try {
            $db = Database::connection();
            $own = $db->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0 AND user_id = ?');
            $own->execute([$userId]);

            $shared = $db->prepare(
                'INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
                 SELECT n.id, ?, NOW() FROM notifications n
                  WHERE n.user_id IS NULL AND n.is_read = 0 AND (n.role_key = ? OR n.role_key IS NULL)'
            );
            $shared->execute([$userId, $roleKey]);

            return $own->rowCount() + $shared->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Raises an alert once per key, for every role it concerns, with email the
     * first time. For alerts that come from the clock rather than an action.
     *
     * @param string|list<string> $roles
     */
    public static function raiseOnce(string $key, string|array $roles, string $title, string $message, ?string $route, string $severity, ?string $entityType = null, ?string $entityId = null): void
    {
        try {
            self::once($key, $roles, $title, $message, $route, $severity, $entityType, $entityId);
        } catch (\Throwable) {
            // An alert is a convenience; it never blocks anything.
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
        // Raised on sign-in and by the clock: the emails are held back and sent
        // in a batch, so nobody waits on the mail provider to open a page.
        $wasDeferring = Mailer::defer(true);
        try {
            $db = Database::connection();
            // Documents at 30, 14 and 3 days before expiry, and services at the
            // warning and due marks (sections 5 and 6).
            Fleet::alerts();

            // Items at or below their minimum, to that company's store (section 12).
            Store::lowStockAlerts();

            $db->exec("UPDATE invoices SET status = 'overdue' WHERE deleted_at IS NULL AND status IN ('issued','partially_paid') AND due_date < CURDATE()");
            foreach ($db->query("SELECT invoice_number, due_date, currency, (total_amount - amount_paid) AS balance FROM invoices WHERE deleted_at IS NULL AND status = 'overdue'")->fetchAll() as $invoice) {
                self::once(
                    'overdue-' . md5((string) $invoice['invoice_number']),
                    ['finance', 'super_admin', 'management'],
                    'Invoice overdue',
                    sprintf('%s was due on %s with %s outstanding.', $invoice['invoice_number'], self::when((string) $invoice['due_date']), Currency::format((float) $invoice['balance'], (string) ($invoice['currency'] ?: Currency::base()))),
                    'invoices',
                    'danger',
                    'invoices',
                    (string) $invoice['invoice_number']
                );
            }

            // A box out past its free days costs money every day it is not back.
            Containers::alerts(static function (string $key, string $title, string $message, string $severity): void {
                self::once($key, ['logistics_manager', 'super_admin', 'finance'], $title, $message, 'containers', $severity);
            });

            // A clearance file standing still, and a truck that went quiet.
            Clearance::stuckAlerts([self::class, 'raiseOnce']);
            Tracking::silenceAlerts([self::class, 'raiseOnce']);
        } catch (\Throwable) {
            // Alerts are a convenience; a failure here must not block the dashboard.
        } finally {
            Mailer::defer($wasDeferring);
        }
    }

    /** "12 Oct 2026 (in 5 days)": the date, and how long is left or how long ago it passed. */
    private static function when(string $date): string
    {
        $days = (int) floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
        $left = match (true) {
            $days === 0 => 'today',
            $days === 1 => 'tomorrow',
            $days === -1 => 'yesterday',
            $days > 1 => 'in ' . $days . ' days',
            default => abs($days) . ' days ago',
        };

        return \human_date($date) . ' (' . $left . ')';
    }

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
    private static function once(string $key, string|array $roles, string $title, string $message, ?string $route, string $severity, ?string $entityType = null, ?string $entityId = null): void
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO notifications (notification_key, company_id, user_id, role_key, title, message, link_route, entity_type, entity_id, severity)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach (array_values(array_unique((array) $roles)) as $index => $roleKey) {
            $roleKey = (string) $roleKey;
            $rowKey = $index === 0 ? $key : $key . '-' . $roleKey;
            $statement->execute([mb_substr($rowKey, 0, 80), self::companyId(), $roleKey, $title, $message, $route, $entityType, $entityId, $severity]);

            // Only email an alert the first time it is raised, which is what the
            // INSERT IGNORE above has just told us.
            if ($statement->rowCount() > 0 && Settings::int('email_alerts_to_roles', 1) === 1) {
                self::email($rowKey, null, $roleKey, $title, $message, $route, $severity, 'alert', $entityType, $entityId);
            }
            if ($statement->rowCount() > 0) {
                self::whatsapp($rowKey, null, $roleKey, $title, $message, $route, $entityType, $entityId);
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

            [$code, $path] = self::reference($route, $entityType, $entityId);
            // "A · B · C" on the bell reads as three lines in a letter.
            $lines = array_values(array_filter(array_map('trim', explode(' · ', $message)), static fn (string $l): bool => $l !== ''));

            // A request carries its own document. When the letter is about a
            // request, the PDF as it stands — with the signatures gathered so
            // far — goes out attached, not only as a link. Built once for the
            // whole recipient list.
            $attachments = [];
            if ($entityType === 'form_requests' && $entityId !== null && $entityId !== '') {
                $document = RequestPdf::store((int) $entityId);
                if ($document !== null) {
                    $attachments[] = $document;
                }
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
                    'subject' => $code !== '' && !str_contains($title, $code) ? $title . ': ' . $code : $title,
                    'heading' => $title,
                    'tone' => in_array($severity, ['danger', 'warning', 'success'], true) ? $severity : '',
                    'lines' => $lines !== [] ? $lines : [$message],
                    'facts' => $code !== '' ? ['Reference' => $code] : [],
                    'action' => $path === null ? null : [
                        'label' => $code !== '' ? 'Open ' . $code : 'Open in LMS',
                        'path' => $path,
                    ],
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'attachments' => $attachments,
                ]);
            }
        } catch (\Throwable) {
            // A notification is never worth failing the user's action for, and
            // an email even less so.
        }
    }

    /**
     * The record a notification is about, by the code people know it by, and the
     * page that opens it: the record itself rather than the whole list.
     *
     * @return array{0: string, 1: ?string}
     */
    private static function reference(?string $route, ?string $entityType, ?string $entityId): array
    {
        $entityId = trim((string) $entityId);
        $route = trim((string) $route);
        $path = $route === '' ? null : $route;
        if ($entityId === '') {
            return ['', $path];
        }

        if (!ctype_digit($entityId)) {
            return [$entityId, $route === '' || $route === 'my-trips' ? $path : $route . '?' . http_build_query(['q' => $entityId], '', '&', PHP_QUERY_RFC3986)];
        }

        // A bare id: look up its code in the module it belongs to.
        $code = '';
        foreach (array_unique(array_filter([(string) $entityType, $route])) as $module) {
            try {
                if (!Schema::has($module)) {
                    continue;
                }
                $definition = Schema::get($module);
                if (empty($definition['table']) || empty($definition['code'])) {
                    continue;
                }
                $statement = Database::connection()->prepare(sprintf('SELECT `%s` FROM `%s` WHERE id = ?', $definition['code'], $definition['table']));
                $statement->execute([(int) $entityId]);
                $code = (string) ($statement->fetchColumn() ?: '');
                if ($code !== '') {
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [$code, $route === '' || $route === 'my-trips' ? $path : $route . '/' . $entityId];
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

        // People of the company the update is about, and whoever sees the whole group.
        $company = self::companyId();
        $statement = Database::connection()->prepare(
            "SELECT u.id, u.email, u.full_name
               FROM users u
               INNER JOIN roles r ON r.id = u.role_id
              WHERE r.role_key = ? AND u.status = 'active' AND u.deleted_at IS NULL AND u.notify_by_email = 1
                AND (? IS NULL OR u.company_id = ? OR u.company_id IS NULL
                     OR EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = u.role_id AND rp.permission_key = 'all_companies' AND rp.can_view = 1))"
        );
        $statement->execute([$roleKey, $company, $company]);

        return $statement->fetchAll();
    }

    /** On the bell only, no email: for the small updates of a trip on the road. */
    public static function bellToRole(string $roleKey, string $title, string $message, ?string $route = null, string $severity = 'info', ?string $entityType = null, ?string $entityId = null): void
    {
        self::insert(null, $roleKey, $title, $message, $route, $severity, $entityType, $entityId, false);
    }

    private static function insert(?int $userId, ?string $roleKey, string $title, string $message, ?string $route, string $severity, ?string $entityType, ?string $entityId, bool $email = true): void
    {
        $key = uniqid('n-', true);

        try {
            $severity = in_array($severity, ['info', 'success', 'warning', 'danger'], true) ? $severity : 'info';
            $statement = Database::connection()->prepare(
                'INSERT INTO notifications (notification_key, company_id, user_id, role_key, title, message, link_route, entity_type, entity_id, severity)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $key,
                self::companyId(),
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

        if ($email) {
            self::email($key, $userId, $roleKey, $title, $message, $route, $severity, 'notification', $entityType, $entityId);
            self::whatsapp($key, $userId, $roleKey, $title, $message, $route, $entityType, $entityId);
        }
    }

    /**
     * The same update as a short WhatsApp message with a link (section 19),
     * to each person who chose that kind of update for WhatsApp and has a
     * number. Sent from the company's WhatsApp Business number.
     */
    private static function whatsapp(string $key, ?int $userId, ?string $roleKey, string $title, string $message, ?string $route, ?string $entityType, ?string $entityId): void
    {
        try {
            $topic = \Support\WhatsApp::topicFor($route, $title);
            $company = self::companyId();
            if ($userId !== null) {
                $statement = Database::connection()->prepare(
                    "SELECT u.id, COALESCE(NULLIF(u.whatsapp_number, ''), u.phone) AS number, u.whatsapp_topics, r.role_key
                       FROM users u INNER JOIN roles r ON r.id = u.role_id
                      WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL"
                );
                $statement->execute([$userId]);
            } elseif ($roleKey !== null && $roleKey !== '') {
                $statement = Database::connection()->prepare(
                    "SELECT u.id, COALESCE(NULLIF(u.whatsapp_number, ''), u.phone) AS number, u.whatsapp_topics, r.role_key
                       FROM users u INNER JOIN roles r ON r.id = u.role_id
                      WHERE r.role_key = ? AND u.status = 'active' AND u.deleted_at IS NULL
                        AND (? IS NULL OR u.company_id = ? OR u.company_id IS NULL
                             OR EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = u.role_id AND rp.permission_key = 'all_companies' AND rp.can_view = 1))"
                );
                $statement->execute([$roleKey, $company, $company]);
            } else {
                return;
            }
            [, $path] = self::reference($route, $entityType, $entityId);
            $link = $path !== null ? Mailer::link($path) : null;
            foreach ($statement->fetchAll() as $person) {
                if (trim((string) $person['number']) === '' || !in_array($topic, \Support\WhatsApp::topicsOf($person['whatsapp_topics'], (string) $person['role_key']), true)) {
                    continue;
                }
                \Support\WhatsApp::send($key . '-w' . $person['id'], (int) $person['id'], (string) $person['number'], $title, $message, $link, $topic, $company);
            }
        } catch (\Throwable) {
            // WhatsApp never stands in the way of the action that caused it.
        }
    }
}
