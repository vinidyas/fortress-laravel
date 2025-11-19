<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContratosReportPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user?->hasPermission('reports.view.operacional') && ! $user?->hasPermission('reports.view.financeiro')) {
            abort(403);
        }

        return Inertia::render('Relatorios/Contratos', [
            'canExport' => $user->hasPermission('reports.export'),
        ]);
    }
}
