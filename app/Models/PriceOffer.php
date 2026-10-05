<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * A price offer: the rate cards picked for one customer and sent to them.
 *
 * The company keeps its prices as general rate cards, one per route. When a
 * customer asks "what do you charge?", the office picks the cards that fit what
 * they want and sends them together. When the customer accepts, the transport
 * request is raised from the offer, carrying the customer and the route, and
 * goes on through approval like any other.
 */
final class PriceOffer
{
    public const STATUS = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /** The rate cards on an offer, with what a customer needs to read. */
    public static function cards(int $offerId): array
    {
        $statement = self::db()->prepare(
            'SELECT rc.*, l.note, wo.warehouse_name AS from_depot, wd.warehouse_name AS to_depot
               FROM price_offer_lines l
               INNER JOIN rate_cards rc ON rc.id = l.rate_card_id
               LEFT JOIN warehouses wo ON wo.id = rc.origin_warehouse_id
               LEFT JOIN warehouses wd ON wd.id = rc.destination_warehouse_id
              WHERE l.offer_id = ?
              ORDER BY l.id'
        );
        $statement->execute([$offerId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** "Mombasa → Kigali · USD 4,800 per trip": how a card reads in a list. */
    public static function cardLabel(array $card): string
    {
        $from = trim((string) ($card['from_depot'] ?? '')) ?: (string) $card['origin'];
        $to = trim((string) ($card['to_depot'] ?? '')) ?: (string) $card['destination'];

        return sprintf('%s · %s · %s → %s · %s %s', $card['rate_code'], self::service((string) ($card['service_type'] ?? '')), $from, $to,
            Currency::format((float) $card['rate_amount'], (string) ($card['currency'] ?: Currency::base())), self::basis((string) $card['rate_type']));
    }

    public static function service(string $type): string
    {
        return match ($type) {
            'full_truck' => 'Full truck',
            'shared' => 'Shared truck',
            default => 'Truck',
        };
    }

    public static function basis(string $type): string
    {
        return match ($type) {
            'per_trip' => 'per trip',
            'per_kg' => 'per kg',
            'per_m3' => 'per m³',
            'per_package' => 'per package',
            'per_day' => 'per day',
            default => $type,
        };
    }

    public static function guard(string $action, array $offer): ?string
    {
        if ($action === 'send') {
            if (self::cards((int) $offer['id']) === []) {
                return 'Add at least one rate card to the offer (Rates offered, below) before sending it.';
            }
            $email = (string) Database::connection()->query('SELECT email FROM customers WHERE id = ' . (int) $offer['customer_id'])->fetchColumn();
            if (trim($email) === '') {
                return 'This customer has no email address. Add one on the customer before sending.';
            }
        }

        return null;
    }

    public static function applied(string $action, array $offer, string $reason, ?int $actor): string
    {
        $id = (int) $offer['id'];
        $db = self::db();

        if ($action === 'send') {
            $db->prepare('UPDATE price_offers SET sent_at = NOW(), valid_until = COALESCE(valid_until, DATE_ADD(CURDATE(), INTERVAL 30 DAY)) WHERE id = ?')->execute([$id]);
            $queued = self::email($id);

            return sprintf('Offer %s sent to the customer%s.', $offer['offer_code'], $queued ? '' : ' (the email could not be queued: check the outbox)');
        }

        if ($action === 'accept') {
            $db->prepare('UPDATE price_offers SET accepted_at = NOW() WHERE id = ?')->execute([$id]);
            Notifier::toRole('logistics_manager', 'Price offer accepted',
                sprintf('%s was accepted by the customer. Send them the LPO form, then raise the transport request.', $offer['offer_code']),
                'price_offers', 'success', 'price_offers', (string) $id);

            return sprintf('Offer %s accepted. Press "Send LPO form" so the customer can confirm the order, then "Raise transport request".', $offer['offer_code']);
        }

        if ($action === 'decline') {
            $db->prepare('UPDATE price_offers SET notes = TRIM(CONCAT(COALESCE(notes, ""), "\nDeclined: ", ?)) WHERE id = ?')->execute([$reason, $id]);

            return sprintf('Offer %s marked declined.', $offer['offer_code']);
        }

        return 'Done.';
    }

    /**
     * The letter as the office last edited it on the "Send to customer" page:
     * address, subject and body. Set just before the send action runs, so the
     * customer gets exactly what was on the screen.
     */
    private static ?array $edited = null;

    public static function useEdited(string $to, string $subject, string $bodyHtml): void
    {
        self::$edited = ['to' => $to, 'subject' => $subject, 'body_html' => $bodyHtml];
    }

    /** Queues the letter, edited or as written by the system. */
    private static function email(int $offerId): bool
    {
        $message = self::letter($offerId);
        if ($message === null) {
            return false;
        }
        if (self::$edited !== null) {
            $message = array_merge($message, self::$edited);
            self::$edited = null;
        }
        if (trim((string) $message['to']) === '') {
            return false;
        }

        return (bool) Mailer::send($message)['queued'];
    }

    /**
     * The offer, as a business letter: who it is for, the rates in a table
     * (route, service, what the price covers, price), the terms, how to accept,
     * and who to contact.
     */
    /** The offer with its customer and the person who prepared it. */
    public static function offer(int $offerId): ?array
    {
        $statement = self::db()->prepare(
            'SELECT o.*, c.customer_name, c.contact_name, c.email, c.phone AS customer_phone, c.address AS customer_address,
                    c.tin_number AS customer_tin, c.payment_terms_days,
                    u.full_name AS sender_name, u.email AS sender_email, u.phone AS sender_phone, u.job_title AS sender_title
               FROM price_offers o
               INNER JOIN customers c ON c.id = o.customer_id
               LEFT JOIN users u ON u.id = o.created_by
              WHERE o.id = ? AND o.deleted_at IS NULL'
        );
        $statement->execute([$offerId]);
        $offer = $statement->fetch(PDO::FETCH_ASSOC);

        return $offer === false ? null : $offer;
    }

    public static function letter(int $offerId): ?array
    {
        $offer = self::offer($offerId);
        if ($offer === null) {
            return null;
        }

        $rows = [];
        $hasShared = false;
        foreach (self::cards($offerId) as $card) {
            $code = (string) ($card['currency'] ?: Currency::base());
            $shared = ($card['service_type'] ?? '') === 'shared';
            $hasShared = $hasShared || $shared;
            $maxKg = (float) ($card['full_load_kg'] ?? 0);
            $covers = $shared
                ? 'Your goods only, charged on the weight loaded'
                : 'The whole truck for your goods' . ($maxKg > 0 ? ', up to ' . number_format($maxKg) . ' kg' : '');
            if (trim((string) ($card['note'] ?? '')) !== '') {
                $covers .= ' (' . trim((string) $card['note']) . ')';
            }
            $price = Currency::format((float) $card['rate_amount'], $code) . ' ' . self::basis((string) $card['rate_type']);
            if ((float) $card['minimum_charge'] > 0 && !in_array($card['rate_type'], ['per_trip', 'per_day'], true)) {
                $price .= ', minimum ' . Currency::format((float) $card['minimum_charge'], $code);
            }
            $rows[] = [
                'route' => (trim((string) ($card['from_depot'] ?? '')) ?: $card['origin']) . ' to ' . (trim((string) ($card['to_depot'] ?? '')) ?: $card['destination']),
                'service' => self::service((string) ($card['service_type'] ?? '')),
                'covers' => $covers,
                'price' => $price,
            ];
        }

        $company = \company_name();
        $name = trim((string) ($offer['contact_name'] ?: $offer['customer_name']));
        $valid = date('j F Y', (int) strtotime((string) ($offer['valid_until'] ?: date('Y-m-d', strtotime('+30 days')))));
        $terms = (int) ($offer['payment_terms_days'] ?: Settings::int('payment_terms_days', 30));

        $lines = [
            sprintf('Dear %s,', $name !== '' ? $name : 'Sir or Madam'),
            sprintf('Thank you for your enquiry and for considering %s for the transport of your goods.', $company),
        ];
        // The office's own words when it wrote some, otherwise a line that fits
        // any enquiry, by phone, email or in person.
        $lines[] = trim((string) $offer['message']) !== '' ? trim((string) $offer['message']) : 'As you requested, please find below our rates for the transport of your goods.';

        $closing = [
            'Included in the price: carriage from the point of collection to the destination shown, the truck and its driver, fuel, road tolls and tracking of your goods throughout the journey.',
            'Not included: customs duty and taxes, port and storage charges, and other government fees. Where we pay these on your behalf, they are recharged at cost on your invoice, with the receipts.',
        ];
        if ($hasShared) {
            $closing[] = 'Shared truck prices are charged on the actual weight loaded, subject to the minimum charge shown.';
        }
        $closing[] = sprintf('To proceed, simply reply to this email with the route, a description of the goods, their weight and the date you would like them collected, quoting reference %s. We will confirm the booking and the collection details.', $offer['offer_code']);
        $closing[] = 'We look forward to working with you.';
        $closing[] = 'Kind regards,';
        $closing[] = trim(implode(', ', array_filter([(string) ($offer['sender_name'] ?? ''), $company])));
        $contact = array_filter([(string) ($offer['sender_phone'] ?? '') ?: Settings::get('company_phone', ''), (string) ($offer['sender_email'] ?? '') ?: Settings::get('company_email', '')]);
        if ($contact !== []) {
            $closing[] = implode('  ·  ', $contact);
        }

        return [
            'key' => 'price-offer-' . $offerId . '-' . date('YmdHis'),
            'category' => 'notification',
            'to' => (string) $offer['email'],
            'to_name' => $name,
            'subject' => sprintf('Transport rates for %s — offer %s', $offer['customer_name'], $offer['offer_code']),
            'heading' => 'Transport rate offer',
            'lines' => $lines,
            'items' => [
                'title' => 'Our rates',
                'columns' => ['route' => 'Route', 'service' => 'Service', 'covers' => 'What the price covers', 'price' => 'Price'],
                'numeric' => [],
                'rows' => $rows,
            ],
            'facts' => [
                'Offer reference' => (string) $offer['offer_code'],
                'Date' => date('j F Y'),
                'Prepared for' => (string) $offer['customer_name'],
                'Valid until' => $valid,
                'Payment terms' => sprintf('%d days from the date of invoice', $terms),
            ],
            'closing' => $closing,
            'signature' => trim($company . '  ·  ' . \Support\Report::companyDetails(), ' ·'),
            'entity_type' => 'price_offers',
            'entity_id' => (string) $offer['offer_code'],
        ];
    }

    /**
     * The email that carries the LPO form to a customer who accepted: thanks,
     * what is already filled in, what is theirs to complete, and where to send
     * it back. The form itself is attached when it is sent.
     */
    public static function lpoLetter(int $offerId): ?array
    {
        $offer = self::offer($offerId);
        if ($offer === null) {
            return null;
        }

        $company = \company_name();
        $name = trim((string) ($offer['accepted_by'] ?: $offer['contact_name'] ?: $offer['customer_name']));
        $returnTo = trim((string) ($offer['sender_email'] ?? '')) ?: trim(Settings::get('company_email', ''));
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $p = static fn (string $html): string => '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#33404c">' . $html . '</p>';
        $li = static fn (string $html): string => '<li style="margin:0 0 6px;font-size:14px;line-height:1.5;color:#33404c">' . $html . '</li>';

        $contact = implode('  ·  ', array_filter([(string) ($offer['sender_phone'] ?? '') ?: Settings::get('company_phone', ''), $returnTo]));
        $body = $p($e(sprintf('Dear %s,', $name !== '' ? $name : 'Sir or Madam')))
            . $p($e(sprintf('Thank you for accepting our offer %s. We are pleased to be working with you.', $offer['offer_code'])))
            . $p($e('To confirm the order, please find attached our Local Purchase Order (LPO) form. Our company details, the agreed routes and prices, and the terms are already filled in.'))
            . $p('<strong>Please complete the shaded boxes:</strong>')
            . '<ul style="margin:0 0 16px;padding-left:20px">'
            . $li('Your LPO number and date')
            . $li('Your company details (please check the ones we have filled in)')
            . $li('The quantity and the amount for each service, and the total')
            . $li('The goods, the collection date and the delivery details')
            . $li('Who our invoices should be sent to')
            . $li('The names of the people authorising the order, with their <strong>signatures and your company stamp</strong>')
            . '</ul>'
            . $p($e(sprintf('The form can be filled in on a computer or phone with any PDF reader (Adobe Reader, Chrome or Edge), or printed and filled in by hand. Please send it back by reply to this email%s, quoting %s. A scan or a photo of the signed form is enough.', $returnTo !== '' ? ' (' . $returnTo . ')' : '', $offer['offer_code'])))
            . $p($e('Once we receive it, we will confirm the collection date, the truck and the driver.'))
            . $p($e('Kind regards,') . '<br>' . $e(trim(implode(', ', array_filter([(string) ($offer['sender_name'] ?? ''), $company]))))
                . ($contact !== '' ? '<br>' . $e($contact) : ''));

        return [
            'key' => 'price-offer-lpo-' . $offerId . '-' . date('YmdHis'),
            'category' => 'notification',
            'to' => (string) $offer['email'],
            'to_name' => $name,
            'subject' => sprintf('LPO form to confirm your order — offer %s', $offer['offer_code']),
            'heading' => 'Local Purchase Order form',
            'body_html' => $body,
            'signature' => trim($company . '  ·  ' . \Support\Report::companyDetails(), ' ·'),
            'entity_type' => 'price_offers',
            'entity_id' => (string) $offer['offer_code'],
        ];
    }

    /** Sends the LPO form, with the letter as edited, and records when it went. */
    public static function sendLpo(int $offerId, string $to, string $subject, string $bodyHtml): array
    {
        $message = self::lpoLetter($offerId);
        $form = LpoForm::store($offerId);
        if ($message === null || $form === null) {
            return ['ok' => false, 'message' => 'The LPO form could not be prepared. Check that the uploads folder can be written to.'];
        }

        $result = Mailer::send(array_merge($message, [
            'to' => $to,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'attachments' => [$form],
        ]));
        if (!$result['queued']) {
            return ['ok' => false, 'message' => 'The email could not be queued: ' . $result['reason']];
        }

        self::db()->prepare('UPDATE price_offers SET lpo_sent_at = NOW() WHERE id = ?')->execute([$offerId]);
        $code = (string) self::db()->query('SELECT offer_code FROM price_offers WHERE id = ' . $offerId)->fetchColumn();
        AuditLog::record('price_offer.lpo_sent', 'price_offers', $code, null, ['to' => $to]);

        return ['ok' => true, 'message' => sprintf('LPO form for %s sent to %s%s. When it comes back signed, raise the transport request and attach it there.',
            $code, $to, $result['sent'] ? '' : ' (queued: see the email outbox)')];
    }

    /** Defaults for the request raised from an accepted offer: the customer and its first route. */
    public static function requestDefaults(int $offerId): ?array
    {
        // Who accepted, or else the customer's contact person: almost always the
        // same person who will ask for the truck.
        $statement = self::db()->prepare(
            "SELECT o.*, COALESCE(NULLIF(TRIM(o.accepted_by), ''), c.contact_name) AS accepted_by
               FROM price_offers o
               LEFT JOIN customers c ON c.id = o.customer_id
              WHERE o.id = ? AND o.deleted_at IS NULL AND o.status = 'accepted'"
        );
        $statement->execute([$offerId]);
        $offer = $statement->fetch(PDO::FETCH_ASSOC);
        if ($offer === false) {
            return null;
        }
        $card = self::cards($offerId)[0] ?? null;

        return array_filter([
            'price_offer_id' => $offer['id'],
            'customer_id' => $offer['customer_id'],
            'requested_by_contact' => $offer['accepted_by'],
            'pickup_location' => $card['origin'] ?? null,
            'origin_warehouse_id' => $card['origin_warehouse_id'] ?? null,
            'destination' => $card['destination'] ?? null,
            'destination_warehouse_id' => $card['destination_warehouse_id'] ?? null,
            'currency' => $card['currency'] ?? null,
            'rate_card_id' => $card['id'] ?? null,
        ], static fn ($v): bool => $v !== null && $v !== '');
    }
}
