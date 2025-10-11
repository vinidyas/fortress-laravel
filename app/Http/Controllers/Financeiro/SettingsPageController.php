<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Financeiro\CostCenterResource;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;
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
                    'tipo' => $account->tipo,
                    'ativo' => $account->ativo,
                    'saldo_inicial' => $account->saldo_inicial,
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

        $roots = CostCenter::with(['children' => function ($query) {
            $query->with('children')
                ->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)");
        }])
            ->whereNull('parent_id')
            ->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)")
            ->get();

        $centersTree = CostCenterResource::collection($roots)->resolve();

        $parentOptions = CostCenter::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)")])
            ->orderByRaw("CAST(REPLACE(codigo, '.', '') AS UNSIGNED)")
            ->get()
            ->map(fn (CostCenter $center) => [
                'id' => $center->id,
                'nome' => $center->nome,
                'codigo' => $center->codigo,
                'children' => $center->children->map(fn (CostCenter $child) => [
                    'id' => $child->id,
                    'codigo' => $child->codigo,
                ])->values(),
            ])
            ->values();

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
}