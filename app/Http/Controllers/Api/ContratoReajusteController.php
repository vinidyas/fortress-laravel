<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ContratoReajusteIndice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contrato\ApplyContratoReajusteRequest;
use App\Http\Resources\ContratoResource;
use App\Models\Contrato;
use App\Models\ContratoReajuste;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContratoReajusteController extends Controller
{
    public function store(ApplyContratoReajusteRequest $request, Contrato $contrato): JsonResponse
    {
        if ($contrato->reajuste_indice === null || $contrato->reajuste_indice === ContratoReajusteIndice::SemReajuste) {
            throw ValidationException::withMessages([
                'percentual' => 'Este contrato não possui reajuste configurado.',
            ]);
        }

        $periodicidade = $contrato->reajuste_periodicidade_meses ?? 0;

        if ($periodicidade <= 0) {
            throw ValidationException::withMessages([
                'percentual' => 'Defina a periodicidade de reajuste antes de aplicar um novo reajuste.',
            ]);
        }

        if (! $contrato->data_proximo_reajuste) {
            throw ValidationException::withMessages([
                'percentual' => 'Defina a data do próximo reajuste antes de aplicar um novo reajuste.',
            ]);
        }

        $baseDate = $contrato->data_proximo_reajuste instanceof Carbon
            ? $contrato->data_proximo_reajuste->copy()
            : Carbon::parse((string) $contrato->data_proximo_reajuste);

        if ($baseDate->isFuture()) {
            throw ValidationException::withMessages([
                'percentual' => 'Ainda não chegou a data configurada para reajustar este contrato.',
            ]);
        }

        $percentual = (float) $request->input('percentual');
        $teto = $contrato->reajuste_teto_percentual;

        if ($teto !== null && $percentual > (float) $teto) {
            throw ValidationException::withMessages([
                'percentual' => 'O percentual informado excede o teto configurado para o contrato.',
            ]);
        }

        $valorAnterior = (float) $contrato->valor_aluguel;

        if ($valorAnterior <= 0) {
            throw ValidationException::withMessages([
                'percentual' => 'Valor do aluguel atual inválido para reajuste.',
            ]);
        }

        $valorCalculado = round($valorAnterior * (1 + $percentual / 100), 2);
        $valorNovo = (float) ($request->input('valor_novo') ?? $valorCalculado);

        if ($valorNovo <= 0) {
            throw ValidationException::withMessages([
                'valor_novo' => 'O valor ajustado deve ser maior que zero.',
            ]);
        }

        $valorReajuste = round($valorNovo - $valorAnterior, 2);
        $proximaData = $baseDate->copy()->addMonths((int) $periodicidade);

        $reajuste = DB::transaction(function () use ($request, $contrato, $percentual, $valorAnterior, $valorNovo, $valorReajuste, $proximaData, $baseDate) {
            $contrato->forceFill([
                'valor_aluguel' => $valorNovo,
                'data_proximo_reajuste' => $proximaData->toDateString(),
            ])->save();

            /** @var \App\Models\User|null $user */
            $user = $request->user();

            return $contrato->reajustes()->create([
                'usuario_id' => $user?->id,
                'indice' => $contrato->reajuste_indice?->value,
                'percentual_aplicado' => $percentual,
                'valor_anterior' => $valorAnterior,
                'valor_novo' => $valorNovo,
                'valor_reajuste' => $valorReajuste,
                'teto_percentual' => $contrato->reajuste_teto_percentual,
                'data_base_reajuste' => $baseDate->toDateString(),
                'data_proximo_reajuste_anterior' => $baseDate->toDateString(),
                'data_proximo_reajuste_novo' => $proximaData->toDateString(),
                'observacoes' => $request->input('observacoes'),
            ]);
        });

        $contrato->load([
            'imovel.condominio',
            'locador',
            'locatario',
            'fiadores',
            'contaCobranca',
            'anexos',
            'reajustes.usuario',
        ]);

        return response()->json([
            'message' => 'Reajuste aplicado com sucesso.',
            'reajuste' => $reajuste,
            'contrato' => new ContratoResource($contrato),
        ]);
    }
}
