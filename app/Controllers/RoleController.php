<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Repositories\RoleRepository;
use app\Services\AuditService;
use app\Services\PermissionService;

/**
 * Role management — list roles, view/edit the permission matrix.
 */
final class RoleController extends Controller
{
    private RoleRepository $roles;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->roles = new RoleRepository();
    }

    public function index(): string
    {
        $this->requirePermission('users', 'role', 'view');

        return $this->view('roles/index', [
            'title' => 'Roles & Permissions',
            'roles' => $this->roles->allWithCounts(),
        ])->render();
    }

    public function permissions(int|string $id): string
    {
        $this->requirePermission('users', 'permission', 'edit');
        $id = (int) $id;

        $role = Database::row('SELECT * FROM roles WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$role) {
            flash('error', 'Role not found.');
            return (string) $this->response->redirect($this->request->url('/roles'))->body();
        }

        $catalogue = PermissionService::catalogue();
        $granted = array_flip(PermissionService::permissionsForRole($id));
        $allPerms = Database::query('SELECT id, module, document, action FROM permissions');

        // Map granted permission ids per (module.document.action) key
        $grantedKeys = [];
        foreach ($allPerms as $p) {
            if (isset($granted[$p['id']])) {
                $grantedKeys[$p['module'] . '.' . $p['document'] . '.' . $p['action']] = true;
            }
        }

        return $this->view('roles/permissions', [
            'title'       => 'Permissions — ' . $role['name'],
            'role'        => $role,
            'catalogue'   => $catalogue,
            'grantedKeys' => $grantedKeys,
        ])->render();
    }

    public function savePermissions(int|string $id): mixed
    {
        $this->requirePermission('users', 'permission', 'edit');
        $id = (int) $id;

        $role = Database::row('SELECT * FROM roles WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$role) {
            flash('error', 'Role not found.');
            return $this->response->back();
        }
        if ((int) $role['is_system'] === 1 && $role['slug'] === 'super-admin') {
            flash('error', 'Super Admin always has full access and cannot be modified.');
            return $this->response->back();
        }

        $submitted = $this->request->input('perms', []);
        $keys = is_array($submitted)
            ? array_values(array_filter(array_map('strval', $submitted)))
            : [];

        // Resolve module.document.action keys back to permission ids (server-side)
        $ids = [];
        if ($keys !== []) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $rows = Database::query(
                "SELECT id FROM permissions
                 WHERE CONCAT(module, '.', document, '.', action) IN ({$placeholders})",
                $keys
            );
            $ids = array_map('intval', array_column($rows, 'id'));
        }

        PermissionService::replaceRolePermissions($id, $ids);
        AuditService::log('update', 'users', 'permission', $id, "Updated permission matrix for role {$role['name']}");
        flash('success', 'Permissions updated successfully.');
        return $this->response->redirect($this->request->url('/roles/' . $id . '/permissions'));
    }

}
