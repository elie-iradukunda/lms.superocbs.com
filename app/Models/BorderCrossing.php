<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * A truck at a border post.
 *
 * The office's problem at a crossing is never the tax law — the authority has a
 * system for that. It is the four hours nobody can account for and the charges
 * that turn up on an agent's invoice a fortnight later. So this records the
 * things the company itself owns:
 *
 *   the clock     arrived, lodged, cleared, departed — and the gaps between
 *   the papers    which are needed here, which are in hand, which is holding it
 *   the money     every charge, in the currency it was actually paid in
 *
 * From those three, the questions the office is actually asked become answerable:
 * where is that truck, what did this route cost, and which post holds us up.
 *
 * Nothing here talks to a customs system. When one is available to integrate
 * with, the declaration number on the crossing is the key it would be joined on.
 */
final class BorderCrossing
{
    /** Where the money goes in the books, by the kind of charge it is. */
    private const CHARGE_ACCOUNTS = [
        'Customs duty' => '5500',
        'Import VAT' => '5500',
        'Withholding tax' => '5500',
        'Transit bond' => '5500',
        'Clearing agent fee' => '5600',
        'Weighbridge' => '5700',
        'Escort fee' => '5700',
        'Parking and storage' => '5700',
        'Road toll' => '5200',
    ];

    private const FALLBACK_ACCOUNT = '5700';

    /** Money paid for a customer, waiting to be recharged on their invoice. */
    private const RECOVERABLE_ACCOUNT = '1150';

    /**
     * What must be true before a crossing may move on.
     *
     * The rules are the ones a dispatcher would apply out loud: you cannot lodge
     * a declaration you have not got, and a truck cannot be released while a
     * required paper is still missing.
     */
    public static function guard(string $action, array $crossing): ?string
    {
        $id = (int) $crossing['id'];

        if ($action === 'submit_declaration' && trim((string) $crossing['declaration_no']) === '') {
            return 'Enter the declaration number before submitting it: that number is what everything else is followed up on.';
        }
        if ($action === 'submit_declaration' && self::validDocuments($id) === 0) {
            return 'Add and validate at least one clearance document before submitting the declaration.';
        }

        if ($action === 'clear') {
            $missing = self::missingDocuments($id);
            if ($missing !== []) {
                return sprintf(
                    'Customs will not release this until %s %s in hand. Tick them off under Documents, or record why it is being held.',
                    self::readableList($missing),
                    count($missing) === 1 ? 'is' : 'are'
                );
            }
            if (self::outstandingCharges($id) > 0) {
                return 'Mark every customs charge paid or waived before clearing this shipment.';
            }
        }

        if ($action === 'pass_inspection' && !self::hasPassedInspection($id)) {
            return 'Record a passed inspection before moving the clearance to duties assessment.';
        }

        if ($action === 'release' && !self::hasRelease($id)) {
            return 'Create the formal release record before marking this shipment released.';
        }

        return null;
    }

    /**
     * What happens once the status has moved.
     *
     * Each step stamps its own time if it has not been filled in by hand, which
     * is what makes the dwell figures real rather than typed.
     */
    public static function applied(string $action, array $crossing, string $reason, ?int $actor): string
    {
        $db = Database::connection();
        $id = (int) $crossing['id'];
        $now = date('Y-m-d H:i:s');

        return match ($action) {
            'prepare_documents' => self::stamp($db, $id, 'arrived_at', $now, sprintf('%s is collecting clearance documents.', $crossing['reference'])),
            'submit_declaration' => self::submit($db, $id, $crossing, $now),
            'start_review' => self::stamp($db, $id, 'approved_at', $now, sprintf('%s is under customs review.', $crossing['reference'])),
            'request_inspection' => self::hold($db, $id, $crossing, 'Inspection requested by customs.'),
            'pass_inspection' => sprintf('Inspection passed for %s. Duties can now be assessed.', $crossing['reference']),
            'assess_duties' => sprintf('Duties have been assessed for %s. Record payment next.', $crossing['reference']),
            'request_payment' => sprintf('Payment is now pending for %s.', $crossing['reference']),
            'clear' => self::clear($db, $id, $crossing, $actor),
            'release' => self::depart($db, $id, $crossing),
            'reject' => self::hold($db, $id, $crossing, $reason),
            default => sprintf('%s updated.', $crossing['reference']),
        };
    }

    private static function stamp(PDO $db, int $id, string $column, string $when, string $message): string
    {
        $db->prepare("UPDATE border_crossings SET {$column} = COALESCE({$column}, ?), hold_reason = NULL WHERE id = ?")->execute([$when, $id]);

        return $message;
    }

    private static function submit(PDO $db, int $id, array $crossing, string $when): string
    {
        $db->prepare('UPDATE border_crossings SET submitted_at = COALESCE(submitted_at, ?), lodged_at = COALESCE(lodged_at, ?), hold_reason = NULL WHERE id = ?')->execute([$when, $when, $id]);

        return sprintf('Declaration %s was submitted.', $crossing['declaration_no']);
    }

    private static function hold(PDO $db, int $id, array $crossing, string $reason): string
    {
        $db->prepare('UPDATE border_crossings SET hold_reason = ? WHERE id = ?')->execute([mb_substr(trim($reason), 0, 255), $id]);

        Notifier::toRole(
            'logistics_manager',
            'Load held at the border',
            sprintf('%s at %s is held: %s', $crossing['reference'], self::postName((int) $crossing['border_post_id']), $reason),
            'crossings',
            'danger',
            'crossings',
            (string) $crossing['reference']
        );

        return sprintf('%s is marked held. The reason is on the record and logistics has been told.', $crossing['reference']);
    }

    /**
     * Customs has released the load.
     *
     * This is also where the money reaches the books: until a crossing is
     * cleared the charges are an estimate, and posting an estimate is how a set
     * of books ends up disagreeing with an agent's invoice.
     */
    private static function clear(PDO $db, int $id, array $crossing, ?int $actor): string
    {
        $db->prepare('UPDATE border_crossings SET cleared_at = COALESCE(cleared_at, NOW()), hold_reason = NULL WHERE id = ?')->execute([$id]);
        if (!empty($crossing['shipment_id'])) {
            $db->prepare("UPDATE shipments SET status = 'cleared' WHERE id = ? AND deleted_at IS NULL AND status NOT IN ('delivered', 'returned', 'cancelled')")->execute([(int) $crossing['shipment_id']]);
        }

        $posted = self::post($id);
        $waited = self::hoursBetween((string) $crossing['arrived_at'], date('Y-m-d H:i:s'));

        return sprintf(
            '%s is cleared%s.%s',
            $crossing['reference'],
            $waited === null ? '' : sprintf(' after %s at the post', self::readableHours($waited)),
            // Shown in the currency the charges were paid in, as they were booked.
            $posted > 0 ? sprintf(' %s of charges went to the ledger.', Currency::format($posted, (string) ($crossing['currency'] ?: Currency::base()))) : ''
        );
    }

    private static function depart(PDO $db, int $id, array $crossing): string
    {
        // Released is not gone: when the trip has a stop at this post, the truck
        // has left when its driver presses "Leaving" there, not at this click.
        $stop = null;
        if (!empty($crossing['trip_id'])) {
            $post = $db->prepare('SELECT post_name FROM border_posts WHERE id = ?');
            $post->execute([(int) $crossing['border_post_id']]);
            $stop = self::stopFor((int) $crossing['trip_id'], (string) $post->fetchColumn());
        }
        $departed = $stop === null ? date('Y-m-d H:i:s') : ($stop['departed_at'] ?: null);
        $db->prepare('UPDATE border_crossings SET released_at = COALESCE(released_at, NOW()), departed_at = COALESCE(departed_at, ?), hold_reason = NULL WHERE id = ?')->execute([$departed, $id]);
        if (!empty($crossing['shipment_id'])) {
            $db->prepare("UPDATE shipments SET status = 'released' WHERE id = ? AND deleted_at IS NULL AND status NOT IN ('delivered', 'returned', 'cancelled')")->execute([(int) $crossing['shipment_id']]);
            // Released at a border half way along: the truck drives on, so the load
            // is on the road again, not parked at the post. Without this it stayed
            // "Released" and completing the trip never marked it delivered.
            $db->prepare(
                "UPDATE shipments s INNER JOIN trips t ON t.id = s.trip_id
                    SET s.status = 'in_transit'
                  WHERE s.id = ? AND s.status = 'released' AND t.status = 'in_transit'"
            )->execute([(int) $crossing['shipment_id']]);
        }

        $total = self::hoursBetween((string) $crossing['arrived_at'], date('Y-m-d H:i:s'));

        return sprintf(
            '%s has been formally released%s.',
            $crossing['reference'],
            $total === null ? '' : sprintf(', %s after arriving', self::readableHours($total))
        );
    }

    /**
     * Dr what the charges were for, Cr the clearing agent or the bank.
     *
     * A crossing is one document with several costs on it, so it posts like a
     * cheque does: one line per kind of charge, and a single credit for the lot.
     */
    public static function post(int $id): float
    {
        $crossing = self::load($id);
        if ($crossing === null || $crossing['cleared_at'] === null) {
            Posting::unpost('crossing', $id);
            return 0.0;
        }

        $charges = self::charges($id);
        if ($charges === []) {
            Posting::unpost('crossing', $id);
            return 0.0;
        }

        // Charges are booked exactly as paid, in the crossing's own currency:
        // KES at Malaba go into the KES books, RWF at Gatuna into the RWF books.
        // Nothing is converted.
        // Taxes paid for the customer are not our cost: they wait in
        // "Disbursements recoverable" until their invoice claims them back.
        $byAccount = [];
        foreach ($charges as $charge) {
            $code = ($charge['paid_for'] ?? 'us') === 'customer'
                ? self::RECOVERABLE_ACCOUNT
                : (self::CHARGE_ACCOUNTS[(string) $charge['charge_type']] ?? self::FALLBACK_ACCOUNT);
            $byAccount[$code] = round(($byAccount[$code] ?? 0) + (float) $charge['amount'], 2);
        }

        $total = round(array_sum($byAccount), 2);
        if ($total <= 0) {
            return 0.0;
        }

        $lines = [];
        foreach ($byAccount as $code => $amount) {
            // PHP turns a numeric array key into an integer, and Ledger reads an
            // integer as an account id rather than an account code. Casting it
            // back is the difference between posting to "5500" and posting to
            // whatever account happens to be row 5500.
            $lines[] = ['account' => (string) $code, 'debit' => $amount, 'description' => self::postName((int) $crossing['border_post_id'])];
        }

        // An agent is invoiced and paid later; anything else came out of the
        // money the driver was carrying.
        $credit = $crossing['clearing_agent_id'] !== null ? '2000' : '1010';
        $lines[] = ['account' => $credit, 'credit' => $total, 'description' => 'Border charges, ' . $crossing['reference']];

        Ledger::post(
            date('Y-m-d', (int) strtotime((string) $crossing['cleared_at'])),
            sprintf('Border charges at %s, %s', self::postName((int) $crossing['border_post_id']), $crossing['reference']),
            $lines,
            'crossing',
            $id,
            (string) $crossing['reference'],
            (string) ($crossing['declaration_no'] ?? ''),
            strtoupper(trim((string) ($crossing['currency'] ?? ''))) ?: Currency::base()
        );

        self::recharge($crossing, $charges);

        return $total;
    }

    /**
     * The charges paid for the customer become one disbursement on the
     * clearance, so the invoice claims them back at cost. It is already in the
     * books through the clearance, so the disbursement itself posts nothing.
     *
     * @param array<string, mixed> $crossing
     * @param list<array<string, mixed>> $charges
     */
    private static function recharge(array $crossing, array $charges): void
    {
        $db = Database::connection();
        $id = (int) $crossing['id'];
        $theirs = array_values(array_filter($charges, static fn (array $c): bool => ($c['paid_for'] ?? 'us') === 'customer' && (float) $c['amount'] > 0));
        $amount = round(array_sum(array_map(static fn (array $c): float => (float) $c['amount'], $theirs)), 2);

        $existing = $db->prepare('SELECT id, status FROM disbursements WHERE crossing_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1');
        $existing->execute([$id]);
        $current = $existing->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($amount <= 0) {
            if ($current !== null && $current['status'] === 'pending') {
                $db->prepare('UPDATE disbursements SET deleted_at = NOW() WHERE id = ?')->execute([(int) $current['id']]);
            }
            return;
        }
        // Already on an invoice or settled: the paper trail stays as it was.
        if ($current !== null && $current['status'] !== 'pending') {
            return;
        }

        $owner = $db->prepare('SELECT COALESCE(s.customer_id, t.customer_id) FROM border_crossings bc LEFT JOIN shipments s ON s.id = bc.shipment_id LEFT JOIN trips t ON t.id = bc.trip_id WHERE bc.id = ?');
        $owner->execute([$id]);
        $customerId = (int) $owner->fetchColumn();
        if ($customerId <= 0) {
            return;
        }

        $post = self::postName((int) $crossing['border_post_id']);
        $what = implode(', ', array_map(static fn (array $c): string => trim((string) ($c['description'] ?: $c['charge_type'])), $theirs));
        $description = mb_substr(sprintf('%s at %s, declaration %s (%s)', $what, $post, $crossing['declaration_no'] ?: $crossing['reference'], $crossing['reference']), 0, 255);
        $receipts = implode(', ', array_filter(array_map(static fn (array $c): string => trim((string) $c['receipt_no']), $theirs)));
        $values = [
            $customerId, $crossing['trip_id'] ?: null, $crossing['shipment_id'] ?: null, $crossing['clearing_agent_id'] ?: null,
            'Customs duty', $description, $amount, strtoupper((string) ($crossing['currency'] ?: Currency::base())),
            mb_substr($receipts ?: (string) $crossing['declaration_no'], 0, 80), date('Y-m-d', (int) strtotime((string) $crossing['cleared_at'])),
        ];

        if ($current !== null) {
            $db->prepare('UPDATE disbursements SET customer_id = ?, trip_id = ?, shipment_id = ?, supplier_id = ?, category = ?, description = ?, amount = ?, currency = ?, reference = ?, paid_at = ? WHERE id = ?')
               ->execute([...$values, (int) $current['id']]);
            return;
        }

        $code = Reference::next('DSB', 'disbursements', 'disbursement_code');
        $db->prepare("INSERT INTO disbursements (disbursement_code, customer_id, trip_id, shipment_id, supplier_id, category, description, amount, currency, reference, paid_at, method, status, crossing_id, notes)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bank_transfer', 'pending', ?, ?)")
           ->execute([$code, ...$values, $id, sprintf('Raised by clearance %s: taxes paid for the customer, to recharge at cost.', $crossing['reference'])]);
        AuditLog::record('disbursement.from_clearance', 'disbursements', $code, null, ['crossing' => $crossing['reference'], 'amount' => $amount]);
    }

    /** Keeps the charge total on the crossing itself, in its own currency. */
    public static function recalculate(int $id): float
    {
        $crossing = self::load($id);
        if ($crossing === null) {
            return 0.0;
        }

        $total = round(array_sum(array_map(
            static fn (array $c): float => (float) $c['amount'],
            self::charges($id)
        )), 2);

        // No conversion: the total stays in the currency the charges were paid in.
        Database::connection()
            ->prepare('UPDATE border_crossings SET charges_total = ?, exchange_rate = 1, base_amount = ? WHERE id = ?')
            ->execute([$total, $total, $id]);

        return $total;
    }

    /**
     * Papers this crossing is still waiting on.
     *
     * A document row exists because somebody said it is needed here; it is
     * outstanding until it is ticked off.
     *
     * @return list<string>
     */
    public static function missingDocuments(int $id): array
    {
        $statement = Database::connection()->prepare(
            "SELECT document_type FROM border_documents WHERE crossing_id = ? AND status <> 'valid' ORDER BY id"
        );
        $statement->execute([$id]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function outstandingCharges(int $id): int
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM border_charges WHERE crossing_id = ? AND status = 'pending'");
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }

    private static function validDocuments(int $id): int
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM border_documents WHERE crossing_id = ? AND status = 'valid'");
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }

    private static function hasPassedInspection(int $id): bool
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM clearance_inspections WHERE crossing_id = ? AND result = 'passed'");
        $statement->execute([$id]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function hasRelease(int $id): bool
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM clearance_releases WHERE crossing_id = ?');
        $statement->execute([$id]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * How long crossings take, post by post.
     *
     * Averaging arrival to departure over the crossings that finished tells the
     * office which post to plan around, and whether an agent is getting slower.
     */
    public static function dwellByPost(string $from = '', string $to = ''): array
    {
        $from = trim($from) !== '' ? date('Y-m-d', (int) strtotime($from)) : date('Y-m-d', strtotime('-90 days'));
        $to = trim($to) !== '' ? date('Y-m-d', (int) strtotime($to)) : date('Y-m-d');

        $statement = Database::connection()->prepare(
            'SELECT p.post_name, p.typical_hours,
                    COUNT(*) AS crossings,
                    AVG(TIMESTAMPDIFF(MINUTE, c.arrived_at, c.departed_at)) / 60 AS avg_hours,
                    MAX(TIMESTAMPDIFF(MINUTE, c.arrived_at, c.departed_at)) / 60 AS worst_hours,
                    SUM(c.base_amount) AS charges
               FROM border_crossings c
               INNER JOIN border_posts p ON p.id = c.border_post_id
              WHERE c.deleted_at IS NULL
                AND c.arrived_at IS NOT NULL AND c.departed_at IS NOT NULL
                AND DATE(c.arrived_at) BETWEEN ? AND ?
              GROUP BY p.id, p.post_name, p.typical_hours
              ORDER BY avg_hours DESC'
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Trucks sitting at a border right now, longest wait first. */
    public static function atTheBorder(): array
    {
        return Database::connection()->query(
            "SELECT c.reference, c.status, c.hold_reason, c.arrived_at, c.declaration_no,
                    p.post_name, v.plate_number, d.full_name AS driver,
                    TIMESTAMPDIFF(MINUTE, c.arrived_at, NOW()) / 60 AS hours_waiting
               FROM border_crossings c
               INNER JOIN border_posts p ON p.id = c.border_post_id
               LEFT JOIN vehicles v ON v.id = c.vehicle_id
               LEFT JOIN drivers d ON d.id = c.driver_id
              WHERE c.deleted_at IS NULL
                AND c.status IN ('at_border', 'lodged', 'held', 'cleared')
                AND c.departed_at IS NULL
              ORDER BY c.arrived_at"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function load(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM border_crossings WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function charges(int $id): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM border_charges WHERE crossing_id = ? ORDER BY id');
        $statement->execute([$id]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function postName(int $postId): string
    {
        $statement = Database::connection()->prepare('SELECT post_name FROM border_posts WHERE id = ?');
        $statement->execute([$postId]);
        $name = $statement->fetchColumn();

        return $name === false ? 'the border' : (string) $name;
    }

    private static function hoursBetween(?string $from, ?string $to): ?float
    {
        if ($from === null || $from === '' || $to === null || $to === '') {
            return null;
        }

        $start = strtotime($from);
        $end = strtotime($to);

        return $start === false || $end === false || $end < $start ? null : round(($end - $start) / 3600, 1);
    }

    private static function readableHours(float $hours): string
    {
        if ($hours < 1) {
            return sprintf('%d minutes', (int) round($hours * 60));
        }
        if ($hours < 48) {
            return sprintf('%s hours', rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.'));
        }

        return sprintf('%s days', rtrim(rtrim(number_format($hours / 24, 1, '.', ''), '0'), '.'));
    }

    /**
     * A paper the agent sent, once recorded, fills the number it carries on
     * the clearance: the declaration number (and when it was lodged), the T1
     * bond number. Only an empty field, or one still holding this paper's old
     * number, is written: anything typed by hand stays.
     */
    public static function fillFromDocument(int $crossingId, string $type, string $number, ?string $issued, string $previousNumber = ''): void
    {
        $number = trim($number);
        $column = match ($type) {
            'Customs declaration' => 'declaration_no',
            'T1 transit bond' => 'transit_bond_no',
            default => null,
        };
        if ($column === null || $number === '') {
            return;
        }
        Database::connection()->prepare(
            "UPDATE border_crossings SET {$column} = ? WHERE id = ? AND (COALESCE({$column}, '') = '' OR {$column} = ?)"
        )->execute([$number, $crossingId, trim($previousNumber)]);

        if ($column === 'declaration_no' && $issued !== null && $issued !== '') {
            Database::connection()->prepare('UPDATE border_crossings SET lodged_at = COALESCE(lodged_at, ?) WHERE id = ?')
                ->execute([substr($issued, 0, 10) . ' 00:00:00', $crossingId]);
        }
    }

    /**
     * The times the driver gave on his phone at this border ("I have arrived",
     * "Leaving") fill the clearance's arrival and departure, when empty.
     */
    public static function timesFromStops(int $crossingId): void
    {
        $db = Database::connection();
        $crossing = $db->prepare('SELECT bc.id, bc.trip_id, bc.arrived_at, bc.departed_at, bp.post_name FROM border_crossings bc LEFT JOIN border_posts bp ON bp.id = bc.border_post_id WHERE bc.id = ?');
        $crossing->execute([$crossingId]);
        $c = $crossing->fetch(PDO::FETCH_ASSOC);
        if ($c === false || empty($c['trip_id']) || (!empty($c['arrived_at']) && !empty($c['departed_at']))) {
            return;
        }
        $stop = self::stopFor((int) $c['trip_id'], (string) $c['post_name']);
        if ($stop === null) {
            return;
        }
        $db->prepare('UPDATE border_crossings SET arrived_at = COALESCE(arrived_at, ?), departed_at = COALESCE(departed_at, ?) WHERE id = ?')
            ->execute([$stop['actual_arrival_at'] ?: null, $stop['departed_at'] ?: null, $crossingId]);
    }

    /** The driver reached or left a stop: the clearance at that border, if there is one, takes the time. */
    public static function stopReported(int $tripId, string $stopName): void
    {
        $crossings = Database::connection()->prepare('SELECT bc.id, bp.post_name FROM border_crossings bc LEFT JOIN border_posts bp ON bp.id = bc.border_post_id WHERE bc.trip_id = ? AND bc.deleted_at IS NULL');
        $crossings->execute([$tripId]);
        foreach ($crossings->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if (self::sameBorder((string) $c['post_name'], $stopName)) {
                self::timesFromStops((int) $c['id']);
            }
        }
    }

    /** @return array{actual_arrival_at: ?string, departed_at: ?string}|null the trip's stop at this border post */
    private static function stopFor(int $tripId, string $postName): ?array
    {
        $stops = Database::connection()->prepare("SELECT location_name, actual_arrival_at, departed_at FROM trip_stops WHERE trip_id = ? AND stop_type = 'checkpoint' ORDER BY stop_sequence, id");
        $stops->execute([$tripId]);
        foreach ($stops->fetchAll(PDO::FETCH_ASSOC) as $stop) {
            if (self::sameBorder($postName, (string) $stop['location_name'])) {
                return $stop;
            }
        }

        return null;
    }

    /** "Gatuna / Katuna" and "Gatuna border (Uganda / Rwanda)" are the same place. */
    private static function sameBorder(string $postName, string $stopName): bool
    {
        $stop = strtolower($stopName);
        foreach (preg_split('~[\s/,()-]+~', strtolower($postName)) ?: [] as $word) {
            if (strlen($word) >= 4 && str_contains($stop, $word)) {
                return true;
            }
        }

        return false;
    }

    /** "the T1 and the packing list" rather than "T1, packing list". */
    private static function readableList(array $items): string
    {
        if (count($items) === 1) {
            return (string) $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }
}
