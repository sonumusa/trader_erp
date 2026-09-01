<?php

declare(strict_types=1);

namespace app\Repositories;

use app\Core\Database;

/** Role repository — list roles and their user counts. */
final class RoleRepository
{
    /** @return array<int,array<string,mixed>> */
    public function allWithCounts(): array
    {
        return Database::query(
            'SELECT r.*, COUNT(u.id) AS user_count
             FROM roles r
             LEFT JOIN users u ON u.role_id = r.id AND u.deleted_at IS NULL
             WHERE r.deleted_at IS NULL
             GROUP BY r.id
             ORDER BY r.id'
        );
    }
}
