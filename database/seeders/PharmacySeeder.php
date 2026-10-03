<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Seeds pharmacy medicines, suppliers, batches, and inventory items.
 */
final class PharmacySeeder extends Seeder
{
    public static function label(): string { return 'Pharmacy & Inventory'; }
    public static function order(): int { return 145; }

    public static function run(PDO $db): void
    {
        self::truncate($db, 'medicine_returns', 'medicine_dispensing_items', 'medicine_dispensings',
            'medicine_purchase_items', 'medicine_purchases', 'medicine_batches', 'medicines',
            'inventory_purchase_items', 'inventory_purchases', 'inventory_adjustments', 'inventory_items', 'inventory_categories',
            'stock_movements', 'suppliers');

        // Suppliers.
        $suppliers = [
            ['Square Pharmaceuticals', 'Mr. Karim', '+880 2 555 2001', 'supply@squarepharma.com'],
            ['Beximco Pharma', 'Ms. Salma', '+880 2 555 2002', 'orders@beximcopharma.com'],
            ['Incepta Pharmaceuticals', 'Mr. Jahid', '+880 2 555 2003', 'sales@inceptapharma.com'],
            ['MediSupply Ltd', 'Mr. Tahsin', '+880 2 555 2004', 'info@medisupply.com'],
        ];
        $sInsert = $db->prepare('INSERT INTO suppliers (name, contact_person, phone, email, is_active) VALUES (?, ?, ?, ?, 1)');
        $supplierIds = [];
        foreach ($suppliers as $s) { $sInsert->execute($s); $supplierIds[] = (int) $db->lastInsertId(); }

        // Medicines.
        $medicines = [
            ['Paracetamol', 'Paracetamol', 'Napa', 'Analgesic', 'tablet', '500mg', 'piece', 100],
            ['Amoxicillin', 'Amoxicillin', 'Moxacil', 'Antibiotic', 'capsule', '500mg', 'piece', 50],
            ['Omeprazole', 'Omeprazole', 'Seclo', 'PPI', 'capsule', '20mg', 'piece', 50],
            ['Metformin', 'Metformin', 'Glucophage', 'Antidiabetic', 'tablet', '500mg', 'piece', 80],
            ['Salbutamol', 'Salbutamol', 'Ventolin', 'Bronchodilator', 'inhaler', '100mcg', 'piece', 20],
            ['Atorvastatin', 'Atorvastatin', 'Lipitor', 'Statin', 'tablet', '20mg', 'piece', 40],
            ['Cefixime', 'Cefixime', 'Taxim', 'Antibiotic', 'tablet', '400mg', 'piece', 30],
            ['Ranitidine', 'Ranitidine', 'Aciloc', 'Antacid', 'tablet', '150mg', 'piece', 60],
            ['Prednisolone', 'Prednisolone', 'Deltacortil', 'Corticosteroid', 'tablet', '5mg', 'piece', 50],
            ['Normal Saline', 'Sodium Chloride 0.9%', 'NS 1L', 'IV Fluid', 'injection', '1000ml', 'bag', 10],
        ];
        $mInsert = $db->prepare('INSERT INTO medicines (name, generic_name, brand_name, category, dosage_form, strength, unit, reorder_level, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
        $medIds = [];
        foreach ($medicines as $m) { $mInsert->execute($m); $medIds[] = (int) $db->lastInsertId(); }

        // Batches (some expiring, some expired, some low).
        $bInsert = $db->prepare('INSERT INTO medicine_batches (medicine_id, batch_number, expiry_date, quantity_received, quantity_remaining, cost_price, sell_price, supplier_id, received_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $batchData = [
            [0, 'NAPA-2025-001', date('Y-m-d', strtotime('+18 months')), 500, 320, 2.50, 5.00, $supplierIds[0], date('Y-m-d', strtotime('-3 months'))],
            [0, 'NAPA-2024-EXP', date('Y-m-d', strtotime('-2 months')), 200, 50, 2.00, 4.00, $supplierIds[0], date('Y-m-d', strtotime('-14 months'))],
            [1, 'MOX-2025-003', date('Y-m-d', strtotime('+12 months')), 200, 85, 8.00, 15.00, $supplierIds[1], date('Y-m-d', strtotime('-1 month'))],
            [2, 'SECLO-2025-004', date('Y-m-d', strtotime('+24 months')), 150, 120, 4.00, 8.00, $supplierIds[1], date('Y-m-d', strtotime('-2 months'))],
            [3, 'GLP-2025-005', date('Y-m-d', strtotime('+6 months')), 100, 15, 3.00, 6.00, $supplierIds[2], date('Y-m-d', strtotime('-4 months'))],
            [4, 'VENT-2025-006', date('Y-m-d', strtotime('+15 months')), 30, 12, 150.00, 250.00, $supplierIds[0], date('Y-m-d', strtotime('-1 month'))],
            [5, 'LIP-2025-007', date('Y-m-d', strtotime('+20 months')), 100, 75, 5.00, 10.00, $supplierIds[2], date('Y-m-d', strtotime('-3 months'))],
            [6, 'TAX-2024-EXP', date('Y-m-d', strtotime('-1 month')), 50, 10, 20.00, 35.00, $supplierIds[3], date('Y-m-d', strtotime('-13 months'))],
            [7, 'ACI-2025-008', date('Y-m-d', strtotime('+30 months')), 200, 150, 1.50, 3.00, $supplierIds[0], date('Y-m-d', strtotime('-2 months'))],
            [8, 'DEL-2025-009', date('Y-m-d', strtotime('+10 months')), 100, 40, 0.80, 2.00, $supplierIds[1], date('Y-m-d', strtotime('-5 months'))],
            [9, 'NS-2025-010', date('Y-m-d', strtotime('+8 months')), 50, 8, 80.00, 150.00, $supplierIds[3], date('Y-m-d', strtotime('-3 months'))],
        ];
        foreach ($batchData as $b) { $bInsert->execute($b); }

        // Inventory categories.
        $cats = ['Medical Supplies', 'Office Supplies', 'Cleaning', 'Equipment'];
        $cInsert = $db->prepare('INSERT INTO inventory_categories (name, is_active) VALUES (?, 1)');
        $catIds = [];
        foreach ($cats as $c) { $cInsert->execute([$c]); $catIds[] = (int) $db->lastInsertId(); }

        // Inventory items.
        $items = [
            [0, 'Surgical Gloves (box)', 'GLV-001', 'box', 20, 15, 250.00],
            [0, 'Syringes 5ml (box)', 'SYR-005', 'box', 30, 10, 180.00],
            [0, 'Cotton Roll', 'COT-001', 'roll', 50, 5, 45.00],
            [0, 'Bandage 4"', 'BND-004', 'piece', 40, 25, 30.00],
            [1, 'A4 Paper (ream)', 'PAP-A4', 'ream', 10, 3, 350.00],
            [2, 'Bleach (5L)', 'BLH-5L', 'jug', 15, 8, 120.00],
            [3, 'Stethoscope', 'STH-001', 'piece', 5, 2, 1200.00],
        ];
        $iInsert = $db->prepare('INSERT INTO inventory_items (category_id, name, sku, unit, reorder_level, current_stock, unit_value, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        foreach ($items as $i) { $iInsert->execute(array_merge([$catIds[$i[0]]], array_slice($i, 1))); }
    }
}
