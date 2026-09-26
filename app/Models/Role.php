<?php

declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * The roles, as rows. The set is fixed — the code turns on their keys: a driver
 * only sees his own trips, the dashboards are laid out per role — so a role can
 * be renamed but not added or removed. What a role may do lives in
 * `role_permissions`.
 */
final class Role
{
    /** @return list<array{id: int, role_key: string, role_name: string, users: int}> */
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT r.id, r.role_key, r.role_name,
                            (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users
                     FROM roles r
                     ORDER BY r.id')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.id, r.role_key, r.role_name, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users
             FROM roles r WHERE r.id = ?'
        );
        $statement->execute([$id]);
        $role = $statement->fetch();

        return $role === false ? null : $role;
    }

    /** Null when the name is acceptable, otherwise what is wrong with it. */
    public static function nameProblem(string $name, ?int $ignoreId = null): ?string
    {
        $length = mb_strlen($name);
        if ($length < 2 || $length > 100) {
            return 'A role name needs between 2 and 100 characters.';
        }

        $statement = Database::connection()->prepare('SELECT id FROM roles WHERE role_name = ? AND id <> ?');
        $statement->execute([$name, $ignoreId ?? 0]);
        if ($statement->fetch() !== false) {
            return sprintf('There is already a role called "%s".', $name);
        }

        return null;
    }

    /** Only the name changes; the key stays, so nothing that refers to it breaks. */
    public static function rename(int $id, string $name): void
    {
        Database::connection()->prepare('UPDATE roles SET role_name = ? WHERE id = ?')->execute([$name, $id]);
    }
}
