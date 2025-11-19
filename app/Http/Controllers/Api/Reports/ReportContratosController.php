<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportContratosFilterRequest;
use App\Http\Resources\Reports\ContratoReportResource;
use App\Models\Contrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReportContratosController extends Controller
{
    public function index(ReportContratosFilterRequest $request): JsonResponse
    {
        $contratos = $this->applyFilters($request)
            ->orderByDesc('data_inicio')
            ->orderBy('codigo_contrato')
            ->paginate($request->perPage());

        return ContratoReportResource::collection($contratos)->response();
    }

    public function export(ReportContratosFilterRequest $request): Response
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $contratos = $this->applyFilters($request)
            ->orderByDesc('data_inicio')
            ->orderBy('codigo_contrato')
            ->get();

        $contractsPayload = $contratos->map(function (Contrato $contrato) {
            return [
                'codigo' => $contrato->codigo_contrato,
                'status' => $contrato->status?->value,
                'imovel_label' => $this->formatImovelLabel($contrato),
                'imovel_info' => $this->formatImovelInfo($contrato),
                'locatario' => $contrato->locatario?->nome_razao_social,
                'data_inicio' => optional($contrato->data_inicio)?->format('m/Y'),
                'data_fim' => optional($contrato->data_fim)?->format('m/Y'),
                'proximo_reajuste' => optional($contrato->data_proximo_reajuste)?->format('m/Y'),
                'valor_aluguel' => (float) $contrato->valor_aluguel,
            ];
        });

        $filters = [
            'only_active' => $request->boolean('only_active'),
            'date_field' => $request->dateField(),
            'date_start' => $request->input('date_start'),
            'date_end' => $request->input('date_end'),
        ];

        $pdf = Pdf::loadView('pdf.contracts-report', [
            'contracts' => $contractsPayload,
            'filters' => $filters,
            'generated_at' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = 'relatorio-contratos-'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    private function applyFilters(ReportContratosFilterRequest $request)
    {
        $field = $request->filterField();

        return Contrato::query()
            ->with(['imovel.condominio', 'locatario'])
            ->when($request->boolean('only_active'), fn ($q) => $q->where('status', 'Ativo'))
            ->when($request->filled('date_start'), fn ($q) => $q->whereDate($field, '>=', $request->date('date_start')))
            ->when($request->filled('date_end'), fn ($q) => $q->whereDate($field, '<=', $request->date('date_end')));
    }

    private function formatImovelLabel(Contrato $contrato): string
    {
        $imovel = $contrato->imovel;
        if (! $imovel) {
            return '—';
        }

        $condominio = trim((string) ($imovel->condominio->nome ?? ''));
        $complemento = trim((string) ($imovel->complemento ?? ''));
        $base = $condominio !== '' ? $condominio : 'Sem condomínio';

        return $complemento !== '' ? sprintf('%s — %s', $base, $complemento) : $base;
    }

    private function formatImovelInfo(Contrato $contrato): string
    {
        $imovel = $contrato->imovel;
        if (! $imovel) {
            return '—';
        }

        $parts = [];

        if (! empty($imovel->codigo)) {
            $parts[] = "Código {$imovel->codigo}";
        }

        if (! empty($imovel->cidade)) {
            $parts[] = $imovel->cidade;
        }

        if (! empty($imovel->bairro)) {
            $parts[] = $imovel->bairro;
        }

        return $parts !== [] ? implode(' • ', $parts) : '—';
    }
}
