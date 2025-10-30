<?php

namespace App\Http\Controllers\Api\Reports;

use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportBankLedgerFilterRequest;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportBankLedgerController extends Controller
{
    public function index(ReportBankLedgerFilterRequest $request): JsonResponse
    {
        $accountIds = $this->extractAccountIds($request);
        $accounts = $accountIds !== null && $accountIds !== []
            ? FinancialAccount::query()
                ->whereIn('id', $accountIds)
                ->orderBy('nome')
                ->get(['id', 'nome'])
            : collect();
        $accountLabel = $this->formatAccountLabel($accounts, $accountIds);

        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->toDateString() : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->toDateString() : null;

        $openingBalance = $this->calculateOpeningBalance($request, $accountIds);
        $previewLimit = (int) $request->integer('preview_limit', 25);
        $previewLimit = $previewLimit > 0 ? min($previewLimit, 100) : 25;

        $relations = ['costCenter', 'person', 'property'];

        if (Schema::hasTable('journal_entry_installments')) {
            $relations['installments'] = fn ($query) => $query
                ->select('id', 'journal_entry_id', 'meta')
                ->orderBy('id');
        }

        $entries = $this->baseQuery($request, $accountIds)
            ->with($relations)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->lazy();

        $rowsPayload = $this->buildRowsPayload($entries, $openingBalance, true, $previewLimit);
        $rows = collect($rowsPayload['rows']);

        return response()->json([
            'account' => [
                'ids' => $accountIds,
                'nome' => $accountLabel,
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => $rowsPayload['closing_balance'],
            'totals' => [
                'inflow' => round($rowsPayload['totals_in'], 2),
                'outflow' => round($rowsPayload['totals_out'], 2),
                'net' => round($rowsPayload['totals_in'] - $rowsPayload['totals_out'], 2),
            ],
            'data' => $rows,
            'total_rows' => $rowsPayload['total_rows'],
        ]);
    }

    public function export(ReportBankLedgerFilterRequest $request): StreamedResponse|Response
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $format = strtolower((string) $request->input('format', 'csv'));
        if (! in_array($format, ['csv', 'pdf', 'xlsx'], true)) {
            abort(422, 'Formato solicitado não é suportado.');
        }

        if ($format === 'pdf') {
            $hasFrom = $request->filled('date_from');
            $hasTo = $request->filled('date_to');

            if (($hasFrom && ! $hasTo) || ($hasTo && ! $hasFrom)) {
                abort(422, 'Para exportar em PDF, informe as datas inicial e final do período.');
            }

            if ($hasFrom && $hasTo) {
                $dateFrom = $request->date('date_from');
                $dateTo = $request->date('date_to');

                if ($dateFrom instanceof Carbon && $dateTo instanceof Carbon) {
                    $rangeInDays = $dateFrom->diffInDays($dateTo) + 1;

                    if ($rangeInDays > 31) {
                        abort(422, 'Para exportar em PDF, limite o período a no máximo 31 dias. Utilize XLSX ou CSV para períodos maiores.');
                    }
                }
            }
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $accountIds = $this->extractAccountIds($request);
        $accounts = $accountIds !== null && $accountIds !== []
            ? FinancialAccount::query()
                ->whereIn('id', $accountIds)
                ->orderBy('nome')
                ->get(['id', 'nome'])
            : collect();
        $accountName = $this->formatAccountLabel($accounts, $accountIds);

        $openingBalance = $this->calculateOpeningBalance($request, $accountIds);
        $exportRelations = ['costCenter', 'person', 'property'];

        if (Schema::hasTable('journal_entry_installments')) {
            $exportRelations['installments'] = fn ($query) => $query
                ->select('id', 'journal_entry_id', 'meta')
                ->orderBy('id');
        }

        $entries = $this->baseQuery($request, $accountIds)
            ->with($exportRelations)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->lazy();

        $rowsPayload = $this->buildRowsPayload($entries, $openingBalance, true);
        $rows = $rowsPayload['rows'];
        $totalAbsolute = $rowsPayload['totals_absolute'];
        $totalRevenue = $rowsPayload['totals_revenue'];

        $isExpenseReport = $request->input('type', 'despesa') === 'despesa';

        if ($format === 'pdf') {
            return $this->downloadPdf($accountName, $request, $openingBalance, $rows);
        }

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Relatório');

            $sheet->fromArray([
                ['Conta', $accountName],
                ['Período', ($request->input('date_from') ?? 'Início').' a '.($request->input('date_to') ?? 'Hoje')],
                ['Saldo inicial', number_format($openingBalance, 2, '.', '')],
            ]);

            $headerRowIndex = 5;
            $sheet->fromArray([
                ['Data', 'Fornecedor', 'Descrição', 'Imóvel', 'Vencimento', 'Status', 'Valor'],
            ], null, "A{$headerRowIndex}");

            $rowIndex = $headerRowIndex + 1;
            foreach ($rows as $row) {
                $propertyName = $row['property']['nome'] ?? '';

                $signedAmount = $row['signed_amount'] ?? 0;
                $absoluteAmount = $row['absolute_amount'] ?? null;
                $value = $isExpenseReport
                    ? ($absoluteAmount !== null ? $absoluteAmount : abs($signedAmount))
                    : $signedAmount;

                $sheet->setCellValueExplicit("A{$rowIndex}", (string) ($row['movement_date'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("B{$rowIndex}", $row['person']['nome'] ?? '');
                $sheet->setCellValue("C{$rowIndex}", $row['description'] ?? '');
                $sheet->setCellValue("D{$rowIndex}", $propertyName);
                $sheet->setCellValueExplicit("E{$rowIndex}", (string) ($row['due_date'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("F{$rowIndex}", $row['status_label'] ?? '');
                $sheet->setCellValue("G{$rowIndex}", round((float) $value, 2));

                $rowIndex++;
            }

            $rowIndex++;
            if ($isExpenseReport) {
                $sheet->setCellValue("A{$rowIndex}", 'TOTAL DAS DESPESAS');
                $sheet->setCellValue("G{$rowIndex}", round($totalAbsolute, 2));
            } else {
                $sheet->setCellValue("A{$rowIndex}", 'TOTAL DE RECEITAS');
                $sheet->setCellValue("G{$rowIndex}", round($totalRevenue, 2));
            }

            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'extrato-detalhado-'.Str::slug($accountName).'-'.now()->format('Ymd_His').'.xlsx';

            return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        $filename = 'extrato-detalhado-'.Str::slug($accountName).'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $accountName, $request, $openingBalance, $isExpenseReport, $totalAbsolute, $totalRevenue) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Conta', $accountName]);
            fputcsv($handle, ['Período', ($request->input('date_from') ?? 'Início').' a '.($request->input('date_to') ?? 'Hoje')]);
            fputcsv($handle, ['Saldo inicial', number_format($openingBalance, 2, '.', '')]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Data',
                'Fornecedor',
                'Descrição',
                'Imóvel',
                'Vencimento',
                'Status',
                'Valor',
            ]);

            foreach ($rows as $row) {
                $propertyName = $row['property']['nome'] ?? '';

                $signedAmount = $row['signed_amount'] ?? 0;
                $absoluteAmount = $row['absolute_amount'] ?? null;
                $value = $isExpenseReport
                    ? ($absoluteAmount !== null ? $absoluteAmount : abs($signedAmount))
                    : $signedAmount;

                fputcsv($handle, [
                    $row['movement_date'],
                    $row['person']['nome'] ?? '',
                    $row['description'],
                    $propertyName,
                    $row['due_date'],
                    $row['status_label'],
                    number_format($value, 2, '.', ''),
                ]);
            }

            if ($isExpenseReport) {
                fputcsv($handle, []);
                fputcsv($handle, ['TOTAL DAS DESPESAS', null, null, null, null, null, number_format($totalAbsolute, 2, '.', '')]);
            } else {
                fputcsv($handle, []);
                fputcsv($handle, ['TOTAL DE RECEITAS', null, null, null, null, null, number_format($totalRevenue, 2, '.', '')]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<JournalEntry>
     */
    private function baseQuery(ReportBankLedgerFilterRequest $request, ?array $accountIds = null): Builder
    {
        $accountIds ??= $this->extractAccountIds($request);

        $query = JournalEntry::query()->operational();

        if ($accountIds !== null && $accountIds !== []) {
            $query->whereIn('bank_account_id', $accountIds);
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->string('type'));
        }

        if ($request->filled('status')) {
            $statuses = JournalEntryStatus::filterValues((string) $request->string('status'));

            $query->when(
                count($statuses) === 1,
                fn ($builder) => $builder->where('status', $statuses[0]),
                fn ($builder) => $builder->whereIn('status', $statuses)
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate('movement_date', '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('movement_date', '<=', $request->date('date_to')->toDateString());
        }

        return $query;
    }

    private function calculateOpeningBalance(ReportBankLedgerFilterRequest $request, ?array $accountIds = null): float
    {
        if (! $request->filled('date_from')) {
            return 0.0;
        }

        $accountIds ??= $this->extractAccountIds($request);

        $query = JournalEntry::query()
            ->operational()
            ->when(
                $accountIds !== null && $accountIds !== [],
                fn (Builder $builder) => $builder->whereIn('bank_account_id', $accountIds)
            )
            ->whereDate('movement_date', '<', $request->date('date_from')->toDateString());

        if ($request->filled('status')) {
            $statuses = JournalEntryStatus::filterValues((string) $request->string('status'));
            $query->when(
                count($statuses) === 1,
                fn ($builder) => $builder->where('status', $statuses[0]),
                fn ($builder) => $builder->whereIn('status', $statuses)
            );
        }

        return (float) $query->get()->sum(fn (JournalEntry $entry) => $this->resolveSignedAmount($entry));
    }

    private function resolveSignedAmount(JournalEntry $entry): float
    {
        $amount = (float) $entry->amount;

        return match ($entry->type) {
            'receita' => $amount,
            'despesa' => -$amount,
            'transferencia' => -$amount,
            default => 0.0,
        };
    }

    /**
     * @param  iterable<JournalEntry>  $entries
     * @return array{rows: array<int,array<string,mixed>>, closing_balance: float, totals_in: float, totals_out: float, totals_absolute: float, totals_revenue: float, total_rows: int}
     */
    private function buildRowsPayload(iterable $entries, float $openingBalance, bool $detailed = false, ?int $limit = null): array
    {
        $running = $openingBalance;
        $rows = [];
        $totalsIn = 0.0;
        $totalsOut = 0.0;
        $totalsAbsolute = 0.0;
        $totalsRevenue = 0.0;
        $count = 0;

        foreach ($entries as $entry) {
            $count++;
            $typeEnum = $entry->type ? JournalEntryType::tryFrom((string) $entry->type) : null;
            $statusEnum = $entry->status ? JournalEntryStatus::tryFrom((string) $entry->status) : null;
            $signed = $this->resolveSignedAmount($entry);
            $running += $signed;

            $amountIn = $signed > 0 ? round($signed, 2) : 0.0;
            $amountOut = $signed < 0 ? round(abs($signed), 2) : 0.0;
            $totalsIn += $amountIn;
            $totalsOut += $amountOut;
            $absoluteAmount = round(abs($signed), 2);
            $totalsAbsolute += $absoluteAmount;
            if ($signed >= 0) {
                $totalsRevenue += $absoluteAmount;
            }

            if ($limit !== null && count($rows) >= $limit) {
                continue;
            }

            $propertyLabel = $this->resolveEntryPropertyLabel($entry);

            $base = [
                'id' => $entry->id,
                'movement_date' => $entry->movement_date?->toDateString(),
                'due_date' => $entry->due_date?->toDateString(),
                'description' => $entry->description_custom ?? $entry->description_id,
                'type' => $entry->type,
                'type_label' => $typeEnum ? ucfirst($typeEnum->name) : ucfirst((string) $entry->type),
                'property' => $propertyLabel
                    ? [
                        'id' => $entry->property?->id,
                        'nome' => $propertyLabel,
                    ]
                    : null,
                'cost_center' => $entry->costCenter
                    ? [
                        'id' => $entry->costCenter->id,
                        'nome' => $entry->costCenter->nome,
                        'codigo' => $entry->costCenter->codigo,
                    ]
                    : null,
                'amount_in' => $amountIn,
                'amount_out' => $amountOut,
                'balance_after' => round($running, 2),
                'status_label' => $statusEnum
                    ? $statusEnum->label($typeEnum)
                    : ($entry->status ? ucfirst($entry->status) : null),
                'status_category' => $statusEnum?->category(),
            ];

            if ($detailed) {
                $base['notes'] = $entry->notes;
                $base['reference_code'] = $entry->reference_code;
                $base['amount'] = (float) $entry->amount;
                $base['person'] = $entry->person
                    ? [
                        'id' => $entry->person->id,
                        'nome' => $entry->person->nome,
                    ]
                    : null;
                $base['signed_amount'] = round($signed, 2);
                $base['absolute_amount'] = $absoluteAmount;
                $base['status'] = $entry->status;
            }

            $rows[] = $base;
        }

        return [
            'rows' => $rows,
            'closing_balance' => round($running, 2),
            'totals_in' => round($totalsIn, 2),
            'totals_out' => round($totalsOut, 2),
            'totals_absolute' => round($totalsAbsolute, 2),
            'totals_revenue' => round($totalsRevenue, 2),
            'total_rows' => $count,
        ];
    }

    private function downloadPdf(string $accountName, ReportBankLedgerFilterRequest $request, float $openingBalance, array $rows): Response
    {
        $closingBalance = ! empty($rows) ? end($rows)['balance_after'] : $openingBalance;
        $totals = [
            'inflow' => round(array_sum(array_column($rows, 'amount_in')), 2),
            'outflow' => round(array_sum(array_column($rows, 'amount_out')), 2),
            'net' => round(array_sum(array_column($rows, 'amount_in')) - array_sum(array_column($rows, 'amount_out')), 2),
        ];

        $logoPath = base_path('docs/identidade-visual-fortress_3.jpg');
        $logoBase64 = File::exists($logoPath)
            ? 'data:image/jpeg;base64,'.base64_encode(File::get($logoPath))
            : null;

        $user = $request->user();
        $generatedBy = $user?->nome ?? $user?->name ?? $user?->username;

        $data = [
            'account' => ['nome' => $accountName],
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'type' => $request->input('type', 'despesa'),
                'generated_at' => now(),
                'generated_by' => $generatedBy,
            ],
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'totals' => $totals,
            'rows' => $rows,
            'company' => [
                'name' => config('app.company_name', config('app.name', 'Fortress Empreendimentos')),
            ],
            'logoBase64' => $logoBase64,
        ];

        $typeSlug = $request->input('type', 'despesa') === 'receita' ? 'receitas' : 'despesas';
        $filename = sprintf(
            'relatorio-%s-%s-%s.pdf',
            $typeSlug,
            Str::slug($accountName),
            now()->format('Ymd_His')
        );

        $html = view('pdf.bank-ledger-report', $data)->render();

        if ($request->boolean('preview')) {
            return response($html);
        }

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    /**
     * @return array<int>|null
     */
    private function extractAccountIds(ReportBankLedgerFilterRequest $request): ?array
    {
        $ids = $request->input('financial_account_ids');

        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }

        if (is_array($ids) && $ids !== []) {
            $normalized = collect($ids)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            return $normalized !== [] ? $normalized : null;
        }

        if ($request->filled('financial_account_id')) {
            $id = (int) $request->integer('financial_account_id');

            return $id > 0 ? [$id] : null;
        }

        return null;
    }

    private function formatAccountLabel(Collection $accounts, ?array $accountIds): string
    {
        if ($accountIds === null || $accountIds === []) {
            return 'Todos os bancos';
        }

        $normalizedIds = array_values(array_unique(array_map('intval', $accountIds)));
        $totalRequested = count($normalizedIds);

        $names = $accounts
            ->pluck('nome')
            ->map(fn ($name) => is_string($name) ? trim($name) : null)
            ->filter()
            ->values();

        if ($totalRequested === 1) {
            return $names->first() ?: 'Conta selecionada';
        }

        if ($names->count() === $totalRequested && $totalRequested <= 3) {
            return $names->implode(', ');
        }

        if ($names->count() > 0 && $names->count() <= 3) {
            return $names->implode(', ');
        }

        return sprintf('%d contas selecionadas', $totalRequested);
    }

    private function resolvePropertyLabel($property): ?string
    {
        if (! $property) {
            return null;
        }

        $segments = [];

        if (! empty($property->complemento)) {
            $segments[] = trim((string) $property->complemento);
        }

        if (! empty($property->logradouro)) {
            $logradouro = trim((string) $property->logradouro);
            if (! empty($property->numero)) {
                $logradouro = trim($logradouro.' '.$property->numero);
            }
            $segments[] = $logradouro;
        }

        if (! empty($property->bairro)) {
            $segments[] = trim((string) $property->bairro);
        }

        if (! empty($property->cidade)) {
            $segments[] = trim((string) $property->cidade);
        }

        if (empty($segments) && ! empty($property->codigo)) {
            $segments[] = trim((string) $property->codigo);
        }

        $label = trim(implode(' • ', array_filter($segments)));

        return $label !== '' ? $label : ($property->codigo ?? null);
    }

    private function resolveEntryPropertyLabel(JournalEntry $entry): ?string
    {
        $label = null;

        if ($entry->relationLoaded('property') && $entry->property) {
            $label = $this->resolvePropertyLabel($entry->property);
        }

        if (! $label) {
            $label = $entry->costCenter?->nome;
        }

        if (! $label && Schema::hasTable('journal_entry_installments')) {
            if ($entry->relationLoaded('installments')) {
                $firstInstallment = $entry->installments->first();
            } else {
                $firstInstallment = $entry->installments()
                    ->select('meta')
                    ->orderBy('id')
                    ->first();
            }

            if ($firstInstallment && is_array($firstInstallment->meta ?? null)) {
                $label = $firstInstallment->meta['property_label'] ?? null;
            }
        }

        return $label;
    }
}
