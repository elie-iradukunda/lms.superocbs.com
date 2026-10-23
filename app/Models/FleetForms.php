<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * The rules behind the fleet forms of sections 5 and 6: trucks, trailers,
 * drivers, their documents, the document types and MoMo number changes.
 */
final class FleetForms
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    private static function one(string $sql, array $parameters = []): mixed
    {
        $statement = self::db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    /** The trip a truck or driver is busy with, if any (control 4). */
    public static function activeTrip(string $column, int $id, ?int $exceptTrip = null): ?string
    {
        if (!in_array($column, ['vehicle_id', 'driver_id', 'trailer_id'], true) || $id <= 0) {
            return null;
        }
        $code = self::one(
            "SELECT reference_code FROM trips
              WHERE {$column} = ? AND deleted_at IS NULL AND id <> ?
                AND status NOT IN ('delivered', 'cancelled', 'closed')
              ORDER BY id DESC LIMIT 1",
            [$id, $exceptTrip ?? 0]
        );

        return $code === false || $code === null ? null : (string) $code;
    }

    // ------------------------------------------------------------- validate

    /** @return list<string> */
    public static function rules(string $key, array $input, ?int $id): array
    {
        $errors = [];
        $value = static fn (string $name): string => trim((string) ($input[$name] ?? ''));
        $number = static fn (string $name): float => (float) str_replace(',', '', $value($name));

        if ($key === 'vehicles') {
            if ($value('max_payload_t') !== '' && $number('max_payload_t') <= 0) {
                $errors[] = 'The maximum load must be more than zero tonnes.';
            }
            if ($value('fleet') === 'tanker' && $value('cargo_tank_l') === '') {
                $errors[] = 'A tanker needs its cargo tank capacity in litres.';
            }
            if ($value('fleet') === 'container' && $value('container_sizes') === '') {
                $errors[] = 'A container truck needs the container sizes it may carry.';
            }
            if ($value('status') === 'off_road' && $value('off_road_reason') === '') {
                $errors[] = 'Say why the truck is off road (accident, major breakdown...).';
            }
            if ($value('last_service_km') !== '' && $value('mileage') !== '' && $number('last_service_km') > $number('mileage')) {
                $errors[] = 'The last service cannot be at more kilometres than the odometer shows now.';
            }
            if ($id !== null && in_array($value('status'), ['available', 'in_garage', 'off_road', 'inactive'], true)) {
                $trip = self::activeTrip('vehicle_id', $id);
                if ($trip !== null) {
                    $errors[] = sprintf('This truck is on trip %s. Its status follows the trip until the trip is closed.', $trip);
                }
            }
        }

        if ($key === 'trailers' && $value('trailer_type') === 'tanker' && $value('tank_capacity_l') === '') {
            $errors[] = 'A tanker trailer needs its tank capacity in litres.';
        }

        if ($key === 'drivers') {
            if ($value('payment_method') === 'momo' && ($value('momo_number') === '' || $value('momo_name') === '')) {
                $errors[] = 'A driver paid by MoMo needs the MoMo number and the name registered on it.';
            }
            if ($value('nationality') === 'Rwandan' && $value('national_id') !== '' && preg_match('/^\d{16}$/', str_replace(' ', '', $value('national_id'))) !== 1) {
                $errors[] = 'A Rwandan national ID has 16 digits.';
            }
            if ($value('nationality') !== '' && $value('nationality') !== 'Rwandan' && $value('passport_number') === '' && $value('national_id') === '') {
                $errors[] = 'A foreign driver needs a passport number.';
            }
            $digits = preg_replace('/\D/', '', $value('phone')) ?? '';
            if ($digits !== '' && strlen($digits) < 9) {
                $errors[] = 'The phone number looks too short: it is the driver\'s login.';
            } elseif ($digits !== '') {
                $other = self::one(
                    "SELECT full_name FROM drivers WHERE deleted_at IS NULL AND id <> ?
                        AND RIGHT(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), 9) = RIGHT(?, 9) LIMIT 1",
                    [$id ?? 0, $digits]
                );
                if ($other !== false && $other !== null) {
                    $errors[] = sprintf('%s already uses this phone number. Each driver signs in with their own.', $other);
                }
            }
            if (in_array($value('status'), ['left', 'suspended', 'on_leave'], true) && $id !== null) {
                $trip = self::activeTrip('driver_id', $id);
                if ($trip !== null) {
                    $errors[] = sprintf('This driver is on trip %s. Replace the driver on the trip first.', $trip);
                }
            }
            if ($value('status') === 'left' && $value('left_reason') === '') {
                $errors[] = 'Give the reason the driver left.';
            }
        }

        if ($key === 'driver_documents' || $key === 'vehicle_documents') {
            $issued = $key === 'driver_documents' ? $value('issue_date') : $value('issued_on');
            $expires = $key === 'driver_documents' ? $value('expiry_date') : $value('expires_on');
            if ($issued !== '' && $expires !== '' && $expires <= $issued) {
                $errors[] = 'The expiry date must be after the issue date.';
            }
        }

        if ($key === 'vehicle_documents') {
            $owner = $value('owner_type') === 'trailer' ? 'trailer' : 'vehicle';
            if ($owner === 'vehicle' && $value('vehicle_id') === '') {
                $errors[] = 'Choose the truck this document belongs to.';
            }
            if ($owner === 'trailer' && $value('trailer_id') === '') {
                $errors[] = 'Choose the trailer this document belongs to.';
            }
            if ($value('document_type') !== '' && !array_key_exists($value('document_type'), Fleet::documentTypeOptions($owner))) {
                $errors[] = sprintf('That document type is not one a %s carries. Admin can add it under Document types.', $owner === 'trailer' ? 'trailer' : 'truck');
            }
        }

        if ($key === 'document_types' && preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $value('alert_days')) !== 1) {
            $errors[] = 'Warning days are numbers separated by commas, such as 30,14,3.';
        }

        if ($key === 'driver_momo_changes' && $id === null && $value('driver_id') !== '') {
            $pending = self::one("SELECT COUNT(*) FROM driver_momo_changes WHERE driver_id = ? AND status = 'pending'", [(int) $value('driver_id')]);
            if ((int) $pending > 0) {
                $errors[] = 'This driver already has a change waiting for the OM.';
            }
            $current = self::one('SELECT momo_number FROM drivers WHERE id = ?', [(int) $value('driver_id')]);
            if ($current !== false && preg_replace('/\D/', '', (string) $current) === preg_replace('/\D/', '', $value('new_number'))) {
                $errors[] = 'That is already the driver\'s MoMo number.';
            }
        }

        return $errors;
    }

    // ---------------------------------------------------------------- derive

    public static function derive(string $key, array $columns, ?int $id): array
    {
        if ($key === 'vehicles') {
            if (isset($columns['plate_number'])) {
                $columns['plate_number'] = strtoupper(preg_replace('/\s+/', ' ', trim((string) $columns['plate_number'])) ?? '');
            }
            if (($columns['status'] ?? '') !== 'off_road') {
                $columns['off_road_reason'] = null;
            }
            // A truck registered without a service history starts counting
            // from the odometer it arrives with.
            if (array_key_exists('last_service_km', $columns) && $columns['last_service_km'] === null && $id === null) {
                $columns['last_service_km'] = (int) ($columns['mileage'] ?? 0);
            }
            if (array_key_exists('last_service_km', $columns) && $columns['last_service_km'] !== null) {
                $interval = (int) ($columns['service_interval_km'] ?? 0) ?: max(500, Settings::int('service_interval_km', 7000));
                $columns['next_service_km'] = (int) $columns['last_service_km'] + $interval;
            }
        }

        if ($key === 'trailers' && isset($columns['trailer_number'])) {
            $columns['trailer_number'] = strtoupper(trim((string) $columns['trailer_number']));
        }

        if ($key === 'vehicle_documents') {
            if (($columns['owner_type'] ?? 'vehicle') === 'trailer') {
                $columns['vehicle_id'] = null;
                if (!empty($columns['trailer_id'])) {
                    $columns['company_id'] = self::one('SELECT company_id FROM trailers WHERE id = ?', [(int) $columns['trailer_id']]) ?: null;
                }
            } else {
                $columns['trailer_id'] = null;
                if (!empty($columns['vehicle_id'])) {
                    $columns['company_id'] = self::one('SELECT company_id FROM vehicles WHERE id = ?', [(int) $columns['vehicle_id']]) ?: null;
                }
            }
            $current = $id !== null ? (string) self::one('SELECT status FROM vehicle_documents WHERE id = ?', [$id]) : 'valid';
            $columns['status'] = Fleet::paperStatus($columns['expires_on'] ?? null, $current === 'cancelled' ? 'cancelled' : 'valid');
        }

        if ($key === 'driver_documents') {
            if (!empty($columns['driver_id'])) {
                $columns['company_id'] = self::one('SELECT company_id FROM drivers WHERE id = ?', [(int) $columns['driver_id']]) ?: null;
            }
            if (!empty($columns['expiry_date']) && $columns['expiry_date'] < date('Y-m-d') && ($columns['status'] ?? '') !== 'rejected') {
                $columns['status'] = 'expired';
            }
        }

        if ($key === 'drivers') {
            if (($columns['status'] ?? '') === 'left' && empty($columns['left_on'])) {
                $columns['left_on'] = date('Y-m-d');
            }
            if (($columns['payment_method'] ?? '') === 'cash' && $id === null) {
                $columns['momo_number'] = $columns['momo_number'] ?? null;
            }
            if (isset($columns['full_name'])) {
                $columns['full_name'] = trim((string) $columns['full_name']);
            }
        }

        if ($key === 'driver_momo_changes' && $id === null && !empty($columns['driver_id'])) {
            $driver = Fleet::driver((int) $columns['driver_id']);
            $columns['old_number'] = $driver['momo_number'] ?? null;
            $columns['old_name'] = $driver['momo_name'] ?? null;
            $columns['company_id'] = $driver['company_id'] ?? null;
            $columns['requested_by'] = \current_user_id();
            $columns['status'] = 'pending';
        }

        return $columns;
    }

    // ----------------------------------------------------------------- saved

    public static function saved(string $key, int $id, array $columns, array $before): void
    {
        $db = self::db();

        if ($key === 'vehicles') {
            // A trailer is coupled to one truck at a time.
            if (!empty($columns['trailer_id'])) {
                $db->prepare('UPDATE vehicles SET trailer_id = NULL WHERE trailer_id = ? AND id <> ?')->execute([(int) $columns['trailer_id'], $id]);
                $db->prepare('UPDATE trailers SET vehicle_id = ? WHERE id = ?')->execute([$id, (int) $columns['trailer_id']]);
            }
            if (!empty($columns['assigned_driver_id'])) {
                $db->prepare('UPDATE drivers SET default_vehicle_id = ? WHERE id = ?')->execute([$id, (int) $columns['assigned_driver_id']]);
            }
        }

        if ($key === 'trailers' && !empty($columns['vehicle_id'])) {
            $db->prepare('UPDATE vehicles SET trailer_id = NULL WHERE trailer_id = ? AND id <> ?')->execute([$id, (int) $columns['vehicle_id']]);
            $db->prepare('UPDATE vehicles SET trailer_id = ? WHERE id = ?')->execute([$id, (int) $columns['vehicle_id']]);
        }

        if ($key === 'drivers') {
            self::driverSaved($id, $columns, $before);
        }

        if ($key === 'driver_documents' && ($columns['document_type'] ?? '') === 'driving_licence' && !empty($columns['driver_id'])) {
            $db->prepare('UPDATE drivers SET license_number = COALESCE(?, license_number), license_expiry = ? WHERE id = ?')
                ->execute([$columns['document_number'] ?? null, $columns['expiry_date'] ?? null, (int) $columns['driver_id']]);
        }

        if ($key === 'driver_momo_changes' && $before === []) {
            $driver = Fleet::driver((int) $columns['driver_id']);
            if ($driver !== null) {
                Notifier::forCompany($driver['company_id'] !== null ? (int) $driver['company_id'] : null, static fn () => Notifier::toRole(
                    'logistics_manager',
                    'MoMo number change to approve',
                    sprintf('%s asks to be paid on %s (%s) instead of %s.', $driver['full_name'], $columns['new_number'], $columns['new_name'], $driver['momo_number'] ?: 'no number'),
                    'driver_momo_changes',
                    'warning',
                    'driver_momo_changes',
                    (string) $id
                ));
            }
        }
    }

    /**
     * A new driver gets a login (phone number) and a one-time password; a driver
     * who left loses it and frees their truck (section 6).
     */
    private static function driverSaved(int $id, array $columns, array $before): void
    {
        $db = self::db();
        $driver = Fleet::driver($id);
        if ($driver === null) {
            return;
        }

        if (!empty($driver['default_vehicle_id'])) {
            $db->prepare('UPDATE vehicles SET assigned_driver_id = NULL WHERE assigned_driver_id = ? AND id <> ?')->execute([$id, (int) $driver['default_vehicle_id']]);
            $db->prepare('UPDATE vehicles SET assigned_driver_id = ? WHERE id = ?')->execute([$id, (int) $driver['default_vehicle_id']]);
        }

        if ($driver['status'] === 'left') {
            $db->prepare('UPDATE vehicles SET assigned_driver_id = NULL WHERE assigned_driver_id = ?')->execute([$id]);
            $db->prepare('UPDATE drivers SET default_vehicle_id = NULL WHERE id = ?')->execute([$id]);
            if (!empty($driver['user_id'])) {
                $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([(int) $driver['user_id']]);
            }

            return;
        }

        if (!empty($driver['user_id'])) {
            // The account follows the record HR keeps.
            $db->prepare("UPDATE users SET full_name = ?, phone = ?, whatsapp_number = ?, company_id = COALESCE(?, company_id), status = IF(status = 'inactive', 'active', status) WHERE id = ?")
                ->execute([$driver['full_name'], $driver['phone'], $driver['whatsapp_number'], $driver['company_id'], (int) $driver['user_id']]);

            return;
        }

        if (trim((string) $driver['phone']) === '') {
            return;
        }

        $roleId = (int) self::one("SELECT id FROM roles WHERE role_key = 'driver'");
        if ($roleId === 0) {
            return;
        }
        $email = trim((string) ($driver['email'] ?? ''));
        if ($email === '' || (int) self::one('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            $email = 'driver-' . $id . '@drivers.invalid';
        }
        $password = LogisticsData::newOneTimePassword();
        $db->prepare(
            "INSERT INTO users (role_id, company_id, full_name, email, password_hash, phone, whatsapp_number, department, job_title, status, prvg, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Operations', 'Driver', 'active', 2, 1)"
        )->execute([$roleId, $driver['company_id'] ?: \current_company_id(), $driver['full_name'], $email, password_hash($password, PASSWORD_DEFAULT), $driver['phone'], $driver['whatsapp_number']]);
        $userId = (int) $db->lastInsertId();
        $db->prepare('UPDATE drivers SET user_id = ? WHERE id = ?')->execute([$userId, $id]);
        LogisticsData::rememberOneTimePassword($password);
        AuditLog::record('driver.login_created', 'drivers', (string) $driver['full_name'], 'Login created for the phone number ' . $driver['phone']);
    }

    // -------------------------------------------------------------- deleting

    public static function deleting(string $key, array $record): void
    {
        if ($key === 'vehicles' && ($trip = self::activeTrip('vehicle_id', (int) $record['id'])) !== null) {
            throw new \RuntimeException(sprintf('This truck is on trip %s and cannot be removed.', $trip));
        }
        if ($key === 'drivers' && ($trip = self::activeTrip('driver_id', (int) $record['id'])) !== null) {
            throw new \RuntimeException(sprintf('This driver is on trip %s. Set them to Left once the trip is closed instead.', $trip));
        }
        if ($key === 'document_types') {
            $table = $record['applies_to'] === 'driver' ? 'driver_documents' : 'vehicle_documents';
            $used = (int) self::one("SELECT COUNT(*) FROM {$table} WHERE document_type = ? AND deleted_at IS NULL", [$record['type_code']]);
            if ($used > 0) {
                throw new \RuntimeException(sprintf('%d document%s of this type exist. Switch "In use" off instead.', $used, $used === 1 ? '' : 's'));
            }
        }
    }
}
