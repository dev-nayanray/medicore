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
use App\Models\Medicine;
use App\Models\MedicineDispensing;
use App\Models\Prescription;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\PharmacyService;
use App\Services\SettingService;

/**
 * Pharmacy controller — medicine catalogue, batch management, dispensing,
 * purchases, and stock alerts.
 */
final class PharmacyController extends Controller
{
    public function index(Request $request): string
    {
        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');
        $filter = $request->query('filter', '');

        $medicines = Database::query(
            'SELECT m.*, COALESCE(SUM(mb.quantity_remaining), 0) AS stock
             FROM medicines m
             LEFT JOIN medicine_batches mb ON mb.medicine_id = m.id
             WHERE m.is_active = 1'
            . ($search !== '' ? ' AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ?)' : '')
            . ($category !== '' ? ' AND m.category = ?' : '')
            . ' GROUP BY m.id ORDER BY m.name ASC'
            . ($search !== '' ? '' : ''),
            $search !== '' ? array_fill(0, 3, '%' . $search . '%') : []
        );

        $counts = Medicine::counts();
        $lowStock = Medicine::lowStock();
        $expiring = Medicine::expiringBatches(90);

        return Response::html(view('admin/pharmacy/index', [
            'medicines'  => $medicines,
            'counts'     => $counts,
            'lowStock'   => $lowStock,
            'expiring'   => $expiring,
            'search'     => $search,
            'filter'     => $filter,
        ]));
    }

    public function show(Request $request, string $id): string
    {
        $med = Medicine::find((int) $id);
        if ($med === null) throw HttpException::notFound('Medicine not found.');
        $med['batches'] = Medicine::allBatches((int) $id);
        $med['stock'] = Medicine::totalStock((int) $id);
        $med['movements'] = \App\Models\StockMovement::forMedicine((int) $id);
        return Response::html(view('admin/pharmacy/show', ['medicine' => $med]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'name' => 'required|min:2|max:200', 'generic_name' => 'nullable|max:200',
            'brand_name' => 'nullable|max:200', 'category' => 'nullable|max:100',
            'dosage_form' => 'nullable|in:tablet,capsule,syrup,injection,ointment,drops,inhaler,other',
            'strength' => 'nullable|max:50', 'unit' => 'nullable|max:30',
            'reorder_level' => 'nullable|integer',
        ]);
        $d = $result['data'];
        Database::execute(
            'INSERT INTO medicines (name, generic_name, brand_name, category, dosage_form, strength, unit, reorder_level, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [$d['name'], $d['generic_name'] ?? null, $d['brand_name'] ?? null, $d['category'] ?? null,
             $d['dosage_form'] ?? 'tablet', $d['strength'] ?? null, $d['unit'] ?? 'piece', (int) ($d['reorder_level'] ?? 50)]
        );
        $id = Database::lastInsertId();
        AuditService::log('pharmacy.medicine_created', 'pharmacy', 'create', "Created medicine {$d['name']}.", ['medicine_id' => $id], $request);
        Session::flash('success', 'Medicine added to catalogue.');
        return Response::redirect(url('/admin/pharmacy/' . $id));
    }

    public function dispense(Request $request): string
    {
        $prescriptionId = (int) $request->query('prescription_id', 0) ?: null;
        $patientId = (int) $request->query('patient_id', 0) ?: null;
        $rx = $prescriptionId !== null ? Prescription::find($prescriptionId) : null;

        $medicines = Database::query(
            'SELECT m.*, COALESCE(SUM(mb.quantity_remaining), 0) AS stock
             FROM medicines m LEFT JOIN medicine_batches mb ON mb.medicine_id = m.id
             WHERE m.is_active = 1 GROUP BY m.id HAVING stock > 0 ORDER BY m.name'
        );

        return Response::html(view('admin/pharmacy/dispense', [
            'prescription' => $rx,
            'patientId' => $patientId,
            'medicines' => $medicines,
        ]));
    }

    public function storeDispensing(Request $request): string
    {
        $result = $this->validate($request, [
            'patient_id' => 'required|integer',
            'prescription_id' => 'nullable|integer',
            'consultation_id' => 'nullable|integer',
            'notes' => 'nullable|max:500',
        ]);
        $items = $this->collectDispenseItems($request);
        if ($items === []) {
            Session::flash('error', 'Add at least one medicine.');
            return Response::redirect(url('/admin/pharmacy/dispense'));
        }
        $result['data']['generate_invoice'] = $request->input('generate_invoice', '1');
        $r = PharmacyService::dispense($result['data'], $items, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? "Dispensing {$r['code']} completed." : ($r['error'] ?? 'Dispensing failed.'));
        return Response::redirect($r['ok'] ? url('/admin/pharmacy/dispensing/' . $r['id']) : url('/admin/pharmacy/dispense'));
    }

    public function showDispensing(Request $request, string $id): string
    {
        $dsp = MedicineDispensing::profile((int) $id);
        if ($dsp === null) throw HttpException::notFound('Dispensing record not found.');
        return Response::html(view('admin/pharmacy/dispensing_show', ['dsp' => $dsp]));
    }

    public function returnMedicine(Request $request, string $id): string
    {
        $itemId = (int) $request->input('item_id', 0);
        $qty = (int) $request->input('quantity', 0);
        $reason = (string) $request->input('reason', '');
        $r = PharmacyService::returnMedicine((int) $id, $itemId, $qty, $reason, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Medicine returned.' : (string) $r['error']);
        return Response::redirect(url('/admin/pharmacy/dispensing/' . $id));
    }

    public function purchases(Request $request): string
    {
        $purchases = Database::query(
            'SELECT mp.*, s.name AS supplier_name, u.name AS created_by_name
             FROM medicine_purchases mp
             LEFT JOIN suppliers s ON s.id = mp.supplier_id
             LEFT JOIN users u ON u.id = mp.created_by
             ORDER BY mp.created_at DESC LIMIT 50'
        );
        $suppliers = Supplier::options();
        $medicines = Database::query('SELECT id, name, dosage_form, strength FROM medicines WHERE is_active = 1 ORDER BY name');
        return Response::html(view('admin/pharmacy/purchases', ['purchases' => $purchases, 'suppliers' => $suppliers, 'medicines' => $medicines]));
    }

    public function storePurchase(Request $request): string
    {
        $result = $this->validate($request, [
            'supplier_id' => 'nullable|integer', 'purchase_date' => 'required|date', 'notes' => 'nullable|max:500',
        ]);
        $year = date('Y');
        $prefix = "MPUR-{$year}-";
        $max = (int) Database::scalar('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(purchase_code, "-", -1) AS UNSIGNED)), 0) + 1 FROM medicine_purchases WHERE purchase_code LIKE ?', [$prefix . '%']);
        $code = $prefix . str_pad((string) $max, 5, '0', STR_PAD_LEFT);
        Database::execute(
            'INSERT INTO medicine_purchases (purchase_code, supplier_id, purchase_date, status, notes, created_by)
             VALUES (?, ?, ?, "draft", ?, ?)',
            [$code, $result['data']['supplier_id'] ?? null, $result['data']['purchase_date'], $result['data']['notes'] ?? null, Auth::id()]
        );
        $id = Database::lastInsertId();
        Session::flash('success', "Purchase {$code} created as draft. Add items then receive.");
        return Response::redirect(url('/admin/pharmacy/purchases/' . $id));
    }

    public function showPurchase(Request $request, string $id): string
    {
        $purchase = Database::queryOne(
            'SELECT mp.*, s.name AS supplier_name FROM medicine_purchases mp
             LEFT JOIN suppliers s ON s.id = mp.supplier_id WHERE mp.id = ?', [$id]
        );
        if ($purchase === null) throw HttpException::notFound('Purchase not found.');
        $purchase['items'] = Database::query('SELECT * FROM medicine_purchase_items WHERE purchase_id = ? ORDER BY id', [$id]);
        $medicines = Database::query('SELECT id, name, dosage_form, strength FROM medicines WHERE is_active = 1 ORDER BY name');
        return Response::html(view('admin/pharmacy/purchase_show', ['purchase' => $purchase, 'medicines' => $medicines]));
    }

    public function receivePurchase(Request $request, string $id): string
    {
        $names = (array) $request->input('medicine_name', []);
        if ($names === []) {
            Session::flash('error', 'Add at least one line item.');
            return Response::redirect(url('/admin/pharmacy/purchases/' . $id));
        }
        $items = [];
        $medicineIds = (array) $request->input('medicine_id', []);
        $batchNumbers = (array) $request->input('batch_number', []);
        $expiryDates = (array) $request->input('expiry_date', []);
        $quantities = (array) $request->input('quantity', []);
        $costs = (array) $request->input('unit_cost', []);
        for ($i = 0; $i < count($medicineIds); $i++) {
            if (empty($medicineIds[$i])) continue;
            $items[] = [
                'medicine_id' => (int) $medicineIds[$i],
                'batch_number' => $batchNumbers[$i] ?? '',
                'expiry_date' => $expiryDates[$i] ?? date('Y-m-d'),
                'quantity' => (int) ($quantities[$i] ?? 0),
                'unit_cost' => $costs[$i] ?? '0',
            ];
        }
        $r = PharmacyService::receivePurchase((int) $id, $items, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Purchase received — batches created + stock updated.' : (string) $r['error']);
        return Response::redirect(url('/admin/pharmacy/purchases/' . $id));
    }

    /** @return array<int, array<string, mixed>> */
    private function collectDispenseItems(Request $request): array
    {
        $ids = (array) $request->input('medicine_id', []);
        $qtys = (array) $request->input('quantity', []);
        $instr = (array) $request->input('instructions', []);
        $items = [];
        for ($i = 0; $i < count($ids); $i++) {
            if (empty($ids[$i]) || (int) ($qtys[$i] ?? 0) <= 0) continue;
            $items[] = ['medicine_id' => (int) $ids[$i], 'quantity' => (int) $qtys[$i], 'instructions' => $instr[$i] ?? ''];
        }
        return $items;
    }
}
