<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The daily tyre and crane logs (sections 8 and 12): each tyre change is
 * logged against the truck (position, date, km) and each crane lift against
 * the container, so the fitter's payment (F6), the crane operator's payment
 * (F7) and tyre life per truck all come from the same record.
 *
 * A log line is paid once: building a request links it, and only a cancelled
 * request releases it (control 7: nothing is billed twice).
 */
final class ServiceLogs
{
    public const KINDS = [
        'tyre' => ['table' => 'tyre_logs', 'form' => 'F6', 'date' => 'changed_on', 'label' => 'Tyre changes', 'payee' => 'Tyre fitter'],
        'crane' => ['table' => 'crane_logs', 'form' => 'F7', 'date' => 'lift_date', 'label' => 'Crane lifts', 'payee' => 'Crane operator'],
    ];

    public const REASONS = ['worn' => 'Worn out', 'puncture' => 'Puncture', 'burst' => 'Burst', 'rotation' => 'Rotation', 'damaged' => 'Damaged', 'other' => 'Other'];

    private static function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Log lines of a fitter or operator not yet on any payment request.
     *
     * @return list<array<string, mixed>>
     */
    public static function unpaid(string $kind, ?int $supplierId = null, ?string $from = null, ?string $to = null): array
    {
        $meta = self::KINDS[$kind] ?? throw new \InvalidArgumentException('Unknown log.');
        $table = $meta['table'];
        $date = $meta['date'];
        $where = ["l.deleted_at IS NULL", 'l.request_id IS NULL'];
        $params = [];
        if ($supplierId) {
            $where[] = 'l.supplier_id = ?';
            $params[] = $supplierId;
        }
        if ($from) {
            $where[] = "l.{$date} >= ?";
            $params[] = $from;
        }
        if ($to) {
            $where[] = "l.{$date} <= ?";
            $params[] = $to;
        }
        if (($condition = Company::condition($table, 'l.company_id')) !== null) {
            $where[] = $condition;
        }
        $extra = $kind === 'tyre' ? ', d.full_name AS driver_name, tr.trailer_number' : '';
        $joins = $kind === 'tyre' ? ' LEFT JOIN drivers d ON d.id = l.driver_id LEFT JOIN trailers tr ON tr.id = l.trailer_id' : '';
        $statement = self::db()->prepare(
            "SELECT l.*, v.plate_number, s.supplier_name{$extra} FROM {$table} l
               LEFT JOIN vehicles v ON v.id = l.vehicle_id LEFT JOIN suppliers s ON s.id = l.supplier_id{$joins}
              WHERE " . implode(' AND ', $where) . " ORDER BY l.{$date}, l.id"
        );
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Builds the payment request (F6 tyre fitter, F7 crane operator) for one
     * supplier and period from the log, links the lines, and returns its id.
     * The OM checks it and sends it into the chain.
     */
    public static function buildRequest(string $kind, int $supplierId, string $from, string $to): int
    {
        $meta = self::KINDS[$kind] ?? throw new \InvalidArgumentException('Unknown log.');
        $statement = self::db()->prepare('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL');
        $statement->execute([$supplierId]);
        $supplier = $statement->fetch(PDO::FETCH_ASSOC);
        if ($supplier === false) {
            throw new \RuntimeException('Choose the ' . strtolower($meta['payee']) . '.');
        }
        if ($from === '' || $to === '' || strtotime($from) === false || strtotime($to) === false || $from > $to) {
            throw new \RuntimeException('Give the period: from and to.');
        }

        $rows = self::unpaid($kind, $supplierId, $from, $to);
        if ($rows === []) {
            throw new \RuntimeException(sprintf('Nothing unpaid for %s between %s and %s.', $supplier['supplier_name'], \human_date($from), \human_date($to)));
        }
        $currencies = array_unique(array_column($rows, 'currency'));
        if (count($currencies) > 1) {
            throw new \RuntimeException('These lines are in more than one currency (' . implode(', ', $currencies) . '). A request is in one currency: shorten the period or correct the log.');
        }

        $payee = [
            'payee_type' => 'supplier', 'payee_id' => $supplierId, 'payee_name' => $supplier['supplier_name'],
            'payment_method' => (string) ($supplier['payment_method'] ?? 'bank'),
            'momo_number' => $supplier['momo_number'] ?? null, 'momo_name' => $supplier['momo_name'] ?? null,
        ];
        $lines = [];
        foreach ($rows as $row) {
            if ($kind === 'tyre') {
                $position = RequestFormConfig::POSITIONS[$row['position']] ?? $row['position'];
                $lines[] = $payee + [
                    'item_code' => 'tyre', 'vehicle_id' => $row['vehicle_id'], 'trailer_id' => $row['trailer_id'], 'driver_id' => $row['driver_id'],
                    'description' => sprintf('%s · %s · %s%s', \human_date((string) $row['changed_on']), trim(($row['plate_number'] ?? '') . ' ' . ($row['trailer_number'] ?? '')), $position, $row['odometer_km'] ? ' · ' . number_format((int) $row['odometer_km']) . ' km' : ''),
                    'quantity' => (float) $row['quantity'], 'unit' => 'tyre', 'unit_price' => (float) $row['unit_price'],
                    'extra' => ['log' => (int) $row['id'], 'date' => $row['changed_on'], 'position' => $row['position']],
                ];
            } else {
                $lines[] = $payee + [
                    'item_code' => 'crane', 'vehicle_id' => $row['vehicle_id'], 'trip_id' => $row['trip_id'],
                    'description' => sprintf('%s · %s · %s · %s ft %s', \human_date((string) $row['lift_date']), $row['plate_number'] ?? '', $row['container_no'], $row['container_size'], $row['operation']),
                    'quantity' => 1, 'unit' => 'lift', 'unit_price' => (float) $row['fee'], 'amount' => (float) $row['fee'],
                    'extra' => ['log' => (int) $row['id'], 'date' => $row['lift_date'], 'container_no' => $row['container_no'], 'operation' => $row['operation'], 'size' => $row['container_size']],
                ];
            }
        }

        $db = self::db();
        $db->beginTransaction();
        try {
            $requestId = RequestForms::create([
                'form_code' => $meta['form'],
                'title' => sprintf('%s — %s, %s to %s', $meta['label'], $supplier['supplier_name'], \human_date($from), \human_date($to)),
                'currency' => $currencies[0],
                'supplier_id' => $supplierId,
                'period_from' => $from,
                'period_to' => $to,
                'notes' => sprintf('Built from the %s log: %d line(s).', $kind, count($rows)),
            ], $lines);
            $ids = implode(',', array_map(static fn (array $r): int => (int) $r['id'], $rows));
            $db->exec("UPDATE {$meta['table']} SET request_id = {$requestId} WHERE id IN ({$ids}) AND request_id IS NULL");
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        AuditLog::record('logs.request_built', $meta['table'], $meta['form'] . ' #' . $requestId, sprintf('%d line(s)', count($rows)));

        return $requestId;
    }

    /** A cancelled F6 or F7 gives its log lines back, to be paid on another. */
    public static function released(array $request): void
    {
        foreach (self::KINDS as $meta) {
            if ($meta['form'] === $request['form_code']) {
                self::db()->prepare("UPDATE {$meta['table']} SET request_id = NULL WHERE request_id = ?")->execute([(int) $request['id']]);
            }
        }
    }

    /**
     * Tyre life per truck and position: the km each tyre ran before the next
     * change in the same place.
     *
     * @return list<array{plate_number:string, position:string, changed_on:string, odometer_km:?int, ran_km:?int, reason:string}>
     */
    public static function tyreLife(?int $vehicleId = null): array
    {
        $where = 'l.deleted_at IS NULL AND l.vehicle_id IS NOT NULL';
        if ($vehicleId) {
            $where .= ' AND l.vehicle_id = ' . (int) $vehicleId;
        }
        if (($condition = Company::condition('tyre_logs', 'l.company_id')) !== null) {
            $where .= ' AND ' . $condition;
        }
        $rows = self::db()->query(
            "SELECT l.vehicle_id, l.position, l.changed_on, l.odometer_km, l.reason, l.brand, v.plate_number
               FROM tyre_logs l INNER JOIN vehicles v ON v.id = l.vehicle_id
              WHERE {$where} ORDER BY v.plate_number, l.position, l.changed_on, l.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        $previous = [];
        foreach ($rows as $row) {
            $key = $row['vehicle_id'] . '|' . $row['position'];
            $before = $previous[$key] ?? null;
            $out[] = [
                'plate_number' => (string) $row['plate_number'],
                'position' => RequestFormConfig::POSITIONS[$row['position']] ?? (string) $row['position'],
                'changed_on' => (string) $row['changed_on'],
                'odometer_km' => $row['odometer_km'] !== null ? (int) $row['odometer_km'] : null,
                'ran_km' => $before !== null && $before['odometer_km'] !== null && $row['odometer_km'] !== null ? (int) $row['odometer_km'] - (int) $before['odometer_km'] : null,
                'reason' => self::REASONS[$row['reason']] ?? (string) $row['reason'],
                'brand' => (string) ($row['brand'] ?? ''),
            ];
            $previous[$key] = $row;
        }

        return $out;
    }

    // ------------------------------------------------------- form rules (Hooks)

    /** Defaults from the rate card, and the truck's km. */
    public static function derive(string $module, array $data, ?int $id = null): array
    {
        if ($module === 'tyre_logs') {
            if ((float) ($data['unit_price'] ?? 0) <= 0 && ($rate = self::rate('tyre', (int) ($data['supplier_id'] ?? 0), 'per_tyre')) !== null) {
                $data['unit_price'] = $rate['amount'];
                $data['currency'] = $rate['currency'];
            }
            if (!empty($data['vehicle_id']) && empty($data['odometer_km'])) {
                $data['odometer_km'] = (int) (Fleet::vehicle((int) $data['vehicle_id'])['mileage'] ?? 0) ?: null;
            }
        }
        if ($module === 'crane_logs') {
            $data['container_no'] = strtoupper(preg_replace('/\s+/', '', (string) ($data['container_no'] ?? '')));
            if ((float) ($data['fee'] ?? 0) <= 0) {
                $variant = ((string) ($data['container_size'] ?? '20') === '40' ? '40ft_' : '20ft_') . ($data['operation'] ?? 'loading');
                if (($rate = self::rate('crane', (int) ($data['supplier_id'] ?? 0), $variant)) !== null) {
                    $data['fee'] = $rate['amount'];
                    $data['currency'] = $rate['currency'];
                }
            }
        }

        return $data;
    }

    /** @return list<string> */
    public static function rules(string $module, array $input, ?int $id): array
    {
        $existing = null;
        if ($id !== null) {
            $statement = self::db()->prepare('SELECT * FROM ' . self::KINDS[$module === 'tyre_logs' ? 'tyre' : 'crane']['table'] . ' WHERE id = ?');
            $statement->execute([$id]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $data = self::derive($module, $input, $id);
        $problems = [];
        if ($existing !== null && !empty($existing['request_id'])) {
            $problems[] = 'This line is already on a payment request and cannot be changed. Cancel that request first.';
        }
        if ($module === 'tyre_logs') {
            if (empty($data['vehicle_id']) && empty($data['trailer_id'])) {
                $problems[] = 'Choose the truck or the trailer the tyre was fitted to.';
            }
            if ((float) ($data['unit_price'] ?? 0) <= 0) {
                $problems[] = 'There is no fitting price: enter it, or add the fitter\'s rate under Crane, tyre and labour rates.';
            }
        }
        if ($module === 'crane_logs') {
            if (preg_match('/^[A-Z]{4}\d{7}$/', (string) ($data['container_no'] ?? '')) !== 1) {
                $problems[] = 'The container number is 4 letters and 7 digits, e.g. MSCU1234567.';
            }
            if ((float) ($data['fee'] ?? 0) <= 0) {
                $problems[] = 'There is no fee: enter it, or add the operator\'s rate under Crane, tyre and labour rates.';
            }
            // Control 7: the same container cannot be lifted (billed) twice.
            $statement = self::db()->prepare('SELECT COUNT(*) FROM crane_logs WHERE deleted_at IS NULL AND container_no = ? AND operation = ? AND lift_date = ? AND id <> ?');
            $statement->execute([(string) ($data['container_no'] ?? ''), (string) ($data['operation'] ?? ''), (string) ($data['lift_date'] ?? ''), (int) ($existing['id'] ?? 0)]);
            if ((int) $statement->fetchColumn() > 0) {
                $problems[] = 'This container is already logged for the same lift on that day.';
            }
        }

        return $problems;
    }

    /** A line already on a payment request cannot be deleted. */
    public static function deleting(string $module, array $record): void
    {
        if (!empty($record['request_id'])) {
            throw new \RuntimeException('This line is on a payment request and cannot be deleted.');
        }
    }

    /** @return array{amount: float, currency: string}|null */
    private static function rate(string $type, int $supplierId, string $variant): ?array
    {
        $statement = self::db()->prepare(
            'SELECT amount, currency FROM service_rates WHERE deleted_at IS NULL AND is_active = 1 AND rate_type = ? AND (variant = ? OR variant IS NULL)
                AND (supplier_id = ? OR supplier_id IS NULL) AND valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE())
              ORDER BY supplier_id IS NULL, variant IS NULL, valid_from DESC LIMIT 1'
        );
        $statement->execute([$type, $variant, $supplierId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['amount' => (float) $row['amount'], 'currency' => (string) $row['currency']];
    }
}
