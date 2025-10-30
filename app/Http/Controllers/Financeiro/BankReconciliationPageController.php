<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\BankStatementResource;
use App\Http\Resources\Financeiro\FinancialReconciliationResource;
use App\Models\BankStatement;
use App\Models\FinancialAccount;
use App\Models\FinancialReconciliation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BankStatement::class);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $filters = [
            'financial_account_id' => $request->integer('filter.financial_account_id'),
            'status' => $request->input('filter.status'),
            'reference' => $request->input('filter.reference'),
        ];

        $statements = BankStatement::query()
            ->with(['account:id,nome'])
            ->withCount([
                'lines as total_lines_count',
                'lines as pending_lines_count' => fn ($query) => $query->whereIn('match_status', ['nao_casado', 'sugerido']),
                'lines as confirmed_lines_count' => fn ($query) => $query->where('match_status', 'confirmado'),
            ])
            ->when($filters['financial_account_id'], fn ($query, $accountId) => $query->where('financial_account_id', $accountId))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['reference'], fn ($query, $reference) => $query->where('reference', 'like', '%'.trim($reference).'%'))
            ->orderByDesc('imported_at')
            ->paginate($perPage)
            ->withQueryString();

        $reconciliations = FinancialReconciliation::query()
            ->when($filters['financial_account_id'], fn ($query, $accountId) => $query->where('financial_account_id', $accountId))
            ->orderByDesc('period_end')
            ->limit(20)
            ->get();

        return Inertia::render('Financeiro/Reconciliation/Index', [
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'statements' => BankStatementResource::collection($statements),
            'reconciliations' => FinancialReconciliationResource::collection($reconciliations),
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            'can' => [
                'upload' => $request->user()->can('create', BankStatement::class),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }
}
