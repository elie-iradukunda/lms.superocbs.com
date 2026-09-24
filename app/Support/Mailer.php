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

    private static function db(): PDO
    {
        return Database::connection();
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
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
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

        $html = $message['html'] ?? self::render($message, $realRecipient, $redirect !== '');
        $text = $message['text'] ?? self::plain($message);

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

        $replyTo = trim((string) \config('mail.reply_to', ''));
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

            $body .= '<tr>';
            foreach ($columns as $key => $label) {
                $body .= '<th align="' . $align((string) $key) . '" style="padding:8px 10px;background:#f2f5f8;border-bottom:2px solid ' . $colour
                    . ';font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#4a5560;font-weight:700">' . $escape($label) . '</th>';
            }
            $body .= '</tr>';

            foreach ($items['rows'] as $row) {
                $body .= '<tr>';
                foreach ($columns as $key => $label) {
                    $body .= '<td align="' . $align((string) $key) . '" style="padding:9px 10px;border-bottom:1px solid #e9edf1;font-size:14px;color:#22303c">'
                        . $escape($row[$key] ?? '') . '</td>';
                }
                $body .= '</tr>';
            }

            foreach ($items['totals'] ?? [] as $label => $value) {
                $span = max(1, count($columns) - 1);
                $body .= '<tr>'
                    . '<td colspan="' . $span . '" align="right" style="padding:9px 10px;font-size:13px;color:#77828d">' . $escape($label) . '</td>'
                    . '<td align="right" style="padding:9px 10px;font-size:15px;color:#18222c;font-weight:700;border-top:1px solid #e9edf1">' . $escape($value) . '</td>'
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

        // A message that asks for a reply cannot be signed "do not reply".
        $signature = $escape($message['signature'] ?? self::signature());
        $redirectNote = $redirected
            ? '<p style="margin:14px 0 0;padding:10px 12px;background:#fff8e6;border-left:3px solid #e0a800;font-size:12px;color:#6b5600">'
              . 'Test mode: this message was addressed to <strong>' . $escape($realRecipient)
              . '</strong> and redirected here.</p>'
            : '';

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $escape($message['subject'] ?? 'Update') . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f1f4f7;font-family:Segoe UI,Helvetica,Arial,sans-serif">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:#f1f4f7;padding:24px 12px">'
            . '<tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:580px;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(20,30,40,.07)">'
            . '<tr><td style="background:' . $colour . ';padding:18px 26px">'
            . '<span style="color:#ffffff;font-size:16px;font-weight:700;letter-spacing:.4px">' . $escape($company) . '</span>'
            . '<span style="display:block;color:rgba(255,255,255,.82);font-size:11px;letter-spacing:1.2px;text-transform:uppercase;margin-top:2px">Logistics Management System</span>'
            . '</td></tr>'
            . '<tr><td style="padding:26px">'
            . '<h1 style="margin:0 0 16px;font-size:19px;line-height:1.35;color:#18222c;font-weight:700">' . $heading . '</h1>'
            . $body
            . $redirectNote
            . '</td></tr>'
            . '<tr><td style="padding:16px 26px;background:#f7f9fb;border-top:1px solid #e9edf1">'
            . '<p style="margin:0;font-size:11px;line-height:1.6;color:#8a949e">' . $signature . '</p>'
            . '<p style="margin:6px 0 0;font-size:11px;color:#9aa4ad">Powered by ' . $escape(\vendor_name()) . ' &copy; ' . date('Y') . '</p>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** The same message as plain text, for clients that will not show HTML. */
    private static function plain(array $message): string
    {
        $parts = [(string) ($message['heading'] ?? $message['subject'] ?? 'Update'), ''];

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
