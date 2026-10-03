<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Expense;
use App\Services\AuditService;
use App\Services\ExpenseService;
use App\Services\SettingService;

/**
 * Expense management — CRUD for hospital operational spending.
 */
final class ExpenseController extends Controller
{
    public function index(Request $request): string
    {
        $filters = $this->collectFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(5, min(50, (int) SettingService::get('items_per_page', 15)));

        $data = Expense::directory($filters, $page, $perPage);
        $totals = Expense::totalsForRange(date('Y-m-01'), date('Y-m-d'));

        $query = http_build_query(array_filter($filters, static fn ($v) => $v !== '' && $v !== null));
        $baseUrl = url('/admin/expenses') . ($query !== '' ? '?' . $query . '&' : '?');

        return Response::html(view('admin/expenses/index', [
            'expenses'  => $data['rows'],
            'total'     => $data['total'],
            'page'      => $data['page'],
            'pages'     => $data['pages'],
            'perPage'   => $data['perPage'],
            'filters'   => $filters,
            'totals'    => $totals,
            'baseUrl'   => $baseUrl,
        ]));
    }

    public function store(Request $request): string
    {
        $result = $this->validate($request, [
            'category'       => 'required|in:salaries,utilities,supplies,maintenance,equipment,rent,other',
            'description'    => 'required|min:3|max:300',
            'amount'         => 'required|numeric',
            'expense_date'   => 'required|date',
            'paid_to'        => 'nullable|max:150',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,cheque,other',
            'reference_number' => 'nullable|max:100',
        ]);
        $r = ExpenseService::create($result['data'], $request);
        if (!$r['ok']) {
            Session::flash('error', $r['error'] ?? 'Could not create expense.');
            Session::flashInput($request->all());
        } else {
            Session::flash('success', "Expense {$r['code']} recorded.");
        }
        return Response::redirect(url('/admin/expenses'));
    }

    public function update(Request $request, string $id): string
    {
        $result = $this->validate($request, [
            'category'       => 'required|in:salaries,utilities,supplies,maintenance,equipment,rent,other',
            'description'    => 'required|min:3|max:300',
            'amount'         => 'required|numeric',
            'expense_date'   => 'required|date',
            'paid_to'        => 'nullable|max:150',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,cheque,other',
            'reference_number' => 'nullable|max:100',
        ]);
        $r = ExpenseService::update((int) $id, $result['data'], $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Expense updated.' : (string) $r['error']);
        return Response::redirect(url('/admin/expenses'));
    }

    public function destroy(Request $request, string $id): string
    {
        $r = ExpenseService::delete((int) $id, $request);
        Session::flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Expense deleted.' : (string) $r['error']);
        return Response::redirect(url('/admin/expenses'));
    }

    /** @return array<string, mixed> */
    private function collectFilters(Request $request): array
    {
        return [
            'search'    => trim(mb_substr((string) $request->query('q', ''), 0, 60)),
            'category'  => in_array($request->query('category', ''), Expense::CATEGORIES, true) ? (string) $request->query('category') : '',
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
        ];
    }
}
