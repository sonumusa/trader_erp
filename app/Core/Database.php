<?php

declare(strict_types=1);

namespace app\Core;

use PDO;

/**
 * Database access layer — a thin PDO wrapper.
 *
 * Every query from the application must go through prepared statements
 * ($sql with ? placeholders + bound values) via query()/row()/value().
 * All parameters are bound, never concatenated, which makes SQL injection
 * practically impossible.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function boot(array $cfg): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    }

    /** @param array<string,string> $cfg */
    public static function connect(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Database not booted.');
        }
        return self::$pdo;
    }

    /** Execute a prepared statement; returns affected row count. */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        return $stmt->rowCount();
    }

    /** Fetch all rows. */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        return $stmt->fetchAll();
    }

    /** Fetch a single row, or null. */
    public static function row(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch a single scalar value. */
    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = self::row($sql, $params);
        if ($row === null) {
            return $default;
        }
        return reset($row);
    }

    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }

    public static function begin(): void
    {
        if (!self::pdo()->inTransaction()) {
            self::pdo()->beginTransaction();
        }
    }

    public static function commit(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    public static function transaction(callable $fn): mixed
    {
        self::begin();
        try {
            $result = $fn();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /** @return array<int,mixed> PDO requires indexed params when using ? placeholders */
    private static function normalizeParams(array $params): array
    {
        if ($params === [] || array_is_list($params)) {
            return array_values($params);
        }
        return array_values($params);
    }
}
