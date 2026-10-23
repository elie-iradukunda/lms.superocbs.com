<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Shortages (section 13): loaded minus delivered on the delivery note (F8).
 * Above the customer's tolerance for that cargo, the confirmed delivery note
 * raises a deduction request (F9) for the driver, valued at the customer's
 * price. The driver can explain it in the app; Finance verifies, the MD
 * authorizes, and it goes on the driver's next payroll list.
 */
final class Shortages
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    /** The rule for a customer and cargo on a date: the customer's own, else everyone's. */
    public static function rule(int $companyId, ?int $customerId, string $cargoType, ?string $date = null): ?array
    {
        $statement = self::db()->prepare(
            'SELECT * FROM shortage_rules WHERE deleted_at IS NULL AND is_active = 1 AND cargo_type = ?
                AND (customer_id = ? OR customer_id IS NULL) AND (company_id = ? OR company_id IS NULL)
                AND valid_from <= ? AND (valid_to IS NULL OR valid_to >= ?)
              ORDER BY customer_id IS NULL, company_id IS NULL, valid_from DESC LIMIT 1'
        );
        $date ??= date('Y-m-d');
        $statement->execute([$cargoType, $customerId ?? 0, $companyId, $date, $date]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * What a shortage costs under the rule.
     *
     * @return array{tolerance: float, chargeable: float, value: float, currency: string, unit_value: float}|null
     */
    public static function assess(float $loaded, float $shortage, ?array $rule): ?array
    {
        if ($rule === null || $shortage <= 0) {
            return null;
        }
        $tolerance = $rule['tolerance_type'] === 'percent'
            ? round($loaded * (float) $rule['tolerance_value'] / 100, 3)
            : (float) $rule['tolerance_value'];
        if ($shortage <= $tolerance + 0.0005) {
            return ['tolerance' => $tolerance, 'chargeable' => 0.0, 'value' => 0.0, 'currency' => (string) $rule['currency'], 'unit_value' => (float) $rule['unit_value']];
        }
        $chargeable = $rule['charge'] === 'full' ? $shortage : round($shortage - $tolerance, 3);

        return [
            'tolerance' => $tolerance,
            'chargeable' => $chargeable,
            'value' => round($chargeable * (float) $rule['unit_value'], 2),
            'currency' => (string) $rule['currency'],
            'unit_value' => (float) $rule['unit_value'],
        ];
    }

    /**
     * The OM confirmed the delivery note: the shortage is assessed, stored on
     * the note (the invoice reads it) and, above tolerance, a deduction
     * request (F9) is sent for the driver.
     */
    public static function fromDeliveryNote(array $note): ?int
    {
        $payload = is_array($note['payload'] ?? null) ? $note['payload'] : (json_decode((string) ($note['payload'] ?? ''), true) ?: []);
        $shortage = (float) ($payload['shortage'] ?? 0);
        if ($shortage <= 0 || empty($note['trip_id'])) {
            return null;
        }
        $trip = TripLife::find((int) $note['trip_id']);
        if ($trip === null) {
            return null;
        }
        $companyId = (int) $note['company_id'];
        $rule = self::rule($companyId, $trip['customer_id'] ? (int) $trip['customer_id'] : null, (string) ($trip['cargo_type'] ?: 'litres'));
        $assessment = self::assess((float) ($payload['loaded'] ?? 0), $shortage, $rule);

        $payload['assessment'] = $assessment;
        self::db()->prepare('UPDATE form_requests SET payload = ? WHERE id = ?')->execute([json_encode($payload), (int) $note['id']]);

        if ($rule === null) {
            Notifier::forCompany($companyId, static fn () => Notifier::toRole('finance', 'Shortage without a rule', sprintf('%s is short %s %s but %s has no shortage rule for this cargo. Add one under Shortage tolerance.', $trip['reference_code'], $shortage, $payload['unit'] ?? '', $trip['customer_name'] ?: 'the customer'), 'shortage_rules', 'warning', 'form_requests', (string) $note['id']));

            return null;
        }
        if ($assessment === null || $assessment['chargeable'] <= 0 || $assessment['value'] <= 0) {
            return null;
        }

        $unit = (string) ($payload['unit'] ?? '');
        $requestId = RequestForms::create([
            'form_code' => 'F9',
            'company_id' => $companyId,
            'title' => sprintf('Shortage %s — %s', $trip['reference_code'], $trip['driver_name']),
            'currency' => $assessment['currency'],
            'trip_id' => (int) $trip['id'],
            'driver_id' => (int) $trip['driver_id'],
            'vehicle_id' => (int) $trip['vehicle_id'],
            'customer_id' => $trip['customer_id'],
            'parent_request_id' => (int) $note['id'],
            'payload' => ['loaded' => $payload['loaded'] ?? null, 'delivered' => $payload['delivered'] ?? null, 'shortage' => $shortage, 'unit' => $unit, 'tolerance' => $assessment['tolerance']],
            'notes' => sprintf('Loaded %s, delivered %s, short %s %s; tolerance %s %s.', $payload['loaded'] ?? '', $payload['delivered'] ?? '', $shortage, $unit, $assessment['tolerance'], $unit),
        ], [[
            'item_code' => 'shortage', 'trip_id' => (int) $trip['id'], 'vehicle_id' => (int) $trip['vehicle_id'], 'driver_id' => (int) $trip['driver_id'],
            'description' => sprintf('%s %s short above tolerance, at %s', $assessment['chargeable'], $unit, Currency::format($assessment['unit_value'], $assessment['currency'])),
            'quantity' => $assessment['chargeable'], 'unit' => $unit, 'unit_price' => $assessment['unit_value'], 'amount' => $assessment['value'],
        ]]);
        RequestForms::send($requestId);

        $driver = Fleet::driver((int) $trip['driver_id']);
        if (!empty($driver['user_id'])) {
            Notifier::forCompany($companyId, static fn () => Notifier::toUser((int) $driver['user_id'], 'Shortage on your trip', sprintf('%s: %s %s short above tolerance, %s. You can explain it in the app.', $trip['reference_code'], $assessment['chargeable'], $unit, Currency::format($assessment['value'], $assessment['currency'])), 'my-trips', 'warning', 'form_requests', (string) $requestId));
        }

        return $requestId;
    }

    /** @return list<array<string, mixed>> the driver's shortage requests not yet decided or recently decided */
    public static function forDriver(int $driverId): array
    {
        $statement = self::db()->prepare(
            "SELECT r.*, t.reference_code AS trip_code FROM form_requests r LEFT JOIN trips t ON t.id = r.trip_id
              WHERE r.form_code = 'F9' AND r.driver_id = ? AND r.deleted_at IS NULL AND r.status <> 'draft'
                AND (r.status IN ('sent','verified','returned') OR r.authorized_at >= DATE_SUB(NOW(), INTERVAL 60 DAY))
              ORDER BY r.id DESC"
        );
        $statement->execute([$driverId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['payload'] = json_decode((string) ($row['payload'] ?? ''), true) ?: [];
        }

        return $rows;
    }

    /** The driver's side of the story, kept on the request for Finance and the MD. */
    public static function explain(int $requestId, int $driverId, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Write your explanation.');
        }
        $statement = self::db()->prepare("SELECT * FROM form_requests WHERE id = ? AND form_code = 'F9' AND driver_id = ?");
        $statement->execute([$requestId, $driverId]);
        $request = $statement->fetch(PDO::FETCH_ASSOC);
        if ($request === false) {
            throw new \RuntimeException('That shortage is not yours.');
        }
        if (!in_array($request['status'], ['sent', 'verified', 'returned'], true)) {
            throw new \RuntimeException('This shortage has already been decided.');
        }
        $payload = json_decode((string) ($request['payload'] ?? ''), true) ?: [];
        $payload['explanation'] = mb_substr($text, 0, 1000);
        $payload['explained_at'] = date('Y-m-d H:i:s');
        self::db()->prepare('UPDATE form_requests SET payload = ? WHERE id = ?')->execute([json_encode($payload), $requestId]);
        RequestForms::event($requestId, (int) $request['version'], 'note', 'Driver: ' . mb_substr($text, 0, 500));
        Notifier::forCompany((int) $request['company_id'], static fn () => Notifier::toRoles(['finance', 'logistics_manager'], 'Driver explained a shortage', sprintf('%s: %s', $request['request_no'] ?: $request['title'], mb_strimwidth($text, 0, 140, '…')), 'form_requests', 'info', 'form_requests', (string) $requestId));
    }

    // --------------------------------------------------------- form rules

    /** @return list<string> */
    public static function rules(string $key, array $input, ?int $id): array
    {
        $problems = [];
        if ((float) ($input['unit_value'] ?? 0) <= 0) {
            $problems[] = 'Give the customer\'s price of one unit of cargo: the shortage is valued at it.';
        }
        if (($input['tolerance_type'] ?? '') === 'percent' && (float) ($input['tolerance_value'] ?? 0) > 100) {
            $problems[] = 'A percentage tolerance cannot be above 100.';
        }

        return $problems;
    }
}
