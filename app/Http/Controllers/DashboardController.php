<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Imovel;
use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $metrics = [
            'propertiesTotal' => Imovel::count(),
            'propertiesAvailable' => Imovel::where('disponibilidade', 'Disponivel')->count(),
            'propertiesUnavailable' => Imovel::where('disponibilidade', 'Indisponivel')->count(),
            'activeContracts' => Contrato::where('status', 'Ativo')->count(),
            'contractsExpiringSoon' => Contrato::where('status', 'Ativo')
                ->whereNotNull('data_fim')
                ->whereBetween('data_fim', [$now->copy()->startOfDay(), $now->copy()->addDays(30)])
                ->count(),
            'openInvoices' => Fatura::where('status', 'Aberta')->count(),
            'overdueInvoices' => Fatura::where('status', 'Aberta')
                ->whereDate('vencimento', '<', $now->toDateString())
                ->count(),
            'openAmount' => (float) Fatura::where('status', 'Aberta')->sum('valor_total'),
            'paidThisMonth' => (float) Fatura::where('status', 'Paga')
                ->whereBetween('pago_em', [$startOfMonth, $endOfMonth])
                ->sum('valor_pago'),
        ];

        $expiringContracts = Contrato::query()
            ->with(['imovel'])
            ->where('status', 'Ativo')
            ->whereNotNull('data_fim')
            ->whereBetween('data_fim', [$now->copy()->startOfDay(), $now->copy()->addDays(30)])
            ->orderBy('data_fim')
            ->limit(5)
            ->get()
            ->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'code' => $contrato->codigo_contrato,
                'imovel' => $contrato->imovel?->codigo,
                'endsAt' => optional($contrato->data_fim)?->toDateString(),
                'daysLeft' => $contrato->data_fim
                    ? $now->diffInDays($contrato->data_fim, false)
                    : null,
            ]);

        $openInvoices = Fatura::query()
            ->with(['contrato.imovel'])
            ->where('status', 'Aberta')
            ->orderBy('vencimento')
            ->limit(5)
            ->get()
            ->map(fn (Fatura $fatura) => [
                'id' => $fatura->id,
                'competencia' => optional($fatura->competencia)?->toDateString(),
                'dueDate' => optional($fatura->vencimento)?->toDateString(),
                'contract' => $fatura->contrato?->codigo_contrato,
                'property' => $fatura->contrato?->imovel?->codigo,
                'amount' => (float) $fatura->valor_total,
                'lateDays' => $fatura->vencimento
                    ? $fatura->vencimento->diffInDays($now, false)
                    : null,
            ]);

        $recentPeople = Pessoa::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Pessoa $pessoa) => [
                'id' => $pessoa->id,
                'name' => $pessoa->nome_razao_social,
                'document' => $pessoa->cpf_cnpj,
                'type' => $pessoa->tipo_pessoa,
                'roles' => $pessoa->papeis ?? [],
                'createdAt' => optional($pessoa->created_at)?->toDateTimeString(),
            ]);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'expiringContracts' => $expiringContracts,
            'openInvoices' => $openInvoices,
            'recentPeople' => $recentPeople,
        ]);
    }
}
