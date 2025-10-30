<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Domain\Financeiro\Services\Reconciliation\CloseReconciliationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\FinancialReconciliationCloseRequest;
use App\Http\Resources\Financeiro\FinancialReconciliationResource;
use App\Models\FinancialReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReconciliationController extends Controller
{
    public function __construct(private readonly CloseReconciliationService $closeService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FinancialReconciliation::class);

        $items = FinancialReconciliation::query()
            ->when($request->filled('financial_account_id'), fn ($query) => $query->where('financial_account_id', $request->integer('financial_account_id')))
            ->when($request->filled('period_start'), fn ($query) => $query->whereDate('period_start', '>=', $request->date('period_start')->toDateString()))
            ->when($request->filled('period_end'), fn ($query) => $query->whereDate('period_end', '<=', $request->date('period_end')->toDateString()))
            ->orderByDesc('period_end')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return FinancialReconciliationResource::collection($items);
    }

    public function show(FinancialReconciliation $financialReconciliation): FinancialReconciliationResource
    {
        $this->authorize('view', $financialReconciliation);

        return FinancialReconciliationResource::make($financialReconciliation);
    }

    public function store(FinancialReconciliationCloseRequest $request): JsonResponse
    {
        $this->authorize('create', FinancialReconciliation::class);

        $reconciliation = $this->closeService->handle(
            financialAccountId: (int) $request->integer('financial_account_id'),
            periodStart: $request->input('period_start'),
            periodEnd: $request->input('period_end'),
            openingBalance: (float) $request->input('opening_balance'),
            closingBalance: (float) $request->input('closing_balance'),
            statementIds: $request->input('statement_ids', []),
        );

        return FinancialReconciliationResource::make($reconciliation)
            ->additional(['message' => 'Reconciliação fechada com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(FinancialReconciliation $financialReconciliation): Response
    {
        $this->authorize('delete', $financialReconciliation);

        $financialReconciliation->delete();

        return response()->noContent();
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', FinancialReconciliation::class);

        $query = FinancialReconciliation::query()
            ->when($request->filled('financial_account_id'), fn ($q) => $q->where('financial_account_id', $request->integer('financial_account_id')))
            ->when($request->filled('period_start'), fn ($q) => $q->whereDate('period_start', '>=', $request->date('period_start')->toDateString()))
            ->when($request->filled('period_end'), fn ($q) => $q->whereDate('period_end', '<=', $request->date('period_end')->toDateString()))
            ->orderByDesc('period_end');

        $filename = 'reconciliacoes-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Conta', 'Período Inicial', 'Período Final', 'Saldo Inicial', 'Saldo Final', 'Status', 'Criado em']);

            $query->lazy(200)->each(function (FinancialReconciliation $item) use ($handle) {
                fputcsv($handle, [
                    $item->financial_account_id,
                    $item->period_start?->toDateString(),
                    $item->period_end?->toDateString(),
                    number_format((float) $item->opening_balance, 2, '.', ''),
                    number_format((float) $item->closing_balance, 2, '.', ''),
                    $item->status,
                    $item->created_at?->toDateTimeString(),
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
