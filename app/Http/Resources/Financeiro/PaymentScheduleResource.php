<?php

namespace App\Http\Resources\Financeiro;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'valor_total' => $this->valor_total,
            'valor_total_formatado' => number_format((float) $this->valor_total, 2, ',', '.'),
            'parcela_atual' => $this->parcela_atual,
            'total_parcelas' => $this->total_parcelas,
            'vencimento' => $this->vencimento?->toDateString(),
            'vencimento_formatado' => $this->vencimento?->format('d/m/Y'),
            'status' => $this->status,
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
