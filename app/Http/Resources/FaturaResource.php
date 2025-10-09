<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaturaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contrato_id' => $this->contrato_id,
            'competencia' => $this->competencia,
            'vencimento' => $this->vencimento,
            'status' => $this->status,
            'valor_total' => $this->valor_total,
            'valor_pago' => $this->valor_pago,
            'pago_em' => $this->pago_em,
            'metodo_pagamento' => $this->metodo_pagamento,
            'nosso_numero' => $this->nosso_numero,
            'boleto_url' => $this->boleto_url,
            'pix_qrcode' => $this->pix_qrcode,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'contrato' => $this->whenLoaded('contrato', function () {
                return [
                    'id' => $this->contrato->id,
                    'codigo_contrato' => $this->contrato->codigo_contrato,
                    'status' => $this->contrato->status,
                    'imovel' => optional($this->contrato->imovel, function ($imovel) {
                        return [
                            'id' => $imovel->id,
                            'codigo' => $imovel->codigo,
                            'cidade' => $imovel->cidade,
                            'bairro' => $imovel->bairro,
                        ];
                    }),
                ];
            }),
            'itens' => FaturaLancamentoResource::collection($this->whenLoaded('itens')),
        ];
    }
}
