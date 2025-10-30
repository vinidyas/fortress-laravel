<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\JournalEntryResource;
use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\Imovel;
use App\Models\JournalEntry;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FinanceiroPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $this->applyDefaultFilters($request);

        $query = $this->makeFilteredQuery($request);

        $perPageParam = $request->input('per_page', 'all');

        if ($perPageParam === 'all') {
            $collection = (clone $query)
                ->orderByDesc('movement_date')
                ->get();

            $transactions = new LengthAwarePaginator(
                $collection,
                $collection->count(),
                max($collection->count(), 1),
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } else {
            $perPage = min(max((int) $perPageParam, 1), 100);
            $transactions = $query
                ->orderByDesc('movement_date')
                ->paginate($perPage)
                ->withQueryString();
        }

        $totals = $this->calculateTotals($this->makeFilteredQuery($request, false));

        return Inertia::render('Financeiro/Index', [
            'entries' => JournalEntryResource::collection($transactions),
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome', 'codigo', 'parent_id']),
            'people' => Pessoa::query()
                ->orderBy('nome_razao_social')
                ->get([
                    'id',
                    'nome_razao_social as nome',
                    'papeis',
                ]),
            'properties' => Imovel::query()
                ->with('condominio:id,nome')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'complemento', 'condominio_id'])
                ->map(function (Imovel $imovel) {
                    $condominioNome = trim($imovel->condominio->nome ?? '');
                    $complemento = trim($imovel->complemento ?? '');
                    $titulo = $condominioNome;

                    if ($complemento !== '') {
                        $titulo = $titulo !== ''
                            ? sprintf('%s — %s', $condominioNome, $complemento)
                            : $complemento;
                    }

                    if ($titulo === '') {
                        $titulo = sprintf('Imóvel %s', $imovel->codigo ?? $imovel->id);
                    }

                    return [
                        'id' => $imovel->id,
                        'titulo' => $titulo,
                        'codigo_interno' => $imovel->codigo,
                    ];
                })
                ->values(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'account_id' => $request->input('filter.account_id'),
                'cost_center_id' => $request->input('filter.cost_center_id'),
                'status' => $request->input('filter.status'),
                'tipo' => $request->input('filter.tipo'),
                'data_de' => $request->input('filter.data_de'),
                'data_ate' => $request->input('filter.data_ate'),
                'per_page' => $request->input('per_page'),
            ],
            'totals' => $totals,
            'can' => [
                'create' => $request->user()->can('create', JournalEntry::class),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
                'export' => $request->user()->can('export', JournalEntry::class),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
            ],
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', JournalEntry::class);

        return Inertia::render('Financeiro/Transactions/Form', [
            'mode' => 'create',
            'transaction' => null,
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome', 'codigo', 'parent_id']),
            'people' => Pessoa::query()
                ->orderBy('nome_razao_social')
                ->get([
                    'id',
                    'nome_razao_social as nome',
                    'papeis',
                ]),
            'properties' => Imovel::query()
                ->with('condominio:id,nome')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'complemento', 'condominio_id'])
                ->map(function (Imovel $imovel) {
                    $condominioNome = trim($imovel->condominio->nome ?? '');
                    $complemento = trim($imovel->complemento ?? '');
                    $titulo = $condominioNome;

                    if ($complemento !== '') {
                        $titulo = $titulo !== ''
                            ? sprintf('%s — %s', $condominioNome, $complemento)
                            : $complemento;
                    }

                    if ($titulo === '') {
                        $titulo = sprintf('Imóvel %s', $imovel->codigo ?? $imovel->id);
                    }

                    return [
                        'id' => $imovel->id,
                        'titulo' => $titulo,
                        'codigo_interno' => $imovel->codigo,
                    ];
                })
                ->values(),
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    public function edit(Request $request, JournalEntry $journalEntry): Response
    {
        $this->authorize('view', $journalEntry);

        $journalEntry->load(['bankAccount', 'counterBankAccount', 'costCenter.parent', 'person', 'installments', 'allocations']);

        return Inertia::render('Financeiro/Transactions/Form', [
            'mode' => 'edit',
            'transaction' => JournalEntryResource::make(
                $journalEntry->load([
                    'bankAccount',
                    'counterBankAccount',
                    'costCenter.parent',
                    'person',
                    'installments',
                    'allocations',
                    'attachments.uploadedBy',
                    'receipts.issuedBy',
                ])
            ),
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::query()->orderBy('nome')->get(['id', 'nome', 'codigo', 'parent_id']),
            'people' => Pessoa::query()
                ->orderBy('nome_razao_social')
                ->get([
                    'id',
                    'nome_razao_social as nome',
                    'papeis',
                ]),
            'properties' => Imovel::query()
                ->with('condominio:id,nome')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'complemento', 'condominio_id'])
                ->map(function (Imovel $imovel) {
                    $condominioNome = trim($imovel->condominio->nome ?? '');
                    $complemento = trim($imovel->complemento ?? '');
                    $titulo = $condominioNome;

                    if ($complemento !== '') {
                        $titulo = $titulo !== ''
                            ? sprintf('%s — %s', $condominioNome, $complemento)
                            : $complemento;
                    }

                    if ($titulo === '') {
                        $titulo = sprintf('Imóvel %s', $imovel->codigo ?? $imovel->id);
                    }

                    return [
                        'id' => $imovel->id,
                        'titulo' => $titulo,
                        'codigo_interno' => $imovel->codigo,
                    ];
                })
                ->values(),
            'permissions' => [
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'reconcile' => $request->user()->hasPermission('financeiro.reconcile'),
            ],
        ]);
    }

    private function makeFilteredQuery(Request $request, bool $withRelations = true): Builder
    {
        $query = JournalEntry::query()->operational();

        if ($withRelations) {
            $query->with(['bankAccount', 'costCenter.parent', 'person', 'property.condominio']);
        }

        return $query
            ->when($request->filled('filter.search'), function ($q) use ($request) {
                $search = trim((string) $request->string('filter.search'));
                if ($search === '') {
                    return;
                }

                $term = '%'.str_replace('%', '', $search).'%';

                $numericCandidate = preg_replace('/[^0-9,.\-]/', '', $search);
                $amountValue = null;
                if ($numericCandidate !== '' && preg_match('/\d/', $numericCandidate)) {
                    $normalized = str_replace(['.', ','], ['', '.'], $numericCandidate);
                    if (is_numeric($normalized)) {
                        $amountValue = (float) $normalized;
                    }
                }

                $q->where(function ($inner) use ($term, $amountValue) {
                    $inner->where('description_custom', 'like', $term)
                        ->orWhere('notes', 'like', $term)
                        ->orWhere('reference_code', 'like', $term)
                        ->orWhereHas('person', function ($person) use ($term) {
                            $person->where('nome_razao_social', 'like', $term);
                        })
                        ->orWhereHas('property', function ($property) use ($term) {
                            $property->where('codigo', 'like', $term)
                                ->orWhere('complemento', 'like', $term)
                                ->orWhere('cidade', 'like', $term)
                                ->orWhere('bairro', 'like', $term)
                                ->orWhereHas('condominio', function ($condominio) use ($term) {
                                    $condominio->where('nome', 'like', $term);
                                });
                        })
                        ->orWhereHas('costCenter', function ($center) use ($term) {
                            $center->where('nome', 'like', $term)
                                ->orWhere('codigo', 'like', $term);
                        });

                    if ($amountValue !== null) {
                        $inner->orWhereRaw('ROUND(amount, 2) = ?', [round($amountValue, 2)]);
                    }
                });
            })
            ->when($request->filled('filter.tipo'), fn ($q) => $q->where('type', $request->string('filter.tipo')))
            ->when($request->filled('filter.status'), function ($q) use ($request) {
                $statuses = JournalEntryStatus::filterValues((string) $request->string('filter.status'));

                return count($statuses) === 1
                    ? $q->where('status', $statuses[0])
                    : $q->whereIn('status', $statuses);
            })
            ->when($request->filled('filter.account_id'), fn ($q) => $q->where('bank_account_id', $request->integer('filter.account_id')))
            ->when($request->filled('filter.cost_center_id'), fn ($q) => $q->where('cost_center_id', $request->integer('filter.cost_center_id')))
            ->when($request->filled('filter.data_de'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('filter.data_de')->toDateString()))
            ->when($request->filled('filter.data_ate'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('filter.data_ate')->toDateString()));
    }

    private function calculateTotals(Builder $builder): array
    {
        $receita = (float) (clone $builder)->where('type', 'receita')->sum('amount');
        $despesa = (float) (clone $builder)->where('type', 'despesa')->sum('amount');

        return [
            'receita' => $receita,
            'despesa' => $despesa,
            'saldo' => $receita - $despesa,
        ];
    }

    private function applyDefaultFilters(Request $request): void
    {
        $filters = $request->input('filter', []);

        $now = Carbon::now();
        $defaultStart = $now->copy()->startOfMonth()->toDateString();
        $defaultEnd = $now->toDateString();

        if (empty($filters['data_de']) || !is_string($filters['data_de'])) {
            $filters['data_de'] = $defaultStart;
        }

        if (empty($filters['data_ate']) || !is_string($filters['data_ate'])) {
            $filters['data_ate'] = $defaultEnd;
        }

        $request->merge(['filter' => $filters]);

        if ($this->isCurrentMonthRange($filters['data_de'] ?? null, $filters['data_ate'] ?? null)) {
            $request->merge(['per_page' => 'all']);
        }
    }

    private function isCurrentMonthRange(?string $start, ?string $end): bool
    {
        if (!$start || !$end) {
            return false;
        }

        try {
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = Carbon::parse($end)->startOfDay();
        } catch (\Exception) {
            return false;
        }

        $today = Carbon::now()->startOfDay();
        $startOfMonth = $today->copy()->startOfMonth();

        return $startDate->equalTo($startOfMonth) && $endDate->equalTo($today);
    }
}
