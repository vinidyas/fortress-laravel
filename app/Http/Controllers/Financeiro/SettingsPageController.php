<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\CostCenterResource;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SettingsPageController extends Controller
{
    public function accounts(Request $request): Response
    {
        $this->authorize('viewAny', FinancialAccount::class);

        return Inertia::render('Financeiro/Accounts/Index', [
            'accounts' => FinancialAccount::query()
                ->orderBy('nome')
                ->paginate(15)
                ->through(fn (FinancialAccount $account) => [
                    'id' => $account->id,
                    'nome' => $account->nome,
                    'apelido' => $account->apelido,
                    'tipo' => $account->tipo,
                    'categoria' => $account->categoria,
                    'instituicao' => $account->instituicao,
                    'banco' => $account->banco,
                    'agencia' => $account->agencia,
                    'numero' => $account->numero,
                    'carteira' => $account->carteira,
                    'moeda' => $account->moeda,
                    'ativo' => $account->ativo,
                    'saldo_inicial' => $account->saldo_inicial,
                    'saldo_atual' => $account->saldo_atual,
                    'limite_credito' => $account->limite_credito,
                    'data_saldo_inicial' => $account->data_saldo_inicial?->toDateString(),
                    'permite_transf' => $account->permite_transf,
                    'padrao_recebimento' => $account->padrao_recebimento,
                    'padrao_pagamento' => $account->padrao_pagamento,
                    'observacoes' => $account->observacoes,
                ]),
            'can' => [
                'create' => $request->user()->hasPermission('financeiro.create'),
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
            ],
        ]);
    }

    public function costCenters(Request $request): Response
    {
        $this->authorize('viewAny', CostCenter::class);

        $roots = CostCenter::with('childrenRecursive')
            ->whereNull('parent_id')
            ->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)")
            ->get();

        $centersTree = CostCenterResource::collection($roots)->resolve();

        $parentOptions = $this->flattenCostCenters($roots);

        return Inertia::render('Financeiro/CostCenters/Index', [
            'centers' => $centersTree,
            'parentOptions' => $parentOptions,
            'can' => [
                'create' => $request->user()->hasPermission('financeiro.create'),
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
                'export' => $request->user()->hasPermission('financeiro.export'),
                'import' => $request->user()->hasPermission('financeiro.create'),
            ],
        ]);
    }

    private function flattenCostCenters(Collection $centers, int $depth = 0): array
    {
        return $centers->flatMap(function (CostCenter $center) use ($depth) {
            $children = $center->relationLoaded('childrenRecursive')
                ? $center->childrenRecursive
                : $center->children;

            return collect([
                [
                    'id' => $center->id,
                    'nome' => $center->nome,
                    'codigo' => $center->codigo,
                    'parent_id' => $center->parent_id,
                    'depth' => $depth,
                ],
                ...$this->flattenCostCenters($children, $depth + 1),
            ]);
        })->values()->all();
    }
}
