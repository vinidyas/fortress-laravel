<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_contrato' => $this->codigo_contrato,
            'imovel_id' => $this->imovel_id,
            'locador_id' => $this->locador_id,
            'locatario_id' => $this->locatario_id,
            'fiador_id' => $this->fiador_id,
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'dia_vencimento' => $this->dia_vencimento,
            'valor_aluguel' => $this->valor_aluguel,
            'reajuste_indice' => $this->reajuste_indice,
            'data_proximo_reajuste' => $this->data_proximo_reajuste,
            'garantia_tipo' => $this->garantia_tipo,
            'caucao_valor' => $this->caucao_valor,
            'taxa_adm_percentual' => $this->taxa_adm_percentual,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'imovel' => $this->whenLoaded('imovel', fn () => [
                'id' => $this->imovel->id,
                'codigo' => $this->imovel->codigo,
                'cidade' => $this->imovel->cidade,
                'bairro' => $this->imovel->bairro,
            ]),
            'locador' => $this->whenLoaded('locador', fn () => [
                'id' => $this->locador->id,
                'nome_razao_social' => $this->locador->nome_razao_social,
            ]),
            'locatario' => $this->whenLoaded('locatario', fn () => [
                'id' => $this->locatario->id,
                'nome_razao_social' => $this->locatario->nome_razao_social,
            ]),
            'fiador' => $this->whenLoaded('fiador', fn () => [
                'id' => $this->fiador?->id,
                'nome_razao_social' => $this->fiador?->nome_razao_social,
            ]),
        ];
    }
}
