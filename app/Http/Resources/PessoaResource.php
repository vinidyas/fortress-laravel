<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PessoaResource extends JsonResource
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
            'nome_razao_social' => $this->nome_razao_social,
            'cpf_cnpj' => $this->cpf_cnpj,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'tipo_pessoa' => $this->tipo_pessoa,
            'papeis' => $this->papeis ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
