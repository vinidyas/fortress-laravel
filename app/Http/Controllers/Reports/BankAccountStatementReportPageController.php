<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountStatementReportPageController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->hasPermission('reports.view.financeiro')) {
            abort(403);
        }

        $accounts = FinancialAccount::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'saldo_inicial', 'data_saldo_inicial', 'ativo']);

        return Inertia::render('Relatorios/BankAccountStatement', [
            'accounts' => $accounts,
            'canUpdateBalance' => $request->user()?->hasPermission('financeiro.update') ?? false,
        ]);
    }
}
