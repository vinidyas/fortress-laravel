<?php

namespace App\Http\Controllers\Api\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\PaymentScheduleRequest;
use App\Http\Resources\Financeiro\PaymentScheduleResource;
use App\Models\PaymentSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PaymentScheduleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentSchedule::class);

        $schedules = PaymentSchedule::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('vencimento_de'), fn ($q) => $q->whereDate('vencimento', '>=', $request->date('vencimento_de')->toDateString()))
            ->when($request->filled('vencimento_ate'), fn ($q) => $q->whereDate('vencimento', '<=', $request->date('vencimento_ate')->toDateString()))
            ->orderBy('vencimento')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return PaymentScheduleResource::collection($schedules);
    }

    public function store(PaymentScheduleRequest $request): JsonResponse
    {
        $this->authorize('create', PaymentSchedule::class);

        $schedule = PaymentSchedule::create($request->validated());

        return PaymentScheduleResource::make($schedule)
            ->additional(['message' => 'Agendamento criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PaymentSchedule $paymentSchedule): PaymentScheduleResource
    {
        $this->authorize('view', $paymentSchedule);

        return PaymentScheduleResource::make($paymentSchedule);
    }

    public function update(PaymentScheduleRequest $request, PaymentSchedule $paymentSchedule): PaymentScheduleResource
    {
        $this->authorize('update', $paymentSchedule);

        $paymentSchedule->update($request->validated());

        return PaymentScheduleResource::make($paymentSchedule)->additional([
            'message' => 'Agendamento atualizado com sucesso.',
        ]);
    }

    public function destroy(PaymentSchedule $paymentSchedule): Response
    {
        $this->authorize('delete', $paymentSchedule);

        $paymentSchedule->delete();

        return response()->noContent();
    }
}
