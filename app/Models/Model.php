<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Minimal base model — table gateway with prepared statements only.
 */
abstract class Model
{
    /** Child classes must declare their table name. */
    abstract protected static function table(): string;

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::queryOne(
            'SELECT * FROM `' . static::table() . '` WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $allowed = ['ASC', 'DESC'];
        $direction = in_array(strtoupper($direction), $allowed, true) ? strtoupper($direction) : 'ASC';
        return Database::query(
            'SELECT * FROM `' . static::table() . '` ORDER BY `' . str_replace('`', '', $orderBy) . "` {$direction}"
        );
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM `' . static::table() . "` WHERE {$where}",
            $params
        );
    }

    public static function delete(int $id): int
    {
        return Database::execute('DELETE FROM `' . static::table() . '` WHERE id = ?', [$id]);
    }

    /**
     * Paginated fetch with a total count.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function paginate(string $where = '1=1', array $params = [], int $page = 1, int $perPage = 10, string $orderBy = 'id DESC'): array
    {
        $page = max(1, $page);
        $total = static::count($where, $params);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            'SELECT * FROM `' . static::table() . "` WHERE {$where} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }
}
