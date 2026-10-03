<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Department model — directory with doctor/staff counts, slug generation,
 * and soft-archive support.
 */
final class Department extends Model
{
    protected static function table(): string
    {
        return 'departments';
    }

    /** Generate a URL-safe slug from a name, with a collision-safe suffix. */
    public static function slugFrom(string $name): string
    {
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        if ($base === '') {
            $base = 'dept';
        }
        $slug = $base;
        $i = 1;
        while ((int) Database::scalar('SELECT COUNT(*) FROM departments WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Directory with aggregated member counts.
     *
     * @param array{search?:string, status?:string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function directory(array $filters, int $page = 1, int $perPage = 10): array
    {
        $conditions = ['1=1'];
        $params = [];

        $status = $filters['status'] ?? 'active';
        if ($status === 'archived') {
            $conditions[] = 'd.archived_at IS NOT NULL';
        } elseif ($status === 'all') {
            // no filter
        } else {
            $conditions[] = 'd.archived_at IS NULL';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(d.name LIKE ? OR d.slug LIKE ? OR d.location LIKE ? OR d.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $where = implode(' AND ', $conditions);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM departments d WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT d.*,
                    u.name AS head_doctor_name,
                    (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id AND doc.archived_at IS NULL) AS doctor_count,
                    (SELECT COUNT(*) FROM staff_profiles sp WHERE sp.department_id = d.id AND sp.archived_at IS NULL) AS staff_count
             FROM departments d
             LEFT JOIN users u ON u.id = d.head_doctor_id
             WHERE {$where}
             ORDER BY d.archived_at IS NULL DESC, d.name ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    /** Department with aggregated counts and member lists. */
    public static function withMembers(int $id): ?array
    {
        $dept = self::find($id);
        if ($dept === null) {
            return null;
        }
        $dept['head_doctor_name'] = (string) Database::scalar(
            'SELECT u.name FROM departments d LEFT JOIN users u ON u.id = d.head_doctor_id WHERE d.id = ?',
            [$id]
        );
        $dept['doctors'] = Database::query(
            'SELECT doc.*, u.name AS user_name, u.email, u.phone
             FROM doctors doc
             INNER JOIN users u ON u.id = doc.user_id
             WHERE doc.department_id = ? AND doc.archived_at IS NULL
             ORDER BY u.name',
            [$id]
        );
        $dept['staff'] = Database::query(
            'SELECT sp.*, u.name AS user_name, u.email, u.phone
             FROM staff_profiles sp
             INNER JOIN users u ON u.id = sp.user_id
             WHERE sp.department_id = ? AND sp.archived_at IS NULL
             ORDER BY u.name',
            [$id]
        );
        return $dept;
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        return [
            'total'    => (int) Database::scalar('SELECT COUNT(*) FROM departments'),
            'active'   => (int) Database::scalar('SELECT COUNT(*) FROM departments WHERE archived_at IS NULL AND is_active = 1'),
            'archived' => (int) Database::scalar('SELECT COUNT(*) FROM departments WHERE archived_at IS NOT NULL'),
        ];
    }

    /** Departments as a select-friendly list (id => name). */
    public static function options(): array
    {
        return Database::query(
            'SELECT id, name FROM departments WHERE archived_at IS NULL AND is_active = 1 ORDER BY name'
        );
    }
}
