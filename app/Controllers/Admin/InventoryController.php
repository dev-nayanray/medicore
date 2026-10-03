<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\InventoryAdjustment;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\InventoryService;

/**
 * Inventory controller — items, categories, purchases, adjustments.
 */
final class InventoryController extends Controller
{
    public function index(Request $request): string
    {
        $search = trim((string) $request->query('q', ''));
        $items = Database::query(
            'SELECT i.*, c.name AS category_name
             FROM inventory_items i
             LEFT JOIN inventory_categories c ON c.id = i.category_id
             WHERE i.is_active = 1'
            . ($search !== '' ? ' AND (i.name LIKE ? OR i.sku LIKE ?)' : '')
            . ' ORDER BY c.name, i.name',
            $search !== '' ? array_fill(0, 2, '%' . $search . '%') : []
        );
        $counts = InventoryItem::counts();
        $lowStock = InventoryItem::lowStock();
        $categories = InventoryCategory::options();
        $suppliers = Supplier::options();
        $pendingAdjustments = InventoryAdjustment::pending();

        return Response::html(view('admin/inventory/index', [
            'items' => $items, 'counts' => $counts, 'lowStock' => $lowStock,
            'categories' => $categories, 'suppliers' => $suppliers,
            'pendingAdjustments' => $pendingAdjustments, 'search' => $search,
        ]));
    }

    public function storeItem(Request $request): string
    {
        $result = $this->validate($request, [
            'name' => 'required|min:2|max:200', 'category_id' => 'nullable|integer',
            'sku' => 'nullable|max:50', 'unit' => 'nullable|max:30',
            'reorder_level' => 'nullable|integer', 'unit_value' => 'nullable|numeric',
        ]);
        $d = $result['data'];
        Database::execute(
            'INSERT INTO inventory_items (category_id, name, sku, unit, reorder_level, current_stock, unit_value, is_active)
             VALUES (?, ?, ?, ?, ?, 0, ?, 1)',
            [$d['category_id'] ?? null, $d['name'], $d['sku'] ?? null, $d['unit'] ?? 'piece',
             (int) ($d['reorder_level'] ?? 20), (float) ($d['unit_value'] ?? 0)]
        );
        $id = Database::lastInsertId();
        AuditService::log('inventory.item_created', 'inventory', 'create', "Created inventory item {$d['name']}.", ['item_id' => $id], $request);
        Session::flash('success', 'Item created.');
        return Response::redirect(url('/admin/inventory'));
    }

    public function storePurchase(Request $request): string
    {
        $result = $this->validate($request, ['supplier_id' => 'nullable|integer', 'purchase_date' => 'required|date', 'notes' => 'nullable|max:500']);
        $year = date('Y');
        $prefix = "IPUR-{$year}-";
        $max = (int) Database::scalar('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(purchase_code, "-", -1) AS UNSIGNED)), 0) + 1 FROM inventory_purchases WHERE purchase_code LIKE ?', [$prefix . '%']);
        $code = $prefix . str_pad((string) $max, 5, '0', STR_PAD_LEFT);
        Database::execute('INSERT INTO inventory_purchases (purchase_code, supplier_id, purchase_date, status, notes, created_by) VALUES (?, ?, ?, "draft", ?, ?)', [$code, $result['data']['supplier_id'] ?? null, $result['data']['purchase_date'], $result['data']['notes'] ?? null, Auth::id()]);
        $id = Database::lastInsertId();
        Session::flash('success', "Purchase {$code} created as draft.");
        return Response::redirect(url('/admin/inventory/purchases/' . $id));
    }

    public function showPurchase(Request $request, string $id): string
    {
        $purchase = Database::queryOne('SELECT ip.*, s.name AS supplier_name FROM inventory_purchases ip LEFT JOIN suppliers s ON s.id = ip.supplier_id WHERE ip.id = ?', [$id]);
        if ($purchase === null) throw HttpException::notFound('Purchase not found.');
        $purchase['items'] = Database::query('SELECT pi.*, i.name AS item_name FROM inventory_purchase_items pi INNER JOIN inventory_items i ON i.id = pi.item_id WHERE pi.purchase_id = ?', [$id]);
        $inventoryItems = Database::query('SELECT id, name, unit, current_stock FROM inventory_items WHERE is_active = 1 ORDER BY name');
        return Response::html(view('admin/inventory/purchase_show', ['purchase' => $purchase, 'inventoryItems' => $inventoryItems]));
    }

    public function receivePurchase(Request $request, string $id): string
    {
        $itemIds = (array) $request->input('item_id', []);
        $quantities = (array) $request->input('quantity', []);
        $costs = (array) $request->input('unit_cost', []);
        $items = [];
        for ($i = 0; $i < count($itemIds); $i++) {
            if (empty($itemIds[$i])) continue;
            $items[] = ['item_id' => (int) $itemIds[$i], 'quantity' => (int) ($quantities[$i] ?? 0), 'unit_cost' => $costs[$i] ?? '0'];
        }
        $r = InventoryService::receivePurchase((int) $id, $items, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Purchase received — stock updated.' : (string) $r['error']);
        return Response::redirect(url('/admin/inventory/purchases/' . $id));
    }

    public function requestAdjustment(Request $request): string
    {
        $result = $this->validate($request, ['item_id' => 'required|integer', 'adjustment_type' => 'required|in:increase,decrease', 'quantity' => 'required|integer', 'reason' => 'required|min:3|max:300']);
        $r = InventoryService::requestAdjustment((int) $result['data']['item_id'], $result['data']['adjustment_type'], (int) $result['data']['quantity'], $result['data']['reason'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Adjustment requested — pending approval.' : (string) $r['error']);
        return Response::redirect(url('/admin/inventory'));
    }

    public function approveAdjustment(Request $request, string $id): string
    {
        $r = InventoryService::approveAdjustment((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Adjustment approved — stock updated.' : (string) $r['error']);
        return Response::redirect(url('/admin/inventory'));
    }

    public function rejectAdjustment(Request $request, string $id): string
    {
        $r = InventoryService::rejectAdjustment((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Adjustment rejected.' : (string) $r['error']);
        return Response::redirect(url('/admin/inventory'));
    }

    public function showItem(Request $request, string $id): string
    {
        $item = InventoryItem::find((int) $id);
        if ($item === null) throw HttpException::notFound('Item not found.');
        $item['movements'] = \App\Models\StockMovement::forInventoryItem((int) $id);
        return Response::html(view('admin/inventory/show', ['item' => $item]));
    }
}
