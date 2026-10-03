<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Service;
use App\Services\AuditService;

/**
 * Hospital services CRUD — configurable catalogue with pricing.
 */
final class ServiceController extends Controller
{
    public function index(Request $request): string
    {
        $services = Database::query('SELECT * FROM services ORDER BY category ASC, name ASC');
        $byCategory = [];
        foreach ($services as $s) {
            $byCategory[$s['category']][] = $s;
        }
        return Response::html(view('admin/services/index', [
            'byCategory' => $byCategory,
            'categories' => Service::CATEGORIES,
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'name'        => 'required|min:2|max:150',
            'category'    => 'required|in:consultation,laboratory,procedure,medicine,admission,other',
            'price'       => 'required|numeric',
            'description' => 'nullable|max:500',
            'is_active'   => 'nullable',
        ]);
        $data = $result['data'];
        Database::execute(
            'INSERT INTO services (name, category, description, price, is_active) VALUES (?, ?, ?, ?, ?)',
            [
                $data['name'], $data['category'], $data['description'] ?? null,
                round((float) $data['price'], 2),
                !empty($data['is_active']) ? 1 : 1, // default active
            ]
        );
        $id = Database::lastInsertId();
        AuditService::log('service.created', 'billing', 'create', "Created service {$data['name']} ({$data['category']}).", [
            'service_id' => $id,
        ], $request);
        Session::flash('success', 'Service created.');
        return Response::redirect(url('/admin/services'));
    }

    public function update(Request $request, string $id): string
    {
        $svc = Service::find((int) $id);
        if ($svc === null) {
            throw HttpException::notFound('Service not found.');
        }
        $result = $this->validate($request, [
            'name'        => 'required|min:2|max:150',
            'category'    => 'required|in:consultation,laboratory,procedure,medicine,admission,other',
            'price'       => 'required|numeric',
            'description' => 'nullable|max:500',
            'is_active'   => 'nullable',
        ]);
        $data = $result['data'];
        Database::execute(
            'UPDATE services SET name = ?, category = ?, description = ?, price = ?, is_active = ? WHERE id = ?',
            [
                $data['name'], $data['category'], $data['description'] ?? null,
                round((float) $data['price'], 2),
                !empty($data['is_active']) ? 1 : 0,
                $id,
            ]
        );
        AuditService::log('service.updated', 'billing', 'update', "Updated service {$svc['name']}.", [
            'service_id' => (int) $id,
        ], $request);
        Session::flash('success', 'Service updated.');
        return Response::redirect(url('/admin/services'));
    }

    public function destroy(Request $request, string $id): string
    {
        $svc = Service::find((int) $id);
        if ($svc === null) {
            throw HttpException::notFound('Service not found.');
        }
        // Soft-deactivate instead of hard-delete (preserves invoice item references).
        Database::execute('UPDATE services SET is_active = 0 WHERE id = ?', [$id]);
        AuditService::log('service.deactivated', 'billing', 'delete', "Deactivated service {$svc['name']}.", [
            'service_id' => (int) $id,
        ], $request);
        Session::flash('success', 'Service deactivated.');
        return Response::redirect(url('/admin/services'));
    }
}
