<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Department;

/**
 * Department administration — CRUD, archive/restore, head-doctor assignment.
 * All mutations are audit-logged.
 */
final class DepartmentService
{
    private const UPLOAD_DIR = BASE_PATH . '/storage/uploads/departments';
    private const MAX_IMAGE_BYTES = 2097152; // 2 MB
    private const IMAGE_TYPES = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']];

    /** @return array{ok: bool, error?: string, id?: int} */
    public static function create(array $data, Request $request): array
    {
        $slug = Department::slugFrom($data['name']);
        Database::execute(
            'INSERT INTO departments (name, slug, description, head_doctor_id, location, phone, email, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $slug,
                $data['description'] ?? null,
                !empty($data['head_doctor_id']) ? (int) $data['head_doctor_id'] : null,
                $data['location'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                1,
            ]
        );
        $id = Database::lastInsertId();
        AuditService::log('department.created', 'departments', 'create', "Created department {$data['name']} ({$slug}).", [
            'department_id' => $id, 'slug' => $slug,
        ], $request);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok: bool, error?: string} */
    public static function update(int $id, array $data, Request $request): array
    {
        $dept = Department::find($id);
        if ($dept === null) {
            return ['ok' => false, 'error' => 'Department not found.'];
        }
        Database::execute(
            'UPDATE departments SET name = ?, description = ?, head_doctor_id = ?, location = ?, phone = ?, email = ?, is_active = ?
             WHERE id = ?',
            [
                $data['name'],
                $data['description'] ?? null,
                !empty($data['head_doctor_id']) ? (int) $data['head_doctor_id'] : null,
                $data['location'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                !empty($data['is_active']) ? 1 : 0,
                $id,
            ]
        );
        AuditService::log('department.updated', 'departments', 'update', "Updated department {$dept['name']}.", [
            'department_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    public static function archive(int $id, Request $request): array
    {
        $dept = Department::find($id);
        if ($dept === null) {
            return ['ok' => false, 'error' => 'Department not found.'];
        }
        Database::execute('UPDATE departments SET archived_at = NOW(), is_active = 0 WHERE id = ? AND archived_at IS NULL', [$id]);
        AuditService::log('department.archived', 'departments', 'archive', "Archived department {$dept['name']}.", [
            'department_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    public static function restore(int $id, Request $request): array
    {
        $dept = Department::find($id);
        if ($dept === null) {
            return ['ok' => false, 'error' => 'Department not found.'];
        }
        Database::execute('UPDATE departments SET archived_at = NULL, is_active = 1 WHERE id = ?', [$id]);
        AuditService::log('department.restored', 'departments', 'update', "Restored department {$dept['name']}.", [
            'department_id' => $id,
        ], $request);
        return ['ok' => true];
    }
}
