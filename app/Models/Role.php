<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Role model — CRUD, permission assignment and aggregated listing.
 */
final class Role extends Model
{
    protected static function table(): string
    {
        return 'roles';
    }

    /** @return array<int, array<string, mixed>> roles with permission + user counts */
    public static function allWithCounts(): array
    {
        return Database::query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM permission_role pr WHERE pr.role_id = r.id) AS permissions_count,
                    (SELECT COUNT(*) FROM role_user ru WHERE ru.role_id = r.id) AS users_count
             FROM roles r
             ORDER BY r.id ASC"
        );
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return Database::queryOne('SELECT * FROM roles WHERE slug = ? LIMIT 1', [$slug]);
    }

    /** @return array<int, int> permission IDs granted to the role */
    public static function permissionIds(int $roleId): array
    {
        return array_map(
            static fn (array $r): int => (int) $r['permission_id'],
            Database::query('SELECT permission_id FROM permission_role WHERE role_id = ?', [$roleId])
        );
    }

    /** Create a role; slug derived from the name, suffixed on collision. */
    public static function create(string $name, string $description): int
    {
        $slug = self::uniqueSlug(self::slugify($name));
        Database::execute(
            'INSERT INTO roles (name, slug, description, is_system) VALUES (?, ?, ?, 0)',
            [$name, $slug, $description]
        );
        return Database::lastInsertId();
    }

    public static function updateDetails(int $roleId, string $name, string $description): int
    {
        return Database::execute(
            'UPDATE roles SET name = ?, description = ? WHERE id = ?',
            [$name, $description, $roleId]
        );
    }

    /** Replace the role's permission set. @param array<int, int|string> $permissionIds */
    public static function setPermissions(int $roleId, array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        Database::transaction(function () use ($roleId, $permissionIds): void {
            Database::execute('DELETE FROM permission_role WHERE role_id = ?', [$roleId]);
            if ($permissionIds === []) {
                return;
            }
            $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
            $valid = Database::query(
                "SELECT id FROM permissions WHERE id IN ({$placeholders})",
                $permissionIds
            );
            $insert = Database::pdo()->prepare(
                'INSERT IGNORE INTO permission_role (permission_id, role_id) VALUES (?, ?)'
            );
            foreach ($valid as $row) {
                $insert->execute([(int) $row['id'], $roleId]);
            }
        });
    }

    /**
     * Delete a role. Protected: system roles and roles still assigned to
     * users must be cleared first (returns false instead of deleting).
     */
    public static function safeDelete(int $roleId): bool
    {
        $role = self::find($roleId);
        if ($role === null || (int) $role['is_system'] === 1) {
            return false;
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM role_user WHERE role_id = ?', [$roleId]) > 0) {
            return false;
        }
        Database::execute('DELETE FROM roles WHERE id = ?', [$roleId]);
        return true;
    }

    private static function slugify(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
        return $slug !== '' ? $slug : 'role';
    }

    private static function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (self::findBySlug($slug) !== null) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
