<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\Imovel;
use App\Models\Pessoa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralAnalyticReportPageController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->hasPermission('reports.view.financeiro')) {
            abort(403);
        }

        return Inertia::render('Relatorios/GeneralAnalytic', [
            'accounts' => FinancialAccount::query()
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'people' => Pessoa::query()
                ->orderBy('nome_razao_social')
                ->get(['id', 'nome_razao_social'])
                ->map(fn (Pessoa $person) => [
                    'id' => $person->id,
                    'nome' => $person->nome,
                ])
                ->values(),
            'properties' => Imovel::query()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'logradouro', 'numero', 'bairro', 'cidade'])
                ->map(fn (Imovel $property) => [
                    'id' => $property->id,
                    'label' => $this->resolvePropertyLabel($property),
                ])
                ->values(),
            'costCenters' => CostCenter::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'codigo'])
                ->map(fn (CostCenter $center) => [
                    'id' => $center->id,
                    'nome' => $center->nome,
                    'codigo' => $center->codigo,
                    'label' => trim($center->codigo ? "{$center->codigo} • {$center->nome}" : $center->nome),
                ])
                ->values(),
            'canExport' => $request->user()->hasPermission('reports.export'),
        ]);
    }

    private function resolvePropertyLabel(Imovel $property): string
    {
        $segments = [];

        if (! empty($property->codigo)) {
            $segments[] = trim((string) $property->codigo);
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

        $label = trim(implode(' • ', array_filter($segments)));

        return $label !== '' ? $label : sprintf('Imóvel #%d', $property->id);
    }
}
