<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportImoveisFilterRequest;
use App\Http\Resources\Reports\ImovelReportResource;
use App\Models\Imovel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReportImoveisController extends Controller
{
    public function index(ReportImoveisFilterRequest $request): JsonResponse
    {
        $imoveis = $this->query($request)
            ->orderBy('condominio_id')
            ->orderBy('codigo')
            ->paginate($request->perPage());

        return ImovelReportResource::collection($imoveis)->response();
    }

    public function export(ReportImoveisFilterRequest $request): Response
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $imoveis = $this->query($request)
            ->orderBy('condominio_id')
            ->orderBy('codigo')
            ->get();

        $payload = $imoveis->map(function (Imovel $imovel) {
            return [
                'label' => $this->formatLabel($imovel),
                'info' => $this->formatInfo($imovel),
                'tipo' => $imovel->tipo_imovel ?? '—',
                'cidade' => $imovel->cidade ?? '—',
                'valor_locacao' => (float) ($imovel->valor_locacao ?? 0),
                'dormitorios' => $imovel->dormitorios ?? 0,
                'vagas' => $imovel->vagas_garagem ?? 0,
                'disponibilidade' => $imovel->disponibilidade ?? '—',
                'area_total' => $imovel->area_total ?? null,
            ];
        });

        $filters = [
            'only_available' => $request->boolean('only_available'),
        ];

        $pdf = Pdf::loadView('pdf.properties-report', [
            'properties' => $payload,
            'filters' => $filters,
            'generated_at' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('relatorio-imoveis-'.now()->format('Ymd_His').'.pdf');
    }

    private function query(ReportImoveisFilterRequest $request)
    {
        return Imovel::query()
            ->with('condominio')
            ->when($request->boolean('only_available'), fn ($q) => $q->where('disponibilidade', 'Disponivel'));
    }

    private function formatLabel(Imovel $imovel): string
    {
        $condominio = trim((string) ($imovel->condominio?->nome ?? ''));
        $complemento = trim((string) ($imovel->complemento ?? ''));

        $base = $condominio !== '' ? $condominio : 'Sem condomínio';

        return $complemento !== '' ? sprintf('%s — %s', $base, $complemento) : $base;
    }

    private function formatInfo(Imovel $imovel): string
    {
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
