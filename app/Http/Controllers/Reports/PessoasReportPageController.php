<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PessoasReportPageController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->hasPermission('reports.view.pessoas')) {
            abort(403);
        }

        return Inertia::render('Relatorios/Pessoas', [
            'canExport' => $request->user()->hasPermission('reports.export'),
        ]);
    }
}
