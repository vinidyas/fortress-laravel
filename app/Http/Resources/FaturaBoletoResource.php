<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaturaBoletoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fatura_id' => $this->fatura_id,
            'bank_code' => $this->bank_code,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'valor' => $this->valor,
            'valor_pago' => $this->valor_pago,
            'vencimento' => $this->vencimento,
            'registrado_em' => $this->registrado_em,
            'liquidado_em' => $this->liquidado_em,
            'linha_digitavel' => $this->linha_digitavel,
            'codigo_barras' => $this->codigo_barras,
            'pdf_url' => $this->pdf_url,
            'external_id' => $this->external_id,
            'nosso_numero' => $this->nosso_numero,
            'document_number' => $this->document_number,
            'last_synced_at' => $this->last_synced_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
