<?php

declare(strict_types=1);

namespace Support;

use Core\Database;
use Models\Settings;

/**
 * Short WhatsApp messages with a link (section 19), from the company's
 * WhatsApp Business number through the WhatsApp Cloud API.
 *
 * Every message is written to whatsapp_outbox first, so nothing is lost when
 * WhatsApp is switched off, not yet configured, or unreachable; the clock
 * (scripts/alerts.php) sends what is queued. A console run only queues.
 */
final class WhatsApp
{
    /** Topics a person may also receive on WhatsApp, by where the update points. */
    public const TOPICS = [
        'requests' => 'Requests and approvals (waiting for me, sent back, signed)',
        'payments' => 'Money sent to me, payments recorded',
        'trips' => 'Trips: planned, ready, departed, at the turnaround, late',
        'support' => 'Driver support requests',
        'documents' => 'Documents expiring, service due, MoMo changes',
        'store' => 'Store, stock and purchase orders',
        'fuel' => 'Fuel orders',
        'other' => 'Everything else',
    ];

    /** What a role gets on WhatsApp until the person chooses for themselves. */
    public const ROLE_DEFAULTS = [
        'driver' => ['requests', 'payments', 'trips', 'support', 'documents'],
        'logistics_manager' => ['support', 'trips', 'requests'],
        'management' => ['requests'],
        'finance' => ['requests'],
        'cashier' => ['requests', 'payments'],
        'warehouse_manager' => ['store', 'requests'],
        'clearance_officer' => ['requests'],
    ];

    private const ROUTE_TOPICS = [
        'form_requests' => 'requests', 'approvals' => 'requests', 'payment_batches' => 'payments', 'trips' => 'trips', 'my-trips' => 'trips',
        'support_requests' => 'support', 'vehicle_documents' => 'documents', 'driver_documents' => 'documents', 'vehicles' => 'documents',
        'driver_momo_changes' => 'documents', 'warehouse' => 'store', 'procurement' => 'store', 'movements' => 'store', 'fuel_orders' => 'fuel',
    ];

    public static function enabled(): bool
    {
        return Settings::int('whatsapp_enabled', 0) === 1
            && Settings::get('whatsapp_phone_number_id') !== ''
            && Settings::get('whatsapp_access_token') !== '';
    }

    public static function topicFor(?string $route, string $title = ''): string
    {
        $route = (string) $route;
        if ($route === 'my-trips' && preg_match('/money|paid|payment/i', $title) === 1) {
            return 'payments';
        }

        return self::ROUTE_TOPICS[$route] ?? 'other';
    }

    /** The topics a person gets on WhatsApp. @return list<string> */
    public static function topicsOf(?string $chosen, string $roleKey): array
    {
        if ($chosen !== null && trim($chosen) !== '') {
            return array_values(array_intersect(array_map('trim', explode(',', $chosen)), array_keys(self::TOPICS)));
        }

        return self::ROLE_DEFAULTS[$roleKey] ?? [];
    }

    /** A number as WhatsApp wants it: digits only, with the country code. */
    public static function number(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            return preg_replace('/\D/', '', Settings::get('whatsapp_country_code', '250')) . substr($digits, 1);
        }

        return strlen($digits) <= 9 ? preg_replace('/\D/', '', Settings::get('whatsapp_country_code', '250')) . $digits : $digits;
    }

    /**
     * Queues one message, and sends it at once when it may.
     *
     * @return bool whether it was sent now
     */
    public static function send(string $key, ?int $userId, string $toNumber, string $title, string $message, ?string $link, ?string $topic = null, ?int $companyId = null): bool
    {
        $to = self::number($toNumber);
        if ($to === '') {
            return false;
        }
        $body = mb_substr(trim($title) . "\n" . trim($message), 0, 900);
        try {
            $statement = Database::connection()->prepare(
                'INSERT IGNORE INTO whatsapp_outbox (company_id, message_key, user_id, to_number, topic, body, link) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([$companyId, mb_substr($key, 0, 120), $userId, $to, $topic, $body, $link]);
            if ($statement->rowCount() === 0) {
                return false;
            }
            $id = (int) Database::connection()->lastInsertId();
        } catch (\Throwable) {
            return false;
        }

        if (!self::enabled()) {
            Database::connection()->prepare("UPDATE whatsapp_outbox SET status = 'skipped', error = 'WhatsApp is switched off or not configured.' WHERE id = ?")->execute([$id]);

            return false;
        }
        if (PHP_SAPI === 'cli' && getenv('WHATSAPP_SEND_IN_CLI') !== '1') {
            return false;
        }

        return self::deliver($id);
    }

    /** Sends what is still queued (from the clock). */
    public static function flush(int $limit = 50): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $sent = 0;
        $ids = Database::connection()->query("SELECT id FROM whatsapp_outbox WHERE status IN ('queued', 'failed') AND attempts < 5 ORDER BY id LIMIT " . max(1, $limit))->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            if (self::deliver((int) $id)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function deliver(int $id): bool
    {
        $statement = Database::connection()->prepare('SELECT * FROM whatsapp_outbox WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row === false || $row['status'] === 'sent') {
            return false;
        }

        $text = $row['body'] . ($row['link'] ? "\n" . $row['link'] : '');
        $template = Settings::get('whatsapp_template');
        $payload = $template !== ''
            ? [
                'messaging_product' => 'whatsapp', 'to' => $row['to_number'], 'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => Settings::get('whatsapp_template_language', 'en')],
                    'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr(str_replace("\n", ' — ', $text), 0, 1000)]]]],
                ],
            ]
            : ['messaging_product' => 'whatsapp', 'to' => $row['to_number'], 'type' => 'text', 'text' => ['preview_url' => true, 'body' => $text]];

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', rawurlencode(Settings::get('whatsapp_api_version', 'v19.0')), rawurlencode(Settings::get('whatsapp_phone_number_id')));
        $error = null;
        $providerId = null;
        try {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . Settings::get('whatsapp_access_token'), 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload),
            ]);
            $response = (string) curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            $decoded = json_decode($response, true);
            if ($status >= 200 && $status < 300 && isset($decoded['messages'][0]['id'])) {
                $providerId = (string) $decoded['messages'][0]['id'];
            } else {
                $error = $curlError !== '' ? $curlError : (string) ($decoded['error']['message'] ?? ('HTTP ' . $status));
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        Database::connection()->prepare(
            'UPDATE whatsapp_outbox SET status = ?, provider_id = ?, error = ?, attempts = attempts + 1, sent_at = IF(? = \'sent\', NOW(), sent_at) WHERE id = ?'
        )->execute([$error === null ? 'sent' : 'failed', $providerId, $error !== null ? mb_substr($error, 0, 255) : null, $error === null ? 'sent' : 'failed', $id]);

        return $error === null;
    }
}
