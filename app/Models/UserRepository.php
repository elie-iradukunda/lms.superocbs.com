<?php

declare(strict_types=1);

namespace Models;

class UserRepository extends BaseModel
{
    public function activeLoginAccounts(): array
    {
        $rows = $this->fetchAll(
            'SELECT users.id, users.full_name, users.email, roles.role_key, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.status = "active" AND users.deleted_at IS NULL
             ORDER BY FIELD(roles.role_key, "super_admin", "logistics_manager", "fleet_manager", "warehouse_manager", "driver", "finance", "management"), users.id'
        );

        $accounts = [];
        foreach ($rows as $row) {
            $roleKey = (string) $row['role_key'];
            if (isset($accounts[$roleKey])) {
                continue;
            }

            $accounts[$roleKey] = [
                'id' => (int) $row['id'],
                'name' => $row['full_name'],
                'email' => $row['email'],
                'role_name' => $row['role_name'],
            ];
        }

        return $accounts;
    }

    public function findActiveByEmail(string $email): ?array
    {
        return $this->fetchOne(
            'SELECT users.*, roles.role_key, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.email = ? AND users.status <> "inactive" AND users.deleted_at IS NULL
             LIMIT 1',
            [$email]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT users.*, roles.role_key, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = ? AND users.deleted_at IS NULL
             LIMIT 1',
            [$id]
        );
    }

    public function touchLastLogin(int $userId): void
    {
        $statement = $this->db->prepare(
            'UPDATE users SET last_login_at = NOW(), failed_login_count = 0, locked_until = NULL WHERE id = ?'
        );
        $statement->execute([$userId]);
    }

    // ------------------------------------------------------------- security

    /**
     * Whether this account is currently locked out after too many failed logins.
     * `users.status = 'locked'` used to exist but nothing ever set it.
     *
     * @return array{locked: bool, minutes: int}
     */
    public function lockState(?array $user): array
    {
        if ($user === null) {
            return ['locked' => false, 'minutes' => 0];
        }

        if ((string) $user['status'] === 'locked' && $user['locked_until'] === null) {
            return ['locked' => true, 'minutes' => 0];
        }

        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            return ['locked' => true, 'minutes' => (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60)];
        }

        return ['locked' => false, 'minutes' => 0];
    }

    /** Counts a failed attempt and locks the account once the threshold is passed. */
    public function registerFailure(string $email, ?array $user): void
    {
        $this->logAttempt($email, false);

        if ($user === null) {
            return;
        }

        $maximum = max(3, Settings::int('max_login_attempts', 5));
        $minutes = max(1, Settings::int('lockout_minutes', 15));
        $count = (int) $user['failed_login_count'] + 1;

        if ($count >= $maximum) {
            $statement = $this->db->prepare(
                'UPDATE users SET failed_login_count = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), status = "locked" WHERE id = ?'
            );
            $statement->execute([$count, $minutes, (int) $user['id']]);
            return;
        }

        $statement = $this->db->prepare('UPDATE users SET failed_login_count = ? WHERE id = ?');
        $statement->execute([$count, (int) $user['id']]);
    }

    /** Clears a lockout whose window has passed, so the account unlocks itself. */
    public function clearExpiredLock(array $user): array
    {
        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) <= time()) {
            $statement = $this->db->prepare(
                'UPDATE users SET locked_until = NULL, failed_login_count = 0, status = IF(status = "locked", "active", status) WHERE id = ?'
            );
            $statement->execute([(int) $user['id']]);
            $user['locked_until'] = null;
            $user['failed_login_count'] = 0;
            $user['status'] = $user['status'] === 'locked' ? 'active' : $user['status'];
        }

        return $user;
    }

    public function logAttempt(string $email, bool $succeeded): void
    {
        try {
            $statement = $this->db->prepare('INSERT INTO login_attempts (email, ip_address, succeeded) VALUES (?, ?, ?)');
            $statement->execute([mb_substr($email, 0, 190), $_SERVER['REMOTE_ADDR'] ?? null, $succeeded ? 1 : 0]);
        } catch (\Throwable) {
            // Attempt logging must never block a login.
        }
    }

    // ------------------------------------------------------------- passwords

    public function setPassword(int $userId, string $plain, bool $mustChange = false): void
    {
        $statement = $this->db->prepare(
            'UPDATE users SET password_hash = ?, password_changed_at = NOW(), must_change_password = ? WHERE id = ?'
        );
        $statement->execute([password_hash($plain, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $userId]);
    }

    public function verifyPassword(int $userId, string $plain): bool
    {
        $hash = $this->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$userId]);

        return $hash !== null && password_verify($plain, (string) $hash['password_hash']);
    }

    /** Creates a single-use reset token and returns the plain value to hand to the user. */
    public function createResetToken(int $userId, int $minutes = 60): string
    {
        $token = bin2hex(random_bytes(32));
        $statement = $this->db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
        );
        $statement->execute([$userId, hash('sha256', $token), $minutes]);

        return $token;
    }

    public function consumeResetToken(string $token): ?int
    {
        $row = $this->fetchOne(
            'SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );

        if ($row === null) {
            return null;
        }

        $statement = $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $statement->execute([(int) $row['id']]);

        return (int) $row['user_id'];
    }

    public function updateProfile(
        int $userId,
        string $fullName,
        string $phone,
        string $jobTitle,
        bool $notifyByEmail = true,
        ?string $avatarPath = null
    ): void {
        $statement = $this->db->prepare(
            'UPDATE users SET full_name = ?, phone = ?, job_title = ?, notify_by_email = ?, avatar_path = ? WHERE id = ?'
        );
        $statement->execute([
            $fullName,
            $phone === '' ? null : $phone,
            $jobTitle === '' ? null : $jobTitle,
            $notifyByEmail ? 1 : 0,
            $avatarPath === '' ? null : $avatarPath,
            $userId,
        ]);
    }
}
