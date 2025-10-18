<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ContratoResource extends JsonResource
{
    /**
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
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'dia_vencimento' => $this->dia_vencimento,
            'prazo_meses' => $this->prazo_meses,
            'carencia_meses' => $this->carencia_meses,
            'data_entrega_chaves' => $this->data_entrega_chaves,
            'valor_aluguel' => $this->valor_aluguel,
            'desconto_mensal' => $this->desconto_mensal,
            'reajuste_indice' => $this->reajuste_indice?->value,
            'reajuste_indice_outro' => $this->reajuste_indice_outro,
            'reajuste_periodicidade_meses' => $this->reajuste_periodicidade_meses,
            'reajuste_teto_percentual' => $this->reajuste_teto_percentual,
            'data_proximo_reajuste' => $this->data_proximo_reajuste,
            'garantia_tipo' => $this->garantia_tipo?->value,
            'caucao_valor' => $this->caucao_valor,
            'taxa_adm_percentual' => $this->taxa_adm_percentual,
            'multa_atraso_percentual' => $this->multa_atraso_percentual,
            'juros_mora_percentual_mes' => $this->juros_mora_percentual_mes,
            'multa_rescisao_alugueis' => $this->multa_rescisao_alugueis,
            'repasse_automatico' => $this->repasse_automatico,
            'conta_cobranca_id' => $this->conta_cobranca_id,
            'forma_pagamento_preferida' => $this->forma_pagamento_preferida?->value,
            'tipo_contrato' => $this->tipo_contrato?->value,
            'status' => $this->status?->value,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'imovel' => $this->whenLoaded('imovel', function () {
                $imovel = $this->imovel;
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
            'locador' => $this->whenLoaded('locador', fn () => [
                'id' => $this->locador->id,
                'nome_razao_social' => $this->locador->nome_razao_social,
            ]),
            'locatario' => $this->whenLoaded('locatario', fn () => [
                'id' => $this->locatario->id,
                'nome_razao_social' => $this->locatario->nome_razao_social,
            ]),
            'fiadores' => $this->whenLoaded('fiadores', fn () => $this->fiadores->map(fn ($fiador) => [
                'id' => $fiador->id,
                'nome_razao_social' => $fiador->nome_razao_social,
            ])->all()),
            'conta_cobranca' => $this->whenLoaded('contaCobranca', fn () => [
                'id' => $this->contaCobranca->id,
                'nome' => $this->contaCobranca->nome,
            ]),
            'anexos' => $this->whenLoaded('anexos', fn () => $this->anexos->map(fn ($anexo) => [
                'id' => $anexo->id,
                'original_name' => $anexo->original_name,
                'mime_type' => $anexo->mime_type,
                'url' => Storage::disk('public')->url($anexo->path),
            ])->all()),
        ];
    }
}
