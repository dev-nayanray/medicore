<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Centralized PDO connection manager.
 *
 * - Lazy singleton: connection opens on first query.
 * - Emulation OFF -> true native prepared statements (SQL-injection safe).
 * - query()/execute() helpers; transaction() wraps a callback with
 *   automatic commit / roll-back.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }

        return self::$pdo;
    }

    private static function connect(): PDO
    {
        $cfg = Config::get('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        try {
            return new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
        } catch (PDOException $e) {
            Logger::critical('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException(
                'Could not connect to the database. Verify .env credentials and that MySQL is running. '
                . (Config::get('app.debug') ? '[' . $e->getMessage() . ']' : ''),
                0
            );
        }
    }

    /**
     * Run a SELECT and return all rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Run a SELECT and return the first row (or null).
     *
     * @return array<string, mixed>|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Return a single scalar value (first column of first row).
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Run an INSERT / UPDATE / DELETE; returns affected row count.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Transactional wrapper with automatic rollback on any failure.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     * @throws PDOException|RuntimeException
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Check whether a table exists (used for graceful module detection).
     */
    public static function tableExists(string $table): bool
    {
        $result = self::scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [(string) Config::get('database.database'), $table]
        );
        return (int) $result > 0;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }
}
