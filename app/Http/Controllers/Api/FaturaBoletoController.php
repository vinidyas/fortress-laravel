<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaturaBoletoResource;
use App\Models\Fatura;
use App\Models\FaturaBoleto;
use App\Services\Banking\Bradesco\BradescoApiClient;
use App\Services\Banking\Bradesco\BradescoBoletoGateway;
use App\Services\Banking\Bradesco\GenerateBradescoBoletoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FaturaBoletoController extends Controller
{
    public function index(Fatura $fatura)
    {
        $this->authorize('view', $fatura);

        $fatura->load(['boletos' => fn ($query) => $query->latest('created_at')]);

        return FaturaBoletoResource::collection($fatura->boletos);
    }

    public function store(Request $request, Fatura $fatura, GenerateBradescoBoletoService $service): JsonResponse
    {
        $this->authorize('update', $fatura);

        abort_unless($request->user()?->hasPermission('faturas.boleto.generate'), 403, 'Você não tem permissão para gerar boletos.');

        $payload = $request->validate([
            'pagador' => ['nullable', 'array'],
            'pagador.nome' => ['nullable', 'string', 'max:255'],
            'pagador.documento' => ['nullable', 'string', 'max:50'],
            'pagador.endereco' => ['nullable', 'array'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'numeroDocumento' => ['nullable', 'string', 'max:50'],
            'instrucoes' => ['nullable', 'array'],
            'multa' => ['nullable', 'array'],
            'juros' => ['nullable', 'array'],
        ]);

        $boleto = $service->handle($fatura, $payload);
        $wasCreated = $boleto->wasRecentlyCreated;
        $boleto = $boleto->fresh();

        return (new FaturaBoletoResource($boleto))
            ->additional([
                'meta' => [
                    'message' => $wasCreated
                        ? 'Boleto gerado com sucesso.'
                        : 'Último boleto ativo foi reutilizado.',
                ],
            ])
            ->response()
            ->setStatusCode($wasCreated ? 201 : 200);
    }

    public function show(Fatura $fatura, FaturaBoleto $boleto): FaturaBoletoResource
    {
        $this->authorize('view', $fatura);

        abort_unless($boleto->fatura_id === $fatura->id, 404);

        return new FaturaBoletoResource($boleto);
    }

    public function sync(Fatura $fatura, BradescoBoletoGateway $gateway): JsonResponse
    {
        $this->authorize('view', $fatura);

        $boletos = $fatura->boletos()
            ->where('bank_code', BradescoApiClient::BANK_CODE)
            ->get();

        $synced = 0;

        $refreshed = $boletos->map(function (FaturaBoleto $boleto) use ($gateway, &$synced) {
            try {
                $synced++;

                return $gateway->refreshStatus($boleto)->fresh();
            } catch (\Throwable $exception) {
                Log::warning('[Bradesco] Falha ao sincronizar boleto manualmente', [
                    'fatura_boleto_id' => $boleto->id,
                    'exception' => $exception->getMessage(),
                ]);

                return $boleto;
            }
        });

        return response()->json([
            'data' => FaturaBoletoResource::collection($refreshed)->resolve(),
            'meta' => [
                'message' => $synced > 0
                    ? 'Boletos atualizados com sucesso.'
                    : 'Nenhum boleto Bradesco encontrado para esta fatura.',
                'synced' => $synced,
            ],
        ]);
    }
}
