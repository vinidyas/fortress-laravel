<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportPessoasFilterRequest;
use App\Models\Pessoa;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPessoasController extends Controller
{
    public function index(ReportPessoasFilterRequest $request): JsonResponse
    {
        $query = Pessoa::query()
            ->when($request->filled('papel'), fn ($q) => $q->whereJsonContains('papeis', $request->string('papel')))
            ->when($request->filled('tipo_pessoa'), fn ($q) => $q->where('tipo_pessoa', $request->string('tipo_pessoa')));

        $total = (clone $query)->count();
        $porTipo = (clone $query)
            ->selectRaw('tipo_pessoa, count(*) as total')
            ->groupBy('tipo_pessoa')
            ->pluck('total', 'tipo_pessoa');

        return response()->json([
            'total' => $total,
            'por_tipo' => $porTipo,
            'amostra' => $query->limit(20)->get(['id', 'nome_razao_social', 'tipo_pessoa', 'papeis']),
        ]);
    }

    public function export(ReportPessoasFilterRequest $request): StreamedResponse
    {
        if (! $request->user()?->hasPermission('reports.export')) {
            abort(403);
        }

        $format = strtolower((string) $request->input('format', 'csv'));
        if ($format !== 'csv') {
            abort(422, 'Formato solicitado nao e suportado.');
        }

        $rows = Pessoa::query()
            ->when($request->filled('papel'), fn ($q) => $q->whereJsonContains('papeis', $request->string('papel')))
            ->when($request->filled('tipo_pessoa'), fn ($q) => $q->where('tipo_pessoa', $request->string('tipo_pessoa')))
            ->orderBy('nome_razao_social')
            ->get();

        $filename = 'relatorio-pessoas-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Nome', 'Tipo', 'Papeis']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->nome_razao_social,
                    $row->tipo_pessoa,
                    implode(', ', $row->papeis ?? []),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
