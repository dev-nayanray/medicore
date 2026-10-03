<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Models\Expense;

/**
 * Expense management — CRUD for hospital operational spending.
 * All mutations are audit-logged.
 */
final class ExpenseService
{
    /** @return array{ok: bool, error?: string, id?: int, code?: string} */
    public static function create(array $data, Request $request): array
    {
        $code = Expense::nextCode();
        Database::execute(
            'INSERT INTO expenses (expense_code, category, description, amount, expense_date, paid_to, payment_method, reference_number, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code, $data['category'], $data['description'], round((float) $data['amount'], 2),
                $data['expense_date'], $data['paid_to'] ?? null,
                $data['payment_method'] ?? 'cash', $data['reference_number'] ?? null,
                Auth::id(),
            ]
        );
        $id = Database::lastInsertId();
        AuditService::log('expense.created', 'expenses', 'create', "Recorded expense {$code} ({$data['category']}): {$data['amount']}.", [
            'expense_id' => $id, 'expense_code' => $code,
        ], $request);
        return ['ok' => true, 'id' => (int) $id, 'code' => $code];
    }

    /** @return array{ok: bool, error?: string} */
    public static function update(int $id, array $data, Request $request): array
    {
        $exp = Expense::find($id);
        if ($exp === null) {
            return ['ok' => false, 'error' => 'Expense not found.'];
        }
        Database::execute(
            'UPDATE expenses SET category = ?, description = ?, amount = ?, expense_date = ?, paid_to = ?, payment_method = ?, reference_number = ? WHERE id = ?',
            [
                $data['category'], $data['description'], round((float) $data['amount'], 2),
                $data['expense_date'], $data['paid_to'] ?? null,
                $data['payment_method'] ?? 'cash', $data['reference_number'] ?? null, $id,
            ]
        );
        AuditService::log('expense.updated', 'expenses', 'update', "Updated expense {$exp['expense_code']}.", [
            'expense_id' => $id,
        ], $request);
        return ['ok' => true];
    }

    public static function delete(int $id, Request $request): array
    {
        $exp = Expense::find($id);
        if ($exp === null) {
            return ['ok' => false, 'error' => 'Expense not found.'];
        }
        Database::execute('DELETE FROM expenses WHERE id = ?', [$id]);
        AuditService::log('expense.deleted', 'expenses', 'delete', "Deleted expense {$exp['expense_code']}.", [
            'expense_id' => $id,
        ], $request);
        return ['ok' => true];
    }
}
