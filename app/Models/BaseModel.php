<?php

declare(strict_types=1);

namespace app\Models;

/**
 * Base model — small, explicit DB helpers. Models are thin; business
 * logic lives in Services. All data access goes through prepared statements.
 */
abstract class BaseModel
{
    protected const TABLE = '';
    protected const PK = 'id';

    public static function table(): string
    {
        return static::TABLE;
    }

    public static function find(int|string $id): ?array
    {
        return db_row('SELECT * FROM ' . static::TABLE . ' WHERE ' . static::PK . ' = ? AND deleted_at IS NULL LIMIT 1', [$id]);
    }

    public static function findWithTrashed(int|string $id): ?array
    {
        return db_row('SELECT * FROM ' . static::TABLE . ' WHERE ' . static::PK . ' = ? LIMIT 1', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        return db_query('SELECT * FROM ' . static::TABLE . ' WHERE deleted_at IS NULL ORDER BY ' . $orderBy . ' ' . $dir);
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        return (int) db_value('SELECT COUNT(*) FROM ' . static::TABLE . ' WHERE ' . $where, $params);
    }

    /** Insert a row, return its id. */
    public static function create(array $data): int
    {
        $data['created_at'] = $data['created_at'] ?? now();
        $data['updated_at'] = $data['updated_at'] ?? now();
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . static::TABLE . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        db_execute($sql, array_values($data));
        return (int) db_last_insert_id();
    }

    public static function update(int|string $id, array $data): int
    {
        $data['updated_at'] = now();
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $id;
        return db_execute('UPDATE ' . static::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE ' . static::PK . ' = ?', $params);
    }

    /** Soft delete (mark deleted_at). */
    public static function softDelete(int|string $id): int
    {
        return db_execute('UPDATE ' . static::TABLE . ' SET deleted_at = ?, updated_at = ? WHERE ' . static::PK . ' = ?', [now(), now(), $id]);
    }

    /** Hard delete — only for masters without financial impact. */
    public static function hardDelete(int|string $id): int
    {
        return db_execute('DELETE FROM ' . static::TABLE . ' WHERE ' . static::PK . ' = ?', [$id]);
    }
}
