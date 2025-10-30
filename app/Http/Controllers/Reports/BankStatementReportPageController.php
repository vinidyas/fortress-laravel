<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankStatementReportPageController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->hasPermission('reports.view.financeiro')) {
            abort(403);
        }

        return Inertia::render('Relatorios/BankStatements', [
            'accounts' => FinancialAccount::query()->orderBy('nome')->get(['id', 'nome']),
            'canExport' => $request->user()->hasPermission('reports.export'),
        ]);
    }
}
