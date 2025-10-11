<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\FinancialTransactionReconcileRequest;
use App\Http\Requests\Financeiro\FinancialTransactionStoreRequest;
use App\Http\Requests\Financeiro\FinancialTransactionUpdateRequest;
use App\Http\Resources\Financeiro\FinancialTransactionResource;
use App\Models\FinancialTransaction;
use App\Support\Database\Concerns\InteractsWithJsonLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialTransactionController extends Controller
{
    use InteractsWithJsonLike;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FinancialTransaction::class);

        $query = $this->makeFilteredQuery($request);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $transactions = $query
            ->orderByDesc('data_ocorrencia')
            ->paginate($perPage)
            ->appends($request->query());

        $totals = $this->calculateTotals($this->makeFilteredQuery($request, false));

        return FinancialTransactionResource::collection($transactions)->additional([
            'totals' => $totals,
        ]);
    }

    public function store(FinancialTransactionStoreRequest $request): JsonResponse
    {
        $this->authorize('create', FinancialTransaction::class);

        $data = $request->validated();

        if (($data['status'] ?? 'pendente') === 'conciliado' && ! $request->user()->hasPermission('financeiro.reconcile')) {
            $data['status'] = 'pendente';
        }

        $transaction = FinancialTransaction::create($data);
        $transaction->load(['account', 'costCenter', 'contrato', 'fatura']);

        return FinancialTransactionResource::make($transaction)
            ->additional(['message' => 'Lancamento criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(FinancialTransaction $transaction): FinancialTransactionResource
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'costCenter', 'contrato', 'fatura']);

        return FinancialTransactionResource::make($transaction);
    }

    public function update(FinancialTransactionUpdateRequest $request, FinancialTransaction $transaction): FinancialTransactionResource
    {
        $this->authorize('update', $transaction);

        $transaction->update($request->validated());
        $transaction->load(['account', 'costCenter', 'contrato', 'fatura']);

        return FinancialTransactionResource::make($transaction)->additional([
            'message' => 'Lancamento atualizado com sucesso.',
        ]);
    }

    public function destroy(FinancialTransaction $transaction): Response
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->noContent();
    }

    public function reconcile(FinancialTransactionReconcileRequest $request, FinancialTransaction $transaction): FinancialTransactionResource
    {
        $payload = $request->validated();

        if ($transaction->status !== 'pendente') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Somente lancamentos pendentes podem ser conciliados.');
        }

        $transaction->status = 'conciliado';
        $transaction->meta = array_merge($transaction->meta ?? [], [
            'conciliado_em' => now()->toDateTimeString(),
            'valor_conciliado' => $payload['valor_conciliado'],
            'observacao' => $payload['observacao'] ?? null,
        ]);
        $transaction->save();

        $transaction->refresh()->load(['account', 'costCenter']);

        return FinancialTransactionResource::make($transaction)->additional([
            'message' => 'Lancamento conciliado.',
        ]);
    }

    public function cancel(FinancialTransaction $transaction): FinancialTransactionResource
    {
        $this->authorize('update', $transaction);

        if ($transaction->status === 'conciliado') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Nao e possivel cancelar um lancamento conciliado.');
        }

        $transaction->status = 'cancelado';
        $transaction->save();

        return FinancialTransactionResource::make($transaction)->additional([
            'message' => 'Lancamento cancelado.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', FinancialTransaction::class);

        $query = $this->makeFilteredQuery($request);
        $rows = $query->orderByDesc('data_ocorrencia')->get();

        $filename = 'transacoes-financeiras-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Data', 'Conta', 'Tipo', 'Valor', 'Status', 'Descricao']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    optional($row->data_ocorrencia)->format('Y-m-d'),
                    optional($row->account)->nome,
                    $row->tipo,
                    number_format((float) $row->valor, 2, '.', ''),
                    $row->status,
                    $row->descricao,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function makeFilteredQuery(Request $request, bool $withRelations = true): Builder
    {
        $query = FinancialTransaction::query();

        if ($withRelations) {
            $query->with(['account', 'costCenter', 'contrato', 'fatura']);
        }

        return $query
            ->when($request->filled('filter.tipo'), fn ($q) => $q->where('tipo', $request->string('filter.tipo')))
            ->when($request->filled('filter.status'), fn ($q) => $q->where('status', $request->string('filter.status')))
            ->when($request->filled('filter.account_id'), fn ($q) => $q->where('account_id', $request->integer('filter.account_id')))
            ->when($request->filled('filter.cost_center_id'), fn ($q) => $q->where('cost_center_id', $request->integer('filter.cost_center_id')))
            ->when($request->filled('filter.contrato_id'), fn ($q) => $q->where('contrato_id', $request->integer('filter.contrato_id')))
            ->when($request->filled('filter.fatura_id'), fn ($q) => $q->where('fatura_id', $request->integer('filter.fatura_id')))
            ->when($request->filled('filter.data_de'), fn ($q) => $q->whereDate('data_ocorrencia', '>=', $request->date('filter.data_de')->toDateString()))
            ->when($request->filled('filter.data_ate'), fn ($q) => $q->whereDate('data_ocorrencia', '<=', $request->date('filter.data_ate')->toDateString()))
            ->when($request->filled('filter.search'), function ($q) use ($request) {
                $raw = (string) $request->string('filter.search');
                $clean = trim($raw, ' %');

                if ($clean === '') {
                    return;
                }

                $term = '%'.mb_strtolower($clean).'%';

                $q->where(function ($inner) use ($term) {
                    $inner->whereRaw('LOWER(descricao) LIKE ?', [$term]);

                    $this->orWhereJsonContainsLike($inner, 'meta', $term);
                });
            });
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
