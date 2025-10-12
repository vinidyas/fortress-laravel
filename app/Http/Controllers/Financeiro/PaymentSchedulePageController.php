<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSchedulePageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PaymentSchedule::class);

        $schedules = PaymentSchedule::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('vencimento')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Financeiro/PaymentSchedules/Index', [
            'schedules' => $schedules->through(fn ($schedule) => [
                'id' => $schedule->id,
                'titulo' => $schedule->titulo,
                'valor_total' => $schedule->valor_total,
                'valor_total_formatado' => number_format((float) $schedule->valor_total, 2, ',', '.'),
                'parcela_atual' => $schedule->parcela_atual,
                'total_parcelas' => $schedule->total_parcelas,
                'vencimento' => optional($schedule->vencimento)->format('d/m/Y'),
                'status' => $schedule->status,
            ]),
            'filters' => [
                'status' => $request->input('status'),
            ],
            'can' => [
                'create' => $request->user()->hasPermission('financeiro.create'),
                'update' => $request->user()->hasPermission('financeiro.update'),
                'delete' => $request->user()->hasPermission('financeiro.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PaymentSchedule::class);

        return Inertia::render('Financeiro/PaymentSchedules/Create', [
            'defaults' => [
                'parcela_atual' => 1,
                'total_parcelas' => 1,
                'status' => 'aberto',
                'vencimento' => now()->toDateString(),
            ],
        ]);
    }
}
