<?php

namespace App\Http\Resources\Financeiro;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'valor_formatado' => number_format((float) $this->valor, 2, ',', '.'),
            'data_ocorrencia' => $this->data_ocorrencia?->toDateString(),
            'data_ocorrencia_formatada' => $this->data_ocorrencia?->format('d/m/Y'),
            'descricao' => $this->descricao,
            'status' => $this->status,
            'meta' => $this->meta ?? [],
            'account' => $this->whenLoaded('account', fn () => [
                'id' => $this->account->id,
                'nome' => $this->account->nome,
            ]),
            'cost_center' => $this->whenLoaded('costCenter', fn () => [
                'id' => $this->costCenter->id,
                'nome' => $this->costCenter->nome,
            ]),
            'contrato' => $this->whenLoaded('contrato', fn () => [
                'id' => $this->contrato->id,
                'codigo_contrato' => $this->contrato->codigo_contrato,
            ]),
            'fatura' => $this->whenLoaded('fatura', fn () => [
                'id' => $this->fatura->id,
                'competencia' => $this->fatura->competencia?->toDateString(),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
