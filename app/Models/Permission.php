<?php

declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Role permissions read from `role_permissions`, so the Users and permissions
 * screen can really change what a role may see, create, edit, delete or approve.
 *
 * The legacy hard-coded map is kept only as a fallback for a database that has
 * not been migrated yet; once the table has rows it is never consulted.
 */
final class Permission
{
    public const ABILITIES = ['view', 'create', 'edit', 'delete', 'approve'];

    /** @var array<string, array<string, array<string, bool>>> */
    private static array $cache = [];

    private const LEGACY = [
        'super_admin' => '*',
        'logistics_manager' => ['dashboard', 'vehicles', 'trips', 'deliveries', 'requests', 'drivers', 'maintenance', 'fuel', 'expenses', 'warehouse', 'procurement', 'reports'],
        'fleet_manager' => ['dashboard', 'vehicles', 'drivers', 'maintenance', 'fuel', 'reports'],
        'warehouse_manager' => ['dashboard', 'warehouse', 'procurement', 'requests', 'reports'],
        'driver' => ['dashboard', 'trips', 'deliveries'],
        'finance' => ['dashboard', 'fuel', 'expenses', 'procurement', 'reports'],
        'management' => ['dashboard', 'reports'],
    ];

    /** @return array<string, array<string, bool>> permission key => ability => allowed */
    public static function forRole(string $roleKey): array
    {
        if (isset(self::$cache[$roleKey])) {
            return self::$cache[$roleKey];
        }

        $matrix = [];

        try {
            $statement = Database::connection()->prepare(
                'SELECT rp.permission_key, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_approve
                 FROM role_permissions rp
                 INNER JOIN roles r ON r.id = rp.role_id
                 WHERE r.role_key = ?'
            );
            $statement->execute([$roleKey]);
            foreach ($statement->fetchAll() as $row) {
                $matrix[(string) $row['permission_key']] = [
                    'view' => (bool) $row['can_view'],
                    'create' => (bool) $row['can_create'],
                    'edit' => (bool) $row['can_edit'],
                    'delete' => (bool) $row['can_delete'],
                    'approve' => (bool) $row['can_approve'],
                ];
            }
        } catch (\Throwable) {
            $matrix = [];
        }

        if ($matrix === []) {
            $matrix = self::legacyMatrix($roleKey);
        }

        self::$cache[$roleKey] = $matrix;
        return $matrix;
    }

    public static function allows(string $roleKey, string $permissionKey, string $ability = 'view'): bool
    {
        $matrix = self::forRole($roleKey);
        return (bool) ($matrix[$permissionKey][$ability] ?? false);
    }

    /** @return list<array{permission_key: string, permission_label: string, permission_group: string}> */
    public static function catalogue(): array
    {
        try {
            return Database::connection()
                ->query('SELECT permission_key, permission_label, permission_group FROM permissions ORDER BY sort_order, permission_label')
                ->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Replaces the whole matrix for one role. `$grants` is permission key => list of abilities. */
    public static function sync(int $roleId, array $grants): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $delete = $db->prepare('DELETE FROM role_permissions WHERE role_id = ?');
            $delete->execute([$roleId]);

            $insert = $db->prepare(
                'INSERT INTO role_permissions (role_id, permission_key, can_view, can_create, can_edit, can_delete, can_approve)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($grants as $permissionKey => $abilities) {
                $abilities = array_map('strval', (array) $abilities);
                if ($abilities === []) {
                    continue;
                }
                $insert->execute([
                    $roleId,
                    (string) $permissionKey,
                    (int) in_array('view', $abilities, true),
                    (int) in_array('create', $abilities, true),
                    (int) in_array('edit', $abilities, true),
                    (int) in_array('delete', $abilities, true),
                    (int) in_array('approve', $abilities, true),
                ]);
            }
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        self::$cache = [];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function legacyMatrix(string $roleKey): array
    {
        $routes = self::LEGACY[$roleKey] ?? [];
        $keys = $routes === '*' ? array_column(self::catalogue(), 'permission_key') : $routes;
        $all = $routes === '*';

        $matrix = [];
        foreach ($keys as $key) {
            $matrix[$key] = [
                'view' => true,
                'create' => $all || $key !== 'dashboard',
                'edit' => $all || $key !== 'dashboard',
                'delete' => $all,
                'approve' => $all,
            ];
        }

        return $matrix;
    }
}
