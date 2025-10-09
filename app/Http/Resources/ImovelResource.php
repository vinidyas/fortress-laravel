<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImovelResource extends JsonResource
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
            'codigo' => $this->codigo,
            'tipo_imovel' => $this->tipo_imovel,
            'finalidade' => $this->finalidade,
            'disponibilidade' => $this->disponibilidade,
            'enderecos' => [
                'cep' => $this->cep,
                'estado' => $this->estado,
                'cidade' => $this->cidade,
                'bairro' => $this->bairro,
                'rua' => $this->rua,
                'logradouro' => $this->logradouro,
                'numero' => $this->numero,
                'complemento' => $this->complemento,
            ],
            'valores' => [
                'valor_locacao' => $this->valor_locacao,
                'valor_condominio' => $this->valor_condominio,
                'condominio_isento' => $this->condominio_isento,
                'valor_iptu' => $this->valor_iptu,
                'iptu_isento' => $this->iptu_isento,
                'outros_valores' => $this->outros_valores,
                'outros_isento' => $this->outros_isento,
                'periodo_iptu' => $this->periodo_iptu,
            ],
            'caracteristicas' => [
                'dormitorios' => $this->dormitorios,
                'suites' => $this->suites,
                'banheiros' => $this->banheiros,
                'vagas_garagem' => $this->vagas_garagem,
                'area_total' => $this->area_total,
                'area_construida' => $this->area_construida,
                'comodidades' => $this->comodidades,
            ],
            'proprietario' => $this->whenLoaded('proprietario', fn () => [
                'id' => $this->proprietario->id,
                'nome_razao_social' => $this->proprietario->nome_razao_social,
            ]),
            'agenciador' => $this->whenLoaded('agenciador', fn () => [
                'id' => $this->agenciador?->id,
                'nome_razao_social' => $this->agenciador?->nome_razao_social,
            ]),
            'responsavel' => $this->whenLoaded('responsavel', fn () => [
                'id' => $this->responsavel?->id,
                'nome_razao_social' => $this->responsavel?->nome_razao_social,
            ]),
            'condominio' => $this->whenLoaded('condominio', fn () => [
                'id' => $this->condominio?->id,
                'nome' => $this->condominio?->nome,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
