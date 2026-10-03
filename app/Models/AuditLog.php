<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Audit log model — the append-only activity trail.
 */
final class AuditLog extends Model
{
    protected static function table(): string
    {
        return 'audit_logs';
    }

    /**
     * Paginated audit browser with actor names.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function paginateWithActor(string $search = '', string $event = '', int $page = 1, int $perPage = 10): array
    {
        $conditions = ['1=1'];
        $params = [];

        if ($search !== '') {
            $conditions[] = '(a.description LIKE ? OR a.event LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($event !== '') {
            $conditions[] = 'a.event = ?';
            $params[] = $event;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE {$where}",
            $params
        );
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT a.*, u.name AS actor_name, u.email AS actor_email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$where}
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /**
     * Latest activity with actor names (dashboard feed).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 8): array
    {
        return Database::query(
            "SELECT a.id, a.event, a.module, a.action, a.description, a.created_at,
                    u.name AS actor_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT " . max(1, min(50, $limit))
        );
    }

    /** Distinct event names for the filter dropdown. @return array<int, string> */
    public static function distinctEvents(): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['event'],
            Database::query('SELECT DISTINCT event FROM audit_logs ORDER BY event ASC')
        );
    }
}
