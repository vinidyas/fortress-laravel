<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportOperacionalFilterRequest;
use App\Models\Contrato;
use App\Models\Imovel;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportOperacionalController extends Controller
{
    public function index(ReportOperacionalFilterRequest $request): JsonResponse
    {
        $ocupacao = $this->buildOcupacao($request);
        $contratosVencendo = $this->buildContratosVencendo($request);

        return response()->json([
            'ocupacao' => $ocupacao,
            'contratos_vencendo' => $contratosVencendo,
        ]);
    }

    public function export(ReportOperacionalFilterRequest $request): StreamedResponse
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $format = strtolower((string) $request->input('format', 'csv'));
        if ($format !== 'csv') {
            abort(422, 'Formato solicitado nao e suportado.');
        }

        $dados = $this->buildContratosVencendo($request);
        $filename = 'relatorio-operacional-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($dados) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Contrato', 'Imovel', 'Cidade', 'Status', 'Fim']);

            foreach ($dados as $linha) {
                fputcsv($handle, [
                    $linha['contrato'],
                    $linha['imovel'],
                    $linha['cidade'],
                    $linha['status'],
                    $linha['data_fim'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function buildOcupacao(ReportOperacionalFilterRequest $request): array
    {
        $imoveis = Imovel::query()
            ->when($request->filled('cidade'), fn ($q) => $q->where('cidade', $request->string('cidade')))
            ->when($request->filled('condominio_id'), fn ($q) => $q->where('condominio_id', $request->integer('condominio_id')));

        $total = (clone $imoveis)->count();
        $disponiveis = (clone $imoveis)->where('disponibilidade', 'Disponivel')->count();
        $indisponiveis = $total - $disponiveis;

        return [
            'total' => $total,
            'disponiveis' => $disponiveis,
            'indisponiveis' => $indisponiveis,
            'ocupacao_percentual' => $total > 0 ? round(($indisponiveis / $total) * 100, 2) : 0,
        ];
    }

    private function buildContratosVencendo(ReportOperacionalFilterRequest $request): array
    {
        $limite = $request->date('ate') ?? now()->addMonth();
        $status = $request->input('status_contrato');

        return Contrato::query()
            ->with('imovel')
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', 'Ativo'))
            ->whereBetween('data_fim', [now()->toDateString(), $limite->toDateString()])
            ->get()
            ->map(fn (Contrato $contrato) => [
                'contrato' => $contrato->codigo_contrato,
                'imovel' => $contrato->imovel?->codigo,
                'cidade' => $contrato->imovel?->cidade,
                'status' => $contrato->status?->value,
                'data_fim' => optional($contrato->data_fim)->toDateString(),
            ])
            ->all();
    }
}
