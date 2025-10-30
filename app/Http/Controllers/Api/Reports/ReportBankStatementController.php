<?php

namespace App\Http\Controllers\Api\Reports;

use App\Domain\Financeiro\Support\BankStatementStatus;
use App\Http\Controllers\Api\Financeiro\Concerns\HandlesBankStatementAggregates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportBankStatementFilterRequest;
use App\Http\Resources\Financeiro\BankStatementResource;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportBankStatementController extends Controller
{
    use HandlesBankStatementAggregates;

    public function index(ReportBankStatementFilterRequest $request)
    {
        $baseQuery = $this->filteredStatementsQuery($request);
        $summary = $this->buildSummary(clone $baseQuery);

        $query = $this->applyAggregatesToQuery($baseQuery);

        $perPage = min(max($request->integer('per_page', 25), 1), 200);

        $statements = $query
            ->orderByDesc('imported_at')
            ->paginate($perPage)
            ->appends($request->validated());

        if ($request->boolean('with_lines')) {
            $statements->getCollection()->load(['lines.matchedInstallment.journalEntry']);
        }

        return BankStatementResource::collection($statements)->additional([
            'summary' => $summary,
        ]);
    }

    public function export(ReportBankStatementFilterRequest $request): StreamedResponse
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $format = strtolower((string) $request->input('format', 'csv'));
        if ($format !== 'csv') {
            abort(422, 'Formato solicitado nao e suportado.');
        }

        $query = $this->applyAggregatesToQuery(
            $this->filteredStatementsQuery($request)
        )->orderByDesc('imported_at');

        $statements = $query->get();

        $includeLines = $request->boolean('with_lines');

        if ($includeLines) {
            $statements->load(['lines.matchedInstallment.journalEntry']);
        }

        $filename = 'relatorio-extratos-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($statements, $includeLines) {
            $handle = fopen('php://output', 'w');

            if (! $includeLines) {
                fputcsv($handle, [
                    'Conta',
                    'Referência',
                    'Importado em',
                    'Status',
                    'Entradas',
                    'Saídas',
                    'Saldo Líquido',
                    'Saldo Final',
                    'Pendentes',
                    'Sugestões',
                    'Confirmados',
                    'Ignorados',
                ]);

                foreach ($statements as $statement) {
                    $statusEnum = $statement->status ? BankStatementStatus::tryFrom((string) $statement->status) : null;

                    fputcsv($handle, [
                        optional($statement->account)->nome ?? ('Conta #'.$statement->financial_account_id),
                        $statement->reference,
                        optional($statement->imported_at)?->toDateTimeString(),
                        $statusEnum?->label() ?? $statement->status,
                        number_format((float) ($statement->credit_sum_amount ?? 0), 2, '.', ''),
                        number_format(abs((float) ($statement->debit_sum_amount ?? 0)), 2, '.', ''),
                        number_format((float) (($statement->total_sum_amount ?? 0)), 2, '.', ''),
                        number_format((float) ($statement->meta['closing_balance'] ?? 0), 2, '.', ''),
                        $statement->pending_lines_count ?? 0,
                        $statement->suggested_lines_count ?? 0,
                        $statement->confirmed_lines_count ?? 0,
                        $statement->ignored_lines_count ?? 0,
                    ]);
                }
            } else {
                fputcsv($handle, [
                    'Conta',
                    'Referência',
                    'Importado em',
                    'Status',
                    'Linha',
                    'Data movimento',
                    'Descrição',
                    'Valor',
                    'Tipo',
                    'Status conciliação',
                    'Lançamento ligado',
                ]);

                foreach ($statements as $statement) {
                    $statusEnum = $statement->status ? BankStatementStatus::tryFrom((string) $statement->status) : null;
                    $header = [
                        optional($statement->account)->nome ?? ('Conta #'.$statement->financial_account_id),
                        $statement->reference,
                        optional($statement->imported_at)?->toDateTimeString(),
                        $statusEnum?->label() ?? $statement->status,
                    ];

                    foreach ($statement->lines as $line) {
                        fputcsv($handle, [
                            ...$header,
                            $line->linha,
                            optional($line->transaction_date)?->toDateString(),
                            $line->description,
                            number_format((float) $line->amount, 2, '.', ''),
                            $line->amount >= 0 ? 'Crédito' : 'Débito',
                            ucfirst(str_replace('_', ' ', (string) $line->match_status)),
                            $line->journal_entry?->id
                                ? sprintf(
                                    '#%d %s',
                                    $line->journal_entry->id,
                                    $line->journal_entry->description ?? ''
                                )
                                : '',
                        ]);
                    }
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<BankStatement>
     */
    private function filteredStatementsQuery(ReportBankStatementFilterRequest $request): Builder
    {
        return BankStatement::query()
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
    }

    /**
     * @param  Builder<BankStatement>  $query
     * @return array<string,mixed>
     */
    private function buildSummary(Builder $query): array
    {
        /** @var Collection<int,int> $ids */
        $ids = (clone $query)->pluck('id');

        if ($ids->isEmpty()) {
            return [
                'statements' => [
                    'total' => 0,
                    'status' => [],
                ],
                'lines' => [
                    'total' => 0,
                    'pending' => 0,
                    'suggested' => 0,
                    'confirmed' => 0,
                    'ignored' => 0,
                ],
                'totals' => [
                    'inflow' => 0.0,
                    'outflow' => 0.0,
                    'net' => 0.0,
                ],
            ];
        }

        $lineTotals = BankStatementLine::query()
            ->select([
                DB::raw("SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as inflow"),
                DB::raw("SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as outflow"),
                DB::raw('COUNT(*) as total'),
            ])
            ->whereIn('bank_statement_id', $ids)
            ->first();

        $lineStatusCounts = BankStatementLine::query()
            ->select('match_status', DB::raw('COUNT(*) as total'))
            ->whereIn('bank_statement_id', $ids)
            ->groupBy('match_status')
            ->pluck('total', 'match_status');

        $statementStatusCounts = BankStatement::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->whereIn('id', $ids)
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'statements' => [
                'total' => $ids->count(),
                'status' => $statementStatusCounts,
            ],
            'lines' => [
                'total' => (int) ($lineTotals->total ?? 0),
                'pending' => (int) ($lineStatusCounts['nao_casado'] ?? 0),
                'suggested' => (int) ($lineStatusCounts['sugerido'] ?? 0),
                'confirmed' => (int) ($lineStatusCounts['confirmado'] ?? 0),
                'ignored' => (int) ($lineStatusCounts['ignorado'] ?? 0),
            ],
            'totals' => [
                'inflow' => round((float) ($lineTotals->inflow ?? 0), 2),
                'outflow' => round(abs((float) ($lineTotals->outflow ?? 0)), 2),
                'net' => round((float) (($lineTotals->inflow ?? 0) + ($lineTotals->outflow ?? 0)), 2),
            ],
        ];
    }
}
