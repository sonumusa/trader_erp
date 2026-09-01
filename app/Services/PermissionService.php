<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * RBAC permission service.
 *
 * Structure: Module → Document → Action (e.g. sales → sales_invoice → create).
 * A role may also carry the special wildcard "*" (full access), used by
 * Super Admin. All authorization is enforced server-side via can().
 */
final class PermissionService
{
    private static ?array $cache = null;

    /** Check if the current user can perform $action on $document in $module. */
    public static function can(string $module, string $document, string $action, ?int $userId = null): bool
    {
        $perms = self::allForUser($userId);

        // Wildcard = full access
        if (isset($perms['*'])) {
            return true;
        }
        return isset($perms[$module . '.' . $document . '.' . $action])
            || isset($perms[$module . '.' . $document . '.*'])
            || isset($perms[$module . '.*']);
    }

    /** @return array<string,true> set of permission keys for a user */
    public static function allForUser(?int $userId = null): array
    {
        $userId = $userId ?? AuthService::id();
        if ($userId === null) {
            return [];
        }
        if (self::$cache !== null && isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        // Super admins hold the wildcard — full access to every module.
        $isSuper = (int) Database::value('SELECT is_super_admin FROM users WHERE id = ?', [$userId]) === 1;
        if ($isSuper) {
            self::$cache[$userId] = ['*' => true];
            return self::$cache[$userId];
        }

        $rows = Database::query(
            'SELECT DISTINCT p.module, p.document, p.action
             FROM permission_role pr
             JOIN permissions p ON p.id = pr.permission_id
             JOIN users u ON u.role_id = pr.role_id
             WHERE u.id = ?
             UNION
             SELECT DISTINCT p.module, p.document, p.action
             FROM permissions p
             JOIN roles r ON r.slug = \'super-admin\'
             JOIN permission_role pr ON pr.role_id = r.id AND pr.permission_id = p.id
             WHERE EXISTS (SELECT 1 FROM users u2 WHERE u2.id = ? AND u2.is_super_admin = 1)',
            [$userId, $userId]
        );

        $set = [];
        foreach ($rows as $row) {
            $set[$row['module'] . '.' . $row['document'] . '.' . $row['action']] = true;
        }
        self::$cache[$userId] = $set;
        return $set;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /** Return true when the user has any permission within a module. */
    public static function moduleAccessible(string $module): bool
    {
        if (AuthService::isSuperAdmin()) {
            return true;
        }
        foreach (self::allForUser() as $key => $_) {
            if (str_starts_with($key, $module . '.')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Permission catalogue — module → documents → actions.
     * Loaded from config/permissions.php (shared with the installer).
     */
    public static function catalogue(): array
    {
        return require APP_ROOT . '/config/permissions.php';
    }

    /** All distinct (module, document, action) rows. */
    public static function allPermissions(): array
    {
        return Database::query('SELECT * FROM permissions ORDER BY module, document, action');
    }

    /** @return array<int,int> permission ids granted to a role */
    public static function permissionsForRole(int $roleId): array
    {
        return array_map('intval', array_column(
            Database::query('SELECT permission_id FROM permission_role WHERE role_id = ?', [$roleId]),
            'permission_id'
        ));
    }

    public static function replaceRolePermissions(int $roleId, array $permissionIds): void
    {
        Database::execute('DELETE FROM permission_role WHERE role_id = ?', [$roleId]);
        $stmt = Database::pdo()->prepare('INSERT INTO permission_role (role_id, permission_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
            $stmt->execute([$roleId, $pid]);
        }
        self::clearCache();
    }
}
