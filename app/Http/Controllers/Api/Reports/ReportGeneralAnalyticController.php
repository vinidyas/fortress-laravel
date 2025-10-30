<?php

namespace App\Http\Controllers\Api\Reports;

use App\Domain\Financeiro\Support\JournalEntryStatus;
use App\Domain\Financeiro\Support\JournalEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportGeneralAnalyticRequest;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\Imovel;
use App\Models\JournalEntry;
use App\Models\Pessoa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportGeneralAnalyticController extends Controller
{
    public function index(ReportGeneralAnalyticRequest $request): JsonResponse
    {
        $previewLimit = (int) $request->integer('preview_limit', 50);
        $previewLimit = $previewLimit > 0 ? min($previewLimit, 200) : 50;

        $baseQuery = $this->baseQuery($request);

        $entries = (clone $baseQuery)
            ->limit($previewLimit)
            ->get();

        $rows = $entries->map(fn (JournalEntry $entry) => $this->transformEntry($entry));

        $totals = $this->calculateTotals(clone $baseQuery);
        $totalRows = (clone $baseQuery)->count('journal_entries.id');

        return response()->json([
            'totals' => $totals,
            'data' => $rows,
            'total_rows' => $totalRows,
        ]);
    }

    public function export(ReportGeneralAnalyticRequest $request): StreamedResponse|Response
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $format = strtolower((string) $request->input('format', 'csv'));
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            abort(422, 'Formato solicitado não é suportado.');
        }

        if ($format === 'pdf') {
            if (! $request->filled('date_from') || ! $request->filled('date_to')) {
                abort(422, 'Para exportar em PDF, informe o período inicial e final.');
            }

            $dateFrom = $request->date('date_from');
            $dateTo = $request->date('date_to');
            if ($dateFrom && $dateTo && $dateFrom->diffInDays($dateTo) > 31) {
                abort(422, 'Para exportar em PDF, limite o período a no máximo 31 dias.');
            }
        }

        $query = $this->baseQuery($request);
        $rows = (clone $query)->get()->map(fn (JournalEntry $entry) => $this->transformEntry($entry));
        $totals = $this->calculateTotals(clone $query);

        if ($format === 'pdf') {
            return $this->downloadPdf($rows, $request, $totals);
        }

        return $format === 'xlsx'
            ? $this->downloadXlsx($rows, $request)
            : $this->downloadCsv($rows, $request);
    }

    /**
     * @return Builder<JournalEntry>
     */
    private function baseQuery(ReportGeneralAnalyticRequest $request): Builder
    {
        $accountIds = $this->extractAccountIds($request);
        $dateBasis = $request->input('date_basis', 'movement') === 'due' ? 'due_date' : 'movement_date';

        $query = JournalEntry::query()
            ->operational()
            ->select('journal_entries.*')
            ->with([
                'person:id,nome_razao_social',
                'property',
                'costCenter:id,nome,codigo',
                'bankAccount:id,nome',
                'description:id,texto',
            ])
            ->leftJoin('pessoas as people', 'people.id', '=', 'journal_entries.person_id')
            ->leftJoin('journal_entry_descriptions as descriptions', 'descriptions.id', '=', 'journal_entries.description_id');

        if (! empty($accountIds)) {
            $query->whereIn('journal_entries.bank_account_id', $accountIds);
        }

        $type = $request->input('type');
        if ($type && $type !== 'todos') {
            $query->where('journal_entries.type', $type);
        }

        $status = $request->input('status');
        if ($status && $status !== 'todos') {
            $statusFilter = $status === 'pago'
                ? JournalEntryStatus::filterValues('pago')
                : JournalEntryStatus::filterValues('open');

            $query->whereIn('journal_entries.status', $statusFilter);
        }

        if ($request->filled('date_from')) {
            $query->whereDate("journal_entries.{$dateBasis}", '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate("journal_entries.{$dateBasis}", '<=', $request->date('date_to')->toDateString());
        }

        if ($request->filled('description')) {
            $term = trim((string) $request->input('description'));

            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('journal_entries.description_custom', 'like', "%{$term}%")
                    ->orWhere('descriptions.texto', 'like', "%{$term}%");
            });
        }

        if ($request->filled('person_id')) {
            $query->where('journal_entries.person_id', $request->integer('person_id'));
        }

        if ($request->filled('property_id')) {
            $query->where('journal_entries.property_id', $request->integer('property_id'));
        }

        if ($request->filled('cost_center_id')) {
            $query->where('journal_entries.cost_center_id', $request->integer('cost_center_id'));
        }

        $orderBy = $request->input('order_by', 'movement_date');
        $direction = $request->boolean('order_desc') ? 'desc' : 'asc';

        $query->when($orderBy === 'movement_date', fn (Builder $builder) => $builder->orderBy('journal_entries.movement_date', $direction))
            ->when($orderBy === 'due_date', fn (Builder $builder) => $builder->orderBy('journal_entries.due_date', $direction))
            ->when($orderBy === 'person', fn (Builder $builder) => $builder->orderBy('people.nome_razao_social', $direction))
            ->when(
                $orderBy === 'description',
                fn (Builder $builder) => $builder->orderBy(DB::raw('COALESCE(journal_entries.description_custom, descriptions.texto)'), $direction)
            )
            ->when($orderBy === 'notes', fn (Builder $builder) => $builder->orderBy('journal_entries.notes', $direction))
            ->when($orderBy === 'document', fn (Builder $builder) => $builder->orderBy('journal_entries.reference_code', $direction))
            ->orderBy('journal_entries.id', $direction);

        return $query;
    }

    private function calculateTotals(Builder $query): array
    {
        $totals = (clone $query)
            ->selectRaw("SUM(CASE WHEN journal_entries.type = 'receita' THEN journal_entries.amount ELSE 0 END) as total_inflow")
            ->selectRaw("SUM(CASE WHEN journal_entries.type = 'despesa' THEN journal_entries.amount ELSE 0 END) as total_outflow")
            ->first();

        $inflow = (float) ($totals->total_inflow ?? 0);
        $outflow = (float) ($totals->total_outflow ?? 0);

        return [
            'inflow' => round($inflow, 2),
            'outflow' => round($outflow, 2),
            'net' => round($inflow - $outflow, 2),
        ];
    }

    private function transformEntry(JournalEntry $entry): array
    {
        $typeEnum = $entry->type ? JournalEntryType::tryFrom((string) $entry->type) : null;
        $statusEnum = $entry->status ? JournalEntryStatus::tryFrom((string) $entry->status) : null;
        $signedAmount = $this->resolveSignedAmount($entry);
        $description = $entry->description_custom ?? $entry->description?->texto;

        return [
            'id' => $entry->id,
            'movement_date' => $entry->movement_date?->toDateString(),
            'due_date' => $entry->due_date?->toDateString(),
            'type' => $entry->type,
            'status' => $entry->status,
            'status_label' => $statusEnum ? $statusEnum->label($typeEnum) : null,
            'description' => $description,
            'notes' => $entry->notes,
            'document' => $entry->reference_code,
            'person' => $entry->person ? [
                'id' => $entry->person->id,
                'nome' => $entry->person->nome,
            ] : null,
            'property' => $entry->property ? [
                'id' => $entry->property->id,
                'nome' => $this->resolvePropertyLabel($entry->property),
            ] : null,
            'cost_center' => $entry->costCenter ? [
                'id' => $entry->costCenter->id,
                'nome' => $entry->costCenter->nome,
                'codigo' => $entry->costCenter->codigo,
            ] : null,
            'bank_account' => $entry->bankAccount ? [
                'id' => $entry->bankAccount->id,
                'nome' => $entry->bankAccount->nome,
            ] : null,
            'amount' => round((float) $entry->amount, 2),
            'signed_amount' => round($signedAmount, 2),
        ];
    }

    private function downloadCsv(Collection $rows, ReportGeneralAnalyticRequest $request): StreamedResponse
    {
        $filename = sprintf('relatorio-geral-analitico-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Data movimento',
                'Data vencimento',
                'Tipo',
                'Status',
                'Fornecedor/Cliente',
                'Descrição',
                'Observação',
                'Imóvel',
                'Centro de custo',
                'Conta bancária',
                'Documento',
                'Valor original',
                'Valor assinado',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['movement_date'],
                    $row['due_date'],
                    $row['type'],
                    $row['status_label'],
                    $row['person']['nome'] ?? '',
                    $row['description'],
                    $row['notes'],
                    $row['property']['nome'] ?? '',
                    $row['cost_center']['nome'] ?? '',
                    $row['bank_account']['nome'] ?? '',
                    $row['document'],
                    number_format($row['amount'], 2, '.', ''),
                    number_format($row['signed_amount'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function downloadPdf(Collection $rows, ReportGeneralAnalyticRequest $request, array $totals): Response
    {
        $accountLabel = $this->resolveAccountLabel($request);

        $filters = [
            'type' => $request->input('type', 'todos'),
            'type_label' => $this->formatTypeLabel($request->input('type')),
            'status' => $request->input('status', 'todos'),
            'status_label' => $this->formatStatusLabel($request->input('status')),
            'date_basis' => $request->input('date_basis', 'movement'),
            'date_basis_label' => $this->formatDateBasisLabel($request->input('date_basis')),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'description' => $request->input('description'),
            'person_label' => $this->resolvePersonName($request->input('person_id')),
            'property_label' => $this->resolvePropertyName($request->input('property_id')),
            'cost_center_label' => $this->resolveCostCenterName($request->input('cost_center_id')),
            'account_label' => $accountLabel,
            'order_by' => $request->input('order_by'),
            'order_by_label' => $this->formatOrderByLabel($request->input('order_by')),
            'order_desc' => $request->boolean('order_desc'),
            'generated_at' => now(),
        ];

        $preparedRows = $rows->map(function (array $row) {
            $signed = (float) ($row['signed_amount'] ?? 0);
            $accountName = $row['bank_account']['nome'] ?? null;
            $costCenterName = $row['cost_center']['nome'] ?? null;
            $costCenterCode = $row['cost_center']['codigo'] ?? null;
            $notes = trim((string) ($row['notes'] ?? ''));

            return [
                'movement_date' => $row['movement_date'] ?? null,
                'due_date' => $row['due_date'] ?? null,
                'type_label' => $this->formatTypeLabel($row['type'] ?? null),
                'status_label' => $row['status_label'] ?? null,
                'status' => $row['status'] ?? null,
                'person' => $row['person']['nome'] ?? null,
                'description' => $row['description'] ?? null,
                'notes' => $notes !== '' ? $notes : null,
                'property' => $row['property']['nome'] ?? null,
                'cost_center' => $costCenterName
                    ? trim($costCenterCode ? "{$costCenterCode} • {$costCenterName}" : $costCenterName)
                    : null,
                'account' => $accountName,
                'document' => $row['document'] ?? null,
                'signed_amount' => $signed,
            ];
        });

        $logoPath = base_path('docs/identidade-visual-fortress_3.jpg');
        $logoBase64 = File::exists($logoPath)
            ? 'data:image/jpeg;base64,'.base64_encode(File::get($logoPath))
            : null;

        $user = $request->user();
        $generatedBy = $user?->nome ?? $user?->username ?? $user?->name;

        $data = [
            'account' => ['nome' => $accountLabel],
            'filters' => array_merge($filters, [
                'generated_by' => $generatedBy,
            ]),
            'totals' => $totals,
            'rows' => $preparedRows->toArray(),
            'company' => [
                'name' => config('app.company_name', config('app.name', 'Fortress Empreendimentos')),
            ],
            'logoBase64' => $logoBase64,
        ];

        $filename = sprintf(
            'relatorio-geral-analitico-%s-%s.pdf',
            Str::slug($accountLabel),
            now()->format('Ymd_His')
        );

        $html = view('pdf.general-analytic-report', $data)->render();

        if ($request->boolean('preview')) {
            return response($html);
        }

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function downloadXlsx(Collection $rows, ReportGeneralAnalyticRequest $request): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Relatório Analítico');

        $sheet->fromArray([[
            'Data movimento',
            'Data vencimento',
            'Tipo',
            'Status',
            'Fornecedor/Cliente',
            'Descrição',
            'Observação',
            'Imóvel',
            'Centro de custo',
            'Conta bancária',
            'Documento',
            'Valor original',
            'Valor assinado',
        ]]);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['movement_date'],
                $row['due_date'],
                $row['type'],
                $row['status_label'],
                $row['person']['nome'] ?? '',
                $row['description'],
                $row['notes'],
                $row['property']['nome'] ?? '',
                $row['cost_center']['nome'] ?? '',
                $row['bank_account']['nome'] ?? '',
                $row['document'],
                $row['amount'],
                $row['signed_amount'],
            ]], null, "A{$rowIndex}");
            $rowIndex++;
        }

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        $filename = sprintf('relatorio-geral-analitico-%s.xlsx', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function formatTypeLabel(?string $type): string
    {
        return match ($type) {
            'despesa' => 'Despesa',
            'receita' => 'Receita',
            'transferencia' => 'Transferência',
            'todos', null, '' => 'Todos',
            default => ucfirst((string) $type),
        };
    }

    private function formatStatusLabel(?string $status): string
    {
        return match ($status) {
            'pago' => 'Pago',
            'em_aberto' => 'Em aberto',
            'todos', null, '' => 'Todos',
            default => ucfirst((string) $status),
        };
    }

    private function formatDateBasisLabel(?string $basis): string
    {
        return $basis === 'due' ? 'Data de vencimento' : 'Data de movimento';
    }

    private function formatOrderByLabel(?string $orderBy): string
    {
        return match ($orderBy) {
            'movement_date' => 'Data de movimento',
            'due_date' => 'Data de vencimento',
            'person' => 'Nome do cliente/fornecedor',
            'description' => 'Descrição da despesa/receita',
            'notes' => 'Observação',
            'document' => 'Documento',
            default => $orderBy ? ucfirst(str_replace('_', ' ', $orderBy)) : 'Data de movimento',
        };
    }

    private function resolveAccountLabel(ReportGeneralAnalyticRequest $request): string
    {
        $ids = $this->extractAccountIds($request);

        if ($ids === null || $ids === []) {
            return 'Todos os bancos';
        }

        $names = FinancialAccount::query()
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->pluck('nome')
            ->map(fn ($name) => is_string($name) ? trim($name) : null)
            ->filter()
            ->values();

        if (count($ids) === 1) {
            return $names->first() ?: 'Conta selecionada';
        }

        if ($names->count() > 0 && $names->count() <= 3) {
            return $names->implode(', ');
        }

        return sprintf('%d contas selecionadas', count($ids));
    }

    private function resolvePersonName($id): ?string
    {
        if (empty($id)) {
            return null;
        }

        return Pessoa::query()
            ->whereKey((int) $id)
            ->value('nome_razao_social');
    }

    private function resolvePropertyName($id): ?string
    {
        if (empty($id)) {
            return null;
        }

        $property = Imovel::query()
            ->select(['id', 'codigo', 'logradouro', 'numero', 'bairro', 'cidade'])
            ->find((int) $id);

        return $property ? $this->resolvePropertyLabel($property) : null;
    }

    private function resolveCostCenterName($id): ?string
    {
        if (empty($id)) {
            return null;
        }

        $costCenter = CostCenter::query()
            ->select(['id', 'nome', 'codigo'])
            ->find((int) $id);

        if (! $costCenter) {
            return null;
        }

        return trim($costCenter->codigo ? "{$costCenter->codigo} • {$costCenter->nome}" : $costCenter->nome);
    }

    private function resolveSignedAmount(JournalEntry $entry): float
    {
        $amount = (float) $entry->amount;

        return match ($entry->type) {
            'receita' => $amount,
            'despesa' => -$amount,
            default => $amount,
        };
    }

    /**
     * @return array<int>|null
     */
    private function extractAccountIds(ReportGeneralAnalyticRequest $request): ?array
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

    private function resolvePropertyLabel($property): ?string
    {
        if (! $property) {
            return null;
        }

        if (! empty($property->nome)) {
            return trim((string) $property->nome);
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
}
