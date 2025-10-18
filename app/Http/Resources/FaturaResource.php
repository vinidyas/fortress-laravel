<?php

namespace App\Http\Resources;

use App\Services\FaturaEmailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaturaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shouldIncludeEmail = $request->routeIs(
            'faturas.show',
            'faturas.index',
            'faturas.store',
            'faturas.update',
            'faturas.settle',
            'faturas.cancel',
            'faturas.email',
            'faturas.contract-payment-method'
        );

        $emailData = null;

        if ($shouldIncludeEmail || $this->relationLoaded('emailLogs')) {
            $emailService = app(FaturaEmailService::class);
            $emailDefaults = $emailService->buildDefaults($this->resource);
            $lastLog = $this->relationLoaded('emailLogs') ? $this->emailLogs->first() : null;

            $emailData = [
                'defaults' => $emailDefaults,
                'history' => FaturaEmailLogResource::collection($this->whenLoaded('emailLogs')),
                'last_sent_at' => optional($lastLog)->created_at,
                'last_status' => $lastLog?->status,
            ];
        }

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
            'anexos' => FaturaAttachmentResource::collection($this->whenLoaded('anexos')),
            'contrato' => $this->whenLoaded('contrato', function () {
                return [
                    'id' => $this->contrato->id,
                    'codigo_contrato' => $this->contrato->codigo_contrato,
                    'status' => $this->contrato->status,
                    'forma_pagamento_preferida' => $this->contrato->forma_pagamento_preferida?->value,
                    'forma_pagamento_preferida_label' => $this->contrato->forma_pagamento_preferida?->label(),
                    'imovel' => optional($this->contrato->imovel, function ($imovel) {
                        $condominio = $imovel->relationLoaded('condominio') ? $imovel->condominio : null;

                        return [
                            'id' => $imovel->id,
                            'codigo' => $imovel->codigo,
                            'cidade' => $imovel->cidade,
                            'bairro' => $imovel->bairro,
                            'complemento' => $imovel->complemento,
                            'condominio' => $condominio ? [
                                'id' => $condominio->id,
                                'nome' => $condominio->nome,
                            ] : null,
                        ];
                    }),
                ];
            }),
            'itens' => FaturaLancamentoResource::collection($this->whenLoaded('itens')),
            'anexos_count' => $this->anexos_count ?? ($this->relationLoaded('anexos') ? $this->anexos->count() : null),
            'email' => $emailData,
        ];
    }
}
