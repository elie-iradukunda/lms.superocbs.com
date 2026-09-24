<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\AuditLog;
use Models\Permission;

/**
 * The permission matrix. The "Users and permissions" page used to be user CRUD
 * only: the role map lived in bootstrap.php and could not be changed at all.
 */
final class PermissionController
{
    public function index(): void
    {
        \view('admin/permissions', [
            'title' => 'Role permissions',
            'roles' => $this->roles(),
            'permissions' => Permission::catalogue(),
            'matrix' => $this->matrix(),
            'abilities' => Permission::ABILITIES,
            'canEdit' => \can_edit('users'),
        ]);
    }

    public function update(): void
    {
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $roles = $this->roles();
        $role = null;
        foreach ($roles as $candidate) {
            if ((int) $candidate['id'] === $roleId) {
                $role = $candidate;
                break;
            }
        }

        if ($role === null) {
            Flash::error('That role does not exist.');
            header('Location: ' . \url('permissions'));
            exit;
        }

        if ($role['role_key'] === 'super_admin') {
            Flash::error('Super Admin keeps full access and cannot be restricted. Change another role instead.');
            header('Location: ' . \url('permissions'));
            exit;
        }

        $grants = [];
        $submitted = $_POST['grants'] ?? [];
        $allowed = array_column(Permission::catalogue(), 'permission_key');

        foreach (is_array($submitted) ? $submitted : [] as $permissionKey => $abilities) {
            if (!in_array((string) $permissionKey, $allowed, true) || !is_array($abilities)) {
                continue;
            }
            $clean = array_values(array_intersect(array_map('strval', array_keys($abilities)), Permission::ABILITIES));
            // A right without view is meaningless, so grant view alongside it.
            if ($clean !== [] && !in_array('view', $clean, true)) {
                $clean[] = 'view';
            }
            if ($clean !== []) {
                $grants[(string) $permissionKey] = $clean;
            }
        }

        try {
            Permission::sync($roleId, $grants);
        } catch (\Throwable $exception) {
            Flash::error('The permissions could not be saved: ' . $exception->getMessage());
            header('Location: ' . \url('permissions'));
            exit;
        }

        AuditLog::record('permissions.updated', 'users', $role['role_key'], null, ['permissions' => count($grants)]);
        Flash::success(sprintf('Permissions for %s were saved.', $role['role_name']));

        header('Location: ' . \url('permissions', ['role' => $role['role_key']]));
        exit;
    }

    private function roles(): array
    {
        return Database::connection()
            ->query('SELECT id, role_key, role_name FROM roles ORDER BY id')
            ->fetchAll();
    }

    /** role_key => permission_key => ability => bool */
    private function matrix(): array
    {
        $matrix = [];
        foreach ($this->roles() as $role) {
            $matrix[$role['role_key']] = Permission::forRole((string) $role['role_key']);
        }

        return $matrix;
    }
}
