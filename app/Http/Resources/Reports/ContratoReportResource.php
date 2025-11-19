<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo_contrato,
            'status' => $this->status?->value,
            'data_inicio' => optional($this->data_inicio)?->toDateString(),
            'data_fim' => optional($this->data_fim)?->toDateString(),
            'dia_vencimento' => $this->dia_vencimento,
            'valor_aluguel' => (float) $this->valor_aluguel,
            'prazo_meses' => $this->prazo_meses,
            'carencia_meses' => $this->carencia_meses,
            'proximo_reajuste' => optional($this->data_proximo_reajuste)?->toDateString(),
            'imovel' => $this->whenLoaded('imovel', function () {
                return [
                    'codigo' => $this->imovel?->codigo,
                    'cidade' => $this->imovel?->cidade,
                    'bairro' => $this->imovel?->bairro,
                    'complemento' => $this->imovel?->complemento,
                    'condominio' => $this->imovel?->condominio?->nome,
                ];
            }),
            'locador' => $this->whenLoaded('locador', fn () => $this->locador?->nome_razao_social),
            'locatario' => $this->whenLoaded('locatario', fn () => $this->locatario?->nome_razao_social),
            'link' => route('contratos.show', $this->id),
        ];
    }
}
