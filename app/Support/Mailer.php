<?php

declare(strict_types=1);

namespace Support;

use Core\Database;
use Models\Settings;
use PDO;

/**
 * Email, through Resend's HTTP API.
 *
 * WRITTEN DOWN BEFORE IT IS SENT
 * Every message is recorded in `email_outbox` first and only then handed to the
 * provider. If mail is switched off, if the key is missing, if Resend is
 * unreachable — the message is still on record with the reason it did not go,
 * and Administration → Email outbox can retry it. Nothing is silently lost, and
 * nobody has to guess whether a notification was actually delivered.
 *
 * IT NEVER BREAKS THE WORK
 * Sending is the last thing that happens after an approval or a dispatch, and a
 * mail failure must not undo it. Every path here catches its own errors.
 *
 * A message is described, not written as HTML by the caller:
 *
 *   Mailer::send([
 *       'key'      => 'expense-approved-42',      // makes it idempotent
 *       'category' => 'notification',
 *       'to'       => 'driver@company.rw',
 *       'subject'  => 'Your expense was approved',
 *       'heading'  => 'Expense EXP-2026-0001 approved',
 *       'lines'    => ['Emmanuel approved your night-out allowance.'],
 *       'facts'    => ['Amount' => 'RWF 60,000', 'Trip' => 'TRP-2026-0001'],
 *       'action'   => ['label' => 'Open the expense', 'path' => 'expenses/12'],
 *   ]);
 */
final class Mailer
{
    private const MAX_ATTEMPTS = 3;

    /** Severities in order, so a threshold setting can filter the quiet ones out. */
    private const SEVERITY_ORDER = ['info' => 1, 'success' => 2, 'warning' => 3, 'danger' => 4];

    /** While on, messages are recorded and left for flushDeferred() instead of posted now. */
    private static bool $defer = false;

    private static function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Holds back sending for work the clock does (alerts on sign-in, approval
     * reminders), so nobody waits on the mail provider to open a page. Returns
     * the previous setting, to put back.
     */
    public static function defer(bool $on): bool
    {
        $was = self::$defer;
        self::$defer = $on;

        return $was;
    }

    /**
     * Sends what was held back: only messages waiting since the last day, so a
     * pile left while email was switched off is never sent by surprise.
     */
    public static function flushDeferred(int $limit = 20): int
    {
        if (!self::mayDeliver()) {
            return 0;
        }
        $statement = self::db()->prepare(
            "SELECT id FROM email_outbox
              WHERE status = 'queued' AND attempts < ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
              ORDER BY id
              LIMIT " . max(1, $limit)
        );
        $statement->execute([self::MAX_ATTEMPTS]);
        $sent = 0;
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $sent += self::deliver((int) $id) ? 1 : 0;
        }

        return $sent;
    }

    // ----------------------------------------------------------- can we send

    /** Is email switched on, configured, and allowed in this context? */
    public static function enabled(): bool
    {
        if (\config('mail.api_key', '') === '') {
            return false;
        }

        try {
            return Settings::int('email_enabled', 1) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * A console run records its messages but does not post them, so a test
     * suite or a migration can never mail real people by accident.
     */
    private static function mayDeliver(): bool
    {
        if (!self::enabled()) {
            return false;
        }

        return PHP_SAPI !== 'cli' || (bool) \config('mail.send_in_cli', false);
    }

    /** Is this severity worth an email, given the configured threshold? */
    public static function passesThreshold(string $severity): bool
    {
        $minimum = strtolower(Settings::get('email_min_severity', 'info'));

        return (self::SEVERITY_ORDER[strtolower($severity)] ?? 1)
            >= (self::SEVERITY_ORDER[$minimum] ?? 1);
    }

    // --------------------------------------------------------------- sending

    /**
     * Records a message and, when it may, sends it.
     *
     * @return array{queued: bool, sent: bool, id: ?int, reason: string}
     */
    public static function send(array $message): array
    {
        try {
            $id = self::queue($message);
        } catch (\Throwable $exception) {
            return ['queued' => false, 'sent' => false, 'id' => null, 'reason' => $exception->getMessage()];
        }

        if ($id === null) {
            return ['queued' => false, 'sent' => false, 'id' => null, 'reason' => 'Already recorded, or no address to send to.'];
        }

        if (!self::mayDeliver()) {
            self::mark($id, 'skipped', null, self::enabled()
                ? 'Not sent from the console; the message is queued.'
                : 'Email is switched off or no API key is configured.');

            return ['queued' => true, 'sent' => false, 'id' => $id, 'reason' => 'Queued but not sent.'];
        }

        if (self::$defer || !empty($message['defer'])) {
            return ['queued' => true, 'sent' => false, 'id' => $id, 'reason' => 'Queued; it is sent within a few minutes.'];
        }

        $sent = self::deliver($id);

        return ['queued' => true, 'sent' => $sent, 'id' => $id, 'reason' => $sent ? 'Sent.' : 'Queued; the provider refused it.'];
    }

    /**
     * Writes the message to the outbox and returns its id, or null when there is
     * nothing to send or the same key has already been recorded.
     */
    public static function queue(array $message): ?int
    {
        $to = trim((string) ($message['to'] ?? ''));
        // A driver without email has a placeholder address on the reserved
        // .invalid domain: nothing is ever sent to it.
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false || str_ends_with(strtolower($to), '.invalid')) {
            return null;
        }

        // While testing, everything goes to one real mailbox rather than to the
        // seeded addresses, which nobody reads.
        $redirect = trim((string) \config('mail.redirect_all_to', ''));
        $realRecipient = $to;
        if ($redirect !== '') {
            $to = $redirect;
        }

        $key = trim((string) ($message['key'] ?? '')) ?: 'msg-' . bin2hex(random_bytes(8));
        $subject = mb_substr(trim((string) ($message['subject'] ?? 'Update from LMS')), 0, 200);

        // A customer, supplier or agent is written to as the company; staff get
        // the system's own frame. Outsiders' replies go to the company mailbox.
        $message['audience'] ??= self::isStaff($realRecipient) ? 'staff' : 'external';
        if ($message['audience'] === 'external' && trim((string) ($message['reply_to'] ?? '')) === '') {
            $message['reply_to'] = trim(\Models\Settings::get('company_email', ''));
        }

        $html = $message['html'] ?? self::render($message, $realRecipient, $redirect !== '');
        $text = $message['text'] ?? self::plain($message);

        // Files go by their path under the uploads folder, so a retry from the
        // outbox attaches the same file again.
        $attachments = [];
        foreach ((array) ($message['attachments'] ?? []) as $file) {
            $relative = self::uploadsRelative((string) ($file['path'] ?? ''));
            if ($relative !== null) {
                $attachments[] = ['filename' => (string) ($file['filename'] ?? basename($relative)), 'path' => $relative];
            }
        }

        // People copied in, and where replies go: the person who wrote it, when
        // the email asks for an answer (the agent replying to the logistics
        // manager). In test mode nobody else is copied.
        $cc = [];
        foreach ((array) ($message['cc'] ?? []) as $address) {
            $address = trim((string) $address);
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false && strcasecmp($address, $realRecipient) !== 0) {
                $cc[] = $address;
            }
        }
        $cc = $redirect !== '' ? [] : array_values(array_unique($cc));
        $replyTo = trim((string) ($message['reply_to'] ?? ''));
        $replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false ? $replyTo : '';

        if ($attachments !== [] || $cc !== [] || $replyTo !== '') {
            $statement = self::db()->prepare(
                'INSERT IGNORE INTO email_outbox
                    (message_key, category, to_email, to_name, cc_emails, reply_to, user_id, subject, body_html, body_text, attachments, entity_type, entity_id, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "queued")'
            );
            $statement->execute([
                mb_substr($key, 0, 120),
                self::category((string) ($message['category'] ?? 'notification')),
                mb_substr($to, 0, 190),
                $message['to_name'] ?? null,
                $cc !== [] ? json_encode($cc) : null,
                $replyTo !== '' ? $replyTo : null,
                isset($message['user_id']) ? (int) $message['user_id'] : null,
                $subject,
                $html,
                $text,
                $attachments !== [] ? json_encode($attachments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                $message['entity_type'] ?? null,
                $message['entity_id'] ?? null,
            ]);

            return $statement->rowCount() === 0 ? null : (int) self::db()->lastInsertId();
        }

        $statement = self::db()->prepare(
            'INSERT IGNORE INTO email_outbox
                (message_key, category, to_email, to_name, user_id, subject, body_html, body_text, entity_type, entity_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "queued")'
        );
        $statement->execute([
            mb_substr($key, 0, 120),
            self::category((string) ($message['category'] ?? 'notification')),
            mb_substr($to, 0, 190),
            $message['to_name'] ?? null,
            isset($message['user_id']) ? (int) $message['user_id'] : null,
            $subject,
            $html,
            $text,
            $message['entity_type'] ?? null,
            $message['entity_id'] ?? null,
        ]);

        // INSERT IGNORE returns 0 rows when the key was already recorded, which
        // is how the same alert raised twice is sent once.
        return $statement->rowCount() === 0 ? null : (int) self::db()->lastInsertId();
    }

    /** Hands one queued message to Resend and records what came back. */
    public static function deliver(int $id): bool
    {
        $row = self::row($id);
        if ($row === null || $row['status'] === 'sent') {
            return false;
        }

        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            self::mark($id, 'failed', null, 'Given up after ' . self::MAX_ATTEMPTS . ' attempts.');
            return false;
        }

        if (!self::enabled()) {
            self::mark($id, 'skipped', null, 'Email is switched off or no API key is configured.');
            return false;
        }

        $payload = [
            'from' => sprintf('%s <%s>', \config('mail.from_name', 'LMS'), \config('mail.from_email', '')),
            'to' => [$row['to_email']],
            'subject' => $row['subject'],
            'html' => $row['body_html'],
        ];

        if (($row['body_text'] ?? '') !== '') {
            $payload['text'] = $row['body_text'];
        }

        foreach ((array) json_decode((string) ($row['attachments'] ?? ''), true) as $file) {
            $path = self::uploadsRoot() . '/' . ltrim((string) ($file['path'] ?? ''), '/');
            $bytes = is_file($path) ? @file_get_contents($path) : false;
            if ($bytes === false) {
                self::mark($id, 'failed', null, 'The attachment ' . ($file['filename'] ?? '') . ' is missing from the server.');
                return false;
            }
            $payload['attachments'][] = ['filename' => (string) $file['filename'], 'content' => base64_encode($bytes)];
        }

        $cc = array_values(array_filter((array) json_decode((string) ($row['cc_emails'] ?? ''), true)));
        if ($cc !== []) {
            $payload['cc'] = $cc;
        }

        $replyTo = trim((string) ($row['reply_to'] ?? '')) ?: trim((string) \config('mail.reply_to', ''));
        if ($replyTo !== '') {
            $payload['reply_to'] = $replyTo;
        }

        [$status, $body, $error] = self::post($payload);

        if ($error !== null) {
            self::mark($id, 'queued', null, 'Could not reach the mail provider: ' . $error);
            return false;
        }

        $decoded = json_decode($body, true);

        if ($status >= 200 && $status < 300 && isset($decoded['id'])) {
            self::mark($id, 'sent', (string) $decoded['id'], null);
            return true;
        }

        $reason = $decoded['message'] ?? $decoded['error']['message'] ?? ('HTTP ' . $status);
        // A refusal is the provider's final word; a fault on our side is worth
        // another try, so only the first is marked failed.
        self::mark($id, $status >= 400 && $status < 500 ? 'failed' : 'queued', null, 'Provider: ' . $reason);

        return false;
    }

    /** Sends everything still waiting. Returns how many went. */
    public static function flush(int $limit = 50): int
    {
        if (!self::mayDeliver()) {
            return 0;
        }

        $statement = self::db()->prepare(
            "SELECT id FROM email_outbox
              WHERE status IN ('queued', 'skipped') AND attempts < ?
              ORDER BY id
              LIMIT " . max(1, $limit)
        );
        $statement->execute([self::MAX_ATTEMPTS]);

        $sent = 0;
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            if (self::deliver((int) $id)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** Lets an administrator push one message again from the outbox page. */
    public static function retry(int $id): bool
    {
        self::db()->prepare("UPDATE email_outbox SET status = 'queued', attempts = 0, error = NULL WHERE id = ?")
            ->execute([$id]);

        return self::deliver($id);
    }

    // --------------------------------------------------------------- the HTTP

    /**
     * @return array{0: int, 1: string, 2: ?string} status, body, transport error
     */
    private static function post(array $payload): array
    {
        $handle = curl_init((string) \config('mail.endpoint', 'https://api.resend.com/emails'));
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . \config('mail.api_key', ''),
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);

        $body = curl_exec($handle);
        $error = curl_error($handle) ?: null;
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return [$status, is_string($body) ? $body : '', $error];
    }

    // ------------------------------------------------------------- the letter

    /**
     * The branded HTML. Tables and inline styles, because that is what mail
     * clients actually render; anything cleverer breaks in Outlook.
     */
    private static function render(array $message, string $realRecipient, bool $redirected): string
    {
        $brand = Report::brand();
        $colour = '#' . $brand['rgb'];
        $company = self::companyName();
        $escape = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $heading = $escape($message['heading'] ?? $message['subject'] ?? 'Update');
        $external = ($message['audience'] ?? 'external') !== 'staff';

        // A letter someone edited before sending arrives as HTML already, and is
        // cleaned again here so nothing unsafe reaches an inbox.
        $body = isset($message['body_html']) ? self::clean((string) $message['body_html']) : self::content($message);

        // The header: the company's logo and name. Mail programs show images
        // from a public address only, so the logo is served at /brand/logo.
        $logoFile = \Models\CompanyImage::path(\Models\CompanyImage::LOGO);
        $logo = $logoFile !== null
            ? '<img src="' . $escape(self::link('brand/logo') . '?v=' . substr(md5((string) filemtime($logoFile)), 0, 8)) . '" alt="' . $escape($company)
              . '" height="40" style="display:block;height:40px;width:auto;max-width:160px;border:0">'
            : '';
        $header = '<tr><td style="height:4px;line-height:4px;font-size:0;background:' . $colour . '">&nbsp;</td></tr>'
            . '<tr><td style="padding:18px 26px 16px;border-bottom:1px solid #e9edf1">'
            . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
            . ($logo !== '' ? '<td style="padding-right:12px;vertical-align:middle">' . $logo . '</td>' : '')
            . '<td style="vertical-align:middle"><span style="display:block;color:#18222c;font-size:17px;font-weight:700;letter-spacing:.2px">' . $escape($company) . '</span>'
            . ($external ? '' : '<span style="display:block;color:#77828d;font-size:11px;letter-spacing:1px;text-transform:uppercase;margin-top:2px">Logistics Management System</span>')
            . '</td></tr></table></td></tr>';

        // The first words an inbox shows under the subject: the point of the
        // email, not the header. Hidden in the message itself.
        $preheader = trim((string) ($message['preheader'] ?? ''));
        if ($preheader === '') {
            foreach ((array) ($message['lines'] ?? []) as $line) {
                $line = trim((string) $line);
                if ($line !== '' && !preg_match('/^(Dear|Hello)\b/i', $line)) {
                    $preheader = $line;
                    break;
                }
            }
        }
        $preheaderHtml = $preheader !== ''
            ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px">' . $escape(mb_substr($preheader, 0, 140)) . '</div>'
            : '';

        // Urgent and warning messages say so before anything else.
        $tone = (string) ($message['tone'] ?? '');
        $badge = match ($tone) {
            'danger' => ['Urgent', '#b42332', '#fbe3e5'],
            'warning' => ['Needs attention', '#9a6700', '#fdf1d8'],
            default => null,
        };
        $badgeHtml = $badge === null ? '' : '<p style="margin:0 0 10px"><span style="display:inline-block;padding:3px 10px;border-radius:12px;background:' . $badge[2]
            . ';color:' . $badge[1] . ';font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase">' . $badge[0] . '</span></p>';

        // The foot: to an outsider, who we are and that a reply reaches us;
        // to staff, that it came from the system.
        if ($external) {
            $details = Report::companyDetails();
            $signature = (string) ($message['signature'] ?? 'Questions about this message? Reply to it and it reaches our team.');
            $foot = '<p style="margin:0;font-size:11px;line-height:1.6;color:#5b6670">' . $escape($signature) . '</p>';
            if (!str_contains($signature, $details) && trim($details) !== '') {
                $foot .= '<p style="margin:6px 0 0;font-size:11px;line-height:1.6;color:#8a949e">' . $escape($company . '  ·  ' . $details) . '</p>';
            }
        } else {
            $foot = '<p style="margin:0;font-size:11px;line-height:1.6;color:#8a949e">' . $escape($message['signature'] ?? self::signature()) . '</p>'
                . '<p style="margin:6px 0 0;font-size:11px;color:#9aa4ad">Powered by ' . $escape(\vendor_name()) . ' &copy; ' . date('Y') . '</p>';
        }

        $redirectNote = $redirected
            ? '<p style="margin:14px 0 0;padding:10px 12px;background:#fff8e6;border-left:3px solid #e0a800;font-size:12px;color:#6b5600">'
              . 'Test mode: this message was addressed to <strong>' . $escape($realRecipient)
              . '</strong> and redirected here.</p>'
            : '';

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $escape($message['subject'] ?? 'Update') . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f1f4f7;font-family:Segoe UI,Helvetica,Arial,sans-serif">'
            . $preheaderHtml
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:#f1f4f7;padding:24px 12px">'
            . '<tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(20,30,40,.07)">'
            . $header
            . '<tr><td style="padding:24px 26px 26px;font-size:14px;line-height:1.6;color:#33404c">'
            . $badgeHtml
            . '<h1 style="margin:0 0 16px;font-size:19px;line-height:1.35;color:#18222c;font-weight:700">' . $heading . '</h1>'
            . $body
            . $redirectNote
            . '</td></tr>'
            . '<tr><td style="padding:16px 26px;background:#f7f9fb;border-top:1px solid #e9edf1">' . $foot . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** True when the address belongs to a member of staff with an account. */
    private static function isStaff(string $email): bool
    {
        static $known = [];
        $email = strtolower(trim($email));
        if (!array_key_exists($email, $known)) {
            try {
                $statement = self::db()->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND deleted_at IS NULL');
                $statement->execute([$email]);
                $known[$email] = (int) $statement->fetchColumn() > 0;
            } catch (\Throwable) {
                $known[$email] = false;
            }
        }

        return $known[$email];
    }

    /**
     * The body of the letter, between the heading and the signature: its lines,
     * tables, facts and closing. Public so a page can put it in an editor for
     * someone to change before it goes.
     */
    public static function content(array $message): string
    {
        $colour = '#' . Report::brand()['rgb'];
        $escape = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $body = '';
        foreach ((array) ($message['lines'] ?? []) as $line) {
            $body .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#33404c">' . $escape($line) . '</p>';
        }

        // Items, lines, products: things with a quantity and a price belong in a
        // table with headings, not in a run of sentences. A supplier reading an
        // order, or a customer checking an invoice, is comparing columns.
        foreach (self::itemBlocks($message) as $items) {
            $columns = $items['columns'] ?? [];
            $align = static fn (string $key): string => in_array($key, $items['numeric'] ?? [], true) ? 'right' : 'left';

            if (!empty($items['title'])) {
                $body .= '<p style="margin:22px 0 8px;font-size:12px;letter-spacing:.8px;text-transform:uppercase;color:#77828d;font-weight:700">'
                    . $escape($items['title']) . '</p>';
            }

            $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 18px;border-collapse:collapse">';

            // A two-column "Detail / Value" list reads as a list: no heading row,
            // labels in grey, values in bold, like the facts below.
            $labels = array_map(static fn ($l): string => strtolower(trim((string) $l)), array_values($columns));
            $isList = count($columns) === 2 && $labels[0] === 'detail' && $labels[1] === 'value';

            if (!$isList) {
                $body .= '<tr>';
                foreach ($columns as $key => $label) {
                    $body .= '<th align="' . $align((string) $key) . '" style="padding:8px 10px;background:#f2f5f8;border-bottom:2px solid ' . $colour
                        . ';font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#4a5560;font-weight:700' . ($align((string) $key) === 'right' ? ';white-space:nowrap' : '') . '">' . $escape($label) . '</th>';
                }
                $body .= '</tr>';
            }

            foreach ($items['rows'] as $row) {
                $body .= '<tr>';
                $first = true;
                foreach ($columns as $key => $label) {
                    if ($isList) {
                        $body .= $first
                            ? '<td style="padding:7px 12px 7px 0;font-size:13px;color:#77828d;border-bottom:1px solid #e9edf1;width:38%">' . $escape($row[$key] ?? '') . '</td>'
                            : '<td style="padding:7px 0;font-size:14px;color:#22303c;border-bottom:1px solid #e9edf1;font-weight:600">' . $escape($row[$key] ?? '') . '</td>';
                    } else {
                        // Amounts stay on one line: "USD 2,350.00", never "USD" over "2,350.00".
                        $nowrap = $align((string) $key) === 'right' ? ';white-space:nowrap' : '';
                        $body .= '<td align="' . $align((string) $key) . '" style="padding:9px 10px;border-bottom:1px solid #e9edf1;font-size:14px;color:#22303c' . $nowrap . '">'
                            . $escape($row[$key] ?? '') . '</td>';
                    }
                    $first = false;
                }
                $body .= '</tr>';
            }

            foreach ($items['totals'] ?? [] as $label => $value) {
                $span = max(1, count($columns) - 1);
                $body .= '<tr>'
                    . '<td colspan="' . $span . '" align="right" style="padding:9px 10px;font-size:13px;color:#77828d">' . $escape($label) . '</td>'
                    . '<td align="right" style="padding:9px 10px;font-size:15px;color:#18222c;font-weight:700;border-top:1px solid #e9edf1;white-space:nowrap">' . $escape($value) . '</td>'
                    . '</tr>';
            }

            $body .= '</table>';
        }

        if (!empty($message['facts'])) {
            $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:18px 0;border-collapse:collapse">';
            foreach ($message['facts'] as $label => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $body .= '<tr>'
                    . '<td style="padding:7px 12px 7px 0;font-size:13px;color:#77828d;border-bottom:1px solid #e9edf1;white-space:nowrap">' . $escape($label) . '</td>'
                    . '<td style="padding:7px 0;font-size:14px;color:#22303c;border-bottom:1px solid #e9edf1;font-weight:600">' . $escape($value) . '</td>'
                    . '</tr>';
            }
            $body .= '</table>';
        }

        // What is said after the numbers: how to quote a reference, who to reply
        // to. It reads as the close of a letter rather than part of the table.
        foreach ((array) ($message['closing'] ?? []) as $line) {
            $body .= '<p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#5b6670">' . $escape($line) . '</p>';
        }

        if (!empty($message['action']['label'])) {
            $href = $message['action']['url'] ?? self::link((string) ($message['action']['path'] ?? ''));
            if ($href !== '') {
                $body .= '<p style="margin:24px 0 8px">'
                    . '<a href="' . $escape($href) . '" style="display:inline-block;padding:11px 22px;background:' . $colour
                    . ';color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600">'
                    . $escape($message['action']['label']) . '</a></p>'
                    . '<p style="margin:0 0 6px;font-size:12px;color:#8a949e">If the button does not work, open this address:<br>'
                    . '<span style="color:#5b6670;word-break:break-all">' . $escape($href) . '</span></p>';
            }
        }

        return $body;
    }

    /**
     * HTML from an editor, reduced to what a letter needs: paragraphs, bold and
     * italics, lists, links and tables, with their inline styles. Scripts,
     * forms, frames and event handlers are dropped; any other tag is unwrapped
     * and keeps its text.
     */
    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $keep = ['p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4',
                 'blockquote', 'hr', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'];
        $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'head', 'title', 'svg', 'math', 'img', 'video', 'audio'];
        $attributes = ['style', 'align', 'valign', 'colspan', 'rowspan', 'href', 'cellpadding', 'cellspacing', 'role', 'width'];

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="lms-mail-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('lms-mail-root');
        if ($root === null) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $walk = static function (\DOMNode $node) use (&$walk, $keep, $drop, $attributes): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                    $node->removeChild($child);
                    continue;
                }
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                $tag = strtolower($child->nodeName);
                if (in_array($tag, $drop, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (!in_array($tag, $keep, true)) {
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->nodeName);
                    $value = (string) $attribute->nodeValue;
                    $unsafe = !in_array($name, $attributes, true)
                        || ($name === 'href' && preg_match('~^(https?:|mailto:|tel:)~i', trim($value)) !== 1)
                        || ($name === 'style' && preg_match('~expression|javascript|url\s*\(|@import|behavior~i', $value) === 1);
                    if ($unsafe) {
                        $child->removeAttribute($attribute->nodeName);
                    }
                }
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /** An edited letter as plain text: one paragraph or table row per line. */
    public static function textFromHtml(string $html): string
    {
        $text = preg_replace('~<\s*br\s*/?>~i', "\n", $html) ?? $html;
        $text = preg_replace('~<\s*li[^>]*>~i', '- ', $text) ?? $text;
        $text = preg_replace('~</\s*(t[hd])\s*>~i', '  |  ', $text) ?? $text;
        $text = preg_replace('~</\s*(p|h[1-6]|table|ul|ol|blockquote)\s*>~i', "\n\n", $text) ?? $text;
        $text = preg_replace('~</\s*(div|tr|li)\s*>~i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~[ \t]*\|\s*$~m', '', $text) ?? $text;
        $text = preg_replace('~[ \t]+\n~', "\n", $text) ?? $text;
        $text = preg_replace("~\n{3,}~", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** The same message as plain text, for clients that will not show HTML. */
    private static function plain(array $message): string
    {
        $parts = [(string) ($message['heading'] ?? $message['subject'] ?? 'Update'), ''];

        if (isset($message['body_html'])) {
            $parts[] = self::textFromHtml(self::clean((string) $message['body_html']));
            $parts[] = '';
            $parts[] = $message['signature'] ?? self::signature();
            $parts[] = 'Powered by ' . \vendor_name() . ' (c) ' . date('Y');

            return implode("\n", $parts);
        }

        foreach ((array) ($message['lines'] ?? []) as $line) {
            $parts[] = (string) $line;
            $parts[] = '';
        }

        foreach (self::itemBlocks($message) as $items) {
            $parts[] = strtoupper((string) ($items['title'] ?? 'Items'));
            foreach ($items['rows'] as $row) {
                $cells = [];
                foreach ($items['columns'] ?? [] as $key => $label) {
                    $cells[] = $label . ': ' . ($row[$key] ?? '');
                }
                $parts[] = '  ' . implode('  |  ', $cells);
            }
            foreach ($items['totals'] ?? [] as $label => $value) {
                $parts[] = '  ' . $label . ': ' . $value;
            }
            $parts[] = '';
        }

        foreach ((array) ($message['facts'] ?? []) as $label => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }

        foreach ((array) ($message['closing'] ?? []) as $line) {
            $parts[] = '';
            $parts[] = (string) $line;
        }

        if (!empty($message['action']['label'])) {
            $href = $message['action']['url'] ?? self::link((string) ($message['action']['path'] ?? ''));
            if ($href !== '') {
                $parts[] = '';
                $parts[] = $message['action']['label'] . ': ' . $href;
            }
        }

        $parts[] = '';
        $parts[] = $message['signature'] ?? self::signature();
        $parts[] = 'Powered by ' . \vendor_name() . ' (c) ' . date('Y');

        return implode("\n", $parts);
    }

    public static function uploadsRoot(): string
    {
        return rtrim(str_replace('\\', '/', (string) \config('uploads.path', dirname(__DIR__, 2) . '/storage/uploads')), '/');
    }

    /** A file's path under the uploads folder, or null when it is not in there. */
    private static function uploadsRelative(string $path): ?string
    {
        $root = realpath(self::uploadsRoot());
        $real = realpath($path) ?: realpath(self::uploadsRoot() . '/' . ltrim($path, '/\\'));
        if ($root === false || $real === false || !is_file($real)) {
            return null;
        }
        $root = str_replace('\\', '/', $root);
        $real = str_replace('\\', '/', $real);

        return str_starts_with($real, $root . '/') ? substr($real, strlen($root) + 1) : null;
    }

    /** An absolute link, because a relative one is meaningless in an inbox. */
    public static function link(string $path): string
    {
        $path = trim($path, '/');
        $base = (string) \config('app.url', '');

        if ($base === '') {
            // Fall back to the running request, which is right in a browser and
            // simply absent on the console.
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host === '') {
                return '';
            }
            $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $host . rtrim((string) \config('app.base_url', ''), '/');
        }

        return $path === '' ? $base : $base . '/' . $path;
    }

    private static function companyName(): string
    {
        try {
            return Settings::get('company_name', 'LMS Logistics');
        } catch (\Throwable) {
            return 'LMS Logistics';
        }
    }

    /**
     * The tables a message carries.
     *
     * One block is the common case and is what every caller wrote first; a list
     * of blocks is for a message that is genuinely several tables, such as a
     * driver's trip sheet. Blocks with no rows are dropped so an empty table
     * never reaches the page.
     *
     * @return list<array<string, mixed>>
     */
    private static function itemBlocks(array $message): array
    {
        $items = $message['items'] ?? null;
        if (!is_array($items) || $items === []) {
            return [];
        }

        $blocks = isset($items['rows']) || isset($items['columns']) ? [$items] : array_values($items);

        return array_values(array_filter(
            $blocks,
            static fn (mixed $block): bool => is_array($block) && !empty($block['rows'])
        ));
    }
    private static function signature(): string
    {
        try {
            return Settings::get('email_signature', 'Sent by the LMS logistics system.');
        } catch (\Throwable) {
            return 'Sent by the LMS logistics system.';
        }
    }

    private static function category(string $category): string
    {
        $allowed = ['notification', 'alert', 'password_reset', 'new_account', 'invoice', 'manual', 'test'];

        return in_array($category, $allowed, true) ? $category : 'notification';
    }

    private static function row(int $id): ?array
    {
        $statement = self::db()->prepare('SELECT * FROM email_outbox WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    private static function mark(int $id, string $status, ?string $providerId, ?string $error): void
    {
        try {
            self::db()->prepare(
                'UPDATE email_outbox
                    SET status = ?, provider_id = COALESCE(?, provider_id), error = ?,
                        attempts = attempts + 1,
                        sent_at = CASE WHEN ? = "sent" THEN NOW() ELSE sent_at END
                  WHERE id = ?'
            )->execute([$status, $providerId, $error === null ? null : mb_substr($error, 0, 500), $status, $id]);
        } catch (\Throwable) {
            // The outbox is a record, not the work itself.
        }
    }
}
