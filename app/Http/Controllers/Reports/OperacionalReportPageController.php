<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Condominio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperacionalReportPageController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->hasPermission('reports.view.operacional')) {
            abort(403);
        }

        return Inertia::render('Relatorios/Operacional', [
            'condominios' => Condominio::query()->orderBy('nome')->get(['id', 'nome']),
            'canExport' => $request->user()->hasPermission('reports.export'),
        ]);
    }
}
