<?php

declare(strict_types=1);

namespace app\Repositories;

use app\Core\Database;
use app\Services\AuthService;

/**
 * User repository — data access for user management.
 * Business rules (hashing, validation) live in the controller/service.
 */
final class UserRepository
{
    public function paginate(int $page, int $perPage, string $search = '', string $sort = 'id', string $dir = 'DESC'): array
    {
        $allowed = ['id', 'name', 'email', 'status', 'created_at'];
        $sort = in_array($sort, $allowed, true) ? $sort : 'id';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE u.deleted_at IS NULL';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM users u {$where}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u
             JOIN roles r ON r.id = u.role_id
             {$where}
             ORDER BY {$sort} {$dir}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'   => $rows,
            'total'  => $total,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'page'   => $page,
            'per'    => $perPage,
        ];
    }

    public function findByEmail(string $email): ?array
    {
        return Database::row('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    }

    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO users (role_id, company_id, name, email, password_hash, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['role_id'],
                $data['company_id'] ?? null,
                $data['name'],
                $data['email'],
                $data['password_hash'],
                $data['status'],
                AuthService::id() ?? null,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]
        );
        return (int) Database::lastInsertId();
    }

    public function updatePassword(int $id, string $hash): void
    {
        Database::execute('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [$hash, date('Y-m-d H:i:s'), $id]);
    }
}
