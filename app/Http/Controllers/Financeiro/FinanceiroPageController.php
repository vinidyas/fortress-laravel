<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\FinancialTransactionResource;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceiroPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinancialTransaction::class);

        $query = $this->makeFilteredQuery($request);

        $transactions = $query
            ->orderByDesc('data_ocorrencia')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $totals = $this->calculateTotals($this->makeFilteredQuery($request, false));

        return Inertia::render('Financeiro/Index', [
            'transactions' => FinancialTransactionResource::collection($transactions),
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome']),
            'filters' => [
                'search' => $request->input('filter.search'),
                'account_id' => $request->input('filter.account_id'),
                'cost_center_id' => $request->input('filter.cost_center_id'),
                'status' => $request->input('filter.status'),
                'tipo' => $request->input('filter.tipo'),
                'data_de' => $request->input('filter.data_de'),
                'data_ate' => $request->input('filter.data_ate'),
            ],
            'totals' => $totals,
            'can' => [
                'create' => $request->user()->hasPermission('financeiro.create'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
                'export' => $request->user()->hasPermission('financeiro.export'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', FinancialTransaction::class);

        return Inertia::render('Financeiro/Transactions/Form', [
            'mode' => 'create',
            'transaction' => null,
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome']),
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    public function edit(Request $request, FinancialTransaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'costCenter', 'contrato', 'fatura']);

        return Inertia::render('Financeiro/Transactions/Form', [
            'mode' => 'edit',
            'transaction' => FinancialTransactionResource::make($transaction),
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome']),
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    private function makeFilteredQuery(Request $request, bool $withRelations = true): Builder
    {
        $query = FinancialTransaction::query();

        if ($withRelations) {
            $query->with(['account', 'costCenter']);
        }

        return $query
            ->when($request->filled('filter.search'), function ($q) use ($request) {
                $search = (string) $request->string('filter.search');
                $term = '%' . str_replace('%', '', $search) . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('descricao', 'like', $term)
                        ->orWhere('meta->observacao', 'like', $term);
                });
            })
            ->when($request->filled('filter.tipo'), fn ($q) => $q->where('tipo', $request->string('filter.tipo')))
            ->when($request->filled('filter.status'), fn ($q) => $q->where('status', $request->string('filter.status')))
            ->when($request->filled('filter.account_id'), fn ($q) => $q->where('account_id', $request->integer('filter.account_id')))
            ->when($request->filled('filter.cost_center_id'), fn ($q) => $q->where('cost_center_id', $request->integer('filter.cost_center_id')))
            ->when($request->filled('filter.data_de'), fn ($q) => $q->whereDate('data_ocorrencia', '>=', $request->date('filter.data_de')->toDateString()))
            ->when($request->filled('filter.data_ate'), fn ($q) => $q->whereDate('data_ocorrencia', '<=', $request->date('filter.data_ate')->toDateString()));
    }

    private function calculateTotals(Builder $builder): array
    {
        $credito = (float) (clone $builder)->where('tipo', 'credito')->sum('valor');
        $debito = (float) (clone $builder)->where('tipo', 'debito')->sum('valor');

        return [
            'credito' => $credito,
            'debito' => $debito,
            'saldo' => $credito - $debito,
        ];
    }
}
