<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $pessoaId = $user?->pessoa_id;

        $contratos = Contrato::query()
            ->with([
                'imovel:id,codigo,rua,numero,bairro,cidade,estado,tipo_imovel',
                'locador:id,nome_razao_social,cpf_cnpj',
            ])
            ->where('locatario_id', $pessoaId)
            ->orderByDesc('data_inicio')
            ->get()
            ->map(function (Contrato $contrato) {
                return [
                    'id' => $contrato->id,
                    'codigo' => $contrato->codigo_contrato,
                    'status' => $contrato->status?->value ?? (string) $contrato->status,
                    'valor_aluguel' => (float) $contrato->valor_aluguel,
                    'dia_vencimento' => $contrato->dia_vencimento,
                    'data_inicio' => optional($contrato->data_inicio)->toDateString(),
                    'data_fim' => optional($contrato->data_fim)->toDateString(),
                    'imovel' => [
                        'codigo' => $contrato->imovel?->codigo,
                        'tipo' => $contrato->imovel?->tipo_imovel,
                        'endereco' => trim(sprintf(
                            '%s, %s - %s, %s/%s',
                            $contrato->imovel?->rua,
                            $contrato->imovel?->numero,
                            $contrato->imovel?->bairro,
                            $contrato->imovel?->cidade,
                            $contrato->imovel?->estado
                        )),
                    ],
                    'locador' => [
                        'nome' => $contrato->locador?->nome_razao_social,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'data' => $contratos,
        ]);
    }
}
