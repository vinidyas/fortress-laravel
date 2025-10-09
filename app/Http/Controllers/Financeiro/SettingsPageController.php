<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
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
            'accounts' => FinancialAccount::query()->orderBy('nome')->paginate(15)->through(fn ($account) => [
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

        return Inertia::render('Financeiro/CostCenters/Index', [
            'centers' => CostCenter::query()->orderBy('nome')->paginate(15)->through(fn ($center) => [
                'id' => $center->id,
                'nome' => $center->nome,
                'descricao' => $center->descricao,
            ]),
            'can' => [
                'create' => $request->user()->hasPermission('financeiro.create'),
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
            ],
        ]);
    }
}