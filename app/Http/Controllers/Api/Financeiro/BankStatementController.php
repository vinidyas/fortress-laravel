<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Domain\Financeiro\Services\Reconciliation\ConfirmBankStatementMatchService;
use App\Domain\Financeiro\Services\Reconciliation\IgnoreBankStatementLineService;
use App\Domain\Financeiro\Services\Reconciliation\ImportBankStatementService;
use App\Domain\Financeiro\Services\Reconciliation\SuggestMatchesService;
use App\Domain\Financeiro\Support\BankStatementStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Financeiro\Concerns\HandlesBankStatementAggregates;
use App\Http\Requests\Financeiro\BankStatementConfirmMatchRequest;
use App\Http\Requests\Financeiro\BankStatementIgnoreRequest;
use App\Http\Requests\Financeiro\BankStatementImportRequest;
use App\Http\Resources\Financeiro\BankStatementResource;
use App\Http\Resources\Financeiro\BankStatementLineResource;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\JournalEntryInstallment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BankStatementController extends Controller
{
    use HandlesBankStatementAggregates;

    public function __construct(
        private readonly ImportBankStatementService $importService,
        private readonly SuggestMatchesService $suggestMatchesService,
        private readonly ConfirmBankStatementMatchService $confirmMatchService,
        private readonly IgnoreBankStatementLineService $ignoreLineService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BankStatement::class);

        $query = BankStatement::query();

        $this->applyAggregatesToQuery($query);

        $query
            ->when(
                $request->filled('financial_account_id'),
                fn ($builder) => $builder->where('financial_account_id', $request->integer('financial_account_id'))
            )
            ->when(
                $request->filled('status'),
                function ($builder) use ($request) {
                    $statuses = BankStatementStatus::filterValues((string) $request->string('status'));

                    return count($statuses) === 1
                        ? $builder->where('status', $statuses[0])
                        : $builder->whereIn('status', $statuses);
                }
            )
            ->when(
                $request->filled('reference'),
                fn ($builder) => $builder->where('reference', 'like', '%'.trim((string) $request->string('reference')).'%')
            )
            ->when(
                $request->filled('imported_at_from'),
                fn ($builder) => $builder->whereDate('imported_at', '>=', $request->date('imported_at_from')->toDateString())
            )
            ->when(
                $request->filled('imported_at_to'),
                fn ($builder) => $builder->whereDate('imported_at', '<=', $request->date('imported_at_to')->toDateString())
            );

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $statements = $query
            ->orderByDesc('imported_at')
            ->paginate($perPage)
            ->appends($request->query());

        return BankStatementResource::collection($statements);
    }

    public function store(BankStatementImportRequest $request): JsonResponse
    {
        $this->authorize('create', BankStatement::class);

        $statement = $this->importService->handle(
            financialAccountId: (int) $request->integer('financial_account_id'),
            file: $request->file('file'),
        );
        $statement = $this->applyAggregatesToModel(
            $statement->load(['lines.matchedInstallment.journalEntry'])
        );

        return BankStatementResource::make($statement)
            ->additional(['message' => 'Extrato importado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BankStatement $bankStatement): BankStatementResource
    {
        $this->authorize('view', $bankStatement);

        $statement = $this->applyAggregatesToModel(
            $bankStatement->load(['lines.matchedInstallment.journalEntry'])
        );

        return BankStatementResource::make($statement);
    }

    public function suggest(BankStatement $bankStatement): BankStatementResource
    {
        $this->authorize('update', $bankStatement);

        $statement = $this->suggestMatchesService->handle($bankStatement);
        $statement = $this->applyAggregatesToModel($statement->load(['lines.matchedInstallment.journalEntry']));

        return BankStatementResource::make($statement);
    }

    public function confirmLine(
        BankStatementConfirmMatchRequest $request,
        BankStatement $bankStatement,
        BankStatementLine $bankStatementLine,
    ): BankStatementLineResource {
        $this->authorize('update', $bankStatement);
        $this->assertLineBelongsToStatement($bankStatement, $bankStatementLine);

        $installment = $bankStatementLine->matchedInstallment?->id === $request->integer('installment_id')
            ? $bankStatementLine->matchedInstallment
            : JournalEntryInstallment::query()->findOrFail($request->integer('installment_id'));

        $line = $this->confirmMatchService->handle(
            $bankStatementLine,
            $installment,
            $request->input('payment_date'),
        );

        return BankStatementLineResource::make($line->load(['matchedInstallment.journalEntry']));
    }

    public function ignoreLine(
        BankStatementIgnoreRequest $request,
        BankStatement $bankStatement,
        BankStatementLine $bankStatementLine,
    ): BankStatementLineResource {
        $this->authorize('update', $bankStatement);
        $this->assertLineBelongsToStatement($bankStatement, $bankStatementLine);

        $line = $this->ignoreLineService->handle($bankStatementLine, $request->input('reason'));

        return BankStatementLineResource::make($line);
    }

    public function destroy(BankStatement $bankStatement): Response
    {
        $this->authorize('delete', $bankStatement);

        $bankStatement->delete();

        return response()->noContent();
    }

    private function assertLineBelongsToStatement(BankStatement $statement, BankStatementLine $line): void
    {
        if ($line->bank_statement_id !== $statement->id) {
            abort(Response::HTTP_NOT_FOUND, 'Linha não pertence ao extrato informado.');
        }
    }
}
