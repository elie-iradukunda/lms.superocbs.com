<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\Permission;
use Models\Role;

/**
 * The permission matrix. The "Users and permissions" page used to be user CRUD
 * only: the role map lived in bootstrap.php and could not be changed at all.
 * The fixed set of roles can be renamed here as well.
 */
final class PermissionController
{
    public function index(): void
    {
        \view('admin/permissions', [
            'title' => 'Role permissions',
            'roles' => Role::all(),
            'permissions' => Permission::catalogue(),
            'matrix' => $this->matrix(),
            'abilities' => Permission::ABILITIES,
            'canEdit' => \can_edit('users'),
        ]);
    }

    public function update(): void
    {
        $role = Role::find((int) ($_POST['role_id'] ?? 0));

        if ($role === null) {
            $this->back('That role does not exist.');
        }

        if ($role['role_key'] === 'super_admin') {
            $this->back('Super Admin keeps full access and cannot be restricted. Change another role instead.');
        }

        $grants = [];
        $submitted = $_POST['grants'] ?? [];
        $allowed = array_column(Permission::catalogue(), 'permission_key');

        foreach (is_array($submitted) ? $submitted : [] as $permissionKey => $abilities) {
            if (!in_array((string) $permissionKey, $allowed, true) || !is_array($abilities)) {
                continue;
            }
            $clean = array_values(array_intersect(
                array_map('strval', array_keys($abilities)),
                Permission::applicable((string) $permissionKey)
            ));
            // A right without view is meaningless, so grant view alongside it.
            if ($clean !== [] && !in_array('view', $clean, true)) {
                $clean[] = 'view';
            }
            if ($clean !== []) {
                $grants[(string) $permissionKey] = $clean;
            }
        }

        try {
            Permission::sync((int) $role['id'], $grants);
        } catch (\Throwable $exception) {
            $this->back('The permissions could not be saved: ' . $exception->getMessage(), $role['role_key']);
        }

        AuditLog::record('permissions.updated', 'users', $role['role_key'], null, ['permissions' => count($grants)]);
        Flash::success(sprintf('Permissions for %s were saved.', $role['role_name']));
        $this->to($role['role_key']);
    }

    public function renameRole(array $params): void
    {
        $role = Role::find((int) ($params['id'] ?? 0));
        if ($role === null) {
            $this->back('That role does not exist.');
        }

        $name = trim((string) ($_POST['role_name'] ?? ''));
        if (($problem = Role::nameProblem($name, (int) $role['id'])) !== null) {
            $this->back($problem, $role['role_key']);
        }

        Role::rename((int) $role['id'], $name);
        AuditLog::record('roles.renamed', 'users', $role['role_key'], null, ['from' => $role['role_name'], 'to' => $name]);
        Flash::success(sprintf('%s is now called %s.', $role['role_name'], $name));
        $this->to($role['role_key']);
    }

    /** role_key => permission_key => ability => bool */
    private function matrix(): array
    {
        $matrix = [];
        foreach (Role::all() as $role) {
            $matrix[$role['role_key']] = Permission::forRole((string) $role['role_key']);
        }

        return $matrix;
    }

    private function back(string $message, ?string $roleKey = null): never
    {
        Flash::error($message);
        $this->to($roleKey);
    }

    private function to(?string $roleKey): never
    {
        header('Location: ' . ($roleKey === null ? \url('permissions') : \url('permissions', ['role' => $roleKey])));
        exit;
    }
}
