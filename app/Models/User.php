<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Staff user model — credentials, lifecycle and RBAC resolution.
 */
final class User extends Model
{
    protected static function table(): string
    {
        return 'users';
    }

    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Database::queryOne('SELECT * FROM users WHERE email = ? LIMIT 1', [trim($email)]);
    }

    // ------------------------------------------------------------------
    // RBAC resolution
    // ------------------------------------------------------------------
    /** @return array<int, string> role slugs for the user */
    public static function roleSlugs(int $userId): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['slug'],
            Database::query(
                'SELECT r.slug FROM roles r
                 INNER JOIN role_user ru ON ru.role_id = r.id
                 WHERE ru.user_id = ?',
                [$userId]
            )
        );
    }

    /** @return array<int, string> permission names held via any role */
    public static function permissionNames(int $userId): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['name'],
            Database::query(
                'SELECT DISTINCT p.name FROM permissions p
                 INNER JOIN permission_role pr ON pr.permission_id = p.id
                 INNER JOIN role_user ru ON ru.role_id = pr.role_id
                 WHERE ru.user_id = ?',
                [$userId]
            )
        );
    }

    /** @return array<int, string> direct override permission names ('allow'|'deny') */
    public static function directPermissionNames(int $userId, string $type): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['name'],
            Database::query(
                'SELECT p.name FROM permissions p
                 INNER JOIN permission_user pu ON pu.permission_id = p.id
                 WHERE pu.user_id = ? AND pu.type = ?',
                [$userId, $type]
            )
        );
    }

    /**
     * Replace role assignments from a list of role IDs (validated against
     * the roles table). Super-admin assignment requires the caller to hold
     * the super-admin role themselves (checked by UserService).
     *
     * @param array<int, int|string> $roleIds
     */
    public static function setRoles(int $userId, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        Database::transaction(function () use ($userId, $roleIds): void {
            Database::execute('DELETE FROM role_user WHERE user_id = ?', [$userId]);
            if ($roleIds === []) {
                return;
            }
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $valid = Database::query(
                "SELECT id FROM roles WHERE id IN ({$placeholders})",
                $roleIds
            );
            $insert = Database::pdo()->prepare(
                'INSERT IGNORE INTO role_user (user_id, role_id) VALUES (?, ?)'
            );
            foreach ($valid as $row) {
                $insert->execute([$userId, (int) $row['id']]);
            }
        });
    }

    /**
     * Replace direct permission overrides.
     * Input: [permission_id => 'allow'|'deny', ...] — invalid rows dropped.
     *
     * @param array<int|string, string> $overrides
     */
    public static function setDirectPermissions(int $userId, array $overrides): void
    {
        Database::transaction(function () use ($userId, $overrides): void {
            Database::execute('DELETE FROM permission_user WHERE user_id = ?', [$userId]);
            if ($overrides === []) {
                return;
            }
            $insert = Database::pdo()->prepare(
                'INSERT IGNORE INTO permission_user (user_id, permission_id, type) VALUES (?, ?, ?)'
            );
            foreach ($overrides as $permissionId => $type) {
                if (!in_array($type, ['allow', 'deny'], true)) {
                    continue;
                }
                $exists = Database::scalar('SELECT COUNT(*) FROM permissions WHERE id = ?', [(int) $permissionId]);
                if ((int) $exists === 1) {
                    $insert->execute([$userId, (int) $permissionId, $type]);
                }
            }
        });
    }

    /** @return array<int, array{id:int, name:string, type:string}> */
    public static function directOverrides(int $userId): array
    {
        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['permission_id'],
                'name' => (string) $r['name'],
                'type' => (string) $r['type'],
            ],
            Database::query(
                'SELECT pu.permission_id, p.name, pu.type
                 FROM permission_user pu
                 INNER JOIN permissions p ON p.id = pu.permission_id
                 WHERE pu.user_id = ?
                 ORDER BY p.name',
                [$userId]
            )
        );
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------
    /** @return array<string, mixed> */
    public static function create(array $data): array
    {
        Database::execute(
            'INSERT INTO users (name, email, password_hash, phone, is_active, must_change_password, password_changed_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['name'],
                $data['email'],
                $data['password_hash'],
                $data['phone'] ?? null,
                (int) ($data['is_active'] ?? 1),
                (int) ($data['must_change_password'] ?? 0),
            ]
        );
        return ['id' => Database::lastInsertId()];
    }

    public static function updateDetails(int $userId, string $name, string $email, ?string $phone): int
    {
        return Database::execute(
            'UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?',
            [$name, $email, $phone, $userId]
        );
    }

    public static function updatePassword(int $userId, string $hash, bool $mustChange = false): void
    {
        Database::execute(
            'UPDATE users SET password_hash = ?, must_change_password = ?, password_changed_at = NOW() WHERE id = ?',
            [$hash, $mustChange ? 1 : 0, $userId]
        );
    }

    public static function clearMustChangePassword(int $userId): void
    {
        Database::execute('UPDATE users SET must_change_password = 0 WHERE id = ?', [$userId]);
    }

    public static function setActive(int $userId, bool $active): int
    {
        return Database::execute('UPDATE users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $userId]);
    }

    public static function archive(int $userId): int
    {
        return Database::execute('UPDATE users SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL', [$userId]);
    }

    public static function restore(int $userId): int
    {
        return Database::execute('UPDATE users SET archived_at = NULL WHERE id = ?', [$userId]);
    }

    public static function updateLastLogin(int $userId): void
    {
        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    }

    public static function updateProfile(int $userId, string $name, ?string $phone): void
    {
        Database::execute('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone, $userId]);
    }

    // ------------------------------------------------------------------
    // Listing with filters
    // ------------------------------------------------------------------
    /**
     * Filtered, paginated directory. Status: active|inactive|archived|all.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, perPage: int}
     */
    public static function paginateWithRoles(string $search = '', string $role = '', string $status = 'not_archived', int $page = 1, int $perPage = 10): array
    {
        $conditions = ['1=1'];
        $params = [];

        if ($status === 'archived') {
            $conditions[] = 'u.archived_at IS NOT NULL';
        } elseif ($status === 'active') {
            $conditions[] = 'u.archived_at IS NULL AND u.is_active = 1';
        } elseif ($status === 'inactive') {
            $conditions[] = 'u.archived_at IS NULL AND u.is_active = 0';
        } elseif ($status === 'all') {
            // no filter
        } else { // default: hide archived
            $conditions[] = 'u.archived_at IS NULL';
        }

        if ($search !== '') {
            $conditions[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        if ($role !== '') {
            $conditions[] = 'u.id IN (SELECT ru.user_id FROM role_user ru INNER JOIN roles r ON r.id = ru.role_id WHERE r.slug = ?)';
            $params[] = $role;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT u.*,
                    COALESCE(
                        (SELECT GROUP_CONCAT(r.name SEPARATOR ', ')
                         FROM roles r INNER JOIN role_user ru ON ru.role_id = r.id
                         WHERE ru.user_id = u.id), '—'
                    ) AS roles_label,
                    (SELECT COUNT(*) FROM roles r INNER JOIN role_user ru ON ru.role_id = r.id
                     WHERE ru.user_id = u.id) AS roles_count,
                    (SELECT COUNT(*) FROM permission_user pu WHERE pu.user_id = u.id) AS overrides_count
             FROM users u
             WHERE {$where}
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }
}
